import 'package:flutter/material.dart';

import '../../features/cash_register/screens/cash_register_screen.dart';
import '../../features/devices/screens/devices_screen.dart';
import '../../features/languages/screens/languages_screen.dart';
import '../../features/pos/screens/pos_screen.dart';
import '../../features/restaurant/screens/restaurant_pos_screen.dart';
import '../../features/settings/screens/tenant_settings_screen.dart';
import '../../features/staff/screens/staff_screen.dart';
import '../../features/subscription/screens/subscription_screen.dart';
import '../config/bootstrap_cache.dart';
import '../widgets/coming_soon_screen.dart';
import 'components/navigation_tree_builder.dart';
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
    'cash_register': (_) => const CashRegisterScreen(),

    // Core Screens
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
    _registry[_normalize(componentKey)] = builder;
  }

  /// Whether the given component key has a registered builder.
  bool has(String componentKey) {
    return _registry.containsKey(_normalize(componentKey));
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

    if (componentKey == null || componentKey.trim().isEmpty) {
      return (_) =>
          const ComingSoonScreen(title: 'Module', icon: Icons.widgets_outlined);
    }

    final key = _normalize(componentKey);
    final builder = _registry[key];
    if (builder != null) return builder;

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
          endpoint: '/api/tenant/views/${key.replaceAll('_', '-')}',
          initialTitle: componentKey,
        );
  }

  static String _normalize(String value) => value.toLowerCase().trim();
}
