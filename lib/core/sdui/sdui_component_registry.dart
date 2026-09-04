import 'package:flutter/material.dart';

import '../../features/analytics/screens/analytics_screen.dart';
import '../../features/cash_register/screens/cash_register_screen.dart';
import '../../features/catalog/screens/catalog_screen.dart';
import '../../features/catalog_admin/screens/catalog_admin_screen.dart';
import '../../features/consignments/screens/consignments_screen.dart';
import '../../features/customers/screens/customers_screen.dart';
import '../../features/devices/screens/devices_screen.dart';
import '../../features/inventory/screens/inventory_management_screen.dart';
import '../../features/languages/screens/languages_screen.dart';
import '../../features/payables/screens/payables_screen.dart';
import '../../features/pos/screens/pos_screen.dart';
import '../../features/quotations/screens/quotations_screen.dart';
import '../../features/receivables/screens/due_receivables_screen.dart';
import '../../features/reports/screens/reports_screen.dart';
import '../../features/restaurant/screens/restaurant_kds_screen.dart';
import '../../features/restaurant/screens/restaurant_pos_screen.dart';
import '../../features/restaurant/screens/restaurant_tables_screen.dart';
import '../../features/sales/screens/sales_screen.dart';
import '../../features/sales_targets/screens/sales_targets_screen.dart';
import '../../features/service_orders/screens/service_orders_screen.dart';
import 'components/navigation_tree_builder.dart';
import '../../features/settings/screens/tenant_settings_screen.dart';
import '../../features/staff/screens/staff_screen.dart';
import '../../features/subscription/screens/subscription_screen.dart';
import '../../features/taxes/screens/taxes_screen.dart';
import '../config/bootstrap_cache.dart';
import '../widgets/coming_soon_screen.dart';
import 'screens/dynamic_module_screen.dart';
import 'screens/dynamic_schema_page.dart';

/// Registry mapping server-driven component identifiers to screen builders.
class SduiComponentRegistry {
  SduiComponentRegistry._();

  static final SduiComponentRegistry instance = SduiComponentRegistry._();

  final Map<String, WidgetBuilder> _registry = {
    // POS & Terminals
    'pos': (_) => const PosScreen(),
    'restaurant_pos': (_) => const RestaurantPosScreen(),
    'floor_plan': (_) => const RestaurantTablesScreen(),
    'kitchen_display': (_) => const RestaurantKdsScreen(),

    // Cash & Sales
    'sales': (_) => const SalesScreen(),
    'dining_history': (_) => const SalesScreen(),
    'cash_register': (_) => const CashRegisterScreen(),
    'quotations': (_) => const QuotationsScreen(),
    'consignments': (_) => const ConsignmentsScreen(),
    'service_orders': (_) => const ServiceOrdersScreen(),
    'customers': (_) => const CustomersScreen(),

    // Financial
    'due_receivables': (_) => const DueReceivablesScreen(),
    'accounts_receivable': (_) => const DueReceivablesScreen(),
    'payables': (_) => const PayablesScreen(),
    'accounts_payable': (_) => const PayablesScreen(),
    'sales_targets': (_) => const SalesTargetsScreen(),
    'reports': (_) => const ReportsScreen(),
    'reports_analytics': (_) => const ReportsScreen(),
    'analytics': (_) => const AnalyticsScreen(),

    // Inventory & Catalog
    'inventory': (_) => const InventoryManagementScreen(),
    'menu_dishes': (_) => const InventoryManagementScreen(),
    'categories': (_) => const CategoriesScreen(),
    'brands': (_) => const BrandsScreen(),
    'units': (_) => const UnitsScreen(),
    'suppliers': (_) => const SuppliersScreen(),
    'food_suppliers': (_) => const SuppliersScreen(),
    'taxes': (_) => const TaxesScreen(),
    'catalog': (_) => const CatalogScreen(),

    // Legacy native destinations. New and settings destinations arrive with
    // target_endpoint and bypass this compatibility registry entirely.
    'subscription': (_) => const SubscriptionScreen(),
    'settings': (_) => const TenantSettingsScreen(),
    'navigation': (_) => const NavMenuSettingsTab(),
    'navigation_menu': (_) => const NavMenuSettingsTab(),
    'settings-navigation': (_) => const NavMenuSettingsTab(),
    'settings_navigation': (_) => const NavMenuSettingsTab(),
    'tree_builder': (_) => const NavMenuSettingsTab(),
    'navigation_builder': (_) => const NavMenuSettingsTab(),
    'languages': (_) => const LanguagesScreen(),
    'staff': (_) => const StaffScreen(),
    'devices': (_) => const DevicesScreen(),
    'dynamic_page': (_) => const DynamicSchemaPage(),
  };

  /// Register or override a component builder at runtime.
  void register(String componentKey, WidgetBuilder builder) {
    _registry[componentKey.toLowerCase().trim()] = builder;
  }

  /// Whether the given component key has a registered builder.
  bool has(String componentKey) {
    return _registry.containsKey(componentKey.toLowerCase().trim());
  }

  /// Resolves a screen builder for the given component/tile key.
  /// If [targetEndpoint] is provided, dynamically resolves to [DynamicSchemaPage].
  /// Unfamiliar/unmapped paths route purely from JSON via [DynamicSchemaPage].
  WidgetBuilder resolve(String? componentKey, {String? targetEndpoint}) {
    if (targetEndpoint != null && targetEndpoint.trim().isNotEmpty) {
      return (_) => DynamicSchemaPage(
            endpoint: targetEndpoint.trim(),
            initialTitle: componentKey,
          );
    }

    final key = componentKey?.toLowerCase().trim();
    if (key != null && key.isNotEmpty) {
      final builder = _registry[key];
      if (builder != null) return builder;
    }

    if (componentKey == null || componentKey.trim().isEmpty) {
      return (_) =>
          const ComingSoonScreen(title: 'Module', icon: Icons.widgets_outlined);
    }

    // Check if the key corresponds to a registered server module
    final module = BootstrapCache.instance.modules[key];
    if (module != null) {
      return (context) => DynamicModuleScreen(
            module: module,
            onAction: (action) {
              final actionBuilder = resolve(action);
              Navigator.of(context)
                  .push(MaterialPageRoute(builder: actionBuilder));
            },
          );
    }

    // Route all unfamiliar paths to DynamicSchemaPage so new pages render purely from JSON
    return (_) => DynamicSchemaPage(
          endpoint: '/api/tenant/views/${key!.replaceAll('_', '-')}',
          initialTitle: componentKey,
        );
  }
}
