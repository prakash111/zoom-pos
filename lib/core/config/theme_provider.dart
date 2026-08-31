import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../features/settings/settings_repository.dart';
import '../utils/color_utils.dart';
import 'theme.dart';

/// Holds the tenant's chosen brand color, persisted locally so the correct
/// theme paints immediately on the next launch, and refreshed in the
/// background from the tenant's saved Settings profile — the same
/// local-first, network-refresh-second pattern as [HeldCartsStore].
class ThemeProvider extends ChangeNotifier {
  static const _colorKey = 'zoom_pos.primary_color';

  Color seedColor = AppTheme.primary;

  Future<void> load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final hex = prefs.getString(_colorKey);
      final parsed = hex != null ? parseHexColor(hex) : null;
      if (parsed != null) {
        seedColor = parsed;
        notifyListeners();
      }
    } catch (e) {
      debugPrint('ThemeProvider.load error: $e');
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

  /// Best-effort background refresh from the tenant's saved profile — never
  /// throws, since this always runs on top of an already-usable persisted
  /// or default color.
  Future<void> refreshFromServer(SettingsRepository repository) async {
    try {
      final bundle = await repository.fetchAll();
      final parsed = parseHexColor(bundle.profile.primaryColor);
      if (parsed != null && parsed.value != seedColor.value) {
        await setColor(parsed);
      }
    } catch (e) {
      debugPrint('ThemeProvider.refreshFromServer error: $e');
    }
  }
}
