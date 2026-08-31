import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../config/app_config.dart';

/// Non-secret, per-device settings — which server this terminal talks to
/// (store owners can self-host on a custom domain) and the app's own
/// display/API locale.
class AppPreferences {
  static const _baseUrlKey = 'zoom_pos.base_url';
  static const _localeKey = 'zoom_pos.locale';
  static const _navDockPositionKey = 'zoom_pos.nav_dock_position';

  Future<String> readBaseUrl() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString(_baseUrlKey) ?? AppConfig.defaultBaseUrl;
    } catch (e) {
      debugPrint('AppPreferences.readBaseUrl error: $e');
      return AppConfig.defaultBaseUrl;
    }
  }

  Future<void> saveBaseUrl(String url) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final normalized = url.trim().replaceAll(RegExp(r'/+$'), '');
      await prefs.setString(_baseUrlKey, normalized);
    } catch (e) {
      debugPrint('AppPreferences.saveBaseUrl error: $e');
    }
  }

  /// The locale code (e.g. `en`, `ar`, `hi`) used both for this app's own
  /// display language and as the `Accept-Language` header sent on every
  /// request to the Laravel backend, so server-rendered content (PDFs,
  /// emails, validation messages) matches what the user picked.
  Future<String> readLocale() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString(_localeKey) ?? 'en';
    } catch (e) {
      debugPrint('AppPreferences.readLocale error: $e');
      return 'en';
    }
  }

  Future<void> saveLocale(String code) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_localeKey, code);
    } catch (e) {
      debugPrint('AppPreferences.saveLocale error: $e');
    }
  }

  /// Where the main app's navigation dock is docked (`left`, `top`, `right`,
  /// `bottom`) — a per-device workspace preference, set from
  /// Settings > Appearance. Returns `null` when nothing's been saved yet, so
  /// the caller can apply its own default.
  Future<String?> readNavDockPosition() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString(_navDockPositionKey);
    } catch (e) {
      debugPrint('AppPreferences.readNavDockPosition error: $e');
      return null;
    }
  }

  Future<void> saveNavDockPosition(String position) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_navDockPositionKey, position);
    } catch (e) {
      debugPrint('AppPreferences.saveNavDockPosition error: $e');
    }
  }
}
