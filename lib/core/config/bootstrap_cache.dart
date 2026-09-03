import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../../l10n/translations_cache.dart';
import '../api/api_client.dart';
import '../models/settings_models.dart';
import 'app_config.dart';

/// Single cold-start call to GET /app/bootstrap: this locale's translation
/// dictionary (forwarded to [TranslationsCache] so callers only ever read
/// phrases from one place), this tenant's nav customization (which drawer
/// destinations it hides and what order its section groups render in — see
/// [DashboardScreen]), and a handful of company config values. Cached to
/// disk the same way [TranslationsCache] is, so the app's menu and phrases
/// stay put across a restart with no network round trip, and a background
/// [refresh] failure (offline, server error) just leaves the last-known copy
/// in place instead of blanking the menu.
class BootstrapCache {
  BootstrapCache._();

  static final BootstrapCache instance = BootstrapCache._();

  static const _navCacheKey = 'zoom_pos.bootstrap.nav';
  static const _configCacheKey = 'zoom_pos.bootstrap.config';

  NavConfig navConfig = const NavConfig();
  Map<String, dynamic> config = {};

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
    } catch (_) {
      // Corrupt or unavailable cache — callers fall back to the compiled-in
      // default nav (no hidden tiles, default section order).
    }
  }

  /// Applies a nav config obtained from somewhere other than [refresh] —
  /// namely Settings > Navigation Menu saving a change through
  /// SettingsRepository.updateNavConfig — so the drawer picks it up
  /// immediately instead of waiting for the next locale switch/app start.
  Future<void> applyNav(NavConfig nav) async {
    navConfig = nav;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_navCacheKey, jsonEncode(nav.toJson()));
  }

  Future<void> refresh(String locale, ApiClient client) async {
    try {
      final response = await client.get(ApiEndpoints.appBootstrap, query: {'locale': locale});

      final translations = response['translations'];
      if (translations is Map && translations.isNotEmpty) {
        await TranslationsCache.instance.applyFetched(
          locale,
          translations.map((key, value) => MapEntry(key.toString(), value.toString())),
        );
      }

      final nav = response['nav'];
      if (nav is Map) {
        await applyNav(NavConfig.fromJson(Map<String, dynamic>.from(nav)));
      }

      final cfg = response['config'];
      if (cfg is Map) {
        config = Map<String, dynamic>.from(cfg);
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(_configCacheKey, jsonEncode(config));
      }
    } catch (_) {
      // Offline or the endpoint errored — keep whatever's already cached.
    }
  }
}
