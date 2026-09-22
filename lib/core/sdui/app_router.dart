import 'package:flutter/material.dart';

import '../../features/auth/screens/login_screen.dart';
import '../../features/auth/screens/register_screen.dart';
import '../../features/landing/screens/landing_screen.dart';
import '../../features/settings/screens/document_templates_tab.dart';
import '../../features/storefront/screens/storefront_screen.dart';
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

    final endpoint = requested.startsWith('/api/')
        ? requested
        : '/api/tenant/views/${requested.replaceFirst(RegExp(r'^/+'), '')}';

    return MaterialPageRoute<void>(
      settings: settings,
      builder: (_) => DynamicSchemaPage(endpoint: endpoint),
    );
  }
}
