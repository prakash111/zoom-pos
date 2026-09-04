import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/config/bootstrap_cache.dart';
import '../../core/config/locale_provider.dart';
import '../../core/models/user_model.dart';
import '../../core/sdui/models/sdui_models.dart';
import '../../core/sdui/screens/dynamic_schema_page.dart';
import '../../core/sdui/sdui_icon_registry.dart';
import '../../core/services/dynamic_string_service.dart';
import '../auth/auth_provider.dart';

class _ShellItem {
  const _ShellItem(this.schema, this.parentKey, this.order);

  final SduiNavItemSchema schema;
  final String? parentKey;
  final int order;
}

class _ShellSection {
  const _ShellSection(this.schema, this.items, this.parentByKey);

  final SduiNavSectionSchema schema;
  final List<_ShellItem> items;
  final Map<String, String?> parentByKey;
}

/// Authenticated application chrome. Business content and destinations are
/// always fetched as schemas; this class only renders navigation primitives.
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final _scaffoldKey = GlobalKey<ScaffoldState>();
  String _endpoint = '/api/tenant/views/dashboard';
  String? _title;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<LocaleProvider>().refreshFromServer();
    });
  }

  bool _canOpen(UserModel? user, SduiNavItemSchema item) {
    final permission = item.permission?.trim();
    if (permission == null || permission.isEmpty || user == null) return true;
    return user.can(permission.contains('.') ? permission : '$permission.view');
  }

  List<_ShellSection> _sections(BootstrapCache bootstrap, UserModel? user) {
    final configByKey = {
      for (final item in bootstrap.navConfig.items) item.key: item
    };
    final sectionByKey = {
      for (final section in bootstrap.effectiveSections) section.key: section,
    };
    final grouped = <String, List<_ShellItem>>{};
    final seen = <String>{};
    var fallbackOrder = 0;

    void collect(
      SduiNavSectionSchema section,
      Iterable<SduiNavItemSchema> items,
      String? inheritedParent,
    ) {
      for (final item in items) {
        if (!seen.add(item.key)) continue;
        final override = configByKey[item.key];
        if (override?.visible == false || !_canOpen(user, item)) continue;
        final targetSection = override?.section?.isNotEmpty == true &&
                sectionByKey.containsKey(override!.section)
            ? override.section!
            : section.key;
        final parent =
            override?.parentId ?? item.effectiveParentId ?? inheritedParent;
        (grouped[targetSection] ??= []).add(_ShellItem(
          item,
          parent,
          override?.order ?? fallbackOrder++,
        ));
        collect(section, item.children, item.key);
      }
    }

    for (final section in bootstrap.effectiveSections) {
      collect(section, section.items, null);
    }

    final result = <_ShellSection>[];
    for (final entry in grouped.entries) {
      final rows = entry.value;
      final keys = {for (final row in rows) row.schema.key};
      final parentByKey = <String, String?>{
        for (final row in rows)
          row.schema.key: row.parentKey != null && keys.contains(row.parentKey)
              ? row.parentKey
              : null,
      };
      rows.sort((a, b) => a.order.compareTo(b.order));
      result.add(_ShellSection(sectionByKey[entry.key]!, rows, parentByKey));
    }

    final explicitOrder = {
      for (final section in bootstrap.navConfig.sections)
        section.key: section.order,
    };
    final fallbackSectionOrder = {
      for (var i = 0; i < bootstrap.effectiveSections.length; i++)
        bootstrap.effectiveSections[i].key: i,
    };
    result.sort((a, b) =>
        (explicitOrder[a.schema.key] ?? fallbackSectionOrder[a.schema.key] ?? 0)
            .compareTo(explicitOrder[b.schema.key] ??
                fallbackSectionOrder[b.schema.key] ??
                0));
    return result;
  }

  void _open(SduiNavItemSchema item) {
    final endpoint = item.targetEndpoint?.isNotEmpty == true
        ? item.targetEndpoint!
        : '/api/tenant/views/${item.key.replaceAll('_', '-')}';
    Navigator.of(context).pop();
    setState(() {
      _endpoint = endpoint;
      _title = context.tr(item.title);
    });
  }

  Future<void> _confirmLogout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.tr('Sign out?')),
        content: Text(context.tr("You'll need your password to sign back in.")),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(context.tr('Cancel')),
          ),
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(context.tr('Sign out')),
          ),
        ],
      ),
    );
    if (confirmed == true && mounted)
      await context.read<AuthProvider>().logout();
  }

  Widget _drawer(
      BuildContext context, BootstrapCache bootstrap, AuthProvider auth) {
    final children = <Widget>[
      DrawerHeader(
        decoration: BoxDecoration(
          color: bootstrap.theme.drawerGradient == null
              ? bootstrap.theme.drawerBgValue ??
                  Theme.of(context).colorScheme.primary
              : null,
          gradient: bootstrap.theme.drawerGradient,
        ),
        child: Align(
          alignment: Alignment.bottomLeft,
          child: Text(
            auth.company?.tradeName ??
                auth.company?.name ??
                bootstrap.tenant?.businessName ??
                '',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 18,
              fontWeight: FontWeight.bold,
            ),
          ),
        ),
      ),
      ListTile(
        leading: const Icon(Icons.home_outlined),
        title: Text(context.tr('Home')),
        onTap: () {
          Navigator.of(context).pop();
          setState(() {
            _endpoint = '/api/tenant/views/dashboard';
            _title = null;
          });
        },
      ),
    ];

    for (final section in _sections(bootstrap, auth.user)) {
      children.add(Padding(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
        child: Text(
          context.tr(section.schema.title),
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: section.schema.color == null
                    ? null
                    : SduiIconRegistry.parseColor(section.schema.color),
                fontWeight: FontWeight.bold,
              ),
        ),
      ));
      final childrenByParent = <String?, List<_ShellItem>>{};
      for (final row in section.items) {
        (childrenByParent[section.parentByKey[row.schema.key]] ??= []).add(row);
      }

      Widget buildItem(_ShellItem row, int depth) {
        final nested = childrenByParent[row.schema.key] ?? const <_ShellItem>[];
        if (nested.isNotEmpty) {
          return ExpansionTile(
            leading: Icon(SduiIconRegistry.resolve(row.schema.icon)),
            title: Text(context.tr(row.schema.title)),
            childrenPadding: EdgeInsets.only(left: (depth + 1) * 16.0),
            children: [for (final child in nested) buildItem(child, depth + 1)],
          );
        }
        return ListTile(
          contentPadding: EdgeInsets.only(left: 16 + depth * 16.0, right: 12),
          leading: Icon(SduiIconRegistry.resolve(row.schema.icon)),
          title: Text(context.tr(row.schema.title)),
          onTap: () => _open(row.schema),
        );
      }

      for (final root in childrenByParent[null] ?? const <_ShellItem>[]) {
        children.add(buildItem(root, 0));
      }
    }

    return Drawer(
      backgroundColor: bootstrap.theme.drawerGradient == null
          ? bootstrap.theme.drawerBgValue
          : Colors.transparent,
      child: Container(
        decoration: BoxDecoration(gradient: bootstrap.theme.drawerGradient),
        child: SafeArea(
            child: ListView(padding: EdgeInsets.zero, children: children)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final bootstrap = context.watch<BootstrapCache>();
    final auth = context.watch<AuthProvider>();
    final title = _title ??
        auth.company?.tradeName ??
        auth.company?.name ??
        bootstrap.tenant?.businessName ??
        '';

    return Scaffold(
      key: _scaffoldKey,
      appBar: AppBar(
        title: Text(title),
        actions: [
          IconButton(
            tooltip: context.tr('Refresh'),
            icon: const Icon(Icons.refresh),
            onPressed: () async {
              await context.read<LocaleProvider>().refreshFromServer();
              if (mounted) setState(() {});
            },
          ),
          IconButton(
            tooltip: context.tr('Sign out'),
            icon: const Icon(Icons.logout),
            onPressed: _confirmLogout,
          ),
        ],
      ),
      drawer: _drawer(context, bootstrap, auth),
      body: DynamicSchemaPage(
        key: ValueKey(_endpoint),
        endpoint: _endpoint,
        embedded: true,
      ),
    );
  }
}
