import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/settings_models.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/auth_provider.dart';
import '../../dashboard/dashboard_screen.dart' show navSectionsForSettings, NavSectionDescriptor;
import '../settings_repository.dart';

/// Settings > Navigation Menu — lets a tenant hide drawer/rail/bar
/// destinations it doesn't use and reorder the drawer's section groups
/// (DashboardScreen._sectionsFor applies both). A store-wide setting, unlike
/// Appearance's per-device dock position, so it's saved through
/// [SettingsRepository] rather than local preferences.
class NavMenuSettingsTab extends StatefulWidget {
  const NavMenuSettingsTab({required this.repository, required this.initial});

  final SettingsRepository repository;
  final NavConfig initial;

  @override
  State<NavMenuSettingsTab> createState() => _NavMenuSettingsTabState();
}

class _NavMenuSettingsTabState extends State<NavMenuSettingsTab> {
  late List<NavSectionDescriptor> _sections;
  late Set<String> _hiddenTiles;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _hiddenTiles = widget.initial.hiddenTiles.toSet();
    _sections = [];
  }

  void _ensureSectionsLoaded(AppLocalizations l10n) {
    if (_sections.isNotEmpty) return;
    final company = context.read<AuthProvider>().company;
    final compiled = navSectionsForSettings(l10n, company);
    final order = widget.initial.sectionOrder;
    if (order.isEmpty) {
      _sections = compiled;
      return;
    }
    final byKey = {for (final s in compiled) s.key: s};
    _sections = [
      for (final key in order)
        if (byKey.containsKey(key)) byKey.remove(key)!,
      ...byKey.values,
    ];
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final messenger = ScaffoldMessenger.of(context);
    final l10n = AppLocalizations.of(context);
    try {
      final saved = await widget.repository.updateNavConfig(NavConfig(
        hiddenTiles: _hiddenTiles.toList(),
        sectionOrder: [for (final s in _sections) s.key],
      ));
      if (!mounted) return;
      setState(() => _hiddenTiles = saved.hiddenTiles.toSet());
      messenger.showSnackBar(SnackBar(content: Text(l10n.navMenuSaved)));
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    _ensureSectionsLoaded(l10n);

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: Text(l10n.navMenuDescription, style: TextStyle(color: Colors.grey.shade600)),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(
            children: [
              Icon(Icons.drag_handle, size: 18, color: Colors.grey.shade600),
              const SizedBox(width: 6),
              Text(l10n.navMenuSectionOrderHint, style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
            ],
          ),
        ),
        Expanded(
          child: ReorderableListView(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
            onReorderItem: (oldIndex, newIndex) {
              setState(() {
                final section = _sections.removeAt(oldIndex);
                _sections.insert(newIndex, section);
              });
            },
            children: [
              for (final section in _sections)
                Card(
                  key: ValueKey(section.key),
                  margin: const EdgeInsets.symmetric(vertical: 6),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(section.label, style: const TextStyle(fontWeight: FontWeight.bold)),
                            ),
                            Icon(Icons.drag_handle, color: Colors.grey.shade400),
                          ],
                        ),
                      ),
                      for (final tile in section.tiles)
                        CheckboxListTile(
                          value: !_hiddenTiles.contains(tile.key),
                          dense: true,
                          controlAffinity: ListTileControlAffinity.leading,
                          title: Text(tile.label),
                          onChanged: (checked) {
                            setState(() {
                              if (checked ?? true) {
                                _hiddenTiles.remove(tile.key);
                              } else {
                                _hiddenTiles.add(tile.key);
                              }
                            });
                          },
                        ),
                      const SizedBox(height: 4),
                    ],
                  ),
                ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: ElevatedButton(
            style: ElevatedButton.styleFrom(minimumSize: const Size(double.infinity, 50)),
            onPressed: _saving ? null : _save,
            child: _saving
                ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : Text(MaterialLocalizations.of(context).saveButtonLabel),
          ),
        ),
      ],
    );
  }
}
