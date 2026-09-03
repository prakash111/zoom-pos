import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/settings_models.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/auth_provider.dart';
import '../../dashboard/dashboard_screen.dart' show navSectionsForSettings, NavTileDescriptor;
import '../settings_repository.dart';

class _WorkingItem {
  _WorkingItem({required this.key, required this.label, required this.visible, this.parentKey});
  final String key;
  final String label;
  bool visible;

  /// Another item's key in the same section this item is nested under, or
  /// null for a root-level item — set only via the explicit "Nest under..."
  /// action (see [_NavMenuSettingsTabState._nestItem]), never implied by
  /// drag-reordering. Only one level of nesting is supported: an item that
  /// is itself nested can't become a parent (enforced when building the
  /// "Nest under..." picker's choices).
  String? parentKey;
}

class _WorkingSection {
  _WorkingSection({required this.key, required this.label, required this.tiles});
  final String key;
  final String label;
  List<_WorkingItem> tiles;
}

/// Settings > Navigation Menu — lets a tenant hide drawer/rail/bar
/// destinations it doesn't use, reorder the drawer's section groups, reorder
/// destinations within a section, and move a destination to a different
/// section (DashboardScreen._sectionsFor applies all of it). A store-wide
/// setting, unlike Appearance's per-device dock position, so it's saved
/// through [SettingsRepository] rather than local preferences.
class NavMenuSettingsTab extends StatefulWidget {
  const NavMenuSettingsTab({required this.repository, required this.initial});

  final SettingsRepository repository;
  final NavConfig initial;

  @override
  State<NavMenuSettingsTab> createState() => _NavMenuSettingsTabState();
}

class _NavMenuSettingsTabState extends State<NavMenuSettingsTab> {
  List<_WorkingSection> _sections = [];
  bool _saving = false;

  /// Builds the editable working copy by layering this tenant's saved
  /// overrides (which section an item lives in, its order, its visibility)
  /// on top of the compiled-in defaults — an item with no override keeps
  /// its default section/position, exactly like DashboardScreen._sectionsFor.
  void _ensureSectionsLoaded(AppLocalizations l10n) {
    if (_sections.isNotEmpty) return;
    final company = context.read<AuthProvider>().company;
    final compiled = navSectionsForSettings(l10n, company);
    final compiledByKey = {for (final s in compiled) s.key: s};

    final itemOverrides = {for (final i in widget.initial.items) i.key: i};
    final sectionOrderOverrides = {for (final s in widget.initial.sections) s.key: s.order};

    final grouped = <String, List<(int, NavTileDescriptor)>>{};
    for (final section in compiled) {
      for (var i = 0; i < section.tiles.length; i++) {
        final tile = section.tiles[i];
        final override = itemOverrides[tile.key];
        final targetSectionKey =
            (override?.section != null && compiledByKey.containsKey(override!.section)) ? override.section! : section.key;
        final order = override?.order ?? i;
        (grouped[targetSectionKey] ??= []).add((order, tile));
      }
    }

    final sections = [
      for (final entry in grouped.entries)
        _WorkingSection(
          key: entry.key,
          label: compiledByKey[entry.key]!.label,
          tiles: (entry.value..sort((a, b) => a.$1.compareTo(b.$1)))
              .map((e) => _WorkingItem(
                    key: e.$2.key,
                    label: e.$2.label,
                    visible: itemOverrides[e.$2.key]?.visible ?? true,
                    parentKey: itemOverrides[e.$2.key]?.parent,
                  ))
              .toList(),
        ),
    ];

    // A parent link only holds if it names another tile in the same section
    // that isn't itself nested — otherwise this item falls back to the
    // section root instead of silently vanishing behind a dangling parent.
    for (final section in sections) {
      final keysInSection = {for (final t in section.tiles) t.key: t};
      for (final tile in section.tiles) {
        final parent = tile.parentKey == null ? null : keysInSection[tile.parentKey];
        if (tile.parentKey != null && (parent == null || parent.parentKey != null)) {
          tile.parentKey = null;
        }
      }
    }

    final compiledIndex = {for (var i = 0; i < compiled.length; i++) compiled[i].key: i};
    sections.sort((a, b) {
      final orderA = sectionOrderOverrides[a.key] ?? compiledIndex[a.key] ?? 0;
      final orderB = sectionOrderOverrides[b.key] ?? compiledIndex[b.key] ?? 0;
      return orderA.compareTo(orderB);
    });

    _sections = sections;
  }

  Future<void> _moveItemToSection(_WorkingSection fromSection, _WorkingItem item) async {
    final target = await showModalBottomSheet<_WorkingSection>(
      context: context,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text('Move to section', style: TextStyle(fontWeight: FontWeight.bold)),
            ),
            for (final section in _sections)
              if (section.key != fromSection.key)
                ListTile(
                  title: Text(section.label),
                  onTap: () => Navigator.of(sheetContext).pop(section),
                ),
          ],
        ),
      ),
    );
    if (target == null) return;

    setState(() {
      fromSection.tiles.remove(item);
      // Nesting only makes sense within one section — an item's parent
      // lives in the section it's leaving, and any child of this item
      // (it can't have one — only root items can be parents — but the
      // un-nest below is what keeps that invariant true) would be left
      // dangling too, so both are cleared on a cross-section move.
      item.parentKey = null;
      for (final other in fromSection.tiles) {
        if (other.parentKey == item.key) other.parentKey = null;
      }
      target.tiles.add(item);
    });
  }

  /// "Nest under..." / "Un-nest": lets a tenant group one destination as a
  /// sub-item of another within the same section, or pull it back out to
  /// the root — the settings-tab equivalent of web's drag-onto-another-item
  /// gesture, without needing a full tree-drag widget on mobile. Only root
  /// items (no parent of their own) are offered as nesting targets, since
  /// only one level of nesting is supported.
  Future<void> _nestItem(_WorkingSection section, _WorkingItem item) async {
    final isNested = item.parentKey != null;
    final hasChildren = section.tiles.any((t) => t.parentKey == item.key);
    if (hasChildren) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Un-nest this item\'s own sub-items first.')),
      );
      return;
    }
    final candidates = section.tiles.where((t) => t.key != item.key && t.parentKey == null).toList();

    final choice = await showModalBottomSheet<String>(
      context: context,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text('Nest under', style: TextStyle(fontWeight: FontWeight.bold)),
            ),
            if (isNested)
              ListTile(
                leading: const Icon(Icons.first_page),
                title: const Text('Un-nest (move to top level)'),
                onTap: () => Navigator.of(sheetContext).pop(''),
              ),
            for (final candidate in candidates)
              ListTile(
                title: Text(candidate.label),
                onTap: () => Navigator.of(sheetContext).pop(candidate.key),
              ),
          ],
        ),
      ),
    );
    if (choice == null) return;

    setState(() => item.parentKey = choice.isEmpty ? null : choice);
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final messenger = ScaffoldMessenger.of(context);
    final l10n = AppLocalizations.of(context);
    try {
      await widget.repository.updateNavConfig(NavConfig(
        sections: [for (var i = 0; i < _sections.length; i++) NavSectionOrder(key: _sections[i].key, order: i)],
        items: [
          for (final section in _sections)
            for (var i = 0; i < section.tiles.length; i++)
              NavItemConfig(
                key: section.tiles[i].key,
                section: section.key,
                parent: section.tiles[i].parentKey,
                order: i,
                visible: section.tiles[i].visible,
              ),
        ],
      ));
      if (!mounted) return;
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
              Expanded(
                child: Text(l10n.navMenuSectionOrderHint, style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
              ),
            ],
          ),
        ),
        Expanded(
          child: ReorderableListView(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
            buildDefaultDragHandles: false,
            onReorderItem: (oldIndex, newIndex) {
              setState(() {
                final section = _sections.removeAt(oldIndex);
                _sections.insert(newIndex, section);
              });
            },
            children: [
              for (var sectionIndex = 0; sectionIndex < _sections.length; sectionIndex++)
                Card(
                  key: ValueKey(_sections[sectionIndex].key),
                  margin: const EdgeInsets.symmetric(vertical: 6),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(_sections[sectionIndex].label, style: const TextStyle(fontWeight: FontWeight.bold)),
                            ),
                            // Only this handle starts an outer (section)
                            // drag — the rest of the card, including the
                            // inner reorderable list below, is untouched by
                            // it, so inner item drags never get hijacked by
                            // the outer list.
                            ReorderableDragStartListener(
                              index: sectionIndex,
                              child: Icon(Icons.drag_handle, color: Colors.grey.shade400),
                            ),
                          ],
                        ),
                      ),
                      ReorderableListView(
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        buildDefaultDragHandles: false,
                        onReorderItem: (oldIndex, newIndex) {
                          setState(() {
                            final tiles = _sections[sectionIndex].tiles;
                            final item = tiles.removeAt(oldIndex);
                            tiles.insert(newIndex, item);
                          });
                        },
                        children: [
                          for (var itemIndex = 0; itemIndex < _sections[sectionIndex].tiles.length; itemIndex++)
                            Padding(
                              key: ValueKey(_sections[sectionIndex].tiles[itemIndex].key),
                              padding: EdgeInsets.only(
                                left: _sections[sectionIndex].tiles[itemIndex].parentKey != null ? 32 : 8,
                                right: 8,
                              ),
                              child: Row(
                                children: [
                                  if (_sections[sectionIndex].tiles[itemIndex].parentKey != null)
                                    Icon(Icons.subdirectory_arrow_right, size: 16, color: Colors.grey.shade400),
                                  Checkbox(
                                    value: _sections[sectionIndex].tiles[itemIndex].visible,
                                    onChanged: (checked) =>
                                        setState(() => _sections[sectionIndex].tiles[itemIndex].visible = checked ?? true),
                                  ),
                                  Expanded(child: Text(_sections[sectionIndex].tiles[itemIndex].label)),
                                  IconButton(
                                    icon: Icon(
                                      _sections[sectionIndex].tiles[itemIndex].parentKey != null
                                          ? Icons.subdirectory_arrow_right_outlined
                                          : Icons.turn_slight_right,
                                      size: 20,
                                    ),
                                    tooltip: 'Nest under',
                                    onPressed: _sections[sectionIndex].tiles.length < 2
                                        ? null
                                        : () => _nestItem(
                                              _sections[sectionIndex],
                                              _sections[sectionIndex].tiles[itemIndex],
                                            ),
                                  ),
                                  IconButton(
                                    icon: const Icon(Icons.drive_file_move_outline, size: 20),
                                    tooltip: 'Move to section',
                                    onPressed: _sections.length < 2
                                        ? null
                                        : () => _moveItemToSection(
                                              _sections[sectionIndex],
                                              _sections[sectionIndex].tiles[itemIndex],
                                            ),
                                  ),
                                  ReorderableDragStartListener(
                                    index: itemIndex,
                                    child: Icon(Icons.drag_handle, color: Colors.grey.shade400),
                                  ),
                                ],
                              ),
                            ),
                        ],
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
