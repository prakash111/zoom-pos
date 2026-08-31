import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_client.dart';
import '../../core/config/theme_provider.dart';
import '../../core/models/analytics_model.dart';
import '../../core/storage/app_preferences.dart';
import '../../core/utils/currency_formatter.dart';
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
  _FeatureTile(this.title, this.icon, [this.builder]);

  final String title;
  final IconData icon;

  /// Screen this tile opens. Falls back to [ComingSoonScreen] when a module
  /// hasn't been built yet.
  final WidgetBuilder? builder;
}

final List<_FeatureTile> _features = [
  _FeatureTile('Point of Sale', Icons.point_of_sale_outlined, (_) => const PosScreen()),
  _FeatureTile('Sales', Icons.receipt_long_outlined, (_) => const SalesScreen()),
  _FeatureTile('Quotations', Icons.description_outlined, (_) => const QuotationsScreen()),
  _FeatureTile('Inventory Management', Icons.inventory_2_outlined, (_) => const InventoryManagementScreen()),
  _FeatureTile('Customers', Icons.people_outline, (_) => const CustomersScreen()),
  _FeatureTile('Cash Register', Icons.savings_outlined, (_) => const CashRegisterScreen()),
  _FeatureTile('Payables', Icons.request_quote_outlined, (_) => const PayablesScreen()),
  _FeatureTile('Consignments', Icons.local_shipping_outlined, (_) => const ConsignmentsScreen()),
  _FeatureTile('Service Orders', Icons.handyman_outlined, (_) => const ServiceOrdersScreen()),
  _FeatureTile('Sales Targets', Icons.flag_outlined, (_) => const SalesTargetsScreen()),
  _FeatureTile('Reports', Icons.insights_outlined, (_) => const ReportsScreen()),
  _FeatureTile('Taxes', Icons.percent_outlined, (_) => const TaxesScreen()),
  _FeatureTile('Analytics', Icons.bar_chart_outlined, (_) => const AnalyticsScreen()),
  _FeatureTile('Subscription', Icons.workspace_premium_outlined, (_) => const SubscriptionScreen()),
  _FeatureTile('Staff & Access', Icons.badge_outlined, (_) => const StaffScreen()),
  _FeatureTile('Online Catalog', Icons.qr_code_outlined, (_) => const CatalogScreen()),
  _FeatureTile('Languages', Icons.translate_outlined, (_) => const LanguagesScreen()),
  _FeatureTile('Devices', Icons.devices_other_outlined, (_) => const DevicesScreen()),
  _FeatureTile('Settings', Icons.settings_outlined, (_) => const TenantSettingsScreen()),
];

/// The 4 most frequently used destinations, surfaced on the bottom
/// navigation bar. Resolved once (by title, against [_features]) at module
/// load rather than per-build/per-tap — a typo here throws immediately when
/// the dashboard first builds instead of silently no-op'ing on a tap deep
/// into a session.
final List<_FeatureTile> _bottomNavTiles = [
  _features.firstWhere((f) => f.title == 'Point of Sale'),
  _features.firstWhere((f) => f.title == 'Inventory Management'),
  _features.firstWhere((f) => f.title == 'Sales'),
  _features.firstWhere((f) => f.title == 'Settings'),
];

/// Short labels for [_bottomNavTiles] — the tiles' own titles are too long
/// for a fixed-type bottom bar with 5 items (e.g. "Inventory Management").
const List<String> _bottomNavLabels = ['POS', 'Inventory', 'Sales', 'Settings'];

/// The post-login home base. Each feature module still under construction
/// falls back to a [ComingSoonScreen] placeholder — swap in the real screen
/// as it lands and give its [_FeatureTile] a builder.
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  late final AnalyticsRepository _analyticsRepository;
  late Future<AnalyticsModel> _analyticsFuture;
  int _bottomNavIndex = 0;

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

  void _openFeature(BuildContext context, _FeatureTile feature) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: feature.builder ?? (_) => ComingSoonScreen(title: feature.title, icon: feature.icon),
      ),
    );
  }

  void _onBottomNavTap(int index) {
    if (index == 0) return; // Home — already showing.

    setState(() => _bottomNavIndex = index);
    Navigator.of(context)
        .push(
          MaterialPageRoute(
            builder: _bottomNavTiles[index - 1].builder ??
                (_) => ComingSoonScreen(title: _bottomNavTiles[index - 1].title, icon: _bottomNavTiles[index - 1].icon),
          ),
        )
        .then((_) {
      // The bar is a quick-launcher over the app's stack-based navigation,
      // not a persistent multi-tab shell — always settle back on Home once
      // the pushed screen is popped.
      if (mounted) setState(() => _bottomNavIndex = 0);
    });
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final company = auth.company;
    final l10n = AppLocalizations.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(company?.tradeName ?? company?.name ?? 'Sales & Inventory'),
        elevation: 0,
        actions: [
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
        ],
      ),
      drawer: Drawer(
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
            for (final feature in _features)
              ListTile(
                leading: Icon(feature.icon),
                title: Text(feature.title),
                onTap: () {
                  Navigator.of(context).pop();
                  _openFeature(context, feature);
                },
              ),
          ],
        ),
      ),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _bottomNavIndex,
        onTap: _onBottomNavTap,
        type: BottomNavigationBarType.fixed,
        items: [
          const BottomNavigationBarItem(icon: Icon(Icons.home_outlined), label: 'Home'),
          for (var i = 0; i < _bottomNavTiles.length; i++)
            BottomNavigationBarItem(icon: Icon(_bottomNavTiles[i].icon), label: _bottomNavLabels[i]),
        ],
      ),
      body: RefreshIndicator(
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
