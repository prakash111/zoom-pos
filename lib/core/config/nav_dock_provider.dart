import 'package:flutter/foundation.dart';

import '../storage/app_preferences.dart';
import 'page_transitions.dart';

/// Where the app's main navigation dock is docked — set from
/// Settings > Appearance and applied by [DashboardScreen].
enum NavDockPosition {
  left,
  top,
  right,
  bottom;

  static NavDockPosition fromName(String? name) {
    return NavDockPosition.values.firstWhere(
      (p) => p.name == name,
      orElse: () => NavDockPosition.left,
    );
  }
}

/// Persists the chosen dock position locally (a per-device workspace
/// preference, not a tenant/business setting, so it's never synced to the
/// server) — same local-first pattern as [ThemeProvider]/[LocaleProvider].
class NavDockProvider extends ChangeNotifier {
  NavDockProvider({required AppPreferences preferences})
      : _preferences = preferences;

  final AppPreferences _preferences;

  NavDockPosition position = NavDockPosition.left;

  /// The page-move animation applied app-wide when navigating between screens.
  AppPageTransition transition = AppPageTransition.slide;

  Future<void> load() async {
    final saved = await _preferences.readNavDockPosition();
    if (saved != null) {
      position = NavDockPosition.fromName(saved);
    }
    final savedTransition = await _preferences.readPageTransition();
    if (savedTransition != null) {
      transition = AppPageTransition.fromName(savedTransition);
    }
    notifyListeners();
  }

  Future<void> setPosition(NavDockPosition value) async {
    if (position == value) return;
    position = value;
    notifyListeners();
    await _preferences.saveNavDockPosition(value.name);
  }

  Future<void> setTransition(AppPageTransition value) async {
    if (transition == value) return;
    transition = value;
    notifyListeners();
    await _preferences.savePageTransition(value.name);
  }
}
