import 'package:flutter/material.dart';

import '../../l10n/translations_cache.dart';
import '../api/api_client.dart';
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
  LocaleProvider({required AppPreferences preferences, required ApiClient apiClient})
      : _preferences = preferences,
        _apiClient = apiClient;

  final AppPreferences _preferences;
  final ApiClient _apiClient;

  /// Every locale the Laravel backend's language catalog supports
  /// (LocalizationService::$defaultLanguages) — kept in sync with that list
  /// so a store's chosen default language is never silently unsupported.
  static const supportedCodes = ['en', 'es', 'fr', 'de', 'ar', 'hi', 'pt', 'it', 'zh', 'ja', 'ru', 'id', 'tr'];

  Locale locale = const Locale('en');

  Future<void> load() async {
    final code = await _preferences.readLocale();
    if (supportedCodes.contains(code)) {
      locale = Locale(code);
    }
    await BootstrapCache.instance.loadFromDisk();
    await _applyTranslations(locale.languageCode);
  }

  /// Re-fetches the current locale's translations (plus nav/config) — call
  /// once an unauthenticated startup's session restore completes, since the
  /// first [load] may have run before the API client had a token to send.
  Future<void> refreshFromServer() => BootstrapCache.instance.refresh(locale.languageCode, _apiClient).then((_) {
        notifyListeners();
      });

  Future<void> setLocale(Locale newLocale) async {
    locale = newLocale;
    notifyListeners();
    await _preferences.saveLocale(newLocale.languageCode);
    await _applyTranslations(newLocale.languageCode);
  }

  /// Hydrates from disk first for an instant, non-blocking dictionary swap,
  /// then refreshes from the network in the background — one lightweight
  /// GET per switch (translations + nav + config bundled together by
  /// [BootstrapCache]), no polling loop or persistent connection.
  Future<void> _applyTranslations(String code) async {
    await TranslationsCache.instance.loadFromDisk(code);
    notifyListeners();

    await BootstrapCache.instance.refresh(code, _apiClient);
    notifyListeners();
  }
}
