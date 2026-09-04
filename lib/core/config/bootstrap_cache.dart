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
      tenant?.activeMode ?? config['pos_mode']?.toString() ?? '';

  List<String> get availableModes => tenant?.availableModes.isNotEmpty == true
      ? tenant!.availableModes
      : modules.keys.toList(growable: false);

  ModuleSchema get activeModule {
    final mode = activeMode;
    return modules[mode] ??
        ModuleSchema(
          id: mode,
          title: mode,
          layoutType: 'standard_grid',
        );
  }

  /// Server-driven navigation sections. Disk cache supplies offline startup;
  /// an empty cache intentionally renders no business-specific fallback tree.
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
        navConfig =
            NavConfig.fromJson(jsonDecode(navRaw) as Map<String, dynamic>);
      }

      final configRaw = prefs.getString(_configCacheKey);
      if (configRaw != null) {
        config = jsonDecode(configRaw) as Map<String, dynamic>;
      }

      final tenantRaw = prefs.getString(_tenantCacheKey);
      if (tenantRaw != null) {
        tenant = TenantSchema.fromJson(
            jsonDecode(tenantRaw) as Map<String, dynamic>);
      }

      final modulesRaw = prefs.getString(_modulesCacheKey);
      if (modulesRaw != null) {
        final rawMap = jsonDecode(modulesRaw) as Map<String, dynamic>;
        modules = rawMap.map(
          (k, v) =>
              MapEntry(k, ModuleSchema.fromJson(v as Map<String, dynamic>)),
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
      final response = await client
          .get(ApiEndpoints.appBootstrap, query: {'locale': locale});
      final prefs = await SharedPreferences.getInstance();

      if (response['tenant'] is Map) {
        tenant = TenantSchema.fromJson(
            Map<String, dynamic>.from(response['tenant'] as Map));
        await prefs.setString(_tenantCacheKey, jsonEncode(tenant!.toJson()));
      }

      if (response['modules'] is Map) {
        final rawModules =
            Map<String, dynamic>.from(response['modules'] as Map);
        modules = rawModules.map(
          (k, v) => MapEntry(
              k, ModuleSchema.fromJson(Map<String, dynamic>.from(v as Map))),
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
            .map((m) =>
                SduiNavSectionSchema.fromJson(Map<String, dynamic>.from(m)))
            .toList();
        await prefs.setString(
          _menuCacheKey,
          jsonEncode(menuStructure.map((s) => s.toJson()).toList()),
        );
      }

      if (response['ui_schema'] is Map) {
        uiSchema = SduiUiSchema.fromJson(
            Map<String, dynamic>.from(response['ui_schema'] as Map));
        await prefs.setString(_uiSchemaCacheKey, jsonEncode(uiSchema.toJson()));
      }

      final translations = response['translations'];
      if (translations is Map && translations.isNotEmpty) {
        await TranslationsCache.instance.applyFetched(
          locale,
          translations
              .map((key, value) => MapEntry(key.toString(), value.toString())),
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
              .map((m) =>
                  SduiNavSectionSchema.fromJson(Map<String, dynamic>.from(m)))
              .toList();
          await prefs.setString(
            _menuCacheKey,
            jsonEncode(menuStructure.map((s) => s.toJson()).toList()),
          );
        }

        config['pos_mode'] = mode;
        await prefs.setString(_configCacheKey, jsonEncode(config));

        notifyListeners();
        return true;
      }
    } catch (_) {
      // Handle network failure
    }
    return false;
  }

  List<SduiNavSectionSchema> _defaultFallbackSections() => const [];
}
