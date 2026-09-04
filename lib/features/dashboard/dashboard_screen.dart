import 'dart:math' as math;

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_client.dart';
import '../../core/config/bootstrap_cache.dart';
import '../../core/config/nav_dock_provider.dart';
import '../../core/config/theme_provider.dart';
import '../../core/models/analytics_model.dart';
import '../../core/models/company_model.dart';
import '../../core/models/user_model.dart';
import '../../core/sdui/models/sdui_models.dart';
import '../../core/sdui/sdui_component_registry.dart';
import '../../core/sdui/sdui_icon_registry.dart';
import '../../core/services/sync/sync_status_badge.dart';
import '../../core/storage/app_preferences.dart';
import '../../core/utils/currency_formatter.dart';
import '../../core/utils/responsive.dart';
import '../../core/widgets/coming_soon_screen.dart';
import '../../l10n/app_localizations.dart';
import '../analytics/analytics_repository.dart';
import '../analytics/widgets/analytics_widgets.dart';
import '../auth/auth_provider.dart';
import '../settings/screens/change_password_screen.dart';
import '../settings/server_settings_screen.dart';
import '../settings/settings_repository.dart';

const int _maximumNavigationDepth = 2;

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

  bool visibleTo(UserModel? user) =>
      permissionModule == null ||
      user == null ||
      user.can('$permissionModule.view');
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

  for (final s in sduiSections) {
    final tiles = <_FeatureTile>[];
    final parentByKey = <String, String>{};
    final seenKeys = <String>{};

    void collectItems(List<SduiNavItemSchema> items, String? defaultParent) {
      for (final item in items) {
        if (item.key.isEmpty || !seenKeys.add(item.key)) continue;
        tiles.add(_FeatureTile(
          item.key,
          (l10n) => l10n.text(item.title, fallback: item.title),
          SduiIconRegistry.resolve(item.icon),
          SduiComponentRegistry.instance.resolve(
            item.component ?? item.key,
            targetEndpoint: item.targetEndpoint,
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
      }
    }

    collectItems(s.items, null);

    result.add(_NavSection(
      s.key,
      (l10n) => l10n.text(s.title, fallback: s.title),
      tiles,
      headerColor:
          s.color != null ? SduiIconRegistry.parseColor(s.color) : null,
      parentByKey: parentByKey,
    ));
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
      (tilesBySection[targetSection] ??= []).add((
        order: override?.order ?? index,
        fallback: fallback++,
        tile: tile,
      ));
    }
  }

  final result = <_NavSection>[];
  for (final entry in tilesBySection.entries) {
    final rows = entry.value;
    final rowByKey = {for (final row in rows) row.tile.key: row};
    final safeParent = <String, String?>{};

    for (final row in rows) {
      final item = itemOverrides[row.tile.key];
      final rawParent = item?.parentId ??
          item?.parent ??
          sectionMetaByKey[entry.key]?.parentByKey[row.tile.key];
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
        cursor = itemOverrides[cursor]?.parentId ??
            itemOverrides[cursor]?.parent ??
            sectionMetaByKey[entry.key]?.parentByKey[cursor];
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

    result.add(_NavSection(
      entry.key,
      sectionMetaByKey[entry.key]!.header,
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
  late final AnalyticsRepository _analyticsRepository;
  late Future<AnalyticsModel> _analyticsFuture;

  /// 0 = Home (this screen); 1..N = `_featuresFor(company)[index - 1]`.
  /// Shared by whichever nav dock is active — see [NavDockProvider].
  int _dockIndex = 0;

  @override
  void initState() {
    super.initState();
    _analyticsRepository = AnalyticsRepository(context.read<ApiClient>());
    _analyticsFuture = _analyticsRepository.fetchAnalytics();
    context
        .read<ThemeProvider>()
        .refreshFromServer(SettingsRepository(context.read<ApiClient>()));
  }

  Future<void> _confirmLogout(BuildContext context) async {
    final l10n = AppLocalizations.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.signOutConfirmTitle),
        content: Text(l10n.signOutConfirmBody),
        actions: [
          TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: Text(l10n.cancel)),
          TextButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: Text(l10n.signOut)),
        ],
      ),
    );

    if (confirmed == true && context.mounted) {
      await context.read<AuthProvider>().logout();
    }
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

  /// Structured, mode-isolated drawer: a Home tile followed by every
  /// [_NavSection] with its own header — the mobile equivalent of the web
  /// tenant sidebar's slide-out drawer (see layouts/tenant.blade.php).
  /// Section headers are omitted from the rail/top bar/bottom bar (see
  /// [_dockDestinationsFor]), which just render the same tiles flat.
  Widget _buildDrawer(
      BuildContext context, CompanyModel? company, UserModel? user) {
    final l10n = AppLocalizations.of(context);

    final coverUrl = company?.drawerCoverUrl;
    final hasCover = coverUrl != null && coverUrl.isNotEmpty;

    final children = <Widget>[
      Container(
        width: double.infinity,
        padding: EdgeInsets.fromLTRB(
            16, MediaQuery.of(context).padding.top + 12, 16, 14),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.primary,
          image: hasCover
              ? DecorationImage(
                  image: CachedNetworkImageProvider(coverUrl),
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
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              company?.tradeName ?? company?.name ?? 'Sales & Inventory',
              style: const TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 4),
            Row(
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    BootstrapCache.instance.activeModule.title.toUpperCase(),
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.95),
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
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

    for (final section in navSections) {
      if (section.header != null) {
        children.add(Padding(
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 6),
          child: Text(
            section.header!(l10n).toUpperCase(),
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.6,
              color: section.headerColor ??
                  Theme.of(context).colorScheme.onSurfaceVariant,
            ),
          ),
        ));
      }

      final childrenByParent = <String?, List<_FeatureTile>>{};
      for (final tile in section.tiles) {
        (childrenByParent[section.parentByKey[tile.key]] ??= []).add(tile);
      }

      bool branchContainsSelection(_FeatureTile tile) {
        if (indexByKey[tile.key] == _dockIndex) return true;
        return (childrenByParent[tile.key] ?? const <_FeatureTile>[])
            .any(branchContainsSelection);
      }

      Widget buildBranch(_FeatureTile tile, int depth) {
        final nested = childrenByParent[tile.key] ?? const <_FeatureTile>[];
        final index = indexByKey[tile.key]!;
        final padding = EdgeInsets.only(
          left: 16 + depth * 24,
          right: 12,
        );

        if (nested.isEmpty) {
          return ListTile(
            key: ValueKey('drawer-item-${tile.key}'),
            contentPadding: padding,
            dense: depth > 0,
            leading: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (depth > 0) ...[
                  Text(
                    '↳',
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.outline,
                      fontSize: 13,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(width: 6),
                ],
                Icon(tile.icon, size: depth == 0 ? 24 : 18),
              ],
            ),
            title: Text(
              tile.titleOf(l10n),
              style: TextStyle(
                fontSize: depth > 0 ? 13 : 14,
                fontWeight: _dockIndex == index
                    ? FontWeight.w700
                    : (depth > 0 ? FontWeight.w500 : FontWeight.normal),
                color: _dockIndex == index
                    ? Theme.of(context).colorScheme.primary
                    : null,
              ),
            ),
            selected: _dockIndex == index,
            onTap: () => openTile(tile),
          );
        }

        return ExpansionTile(
          key: PageStorageKey<String>(
            'drawer-branch-${section.key}-${tile.key}',
          ),
          tilePadding: padding,
          childrenPadding: EdgeInsets.zero,
          leading: Icon(tile.icon, size: depth == 0 ? 24 : 20),
          initiallyExpanded: branchContainsSelection(tile),
          maintainState: true,
          shape: const Border(),
          collapsedShape: const Border(),
          title: Text(
            tile.titleOf(l10n),
            style: TextStyle(
              fontWeight:
                  _dockIndex == index ? FontWeight.w700 : FontWeight.normal,
              color: _dockIndex == index
                  ? Theme.of(context).colorScheme.primary
                  : null,
            ),
          ),
          children: [
            for (final child in nested)
              buildBranch(
                child,
                math.min(depth + 1, _maximumNavigationDepth),
              ),
          ],
        );
      }

      for (final root in childrenByParent[null] ?? const <_FeatureTile>[]) {
        children.add(buildBranch(root, 0));
      }
    }

    children.add(const Divider());
    children.add(ListTile(
      leading: const Icon(Icons.lock_reset_outlined),
      title: Text(l10n.changePassword),
      onTap: () {
        Navigator.of(context).pop();
        Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => const ChangePasswordScreen()));
      },
    ));

    return Drawer(
      child: SafeArea(
        top: false,
        bottom: true,
        child: ListView(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).padding.bottom + 16,
          ),
          children: children,
        ),
      ),
    );
  }

  /// A persistent rail for the Left/Right dock positions on tablet/desktop
  /// widths — wrapped in a scroll view since a rail doesn't scroll on its
  /// own and this app has far more destinations than fit most window
  /// heights.
  Widget _buildRail(BuildContext context) {
    final extended = MediaQuery.sizeOf(context).width >= Breakpoints.desktop;
    return SingleChildScrollView(
      child: NavigationRail(
        extended: extended,
        selectedIndex: _dockIndex,
        onDestinationSelected: (index) => _onDockItemSelected(context, index),
        labelType: extended
            ? NavigationRailLabelType.none
            : NavigationRailLabelType.all,
        destinations: [
          for (final destination in _dockDestinationsFor(
              AppLocalizations.of(context),
              context.read<AuthProvider>().company,
              context.read<AuthProvider>().user))
            NavigationRailDestination(
                icon: Icon(destination.$1), label: Text(destination.$2)),
        ],
      ),
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
    final company = auth.company;
    final l10n = AppLocalizations.of(context);
    final dock = context.watch<NavDockProvider>().position;
    final wide = isWide(context);

    // Exactly one of these ends up set, per the active dock position —
    // never duplicated across a drawer/rail/bar at the same time.
    Widget? drawer;
    Widget? endDrawer;
    Widget? bottomBar;
    PreferredSizeWidget? appBarBottom;
    Widget? leftRail;
    Widget? rightRail;

    switch (dock) {
      case NavDockPosition.left:
        if (wide) {
          leftRail = _buildRail(context);
        } else {
          drawer = _buildDrawer(context, company, auth.user);
        }
        break;
      case NavDockPosition.right:
        if (wide) {
          rightRail = _buildRail(context);
        } else {
          endDrawer = _buildDrawer(context, company, auth.user);
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
        title: Text(company?.tradeName ?? company?.name ?? 'Sales & Inventory'),
        elevation: 0,
        bottom: appBarBottom,
        actions: [
          const SyncStatusBadge(),
          IconButton(
            tooltip: l10n.refresh,
            icon: const Icon(Icons.refresh),
            onPressed: () => setState(() {
              _analyticsFuture = _analyticsRepository.fetchAnalytics();
            }),
          ),
          IconButton(
            tooltip: l10n.serverAddress,
            icon: const Icon(Icons.dns_outlined),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => ServerSettingsScreen(
                    preferences: context.read<AppPreferences>()),
              ),
            ),
          ),
          IconButton(
            tooltip: l10n.signOut,
            icon: const Icon(Icons.logout),
            onPressed: () => _confirmLogout(context),
          ),
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
          if (leftRail != null) ...[leftRail, const VerticalDivider(width: 1)],
          Expanded(
            // Scoped to just the scrollable content, not the whole Row —
            // otherwise a scrollable left/right rail sitting in the same
            // subtree could also trigger this pull-to-refresh.
            child: RefreshIndicator(
              onRefresh: () async {
                setState(() {
                  _analyticsFuture = _analyticsRepository.fetchAnalytics();
                });
              },
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 1200),
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      FutureBuilder<AnalyticsModel>(
                        future: _analyticsFuture,
                        builder: (context, snapshot) {
                          if (snapshot.connectionState !=
                                  ConnectionState.done ||
                              !snapshot.hasData) {
                            return const SizedBox.shrink();
                          }
                          return _DashboardAnalytics(
                            analytics: snapshot.data!,
                            formatter: CurrencyFormatter(
                                company?.currencySymbol ?? '\$'),
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
          if (rightRail != null) ...[
            const VerticalDivider(width: 1),
            rightRail
          ],
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
  const _DashboardAnalytics({required this.analytics, required this.formatter});

  final AnalyticsModel analytics;
  final CurrencyFormatter formatter;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        LayoutBuilder(
          builder: (context, constraints) {
            final isSmall = constraints.maxWidth < 360;
            return GridView.count(
              crossAxisCount: isSmall ? 1 : 3,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: isSmall ? 3.2 : 1.15,
              children: [
                KpiCard(
                    label: l10n.todaysSales,
                    value: formatter.format(analytics.todayRevenue)),
                KpiCard(
                    label: l10n.ordersToday,
                    value: analytics.todayOrders.toString()),
                KpiCard(
                    label: l10n.avgOrder,
                    value: formatter.format(analytics.averageOrderValue)),
              ],
            );
          },
        ),
        if (analytics.lowStockCount > 0) ...[
          const SizedBox(height: 12),
          Card(
            color: Colors.orange.shade50,
            child: ListTile(
              leading: Icon(Icons.warning_amber_outlined,
                  color: Colors.orange.shade800),
              title: Text(l10n.lowStockWarning(analytics.lowStockCount)),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute(
                    builder:
                        SduiComponentRegistry.instance.resolve('inventory')),
              ),
            ),
          ),
        ],
        if (analytics.totalReceivables > 0) ...[
          const SizedBox(height: 12),
          Card(
            color: Colors.amber.shade50,
            child: ListTile(
              leading: Icon(Icons.request_page_outlined,
                  color: Colors.amber.shade800),
              title: Text(l10n.featureDueReceivables),
              subtitle: Text(
                  '${formatter.format(analytics.totalReceivables)} outstanding'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute(
                    builder: SduiComponentRegistry.instance
                        .resolve('due_receivables')),
              ),
            ),
          ),
        ],
        if (analytics.revenueTrend.isNotEmpty) ...[
          const SizedBox(height: 16),
          Text(l10n.revenueTrend,
              style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          SizedBox(
              height: 140,
              child: RevenueTrendChart(points: analytics.revenueTrend)),
        ],
        if (analytics.topProducts.isNotEmpty) ...[
          const SizedBox(height: 16),
          Text(l10n.topSelling, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          Card(
            child: Column(
              children: [
                for (final product in analytics.topProducts.take(3))
                  ListTile(
                    dense: true,
                    title: Text(product.name),
                    subtitle:
                        Text('${product.unitsSold.toStringAsFixed(0)} sold'),
                    trailing: Text(formatter.format(product.revenue),
                        style: const TextStyle(fontWeight: FontWeight.w600)),
                  ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}
