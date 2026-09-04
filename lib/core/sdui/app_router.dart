import 'package:flutter/material.dart';

import 'screens/dynamic_schema_page.dart';

/// The only business-page route resolver in the Flutter shell.
class AppRouter {
  AppRouter._();

  static Route<dynamic> onGenerateRoute(RouteSettings settings) {
    final requested = settings.name?.trim() ?? '';
    final endpoint = requested.startsWith('/api/')
        ? requested
        : '/api/tenant/views/${requested.replaceFirst(RegExp(r'^/+'), '')}';

    return MaterialPageRoute<void>(
      settings: settings,
      builder: (_) => DynamicSchemaPage(endpoint: endpoint),
    );
  }
}
