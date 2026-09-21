import 'package:flutter/foundation.dart';

import '../storage/app_preferences.dart';
import 'dashboard_layout.dart';
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

  /// Which visual treatment the authenticated dashboard home renders.
  DashboardLayout dashboardLayout = DashboardLayout.redesigned;

  Future<void> load() async {
    final saved = await _preferences.readNavDockPosition();
    if (saved != null) {
      position = NavDockPosition.fromName(saved);
    }
    final savedTransition = await _preferences.readPageTransition();
    if (savedTransition != null) {
      transition = AppPageTransition.fromName(savedTransition);
    }
    final savedLayout = await _preferences.readDashboardLayout();
    if (savedLayout != null) {
      dashboardLayout = DashboardLayout.fromName(savedLayout);
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

  Future<void> setDashboardLayout(DashboardLayout value) async {
    if (dashboardLayout == value) return;
    dashboardLayout = value;
    notifyListeners();
    await _preferences.saveDashboardLayout(value.name);
  }
}
