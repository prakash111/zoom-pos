import 'package:flutter/material.dart';

import '../../features/auth/screens/login_screen.dart';
import '../../features/auth/screens/register_screen.dart';
import '../../features/customers/screens/customers_screen.dart';
import '../../features/inventory/screens/inventory_screen.dart';
import '../../features/landing/screens/landing_screen.dart';
import '../../features/settings/screens/document_templates_tab.dart';
import '../../features/storefront/screens/storefront_screen.dart';
import '../../features/sales/screens/sales_screen.dart';
import 'screens/dynamic_schema_page.dart';
import '../../features/stores/store_management_screen.dart';

/// The business-page and public route resolver in the Flutter shell.
class AppRouter {
  AppRouter._();

  static Route<dynamic> onGenerateRoute(RouteSettings settings) {
    final requested = settings.name?.trim() ?? '';
    final normalized = requested.toLowerCase().replaceFirst(RegExp(r'^/+'), '');

    if (normalized == 'settings/store/branches') {
      return MaterialPageRoute<void>(
          settings: settings, builder: (_) => const StoreManagementScreen());
    }

    if (normalized == 'settings/store/templates') {
      return MaterialPageRoute<void>(
        settings: settings,
        builder: (_) => const DocumentTemplatesScreen(),
      );
    }

    if (normalized == 'store' ||
        normalized == 'storefront' ||
        normalized == 'shop' ||
        normalized.startsWith('c/')) {
      return MaterialPageRoute<void>(
        settings: settings,
        builder: (_) => const StorefrontScreen(),
      );
    }

    if (normalized == 'landing' || normalized == 'home') {
      return MaterialPageRoute<void>(
        settings: settings,
        builder: (_) => const LandingScreen(),
      );
    }

    if (normalized == 'login') {
      return MaterialPageRoute<void>(
        settings: settings,
        builder: (_) => const LoginScreen(),
      );
    }

    if (normalized == 'register') {
      return MaterialPageRoute<void>(
        settings: settings,
        builder: (_) => const RegisterScreen(),
      );
    }

    if (normalized == 'sales' ||
        normalized == 'sales/invoices' ||
        normalized == 'invoices' ||
        normalized == 'orders' ||
        normalized == 'order' ||
        normalized == 'transactions' ||
        normalized == 'tenant/sales') {
      final args = settings.arguments;
      String? initialDueFilter;
      if (args is Map) {
        initialDueFilter = (args['initial_due_filter'] ??
                args['due_filter'] ??
                args['filter'])
            ?.toString();
      }
      return MaterialPageRoute<void>(
        settings: settings,
        builder: (_) => SalesScreen(
          initialDueFilter: initialDueFilter,
        ),
      );
    }

    if (normalized == 'customers' ||
        normalized == 'customer' ||
        normalized == 'crm/customers' ||
        normalized == 'crm') {
      return MaterialPageRoute<void>(
        settings: settings,
        builder: (_) => const CustomersScreen(),
      );
    }

    if (normalized == 'inventory' ||
        normalized == 'products' ||
        normalized == 'product' ||
        normalized == 'stock') {
      final args = settings.arguments;
      final filter = args is Map ? args['filter']?.toString() : null;
      return MaterialPageRoute<void>(
        settings: settings,
        builder: (_) => InventoryScreen(initialFilter: filter),
      );
    }

    final endpoint = requested.startsWith('/api/')
        ? requested
        : '/api/tenant/views/${requested.replaceFirst(RegExp(r'^/+'), '')}';

    return MaterialPageRoute<void>(
      settings: settings,
      builder: (_) => DynamicSchemaPage(endpoint: endpoint),
    );
  }
}
