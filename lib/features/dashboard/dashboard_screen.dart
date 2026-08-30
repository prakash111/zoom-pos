import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_client.dart';
import '../../core/models/analytics_model.dart';
import '../../core/storage/app_preferences.dart';
import '../../core/utils/currency_formatter.dart';
import '../../core/utils/responsive.dart';
import '../../core/widgets/coming_soon_screen.dart';
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

  @override
  void initState() {
    super.initState();
    _analyticsRepository = AnalyticsRepository(context.read<ApiClient>());
    _analyticsFuture = _analyticsRepository.fetchAnalytics();
  }

  Future<void> _confirmLogout(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Sign out?'),
        content: const Text("You'll need your password to sign back in."),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Sign out')),
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

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final company = auth.company;
    final user = auth.user;
    final primaryColor = Theme.of(context).colorScheme.primary;

    return Scaffold(
      appBar: AppBar(
        title: Text(company?.tradeName ?? company?.name ?? 'Zoom POS'),
        elevation: 0,
        actions: [
          IconButton(
            tooltip: 'Refresh',
            icon: const Icon(Icons.refresh),
            onPressed: () => setState(() {
              _analyticsFuture = _analyticsRepository.fetchAnalytics();
            }),
          ),
          IconButton(
            tooltip: 'Server address',
            icon: const Icon(Icons.dns_outlined),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => ServerSettingsScreen(preferences: context.read<AppPreferences>()),
              ),
            ),
          ),
          IconButton(
            tooltip: 'Sign out',
            icon: const Icon(Icons.logout),
            onPressed: () => _confirmLogout(context),
          ),
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
                Container(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [primaryColor, primaryColor.withOpacity(0.8)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: [
                      BoxShadow(
                        color: primaryColor.withOpacity(0.25),
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  padding: const EdgeInsets.all(20),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Welcome back, ${user?.name ?? 'Merchant'}',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              company != null
                                  ? '${company.name} · ${company.planName.toUpperCase()} PLAN'
                                  : 'Point of Sale & Business Suite',
                              style: TextStyle(color: Colors.white.withOpacity(0.9), fontSize: 13),
                            ),
                          ],
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.2),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.check_circle, color: Colors.white, size: 14),
                            SizedBox(width: 4),
                            Text('Online', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
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
                const SizedBox(height: 20),
                Text(
                  'Quick Access',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 12),
                LayoutBuilder(
                  builder: (context, constraints) => GridView.count(
                    crossAxisCount: gridColumnsFor(constraints.maxWidth, mobile: 2, tablet: 3, desktop: 4),
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    mainAxisSpacing: 12,
                    crossAxisSpacing: 12,
                    childAspectRatio: 1.3,
                    children: [
                      for (final feature in _features)
                        Card(
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          elevation: 1,
                          child: InkWell(
                            borderRadius: BorderRadius.circular(14),
                            onTap: () => _openFeature(context, feature),
                            child: Padding(
                              padding: const EdgeInsets.all(16),
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(feature.icon, size: 30, color: Theme.of(context).colorScheme.primary),
                                  const SizedBox(height: 10),
                                  Text(
                                    feature.title,
                                    textAlign: TextAlign.center,
                                    style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 24),
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
                KpiCard(label: "Today's sales", value: formatter.format(analytics.todayRevenue)),
                KpiCard(label: 'Orders today', value: analytics.todayOrders.toString()),
                KpiCard(label: 'Avg. order', value: formatter.format(analytics.averageOrderValue)),
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
              title: Text('${analytics.lowStockCount} product${analytics.lowStockCount == 1 ? '' : 's'} low on stock'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const InventoryManagementScreen()),
              ),
            ),
          ),
        ],
        if (analytics.revenueTrend.isNotEmpty) ...[
          const SizedBox(height: 16),
          Text('Revenue trend', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          SizedBox(height: 140, child: RevenueTrendChart(points: analytics.revenueTrend)),
        ],
        if (analytics.topProducts.isNotEmpty) ...[
          const SizedBox(height: 16),
          Text('Top selling', style: Theme.of(context).textTheme.titleMedium),
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
