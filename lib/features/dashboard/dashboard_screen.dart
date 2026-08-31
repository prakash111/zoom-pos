import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_client.dart';
import '../../core/config/nav_dock_provider.dart';
import '../../core/config/theme_provider.dart';
import '../../core/models/analytics_model.dart';
import '../../core/models/company_model.dart';
import '../../core/services/sync/sync_status_badge.dart';
import '../../core/storage/app_preferences.dart';
import '../../core/utils/currency_formatter.dart';
import '../../core/utils/responsive.dart';
import '../../core/widgets/coming_soon_screen.dart';
import '../../l10n/app_localizations.dart';
import '../analytics/analytics_repository.dart';
import '../analytics/screens/analytics_screen.dart';
import '../analytics/widgets/analytics_widgets.dart';
import '../auth/auth_provider.dart';
import '../cash_register/screens/cash_register_screen.dart';
import '../catalog/screens/catalog_screen.dart';
import '../consignments/screens/consignments_screen.dart';
import '../customers/screens/customers_screen.dart';
import '../devices/screens/devices_screen.dart';
import '../inventory/screens/inventory_management_screen.dart';
import '../languages/screens/languages_screen.dart';
import '../payables/screens/payables_screen.dart';
import '../pos/screens/pos_screen.dart';
import '../quotations/screens/quotations_screen.dart';
import '../reports/screens/reports_screen.dart';
import '../sales/screens/sales_screen.dart';
import '../sales_targets/screens/sales_targets_screen.dart';
import '../service_orders/screens/service_orders_screen.dart';
import '../settings/screens/tenant_settings_screen.dart';
import '../settings/server_settings_screen.dart';
import '../settings/settings_repository.dart';
import '../staff/screens/staff_screen.dart';
import '../subscription/screens/subscription_screen.dart';
import '../taxes/screens/taxes_screen.dart';

class _FeatureTile {
  _FeatureTile(this.titleOf, this.icon, [this.builder]);

  /// Resolves the display title from the active locale — a plain [String]
  /// would freeze at whatever locale was active when this module-level list
  /// was first built, so titles are only ever read through this at render
  /// time (see [DashboardScreen]'s dock builders).
  final String Function(AppLocalizations l10n) titleOf;
  final IconData icon;

  /// Screen this tile opens. Falls back to [ComingSoonScreen] when a module
  /// hasn't been built yet.
  final WidgetBuilder? builder;
}

final List<_FeatureTile> _features = [
  _FeatureTile((l10n) => l10n.featurePos, Icons.point_of_sale_outlined, (_) => const PosScreen()),
  _FeatureTile((l10n) => l10n.featureSales, Icons.receipt_long_outlined, (_) => const SalesScreen()),
  _FeatureTile((l10n) => l10n.featureQuotations, Icons.description_outlined, (_) => const QuotationsScreen()),
  _FeatureTile((l10n) => l10n.featureInventory, Icons.inventory_2_outlined, (_) => const InventoryManagementScreen()),
  _FeatureTile((l10n) => l10n.featureCustomers, Icons.people_outline, (_) => const CustomersScreen()),
  _FeatureTile((l10n) => l10n.featureCashRegister, Icons.savings_outlined, (_) => const CashRegisterScreen()),
  _FeatureTile((l10n) => l10n.featurePayables, Icons.request_quote_outlined, (_) => const PayablesScreen()),
  _FeatureTile((l10n) => l10n.featureConsignments, Icons.local_shipping_outlined, (_) => const ConsignmentsScreen()),
  _FeatureTile((l10n) => l10n.featureServiceOrders, Icons.handyman_outlined, (_) => const ServiceOrdersScreen()),
  _FeatureTile((l10n) => l10n.featureSalesTargets, Icons.flag_outlined, (_) => const SalesTargetsScreen()),
  _FeatureTile((l10n) => l10n.featureReports, Icons.insights_outlined, (_) => const ReportsScreen()),
  _FeatureTile((l10n) => l10n.featureTaxes, Icons.percent_outlined, (_) => const TaxesScreen()),
  _FeatureTile((l10n) => l10n.featureAnalytics, Icons.bar_chart_outlined, (_) => const AnalyticsScreen()),
  _FeatureTile((l10n) => l10n.featureSubscription, Icons.workspace_premium_outlined, (_) => const SubscriptionScreen()),
  _FeatureTile((l10n) => l10n.featureStaff, Icons.badge_outlined, (_) => const StaffScreen()),
  _FeatureTile((l10n) => l10n.featureOnlineCatalog, Icons.qr_code_outlined, (_) => const CatalogScreen()),
  _FeatureTile((l10n) => l10n.featureLanguages, Icons.translate_outlined, (_) => const LanguagesScreen()),
  _FeatureTile((l10n) => l10n.featureDevices, Icons.devices_other_outlined, (_) => const DevicesScreen()),
  _FeatureTile((l10n) => l10n.featureSettings, Icons.settings_outlined, (_) => const TenantSettingsScreen()),
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

  /// 0 = Home (this screen); 1..N = `_features[index - 1]`. Shared by
  /// whichever nav dock is active — see [NavDockProvider].
  int _dockIndex = 0;

  @override
  void initState() {
    super.initState();
    _analyticsRepository = AnalyticsRepository(context.read<ApiClient>());
    _analyticsFuture = _analyticsRepository.fetchAnalytics();
    context.read<ThemeProvider>().refreshFromServer(SettingsRepository(context.read<ApiClient>()));
  }

  Future<void> _confirmLogout(BuildContext context) async {
    final l10n = AppLocalizations.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.signOutConfirmTitle),
        content: Text(l10n.signOutConfirmBody),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: Text(l10n.cancel)),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: Text(l10n.signOut)),
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
    final feature = _features[index - 1];
    final title = feature.titleOf(AppLocalizations.of(context));
    Navigator.of(context)
        .push(MaterialPageRoute(builder: feature.builder ?? (_) => ComingSoonScreen(title: title, icon: feature.icon)))
        .then((_) {
      if (mounted) setState(() => _dockIndex = 0);
    });
  }

  /// The dock's destinations as (icon, label) pairs — Home followed by every
  /// [_FeatureTile], with titles resolved from the active locale — shared by
  /// every dock rendering below so none of them can drift out of sync (or
  /// out of language) with each other.
  List<(IconData, String)> _dockDestinationsFor(AppLocalizations l10n) => [
        (Icons.home_outlined, l10n.navHome),
        for (final feature in _features) (feature.icon, feature.titleOf(l10n)),
      ];

  Widget _buildDrawer(BuildContext context, CompanyModel? company) {
    final destinations = _dockDestinationsFor(AppLocalizations.of(context));
    return Drawer(
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          DrawerHeader(
            decoration: BoxDecoration(color: Theme.of(context).colorScheme.primary),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                Text(
                  company?.tradeName ?? company?.name ?? 'Sales & Inventory',
                  style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                ),
                if (company != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    company.planName.toUpperCase(),
                    style: TextStyle(color: Colors.white.withOpacity(0.85), fontSize: 12),
                  ),
                ],
              ],
            ),
          ),
          for (var i = 0; i < destinations.length; i++)
            ListTile(
              leading: Icon(destinations[i].$1),
              title: Text(destinations[i].$2),
              selected: _dockIndex == i,
              onTap: () {
                Navigator.of(context).pop();
                _onDockItemSelected(context, i);
              },
            ),
        ],
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
        labelType: extended ? NavigationRailLabelType.none : NavigationRailLabelType.all,
        destinations: [
          for (final destination in _dockDestinationsFor(AppLocalizations.of(context)))
            NavigationRailDestination(icon: Icon(destination.$1), label: Text(destination.$2)),
        ],
      ),
    );
  }

  PreferredSizeWidget _buildTopDock(BuildContext context) {
    final destinations = _dockDestinationsFor(AppLocalizations.of(context));
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
    final destinations = _dockDestinationsFor(AppLocalizations.of(context));
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
          drawer = _buildDrawer(context, company);
        }
        break;
      case NavDockPosition.right:
        if (wide) {
          rightRail = _buildRail(context);
        } else {
          endDrawer = _buildDrawer(context, company);
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
                builder: (_) => ServerSettingsScreen(preferences: context.read<AppPreferences>()),
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
                          if (snapshot.connectionState != ConnectionState.done || !snapshot.hasData) {
                            return const SizedBox.shrink();
                          }
                          return _DashboardAnalytics(
                            analytics: snapshot.data!,
                            formatter: CurrencyFormatter(company?.currencySymbol ?? '\$'),
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
          if (rightRail != null) ...[const VerticalDivider(width: 1), rightRail],
        ],
      ),
    );
  }
}

/// One destination in the Top/Bottom dock — a tappable icon-over-label
/// column, since neither [TabBar] nor [BottomNavigationBar] scroll well
/// past a handful of items and this app has ~20 destinations.
class _DockChip extends StatelessWidget {
  const _DockChip({required this.icon, required this.label, required this.selected, required this.onTap});

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? Theme.of(context).colorScheme.primary : Theme.of(context).colorScheme.onSurfaceVariant;
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
              style: TextStyle(fontSize: 10, color: color, fontWeight: selected ? FontWeight.bold : FontWeight.normal),
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
                KpiCard(label: l10n.todaysSales, value: formatter.format(analytics.todayRevenue)),
                KpiCard(label: l10n.ordersToday, value: analytics.todayOrders.toString()),
                KpiCard(label: l10n.avgOrder, value: formatter.format(analytics.averageOrderValue)),
              ],
            );
          },
        ),
        if (analytics.lowStockCount > 0) ...[
          const SizedBox(height: 12),
          Card(
            color: Colors.orange.shade50,
            child: ListTile(
              leading: Icon(Icons.warning_amber_outlined, color: Colors.orange.shade800),
              title: Text(l10n.lowStockWarning(analytics.lowStockCount)),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const InventoryManagementScreen()),
              ),
            ),
          ),
        ],
        if (analytics.revenueTrend.isNotEmpty) ...[
          const SizedBox(height: 16),
          Text(l10n.revenueTrend, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          SizedBox(height: 140, child: RevenueTrendChart(points: analytics.revenueTrend)),
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
                    subtitle: Text('${product.unitsSold.toStringAsFixed(0)} sold'),
                    trailing: Text(formatter.format(product.revenue), style: const TextStyle(fontWeight: FontWeight.w600)),
                  ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}
