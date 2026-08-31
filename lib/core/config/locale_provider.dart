import 'package:flutter/material.dart';

import '../storage/app_preferences.dart';

/// Holds the app's own UI display language (distinct from the tenant's
/// storefront default language, see features/languages/), persisted locally
/// via [AppPreferences] so it survives restarts — same local-first pattern
/// as HeldCartsStore and ThemeProvider. The persisted code is also what
/// [ApiClient] sends as the `Accept-Language` header on every request, so
/// switching the language here also drives backend-rendered content.
class LocaleProvider extends ChangeNotifier {
  LocaleProvider({required AppPreferences preferences}) : _preferences = preferences;

  final AppPreferences _preferences;

  /// Every locale the Laravel backend's language catalog supports
  /// (LocalizationService::$defaultLanguages) — kept in sync with that list
  /// so a store's chosen default language is never silently unsupported.
  static const supportedCodes = ['en', 'es', 'fr', 'de', 'ar', 'hi', 'pt', 'it', 'zh', 'ja', 'ru', 'id', 'tr'];

  Locale locale = const Locale('en');

  Future<void> load() async {
    final code = await _preferences.readLocale();
    if (supportedCodes.contains(code)) {
      locale = Locale(code);
      notifyListeners();
    }
  }

  Future<void> setLocale(Locale newLocale) async {
    locale = newLocale;
    notifyListeners();
    await _preferences.saveLocale(newLocale.languageCode);
  }
}
