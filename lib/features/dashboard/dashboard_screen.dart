import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/storage/app_preferences.dart';
import '../../core/widgets/coming_soon_screen.dart';
import '../analytics/screens/analytics_screen.dart';
import '../auth/auth_provider.dart';
import '../cash_register/screens/cash_register_screen.dart';
import '../catalog_admin/screens/catalog_admin_screen.dart';
import '../customers/screens/customers_screen.dart';
import '../inventory/screens/inventory_screen.dart';
import '../payables/screens/payables_screen.dart';
import '../pos/screens/pos_screen.dart';
import '../quotations/screens/quotations_screen.dart';
import '../reports/screens/reports_screen.dart';
import '../sales/screens/sales_screen.dart';
import '../settings/screens/tenant_settings_screen.dart';
import '../settings/server_settings_screen.dart';
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
  _FeatureTile('Inventory', Icons.inventory_2_outlined, (_) => const InventoryScreen()),
  _FeatureTile('Catalog Admin', Icons.category_outlined, (_) => const CatalogAdminScreen()),
  _FeatureTile('Customers', Icons.people_outline, (_) => const CustomersScreen()),
  _FeatureTile('Cash Register', Icons.savings_outlined, (_) => const CashRegisterScreen()),
  _FeatureTile('Payables', Icons.request_quote_outlined, (_) => const PayablesScreen()),
  _FeatureTile('Reports', Icons.insights_outlined, (_) => const ReportsScreen()),
  _FeatureTile('Taxes', Icons.percent_outlined, (_) => const TaxesScreen()),
  _FeatureTile('Analytics', Icons.bar_chart_outlined, (_) => const AnalyticsScreen()),
  _FeatureTile('Subscription', Icons.workspace_premium_outlined, (_) => const SubscriptionScreen()),
  _FeatureTile('Settings', Icons.settings_outlined, (_) => const TenantSettingsScreen()),
];

/// The post-login home base. Each feature module still under construction
/// falls back to a [ComingSoonScreen] placeholder — swap in the real screen
/// as it lands and give its [_FeatureTile] a builder.
class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

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

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final company = auth.company;
    final user = auth.user;

    return Scaffold(
      appBar: AppBar(
        title: Text(company?.tradeName ?? 'Zoom POS'),
        actions: [
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
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (user != null)
            Text(
              'Welcome back, ${user.name}',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
            ),
          if (company != null) ...[
            const SizedBox(height: 4),
            Text(
              '${company.planName[0].toUpperCase()}${company.planName.substring(1)} plan',
              style: TextStyle(color: Colors.grey.shade600),
            ),
          ],
          const SizedBox(height: 20),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: 1.3,
            children: [
              for (final feature in _features)
                Card(
                  child: InkWell(
                    borderRadius: BorderRadius.circular(12),
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: feature.builder ??
                            (_) => ComingSoonScreen(title: feature.title, icon: feature.icon),
                      ),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(feature.icon, size: 32, color: Theme.of(context).colorScheme.primary),
                          const SizedBox(height: 10),
                          Text(feature.title, textAlign: TextAlign.center),
                        ],
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
