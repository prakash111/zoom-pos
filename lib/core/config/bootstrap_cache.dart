import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../l10n/translations_cache.dart';
import '../api/api_client.dart';
import '../models/settings_models.dart';
import '../sdui/models/sdui_models.dart';
import '../utils/color_utils.dart';
import 'app_config.dart';
import 'theme_provider.dart';

/// Branding tokens delivered in the bootstrap payload from the server.
class BootstrapTheme {
  const BootstrapTheme({
    this.primaryColor,
    this.accentColor,
    this.drawerBg,
  });

  final String? primaryColor;
  final String? accentColor;
  final String? drawerBg;

  Color? get primaryColorValue =>
      primaryColor != null ? parseHexColor(primaryColor!) : null;
  Color? get accentColorValue =>
      accentColor != null ? parseHexColor(accentColor!) : null;
  Color? get drawerBgValue =>
      drawerBg != null ? parseHexColor(drawerBg!) : null;

  factory BootstrapTheme.fromJson(Map<String, dynamic> json) {
    return BootstrapTheme(
      primaryColor: json['primary_color']?.toString(),
      accentColor: json['accent_color']?.toString(),
      drawerBg: json['drawer_bg']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
        if (primaryColor != null) 'primary_color': primaryColor,
        if (accentColor != null) 'accent_color': accentColor,
        if (drawerBg != null) 'drawer_bg': drawerBg,
      };
}

/// Single cold-start call to GET /app/bootstrap: this locale's translation
/// dictionary, active business module schemas, dynamic navigation menus,
/// UI configurations (payment methods, status labels, tax rules), and tenant settings.
class BootstrapCache extends ChangeNotifier {
  BootstrapCache._();

  static final BootstrapCache instance = BootstrapCache._();
  static ThemeProvider? globalThemeProvider;

  static const _navCacheKey = 'zoom_pos.bootstrap.nav';
  static const _configCacheKey = 'zoom_pos.bootstrap.config';
  static const _tenantCacheKey = 'zoom_pos.bootstrap.tenant';
  static const _modulesCacheKey = 'zoom_pos.bootstrap.modules';
  static const _menuCacheKey = 'zoom_pos.bootstrap.menu';
  static const _uiSchemaCacheKey = 'zoom_pos.bootstrap.ui_schema';
  static const _themeCacheKey = 'zoom_pos.bootstrap.theme';

  TenantSchema? tenant;
  Map<String, ModuleSchema> modules = {};
  List<SduiNavSectionSchema> menuStructure = [];
  SduiUiSchema uiSchema = const SduiUiSchema();
  NavConfig navConfig = const NavConfig();
  Map<String, dynamic> config = {};
  BootstrapTheme theme = const BootstrapTheme();

  Future<void>? _diskLoadFuture;
  bool _diskHydrated = false;
  int _activeRefreshes = 0;
  bool _isHydrating = false;
  String? _navigationError;

  bool get isHydrating => _isHydrating;
  final ValueNotifier<bool> isHydratingNotifier = ValueNotifier<bool>(false);

  /// True only while there is no cached menu to draw and disk/network
  /// hydration is still in flight. Returning users keep seeing the cached
  /// menu while the background refresh runs.
  bool get isNavigationLoading =>
      menuStructure.isEmpty &&
      (!_diskHydrated || _activeRefreshes > 0 || _isHydrating);

  String? get navigationError => _navigationError;

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

  Future<void> loadFromDisk() {
    if (_diskHydrated) return Future.value();
    final inFlight = _diskLoadFuture;
    if (inFlight != null) return inFlight;

    final operation = _loadFromDisk();
    _diskLoadFuture = operation;
    return operation.whenComplete(() {
      if (identical(_diskLoadFuture, operation)) {
        _diskLoadFuture = null;
      }
    });
  }

  Future<void> _loadFromDisk() async {
    try {
      final prefs = await SharedPreferences.getInstance();

      final navRaw = prefs.getString(_navCacheKey);
      if (navRaw != null) {
        try {
          navConfig = NavConfig.fromJson(
            Map<String, dynamic>.from(jsonDecode(navRaw) as Map),
          );
        } catch (error, stackTrace) {
          _logParseFailure('cached nav configuration', error, stackTrace);
        }
      }

      final configRaw = prefs.getString(_configCacheKey);
      if (configRaw != null) {
        try {
          config = Map<String, dynamic>.from(jsonDecode(configRaw) as Map);
        } catch (error, stackTrace) {
          _logParseFailure('cached app configuration', error, stackTrace);
        }
      }

      final tenantRaw = prefs.getString(_tenantCacheKey);
      if (tenantRaw != null) {
        try {
          tenant = TenantSchema.fromJson(
            Map<String, dynamic>.from(jsonDecode(tenantRaw) as Map),
          );
        } catch (error, stackTrace) {
          _logParseFailure('cached tenant', error, stackTrace);
        }
      }

      final modulesRaw = prefs.getString(_modulesCacheKey);
      if (modulesRaw != null) {
        try {
          final rawMap = Map<String, dynamic>.from(
            jsonDecode(modulesRaw) as Map,
          );
          final parsedModules = _parseModules(rawMap, source: 'disk cache');
          if (parsedModules.isNotEmpty) modules = parsedModules;
        } catch (error, stackTrace) {
          _logParseFailure('cached modules', error, stackTrace);
        }
      }

      final menuRaw = prefs.getString(_menuCacheKey);
      if (menuRaw != null) {
        try {
          final parsedMenu = _parseMenuStructure(
            jsonDecode(menuRaw),
            source: 'disk cache',
          );
          if (parsedMenu.isNotEmpty) {
            menuStructure = parsedMenu;
            _navigationError = null;
          } else {
            _navigationError = 'No cached navigation is available.';
          }
        } catch (error, stackTrace) {
          _navigationError = 'The cached navigation could not be read.';
          _logParseFailure('cached menu_structure', error, stackTrace);
        }
      }

      final uiSchemaRaw = prefs.getString(_uiSchemaCacheKey);
      if (uiSchemaRaw != null) {
        try {
          uiSchema = SduiUiSchema.fromJson(
            Map<String, dynamic>.from(jsonDecode(uiSchemaRaw) as Map),
          );
        } catch (error, stackTrace) {
          _logParseFailure('cached UI schema', error, stackTrace);
        }
      }

      final themeRaw = prefs.getString(_themeCacheKey);
      if (themeRaw != null) {
        try {
          theme = BootstrapTheme.fromJson(
            Map<String, dynamic>.from(jsonDecode(themeRaw) as Map),
          );
          globalThemeProvider?.syncFromBootstrap(theme);
        } catch (error, stackTrace) {
          _logParseFailure('cached theme', error, stackTrace);
        }
      }
    } catch (error, stackTrace) {
      _logParseFailure('bootstrap disk cache', error, stackTrace);
    } finally {
      _diskHydrated = true;
      notifyListeners();
    }
  }

  Future<void> applyNav(NavConfig nav) async {
    navConfig = nav;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_navCacheKey, jsonEncode(nav.toJson()));
    notifyListeners();
  }

  Future<void> refresh(String locale, ApiClient client) async {
    // A refresh is also a cold-start entry point (for example immediately
    // after a fresh login). Always apply the on-device payload first.
    await loadFromDisk();
    _activeRefreshes++;
    notifyListeners();

    try {
      final responseEnvelope = await client
          .get(ApiEndpoints.appBootstrap, query: {'locale': locale});
      final response = responseEnvelope['data'] is Map
          ? Map<String, dynamic>.from(responseEnvelope['data'] as Map)
          : responseEnvelope;
      final prefs = await SharedPreferences.getInstance();

      if (response['tenant'] is Map) {
        try {
          tenant = TenantSchema.fromJson(
              Map<String, dynamic>.from(response['tenant'] as Map));
          await prefs.setString(_tenantCacheKey, jsonEncode(tenant!.toJson()));
        } catch (error, stackTrace) {
          _logParseFailure('server tenant', error, stackTrace);
        }
      }

      if (response['modules'] is Map) {
        final parsedModules = _parseModules(
          Map<String, dynamic>.from(response['modules'] as Map),
          source: 'server bootstrap',
        );
        if (parsedModules.isNotEmpty) {
          modules = parsedModules;
          await prefs.setString(
            _modulesCacheKey,
            jsonEncode(modules.map((k, v) => MapEntry(k, v.toJson()))),
          );
        }
      }

      final menuPayload = response.containsKey('menu_structure')
          ? response['menu_structure']
          : response['navigation'] ?? response['sections'];
      final parsedMenu = _parseMenuStructure(
        menuPayload,
        source: 'server bootstrap',
      );
      if (parsedMenu.isNotEmpty) {
        menuStructure = parsedMenu;
        _navigationError = null;
        await prefs.setString(
          _menuCacheKey,
          jsonEncode(menuStructure.map((s) => s.toJson()).toList()),
        );
      } else if (menuStructure.isEmpty) {
        _navigationError =
            'The server returned no valid navigation sections. Please retry.';
      }

      if (response['ui_schema'] is Map) {
        try {
          uiSchema = SduiUiSchema.fromJson(
              Map<String, dynamic>.from(response['ui_schema'] as Map));
          await prefs.setString(
              _uiSchemaCacheKey, jsonEncode(uiSchema.toJson()));
        } catch (error, stackTrace) {
          _logParseFailure('server UI schema', error, stackTrace);
        }
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
        try {
          navConfig = NavConfig.fromJson(Map<String, dynamic>.from(nav));
          await prefs.setString(_navCacheKey, jsonEncode(navConfig.toJson()));
        } catch (error, stackTrace) {
          _logParseFailure('server nav configuration', error, stackTrace);
        }
      }

      final cfg = response['config'];
      if (cfg is Map) {
        config = Map<String, dynamic>.from(cfg);
        await prefs.setString(_configCacheKey, jsonEncode(config));
      }

      if (response['theme'] is Map) {
        try {
          theme = BootstrapTheme.fromJson(
            Map<String, dynamic>.from(response['theme'] as Map),
          );
          await prefs.setString(_themeCacheKey, jsonEncode(theme.toJson()));
          globalThemeProvider?.syncFromBootstrap(theme);
        } catch (error, stackTrace) {
          _logParseFailure('server theme', error, stackTrace);
        }
      }
    } catch (error, stackTrace) {
      if (menuStructure.isEmpty) {
        _navigationError =
            'Navigation could not be loaded. Check your connection and retry.';
      }
      debugPrint('Bootstrap refresh failed: $error');
      debugPrintStack(stackTrace: stackTrace);
    } finally {
      _activeRefreshes--;
      notifyListeners();
    }
  }

  /// Hydrates bootstrap state. If [forceRefresh] is true or menu is empty,
  /// fetches fresh navigation, modules, and theme from the backend.
  Future<void> hydrate({
    bool forceRefresh = false,
    ApiClient? client,
    String? locale,
  }) async {
    await loadFromDisk();
    if ((forceRefresh || menuStructure.isEmpty) && client != null) {
      _isHydrating = true;
      isHydratingNotifier.value = true;
      notifyListeners();
      try {
        await refresh(locale ?? 'en', client);
      } finally {
        _isHydrating = false;
        isHydratingNotifier.value = false;
        notifyListeners();
      }
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

        final parsedMenu = _parseMenuStructure(
          response['menu_structure'] ??
              response['navigation'] ??
              response['sections'],
          source: 'mode switch',
        );
        if (parsedMenu.isNotEmpty) {
          menuStructure = parsedMenu;
          _navigationError = null;
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
    } catch (error, stackTrace) {
      debugPrint('Operating mode switch bootstrap failed: $error');
      debugPrintStack(stackTrace: stackTrace);
    }
    return false;
  }

  Map<String, ModuleSchema> _parseModules(
    Map<String, dynamic> rawModules, {
    required String source,
  }) {
    final parsed = <String, ModuleSchema>{};
    for (final entry in rawModules.entries) {
      if (entry.value is! Map) {
        debugPrint(
            'Bootstrap $source: ignoring non-object module ${entry.key}.');
        continue;
      }
      try {
        parsed[entry.key] = ModuleSchema.fromJson(
          Map<String, dynamic>.from(entry.value as Map),
        );
      } catch (error, stackTrace) {
        _logParseFailure('$source module ${entry.key}', error, stackTrace);
      }
    }
    return parsed;
  }

  List<SduiNavSectionSchema> _parseMenuStructure(
    Object? payload, {
    required String source,
  }) {
    Object? rawSections = payload;
    if (rawSections is Map) {
      rawSections = rawSections['sections'] ??
          rawSections['navigation'] ??
          rawSections['menu_structure'];
    }

    if (rawSections is! List) {
      debugPrint(
          'Bootstrap $source: menu_structure must be a list, got ${rawSections.runtimeType}.');
      return const [];
    }

    final parsed = <SduiNavSectionSchema>[];
    for (var index = 0; index < rawSections.length; index++) {
      final rawSection = rawSections[index];
      if (rawSection is! Map) {
        debugPrint(
            'Bootstrap $source: ignoring non-object section at index $index.');
        continue;
      }

      try {
        final section = SduiNavSectionSchema.fromJson(
          Map<String, dynamic>.from(rawSection),
        );
        if (section.key.isEmpty || section.items.isEmpty) {
          debugPrint(
              'Bootstrap $source: ignoring empty section at index $index (${section.key.isEmpty ? 'missing key' : section.key}).');
          continue;
        }
        parsed.add(section);
      } catch (error, stackTrace) {
        _logParseFailure('$source section $index', error, stackTrace);
      }
    }
    return parsed;
  }

  void _logParseFailure(String field, Object error, StackTrace stackTrace) {
    debugPrint('Bootstrap parsing failed for $field: $error');
    debugPrintStack(stackTrace: stackTrace);
  }

  List<SduiNavSectionSchema> _defaultFallbackSections() => const [];
}
