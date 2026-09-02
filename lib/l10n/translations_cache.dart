import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../core/api/api_client.dart';
import '../core/config/app_config.dart';

/// Server-fetched phrase overrides for the active locale, layered on top of
/// the small bundled dictionaries in [AppLocalizations] so the app doesn't
/// have to ship every locale's full translation catalog inside the APK.
/// Lightweight API polling only — one GET per locale switch/app start, no
/// WebSockets — with the result cached to disk so the dictionary is still
/// usable offline after the first successful fetch.
class TranslationsCache {
  TranslationsCache._();

  static final TranslationsCache instance = TranslationsCache._();

  static const _cacheKeyPrefix = 'zoom_pos.translations.';

  final Map<String, Map<String, String>> _byLocale = {};

  /// In-memory overrides for [locale], empty until [loadFromDisk] and/or
  /// [refresh] have populated it.
  Map<String, String> forLocale(String locale) => _byLocale[locale] ?? const {};

  /// Hydrates the in-memory cache for [locale] from the on-disk cache
  /// written by a previous [refresh], so a returning user sees their full
  /// language pack immediately without waiting on a network round trip.
  Future<void> loadFromDisk(String locale) async {
    if (_byLocale.containsKey(locale)) return;
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString('$_cacheKeyPrefix$locale');
      if (raw == null) return;
      final decoded = jsonDecode(raw) as Map<String, dynamic>;
      _byLocale[locale] = decoded.map((key, value) => MapEntry(key, value.toString()));
    } catch (_) {
      // Corrupt or unavailable cache — AppLocalizations falls back to its
      // bundled dictionary until a refresh() succeeds.
    }
  }

  /// Fetches the merged (base + tenant overrides) dictionary for [locale]
  /// from the backend and persists it to disk for next launch. Silent on
  /// failure (offline, server error) — callers just keep whatever was
  /// already cached or the bundled fallback.
  ///
  /// Prefer [BootstrapCache.refresh] for a locale switch/app start — it
  /// fetches this same dictionary as part of one combined call and forwards
  /// it to [applyFetched]. This method remains as a narrow, single-purpose
  /// fetch for anything that only needs the phrase dictionary.
  Future<void> refresh(String locale, ApiClient client) async {
    try {
      final response = await client.get(ApiEndpoints.languageTranslations(locale));
      final raw = response['translations'];
      if (raw is! Map || raw.isEmpty) return;
      await applyFetched(locale, raw.map((key, value) => MapEntry(key.toString(), value.toString())));
    } catch (_) {
      // Offline or the endpoint errored — keep the disk cache/bundled copy.
    }
  }

  /// Stores an already-fetched dictionary for [locale] in memory and on
  /// disk, same as [refresh] but for a caller (namely [BootstrapCache]) that
  /// obtained the translations from a different endpoint.
  Future<void> applyFetched(String locale, Map<String, String> translations) async {
    if (translations.isEmpty) return;
    _byLocale[locale] = translations;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('$_cacheKeyPrefix$locale', jsonEncode(translations));
  }
}
