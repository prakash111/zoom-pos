import 'dart:math' as math;

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../widgets/tenant_logo_avatar.dart';
import '../../core/config/platform_branding_provider.dart';

import '../../core/api/api_client.dart';
import '../../core/config/bootstrap_cache.dart';
import '../../core/config/locale_provider.dart';
import '../../core/config/dashboard_layout.dart';
import '../../core/config/nav_dock_provider.dart';
import '../../core/config/theme.dart';
import '../../core/config/theme_provider.dart';
import '../../core/models/analytics_model.dart';
import '../../core/models/company_model.dart';
import '../../core/models/user_model.dart';
import '../../core/sdui/models/sdui_models.dart';
import '../../core/sdui/sdui_action_dispatcher.dart';
import '../../core/sdui/sdui_component_registry.dart';
import '../../core/sdui/sdui_icon_registry.dart';
import '../../core/services/sync/sync_status_badge.dart';
import '../../core/storage/app_preferences.dart';
import '../../core/utils/currency_formatter.dart';
import '../../core/utils/responsive.dart';
import '../../core/widgets/coming_soon_screen.dart';
import '../../core/widgets/split_navigation_tile.dart';
import '../../core/widgets/tappable_scale.dart';
import '../../l10n/app_localizations.dart';
import '../analytics/analytics_repository.dart';
import 'widgets/dock_rail_slot.dart';
import 'widgets/oroit_dashboard.dart';
import 'widgets/posh_dashboard.dart';
import '../auth/auth_provider.dart';
import '../navigation/presentation/widgets/app_drawer.dart';
import '../sales/screens/sales_screen.dart';
import '../settings/screens/app_preferences_screen.dart';
import '../settings/screens/change_password_screen.dart';
import '../settings/server_settings_screen.dart';
import '../settings/settings_repository.dart';

const int _maximumNavigationDepth = 2;

const Set<String> _forcedRootKeys = <String>{
  'settings',
  'pos',
  'pharmacy_pos',
  'salon_pos',
  'restaurant_pos',
  'consignments',
};

class _FeatureTile {
  _FeatureTile(this.key, this.titleOf, this.icon,
      [this.builder, this.permissionModule]);

  /// Stable identifier for this destination, independent of locale/label and
  /// of [permissionModule] (several tiles share one backend module, e.g.
  /// `featureDueReceivables`/`featurePayables` are both `'finance'`) — what a
  /// tenant's `nav_config.hidden_tiles` (see [AppBootstrapController] /
  /// [BootstrapCache]) actually names to hide one destination.
  final String key;

  /// Resolves the display title from the active locale — a plain [String]
  /// would freeze at whatever locale was active when this module-level list
  /// was first built, so titles are only ever read through this at render
  /// time (see [DashboardScreen]'s dock builders).
  final String Function(AppLocalizations l10n) titleOf;
  final IconData icon;

  /// Screen this tile opens. Falls back to [ComingSoonScreen] when a module
  /// hasn't been built yet.
  final WidgetBuilder? builder;

  /// [PermissionChecker::MODULES] slug this tile belongs to, e.g. `'pos'` or
  /// `'products'` — hidden from the menu unless the signed-in user has
  /// `{module}.view`. `null` means the tile is never permission-gated (no
  /// dedicated backend module, e.g. Subscription/Devices).
  final String? permissionModule;

  bool visibleTo(UserModel? user) {
    final permission = permissionModule?.trim();
    if (permission == null || permission.isEmpty || user == null) return true;

    // Built-in menu rows use a module slug (`pos`), while database-authored
    // SDUI rows may already use the full permission (`pos.view`).
    final requiredPermission =
        permission.contains('.') ? permission : '$permission.view';
    return user.can(requiredPermission);
  }
}

/// One labeled group of [_FeatureTile]s in the drawer — e.g. web's
/// "RESTAURANT OPERATIONS" or "FINANCIAL MANAGEMENT" headers. [header] is
/// `null` for a group that renders with no heading of its own.
class _NavSection {
  const _NavSection(this.key, this.header, this.tiles,
      {this.headerColor, this.parentByKey = const {}});

  /// Stable identifier a tenant's `nav_config.section_order` reorders by
  /// (see [_FeatureTile.key]).
  final String key;
  final String Function(AppLocalizations l10n)? header;
  final Color? headerColor;
  final List<_FeatureTile> tiles;

  /// Tile key -> the parent tile key it's nested under (Settings >
  /// Navigation Menu's "Nest under..." action), for tiles the tenant has
  /// explicitly nested — absent for every root-level tile. Purely a display
  /// hint: [_DashboardScreenState._buildDrawer] indents a nested tile under
  /// its parent, but tap order/routing (see [_featuresFor]) is completely
  /// unaffected — [tiles] stays whatever flat, index-stable order it always
  /// was, exactly like the un-nested compiled-in tree.
  final Map<String, String> parentByKey;
}

/// Dynamically hydrates navigation sections from the Server-Driven UI bootstrap payload,
/// resolving icons via [SduiIconRegistry] and screen builders via [SduiComponentRegistry].
List<_NavSection> _serverDrivenSections() {
  final sduiSections = BootstrapCache.instance.effectiveSections;
  final result = <_NavSection>[];

  for (var sectionIndex = 0;
      sectionIndex < sduiSections.length;
      sectionIndex++) {
    final section = sduiSections[sectionIndex];
    try {
      final tiles = <_FeatureTile>[];
      final parentByKey = <String, String>{};
      final seenKeys = <String>{};

      void collectItems(List<SduiNavItemSchema> items, String? defaultParent) {
        for (var itemIndex = 0; itemIndex < items.length; itemIndex++) {
          final item = items[itemIndex];
          try {
            if (item.key.isEmpty) {
              debugPrint(
                  'Drawer navigation: ignoring item without a key in section ${section.key} at index $itemIndex.');
              continue;
            }
            if (!seenKeys.add(item.key)) continue;

            // Lead Management is a separate vertical module (lead_ops) and must NEVER be inside cashier_sales
            if (section.key == 'cashier_sales' &&
                (item.key == 'lead_management' ||
                    item.key == 'leads' ||
                    item.key.startsWith('lead_') ||
                    item.key.contains('lead') ||
                    item.targetEndpoint == '/tenant/views/leads' ||
                    item.targetEndpoint == '/api/tenant/views/leads' ||
                    item.title.toLowerCase().contains('lead'))) {
              continue;
            }

            tiles.add(_FeatureTile(
              item.key,
              (l10n) => BootstrapCache.instance.resolveNavigationLabel(
                item.key,
                l10n.text(item.title, fallback: item.title),
              ),
              SduiIconRegistry.resolve(item.icon),
              SduiComponentRegistry.instance.resolve(
                item.component ?? item.key,
                targetEndpoint: item.targetEndpoint,
                title: item.title,
              ),
              item.permission,
            ));
            final parent = item.effectiveParentId ?? defaultParent;
            if (parent != null && parent.isNotEmpty) {
              parentByKey[item.key] = parent;
            }
            if (item.children.isNotEmpty) {
              collectItems(item.children, item.key);
            }
          } catch (error, stackTrace) {
            debugPrint(
                'Drawer navigation: failed to compile item ${item.key} in section ${section.key}: $error');
            debugPrintStack(stackTrace: stackTrace);
            // A bad accordion parent must not hide otherwise valid children.
            if (item.children.isNotEmpty) {
              collectItems(item.children, defaultParent);
            }
          }
        }
      }

      collectItems(section.items, null);
      if (section.key.isEmpty || tiles.isEmpty) {
        debugPrint(
            'Drawer navigation: ignoring empty section at index $sectionIndex (${section.key.isEmpty ? 'missing key' : section.key}).');
        continue;
      }

      result.add(_NavSection(
        section.key,
        (l10n) => BootstrapCache.instance.resolveNavigationLabel(
          section.key,
          l10n.text(section.displayTitle, fallback: section.displayTitle),
        ),
        tiles,
        headerColor: section.color != null
            ? SduiIconRegistry.parseColor(section.color)
            : null,
        parentByKey: parentByKey,
      ));
    } catch (error, stackTrace) {
      debugPrint(
          'Drawer navigation: failed to compile section ${section.key}: $error');
      debugPrintStack(stackTrace: stackTrace);
    }
  }

  return result;
}

/// The active nav tree for this tenant — loaded dynamically from the backend SDUI
/// module schema and menu structure, with this tenant's Settings > Navigation Menu
/// customization applied on top.
List<_NavSection> _sectionsFor(CompanyModel? company, UserModel? user) {
  final compiled = _serverDrivenSections();
  final nav = BootstrapCache.instance.navConfig;
  final itemOverrides = {for (final item in nav.items) item.key: item};
  final sectionOverrides = {
    for (final section in nav.sections) section.key: section,
  };
  final sectionOrderOverrides = {
    for (final section in nav.sections) section.key: section.order,
  };
  final sectionMetaByKey = {
    for (final section in compiled) section.key: section
  };
  final tilesBySection =
      <String, List<({int order, int fallback, _FeatureTile tile})>>{};

  var fallback = 0;
  for (final section in compiled) {
    for (var index = 0; index < section.tiles.length; index++) {
      final tile = section.tiles[index];
      if (!tile.visibleTo(user)) continue;

      final override = itemOverrides[tile.key];
      if (override != null && !override.visible) continue;

      final targetSection = override?.section != null &&
              sectionMetaByKey.containsKey(override!.section)
          ? override.section!
          : section.key;

      // CRITICAL: Lead Management is a separate vertical module (lead_ops)
      // and must NEVER be placed into Cashier & Sales, even via custom placement/overrides
      if (targetSection == 'cashier_sales' &&
          (tile.key == 'lead_management' ||
              tile.key == 'leads' ||
              tile.key.startsWith('lead_') ||
              tile.key.contains('lead'))) {
        continue;
      }

      (tilesBySection[targetSection] ??= []).add((
        order: override?.order ?? index,
        fallback: fallback++,
        tile: tile,
      ));
    }
  }

  final activeMode = BootstrapCache.instance.activeMode.toLowerCase().trim();
  final hasRetailLicensed = company?.isModuleEnabled('retail') ?? true;
  final isSpecialized = activeMode != 'retail' &&
      activeMode != 'general' &&
      activeMode.isNotEmpty;

  if ((!isSpecialized || hasRetailLicensed) && !sectionMetaByKey.containsKey('cashier_sales')) {
    sectionMetaByKey['cashier_sales'] = _NavSection(
      'cashier_sales',
      (l10n) => BootstrapCache.instance.resolveNavigationLabel(
        'cashier_sales',
        l10n.text('Cashier & Sales', fallback: 'Cashier & Sales'),
      ),
      const [],
      headerColor: const Color(0xFF1D4ED8),
    );
  }

  // Specialized vertical tenants maintain POS and commerce inside their vertical block;
  // only remove cashier_sales if retail is NOT licensed for this company.
  if (isSpecialized && !hasRetailLicensed) {
    tilesBySection.remove('cashier_sales');
  }

  final result = <_NavSection>[];
  for (final entry in tilesBySection.entries) {
    final rows = entry.value;
    final rowByKey = {for (final row in rows) row.tile.key: row};
    final safeParent = <String, String?>{};

    for (final row in rows) {
      final item = itemOverrides[row.tile.key];
      final isForcedRoot = _forcedRootKeys.contains(row.tile.key);
      final rawParent = isForcedRoot
          ? null
          : (item != null
              ? (item.level == 0 ||
                      item.parentId == null ||
                      item.parentId!.isEmpty
                  ? null
                  : (item.parentId ?? item.parent))
              : sectionMetaByKey[entry.key]?.parentByKey[row.tile.key]);
      final candidate =
          rawParent == null || rawParent.isEmpty ? null : rawParent;

      var cursor = candidate;
      var valid = true;
      var depth = 0;
      final seen = <String>{row.tile.key};

      while (cursor != null && cursor.isNotEmpty) {
        if (!rowByKey.containsKey(cursor) ||
            !seen.add(cursor) ||
            ++depth > _maximumNavigationDepth) {
          valid = false;
          break;
        }
        cursor = _forcedRootKeys.contains(cursor)
            ? null
            : (itemOverrides[cursor] != null
                ? (itemOverrides[cursor]!.level == 0 ||
                        itemOverrides[cursor]!.parentId == null ||
                        itemOverrides[cursor]!.parentId!.isEmpty
                    ? null
                    : (itemOverrides[cursor]!.parentId ??
                        itemOverrides[cursor]!.parent))
                : sectionMetaByKey[entry.key]?.parentByKey[cursor]);
      }
      safeParent[row.tile.key] = valid ? candidate : null;
    }

    int compareRows(
      ({int order, int fallback, _FeatureTile tile}) first,
      ({int order, int fallback, _FeatureTile tile}) second,
    ) {
      final byOrder = first.order.compareTo(second.order);
      return byOrder != 0 ? byOrder : first.fallback.compareTo(second.fallback);
    }

    final childrenByParent =
        <String?, List<({int order, int fallback, _FeatureTile tile})>>{};
    for (final row in rows) {
      (childrenByParent[safeParent[row.tile.key]] ??= []).add(row);
    }
    for (final children in childrenByParent.values) {
      children.sort(compareRows);
    }

    final orderedTiles = <_FeatureTile>[];
    final parentByKey = <String, String>{};
    final visited = <String>{};

    void appendBranch(({int order, int fallback, _FeatureTile tile}) row) {
      if (!visited.add(row.tile.key)) return;
      orderedTiles.add(row.tile);
      for (final child in childrenByParent[row.tile.key] ??
          const <({int order, int fallback, _FeatureTile tile})>[]) {
        parentByKey[child.tile.key] = row.tile.key;
        appendBranch(child);
      }
    }

    for (final root in childrenByParent[null] ??
        const <({int order, int fallback, _FeatureTile tile})>[]) {
      appendBranch(root);
    }
    for (final row in rows..sort(compareRows)) {
      if (!visited.contains(row.tile.key)) {
        appendBranch(row);
      }
    }

    final customTitle = sectionOverrides[entry.key]?.customTitle?.trim();
    result.add(_NavSection(
      entry.key,
      customTitle?.isNotEmpty == true
          ? (_) => customTitle!
          : sectionMetaByKey[entry.key]!.header,
      orderedTiles,
      headerColor: sectionMetaByKey[entry.key]!.headerColor,
      parentByKey: parentByKey,
    ));
  }

  final compiledSectionIndex = {
    for (var index = 0; index < compiled.length; index++)
      compiled[index].key: index,
  };
  result.sort((first, second) {
    final firstOrder = sectionOrderOverrides[first.key] ??
        compiledSectionIndex[first.key] ??
        0;
    final secondOrder = sectionOrderOverrides[second.key] ??
        compiledSectionIndex[second.key] ??
        0;
    return firstOrder.compareTo(secondOrder);
  });

  return result;
}

/// Settings > Navigation Menu's read-only view of one [_FeatureTile] — just
/// enough (key + resolved label) to render a checkbox row, with none of the
/// routing/permission internals a settings screen has no business touching.
class NavTileDescriptor {
  const NavTileDescriptor(this.key, this.label);
  final String key;
  final String label;
}

/// Settings > Navigation Menu's read-only view of one [_NavSection].
class NavSectionDescriptor {
  const NavSectionDescriptor(this.key, this.label, this.tiles);
  final String key;
  final String label;
  final List<NavTileDescriptor> tiles;
}

/// The compiled-in nav tree for this tenant's mode, as plain data — used by
/// Settings > Navigation Menu to let a tenant hide destinations and reorder
/// section groups (persisted via SettingsRepository.updateNavConfig, applied
/// by [_sectionsFor]). Deliberately not permission-filtered: this is the
/// tenant-wide tree an owner/admin configures, independent of which roles
/// can see which module.
List<NavSectionDescriptor> navSectionsForSettings(
    AppLocalizations l10n, CompanyModel? company) {
  final sections = _serverDrivenSections();
  return [
    for (final section in sections)
      NavSectionDescriptor(
        section.key,
        section.header?.call(l10n) ?? section.key,
        [
          for (final tile in section.tiles)
            NavTileDescriptor(tile.key, tile.titleOf(l10n))
        ],
      ),
  ];
}

/// Every tile across all sections, in order — the flat form every dock
/// rendering except the drawer (rail/top bar/bottom bar don't group with
/// headers) uses, and what the drawer's tile-to-index mapping is built from
/// so `_dockIndex` stays in sync across all four.
List<_FeatureTile> _featuresFor(CompanyModel? company, UserModel? user) => [
      for (final section in _sectionsFor(company, user)) ...section.tiles,
    ];

/// The post-login home base. Each feature module still under construction
/// falls back to a [ComingSoonScreen] placeholder — swap in the real screen
/// as it lands and give its [_FeatureTile] a builder.
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final _scaffoldKey = GlobalKey<ScaffoldState>();
  final _dashboardActionFormKey = GlobalKey<FormState>();
  final Map<String, dynamic> _dashboardActionValues = {};
  late final AnalyticsRepository _analyticsRepository;
  late Future<AnalyticsModel> _analyticsFuture;
  int _notificationBadgeCount = 0;
  Map<String, dynamic> _notificationAction = const {
    'type': 'OPEN_BOTTOM_SHEET',
    'title': 'System Alerts & Reminders',
    'endpoint': '/api/v1/tenant/notifications/feed',
  };

  /// The dashboard "Filter" date range — drives every metric card, the
  /// balance sparkline and the statistics deltas.
  AnalyticsRange _range = AnalyticsRange.thisMonth;
  DateTimeRange? _customRange;

  /// 0 = Home (this screen); 1..N = `_featuresFor(company)[index - 1]`.
  /// Shared by whichever nav dock is active — see [NavDockProvider].
  int _dockIndex = 0;

  Future<AnalyticsModel> _loadAnalytics() =>
      _analyticsRepository.fetchAnalytics(
        range: _range,
        from: _customRange?.start,
        to: _customRange?.end,
      );

  Future<void> _loadDashboardChrome() async {
    try {
      final apiClient = context.read<ApiClient>();
      try {
        final unreadRes = await apiClient.requestAbsolute(
          '/api/v1/tenant/notifications/unread-count',
          method: 'GET',
        );
        if (unreadRes['unread_count'] != null) {
          if (mounted) {
            setState(() {
              _notificationBadgeCount =
                  (unreadRes['unread_count'] as num).toInt();
            });
          }
        }
      } catch (_) {}

      final response = await apiClient.requestAbsolute(
        '/api/tenant/views/dashboard',
        method: 'GET',
      );
      final rawSchema = response['schema'];
      if (rawSchema is! Map) return;
      final appBar = rawSchema['app_bar'];
      if (appBar is! Map || appBar['actions'] is! List) return;

      for (final rawAction in appBar['actions'] as List) {
        if (rawAction is! Map ||
            rawAction['type']?.toString() != 'notification_bell') {
          continue;
        }
        final action = rawAction['action'];
        if (!mounted) return;
        setState(() {
          if (rawAction['badge_count'] != null) {
            _notificationBadgeCount =
                (rawAction['badge_count'] as num?)?.toInt() ??
                    _notificationBadgeCount;
          }
          if (action is Map) {
            _notificationAction = Map<String, dynamic>.from(action);
          }
        });
        return;
      }
    } catch (_) {
      // Dashboard content remains usable offline; the bell simply keeps the
      // last server-provided count until the next refresh succeeds.
    }
  }

  Future<void> _openNotificationFeed() async {
    final dispatcher = SduiActionDispatcher(
      resolveApiClient: () => context.read<ApiClient>(),
      formKey: _dashboardActionFormKey,
      formValues: _dashboardActionValues,
      setFormValue: (key, value) => _dashboardActionValues[key] = value,
      onReload: () {
        if (!mounted) return;
        setState(() => _analyticsFuture = _loadAnalytics());
        _loadDashboardChrome();
      },
      showToast: (message, {isError = false}) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(message),
          backgroundColor:
              isError ? Colors.red.shade700 : Colors.green.shade700,
        ));
      },
    );
    await dispatcher.dispatch(context, _notificationAction);
  }

  Future<void> _pickDateRange() async {
    final selected = await showMenu<AnalyticsRange>(
      context: context,
      position: RelativeRect.fromLTRB(
          MediaQuery.of(context).size.width - 260, 96, 24, 0),
      items: [
        for (final r in const [
          AnalyticsRange.today,
          AnalyticsRange.yesterday,
          AnalyticsRange.last7,
          AnalyticsRange.last30,
          AnalyticsRange.thisMonth,
          AnalyticsRange.lastMonth,
          AnalyticsRange.thisYear,
          AnalyticsRange.allTime,
          AnalyticsRange.custom,
        ])
          CheckedPopupMenuItem<AnalyticsRange>(
            value: r,
            checked: _range == r,
            child: Text(r.label),
          ),
      ],
    );
    if (selected == null || !mounted) return;

    DateTimeRange? custom;
    if (selected == AnalyticsRange.custom) {
      final now = DateTime.now();
      custom = await showDateRangePicker(
        context: context,
        firstDate: DateTime(now.year - 5),
        lastDate: now,
        initialDateRange: _customRange ??
            DateTimeRange(
                start: now.subtract(const Duration(days: 7)), end: now),
      );
      if (custom == null) return;
    }

    setState(() {
      _range = selected;
      _customRange = custom;
      _analyticsFuture = _loadAnalytics();
    });
  }

  @override
  void initState() {
    super.initState();
    _analyticsRepository = AnalyticsRepository(context.read<ApiClient>());
    _analyticsFuture = _loadAnalytics();
    _loadDashboardChrome();
    context
        .read<ThemeProvider>()
        .refreshFromServer(SettingsRepository(context.read<ApiClient>()));

    // The unauthenticated startup refresh cannot fetch the protected
    // bootstrap on a fresh install. Retry as soon as Dashboard exists, which
    // means login/session restoration has supplied the bearer token.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<LocaleProvider>().refreshFromServer();
    });
  }

  Future<void> _confirmLogout(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Log Out'),
        content: const Text('Are you sure you want to exit your session?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: const Color(0xFFEF4444),
            ),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Log Out'),
          ),
        ],
      ),
    );

    if (confirmed == true && context.mounted) {
      await context.read<AuthProvider>().logout();
    }
  }

  Future<void> _launchHelpSupport(BuildContext context, String phone) async {
    final branding = context.read<PlatformBrandingProvider>();
    final cleanPhone = phone.replaceAll(RegExp(r'[^0-9+]'), '');
    final digits = phone.replaceAll(RegExp(r'[^0-9]'), '');
    final email = branding.supportEmail.isNotEmpty
        ? branding.supportEmail
        : 'support@zoomnearby.com';

    await showModalBottomSheet<void>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetCtx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: const Color(0xFF10B981).withValues(alpha: 0.15),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.support_agent,
                        color: Color(0xFF10B981), size: 24),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Help & Customer Support',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        Text(
                          'We are here to assist you anytime',
                          style: TextStyle(
                            fontSize: 12,
                            color: Theme.of(context)
                                .colorScheme
                                .onSurface
                                .withValues(alpha: 0.6),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              if (digits.isNotEmpty) ...[
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: const Color(0xFF25D366).withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(Icons.chat, color: Color(0xFF25D366)),
                  ),
                  title: const Text('Chat on WhatsApp',
                      style: TextStyle(fontWeight: FontWeight.w600)),
                  subtitle: Text(phone),
                  trailing: const Icon(Icons.arrow_forward_ios, size: 14),
                  onTap: () async {
                    Navigator.pop(sheetCtx);
                    final waUrl = Uri.parse(
                        'https://wa.me/$digits?text=${Uri.encodeComponent('Hello, I need assistance with ZoomPOS.')}');
                    if (await canLaunchUrl(waUrl)) {
                      await launchUrl(waUrl,
                          mode: LaunchMode.externalApplication);
                    }
                  },
                ),
                const Divider(),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: const Color(0xFF3B82F6).withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(Icons.phone, color: Color(0xFF3B82F6)),
                  ),
                  title: const Text('Call Support Hotline',
                      style: TextStyle(fontWeight: FontWeight.w600)),
                  subtitle: Text(cleanPhone),
                  trailing: const Icon(Icons.arrow_forward_ios, size: 14),
                  onTap: () async {
                    Navigator.pop(sheetCtx);
                    final telUrl = Uri.parse('tel:$cleanPhone');
                    if (await canLaunchUrl(telUrl)) {
                      await launchUrl(telUrl);
                    }
                  },
                ),
              ],
              if (email.isNotEmpty) ...[
                const Divider(),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: const Color(0xFF6366F1).withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(Icons.email_outlined,
                        color: Color(0xFF6366F1)),
                  ),
                  title: const Text('Email Support Desk',
                      style: TextStyle(fontWeight: FontWeight.w600)),
                  subtitle: Text(email),
                  trailing: const Icon(Icons.arrow_forward_ios, size: 14),
                  onTap: () async {
                    Navigator.pop(sheetCtx);
                    final mailUrl = Uri.parse(
                        'mailto:$email?subject=${Uri.encodeComponent('ZoomPOS Support Inquiry')}');
                    if (await canLaunchUrl(mailUrl)) {
                      await launchUrl(mailUrl);
                    }
                  },
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  /// Shared tap handler for every dock rendering (drawer, rail, top bar,
  /// bottom bar) — none of them are a persistent multi-tab shell, they're a
  /// quick-launcher over the app's stack-based navigation, so selecting a
  /// destination pushes a route and settles back on Home once it's popped.
  void _onDockItemSelected(BuildContext context, int index) {
    if (index == 0) {
      setState(() => _dockIndex = 0);
      return;
    }

    setState(() => _dockIndex = index);
    final auth = context.read<AuthProvider>();
    final feature = _featuresFor(auth.company, auth.user)[index - 1];
    final title = feature.titleOf(AppLocalizations.of(context));
    Navigator.of(context)
        .push(MaterialPageRoute(
            builder: feature.builder ??
                (_) => ComingSoonScreen(title: title, icon: feature.icon)))
        .then((_) {
      if (mounted) setState(() => _dockIndex = 0);
    });
  }

  /// The dock's destinations as (icon, label) pairs — Home followed by every
  /// [_FeatureTile], with titles resolved from the active locale — shared by
  /// every dock rendering below so none of them can drift out of sync (or
  /// out of language) with each other.
  List<(IconData, String)> _dockDestinationsFor(
          AppLocalizations l10n, CompanyModel? company, UserModel? user) =>
      [
        (Icons.home_outlined, l10n.navHome),
        for (final feature in _featuresFor(company, user))
          (feature.icon, feature.titleOf(l10n)),
      ];

  String _resolveTenantBadge(CompanyModel? company, BootstrapCache bootstrap) {
    final candidates = [
      company?.businessType,
      bootstrap.tenant?.businessType,
      bootstrap.config['business_type']?.toString(),
      bootstrap.config['store_type']?.toString(),
      bootstrap.activeModule.title,
      bootstrap.activeMode,
      company?.posMode,
    ];
    for (final raw in candidates) {
      final val = raw?.trim();
      if (val != null && val.isNotEmpty && val.toLowerCase() != 'general') {
        return val.toUpperCase();
      }
    }
    return 'RETAIL';
  }

  /// Structured, mode-isolated drawer: a Home tile followed by every
  /// [_NavSection] with its own header — the mobile equivalent of the web
  /// tenant sidebar's slide-out drawer (see layouts/tenant.blade.php).
  /// Section headers are omitted from the rail/top bar/bottom bar (see
  /// [_dockDestinationsFor]), which just render the same tiles flat.
  Widget _buildDrawer(
    BuildContext context,
    CompanyModel? company,
    UserModel? user,
    BootstrapCache bootstrap,
  ) {
    return AnimatedBuilder(
      animation: bootstrap,
      builder: (context, _) {
        final l10n = AppLocalizations.of(context);

        final tp = context.watch<ThemeProvider>();
        final coverUrl = company?.drawerCoverUrl ??
            bootstrap.drawerCoverUrl ??
            bootstrap.config['drawer_cover_url']?.toString();
        final logoUrl = company?.logoUrl ??
            bootstrap.logoUrl ??
            bootstrap.tenant?.logoUrl ??
            bootstrap.config['logo_url']?.toString();
        final storeTitle = company?.tradeName ??
            company?.name ??
            bootstrap.tenant?.businessName ??
            bootstrap.config['store_name']?.toString() ??
            bootstrap.config['trade_name']?.toString() ??
            bootstrap.config['business_name']?.toString() ??
            bootstrap.config['name']?.toString() ??
            'ZoomNearby Enterprise';
        final hasCover = coverUrl != null && coverUrl.isNotEmpty;
        final primaryColor = tp.activeLinkColor ??
            bootstrap.theme.primaryColorValue ??
            Theme.of(context).colorScheme.primary;
        // An explicit per-device pick (App Preferences ▸ "Drawer background")
        // always wins, exactly as set — that's a deliberate user choice.
        // Anything else (unset, "Default", or the tenant's server-synced
        // colour) is a single mode-agnostic value, so it's only honoured when
        // it actually reads on the *active* brightness; otherwise the dark
        // theme's own drawer surface applies instead of rendering a light
        // drawer under a dark scaffold (or vice versa in light mode).
        final isDarkMode = Theme.of(context).brightness == Brightness.dark;
        Color? modeMatched(Color? c) => c == null
            ? null
            : ((c.computeLuminance() > 0.5) == !isDarkMode ? c : null);
        final brandedDrawerBgForMode =
            modeMatched(bootstrap.theme.drawerBgValue);
        final drawerBgColor = (tp.isDrawerBgUserOverride
                ? tp.drawerBg
                : modeMatched(tp.drawerBg)) ??
            brandedDrawerBgForMode ??
            Theme.of(context).drawerTheme.backgroundColor;
        final drawerGradient = bootstrap.theme.drawerGradient;

        // Item colour hierarchy — derived from the *actual* background the
        // rows sit on, so a light drawer never renders faint grey-on-white
        // text/icons (and a dark one never renders dark-on-dark). A local
        // "Drawer text & icon" override in App Preferences still wins.
        final effectiveDrawerBg = drawerGradient is LinearGradient
            ? drawerGradient.colors.first
            : (drawerBgColor ?? Theme.of(context).colorScheme.surface);
        final isLightDrawerBg = effectiveDrawerBg.computeLuminance() > 0.5;
        final unselectedItemColor = tp.drawerTextColor ??
            (isLightDrawerBg
                ? const Color(0xFF334155) // slate-700
                : const Color(0xFFE2E8F0)); // slate-200
        final unselectedIconColor = tp.drawerTextColor ??
            (isLightDrawerBg
                ? const Color(0xFF64748B) // slate-500
                : const Color(0xFF94A3B8)); // slate-400
        final selectedItemColor = primaryColor;

        final headerWidget = Container(
          width: double.infinity,
          padding: EdgeInsets.fromLTRB(
              16, MediaQuery.of(context).padding.top + 12, 16, 14),
          decoration: BoxDecoration(
            color: primaryColor,
            image: hasCover
                ? DecorationImage(
                    image: kIsWeb
                        ? NetworkImage(coverUrl,
                            webHtmlElementStrategy:
                                WebHtmlElementStrategy.prefer)
                        : CachedNetworkImageProvider(coverUrl)
                            as ImageProvider,
                    fit: BoxFit.cover,
                  )
                : null,
            gradient: hasCover
                ? LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      Colors.black.withValues(alpha: 0.15),
                      Colors.black.withValues(alpha: 0.55)
                    ],
                  )
                : null,
          ),
          child: Row(
            children: [
              TenantLogoAvatar(
                logoUrl: logoUrl,
                tenantName: storeTitle,
                size: 38,
                borderRadius: BorderRadius.circular(8),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Row(
                  children: [
                    Flexible(
                      child: Text(
                        storeTitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 15,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.2),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        _resolveTenantBadge(company, bootstrap),
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.95),
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );

        final children = <Widget>[
          ListTile(
            leading: const Icon(Icons.home_outlined),
            title: Text(l10n.navHome),
            selected: _dockIndex == 0,
            onTap: () {
              Navigator.of(context).pop();
              _onDockItemSelected(context, 0);
            },
          ),
        ];
        final navSections = _sectionsFor(company, user);
        if (navSections.isEmpty) {
          children.add(
            bootstrap.isNavigationLoading
                ? const _DrawerNavigationSkeleton()
                : _DrawerNavigationUnavailable(
                    message: bootstrap.navigationError ??
                        'No navigation items are available for this account.',
                    onRetry: () =>
                        context.read<LocaleProvider>().refreshFromServer(),
                  ),
          );
        }
        final indexByKey = <String, int>{};
        var nextIndex = 1;
        for (final section in navSections) {
          for (final tile in section.tiles) {
            indexByKey[tile.key] = nextIndex++;
          }
        }

        void openTile(_FeatureTile tile) {
          final index = indexByKey[tile.key];
          if (index == null) return;
          Navigator.of(context).pop();
          _onDockItemSelected(context, index);
        }

        final itemOverrides = {
          for (final item in bootstrap.navConfig.items) item.key: item
        };

        for (final section in navSections) {
          if (section.tiles.isEmpty) continue;

          final rootItems = <_FeatureTile>[];
          final childrenByParent = <String?, List<_FeatureTile>>{};

          for (final tile in section.tiles) {
            final override = itemOverrides[tile.key];
            final bool isForcedRoot = _forcedRootKeys.contains(tile.key);
            // Check if the item is explicitly a Main Menu (level 0)
            final bool isMainMenu = isForcedRoot ||
                (override != null
                    ? (override.level == 0 ||
                        override.parentId == null ||
                        override.parentId!.isEmpty)
                    : (section.parentByKey[tile.key] == null));

            if (isMainMenu) {
              rootItems.add(tile);
            } else {
              final targetParentKey = (override != null &&
                      override.parentId != null &&
                      override.parentId!.isNotEmpty)
                  ? override.parentId!
                  : section.parentByKey[tile.key];
              if (targetParentKey != null && targetParentKey.isNotEmpty) {
                (childrenByParent[targetParentKey] ??= []).add(tile);
              } else {
                rootItems.add(tile);
              }
            }
          }

          Widget buildBranch(
            _FeatureTile tile,
            int depth, {
            List<_FeatureTile>? childOverride,
            bool sectionParent = false,
          }) {
            final nested = childOverride ??
                childrenByParent[tile.key] ??
                const <_FeatureTile>[];
            final index = indexByKey[tile.key]!;
            final padding = EdgeInsets.only(
              left: 16 + depth * 30,
              right: 12,
            );

            final isSelected = _dockIndex == index;

            if (nested.isEmpty) {
              return ListTile(
                key: ValueKey('drawer-item-${tile.key}'),
                contentPadding: padding,
                dense: depth > 0,
                iconColor: isSelected ? selectedItemColor : unselectedIconColor,
                textColor: isSelected ? selectedItemColor : unselectedItemColor,
                selectedColor: selectedItemColor,
                leading: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (depth > 0) ...[
                      Text(
                        '↳',
                        style: TextStyle(
                          color: unselectedIconColor,
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(width: 6),
                    ],
                    Icon(
                      tile.icon,
                      size: sectionParent ? 22 : (depth == 0 ? 24 : 18),
                      color: isSelected
                          ? selectedItemColor
                          : (depth > 0
                              ? unselectedIconColor.withValues(alpha: 0.85)
                              : unselectedIconColor),
                    ),
                  ],
                ),
                title: Text(
                  bootstrap.resolveNavigationLabel(
                      tile.key, tile.titleOf(l10n)),
                  style: TextStyle(
                    fontSize: depth > 0 ? 13 : 14,
                    fontWeight: sectionParent || isSelected
                        ? FontWeight.w700
                        : (depth > 0 ? FontWeight.w500 : FontWeight.w500),
                    letterSpacing: sectionParent ? 0.2 : null,
                    color: isSelected ? selectedItemColor : unselectedItemColor,
                  ),
                ),
                selected: isSelected,
                onTap: () => openTile(tile),
              );
            }

            return SplitNavigationTile(
              key: PageStorageKey<String>(
                'drawer-branch-${section.key}-${tile.key}',
              ),
              mainTileKey: ValueKey('drawer-item-${tile.key}'),
              expandButtonKey: ValueKey('drawer-expand-${tile.key}'),
              contentPadding: padding,
              iconColor: unselectedIconColor,
              selectedColor: selectedItemColor,
              selected: isSelected,
              dense: depth > 0,
              leading: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (depth > 0) ...[
                    Text(
                      '↳',
                      style: TextStyle(
                        color: unselectedIconColor,
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(width: 6),
                  ],
                  Icon(
                    tile.icon,
                    size: sectionParent ? 22 : (depth == 0 ? 24 : 20),
                    color: isSelected
                        ? selectedItemColor
                        : (depth > 0
                            ? unselectedIconColor.withValues(alpha: 0.85)
                            : unselectedIconColor),
                  ),
                ],
              ),
              initiallyExpanded: false,
              title: Text(
                bootstrap.resolveNavigationLabel(tile.key, tile.titleOf(l10n)),
                style: TextStyle(
                  fontWeight: sectionParent || isSelected
                      ? FontWeight.w700
                      : FontWeight.w500,
                  letterSpacing: sectionParent ? 0.2 : null,
                  color: isSelected ? selectedItemColor : unselectedItemColor,
                ),
              ),
              children: [
                for (final child in nested)
                  buildBranch(
                    child,
                    math.min(depth + 1, _maximumNavigationDepth),
                  ),
              ],
              onTap: () => openTile(tile),
            );
          }

          children.add(Padding(
            key: ValueKey('drawer-section-divider-${section.key}'),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Divider(
              height: 1,
              thickness: 1,
              color: Theme.of(context).dividerColor.withValues(alpha: 0.18),
            ),
          ));

          final sectionTitle = section.header?.call(l10n) ?? '';
          if (sectionTitle.isNotEmpty) {
            children.add(Padding(
              key: ValueKey('drawer-section-title-${section.key}'),
              padding: const EdgeInsets.fromLTRB(20, 4, 16, 4),
              child: Text(
                sectionTitle.toUpperCase(),
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 0.8,
                  color: resolveItemColor(
                    section.headerColor,
                    tp.drawerTextColor?.withValues(alpha: 0.85) ??
                        unselectedIconColor,
                  ),
                ),
              ),
            ));
          }

          for (final root in rootItems) {
            children.add(buildBranch(
              root,
              0,
              childOverride: childrenByParent[root.key],
              sectionParent: true,
            ));
          }
        }

        final hasChangePassword = navSections.any(
          (sec) => sec.tiles.any(
              (t) => t.key == 'change_password' || t.key == 'change-password'),
        );

        if (!hasChangePassword) {
          children.add(const Divider());
          children.add(ListTile(
            leading: const Icon(Icons.lock_reset_outlined),
            title: Text(l10n.changePassword),
            onTap: () {
              Navigator.of(context).pop();
              Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) => const ChangePasswordScreen()));
            },
          ));
        }

        // Contrast guard: bind every drawer surface to the high-contrast
        // item palette derived above from the real background luminance, so
        // text/icons never wash out on a light drawer (or vanish on a dark
        // one). `onDrawer` is the readable body colour; the faint alpha
        // ramps are only for dividers / decoration.
        final onDrawer = unselectedItemColor;
        final baseTheme = Theme.of(context);
        final drawerTheme = baseTheme.copyWith(
          colorScheme: baseTheme.colorScheme.copyWith(
            primary: primaryColor,
            onSurface: onDrawer,
            onSurfaceVariant: unselectedIconColor,
            outline: unselectedIconColor,
          ),
          iconTheme: IconThemeData(color: unselectedIconColor),
          listTileTheme: ListTileThemeData(
            iconColor: unselectedIconColor,
            textColor: onDrawer,
            selectedColor: selectedItemColor,
          ),
          textTheme: baseTheme.textTheme.apply(
            bodyColor: onDrawer,
            displayColor: onDrawer,
          ),
          dividerColor: onDrawer.withValues(alpha: 0.22),
        );

        final brandingProvider = context.read<PlatformBrandingProvider>();
        final supportPhone = (bootstrap.config['support_whatsapp'] ??
                bootstrap.config['support_phone'] ??
                brandingProvider.supportPhone)
            .toString()
            .trim();
        final effectiveSupportPhone =
            supportPhone.isNotEmpty ? supportPhone : '+918535075196';

        return Drawer(
          backgroundColor:
              drawerGradient != null ? Colors.transparent : drawerBgColor,
          child: Theme(
            data: drawerTheme,
            child: Container(
              decoration: BoxDecoration(
                color: drawerGradient == null ? drawerBgColor : null,
                gradient: drawerGradient,
              ),
              child: SafeArea(
                top: false,
                bottom: true,
                child: Column(
                  children: [
                    headerWidget,
                    Expanded(
                      child: ListView(
                        padding: EdgeInsets.zero,
                        children: children,
                      ),
                    ),
                    SafeArea(
                      top: false,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 16, vertical: 10),
                        decoration: BoxDecoration(
                          border: Border(
                            top: BorderSide(
                              color: onDrawer.withValues(alpha: 0.18),
                            ),
                          ),
                        ),
                        child: ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: Container(
                            padding: const EdgeInsets.all(6),
                            decoration: BoxDecoration(
                              color: const Color(0xFF10B981).withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Icon(Icons.support_agent,
                                color: Color(0xFF10B981), size: 18),
                          ),
                          title: const Text(
                            'Help & Support',
                            style: TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 13,
                            ),
                          ),
                              subtitle: Text(
                                effectiveSupportPhone,
                                style: TextStyle(
                                  color: unselectedIconColor,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              trailing: const Icon(Icons.chevron_right, size: 16),
                              shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(10)),
                              onTap: () => _launchHelpSupport(context, effectiveSupportPhone),
                            ),
                          ),
                        ),
                      ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  /// A persistent rail for the Left/Right dock positions on tablet/desktop
  /// widths — wrapped in a scroll view since a rail doesn't scroll on its
  /// own and this app has far more destinations than fit most window
  /// heights.
  ///
  /// At desktop widths it renders a full hierarchical sidebar (nested items
  /// sit under their parent, exactly like the slide-out drawer) rather than
  /// the flat Material `NavigationRail`, which has no notion of sub-menus.
  /// Rail body for a [DockRailSlot], picked by viewport: the full
  /// hierarchical sidebar at desktop widths, a compact icon+label
  /// [NavigationRail] on tablet widths. [width] is the slot's already
  /// viewport-clamped width (see [dockRailWidth]).
  Widget _buildRailContent(BuildContext context, double width) {
    final extended = MediaQuery.sizeOf(context).width >= Breakpoints.desktop;
    return extended
        ? _buildRailTree(context)
        : _buildCompactRail(context, width);
  }

  Widget _buildCompactRail(BuildContext context, double width) {
    return LayoutBuilder(
      builder: (context, constraints) {
        return SingleChildScrollView(
          child: ConstrainedBox(
            constraints: BoxConstraints(minHeight: constraints.maxHeight),
            child: IntrinsicHeight(
              child: NavigationRail(
                extended: false,
                // Never wider than the clamped slot, never below 72dp.
                minWidth: math.min(width, 72).toDouble(),
                selectedIndex: _dockIndex,
                onDestinationSelected: (index) =>
                    _onDockItemSelected(context, index),
                labelType: NavigationRailLabelType.all,
                trailing: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: IconButton(
                    tooltip: 'Help & Support',
                    icon: const Icon(Icons.support_agent, color: Color(0xFF10B981)),
                    onPressed: () {
                      final bootstrap = BootstrapCache.instance;
                      final branding = context.read<PlatformBrandingProvider>();
                      final phone = (bootstrap.config['support_whatsapp'] ??
                              bootstrap.config['support_phone'] ??
                              branding.supportPhone)
                          .toString()
                          .trim();
                      _launchHelpSupport(context, phone.isNotEmpty ? phone : '+918535075196');
                    },
                  ),
                ),
                destinations: [
                  for (final destination in _dockDestinationsFor(
                      AppLocalizations.of(context),
                      context.read<AuthProvider>().company,
                      context.read<AuthProvider>().user))
                    NavigationRailDestination(
                        icon: Icon(destination.$1),
                        label: Text(destination.$2)),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  /// Desktop sidebar: the same Home + section-headers + nested-tile tree the
  /// drawer builds, in a fixed-width persistent column. Tap indices line up
  /// with [_dockDestinationsFor] / [_featuresFor] (Home = 0, then every tile
  /// in section/preorder), so [_dockIndex] and routing are unchanged.
  Widget _buildRailTree(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final auth = context.read<AuthProvider>();
    final bootstrap = context.watch<BootstrapCache>();
    final tp = context.watch<ThemeProvider>();
    final navSections = _sectionsFor(auth.company, auth.user);

    final indexByKey = <String, int>{};
    var nextIndex = 1;
    for (final section in navSections) {
      for (final tile in section.tiles) {
        indexByKey[tile.key] = nextIndex++;
      }
    }

    final rows = <Widget>[
      ListTile(
        dense: true,
        leading: const Icon(Icons.home_outlined, size: 22),
        title: Text(l10n.navHome,
            style: TextStyle(
              fontWeight: _dockIndex == 0 ? FontWeight.w700 : FontWeight.normal,
              color: _dockIndex == 0
                  ? Theme.of(context).colorScheme.primary
                  : null,
            )),
        selected: _dockIndex == 0,
        onTap: () => _onDockItemSelected(context, 0),
      ),
    ];

    if (navSections.isEmpty) {
      rows.add(bootstrap.isNavigationLoading
          ? const _DrawerNavigationSkeleton()
          : _DrawerNavigationUnavailable(
              message: bootstrap.navigationError ??
                  'No navigation items are available for this account.',
              onRetry: () => context.read<LocaleProvider>().refreshFromServer(),
            ));
    }

    for (final section in navSections) {
      final childrenByParent = <String?, List<_FeatureTile>>{};
      for (final tile in section.tiles) {
        final parent = section.parentByKey[tile.key];
        (childrenByParent[parent] ??= []).add(tile);
      }

      Widget branch(
        _FeatureTile tile,
        int depth, {
        List<_FeatureTile>? childOverride,
        bool sectionParent = false,
      }) {
        final nested = childOverride ??
            childrenByParent[tile.key] ??
            const <_FeatureTile>[];
        final index = indexByKey[tile.key]!;
        final selected = _dockIndex == index;
        final labelColor =
            selected ? Theme.of(context).colorScheme.primary : null;
        final label = Text(
          bootstrap.resolveNavigationLabel(tile.key, tile.titleOf(l10n)),
          style: TextStyle(
            fontSize: depth > 0 ? 13 : 14,
            fontWeight: sectionParent || selected
                ? FontWeight.w700
                : (depth > 0 ? FontWeight.w500 : FontWeight.normal),
            letterSpacing: sectionParent ? 0.2 : null,
            color: labelColor,
          ),
        );
        final leading = Icon(
          tile.icon,
          size: depth == 0 ? 22 : 18,
          color: selected
              ? Theme.of(context).colorScheme.primary
              : (tp.drawerTextColor ??
                  (sectionParent
                      ? (Theme.of(context).brightness == Brightness.dark
                          ? const Color(0xFFE2E8F0)
                          : const Color(0xFF334155))
                      : null)),
        );
        final pad = EdgeInsets.only(left: 12 + depth * 20, right: 8);

        if (nested.isEmpty) {
          return ListTile(
            key: ValueKey('rail-item-${tile.key}'),
            dense: true,
            contentPadding: pad,
            leading: leading,
            title: label,
            selected: selected,
            onTap: () => _onDockItemSelected(context, index),
          );
        }

        return SplitNavigationTile(
          key: PageStorageKey<String>('rail-branch-${section.key}-${tile.key}'),
          mainTileKey: ValueKey('rail-item-${tile.key}'),
          expandButtonKey: ValueKey('rail-expand-${tile.key}'),
          contentPadding: pad,
          dense: true,
          selected: selected,
          selectedColor: Theme.of(context).colorScheme.primary,
          iconColor: Theme.of(context).colorScheme.onSurfaceVariant,
          initiallyExpanded: true,
          leading: leading,
          title: label,
          children: [
            for (final child in nested)
              branch(child, math.min(depth + 1, _maximumNavigationDepth)),
          ],
          onTap: () => _onDockItemSelected(context, index),
        );
      }

      final rootItems = childrenByParent[null] ?? const <_FeatureTile>[];
      rows.add(Padding(
        key: ValueKey('rail-section-divider-${section.key}'),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        child: Divider(
          height: 1,
          thickness: 1,
          color: Theme.of(context).dividerColor.withValues(alpha: 0.18),
        ),
      ));

      final sectionTitle = section.header?.call(l10n) ?? '';
      if (sectionTitle.isNotEmpty) {
        rows.add(Padding(
          key: ValueKey('rail-section-title-${section.key}'),
          padding: const EdgeInsets.fromLTRB(14, 2, 10, 4),
          child: Text(
            sectionTitle.toUpperCase(),
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.8,
              color: section.headerColor ??
                  Theme.of(context).colorScheme.onSurfaceVariant,
            ),
          ),
        ));
      }

      for (final root in rootItems) {
        rows.add(branch(
          root,
          0,
          childOverride: childrenByParent[root.key],
          sectionParent: true,
        ));
      }
    }

    final user = auth.user;
    final colorScheme = Theme.of(context).colorScheme;

    // Width is owned by the enclosing [DockRailSlot] (viewport-clamped);
    // this just fills it.
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: rows,
            ),
          ),
        ),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            border: Border(
              top: BorderSide(
                color: colorScheme.outlineVariant.withValues(alpha: 0.5),
              ),
            ),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (user != null) ...[
                Row(
                  children: [
                    CircleAvatar(
                      radius: 16,
                      backgroundColor:
                          colorScheme.primary.withValues(alpha: 0.12),
                      child: Text(
                        (user.name)
                            .trim()
                            .split(RegExp(r'\s+'))
                            .where((part) => part.isNotEmpty)
                            .take(2)
                            .map((part) => part[0])
                            .join()
                            .toUpperCase(),
                        style: TextStyle(
                          color: colorScheme.primary,
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            user.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          Text(
                            user.email,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: colorScheme.onSurfaceVariant,
                              fontSize: 10,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
              ],
              Builder(
                builder: (ctx) {
                  final branding = ctx.read<PlatformBrandingProvider>();
                  final phone = (bootstrap.config['support_whatsapp'] ??
                          bootstrap.config['support_phone'] ??
                          branding.supportPhone)
                      .toString()
                      .trim();
                  final effectivePhone =
                      phone.isNotEmpty ? phone : '+918535075196';

                  return ListTile(
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                    leading: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        color: const Color(0xFF10B981).withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: const Icon(Icons.support_agent,
                          color: Color(0xFF10B981), size: 16),
                    ),
                    title: const Text(
                      'Help & Support',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 12,
                      ),
                    ),
                    subtitle: Text(
                      effectivePhone,
                      style: TextStyle(
                        color: colorScheme.onSurfaceVariant,
                        fontSize: 10,
                      ),
                    ),
                    trailing: const Icon(Icons.chevron_right, size: 14),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8)),
                    onTap: () => _launchHelpSupport(ctx, effectivePhone),
                  );
                },
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildUserAccountMenu(BuildContext context, UserModel? user) {
    final initials = (user?.name ?? '')
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .take(2)
        .map((part) => part[0])
        .join()
        .toUpperCase();

    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final l10n = AppLocalizations.of(context);

    return PopupMenuButton<String>(
      tooltip: 'Account & Settings',
      offset: const Offset(0, 48),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      icon: CircleAvatar(
        radius: 16,
        backgroundColor: colorScheme.primaryContainer,
        child: Text(
          initials.isNotEmpty ? initials : 'U',
          style: TextStyle(
            color: colorScheme.onPrimaryContainer,
            fontSize: 12,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
      onSelected: (value) {
        switch (value) {
          case 'preferences':
            Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const AppPreferencesScreen()),
            );
            break;
          case 'change_password':
            Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const ChangePasswordScreen()),
            );
            break;
          case 'server':
            Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => ServerSettingsScreen(
                  preferences: context.read<AppPreferences>(),
                ),
              ),
            );
            break;
          case 'logout':
            _confirmLogout(context);
            break;
        }
      },
      itemBuilder: (context) => [
        if (user != null) ...[
          PopupMenuItem<String>(
            enabled: false,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  user.name,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: colorScheme.onSurface,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                Text(
                  user.email,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: colorScheme.onSurfaceVariant,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                if (user.role.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: colorScheme.primary.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Text(
                        user.role.toUpperCase(),
                        style: TextStyle(
                          fontSize: 9,
                          fontWeight: FontWeight.w700,
                          color: colorScheme.primary,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          const PopupMenuDivider(),
        ],
        const PopupMenuItem<String>(
          value: 'preferences',
          child: Row(
            children: [
              Icon(Icons.tune, size: 20),
              SizedBox(width: 12),
              Text('Preferences'),
            ],
          ),
        ),
        const PopupMenuItem<String>(
          value: 'change_password',
          child: Row(
            children: [
              Icon(Icons.lock_outline, size: 20),
              SizedBox(width: 12),
              Text('Change Password'),
            ],
          ),
        ),
        PopupMenuItem<String>(
          value: 'server',
          child: Row(
            children: [
              const Icon(Icons.dns_outlined, size: 20),
              const SizedBox(width: 12),
              Text(l10n.serverAddress),
            ],
          ),
        ),
        const PopupMenuDivider(),
        const PopupMenuItem<String>(
          value: 'logout',
          child: Row(
            children: [
              Icon(Icons.logout, color: Color(0xFFEF4444), size: 20),
              SizedBox(width: 12),
              Text(
                'Log Out',
                style: TextStyle(
                  color: Color(0xFFEF4444),
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  PreferredSizeWidget _buildTopDock(BuildContext context) {
    final destinations = _dockDestinationsFor(
        AppLocalizations.of(context),
        context.read<AuthProvider>().company,
        context.read<AuthProvider>().user);
    return PreferredSize(
      preferredSize: const Size.fromHeight(52),
      child: SizedBox(
        height: 52,
        child: ListView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          children: [
            for (var i = 0; i < destinations.length; i++)
              _DockChip(
                icon: destinations[i].$1,
                label: destinations[i].$2,
                selected: _dockIndex == i,
                onTap: () => _onDockItemSelected(context, i),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildBottomDock(BuildContext context) {
    final destinations = _dockDestinationsFor(
        AppLocalizations.of(context),
        context.read<AuthProvider>().company,
        context.read<AuthProvider>().user);
    return Material(
      elevation: 8,
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 64,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            children: [
              for (var i = 0; i < destinations.length; i++)
                _DockChip(
                  icon: destinations[i].$1,
                  label: destinations[i].$2,
                  selected: _dockIndex == i,
                  onTap: () => _onDockItemSelected(context, i),
                ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final bootstrap = context.watch<BootstrapCache>();
    // Rebuild the whole shell (drawer / rail / bars included) when the display
    // language changes, so nav labels re-resolve without reopening the drawer.
    context.watch<LocaleProvider>();
    final company = auth.company;
    final l10n = AppLocalizations.of(context);
    final dock = context.watch<NavDockProvider>().position;
    final wide = isWide(context);

    // Exactly one dock rendering is live per position — never duplicated
    // across a drawer / rail / bar at the same time. The Left/Right rails are
    // both mounted (as `DockRailSlot`s in the body Row) but only the active
    // side expands; the other collapses to zero width, so switching Left <->
    // Right can animate and never strands a stale rail on the far edge.
    Widget? drawer;
    Widget? endDrawer;
    Widget? bottomBar;
    PreferredSizeWidget? appBarBottom;

    final railOnLeft = wide && dock == NavDockPosition.left;
    final railOnRight = wide && dock == NavDockPosition.right;

    switch (dock) {
      case NavDockPosition.left:
        if (!wide) {
          drawer = _buildDrawer(context, company, auth.user, bootstrap);
        }
        break;
      case NavDockPosition.right:
        if (!wide) {
          endDrawer = _buildDrawer(context, company, auth.user, bootstrap);
        }
        break;
      case NavDockPosition.top:
        appBarBottom = _buildTopDock(context);
        break;
      case NavDockPosition.bottom:
        bottomBar = _buildBottomDock(context);
        break;
    }

    return Scaffold(
      key: _scaffoldKey,
      appBar: AppBar(
        title: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            TenantLogoAvatar(
              imageUrl: company?.logoUrl ?? bootstrap.logoUrl,
              tenantName: company?.tradeName ?? company?.name,
              size: 28,
              borderRadius: BorderRadius.circular(6),
            ),
            const SizedBox(width: 8),
            Flexible(
              child: Text(
                company?.tradeName ?? company?.name ?? 'Sales & Inventory',
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
        elevation: 0,
        bottom: appBarBottom,
        actions: [
          const SyncStatusBadge(),
          const _ThemeModeButton(),
          IconButton(
            tooltip: l10n.refresh,
            icon: const Icon(Icons.refresh),
            onPressed: () {
              setState(() {
                _analyticsFuture = _loadAnalytics();
              });
              _loadDashboardChrome();
              context.read<AuthProvider>().reloadSession();
              context.read<LocaleProvider>().refreshFromServer();
            },
          ),
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                icon: const Icon(Icons.notifications_outlined, size: 24),
                tooltip: 'Alerts & Reminders',
                onPressed: _openNotificationFeed,
              ),
              if (_notificationBadgeCount > 0)
                Positioned(
                  right: 8,
                  top: 8,
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: const BoxDecoration(
                      color: Color(0xFFEF4444),
                      shape: BoxShape.circle,
                    ),
                    constraints:
                        const BoxConstraints(minWidth: 16, minHeight: 16),
                    child: Text(
                      _notificationBadgeCount > 99
                          ? '99+'
                          : '$_notificationBadgeCount',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 9,
                        fontWeight: FontWeight.bold,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(width: 4),
          _buildUserAccountMenu(context, auth.user),
          const SizedBox(width: 4),
          // endDrawer has no automatic AppBar affordance the way `drawer`
          // does, so add one explicitly when the dock is docked right.
          if (endDrawer != null)
            IconButton(
              tooltip: l10n.menu,
              icon: const Icon(Icons.menu),
              onPressed: () => _scaffoldKey.currentState?.openEndDrawer(),
            ),
        ],
      ),
      drawer: drawer,
      endDrawer: endDrawer,
      bottomNavigationBar: bottomBar,
      body: Row(
        children: [
          // Left rail slot — expands only when docked left on a wide viewport,
          // otherwise animates to zero width (250ms).
          DockRailSlot(
            side: NavDockPosition.left,
            active: railOnLeft,
            builder: _buildRailContent,
          ),
          Expanded(
            // Scoped to just the scrollable content, not the whole Row —
            // otherwise a scrollable left/right rail sitting in the same
            // subtree could also trigger this pull-to-refresh.
            child: RefreshIndicator(
              onRefresh: () async {
                setState(() {
                  _analyticsFuture = _loadAnalytics();
                });
                await _loadDashboardChrome();
              },
              child: AnimatedPadding(
                duration: const Duration(milliseconds: 250),
                curve: Curves.easeOutCubic,
                // A small breathing gap on the docked edge so content and
                // form controls never butt up against the rail divider; it
                // slides with the rail when the dock position changes.
                padding: EdgeInsets.only(
                  left: railOnLeft ? 8 : 0,
                  right: railOnRight ? 8 : 0,
                ),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 1200),
                    child: ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        FutureBuilder<AnalyticsModel>(
                          future: _analyticsFuture,
                          builder: (context, snapshot) {
                            if (snapshot.connectionState ==
                                ConnectionState.waiting) {
                              return const Padding(
                                padding: EdgeInsets.symmetric(vertical: 48),
                                child: Center(
                                  child: CircularProgressIndicator(),
                                ),
                              );
                            }
                            if (snapshot.hasError || !snapshot.hasData) {
                              return Card(
                                margin:
                                    const EdgeInsets.symmetric(vertical: 16),
                                child: Padding(
                                  padding: const EdgeInsets.all(20),
                                  child: Column(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Icon(Icons.storefront,
                                          size: 48,
                                          color: Theme.of(context)
                                              .colorScheme
                                              .primary),
                                      const SizedBox(height: 12),
                                      Text(
                                        company?.tradeName ??
                                            company?.name ??
                                            'Sales & Inventory',
                                        style: Theme.of(context)
                                            .textTheme
                                            .titleMedium
                                            ?.copyWith(
                                                fontWeight: FontWeight.bold),
                                      ),
                                      const SizedBox(height: 8),
                                      Text(
                                        'Open the drawer to access POS, Sales, Products & Settings.',
                                        textAlign: TextAlign.center,
                                        style: Theme.of(context)
                                            .textTheme
                                            .bodyMedium
                                            ?.copyWith(
                                                color: Colors.grey.shade600),
                                      ),
                                      const SizedBox(height: 16),
                                      OutlinedButton.icon(
                                        icon: const Icon(Icons.refresh),
                                        label: Text(l10n.refresh),
                                        onPressed: () {
                                          setState(() {
                                            _analyticsFuture = _loadAnalytics();
                                          });
                                          _loadDashboardChrome();
                                        },
                                      ),
                                    ],
                                  ),
                                ),
                              );
                            }
                            return _DashboardAnalytics(
                              analytics: snapshot.data!,
                              formatter: CurrencyFormatter(
                                  company?.currencySymbol ?? '\$'),
                              onFilter: _pickDateRange,
                            );
                          },
                        ),
                        const SizedBox(height: 8),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
          // Right rail slot — mirror of the left slot; expands only when
          // docked right on a wide viewport.
          DockRailSlot(
            side: NavDockPosition.right,
            active: railOnRight,
            builder: _buildRailContent,
          ),
        ],
      ),
    );
  }
}

/// Compact pulsing placeholders keep the drawer's dynamic region visibly in
/// a loading state instead of leaving a large unexplained white gap.
class _DrawerNavigationSkeleton extends StatefulWidget {
  const _DrawerNavigationSkeleton();

  @override
  State<_DrawerNavigationSkeleton> createState() =>
      _DrawerNavigationSkeletonState();
}

class _DrawerNavigationSkeletonState extends State<_DrawerNavigationSkeleton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _opacity;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 850),
    )..repeat(reverse: true);
    _opacity = Tween<double>(begin: 0.35, end: 0.75).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme.surfaceContainerHighest;
    Widget bar(double width, double height) => Container(
          width: width,
          height: height,
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(height / 2),
          ),
        );

    return FadeTransition(
      opacity: _opacity,
      child: Padding(
        key: const ValueKey('drawer-navigation-loading'),
        padding: const EdgeInsets.fromLTRB(20, 20, 16, 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            bar(112, 10),
            const SizedBox(height: 18),
            for (var index = 0; index < 4; index++) ...[
              Row(
                children: [
                  bar(22, 22),
                  const SizedBox(width: 16),
                  bar(index.isEven ? 150 : 118, 13),
                ],
              ),
              const SizedBox(height: 20),
            ],
          ],
        ),
      ),
    );
  }
}

class _DrawerNavigationUnavailable extends StatelessWidget {
  const _DrawerNavigationUnavailable({
    required this.message,
    required this.onRetry,
  });

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Padding(
      key: const ValueKey('drawer-navigation-unavailable'),
      padding: const EdgeInsets.fromLTRB(20, 18, 16, 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.cloud_off_outlined,
            size: 20,
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  message,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: 4),
                TextButton.icon(
                  onPressed: onRetry,
                  icon: const Icon(Icons.refresh, size: 18),
                  label: const Text('Retry navigation'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// One destination in the Top/Bottom dock — a tappable icon-over-label
/// column, since neither [TabBar] nor [BottomNavigationBar] scroll well
/// past a handful of items and this app has ~20 destinations.
class _DockChip extends StatelessWidget {
  const _DockChip(
      {required this.icon,
      required this.label,
      required this.selected,
      required this.onTap});

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected
        ? Theme.of(context).colorScheme.primary
        : Theme.of(context).colorScheme.onSurfaceVariant;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 22, color: color),
            const SizedBox(height: 2),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                  fontSize: 10,
                  color: color,
                  fontWeight: selected ? FontWeight.bold : FontWeight.normal),
            ),
          ],
        ),
      ),
    );
  }
}

/// Condensed analytics summary shown directly on the dashboard — the full
/// breakdown (payment methods, complete top-products list) stays on the
/// dedicated Analytics screen.
class _DashboardAnalytics extends StatelessWidget {
  const _DashboardAnalytics(
      {required this.analytics, required this.formatter, this.onFilter});

  final AnalyticsModel analytics;
  final CurrencyFormatter formatter;
  final VoidCallback? onFilter;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final layout = context.watch<NavDockProvider>().dashboardLayout;

    void open(String key) => Navigator.of(context).push(
          MaterialPageRoute(
              builder: SduiComponentRegistry.instance.resolve(key)),
        );

    void openSalesForTag(String tag) => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => SalesScreen(initialFilter: tag)),
        );

    final Widget layoutBody = switch (layout) {
      DashboardLayout.oroit => OroitDashboardHome(
          analytics: analytics,
          formatter: formatter,
          onFilter: onFilter,
          onAddProduct: () => open('inventory'),
          onOpenTransactions: () => open('sales'),
        ),
      DashboardLayout.posh => PoshDashboardHome(
          analytics: analytics,
          formatter: formatter,
          onFilter: onFilter,
          onTagTap: openSalesForTag,
          onAddProduct: () => open('inventory'),
          onOpenTransactions: () => open('sales'),
          onOpenCustomers: () => open('customers'),
        ),
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _DashboardShortcutRow(),
        if (analytics.lowStockCount > 0) ...[
          const SizedBox(height: 12),
          _StatusBanner(
            container: scheme.warningContainer,
            onContainer: scheme.onWarningContainer,
            accent: scheme.warningAccent,
            icon: Icons.warning_amber_outlined,
            title: l10n.lowStockWarning(analytics.lowStockCount),
            onTap: () => open('inventory'),
          ),
        ],
        if (analytics.totalReceivables > 0) ...[
          const SizedBox(height: 12),
          _StatusBanner(
            container: scheme.infoContainer,
            onContainer: scheme.onInfoContainer,
            accent: scheme.infoAccent,
            icon: Icons.request_page_outlined,
            title: l10n.featureDueReceivables,
            subtitle:
                '${formatter.format(analytics.totalReceivables)} outstanding',
            onTap: () => open('due_receivables'),
          ),
        ],
        const SizedBox(height: 16),
        layoutBody,
      ],
    );
  }
}

/// Header toggle for the app-wide light / dark / system theme, mirroring the
/// same control in Settings ▸ Appearance so it's reachable from anywhere.
class _ThemeModeButton extends StatelessWidget {
  const _ThemeModeButton();

  @override
  Widget build(BuildContext context) {
    final theme = context.watch<ThemeProvider>();
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return PopupMenuButton<ThemeMode>(
      tooltip: 'Theme',
      icon: Icon(isDark ? Icons.dark_mode : Icons.light_mode),
      initialValue: theme.themeMode,
      onSelected: theme.setThemeMode,
      itemBuilder: (context) => const [
        PopupMenuItem(
          value: ThemeMode.system,
          child: ListTile(
            dense: true,
            contentPadding: EdgeInsets.zero,
            leading: Icon(Icons.brightness_auto_outlined),
            title: Text('Match device'),
          ),
        ),
        PopupMenuItem(
          value: ThemeMode.light,
          child: ListTile(
            dense: true,
            contentPadding: EdgeInsets.zero,
            leading: Icon(Icons.light_mode_outlined),
            title: Text('Light'),
          ),
        ),
        PopupMenuItem(
          value: ThemeMode.dark,
          child: ListTile(
            dense: true,
            contentPadding: EdgeInsets.zero,
            leading: Icon(Icons.dark_mode_outlined),
            title: Text('Dark'),
          ),
        ),
      ],
    );
  }
}

/// Actionable quick-launch shortcuts across the top of the dashboard — each
/// pushes straight into its module. Restaurant-only shortcuts (Pending KOTs,
/// Tables) are hidden unless the authenticated tenant actually runs a
/// kitchen: they never render for a retail / pharmacy / repair store.
class _DashboardShortcutRow extends StatelessWidget {
  const _DashboardShortcutRow();

  @override
  Widget build(BuildContext context) {
    // Rebuild once the bootstrap payload (mode + module features) lands.
    context.watch<BootstrapCache>();
    final module = BootstrapCache.instance.activeModule;
    final mode = BootstrapCache.instance.activeMode.toLowerCase();
    final hasKitchen = module.featureEnabled('has_kot') ||
        mode.contains('restaurant') ||
        mode.contains('cafe') ||
        mode.contains('food');
    final hasTables = module.featureEnabled('has_tables') || hasKitchen;

    final shortcuts = <(IconData, String, String)>[
      (Icons.point_of_sale, 'Quick Sale', 'pos'),
      (Icons.person_add_alt_1, 'New Customer', 'customers'),
      (Icons.lock_open, 'Open Register', 'cash_register'),
      if (hasTables) (Icons.table_restaurant, 'Tables', 'floor_plan'),
      if (hasKitchen) (Icons.receipt_long, 'Pending KOTs', 'kitchen_display'),
    ];
    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [
        for (final (icon, label, key) in shortcuts)
          _ShortcutButton(
            icon: icon,
            label: label,
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(
                  builder: SduiComponentRegistry.instance.resolve(key)),
            ),
          ),
      ],
    );
  }
}

class _ShortcutButton extends StatefulWidget {
  const _ShortcutButton(
      {required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  State<_ShortcutButton> createState() => _ShortcutButtonState();
}

class _ShortcutButtonState extends State<_ShortcutButton> {
  bool _hovered = false;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return MouseRegion(
      cursor: SystemMouseCursors.click,
      onEnter: (_) => setState(() => _hovered = true),
      onExit: (_) => setState(() => _hovered = false),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 140),
        decoration: BoxDecoration(
          color: _hovered
              ? scheme.primary.withValues(alpha: 0.12)
              : Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: _hovered ? scheme.primary : scheme.outlineVariant,
          ),
        ),
        child: TappableScale(
          onTap: widget.onTap,
          borderRadius: BorderRadius.circular(10),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(widget.icon, size: 18, color: scheme.primary),
                const SizedBox(width: 8),
                Text(widget.label,
                    style: TextStyle(
                        fontWeight: FontWeight.w600, color: scheme.onSurface)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// A tappable status strip (low stock, receivables, …) that keeps its text
/// readable in both themes via [StatusPalette] tones.
class _StatusBanner extends StatelessWidget {
  const _StatusBanner({
    required this.container,
    required this.onContainer,
    required this.accent,
    required this.icon,
    required this.title,
    this.subtitle,
    required this.onTap,
  });

  final Color container;
  final Color onContainer;
  final Color accent;
  final IconData icon;
  final String title;
  final String? subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: container,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Row(
            children: [
              Icon(icon, color: accent),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(title,
                        style: TextStyle(
                            fontWeight: FontWeight.w600, color: onContainer)),
                    if (subtitle != null)
                      Text(subtitle!,
                          style: TextStyle(
                              fontSize: 12.5,
                              color: onContainer.withValues(alpha: 0.85))),
                  ],
                ),
              ),
              Icon(Icons.chevron_right,
                  color: onContainer.withValues(alpha: 0.7)),
            ],
          ),
        ),
      ),
    );
  }
}
