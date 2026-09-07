import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../services/dynamic_string_service.dart';
import '../dynamic_schema_context.dart';
import '../dynamic_schema_parser.dart';
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
      if (rawSchema is Map<String, dynamic>) {
        _loadSchema(rawSchema);
      } else if (rawSchema is Map) {
        _loadSchema(Map<String, dynamic>.from(rawSchema));
      } else {
        throw const FormatException(
            'The server did not return an SDUI schema.');
      }
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _errorMessage = e is ApiException ? e.message : e.toString();
        });
      }
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
    final rawTitle =
        _schema?['title']?.toString() ??
        appBarConfig?['title']?.toString() ??
        widget.initialTitle;

    String displayTitle = 'Screen';
    if (rawTitle != null && rawTitle.trim().isNotEmpty) {
      final trimmed = rawTitle.trim();
      if (trimmed.contains('_') || (trimmed.contains('-') && !trimmed.contains(' '))) {
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
    final fabConfig = _schema?['fab'] as Map<String, dynamic>?;

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
                  IconButton(
                    icon:
                        Icon(SduiIconRegistry.resolve(act['icon']?.toString())),
                    tooltip: act['label'] == null
                        ? null
                        : context.tr(act['label'].toString()),
                    onPressed: () {
                      final a = act['action'] as Map<String, dynamic>?;
                      if (a != null) _dispatchAction(a);
                    },
                  ),
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
      child: content,
    );
  }
}
