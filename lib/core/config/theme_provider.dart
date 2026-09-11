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
  // `_drawerBgKey` holds the tenant's *server-synced* drawer colour
  // (bootstrap `theme.drawer_bg`) — refreshed on every dashboard/bootstrap
  // refresh. `_drawerBgOverrideKey` is the separate, App-Preferences-driven
  // *per-device* choice; keeping these in two slots is what lets a dashboard
  // refresh update the former without ever touching (or persisting over) the
  // latter. See [syncFromBootstrap] / [setDrawerBgOrNull].
  static const _drawerBgKey = 'zoom_pos.drawer_bg';
  static const _drawerBgOverrideKey = 'zoom_pos.drawer_bg_override';
  static const _drawerTextKey = 'zoom_pos.drawer_text';
  static const _activeLinkKey = 'zoom_pos.active_link';
  static const _canvasKey = 'zoom_pos.canvas_bg';
  static const _themeModeKey = 'zoom_pos.theme_mode';

  Color seedColor = AppTheme.primary;
  Color? accentColor;

  /// The Superadmin platform primary (from `GET /auth/branding` →
  /// `theme.primary` / `brand_color`). Used as the seed when this tenant has
  /// no brand colour of its own, so a Superadmin change reflects immediately.
  Color? platformSeedColor;

  /// True once a tenant-owned colour has been applied (persisted locally, from
  /// the bootstrap payload, or set in Settings). While false, the platform
  /// seed drives the theme.
  bool _tenantColorExplicit = false;

  /// Safe hex → [Color] parse (`#RRGGBB` / `RRGGBB` / `#AARRGGBB`).
  static Color hexToColor(String? hexString,
      {Color fallback = const Color(0xFFF95700)}) {
    if (hexString == null || hexString.trim().isEmpty) return fallback;
    final buffer = StringBuffer();
    final raw = hexString.replaceFirst('#', '').trim();
    if (raw.length == 6) buffer.write('ff');
    buffer.write(raw);
    try {
      return Color(int.parse(buffer.toString(), radix: 16));
    } catch (_) {
      return fallback;
    }
  }

  /// Applies the Superadmin platform primary as the theme seed — but only
  /// when this tenant hasn't chosen its own brand colour. Rebuilds the app
  /// immediately via [notifyListeners].
  void applyPlatformSeed(Color color) {
    platformSeedColor = color;
    if (_tenantColorExplicit) return;
    if (seedColor.toARGB32() == color.toARGB32()) return;
    seedColor = color;
    notifyListeners();
  }

  /// Per-device surface overrides configured in App Preferences ▸ "Drawer &
  /// surfaces". `null` = use the theme default. All apply instantly.
  Color? drawerBg;
  Color? drawerTextColor;
  Color? activeLinkColor;
  Color? canvasColor;

  /// True when [drawerBg] came from the user's own "Drawer & surfaces" pick
  /// (App Preferences), not from the tenant's server-synced theme. Consumers
  /// should honour an explicit override unconditionally (even if it doesn't
  /// contrast well with the active light/dark mode — that's the user's
  /// deliberate choice), and only fall back to a mode-matched default
  /// otherwise. See dashboard_screen.dart's drawer background resolution.
  bool isDrawerBgUserOverride = false;

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
        _tenantColorExplicit = true;
      }
      final accentHex = prefs.getString(_accentKey);
      if (accentHex != null) {
        accentColor = parseHexColor(accentHex);
      }
      Color? hexPref(String key) {
        final h = prefs.getString(key);
        return h == null ? null : parseHexColor(h);
      }

      final overrideDrawerBg = hexPref(_drawerBgOverrideKey);
      isDrawerBgUserOverride = overrideDrawerBg != null;
      drawerBg = overrideDrawerBg ?? hexPref(_drawerBgKey);
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

  /// Applies the tenant's server bootstrap theme. Runs on every app start
  /// *and* on every dashboard/pull-to-refresh (via BootstrapCache.refresh),
  /// so it must never clobber a per-device App Preferences override:
  /// `drawerBg` is only touched here when [isDrawerBgUserOverride] is false —
  /// an explicit local pick always wins and is never reset or overwritten in
  /// SharedPreferences by a refresh.
  Future<void> syncFromBootstrap(BootstrapTheme theme) async {
    bool changed = false;
    bool drawerChanged = false;
    final primary = theme.primaryColorValue;
    if (primary != null) {
      _tenantColorExplicit = true;
      if (primary.toARGB32() != seedColor.toARGB32()) {
        seedColor = primary;
        changed = true;
      }
    }
    final accent = theme.accentColorValue;
    if (accent != null && accent.toARGB32() != accentColor?.toARGB32()) {
      accentColor = accent;
      changed = true;
    }
    final drawer = theme.drawerBgValue;
    if (!isDrawerBgUserOverride &&
        drawer != null &&
        drawer.toARGB32() != drawerBg?.toARGB32()) {
      drawerBg = drawer;
      changed = true;
      drawerChanged = true;
    }
    if (changed) {
      notifyListeners();
      try {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(_colorKey, toHexColor(seedColor));
        if (accentColor != null) {
          await prefs.setString(_accentKey, toHexColor(accentColor!));
        }
        // Only the server-synced slot — never the per-device override key.
        if (drawerChanged && drawerBg != null) {
          await prefs.setString(_drawerBgKey, toHexColor(drawerBg!));
        }
      } catch (e) {
        debugPrint('ThemeProvider.syncFromBootstrap error: $e');
      }
    }
  }

  Future<void> setColor(Color color) async {
    seedColor = color;
    _tenantColorExplicit = true;
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

  /// Sets the per-device drawer override (App Preferences ▸ "Drawer
  /// background"). Written to [_drawerBgOverrideKey] — a separate slot from
  /// the tenant's server-synced colour — so it's immune to
  /// [syncFromBootstrap] on the next refresh.
  Future<void> setDrawerBg(Color color) => setDrawerBgOrNull(color);

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

  Future<void> setDrawerBgOrNull(Color? c) async {
    isDrawerBgUserOverride = c != null;
    if (c != null) {
      await _setOverride(_drawerBgOverrideKey, c, (v) => drawerBg = v);
      return;
    }
    // Clearing the override: fall back to whatever the tenant's own synced
    // theme has (not necessarily null) rather than the theme default.
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_drawerBgOverrideKey);
      final synced = prefs.getString(_drawerBgKey);
      drawerBg = synced != null ? parseHexColor(synced) : null;
    } catch (e) {
      debugPrint('ThemeProvider.setDrawerBgOrNull(null) error: $e');
      drawerBg = null;
    }
    notifyListeners();
  }

  Future<void> setDrawerTextColor(Color? c) =>
      _setOverride(_drawerTextKey, c, (v) => drawerTextColor = v);
  Future<void> setActiveLinkColor(Color? c) =>
      _setOverride(_activeLinkKey, c, (v) => activeLinkColor = v);
  Future<void> setCanvasColor(Color? c) =>
      _setOverride(_canvasKey, c, (v) => canvasColor = v);

  Future<void> resetSurfaceOverrides() async {
    drawerTextColor = activeLinkColor = canvasColor = null;
    isDrawerBgUserOverride = false;
    notifyListeners();
    try {
      final prefs = await SharedPreferences.getInstance();
      final synced = prefs.getString(_drawerBgKey);
      drawerBg = synced != null ? parseHexColor(synced) : null;
      for (final k in const [
        _drawerBgOverrideKey,
        _drawerTextKey,
        _activeLinkKey,
        _canvasKey,
      ]) {
        await prefs.remove(k);
      }
      notifyListeners();
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
