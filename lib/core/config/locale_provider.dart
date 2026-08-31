import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Holds the app's own UI display language (distinct from the tenant's
/// storefront default language, see features/languages/), persisted locally
/// so it survives restarts — same local-first pattern as HeldCartsStore and
/// ThemeProvider.
class LocaleProvider extends ChangeNotifier {
  static const _localeKey = 'zoom_pos.app_locale';
  static const _supportedCodes = ['en', 'hi'];

  Locale locale = const Locale('en');

  Future<void> load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final code = prefs.getString(_localeKey);
      if (code != null && _supportedCodes.contains(code)) {
        locale = Locale(code);
        notifyListeners();
      }
    } catch (e) {
      debugPrint('LocaleProvider.load error: $e');
    }
  }

  Future<void> setLocale(Locale newLocale) async {
    locale = newLocale;
    notifyListeners();
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_localeKey, newLocale.languageCode);
    } catch (e) {
      debugPrint('LocaleProvider.setLocale error: $e');
    }
  }
}
