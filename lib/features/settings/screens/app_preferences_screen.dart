import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/config/dashboard_layout.dart';
import '../../../core/config/nav_dock_provider.dart';
import '../../../core/config/page_transitions.dart';
import '../../../core/config/theme_provider.dart';
import '../../../l10n/app_localizations.dart';

/// Per-device workspace preferences that never touch the server: light/dark
/// theme, the page-move animation, and where the navigation dock sits. Shown
/// both as its own screen (opened from the dashboard app bar) and inside the
/// Settings ▸ Appearance tab.
class AppPreferencesScreen extends StatelessWidget {
  const AppPreferencesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('App Preferences')),
      body: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 640),
          child: const AppPreferencesBody(),
        ),
      ),
    );
  }
}

/// The scrollable list of preference groups, reused by [AppPreferencesScreen]
/// and the Settings ▸ Appearance tab.
class AppPreferencesBody extends StatelessWidget {
  const AppPreferencesBody({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final navDock = context.watch<NavDockProvider>();
    final themeProvider = context.watch<ThemeProvider>();
    final muted = Theme.of(context).colorScheme.onSurfaceVariant;

    const themeOptions = <(ThemeMode, IconData, String)>[
      (ThemeMode.system, Icons.brightness_auto_outlined, 'Match device'),
      (ThemeMode.light, Icons.light_mode_outlined, 'Light'),
      (ThemeMode.dark, Icons.dark_mode_outlined, 'Dark'),
    ];

    final dockOptions = <(NavDockPosition, IconData, String)>[
      (NavDockPosition.left, Icons.arrow_back, l10n.navDockLeft),
      (NavDockPosition.top, Icons.arrow_upward, l10n.navDockTop),
      (NavDockPosition.right, Icons.arrow_forward, l10n.navDockRight),
      (NavDockPosition.bottom, Icons.arrow_downward, l10n.navDockBottom),
    ];

    Widget group(String title, String subtitle, Widget child) => Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 4),
            Text(subtitle, style: TextStyle(color: muted)),
            const SizedBox(height: 12),
            child,
            const SizedBox(height: 24),
          ],
        );

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        group(
          'Dashboard layout',
          'Pick which design the home dashboard uses. Both show the same data.',
          RadioGroup<DashboardLayout>(
            groupValue: navDock.dashboardLayout,
            onChanged: (value) {
              if (value != null) navDock.setDashboardLayout(value);
            },
            child: Card(
              margin: EdgeInsets.zero,
              child: Column(
                children: [
                  for (final l in DashboardLayout.values)
                    RadioListTile<DashboardLayout>(
                      value: l,
                      secondary: Icon(l.icon),
                      title: Text(l.label),
                      subtitle: Text(l.description),
                    ),
                ],
              ),
            ),
          ),
        ),
        group(
          'Theme',
          'Choose how the app looks on this device.',
          RadioGroup<ThemeMode>(
            groupValue: themeProvider.themeMode,
            onChanged: (value) {
              if (value != null) themeProvider.setThemeMode(value);
            },
            child: Card(
              margin: EdgeInsets.zero,
              child: Column(
                children: [
                  for (final option in themeOptions)
                    RadioListTile<ThemeMode>(
                      value: option.$1,
                      secondary: Icon(option.$2),
                      title: Text(option.$3),
                    ),
                ],
              ),
            ),
          ),
        ),
        group(
          'Page transition',
          'How screens move when you open a link or menu item.',
          RadioGroup<AppPageTransition>(
            groupValue: navDock.transition,
            onChanged: (value) {
              if (value != null) navDock.setTransition(value);
            },
            child: Card(
              margin: EdgeInsets.zero,
              child: Column(
                children: [
                  for (final style in AppPageTransition.values)
                    RadioListTile<AppPageTransition>(
                      value: style,
                      secondary: Icon(style.icon),
                      title: Text(style.label),
                      subtitle: Text(style.description),
                    ),
                ],
              ),
            ),
          ),
        ),
        group(
          l10n.navDockTitle,
          l10n.navDockDescription,
          RadioGroup<NavDockPosition>(
            groupValue: navDock.position,
            onChanged: (value) {
              if (value != null) navDock.setPosition(value);
            },
            child: Card(
              margin: EdgeInsets.zero,
              child: Column(
                children: [
                  for (final option in dockOptions)
                    RadioListTile<NavDockPosition>(
                      value: option.$1,
                      secondary: Icon(option.$2),
                      title: Text(option.$3),
                    ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}
