import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../l10n/translations_cache.dart';
import '../api/api_client.dart';
import '../models/settings_models.dart';
import '../sdui/models/sdui_models.dart';
import 'app_config.dart';

/// Single cold-start call to GET /app/bootstrap: this locale's translation
/// dictionary, active business module schemas, dynamic navigation menus,
/// UI configurations (payment methods, status labels, tax rules), and tenant settings.
class BootstrapCache extends ChangeNotifier {
  BootstrapCache._();

  static final BootstrapCache instance = BootstrapCache._();

  static const _navCacheKey = 'zoom_pos.bootstrap.nav';
  static const _configCacheKey = 'zoom_pos.bootstrap.config';
  static const _tenantCacheKey = 'zoom_pos.bootstrap.tenant';
  static const _modulesCacheKey = 'zoom_pos.bootstrap.modules';
  static const _menuCacheKey = 'zoom_pos.bootstrap.menu';
  static const _uiSchemaCacheKey = 'zoom_pos.bootstrap.ui_schema';

  TenantSchema? tenant;
  Map<String, ModuleSchema> modules = {};
  List<SduiNavSectionSchema> menuStructure = [];
  SduiUiSchema uiSchema = const SduiUiSchema();
  NavConfig navConfig = const NavConfig();
  Map<String, dynamic> config = {};

  String get activeMode =>
      tenant?.activeMode ??
      (config['pos_mode'] == 'restaurant' ? 'restaurant' : 'retail');

  List<String> get availableModes =>
      tenant?.availableModes.isNotEmpty == true
          ? tenant!.availableModes
          : const ['retail', 'restaurant', 'pharmacy', 'service_booking'];

  ModuleSchema get activeModule {
    final mode = activeMode;
    return modules[mode] ??
        ModuleSchema(
          id: mode,
          title: mode == 'restaurant' ? 'Restaurant & Cafe' : 'Retail POS',
          layoutType: mode == 'restaurant' ? 'table_floor_plan' : 'standard_grid',
          features: {
            'has_tables': mode == 'restaurant',
            'has_kot': mode == 'restaurant',
            'has_barcode_scanner': mode != 'restaurant',
            'has_due_reminders': mode != 'restaurant',
            'prep_timer': mode == 'restaurant',
            'order_alerts': mode == 'restaurant',
          },
        );
  }

  /// Server-driven navigation sections. Falls back to default mode sections
  /// if bootstrap has not yet synced from the network.
  List<SduiNavSectionSchema> get effectiveSections {
    if (menuStructure.isNotEmpty) {
      return menuStructure;
    }
    return _defaultFallbackSections();
  }

  SduiStatusSchema? statusFor(String domain, String statusKey) {
    return uiSchema.statusFor(domain, statusKey);
  }

  SduiPaymentMethodSchema? paymentMethodFor(String code) {
    final match = uiSchema.paymentMethods.where(
      (m) =>
          m.code.toLowerCase() == code.toLowerCase() ||
          m.id.toLowerCase() == code.toLowerCase(),
    );
    return match.isNotEmpty ? match.first : null;
  }

  Future<void> loadFromDisk() async {
    try {
      final prefs = await SharedPreferences.getInstance();

      final navRaw = prefs.getString(_navCacheKey);
      if (navRaw != null) {
        navConfig = NavConfig.fromJson(jsonDecode(navRaw) as Map<String, dynamic>);
      }

      final configRaw = prefs.getString(_configCacheKey);
      if (configRaw != null) {
        config = jsonDecode(configRaw) as Map<String, dynamic>;
      }

      final tenantRaw = prefs.getString(_tenantCacheKey);
      if (tenantRaw != null) {
        tenant = TenantSchema.fromJson(jsonDecode(tenantRaw) as Map<String, dynamic>);
      }

      final modulesRaw = prefs.getString(_modulesCacheKey);
      if (modulesRaw != null) {
        final rawMap = jsonDecode(modulesRaw) as Map<String, dynamic>;
        modules = rawMap.map(
          (k, v) => MapEntry(k, ModuleSchema.fromJson(v as Map<String, dynamic>)),
        );
      }

      final menuRaw = prefs.getString(_menuCacheKey);
      if (menuRaw != null) {
        final rawList = jsonDecode(menuRaw) as List<dynamic>;
        menuStructure = rawList
            .whereType<Map<String, dynamic>>()
            .map(SduiNavSectionSchema.fromJson)
            .toList();
      }

      final uiSchemaRaw = prefs.getString(_uiSchemaCacheKey);
      if (uiSchemaRaw != null) {
        uiSchema = SduiUiSchema.fromJson(
          jsonDecode(uiSchemaRaw) as Map<String, dynamic>?,
        );
      }
    } catch (_) {
      // Corrupt or unavailable cache — callers fall back to safe defaults.
    }
  }

  Future<void> applyNav(NavConfig nav) async {
    navConfig = nav;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_navCacheKey, jsonEncode(nav.toJson()));
    notifyListeners();
  }

  Future<void> refresh(String locale, ApiClient client) async {
    try {
      final response = await client.get(ApiEndpoints.appBootstrap, query: {'locale': locale});
      final prefs = await SharedPreferences.getInstance();

      if (response['tenant'] is Map) {
        tenant = TenantSchema.fromJson(Map<String, dynamic>.from(response['tenant'] as Map));
        await prefs.setString(_tenantCacheKey, jsonEncode(tenant!.toJson()));
      }

      if (response['modules'] is Map) {
        final rawModules = Map<String, dynamic>.from(response['modules'] as Map);
        modules = rawModules.map(
          (k, v) => MapEntry(k, ModuleSchema.fromJson(Map<String, dynamic>.from(v as Map))),
        );
        await prefs.setString(
          _modulesCacheKey,
          jsonEncode(modules.map((k, v) => MapEntry(k, v.toJson()))),
        );
      }

      if (response['menu_structure'] is List) {
        final rawMenu = response['menu_structure'] as List;
        menuStructure = rawMenu
            .whereType<Map>()
            .map((m) => SduiNavSectionSchema.fromJson(Map<String, dynamic>.from(m)))
            .toList();
        await prefs.setString(
          _menuCacheKey,
          jsonEncode(menuStructure.map((s) => s.toJson()).toList()),
        );
      }

      if (response['ui_schema'] is Map) {
        uiSchema = SduiUiSchema.fromJson(Map<String, dynamic>.from(response['ui_schema'] as Map));
        await prefs.setString(_uiSchemaCacheKey, jsonEncode(uiSchema.toJson()));
      }

      final translations = response['translations'];
      if (translations is Map && translations.isNotEmpty) {
        await TranslationsCache.instance.applyFetched(
          locale,
          translations.map((key, value) => MapEntry(key.toString(), value.toString())),
        );
      }

      final nav = response['nav'];
      if (nav is Map) {
        navConfig = NavConfig.fromJson(Map<String, dynamic>.from(nav));
        await prefs.setString(_navCacheKey, jsonEncode(navConfig.toJson()));
      }

      final cfg = response['config'];
      if (cfg is Map) {
        config = Map<String, dynamic>.from(cfg);
        await prefs.setString(_configCacheKey, jsonEncode(config));
      }

      notifyListeners();
    } catch (_) {
      // Offline or network error — keep cached payload.
    }
  }

  /// Switch active operating mode dynamically via backend API.
  Future<bool> switchOperatingMode(String mode, ApiClient client) async {
    try {
      final response = await client.post('/app/mode', data: {'mode': mode});
      if (response['success'] == true) {
        final prefs = await SharedPreferences.getInstance();

        if (tenant != null) {
          tenant = TenantSchema(
            id: tenant!.id,
            businessName: tenant!.businessName,
            activeMode: mode,
            availableModes: tenant!.availableModes,
          );
          await prefs.setString(_tenantCacheKey, jsonEncode(tenant!.toJson()));
        }

        if (response['menu_structure'] is List) {
          final rawMenu = response['menu_structure'] as List;
          menuStructure = rawMenu
              .whereType<Map>()
              .map((m) => SduiNavSectionSchema.fromJson(Map<String, dynamic>.from(m)))
              .toList();
          await prefs.setString(
            _menuCacheKey,
            jsonEncode(menuStructure.map((s) => s.toJson()).toList()),
          );
        }

        config['pos_mode'] = mode == 'restaurant' ? 'restaurant' : 'general';
        await prefs.setString(_configCacheKey, jsonEncode(config));

        notifyListeners();
        return true;
      }
    } catch (_) {
      // Handle network failure
    }
    return false;
  }

  List<SduiNavSectionSchema> _defaultFallbackSections() {
    final isRest = activeMode == 'restaurant';
    if (isRest) {
      return const [
        SduiNavSectionSchema(
          key: 'restaurant_operations',
          title: 'Restaurant Operations',
          color: '#4d7c0f',
          items: [
            SduiNavItemSchema(key: 'restaurant_pos', title: 'Restaurant POS', icon: 'restaurant', component: 'restaurant_pos', permission: 'pos'),
            SduiNavItemSchema(key: 'floor_plan', title: 'Floor Plan & Tables', icon: 'table_restaurant', component: 'floor_plan', permission: 'pos'),
            SduiNavItemSchema(key: 'kitchen_display', title: 'Kitchen Display', icon: 'soup_kitchen', component: 'kitchen_display', permission: 'pos'),
          ],
        ),
        SduiNavSectionSchema(
          key: 'orders_cash',
          title: 'Orders & Cash',
          color: '#0284c7',
          items: [
            SduiNavItemSchema(key: 'dining_history', title: 'Dining History', icon: 'receipt_long', component: 'sales', permission: 'sales'),
            SduiNavItemSchema(key: 'cash_register', title: 'Cash Register', icon: 'savings', component: 'cash_register', permission: 'cash_register'),
          ],
        ),
        SduiNavSectionSchema(
          key: 'financial_management',
          title: 'Financial Management',
          color: '#0f766e',
          items: [
            SduiNavItemSchema(key: 'due_receivables', title: 'Accounts Receivable', icon: 'notifications_active', component: 'due_receivables', permission: 'finance'),
            SduiNavItemSchema(key: 'payables', title: 'Accounts Payable', icon: 'request_quote', component: 'payables', permission: 'finance'),
            SduiNavItemSchema(key: 'reports', title: 'Reports & Analytics', icon: 'insights', component: 'reports', permission: 'reports'),
          ],
        ),
        SduiNavSectionSchema(
          key: 'kitchen_menu_catalog',
          title: 'Kitchen Menu & Catalog',
          color: '#d97706',
          items: [
            SduiNavItemSchema(key: 'inventory', title: 'Menu Dishes & Stock', icon: 'restaurant_menu', component: 'inventory', permission: 'products'),
            SduiNavItemSchema(key: 'categories', title: 'Categories', icon: 'sell', component: 'categories', permission: 'categories'),
            SduiNavItemSchema(key: 'brands', title: 'Brands & Modifiers', icon: 'auto_awesome', component: 'brands', permission: 'categories'),
            SduiNavItemSchema(key: 'units', title: 'Units of Measure', icon: 'straighten', component: 'units', permission: 'units'),
            SduiNavItemSchema(key: 'suppliers', title: 'Food Suppliers', icon: 'local_shipping', component: 'suppliers', permission: 'suppliers'),
            SduiNavItemSchema(key: 'catalog', title: 'Online QR Menu', icon: 'qr_code', component: 'catalog', permission: 'catalog'),
          ],
        ),
        SduiNavSectionSchema(
          key: 'administration',
          title: 'Administration & Settings',
          color: '#475569',
          items: [
            SduiNavItemSchema(key: 'subscription', title: 'Subscription & Billing', icon: 'workspace_premium', component: 'subscription'),
            SduiNavItemSchema(key: 'settings', title: 'Store Settings', icon: 'settings', component: 'settings', permission: 'settings'),
            SduiNavItemSchema(key: 'languages', title: 'Languages & Translations', icon: 'translate', component: 'languages', permission: 'settings'),
            SduiNavItemSchema(key: 'staff', title: 'Users & Permissions', icon: 'badge', component: 'staff', permission: 'users'),
            SduiNavItemSchema(key: 'devices', title: 'Terminals & Devices', icon: 'devices_other', component: 'devices'),
          ],
        ),
      ];
    }

    return const [
      SduiNavSectionSchema(
        key: 'cashier_sales',
        title: 'Cashier & Sales',
        color: '#1d4ed8',
        items: [
          SduiNavItemSchema(key: 'pos', title: 'Point of Sale', icon: 'point_of_sale', component: 'pos', permission: 'pos'),
          SduiNavItemSchema(key: 'sales', title: 'Sales & Invoices', icon: 'receipt_long', component: 'sales', permission: 'sales'),
          SduiNavItemSchema(key: 'quotations', title: 'Quotations', icon: 'description', component: 'quotations', permission: 'quotes'),
          SduiNavItemSchema(key: 'consignments', title: 'Consignments', icon: 'local_shipping', component: 'consignments', permission: 'consignments'),
          SduiNavItemSchema(key: 'service_orders', title: 'Service Orders', icon: 'handyman', component: 'service_orders', permission: 'service_orders'),
          SduiNavItemSchema(key: 'customers', title: 'Customers & CRM', icon: 'people', component: 'customers', permission: 'customers'),
        ],
      ),
      SduiNavSectionSchema(
        key: 'financial_management',
        title: 'Financial Management',
        color: '#0f766e',
        items: [
          SduiNavItemSchema(key: 'cash_register', title: 'Cash Register', icon: 'savings', component: 'cash_register', permission: 'cash_register'),
          SduiNavItemSchema(key: 'due_receivables', title: 'Accounts Receivable', icon: 'notifications_active', component: 'due_receivables', permission: 'finance'),
          SduiNavItemSchema(key: 'payables', title: 'Accounts Payable', icon: 'request_quote', component: 'payables', permission: 'finance'),
          SduiNavItemSchema(key: 'sales_targets', title: 'Sales Targets', icon: 'flag', component: 'sales_targets', permission: 'targets'),
          SduiNavItemSchema(key: 'reports', title: 'Reports', icon: 'insights', component: 'reports', permission: 'reports'),
          SduiNavItemSchema(key: 'analytics', title: 'Analytics', icon: 'bar_chart', component: 'analytics', permission: 'reports'),
        ],
      ),
      SduiNavSectionSchema(
        key: 'products_inventory',
        title: 'Products & Inventory',
        color: '#b45309',
        items: [
          SduiNavItemSchema(key: 'inventory', title: 'All Products', icon: 'inventory_2', component: 'inventory', permission: 'products'),
          SduiNavItemSchema(key: 'categories', title: 'Categories', icon: 'sell', component: 'categories', permission: 'categories'),
          SduiNavItemSchema(key: 'brands', title: 'Brands & Manufacturers', icon: 'auto_awesome', component: 'brands', permission: 'categories'),
          SduiNavItemSchema(key: 'units', title: 'Units of Measure', icon: 'straighten', component: 'units', permission: 'units'),
          SduiNavItemSchema(key: 'suppliers', title: 'Suppliers & Vendors', icon: 'local_shipping', component: 'suppliers', permission: 'suppliers'),
          SduiNavItemSchema(key: 'taxes', title: 'Taxes & Compliance', icon: 'percent', component: 'taxes', permission: 'settings'),
          SduiNavItemSchema(key: 'catalog', title: 'Online Digital Catalog', icon: 'qr_code', component: 'catalog', permission: 'catalog'),
        ],
      ),
      SduiNavSectionSchema(
        key: 'administration',
        title: 'Administration & Settings',
        color: '#475569',
        items: [
          SduiNavItemSchema(key: 'subscription', title: 'Subscription & Billing', icon: 'workspace_premium', component: 'subscription'),
          SduiNavItemSchema(key: 'settings', title: 'Store Settings', icon: 'settings', component: 'settings', permission: 'settings'),
          SduiNavItemSchema(key: 'languages', title: 'Languages & Translations', icon: 'translate', component: 'languages', permission: 'settings'),
          SduiNavItemSchema(key: 'staff', title: 'Users & Permissions', icon: 'badge', component: 'staff', permission: 'users'),
          SduiNavItemSchema(key: 'devices', title: 'Terminals & Devices', icon: 'devices_other', component: 'devices'),
        ],
      ),
    ];
  }
}
