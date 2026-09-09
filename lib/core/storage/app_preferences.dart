import 'dart:ui';

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
  static const _pageTransitionKey = 'zoom_pos.page_transition';
  static const _dashboardLayoutKey = 'zoom_pos.dashboard_layout';
  static const _windowBoundsKey = 'zoom_pos.window_bounds';

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

  Future<String?> readPageTransition() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString(_pageTransitionKey);
    } catch (e) {
      debugPrint('AppPreferences.readPageTransition error: $e');
      return null;
    }
  }

  Future<void> savePageTransition(String style) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_pageTransitionKey, style);
    } catch (e) {
      debugPrint('AppPreferences.savePageTransition error: $e');
    }
  }

  Future<String?> readDashboardLayout() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString(_dashboardLayoutKey);
    } catch (e) {
      debugPrint('AppPreferences.readDashboardLayout error: $e');
      return null;
    }
  }

  Future<void> saveDashboardLayout(String layout) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_dashboardLayoutKey, layout);
    } catch (e) {
      debugPrint('AppPreferences.saveDashboardLayout error: $e');
    }
  }

  /// The desktop window's last position + size (`x,y,width,height`), so the
  /// Windows app reopens where the user left it. `null` until the first save
  /// (the caller then centres a default-sized window). Windows-only in
  /// practice — never read on Android.
  Future<Rect?> readWindowBounds() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_windowBoundsKey);
      if (raw == null) return null;
      final parts = raw.split(',').map(double.tryParse).toList();
      if (parts.length != 4 || parts.any((p) => p == null)) return null;
      final w = parts[2]!, h = parts[3]!;
      if (w < 400 || h < 300)
        return null; // guard against a corrupt/minimised save
      return Rect.fromLTWH(parts[0]!, parts[1]!, w, h);
    } catch (e) {
      debugPrint('AppPreferences.readWindowBounds error: $e');
      return null;
    }
  }

  Future<void> saveWindowBounds(Rect bounds) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(
        _windowBoundsKey,
        '${bounds.left},${bounds.top},${bounds.width},${bounds.height}',
      );
    } catch (e) {
      debugPrint('AppPreferences.saveWindowBounds error: $e');
    }
  }
}
