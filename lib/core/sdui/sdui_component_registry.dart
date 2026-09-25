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
import '../../features/pos_universal/screens/universal_pos_screen.dart';
import '../../features/quotations/screens/quotations_screen.dart';
import '../../features/receivables/screens/due_receivables_screen.dart';
import '../../features/reports/screens/reports_screen.dart';
import '../../features/restaurant/screens/restaurant_kds_screen.dart';
import '../../features/restaurant/screens/restaurant_pos_screen.dart';
import '../../features/restaurant/screens/restaurant_tables_screen.dart';
import '../../features/sales/screens/sales_screen.dart';
import '../../features/sales_targets/screens/sales_targets_screen.dart';
import '../../features/service_orders/screens/service_orders_screen.dart';
import '../../features/settings/screens/app_preferences_screen.dart';
import '../../features/settings/screens/change_password_screen.dart';
import '../../features/settings/screens/document_templates_tab.dart';
import '../../features/stores/store_management_screen.dart';
import '../../features/settings/screens/global_printer_setup_screen.dart';
import 'components/navigation_tree_builder.dart';
import '../../features/settings/screens/store_domain_screen.dart';
import '../../features/settings/screens/tenant_settings_screen.dart';
import '../../features/staff/screens/staff_screen.dart';
import '../../features/storefront/screens/product_reviews_screen.dart';
import '../../features/storefront/screens/store_inquiries_screen.dart';
import '../../features/storefront/screens/storefront_screen.dart';
import '../../features/subscription/screens/subscription_screen.dart';
import '../../features/taxes/screens/taxes_screen.dart';
import '../../features/auth/screens/login_screen.dart';
import '../../features/auth/screens/verify_otp_screen.dart';
import '../config/bootstrap_cache.dart';
import '../widgets/barcode_scanner_screen.dart';
import '../widgets/coming_soon_screen.dart';
import 'screens/dynamic_module_screen.dart';
import 'screens/dynamic_schema_page.dart';

/// Registry mapping server-driven component identifiers to screen builders.
class SduiComponentRegistry {
  SduiComponentRegistry._();

  static final SduiComponentRegistry instance = SduiComponentRegistry._();

  static Widget _resolveServiceOrdersScreen() {
    final mode = BootstrapCache.instance.activeMode.toLowerCase().trim();
    final isRepairMode = mode.contains('repair') ||
        mode.contains('auto') ||
        mode.contains('tech') ||
        mode.contains('service_order');
    if (!isRepairMode) {
      return const DynamicSchemaPage(
        endpoint: '/api/tenant/views/service-catalog',
        initialTitle: 'Service Catalog & Rates',
      );
    }
    return const ServiceOrdersScreen();
  }

  final Map<String, WidgetBuilder> _registry = {
    // POS & Terminals
    'pos': (_) => const PosScreen(),
    'point_of_sale': (_) => const PosScreen(),
    'restaurant_pos': (_) => const RestaurantPosScreen(),
    'floor_plan': (_) => const RestaurantTablesScreen(),
    'kitchen_display': (_) => const RestaurantKdsScreen(),
    // Pharmacy checkouts run through the core native POS now — the old
    // '/api/tenant/views/pharmacy-pos' UniversalPosScreen (rendered as the
    // broken "Pharmacy Counter POS") is retired. Alias the key to PosScreen so
    // every resolution path (drawer, quick-actions, custom nav) lands there.
    'pharmacy_pos': (_) => const PosScreen(),
    'repair_pos': (_) =>
        const UniversalPosScreen(endpoint: '/api/tenant/views/repair-pos'),
    'salon_pos': (_) =>
        const UniversalPosScreen(endpoint: '/api/tenant/views/salon-pos'),

    // Cash & Sales
    'sales': (_) => const SalesScreen(),
    'sale': (_) => const SalesScreen(),
    'orders': (_) => const SalesScreen(),
    'order': (_) => const SalesScreen(),
    'invoices': (_) => const SalesScreen(),
    'invoice': (_) => const SalesScreen(),
    'sales_history': (_) => const SalesScreen(),
    'history': (_) => const SalesScreen(),
    'dining_history': (_) => const SalesScreen(),
    'cash_register': (_) => const CashRegisterScreen(),
    'quotations': (_) => const QuotationsScreen(),
    'quotes': (_) => const QuotationsScreen(),
    '/tenant/views/quotations': (_) => const QuotationsScreen(),
    '/api/tenant/views/quotations': (_) => const QuotationsScreen(),
    'consignments': (_) => const ConsignmentsScreen(),
    '/consignments': (_) => const ConsignmentsScreen(),
    '/tenant/views/consignments': (_) => const ConsignmentsScreen(),
    '/api/tenant/views/consignments': (_) => const ConsignmentsScreen(),
    'lead_management': (_) => const DynamicSchemaPage(
          endpoint: '/api/tenant/views/leads',
          initialTitle: 'Lead Management',
        ),
    'leads': (_) => const DynamicSchemaPage(
          endpoint: '/api/tenant/views/leads',
          initialTitle: 'Lead Management',
        ),
    '/tenant/views/leads': (_) => const DynamicSchemaPage(
          endpoint: '/api/tenant/views/leads',
          initialTitle: 'Lead Management',
        ),
    '/api/tenant/views/leads': (_) => const DynamicSchemaPage(
          endpoint: '/api/tenant/views/leads',
          initialTitle: 'Lead Management',
        ),
    'service_orders': (_) => _resolveServiceOrdersScreen(),
    'service-orders': (_) => _resolveServiceOrdersScreen(),
    'new_prescription_intake': (_) => const DynamicSchemaPage(
          endpoint: '/api/tenant/views/pharmacy-rx-create',
          initialTitle: 'New Prescription Intake',
        ),
    'pharmacy_rx_create': (_) => const DynamicSchemaPage(
          endpoint: '/api/tenant/views/pharmacy-rx-create',
          initialTitle: 'New Prescription Intake',
        ),
    'pharmacy-rx-create': (_) => const DynamicSchemaPage(
          endpoint: '/api/tenant/views/pharmacy-rx-create',
          initialTitle: 'New Prescription Intake',
        ),
    'prescriptions_queue': (_) => const DynamicSchemaPage(
          endpoint: '/api/tenant/views/pharmacy-prescriptions',
          initialTitle: 'Prescriptions & Patient Queue',
        ),
    'pharmacy_prescriptions': (_) => const DynamicSchemaPage(
          endpoint: '/api/tenant/views/pharmacy-prescriptions',
          initialTitle: 'Prescriptions & Patient Queue',
        ),
    'pharmacy-prescriptions': (_) => const DynamicSchemaPage(
          endpoint: '/api/tenant/views/pharmacy-prescriptions',
          initialTitle: 'Prescriptions & Patient Queue',
        ),
    'batch_inventory': (_) => const DynamicSchemaPage(
          endpoint: '/api/tenant/views/pharmacy-batches',
          initialTitle: 'Drug Batches & Expiry Tracker',
        ),
    'pharmacy_batches': (_) => const DynamicSchemaPage(
          endpoint: '/api/tenant/views/pharmacy-batches',
          initialTitle: 'Drug Batches & Expiry Tracker',
        ),
    'verify_otp': (_) => const VerifyOtpScreen(email: ''),
    'verify-otp': (_) => const VerifyOtpScreen(email: ''),
    'verify_email': (_) => const VerifyOtpScreen(email: ''),
    'change_password': (_) => const ChangePasswordScreen(),
    'customers': (_) => const CustomersScreen(),
    '/customers': (_) => const CustomersScreen(),
    '/tenant/views/customers': (_) => const CustomersScreen(),
    '/api/tenant/views/customers': (_) => const CustomersScreen(),

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
    'products': (_) => const InventoryManagementScreen(),
    'stock': (_) => const InventoryManagementScreen(),
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
    'store_management': (_) => const StoreManagementScreen(),
    'nav_stores': (_) => const StoreManagementScreen(),
    '/settings/store/branches': (_) => const StoreManagementScreen(),
    'document_templates': (_) => const DocumentTemplatesScreen(),
    'nav_document_templates': (_) => const DocumentTemplatesScreen(),
    // Device-local workspace prefs (theme / page transition / dock position).
    'app_preferences': (_) => const AppPreferencesScreen(),
    'appearance': (_) => const AppPreferencesScreen(),
    'preferences': (_) => const AppPreferencesScreen(),
    'settings-appearance': (_) => const AppPreferencesScreen(),
    'settings_appearance': (_) => const AppPreferencesScreen(),
    'navigation': (_) => const NavMenuSettingsTab(),
    'navigation_menu': (_) => const NavMenuSettingsTab(),
    'settings-navigation': (_) => const NavMenuSettingsTab(),
    'settings_navigation': (_) => const NavMenuSettingsTab(),
    'tree_builder': (_) => const NavMenuSettingsTab(),
    'navigation_builder': (_) => const NavMenuSettingsTab(),
    'languages': (_) => const LanguagesScreen(),
    'staff': (_) => const StaffScreen(),
    'devices': (_) => const DevicesScreen(),
    // Global hardware pairing — same native screen for every operating mode,
    // surfaced from the drawer ("Printer & Hardware Setup") and Receipt Settings.
    'printer_setup': (_) => const GlobalPrinterSetupScreen(),
    'printer-setup': (_) => const GlobalPrinterSetupScreen(),
    'hardware_printer': (_) => const GlobalPrinterSetupScreen(),
    'hardware_settings': (_) => const GlobalPrinterSetupScreen(),
    'settings_coupons': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-coupons', initialTitle: 'Coupons & Discounts'),
    'settings-coupons': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-coupons', initialTitle: 'Coupons & Discounts'),
    'coupons': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-coupons', initialTitle: 'Coupons & Discounts'),
    'settings_faqs': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-faqs', initialTitle: 'Store FAQs & Help Center'),
    'settings-faqs': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-faqs', initialTitle: 'Store FAQs & Help Center'),
    'faqs': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-faqs', initialTitle: 'Store FAQs & Help Center'),
    'settings_reviews': (_) => const ProductReviewsScreen(),
    'settings-reviews': (_) => const ProductReviewsScreen(),
    'reviews': (_) => const ProductReviewsScreen(),
    'product_reviews': (_) => const ProductReviewsScreen(),
    'product-reviews': (_) => const ProductReviewsScreen(),
    'store_reviews': (_) => const ProductReviewsScreen(),
    'store-reviews': (_) => const ProductReviewsScreen(),
    'product_ratings_reviews': (_) => const ProductReviewsScreen(),
    'product-ratings-reviews': (_) => const ProductReviewsScreen(),
    'storefront_reviews': (_) => const ProductReviewsScreen(),
    'storefront-reviews': (_) => const ProductReviewsScreen(),
    'nav_store_reviews': (_) => const ProductReviewsScreen(),
    '/settings/storefront/reviews': (_) => const ProductReviewsScreen(),
    '/api/tenant/views/settings-reviews': (_) => const ProductReviewsScreen(),
    '/api/tenant/views/reviews': (_) => const ProductReviewsScreen(),
    'settings_notifications': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-notifications', initialTitle: 'Verification & Notifications'),
    'settings-notifications': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-notifications', initialTitle: 'Verification & Notifications'),
    'verification_notifications': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-notifications', initialTitle: 'Verification & Notifications'),
    'settings_integrations': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-integrations', initialTitle: 'API & Integrations'),
    'integrations': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-integrations', initialTitle: 'API & Integrations'),
    'storefront': (_) => const StorefrontScreen(),
    // Storefront Domain Setup
    'store_domain': (_) => const StoreDomainScreen(),
    'storefront_domain': (_) => const StoreDomainScreen(),
    'settings_storefront_domain': (_) => const StoreDomainScreen(),
    'settings-storefront-domain': (_) => const StoreDomainScreen(),
    '/settings/storefront/domain': (_) => const StoreDomainScreen(),
    '/api/tenant/views/settings-storefront-domain': (_) => const StoreDomainScreen(),
    '/api/tenant/views/storefront-domain': (_) => const StoreDomainScreen(),
    '/api/tenant/views/domain': (_) => const StoreDomainScreen(),
    // Storefront Customer Inquiries
    'store_inquiries': (_) => const StoreInquiriesScreen(),
    'storefront_inquiries': (_) => const StoreInquiriesScreen(),
    'inquiries': (_) => const StoreInquiriesScreen(),
    '/storefront/inquiries': (_) => const StoreInquiriesScreen(),
    '/api/tenant/views/storefront-inquiries': (_) => const StoreInquiriesScreen(),
    '/api/tenant/views/inquiries': (_) => const StoreInquiriesScreen(),
    // Storefront Navigation Menus & CMS Pages
    'storefront_menus': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-storefront-menus', initialTitle: 'Store Menus & CMS Pages'),
    'storefront-menus': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-storefront-menus', initialTitle: 'Store Menus & CMS Pages'),
    'settings_storefront_menus': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-storefront-menus', initialTitle: 'Store Menus & CMS Pages'),
    'settings-storefront-menus': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-storefront-menus', initialTitle: 'Store Menus & CMS Pages'),
    'nav_storefront_menus': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-storefront-menus', initialTitle: 'Store Menus & CMS Pages'),
    '/settings/storefront/menus': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-storefront-menus', initialTitle: 'Store Menus & CMS Pages'),
    '/api/tenant/views/settings-storefront-menus': (_) => const DynamicSchemaPage(endpoint: '/api/tenant/views/settings-storefront-menus', initialTitle: 'Store Menus & CMS Pages'),
    'dynamic_page': (_) => const DynamicSchemaPage(),
  };

  /// Universal Dynamic SDUI Route Resolver.
  /// Resolves any route to a screen widget. Reserved local overrides (login,
  /// barcode_scanner) and pre-registered native screens are resolved locally;
  /// every other menu route universally falls back to [DynamicSchemaPage] to
  /// load its schema dynamically from the server without requiring client code changes.
  static Widget resolveRoute(String route, {Map<String, dynamic>? arguments}) {
    final clean = route.trim();
    final key = clean.toLowerCase();

    // 1. Reserved local overrides (authentication, raw camera/hardware scanners)
    switch (key) {
      case 'login':
        return const LoginScreen();
      case 'barcode_scanner':
      case 'scanner':
        return const BarcodeScannerScreen();
      case 'printer_setup':
      case 'printer-setup':
      case 'hardware_settings':
      case 'hardware_printer':
        return const GlobalPrinterSetupScreen();
    }

    // Pre-registered native screens when invoked by route key or endpoint
    if (instance.has(key)) {
      final builder = instance._registry[key];
      if (builder != null) {
        return Builder(builder: builder);
      }
    }

    // 2. UNIVERSAL FALLBACK: Every other menu route loads dynamically from the server endpoint
    final title = arguments?['title']?.toString() ??
        arguments?['label']?.toString() ??
        '';
    final endpoint = clean.startsWith('/api/')
        ? clean
        : '/api/tenant/views/${clean.replaceAll('_', '-')}';

    return DynamicSchemaPage(
      endpoint: endpoint,
      initialTitle: title,
      title: title,
      arguments: arguments,
    );
  }

  /// Register or override a component builder at runtime.
  void register(String componentKey, WidgetBuilder builder) {
    _registry[componentKey.toLowerCase().trim()] = builder;
  }

  /// Whether the given component key has a registered builder.
  bool has(String componentKey) {
    return _registry.containsKey(componentKey.toLowerCase().trim());
  }

  /// Resolves a screen builder for the given component/tile key.
  /// Priority order:
  /// 1. Pre-registered native screens (POS, Invoices, Inventory, etc.)
  /// 2. Explicit [targetEndpoint] dynamically resolved via [DynamicSchemaPage]
  /// 3. Registered dynamic server modules via [DynamicModuleScreen]
  /// 4. Fallback [DynamicSchemaPage] for unfamiliar views
  WidgetBuilder resolve(String? componentKey,
      {String? targetEndpoint, String? title}) {
    final key = componentKey?.toLowerCase().trim();
    if (key != null && key.isNotEmpty) {
      final builder = _registry[key];
      if (builder != null) return builder;
    }

    if (targetEndpoint != null && targetEndpoint.trim().isNotEmpty) {
      return (_) => DynamicSchemaPage(
            endpoint: targetEndpoint.trim(),
            initialTitle: title ?? componentKey,
            title: title ?? componentKey,
            arguments: {
              'id': componentKey,
              'title': title ?? componentKey,
              'route': targetEndpoint.trim(),
            },
          );
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

    // Route all unfamiliar paths universally to DynamicSchemaPage so new pages render purely from JSON
    final clean = key!;
    final endpoint = clean.startsWith('/api/')
        ? clean
        : '/api/tenant/views/${clean.replaceAll('_', '-')}';
    return (_) => DynamicSchemaPage(
          endpoint: endpoint,
          initialTitle: title ?? componentKey,
          title: title ?? componentKey,
          arguments: {
            'id': componentKey,
            'title': title ?? componentKey,
            'route': endpoint,
          },
        );
  }
}
