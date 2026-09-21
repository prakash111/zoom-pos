import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/config/dashboard_layout.dart';
import '../../../core/config/nav_dock_provider.dart';
import '../../../core/config/page_transitions.dart';
import '../../../core/config/theme.dart';
import '../../../core/config/theme_provider.dart';
import '../../../core/utils/color_utils.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/auth_provider.dart';
import '../settings_repository.dart';

/// Workspace preferences: the brand colour (applied instantly, also pushed to
/// the tenant profile) plus the per-device dashboard layout, light/dark theme,
/// page-move animation and navigation-dock placement. Shown both as its own
/// screen (opened from the dashboard app bar) and inside the Settings ▸
/// Appearance tab.
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
          'Brand colour',
          'Applied to buttons, active menu items, badges and focus rings — '
              'takes effect immediately.',
          const _BrandColorGroup(),
        ),
        group(
          'Drawer & surfaces',
          'Fine-tune the sidebar and canvas. Leave any row on "Default" to '
              'follow the theme. Changes apply instantly.',
          const _SurfaceColorsGroup(),
        ),
        group(
          'Dashboard layout',
          'Pick which design the home dashboard uses. All layouts show live store data.',
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

/// Preset brand-colour swatches + a hex field. Picking one recolours the whole
/// app instantly (ThemeProvider → SharedPreferences), and is pushed to the
/// tenant profile in the background so a later bootstrap sync doesn't revert
/// it.
class _BrandColorGroup extends StatefulWidget {
  const _BrandColorGroup();

  @override
  State<_BrandColorGroup> createState() => _BrandColorGroupState();
}

class _BrandColorGroupState extends State<_BrandColorGroup> {
  static const _swatches = <Color>[
    Color(0xFF2563EB),
    Color(0xFF7C3AED),
    Color(0xFF0D9488),
    Color(0xFF16A34A),
    Color(0xFF65A30D),
    Color(0xFFCA8A04),
    Color(0xFFEA580C),
    Color(0xFFDC2626),
    Color(0xFFDB2777),
    Color(0xFF0F172A),
  ];

  late final TextEditingController _hex;
  Timer? _persistDebounce;

  @override
  void initState() {
    super.initState();
    _hex = TextEditingController(
        text: toHexColor(context.read<ThemeProvider>().seedColor));
  }

  @override
  void dispose() {
    _persistDebounce?.cancel();
    _hex.dispose();
    super.dispose();
  }

  void _apply(Color color) {
    // 1. Instant local persistence + live re-theme.
    context.read<ThemeProvider>().setColor(color);
    _hex.text = toHexColor(color);
    setState(() {});
    // 2. Background server persist (debounced) so it survives bootstrap sync.
    _persistDebounce?.cancel();
    final company = context.read<AuthProvider>().company;
    final repo = SettingsRepository(context.read<ApiClient>());
    _persistDebounce = Timer(const Duration(milliseconds: 700), () async {
      if (company == null) return;
      try {
        await repo.updateProfile(
          name: company.name,
          primaryColor: toHexColor(color),
        );
      } catch (_) {
        // Best effort — the local SharedPreferences value already applied.
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final selected = context.watch<ThemeProvider>().seedColor;
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                for (final c in _swatches)
                  InkWell(
                    onTap: () => _apply(c),
                    customBorder: const CircleBorder(),
                    child: Container(
                      width: 34,
                      height: 34,
                      decoration: BoxDecoration(
                        color: c,
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: c.toARGB32() == selected.toARGB32()
                              ? Theme.of(context).colorScheme.onSurface
                              : Theme.of(context).colorScheme.outlineVariant,
                          width: c.toARGB32() == selected.toARGB32() ? 2.5 : 1,
                        ),
                      ),
                      child: c.toARGB32() == selected.toARGB32()
                          ? const Icon(Icons.check,
                              size: 18, color: Colors.white)
                          : null,
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: selected,
                    shape: BoxShape.circle,
                    border: Border.all(
                        color: Theme.of(context).colorScheme.outlineVariant),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextField(
                    controller: _hex,
                    decoration: const InputDecoration(
                      labelText: 'Custom hex',
                      isDense: true,
                    ),
                    onChanged: (v) {
                      final parsed = parseHexColor(v);
                      if (parsed != null) _apply(parsed);
                    },
                  ),
                ),
                const SizedBox(width: 8),
                TextButton(
                  onPressed: () => _apply(AppTheme.primary),
                  child: const Text('Reset'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Four optional surface colours (drawer bg / drawer text / active-link /
/// canvas). Each row offers a small swatch palette plus a "Default" chip that
/// clears the override. Bound straight to [ThemeProvider] — instant + local.
class _SurfaceColorsGroup extends StatelessWidget {
  const _SurfaceColorsGroup();

  static const _palette = <Color>[
    Color(0xFF0F172A),
    Color(0xFF1E293B),
    Color(0xFF334155),
    Color(0xFF475569),
    Color(0xFFF8FAFC),
    Color(0xFFFFFFFF),
    Color(0xFF2563EB),
    Color(0xFF7C3AED),
    Color(0xFF0D9488),
    Color(0xFF16A34A),
    Color(0xFFCA8A04),
    Color(0xFFDC2626),
  ];

  @override
  Widget build(BuildContext context) {
    final tp = context.watch<ThemeProvider>();
    final scheme = Theme.of(context).colorScheme;

    Widget row(String label, Color? value, void Function(Color?) onChanged) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(label,
                      style: const TextStyle(fontWeight: FontWeight.w600)),
                ),
                if (value != null)
                  TextButton(
                    onPressed: () => onChanged(null),
                    child: const Text('Default'),
                  ),
              ],
            ),
            const SizedBox(height: 6),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                for (final c in _palette)
                  InkWell(
                    onTap: () => onChanged(c),
                    customBorder: const CircleBorder(),
                    child: Container(
                      width: 30,
                      height: 30,
                      decoration: BoxDecoration(
                        color: c,
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: value?.toARGB32() == c.toARGB32()
                              ? scheme.onSurface
                              : scheme.outlineVariant,
                          width: value?.toARGB32() == c.toARGB32() ? 2.5 : 1,
                        ),
                      ),
                      child: value?.toARGB32() == c.toARGB32()
                          ? const Icon(Icons.check,
                              size: 15, color: Colors.white)
                          : null,
                    ),
                  ),
              ],
            ),
          ],
        ),
      );
    }

    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            row('Drawer background', tp.drawerBg, tp.setDrawerBgOrNull),
            const Divider(height: 12),
            row('Drawer text & icons', tp.drawerTextColor,
                tp.setDrawerTextColor),
            const Divider(height: 12),
            row('Active link / selected', tp.activeLinkColor,
                tp.setActiveLinkColor),
            const Divider(height: 12),
            row('Canvas / scaffold background', tp.canvasColor,
                tp.setCanvasColor),
            const SizedBox(height: 6),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                onPressed: tp.resetSurfaceOverrides,
                icon: const Icon(Icons.restart_alt, size: 18),
                label: const Text('Reset all to default'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
