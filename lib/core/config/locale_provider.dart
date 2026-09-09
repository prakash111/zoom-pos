import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../services/dynamic_string_service.dart';
import '../storage/app_preferences.dart';
import 'bootstrap_cache.dart';

/// Holds the app's own UI display language (distinct from the tenant's
/// storefront default language, see features/languages/), persisted locally
/// via [AppPreferences] so it survives restarts — same local-first pattern
/// as HeldCartsStore and ThemeProvider. The persisted code is also what
/// [ApiClient] sends as the `Accept-Language` header on every request, so
/// switching the language here also drives backend-rendered content.
///
/// Also owns fetching this locale's full translation catalog via
/// [TranslationsCache] — the on-disk cache is applied immediately (so a
/// returning user sees their language pack with no flash of English), then
/// a background refresh against the backend keeps it current and
/// notifies listeners again once it lands.
class LocaleProvider extends ChangeNotifier {
  LocaleProvider(
      {required AppPreferences preferences, required ApiClient apiClient})
      : _preferences = preferences,
        _apiClient = apiClient;

  final AppPreferences _preferences;
  final ApiClient _apiClient;

  Locale locale = const Locale('en');

  Future<void> load() async {
    final code = await _preferences.readLocale();
    final parsed = parseLocale(code);
    if (parsed != null) locale = parsed;
    await BootstrapCache.instance.loadFromDisk();
    await _applyTranslations(locale.languageCode);
  }

  /// Re-fetches the current locale's translations (plus nav/config) — call
  /// once an unauthenticated startup's session restore completes, since the
  /// first [load] may have run before the API client had a token to send.
  Future<void> refreshFromServer() => BootstrapCache.instance
          .refresh(locale.languageCode, _apiClient)
          .then((_) {
        notifyListeners();
      });

  Future<void> setLocale(Locale newLocale) async {
    final parsed = parseLocale('${newLocale.languageCode}'
        '${newLocale.countryCode != null ? '-${newLocale.countryCode}' : ''}');
    if (parsed == null) return;
    locale = parsed;
    notifyListeners();
    // Persist the base language code — that's what the bundled dictionaries,
    // the translation cache and `Accept-Language` all key on.
    await _preferences.saveLocale(parsed.languageCode);
    await _applyTranslations(parsed.languageCode);
  }

  /// Accepts `pt`, `pt-BR`, `pt_BR`, `zh-Hans`, `ZH` … and returns a normalised
  /// [Locale] (`languageCode` lower-cased, `countryCode` upper-cased), or
  /// `null` when the string isn't a plausible language tag. Region-qualified
  /// codes used to be silently dropped, so picking e.g. Brazilian Portuguese
  /// changed nothing.
  static Locale? parseLocale(String raw) {
    final cleaned = raw.trim().replaceAll('_', '-');
    if (cleaned.isEmpty) return null;
    final parts = cleaned.split('-');
    final lang = parts.first.toLowerCase();
    if (!_isLocaleCode(lang)) return null;
    if (parts.length >= 2 && parts[1].isNotEmpty) {
      final region = parts[1].toUpperCase();
      if (RegExp(r'^[A-Z]{2}$').hasMatch(region)) {
        return Locale(lang, region);
      }
      if (RegExp(r'^[A-Za-z]{4}$').hasMatch(parts[1])) {
        return Locale.fromSubtags(
            languageCode: lang, scriptCode: _titleCase(parts[1]));
      }
    }
    return Locale(lang);
  }

  static String _titleCase(String s) =>
      s.isEmpty ? s : s[0].toUpperCase() + s.substring(1).toLowerCase();

  /// Hydrates from disk first for an instant, non-blocking dictionary swap,
  /// then refreshes from the network in the background — one lightweight
  /// GET per switch (translations + nav + config bundled together by
  /// [BootstrapCache]), no polling loop or persistent connection.
  Future<void> _applyTranslations(String code) async {
    await DynamicStringService.instance.activate(code);
    notifyListeners();

    // A failed network refresh must never undo the locale swap that already
    // took effect above — the bundled/cached dictionary still applies.
    try {
      await BootstrapCache.instance.refresh(code, _apiClient);
    } catch (error) {
      debugPrint('LocaleProvider._applyTranslations refresh failed: $error');
    }
    notifyListeners();
  }

  static bool _isLocaleCode(String code) =>
      RegExp(r'^[A-Za-z]{2,3}$').hasMatch(code.trim());
}
