import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../features/settings/settings_repository.dart';
import '../utils/color_utils.dart';
import 'bootstrap_cache.dart';
import 'theme.dart';

/// Holds the tenant's chosen brand colors, persisted locally so the correct
/// theme paints immediately on the next launch, and refreshed in the
/// background from the server bootstrap payload or saved Settings profile.
class ThemeProvider extends ChangeNotifier {
  static const _colorKey = 'zoom_pos.primary_color';
  static const _accentKey = 'zoom_pos.accent_color';
  static const _drawerBgKey = 'zoom_pos.drawer_bg';
  static const _drawerTextKey = 'zoom_pos.drawer_text';
  static const _activeLinkKey = 'zoom_pos.active_link';
  static const _canvasKey = 'zoom_pos.canvas_bg';
  static const _themeModeKey = 'zoom_pos.theme_mode';

  Color seedColor = AppTheme.primary;
  Color? accentColor;

  /// Per-device surface overrides configured in App Preferences ▸ "Drawer &
  /// surfaces". `null` = use the theme default. All apply instantly.
  Color? drawerBg;
  Color? drawerTextColor;
  Color? activeLinkColor;
  Color? canvasColor;

  /// User-selectable light / dark / follow-system preference, persisted per
  /// device. Defaults to following the OS setting.
  ThemeMode themeMode = ThemeMode.system;

  Future<void> load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final hex = prefs.getString(_colorKey);
      final parsed = hex != null ? parseHexColor(hex) : null;
      if (parsed != null) {
        seedColor = parsed;
      }
      final accentHex = prefs.getString(_accentKey);
      if (accentHex != null) {
        accentColor = parseHexColor(accentHex);
      }
      Color? hexPref(String key) {
        final h = prefs.getString(key);
        return h == null ? null : parseHexColor(h);
      }

      drawerBg = hexPref(_drawerBgKey);
      drawerTextColor = hexPref(_drawerTextKey);
      activeLinkColor = hexPref(_activeLinkKey);
      canvasColor = hexPref(_canvasKey);
      themeMode = _parseThemeMode(prefs.getString(_themeModeKey));
      notifyListeners();
    } catch (e) {
      debugPrint('ThemeProvider.load error: $e');
    }
  }

  static ThemeMode _parseThemeMode(String? raw) {
    switch (raw) {
      case 'light':
        return ThemeMode.light;
      case 'dark':
        return ThemeMode.dark;
      default:
        return ThemeMode.system;
    }
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    if (mode == themeMode) return;
    themeMode = mode;
    notifyListeners();
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_themeModeKey, mode.name);
    } catch (e) {
      debugPrint('ThemeProvider.setThemeMode error: $e');
    }
  }

  Future<void> syncFromBootstrap(BootstrapTheme theme) async {
    bool changed = false;
    final primary = theme.primaryColorValue;
    if (primary != null && primary.toARGB32() != seedColor.toARGB32()) {
      seedColor = primary;
      changed = true;
    }
    final accent = theme.accentColorValue;
    if (accent != null && accent.toARGB32() != accentColor?.toARGB32()) {
      accentColor = accent;
      changed = true;
    }
    final drawer = theme.drawerBgValue;
    if (drawer != null && drawer.toARGB32() != drawerBg?.toARGB32()) {
      drawerBg = drawer;
      changed = true;
    }
    if (changed) {
      notifyListeners();
      try {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(_colorKey, toHexColor(seedColor));
        if (accentColor != null) {
          await prefs.setString(_accentKey, toHexColor(accentColor!));
        }
        if (drawerBg != null) {
          await prefs.setString(_drawerBgKey, toHexColor(drawerBg!));
        }
      } catch (e) {
        debugPrint('ThemeProvider.syncFromBootstrap error: $e');
      }
    }
  }

  Future<void> setColor(Color color) async {
    seedColor = color;
    notifyListeners();
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_colorKey, toHexColor(color));
    } catch (e) {
      debugPrint('ThemeProvider.setColor error: $e');
    }
  }

  Future<void> setAccentColor(Color color) async {
    accentColor = color;
    notifyListeners();
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_accentKey, toHexColor(color));
    } catch (e) {
      debugPrint('ThemeProvider.setAccentColor error: $e');
    }
  }

  Future<void> setDrawerBg(Color color) async {
    drawerBg = color;
    notifyListeners();
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_drawerBgKey, toHexColor(color));
    } catch (e) {
      debugPrint('ThemeProvider.setDrawerBg error: $e');
    }
  }

  Future<void> _setOverride(
      String key, Color? color, void Function(Color?) assign) async {
    assign(color);
    notifyListeners();
    try {
      final prefs = await SharedPreferences.getInstance();
      if (color == null) {
        await prefs.remove(key);
      } else {
        await prefs.setString(key, toHexColor(color));
      }
    } catch (e) {
      debugPrint('ThemeProvider._setOverride($key) error: $e');
    }
  }

  Future<void> setDrawerBgOrNull(Color? c) =>
      _setOverride(_drawerBgKey, c, (v) => drawerBg = v);
  Future<void> setDrawerTextColor(Color? c) =>
      _setOverride(_drawerTextKey, c, (v) => drawerTextColor = v);
  Future<void> setActiveLinkColor(Color? c) =>
      _setOverride(_activeLinkKey, c, (v) => activeLinkColor = v);
  Future<void> setCanvasColor(Color? c) =>
      _setOverride(_canvasKey, c, (v) => canvasColor = v);

  Future<void> resetSurfaceOverrides() async {
    drawerBg = drawerTextColor = activeLinkColor = canvasColor = null;
    notifyListeners();
    try {
      final prefs = await SharedPreferences.getInstance();
      for (final k in const [
        _drawerBgKey,
        _drawerTextKey,
        _activeLinkKey,
        _canvasKey,
      ]) {
        await prefs.remove(k);
      }
    } catch (e) {
      debugPrint('ThemeProvider.resetSurfaceOverrides error: $e');
    }
  }

  /// Best-effort background refresh from the tenant's saved profile — never
  /// throws, since this always runs on top of an already-usable persisted
  /// or default color.
  Future<void> refreshFromServer(SettingsRepository repository) async {
    try {
      final bundle = await repository.fetchAll();
      final parsed = parseHexColor(bundle.profile.primaryColor);
      if (parsed != null && parsed.toARGB32() != seedColor.toARGB32()) {
        await setColor(parsed);
      }
    } catch (e) {
      debugPrint('ThemeProvider.refreshFromServer error: $e');
    }
  }
}
