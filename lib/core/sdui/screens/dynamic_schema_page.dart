import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../config/theme_provider.dart';
import '../../services/dynamic_string_service.dart';
import '../dynamic_schema_context.dart';
import '../dynamic_schema_parser.dart';
import '../schema_cache.dart';
import '../sdui_action_dispatcher.dart';
import '../sdui_icon_registry.dart';

typedef DynamicSchemaRequest = Future<Map<String, dynamic>> Function(
  String endpoint, {
  required String method,
  Map<String, dynamic>? data,
});

/// Universal declarative schema page renderer.
///
/// Accepts a JSON layout definition from the server (either via live API fetch
/// or pre-loaded JSON) and renders the screen automatically without requiring
/// a compiled Flutter class for each page.
class DynamicSchemaPage extends StatefulWidget {
  const DynamicSchemaPage({
    super.key,
    this.endpoint,
    this.schema,
    String? initialTitle,
    String? title,
    this.arguments,
    this.apiClient,
    this.requestExecutor,
    this.embedded = false,
  }) : initialTitle = initialTitle ?? title;

  final String? endpoint;
  final Map<String, dynamic>? schema;
  final String? initialTitle;
  final Map<String, dynamic>? arguments;
  final ApiClient? apiClient;
  final DynamicSchemaRequest? requestExecutor;
  final bool embedded;

  @override
  State<DynamicSchemaPage> createState() => _DynamicSchemaPageState();
}

class _DynamicSchemaPageState extends State<DynamicSchemaPage> {
  static const int _supportedSchemaVersion = 1;

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  bool _isLoading = false;
  String? _errorMessage;
  Map<String, dynamic>? _schema;

  /// True when [_schema] came from [SchemaCache] because the live fetch failed
  /// — drives the "showing the last loaded version" banner.
  bool _schemaFromCache = false;
  DateTime? _schemaCachedAt;
  bool _offlineBannerDismissed = false;

  final Map<String, dynamic> _formValues = {};

  @override
  void initState() {
    super.initState();
    if (widget.schema != null) {
      _loadSchema(widget.schema!);
    } else if (widget.endpoint != null && widget.endpoint!.isNotEmpty) {
      _fetchSchema();
    }
  }

  @override
  void didUpdateWidget(covariant DynamicSchemaPage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.schema != oldWidget.schema && widget.schema != null) {
      setState(() => _loadSchema(widget.schema!));
    } else if (widget.endpoint != oldWidget.endpoint &&
        widget.endpoint != null &&
        widget.endpoint!.isNotEmpty) {
      _fetchSchema();
    }
  }

  ApiClient? _resolveApiClient() {
    if (widget.apiClient != null) return widget.apiClient;
    try {
      return context.read<ApiClient>();
    } catch (_) {
      return null;
    }
  }

  Future<Map<String, dynamic>> _request(
    String endpoint, {
    required String method,
    Map<String, dynamic>? data,
  }) {
    final executor = widget.requestExecutor;
    if (executor != null) {
      return executor(endpoint, method: method, data: data);
    }

    final client = _resolveApiClient();
    if (client == null) {
      throw ApiException('API client is unavailable.');
    }

    return client.requestAbsolute(endpoint, method: method, data: data);
  }

  Future<void> _fetchSchema() async {
    if (widget.endpoint == null) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final res = await _request(widget.endpoint!, method: 'GET');
      final rawSchema = res['schema'] ?? res;
      final Map<String, dynamic> schemaMap = rawSchema is Map<String, dynamic>
          ? rawSchema
          : rawSchema is Map
              ? Map<String, dynamic>.from(rawSchema)
              : throw const FormatException(
                  'The server did not return an SDUI schema.');

      _loadSchema(schemaMap);
      // Keep the last good layout so this screen still opens offline.
      unawaited(SchemaCache.instance.put(widget.endpoint!, schemaMap));
      if (mounted) {
        setState(() {
          _isLoading = false;
          _schemaFromCache = false;
          _schemaCachedAt = null;
        });
      }
    } catch (e) {
      // A transport failure (offline / server unreachable) falls back to the
      // last cached copy of this screen; a real error (4xx, bad schema) is
      // surfaced as before.
      final isTransport = e is ApiException &&
          (e.statusCode == null || (e.statusCode ?? 0) >= 500);
      if (isTransport) {
        final restored = await _loadFromCache()
            .timeout(const Duration(seconds: 2), onTimeout: () => false);
        if (restored) return;
      }

      if (mounted) {
        setState(() {
          _isLoading = false;
          _errorMessage = e is ApiException ? e.message : e.toString();
        });
      }
    }
  }

  /// Renders this screen from [SchemaCache] when the live fetch can't reach
  /// the server. Returns false (leaving the caller to show its error) when
  /// nothing was ever cached for this endpoint.
  Future<bool> _loadFromCache() async {
    if (widget.endpoint == null) return false;
    try {
      final cached = await SchemaCache.instance.get(widget.endpoint!);
      if (cached == null) return false;
      _loadSchema(cached.schema);
      if (mounted) {
        setState(() {
          _isLoading = false;
          _errorMessage = null;
          _schemaFromCache = true;
          _schemaCachedAt = cached.cachedAt;
          _offlineBannerDismissed = false;
        });
      }
      return true;
    } catch (_) {
      return false;
    }
  }

  void _loadSchema(Map<String, dynamic> schema) {
    final version = (schema['schema_version'] as num?)?.toInt() ?? 1;
    if (version > _supportedSchemaVersion) {
      throw FormatException(
        'This screen requires SDUI schema version $version; '
        'this client supports version $_supportedSchemaVersion.',
      );
    }

    if (schema['components'] is! List) {
      throw const FormatException(
          'Invalid SDUI schema: components must be a list.');
    }

    _formValues.clear();
    _schema = schema;
    _collectInitialValues(_schema!);
  }

  void _collectInitialValues(Map<String, dynamic> node) {
    final name = node['name']?.toString();
    if (name != null && name.isNotEmpty && node.containsKey('initial_value')) {
      _formValues[name] = node['initial_value'];
    }

    final children = node['components'] ??
        node['children'] ??
        node['child'] ??
        node['tabs'] ??
        node['steps'];
    if (children is List) {
      for (final child in children) {
        if (child is Map<String, dynamic>) {
          _collectInitialValues(child);
        } else if (child is Map) {
          _collectInitialValues(Map<String, dynamic>.from(child));
        }
      }
    }
  }

  void _setFormValue(String key, dynamic value) {
    _formValues[key] = value;
  }

  SduiActionDispatcher get _dispatcher => SduiActionDispatcher(
        resolveApiClient: _resolveApiClient,
        requestExecutor: widget.requestExecutor,
        formKey: _formKey,
        formValues: _formValues,
        setFormValue: _setFormValue,
        onReload: () {
          if (mounted) _fetchSchema();
        },
        showToast: _showToast,
      );

  Future<void> _dispatchAction(Map<String, dynamic> action) {
    return _dispatcher.dispatch(context, action);
  }

  void _showToast(String message, {bool isError = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.red.shade700 : Colors.green.shade700,
        duration: const Duration(seconds: 3),
      ),
    );
  }

  Widget _buildAppBarAction(Map<String, dynamic> config) {
    final type = config['type']?.toString().toLowerCase().trim();

    if (type == 'theme_selector_dropdown') {
      final current = context.watch<ThemeProvider>().themeMode;
      return PopupMenuButton<ThemeMode>(
        tooltip: 'Theme',
        initialValue: current,
        icon: Icon(current == ThemeMode.dark
            ? Icons.dark_mode_outlined
            : current == ThemeMode.light
                ? Icons.light_mode_outlined
                : Icons.brightness_auto_outlined),
        onSelected: (mode) => context.read<ThemeProvider>().setThemeMode(mode),
        itemBuilder: (_) => const [
          PopupMenuItem(value: ThemeMode.system, child: Text('Match device')),
          PopupMenuItem(value: ThemeMode.light, child: Text('Light')),
          PopupMenuItem(value: ThemeMode.dark, child: Text('Dark')),
        ],
      );
    }

    final rawAction = config['action'];
    final action = rawAction is Map
        ? Map<String, dynamic>.from(rawAction)
        : <String, dynamic>{
            if (config['action_type'] != null) 'type': config['action_type'],
          };
    final icon = Icon(SduiIconRegistry.resolve(config['icon']?.toString()));
    final badgeCount = (config['badge_count'] as num?)?.toInt() ?? 0;
    final iconWidget = type == 'notification_bell' && badgeCount > 0
        ? Badge.count(count: badgeCount, child: icon)
        : icon;

    return IconButton(
      icon: iconWidget,
      tooltip: config['label'] == null
          ? (type == 'notification_bell' ? 'Notifications' : null)
          : context.tr(config['label'].toString()),
      onPressed: action.isEmpty ? null : () => _dispatchAction(action),
    );
  }

  @override
  Widget build(BuildContext context) {
    final client = _resolveApiClient();
    if (widget.embedded) {
      return DynamicSchemaContext(
        formValues: _formValues,
        setFormValue: _setFormValue,
        dispatchAction: _dispatchAction,
        apiClient: client,
        child: Builder(builder: _buildBody),
      );
    }

    final appBarConfig = _schema?['app_bar'] as Map<String, dynamic>?;
    final rawTitle = _schema?['title']?.toString() ??
        appBarConfig?['title']?.toString() ??
        widget.initialTitle;

    String displayTitle = 'Screen';
    if (rawTitle != null && rawTitle.trim().isNotEmpty) {
      final trimmed = rawTitle.trim();
      if (trimmed.contains('_') ||
          (trimmed.contains('-') && !trimmed.contains(' '))) {
        displayTitle = trimmed
            .replaceAll('-', ' ')
            .replaceAll('_', ' ')
            .split(' ')
            .where((w) => w.isNotEmpty)
            .map((w) => '${w[0].toUpperCase()}${w.substring(1)}')
            .join(' ');
      } else {
        displayTitle = trimmed;
      }
    }

    final showBackButton = appBarConfig?['show_back_button'] != false;
    final rawFab = _schema?['fab'] ??
        _schema?['floating_action_button'] ??
        _schema?['fab_action'];
    final fabConfig = rawFab is Map ? Map<String, dynamic>.from(rawFab) : null;

    return DynamicSchemaContext(
      formValues: _formValues,
      setFormValue: _setFormValue,
      dispatchAction: _dispatchAction,
      apiClient: client,
      child: Scaffold(
        appBar: AppBar(
          title: Text(context.tr(displayTitle)),
          automaticallyImplyLeading: showBackButton,
          actions: [
            if (appBarConfig?['actions'] is List)
              for (final act in appBarConfig!['actions'])
                if (act is Map)
                  _buildAppBarAction(Map<String, dynamic>.from(act)),
          ],
        ),
        body: Builder(
          builder: (bodyContext) => _buildBody(bodyContext),
        ),
        floatingActionButton: fabConfig != null
            ? Builder(
                builder: (fabContext) =>
                    DynamicSchemaParser.buildComponent(fabContext, fabConfig),
              )
            : null,
      ),
    );
  }

  Widget _buildBody(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_errorMessage != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const SizedBox(height: 16),
              Text(
                _errorMessage!,
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 14),
              ),
              const SizedBox(height: 16),
              ElevatedButton.icon(
                onPressed: _fetchSchema,
                icon: const Icon(Icons.refresh),
                label: Text(context.tr('Retry')),
              ),
            ],
          ),
        ),
      );
    }

    if (_schema == null) {
      return const SizedBox.shrink();
    }

    final layout =
        _schema!['layout']?.toString().toLowerCase().trim() ?? 'scroll_view';
    final components = _schema!['components'] as List<dynamic>? ?? const [];

    // Keep the last field / submit button reachable above the on-screen
    // keyboard, with extra clearance for any floating action bar.
    final scrollPadding = EdgeInsets.fromLTRB(
        16, 16, 16, MediaQuery.of(context).viewInsets.bottom + 80);

    Widget content;
    switch (layout) {
      case 'column':
        content = SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: scrollPadding,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: DynamicSchemaParser.buildChildren(context, components),
          ),
        );
        break;
      case 'grid':
      case 'grid_view':
        content = SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: scrollPadding,
          child: DynamicSchemaParser.buildComponent(context, {
            'type': 'grid_view',
            'cross_axis_count': _schema!['cross_axis_count'] ?? 2,
            'spacing': _schema!['spacing'] ?? 12,
            'run_spacing': _schema!['run_spacing'] ?? 12,
            'components': components,
          }),
        );
        break;
      case 'tabs':
        content = DynamicSchemaParser.buildComponent(context, {
          'type': 'tabs',
          'tabs': _schema!['tabs'] ?? components,
          'initial_index': _schema!['initial_index'],
          'is_scrollable': _schema!['is_scrollable'],
        });
        break;
      case 'scroll_view':
      default:
        content = ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: scrollPadding,
          children: DynamicSchemaParser.buildChildren(context, components),
        );
        break;
    }

    return Form(
      key: _formKey,
      child: _schemaFromCache && !_offlineBannerDismissed
          ? Column(
              children: [
                _OfflineSchemaBanner(
                  cachedAt: _schemaCachedAt,
                  onDismiss: () =>
                      setState(() => _offlineBannerDismissed = true),
                  onRetry: _fetchSchema,
                ),
                Expanded(child: content),
              ],
            )
          : content,
    );
  }
}

/// Shown above a schema page whose layout was restored from [SchemaCache]
/// because the live fetch failed. Non-blocking — the screen underneath stays
/// interactive (writes queue offline via [SduiActionDispatcher]).
class _OfflineSchemaBanner extends StatelessWidget {
  const _OfflineSchemaBanner({
    required this.cachedAt,
    required this.onDismiss,
    required this.onRetry,
  });

  final DateTime? cachedAt;
  final VoidCallback onDismiss;
  final VoidCallback onRetry;

  String get _age {
    final at = cachedAt;
    if (at == null) return '';
    final d = DateTime.now().difference(at);
    if (d.inMinutes < 1) return ' · just now';
    if (d.inMinutes < 60) return ' · ${d.inMinutes}m ago';
    if (d.inHours < 24) return ' · ${d.inHours}h ago';
    return ' · ${d.inDays}d ago';
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Material(
      color: scheme.secondaryContainer,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 4, 8),
        child: Row(
          children: [
            Icon(Icons.cloud_off, size: 18, color: scheme.onSecondaryContainer),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Offline — showing the last loaded version$_age',
                style: TextStyle(
                    fontSize: 12.5, color: scheme.onSecondaryContainer),
              ),
            ),
            TextButton(onPressed: onRetry, child: const Text('Retry')),
            IconButton(
              icon: const Icon(Icons.close, size: 18),
              tooltip: 'Dismiss',
              onPressed: onDismiss,
            ),
          ],
        ),
      ),
    );
  }
}
