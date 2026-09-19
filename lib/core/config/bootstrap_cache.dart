import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api_client.dart';
import '../models/settings_models.dart';
import '../navigation/navigation_provider.dart';
import '../sdui/models/sdui_models.dart';
import '../utils/color_utils.dart';
import '../services/dynamic_string_service.dart';
import 'app_config.dart';
import 'theme_provider.dart';

/// Branding tokens delivered in the bootstrap payload from the server.
class BootstrapTheme {
  const BootstrapTheme({
    this.primaryColor,
    this.accentColor,
    this.drawerBg,
    this.drawerGradientEnabled = false,
    this.drawerGradientStart,
    this.drawerGradientEnd,
    this.drawerGradientDirection = 'top_to_bottom',
  });

  final String? primaryColor;
  final String? accentColor;
  final String? drawerBg;
  final bool drawerGradientEnabled;
  final String? drawerGradientStart;
  final String? drawerGradientEnd;
  final String? drawerGradientDirection;

  Color? get primaryColorValue =>
      primaryColor != null ? parseHexColor(primaryColor!) : null;
  Color? get accentColorValue =>
      accentColor != null ? parseHexColor(accentColor!) : null;
  Color? get drawerBgValue =>
      drawerBg != null ? parseHexColor(drawerBg!) : null;

  Gradient? get drawerGradient {
    if (!drawerGradientEnabled) return null;
    final start = parseHexColor(drawerGradientStart ?? drawerBg ?? '#1e293b');
    final end = parseHexColor(drawerGradientEnd ?? '#0f172a');
    if (start == null || end == null) return null;

    switch (drawerGradientDirection) {
      case 'diagonal':
        return LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [start, end],
        );
      case 'radial':
        return RadialGradient(
          colors: [start, end],
          radius: 1.0,
        );
      case 'top_to_bottom':
      default:
        return LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [start, end],
        );
    }
  }

  factory BootstrapTheme.fromJson(Map<String, dynamic> json) {
    return BootstrapTheme(
      primaryColor: json['primary_color']?.toString(),
      accentColor: json['accent_color']?.toString(),
      drawerBg: json['drawer_bg']?.toString(),
      drawerGradientEnabled: json['drawer_gradient_enabled'] == true,
      drawerGradientStart: json['drawer_gradient_start']?.toString(),
      drawerGradientEnd: json['drawer_gradient_end']?.toString(),
      drawerGradientDirection:
          json['drawer_gradient_direction']?.toString() ?? 'top_to_bottom',
    );
  }

  Map<String, dynamic> toJson() => {
        if (primaryColor != null) 'primary_color': primaryColor,
        if (accentColor != null) 'accent_color': accentColor,
        if (drawerBg != null) 'drawer_bg': drawerBg,
        'drawer_gradient_enabled': drawerGradientEnabled,
        if (drawerGradientStart != null)
          'drawer_gradient_start': drawerGradientStart,
        if (drawerGradientEnd != null) 'drawer_gradient_end': drawerGradientEnd,
        'drawer_gradient_direction': drawerGradientDirection,
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
  static const _labelsCacheKey = 'zoom_pos.bootstrap.navigation_labels';
  static const _formLabelsCacheKey =
      'zoom_pos.bootstrap.form_field_customizations';

  TenantSchema? tenant;
  Map<String, ModuleSchema> modules = {};
  List<SduiNavSectionSchema> menuStructure = [];
  Map<String, String> navigationLabels = {};
  Map<String, dynamic> formFieldCustomizations = {};
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

  String get activeMode {
    final tMode = tenant?.activeMode.trim();
    if (tMode != null && tMode.isNotEmpty && tMode.toLowerCase() != 'general') {
      return tMode;
    }
    final cMode = config['pos_mode']?.toString().trim();
    if (cMode != null && cMode.isNotEmpty && cMode.toLowerCase() != 'general') {
      return cMode;
    }
    final bType = tenant?.businessType?.trim();
    if (bType != null && bType.isNotEmpty && bType.toLowerCase() != 'general') {
      return bType.toLowerCase();
    }
    return 'retail';
  }

  List<String> get availableModes => tenant?.availableModes.isNotEmpty == true
      ? tenant!.availableModes
      : modules.keys.toList(growable: false);

  String? get logoUrl => config['logo_url']?.toString();
  String? get faviconUrl => config['favicon_url']?.toString();
  String? get drawerCoverUrl => config['drawer_cover_url']?.toString();

  ModuleSchema get activeModule {
    final mode = activeMode;
    return modules[mode] ??
        ModuleSchema(
          id: mode,
          title: mode == 'retail' ? 'Retail' : (mode.isEmpty ? 'Retail' : mode),
          layoutType: 'standard_grid',
        );
  }

  /// Server-driven navigation sections. Disk cache supplies offline startup;
  /// falls back to baseline sections when hydrated cache is empty.
  List<SduiNavSectionSchema> get effectiveSections {
    if (menuStructure.isNotEmpty) {
      return menuStructure;
    }
    if (isNavigationLoading) {
      return const [];
    }
    return _defaultFallbackSections();
  }

  /// Looks up custom navigation label configured by tenant, or falls back to default.
  String resolveNavigationLabel(String key, String fallback) {
    if (navigationLabels.isEmpty) return fallback;
    final trimmed = navigationLabels[key]?.trim();
    if (trimmed != null && trimmed.isNotEmpty) return trimmed;

    final snake = key.replaceAll('-', '_');
    final trimmedSnake = navigationLabels[snake]?.trim();
    if (trimmedSnake != null && trimmedSnake.isNotEmpty) return trimmedSnake;

    final kebab = key.replaceAll('_', '-');
    final trimmedKebab = navigationLabels[kebab]?.trim();
    if (trimmedKebab != null && trimmedKebab.isNotEmpty) return trimmedKebab;

    return fallback;
  }

  /// Looks up custom form field label configured by tenant, or falls back to default.
  String resolveFormFieldLabel(String form, String field, String fallback) {
    final formMap = formFieldCustomizations[form];
    if (formMap is Map) {
      final val = formMap[field]?.toString().trim();
      if (val != null && val.isNotEmpty) {
        return val;
      }
    }
    return fallback;
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
          final decoded = jsonDecode(navRaw);
          if (decoded is Map) {
            navConfig = NavConfig.fromJson(_safeMap(decoded));
          } else if (decoded is List) {
            navConfig = NavConfig.fromJson(
                {'tree': _safeList(decoded), 'sections': _safeList(decoded)});
          }
        } catch (error, stackTrace) {
          _logParseFailure('cached nav configuration', error, stackTrace);
        }
      }

      final configRaw = prefs.getString(_configCacheKey);
      if (configRaw != null) {
        try {
          final decoded = jsonDecode(configRaw);
          if (decoded is Map) {
            config = _safeMap(decoded);
          }
        } catch (error, stackTrace) {
          _logParseFailure('cached app configuration', error, stackTrace);
        }
      }

      final tenantRaw = prefs.getString(_tenantCacheKey);
      if (tenantRaw != null) {
        try {
          final decoded = jsonDecode(tenantRaw);
          if (decoded is Map) {
            tenant = TenantSchema.fromJson(_safeMap(decoded));
          }
        } catch (error, stackTrace) {
          _logParseFailure('cached tenant', error, stackTrace);
        }
      }

      final modulesRaw = prefs.getString(_modulesCacheKey);
      if (modulesRaw != null) {
        try {
          final decoded = jsonDecode(modulesRaw);
          if (decoded is Map) {
            final rawMap = _safeMap(decoded);
            final parsedModules = _parseModules(rawMap, source: 'disk cache');
            if (parsedModules.isNotEmpty) modules = parsedModules;
          }
        } catch (error, stackTrace) {
          _logParseFailure('cached modules', error, stackTrace);
        }
      }

      final menuRaw = prefs.getString(_menuCacheKey);
      if (menuRaw != null) {
        try {
          final decoded = jsonDecode(menuRaw);
          final parsedMenu = _parseMenuStructure(
            decoded,
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
          final decoded = jsonDecode(uiSchemaRaw);
          if (decoded is Map) {
            uiSchema = SduiUiSchema.fromJson(_safeMap(decoded));
          }
        } catch (error, stackTrace) {
          _logParseFailure('cached UI schema', error, stackTrace);
        }
      }

      final themeRaw = prefs.getString(_themeCacheKey);
      if (themeRaw != null) {
        try {
          final decoded = jsonDecode(themeRaw);
          if (decoded is Map) {
            theme = BootstrapTheme.fromJson(_safeMap(decoded));
            globalThemeProvider?.syncFromBootstrap(theme);
          }
        } catch (error, stackTrace) {
          _logParseFailure('cached theme', error, stackTrace);
        }
      }

      final labelsRaw = prefs.getString(_labelsCacheKey);
      if (labelsRaw != null) {
        try {
          final decoded = jsonDecode(labelsRaw);
          if (decoded is Map) {
            navigationLabels =
                decoded.map((k, v) => MapEntry(k.toString(), v.toString()));
          }
        } catch (error, stackTrace) {
          _logParseFailure('cached navigation labels', error, stackTrace);
        }
      }

      final formLabelsRaw = prefs.getString(_formLabelsCacheKey);
      if (formLabelsRaw != null) {
        try {
          final decoded = jsonDecode(formLabelsRaw);
          if (decoded is Map) {
            formFieldCustomizations = _safeMap(decoded);
          }
        } catch (error, stackTrace) {
          _logParseFailure(
              'cached form field customizations', error, stackTrace);
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
      final rawData = responseEnvelope['data'];
      final Map<String, dynamic> response =
          rawData is Map ? _safeMap(rawData) : _safeMap(responseEnvelope);
      final prefs = await SharedPreferences.getInstance();

      if (response['tenant'] is Map) {
        try {
          tenant = TenantSchema.fromJson(_safeMap(response['tenant']));
          await prefs.setString(_tenantCacheKey, jsonEncode(tenant!.toJson()));
        } catch (error, stackTrace) {
          _logParseFailure('server tenant', error, stackTrace);
        }
      }

      if (response['modules'] is Map) {
        final parsedModules = _parseModules(
          _safeMap(response['modules']),
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

      final menuPayload = response['menu_structure'] ??
          response['navigation'] ??
          response['nav_v2'] ??
          response['sections'];
      debugPrint(
          'Bootstrap raw menu payload: ${menuPayload != null ? (menuPayload is List ? "${menuPayload.length} sections" : menuPayload.runtimeType) : "null"}');
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
          uiSchema = SduiUiSchema.fromJson(_safeMap(response['ui_schema']));
          await prefs.setString(
              _uiSchemaCacheKey, jsonEncode(uiSchema.toJson()));
        } catch (error, stackTrace) {
          _logParseFailure('server UI schema', error, stackTrace);
        }
      }

      final translations = response['translations'];
      if (translations is Map && translations.isNotEmpty) {
        await DynamicStringService.instance.applyFetched(
          locale,
          translations
              .map((key, value) => MapEntry(key.toString(), value.toString())),
          version: response['translations_version']?.toString(),
        );
      }

      final nav = response['nav'] ?? response['nav_v2'];
      if (nav is Map) {
        try {
          navConfig = NavConfig.fromJson(_safeMap(nav));
          await prefs.setString(_navCacheKey, jsonEncode(navConfig.toJson()));
        } catch (error, stackTrace) {
          _logParseFailure('nav_v2 schema', error, stackTrace);
        }
      } else if (nav is Iterable) {
        try {
          final list = List.from(nav);
          navConfig = NavConfig.fromJson(
              {'tree': _safeList(list), 'sections': _safeList(list)});
          await prefs.setString(_navCacheKey, jsonEncode(navConfig.toJson()));
        } catch (error, stackTrace) {
          _logParseFailure('nav_v2 schema', error, stackTrace);
        }
      }

      final cfg = response['config'];
      if (cfg is Map) {
        config = _safeMap(cfg);
        await prefs.setString(_configCacheKey, jsonEncode(config));
      }

      if (response['theme'] is Map) {
        try {
          await applyThemeJson(
            _safeMap(response['theme']),
            preferences: prefs,
          );
        } catch (error, stackTrace) {
          _logParseFailure('server theme', error, stackTrace);
        }
      }

      final navLabels = response['navigation_labels'] ??
          (response['tenant'] is Map
              ? response['tenant']['navigation_labels']
              : null);
      if (navLabels is Map) {
        navigationLabels =
            navLabels.map((k, v) => MapEntry(k.toString(), v.toString()));
        await prefs.setString(_labelsCacheKey, jsonEncode(navigationLabels));
      }

      final formCustomizations = response['form_field_customizations'] ??
          (response['tenant'] is Map
              ? response['tenant']['form_field_customizations']
              : null);
      if (formCustomizations is Map) {
        formFieldCustomizations = _safeMap(formCustomizations);
        await prefs.setString(
            _formLabelsCacheKey, jsonEncode(formFieldCustomizations));
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

  /// Applies a just-saved server theme to the singleton immediately and
  /// repaints both Material colors and the navigation background.
  Future<void> applyThemeJson(
    Map<String, dynamic> json, {
    SharedPreferences? preferences,
  }) async {
    theme = BootstrapTheme.fromJson(json);
    final prefs = preferences ?? await SharedPreferences.getInstance();
    await prefs.setString(_themeCacheKey, jsonEncode(theme.toJson()));
    await globalThemeProvider?.syncFromBootstrap(theme);
    notifyListeners();
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
          _safeMap(entry.value),
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
          rawSections['nav_v2'] ??
          rawSections['menu_structure'] ??
          rawSections.values;
    }

    if (rawSections is! Iterable) {
      debugPrint(
          'Bootstrap $source: menu_structure must be an iterable, got ${rawSections.runtimeType}.');
      return const [];
    }

    final rawSectionsList = List.from(rawSections);
    final parsed = <SduiNavSectionSchema>[];
    for (var index = 0; index < rawSectionsList.length; index++) {
      final rawSection = rawSectionsList[index];
      if (rawSection is! Map) {
        debugPrint(
            'Bootstrap $source: ignoring non-object section at index $index.');
        continue;
      }

      try {
        final section = SduiNavSectionSchema.fromJson(
          _safeMap(rawSection),
        );
        if (section.key.isEmpty || section.items.isEmpty) {
          debugPrint(
              'Bootstrap $source: ignoring empty section at index $index (${section.key.isEmpty ? 'missing key' : section.key}).');
          continue;
        }
        parsed.add(section);
      } catch (error, stackTrace) {
        _logParseFailure('nav_v2 schema', error, stackTrace);
      }
    }
    return parsed;
  }

  static Map<String, dynamic> _safeMap(dynamic raw) {
    final result = NavigationProvider.safeMap(raw);
    result.updateAll((key, value) {
      if (value is Map) return _safeMap(value);
      if (value is Iterable) return _safeList(value);
      return value;
    });
    return result;
  }

  static List<dynamic> _safeList(dynamic raw) {
    return NavigationProvider.safeList(raw).map((item) {
      if (item is Map) return _safeMap(item);
      if (item is Iterable) return _safeList(item);
      return item;
    }).toList();
  }

  void _logParseFailure(String field, Object error, StackTrace stackTrace) {
    debugPrint('Bootstrap parsing failed for $field: $error');
    debugPrintStack(stackTrace: stackTrace);
  }

  List<SduiNavSectionSchema> _defaultFallbackSections() => const [
        SduiNavSectionSchema(
          key: 'cashier_sales',
          title: 'Cashier & Sales',
          color: '#1d4ed8',
          items: [
            SduiNavItemSchema(
              key: 'pos',
              title: 'Point of Sale',
              icon: 'point_of_sale',
              component: 'pos',
              permission: 'pos',
              targetEndpoint: '/tenant/views/pos',
            ),
            SduiNavItemSchema(
              key: 'sales',
              title: 'Sales & Invoices',
              icon: 'receipt_long',
              component: 'sales',
              permission: 'sales',
              targetEndpoint: '/tenant/views/sales',
            ),
            SduiNavItemSchema(
              key: 'quotations',
              title: 'Quotations & Proposals',
              icon: 'description',
              component: 'quotations',
              permission: 'quotes',
              targetEndpoint: '/tenant/views/quotations',
            ),
            SduiNavItemSchema(
              key: 'consignments',
              title: 'Consignments',
              icon: 'local_shipping',
              component: 'consignments',
              permission: 'consignments',
              targetEndpoint: '/consignments',
            ),
            SduiNavItemSchema(
              key: 'customers',
              title: 'Customers & CRM',
              icon: 'people',
              component: 'customers',
              permission: 'customers',
              targetEndpoint: '/customers',
            ),
          ],
        ),
        SduiNavSectionSchema(
          key: 'products_inventory',
          title: 'Products & Inventory',
          color: '#b45309',
          items: [
            SduiNavItemSchema(
              key: 'inventory',
              title: 'All Products',
              icon: 'inventory_2',
              component: 'inventory',
              permission: 'products',
            ),
          ],
        ),
        SduiNavSectionSchema(
          key: 'administration',
          title: 'Administration & Settings',
          color: '#475569',
          items: [
            SduiNavItemSchema(
              key: 'settings',
              title: 'Store Settings',
              icon: 'settings',
              component: 'settings',
              permission: 'settings',
            ),
          ],
        ),
      ];
}
