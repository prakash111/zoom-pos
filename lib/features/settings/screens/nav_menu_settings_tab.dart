import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/settings_models.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/auth_provider.dart';
import '../../dashboard/dashboard_screen.dart' show navSectionsForSettings;
import '../settings_repository.dart';

const double navigationIndentStep = 30;
const double _levelOneThreshold = 20;
const double _levelTwoThreshold = 50;
const int _maximumNavigationLevel = 2;

/// Converts the row's effective horizontal offset into the three snapped
/// columns required by the mobile menu editor.
int navigationIndentForOffset(double dx) {
  if (dx < _levelOneThreshold) return 0;
  if (dx < _levelTwoThreshold) return 1;
  return 2;
}

String navigationLevelLabel(int level) {
  return switch (level.clamp(0, _maximumNavigationLevel)) {
    1 => 'Sub-Menu',
    2 => 'Sub-Sub-Menu',
    _ => 'Main Menu',
  };
}

class _WorkingItem {
  _WorkingItem({
    required this.key,
    required this.label,
    required this.visible,
    this.parentKey,
  });

  final String key;
  final String label;
  bool visible;
  String? parentKey;
}

class _WorkingSection {
  _WorkingSection(
      {required this.key, required this.label, required this.tiles});

  final String key;
  final String label;
  List<_WorkingItem> tiles;
}

/// Store-wide WordPress-style navigation outline editor. Vertical dragging
/// changes preorder; horizontal dragging snaps the active row to 0/30/60 px
/// and derives its parent from the nearest valid preceding row.
class NavMenuSettingsTab extends StatefulWidget {
  const NavMenuSettingsTab({required this.repository, required this.initial});

  final SettingsRepository repository;
  final NavConfig initial;

  @override
  State<NavMenuSettingsTab> createState() => _NavMenuSettingsTabState();
}

class _NavMenuSettingsTabState extends State<NavMenuSettingsTab> {
  final ValueNotifier<int> _dragDepthNotifier = ValueNotifier<int>(0);

  List<_WorkingSection> _sections = [];
  bool _saving = false;
  int? _dragSectionIndex;
  String? _dragItemKey;
  double? _dragStartX;
  int _dragStartDepth = 0;
  int _dragPreviewDepth = 0;
  int _dragMaximumDepth = _maximumNavigationLevel;
  bool _dragReorderApplied = false;
  Timer? _dragFinalizeTimer;

  @override
  void dispose() {
    _dragFinalizeTimer?.cancel();
    _dragDepthNotifier.dispose();
    super.dispose();
  }

  /// Layers saved placement over the compiled catalog and converts each
  /// section to tree preorder. Sibling `order` values may repeat at separate
  /// levels, so a plain global sort would not be sufficient here.
  void _ensureSectionsLoaded(AppLocalizations l10n) {
    if (_sections.isNotEmpty) return;

    final company = context.read<AuthProvider>().company;
    final compiled = navSectionsForSettings(l10n, company);
    final compiledByKey = {
      for (final section in compiled) section.key: section
    };
    final itemOverrides = {
      for (final item in widget.initial.items) item.key: item
    };
    final sectionOrderOverrides = {
      for (final section in widget.initial.sections) section.key: section.order
    };
    final grouped =
        <String, List<({int order, int fallback, _WorkingItem item})>>{};

    var fallback = 0;
    for (final section in compiled) {
      for (var index = 0; index < section.tiles.length; index++) {
        final tile = section.tiles[index];
        final override = itemOverrides[tile.key];
        final targetSection = override?.section != null &&
                compiledByKey.containsKey(override!.section)
            ? override.section!
            : section.key;
        (grouped[targetSection] ??= []).add((
          order: override?.order ?? index,
          fallback: fallback++,
          item: _WorkingItem(
            key: tile.key,
            label: tile.label,
            visible: override?.visible ?? true,
            parentKey: override?.parentId,
          ),
        ));
      }
    }

    final sections = <_WorkingSection>[];
    for (final entry in grouped.entries) {
      entry.value.sort((first, second) {
        final byOrder = first.order.compareTo(second.order);
        return byOrder != 0
            ? byOrder
            : first.fallback.compareTo(second.fallback);
      });
      final section = _WorkingSection(
        key: entry.key,
        label: compiledByKey[entry.key]!.label,
        tiles: [for (final row in entry.value) row.item],
      );
      _repairAndArrange(section);
      sections.add(section);
    }

    final compiledIndex = {
      for (var index = 0; index < compiled.length; index++)
        compiled[index].key: index
    };
    sections.sort((first, second) {
      final firstOrder =
          sectionOrderOverrides[first.key] ?? compiledIndex[first.key] ?? 0;
      final secondOrder =
          sectionOrderOverrides[second.key] ?? compiledIndex[second.key] ?? 0;
      return firstOrder.compareTo(secondOrder);
    });
    _sections = sections;
  }

  /// Repairs dangling/cyclic/deep links, then lays rows out in preorder so a
  /// parent and its descendants always form one contiguous draggable block.
  void _repairAndArrange(_WorkingSection section) {
    final byKey = {for (final tile in section.tiles) tile.key: tile};

    for (final tile in section.tiles) {
      var cursor = tile.parentKey;
      final seen = <String>{tile.key};
      var depth = 0;
      var valid = true;
      while (cursor != null) {
        final parent = byKey[cursor];
        if (parent == null ||
            !seen.add(cursor) ||
            ++depth > _maximumNavigationLevel) {
          valid = false;
          break;
        }
        cursor = parent.parentKey;
      }
      if (!valid) tile.parentKey = null;
    }

    final childrenByParent = <String?, List<_WorkingItem>>{};
    for (final tile in section.tiles) {
      (childrenByParent[tile.parentKey] ??= []).add(tile);
    }

    final arranged = <_WorkingItem>[];
    final visited = <String>{};
    void visit(_WorkingItem tile) {
      if (!visited.add(tile.key)) return;
      arranged.add(tile);
      for (final child
          in childrenByParent[tile.key] ?? const <_WorkingItem>[]) {
        visit(child);
      }
    }

    for (final root in childrenByParent[null] ?? const <_WorkingItem>[]) {
      visit(root);
    }
    for (final tile in section.tiles) {
      if (!visited.contains(tile.key)) {
        tile.parentKey = null;
        visit(tile);
      }
    }
    section.tiles = arranged;
  }

  int _depthOf(_WorkingSection section, String key) {
    final byKey = {for (final tile in section.tiles) tile.key: tile};
    final seen = <String>{};
    var depth = 0;
    var current = byKey[key];

    while (current?.parentKey != null &&
        depth < _maximumNavigationLevel &&
        seen.add(current!.key)) {
      final parent = byKey[current.parentKey];
      if (parent == null) break;
      depth++;
      current = parent;
    }
    return depth;
  }

  int _subtreeEnd(_WorkingSection section, int startIndex) {
    final startDepth = _depthOf(section, section.tiles[startIndex].key);
    var end = startIndex + 1;
    while (end < section.tiles.length &&
        _depthOf(section, section.tiles[end].key) > startDepth) {
      end++;
    }
    return end;
  }

  int _subtreeHeight(_WorkingSection section, int startIndex) {
    final startDepth = _depthOf(section, section.tiles[startIndex].key);
    final end = _subtreeEnd(section, startIndex);
    var height = 0;
    for (var index = startIndex + 1; index < end; index++) {
      height = math.max(
          height, _depthOf(section, section.tiles[index].key) - startDepth);
    }
    return height;
  }

  void _beginItemPointer(
      int sectionIndex, int itemIndex, PointerDownEvent event) {
    final section = _sections[sectionIndex];
    final item = section.tiles[itemIndex];
    final depth = _depthOf(section, item.key);

    _dragSectionIndex = sectionIndex;
    _dragItemKey = item.key;
    _dragStartX = event.position.dx;
    _dragStartDepth = depth;
    _dragPreviewDepth = depth;
    _dragMaximumDepth =
        _maximumNavigationLevel - _subtreeHeight(section, itemIndex);
    _dragReorderApplied = false;
    _dragFinalizeTimer?.cancel();
    _dragDepthNotifier.value = depth;
  }

  void _handleItemPointerMove(String itemKey, PointerMoveEvent event) {
    if (_dragItemKey != itemKey || _dragStartX == null) return;

    final effectiveOffset = math.max(
        0.0,
        _dragStartDepth * navigationIndentStep +
            event.position.dx -
            _dragStartX!);
    final nextDepth =
        math.min(_dragMaximumDepth, navigationIndentForOffset(effectiveOffset));
    if (nextDepth == _dragPreviewDepth) return;

    _dragPreviewDepth = nextDepth;
    _dragDepthNotifier.value = nextDepth;
    setState(() {});
  }

  void _onItemDragStart(int sectionIndex, int itemIndex) {
    final item = _sections[sectionIndex].tiles[itemIndex];
    if (_dragItemKey != item.key) {
      final depth = _depthOf(_sections[sectionIndex], item.key);
      _dragSectionIndex = sectionIndex;
      _dragItemKey = item.key;
      _dragStartDepth = depth;
      _dragPreviewDepth = depth;
      _dragMaximumDepth = _maximumNavigationLevel -
          _subtreeHeight(_sections[sectionIndex], itemIndex);
      _dragReorderApplied = false;
      _dragFinalizeTimer?.cancel();
      _dragDepthNotifier.value = depth;
    }
  }

  int _applyRequestedDepth(
    _WorkingSection section,
    int itemIndex,
    int requestedDepth,
  ) {
    final dragged = section.tiles[itemIndex];
    final maximumDepth =
        _maximumNavigationLevel - _subtreeHeight(section, itemIndex);
    var targetDepth = math.min(requestedDepth, maximumDepth);

    // WordPress's top-item rule: a row with nothing above it has no
    // possible parent, regardless of how far right the pointer travelled.
    if (itemIndex == 0) {
      targetDepth = 0;
    } else {
      targetDepth = math.min(
        targetDepth,
        _depthOf(section, section.tiles[itemIndex - 1].key) + 1,
      );
    }

    String? parentKey;
    if (targetDepth > 0) {
      for (var index = itemIndex - 1; index >= 0; index--) {
        if (_depthOf(section, section.tiles[index].key) == targetDepth - 1) {
          parentKey = section.tiles[index].key;
          break;
        }
      }
    }
    dragged.parentKey = parentKey;

    return parentKey == null ? 0 : targetDepth;
  }

  void _onItemReorder(int sectionIndex, int oldIndex, int newIndex) {
    _dragFinalizeTimer?.cancel();
    setState(() {
      final section = _sections[sectionIndex];
      if (oldIndex < 0 || oldIndex >= section.tiles.length) return;

      final dragged = section.tiles[oldIndex];
      final startDepth = _depthOf(section, dragged.key);
      final endIndex = _subtreeEnd(section, oldIndex);
      final block = section.tiles.sublist(oldIndex, endIndex);
      final descendantCount = block.length - 1;

      // Flutter's onReorderItem already adjusts for the dragged row's
      // removal. Account only for the additional descendant rows that this
      // tree editor carries with their parent.
      var insertionIndex = newIndex;
      if (insertionIndex > oldIndex) insertionIndex -= descendantCount;
      section.tiles.removeRange(oldIndex, endIndex);
      insertionIndex = insertionIndex.clamp(0, section.tiles.length);
      section.tiles.insertAll(insertionIndex, block);

      final requestedDepth =
          _dragItemKey == dragged.key ? _dragPreviewDepth : startDepth;
      _dragPreviewDepth =
          _applyRequestedDepth(section, insertionIndex, requestedDepth);
      _dragDepthNotifier.value = _dragPreviewDepth;
      _repairAndArrange(section);
      _dragReorderApplied = true;
    });

    // onReorderEnd fires before Flutter's drop animation and before this
    // callback. Replace its fallback timer now that a reorder was reported.
    _dragFinalizeTimer = Timer(
      const Duration(milliseconds: 16),
      _finishItemDrag,
    );
  }

  void _scheduleFinishItemDrag() {
    _dragFinalizeTimer?.cancel();

    // Flutter completes the default reorder drop animation in 250 ms. If
    // onReorderItem never arrives, the row stayed at the same vertical
    // index; applying after that animation still preserves a horizontal-only
    // indent/outdent gesture.
    _dragFinalizeTimer = Timer(
      const Duration(milliseconds: 350),
      _finishItemDrag,
    );
  }

  void _finishItemDrag() {
    if (!mounted) return;
    setState(() {
      if (!_dragReorderApplied &&
          _dragSectionIndex != null &&
          _dragItemKey != null) {
        final section = _sections[_dragSectionIndex!];
        final itemIndex =
            section.tiles.indexWhere((tile) => tile.key == _dragItemKey);
        if (itemIndex >= 0) {
          _dragPreviewDepth = _applyRequestedDepth(
            section,
            itemIndex,
            _dragPreviewDepth,
          );
          _repairAndArrange(section);
        }
      }

      _dragSectionIndex = null;
      _dragItemKey = null;
      _dragStartX = null;
      _dragStartDepth = 0;
      _dragPreviewDepth = 0;
      _dragMaximumDepth = _maximumNavigationLevel;
      _dragReorderApplied = false;
      _dragFinalizeTimer = null;
      _dragDepthNotifier.value = 0;
    });
  }

  Future<void> _moveItemToSection(
      _WorkingSection fromSection, _WorkingItem item) async {
    final target = await showModalBottomSheet<_WorkingSection>(
      context: context,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text('Move to section',
                  style: TextStyle(fontWeight: FontWeight.bold)),
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
      final startIndex = fromSection.tiles.indexOf(item);
      final endIndex = _subtreeEnd(fromSection, startIndex);
      final block = fromSection.tiles.sublist(startIndex, endIndex);
      fromSection.tiles.removeRange(startIndex, endIndex);
      item.parentKey = null;
      target.tiles.addAll(block);
      _repairAndArrange(fromSection);
      _repairAndArrange(target);
    });
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final messenger = ScaffoldMessenger.of(context);
    final l10n = AppLocalizations.of(context);

    try {
      final items = <NavItemConfig>[];
      for (final section in _sections) {
        final siblingOrder = <String?, int>{};
        for (final tile in section.tiles) {
          final parent = tile.parentKey;
          final order = siblingOrder[parent] ?? 0;
          siblingOrder[parent] = order + 1;
          items.add(NavItemConfig(
            key: tile.key,
            section: section.key,
            parent: parent,
            level: _depthOf(section, tile.key),
            order: order,
            visible: tile.visible,
          ));
        }
      }

      await widget.repository.updateNavConfig(NavConfig(
        sections: [
          for (var index = 0; index < _sections.length; index++)
            NavSectionOrder(key: _sections[index].key, order: index),
        ],
        items: items,
      ));
      if (!mounted) return;
      messenger.showSnackBar(SnackBar(content: Text(l10n.navMenuSaved)));
    } on ApiException catch (error) {
      messenger.showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Widget _dragProxy(Widget child, int index, Animation<double> animation) {
    return ValueListenableBuilder<int>(
      valueListenable: _dragDepthNotifier,
      child: child,
      builder: (context, depth, row) {
        final horizontalShift =
            (depth - _dragStartDepth) * navigationIndentStep;
        return Transform.translate(
          offset: Offset(horizontalShift, 0),
          child: AnimatedBuilder(
            animation: animation,
            builder: (context, proxyChild) => Material(
              elevation: 3 + animation.value * 5,
              color: Theme.of(context).colorScheme.surface,
              shadowColor:
                  Theme.of(context).colorScheme.primary.withValues(alpha: 0.25),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
                side: BorderSide(
                    color: Theme.of(context).colorScheme.primary, width: 2),
              ),
              child: Stack(
                clipBehavior: Clip.none,
                children: [
                  proxyChild!,
                  Positioned(
                    top: -10,
                    left: 8,
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.primary,
                        borderRadius: BorderRadius.circular(7),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 7, vertical: 3),
                        child: Text(
                          navigationLevelLabel(depth),
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.onPrimary,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            child: row,
          ),
        );
      },
    );
  }

  Widget _buildItemRow(int sectionIndex, int itemIndex) {
    final section = _sections[sectionIndex];
    final tile = section.tiles[itemIndex];
    final actualDepth = _depthOf(section, tile.key);
    final isDragging =
        _dragSectionIndex == sectionIndex && _dragItemKey == tile.key;
    final displayDepth = isDragging ? _dragPreviewDepth : actualDepth;
    final colorScheme = Theme.of(context).colorScheme;

    return AnimatedContainer(
      key: ValueKey(tile.key),
      duration: const Duration(milliseconds: 120),
      curve: Curves.easeOut,
      margin: EdgeInsets.only(
          left: 8 + displayDepth * navigationIndentStep, right: 8, bottom: 2),
      constraints: const BoxConstraints(minHeight: 48),
      decoration: BoxDecoration(
        color: isDragging
            ? colorScheme.primaryContainer.withValues(alpha: 0.45)
            : Colors.transparent,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isDragging
              ? colorScheme.primary
              : colorScheme.outlineVariant.withValues(alpha: 0.35),
          width: isDragging ? 2 : 1,
        ),
      ),
      child: Row(
        children: [
          if (displayDepth > 0)
            Padding(
              padding: const EdgeInsets.only(left: 8),
              child: Icon(Icons.subdirectory_arrow_right,
                  size: 16, color: colorScheme.primary),
            ),
          Checkbox(
            value: tile.visible,
            onChanged: (checked) =>
                setState(() => tile.visible = checked ?? true),
          ),
          Expanded(
            child:
                Text(tile.label, maxLines: 2, overflow: TextOverflow.ellipsis),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: colorScheme.surfaceContainerHighest,
                borderRadius: BorderRadius.circular(6),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 3),
                child: Text(
                  navigationLevelLabel(displayDepth),
                  style:
                      const TextStyle(fontSize: 9, fontWeight: FontWeight.w700),
                ),
              ),
            ),
          ),
          IconButton(
            visualDensity: VisualDensity.compact,
            icon: const Icon(Icons.drive_file_move_outline, size: 19),
            tooltip: 'Move to section',
            onPressed: _sections.length < 2
                ? null
                : () => _moveItemToSection(section, tile),
          ),
          Listener(
            onPointerDown: (event) =>
                _beginItemPointer(sectionIndex, itemIndex, event),
            onPointerMove: (event) => _handleItemPointerMove(tile.key, event),
            child: ReorderableDragStartListener(
              index: itemIndex,
              child: Tooltip(
                message:
                    'Drag vertically to reorder and left/right to change level',
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(4, 12, 10, 12),
                  child: Icon(Icons.drag_handle, color: Colors.grey.shade500),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    _ensureSectionsLoaded(l10n);

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: Text(
            l10n.navMenuDescription,
            style: TextStyle(color: Colors.grey.shade600),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(Icons.drag_handle,
                      size: 18, color: Colors.grey.shade600),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      '${l10n.navMenuSectionOrderHint}. Drag menu rows left/right to snap their level.',
                      style:
                          TextStyle(color: Colors.grey.shade600, fontSize: 12),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              const Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  _IndentLegend(label: 'Main Menu', offset: '0 px'),
                  _IndentLegend(label: 'Sub-Menu', offset: '30 px'),
                  _IndentLegend(label: 'Sub-Sub-Menu', offset: '60 px'),
                ],
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
              for (var sectionIndex = 0;
                  sectionIndex < _sections.length;
                  sectionIndex++)
                Card(
                  key: ValueKey(_sections[sectionIndex].key),
                  margin: const EdgeInsets.symmetric(vertical: 6),
                  clipBehavior: Clip.hardEdge,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 12, 12, 6),
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(
                                _sections[sectionIndex].label,
                                style: const TextStyle(
                                    fontWeight: FontWeight.bold),
                              ),
                            ),
                            ReorderableDragStartListener(
                              index: sectionIndex,
                              child: Padding(
                                padding: const EdgeInsets.all(6),
                                child: Icon(Icons.drag_handle,
                                    color: Colors.grey.shade400),
                              ),
                            ),
                          ],
                        ),
                      ),
                      ReorderableListView(
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        buildDefaultDragHandles: false,
                        proxyDecorator: _dragProxy,
                        onReorderStart: (index) =>
                            _onItemDragStart(sectionIndex, index),
                        onReorderEnd: (_) => _scheduleFinishItemDrag(),
                        onReorderItem: (oldIndex, newIndex) =>
                            _onItemReorder(sectionIndex, oldIndex, newIndex),
                        children: [
                          for (var itemIndex = 0;
                              itemIndex < _sections[sectionIndex].tiles.length;
                              itemIndex++)
                            _buildItemRow(sectionIndex, itemIndex),
                        ],
                      ),
                      const SizedBox(height: 6),
                    ],
                  ),
                ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: ElevatedButton(
            style: ElevatedButton.styleFrom(
                minimumSize: const Size(double.infinity, 50)),
            onPressed: _saving ? null : _save,
            child: _saving
                ? const SizedBox(
                    height: 18,
                    width: 18,
                    child: CircularProgressIndicator(
                        strokeWidth: 2, color: Colors.white),
                  )
                : Text(MaterialLocalizations.of(context).saveButtonLabel),
          ),
        ),
      ],
    );
  }
}

class _IndentLegend extends StatelessWidget {
  const _IndentLegend({required this.label, required this.offset});

  final String label;
  final String offset;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
        child: Text('$label · $offset',
            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700)),
      ),
    );
  }
}
