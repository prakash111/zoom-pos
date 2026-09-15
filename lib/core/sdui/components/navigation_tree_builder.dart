import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../config/bootstrap_cache.dart';
import '../../config/locale_provider.dart';
import '../../models/settings_models.dart';
import '../../services/dynamic_string_service.dart';
import '../../../features/settings/settings_repository.dart';
import '../models/sdui_models.dart';

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

class NavTileDescriptor {
  const NavTileDescriptor(this.key, this.label);
  final String key;
  final String label;
}

class NavSectionDescriptor {
  const NavSectionDescriptor(
    this.key,
    this.label,
    this.tiles, {
    this.customTitle,
  });
  final String key;
  final String label;
  final List<NavTileDescriptor> tiles;
  final String? customTitle;
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
      {required this.key,
      required this.label,
      required this.customTitle,
      required this.tiles});

  final String key;
  final String label;
  String customTitle;
  List<_WorkingItem> tiles;
}

/// One row in the editor's single reorder surface. Section headers are real
/// boundaries in the same list as menu items, so crossing a header changes an
/// item's assigned section instead of handing the drag to another list.
class _UnifiedNavEntry {
  const _UnifiedNavEntry.section(this.section) : item = null;

  const _UnifiedNavEntry.item(this.section, this.item);

  final _WorkingSection section;
  final _WorkingItem? item;

  bool get isSectionHeader => item == null;
}

/// Store-wide WordPress-style navigation outline editor. Vertical dragging
/// changes preorder; horizontal dragging snaps the active row to 0/30/60 px
/// and derives its parent from the nearest valid preceding row.
class NavMenuSettingsTab extends StatefulWidget {
  const NavMenuSettingsTab(
      {super.key, this.repository, this.initial, this.schema});

  final SettingsRepository? repository;
  final NavConfig? initial;
  final Map<String, dynamic>? schema;

  @override
  State<NavMenuSettingsTab> createState() => _NavMenuSettingsTabState();
}

class _NavMenuSettingsTabState extends State<NavMenuSettingsTab> {
  final ValueNotifier<int> _dragDepthNotifier = ValueNotifier<int>(0);

  SettingsRepository get _repository =>
      widget.repository ?? SettingsRepository(context.read<ApiClient>());
  NavConfig get _initialConfig {
    if (widget.initial != null) return widget.initial!;
    if (widget.schema?['nav_config'] is Map) {
      try {
        return NavConfig.fromJson(
            Map<String, dynamic>.from(widget.schema!['nav_config'] as Map));
      } catch (_) {}
    }
    return BootstrapCache.instance.navConfig;
  }

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
  void _ensureSectionsLoaded() {
    if (_sections.isNotEmpty) return;

    // An SDUI navigation screen is self-contained. Prefer its active
    // hierarchy over the bootstrap cache so a newly changed mode/menu does
    // not briefly show stale rows, and so this component remains renderable
    // when no AuthProvider is above it (for example in schema previews).
    var compiled = _sectionsFromSchema();
    if (compiled.isEmpty) {
      compiled =
          _parseSectionCollection(BootstrapCache.instance.effectiveSections);
    }

    if (compiled.isEmpty) {
      compiled = const [
        NavSectionDescriptor('cashier_sales', 'Cashier & Sales', [
          NavTileDescriptor('pos', 'Point of Sale'),
          NavTileDescriptor('sales', 'Sales & Invoices'),
          NavTileDescriptor('quotations', 'Quotations & Proposals'),
          NavTileDescriptor('consignments', 'Consignments'),
          NavTileDescriptor('customers', 'Customers & CRM'),
        ]),
        NavSectionDescriptor('financial_mgmt', 'Financial Management', [
          NavTileDescriptor('cash_register', 'Cash Register'),
          NavTileDescriptor('receivables', 'Customer Ledger'),
          NavTileDescriptor('reports', 'Analytics & Reports'),
        ]),
        NavSectionDescriptor('products_inventory', 'Products & Inventory', [
          NavTileDescriptor('products', 'Products'),
          NavTileDescriptor('categories', 'Categories'),
        ]),
        NavSectionDescriptor('settings', 'Administration & Settings', [
          NavTileDescriptor('settings', 'Settings'),
        ]),
      ];
    }

    final compiledByKey = {
      for (final section in compiled) section.key: section
    };

    final rawItemsFromSchema = widget.schema?['items'] is List
        ? (widget.schema!['items'] as List)
            .whereType<Map>()
            .map((it) => NavItemConfig.fromJson(Map<String, dynamic>.from(it)))
            .toList()
        : const <NavItemConfig>[];

    final effectiveItems = _initialConfig.items.isNotEmpty
        ? _initialConfig.items
        : rawItemsFromSchema;

    final itemOverrides = {for (final item in effectiveItems) item.key: item};
    final sectionOrderOverrides = {
      for (final section in _initialConfig.sections) section.key: section.order
    };
    final sectionOverrides = {
      for (final section in _initialConfig.sections) section.key: section
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
        final effectiveParent = tile.key == 'settings'
            ? null
            : (override != null
                ? (override.level == 0 ? null : override.parentId)
                : null);
        (grouped[targetSection] ??= []).add((
          order: override?.order ?? index,
          fallback: fallback++,
          item: _WorkingItem(
            key: tile.key,
            label: tile.label,
            visible: override?.visible ?? true,
            parentKey: effectiveParent,
          ),
        ));
      }
    }

    final sections = <_WorkingSection>[];
    for (final meta in compiled) {
      final rows = grouped[meta.key] ?? [];
      rows.sort((first, second) {
        final byOrder = first.order.compareTo(second.order);
        return byOrder != 0
            ? byOrder
            : first.fallback.compareTo(second.fallback);
      });
      final section = _WorkingSection(
        key: meta.key,
        label: meta.label,
        customTitle:
            sectionOverrides[meta.key]?.customTitle?.trim().isNotEmpty == true
                ? sectionOverrides[meta.key]!.customTitle!.trim()
                : (meta.customTitle?.trim().isNotEmpty == true
                    ? meta.customTitle!.trim()
                    : (rows.isNotEmpty ? rows.first.item.label : meta.label)),
        tiles: [for (final row in rows) row.item],
      );
      _repairAndArrange(section);
      sections.add(section);
    }

    if (sections.isEmpty) {
      for (final s in compiled) {
        final sec = _WorkingSection(
          key: s.key,
          label: s.label,
          customTitle: s.customTitle?.trim().isNotEmpty == true
              ? s.customTitle!.trim()
              : (s.tiles.isNotEmpty ? s.tiles.first.label : s.label),
          tiles: [
            for (final t in s.tiles)
              _WorkingItem(key: t.key, label: t.label, visible: true)
          ],
        );
        _repairAndArrange(sec);
        sections.add(sec);
      }
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

  List<NavSectionDescriptor> _sectionsFromSchema() {
    final schema = widget.schema;
    if (schema == null) return const [];

    // Current contract first, followed by aliases emitted for old clients.
    for (final source in [
      schema['tree_data'],
      schema['sections'],
      schema['menu_structure'],
      schema['items'],
    ]) {
      final parsed = _parseSectionCollection(source);
      if (parsed.isNotEmpty) return parsed;
    }
    return const [];
  }

  /// Converts section maps or typed bootstrap schemas to flat descriptors
  /// while recursively retaining all children. Missing/null/non-list child
  /// values are treated as empty collections rather than aborting the tree.
  List<NavSectionDescriptor> _parseSectionCollection(dynamic source) {
    if (source is! List) return const [];

    final parsed = <NavSectionDescriptor>[];
    for (final rawSection in source) {
      if (rawSection is SduiNavSectionSchema) {
        final tiles = <NavTileDescriptor>[];
        final seen = <String>{};

        void collectTyped(Iterable<SduiNavItemSchema> items) {
          for (final item in items) {
            if (item.key.isNotEmpty && seen.add(item.key)) {
              tiles.add(NavTileDescriptor(item.key, item.title));
            }
            collectTyped(item.children);
          }
        }

        collectTyped(rawSection.items);
        if (rawSection.key.isNotEmpty) {
          parsed.add(NavSectionDescriptor(
              rawSection.key, rawSection.title, tiles,
              customTitle: rawSection.customTitle));
        }
        continue;
      }
      if (rawSection is! Map) continue;

      final section = Map<String, dynamic>.from(rawSection);
      final key = (section['key'] ?? section['id'])?.toString() ?? '';
      if (key.isEmpty) continue;
      final title = (section['title'] ?? section['label'])?.toString() ?? key;
      final customTitle = section['custom_title']?.toString();
      final tiles = <NavTileDescriptor>[];
      final seen = <String>{};

      void collectMaps(dynamic rawItems) {
        if (rawItems is! List) return;
        for (final rawItem in rawItems) {
          if (rawItem is! Map) continue;
          final item = Map<String, dynamic>.from(rawItem);
          final itemKey = (item['key'] ?? item['id'])?.toString() ?? '';
          if (itemKey.isNotEmpty && seen.add(itemKey)) {
            final itemTitle =
                (item['title'] ?? item['label'])?.toString() ?? itemKey;
            tiles.add(NavTileDescriptor(itemKey, itemTitle));
          }
          collectMaps(item['children'] ?? item['items']);
        }
      }

      collectMaps(section['items'] ?? section['children']);
      parsed.add(
          NavSectionDescriptor(key, title, tiles, customTitle: customTitle));
    }

    return parsed;
  }

  /// Repairs dangling/cyclic/deep links, then lays rows out in preorder so a
  /// parent and its descendants always form one contiguous draggable block.
  void _repairAndArrange(_WorkingSection section) {
    final byKey = {for (final tile in section.tiles) tile.key: tile};

    for (final tile in section.tiles) {
      if (tile.key == 'settings') {
        tile.parentKey = null;
      }
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

  List<_UnifiedNavEntry> _unifiedEntries() {
    return [
      for (final section in _sections) ...[
        _UnifiedNavEntry.section(section),
        for (final item in section.tiles) _UnifiedNavEntry.item(section, item),
      ],
    ];
  }

  /// Rebuilds section membership from the position of the nearest preceding
  /// header. This is the single source of truth after a drag: there are no
  /// independent child reorder lists whose callbacks can snap a row back.
  void _reassignSections(List<_UnifiedNavEntry> entries) {
    final orderedSections = entries
        .where((entry) => entry.isSectionHeader)
        .map((entry) => entry.section)
        .toList();
    if (orderedSections.isEmpty) return;

    for (final section in orderedSections) {
      section.tiles = [];
    }

    // A section header is always rendered first after rebuilding. If the
    // first header itself was dragged below one of its rows, those leading
    // rows remain assigned to that first available section.
    var currentSection = orderedSections.first;
    for (final entry in entries) {
      if (entry.isSectionHeader) {
        currentSection = entry.section;
      } else {
        currentSection.tiles.add(entry.item!);
      }
    }

    _sections = orderedSections;
    for (final section in _sections) {
      _repairAndArrange(section);
    }
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

  void _onUnifiedDragStart(int index) {
    final entries = _unifiedEntries();
    if (index < 0 || index >= entries.length) return;
    final entry = entries[index];
    if (entry.isSectionHeader) {
      _dragSectionIndex = _sections.indexOf(entry.section);
      _dragItemKey = null;
      _dragStartX = null;
      _dragStartDepth = 0;
      _dragPreviewDepth = 0;
      _dragMaximumDepth = _maximumNavigationLevel;
      _dragReorderApplied = false;
      _dragFinalizeTimer?.cancel();
      _dragDepthNotifier.value = 0;
      return;
    }

    final sectionIndex = _sections.indexOf(entry.section);
    final itemIndex = entry.section.tiles.indexOf(entry.item!);
    if (sectionIndex >= 0 && itemIndex >= 0) {
      _onItemDragStart(sectionIndex, itemIndex);
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

    // Store Settings is strictly a top-level Main Menu root item (level 0).
    if (dragged.key == 'settings') {
      targetDepth = 0;
    } else if (itemIndex == 0) {
      // WordPress's top-item rule: a row with nothing above it has no
      // possible parent, regardless of how far right the pointer travelled.
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

  void _onUnifiedReorder(int oldIndex, int newIndex) {
    _dragFinalizeTimer?.cancel();
    setState(() {
      final entries = _unifiedEntries();
      if (oldIndex < 0 || oldIndex >= entries.length) return;

      final draggedEntry = entries[oldIndex];
      if (draggedEntry.isSectionHeader) {
        final header = entries.removeAt(oldIndex);
        entries.insert(newIndex.clamp(0, entries.length), header);
        _reassignSections(entries);
        _dragReorderApplied = true;
        return;
      }

      final sourceSection = draggedEntry.section;
      final sourceItemIndex = sourceSection.tiles.indexOf(draggedEntry.item!);
      if (sourceItemIndex < 0) return;

      final dragged = draggedEntry.item!;
      final startDepth = _depthOf(sourceSection, dragged.key);
      final subtreeEnd = _subtreeEnd(sourceSection, sourceItemIndex);
      final blockLength = subtreeEnd - sourceItemIndex;
      final block = entries.sublist(oldIndex, oldIndex + blockLength);

      // onReorderItem reports an index after removing only the dragged row.
      // We also carry its descendants, so compensate for those extra rows
      // when the subtree moves down.
      var insertionIndex = newIndex;
      if (insertionIndex > oldIndex) insertionIndex -= blockLength - 1;
      entries.removeRange(oldIndex, oldIndex + blockLength);
      insertionIndex = insertionIndex.clamp(0, entries.length);
      entries.insertAll(insertionIndex, block);
      _reassignSections(entries);

      final targetSection = _sections.firstWhere(
        (section) => section.tiles.any((item) => item.key == dragged.key),
      );
      final targetItemIndex =
          targetSection.tiles.indexWhere((item) => item.key == dragged.key);

      if (targetSection.key != sourceSection.key) {
        // Crossing a header always makes the moved subtree's first row a new
        // Main Menu item. Descendants keep their internal relationships.
        dragged.parentKey = null;
        _dragPreviewDepth = 0;
        _repairAndArrange(targetSection);
      } else {
        final requestedDepth =
            _dragItemKey == dragged.key ? _dragPreviewDepth : startDepth;
        _dragPreviewDepth = _applyRequestedDepth(
          targetSection,
          targetItemIndex,
          requestedDepth,
        );
        _repairAndArrange(targetSection);
      }
      _dragDepthNotifier.value = _dragPreviewDepth;
      _dragReorderApplied = true;
    });

    // onReorderEnd fires before Flutter's drop animation and before this
    // callback. Replace its fallback timer now that a reorder was reported.
    _dragFinalizeTimer = Timer(
      const Duration(milliseconds: 16),
      _finishItemDrag,
    );
  }

  void _changeItemDepth(
      _WorkingSection section, _WorkingItem item, int requestedDepth) {
    setState(() {
      final itemIndex = section.tiles.indexOf(item);
      if (itemIndex < 0) return;
      if (requestedDepth == 0 || item.key == 'settings') {
        item.parentKey = null;
      }
      _applyRequestedDepth(
        section,
        itemIndex,
        item.key == 'settings' ? 0 : requestedDepth.clamp(0, _maximumNavigationLevel),
      );
      if (requestedDepth == 0 || item.key == 'settings') {
        item.parentKey = null;
      }
      _repairAndArrange(section);
    });
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
    final target = await showDialog<_WorkingSection>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: const Color(0xFF1E293B),
        title: Text(
          'Move "${item.label}" to section',
          style: const TextStyle(color: Colors.white, fontSize: 16),
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              for (final section in _sections)
                if (section.key != fromSection.key)
                  ListTile(
                    key: ValueKey('move-target-${section.key}'),
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      section.customTitle.trim().isNotEmpty
                          ? section.customTitle.trim()
                          : section.label,
                      style: const TextStyle(color: Colors.white),
                    ),
                    subtitle: section.customTitle.trim().isNotEmpty &&
                            section.customTitle.trim() != section.label
                        ? Text(
                            section.label,
                            style: const TextStyle(color: Colors.white70, fontSize: 12),
                          )
                        : null,
                    trailing: const Icon(Icons.arrow_forward_ios, size: 14, color: Colors.white54),
                    onTap: () => Navigator.of(dialogContext).pop(section),
                  ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: Text(
              MaterialLocalizations.of(context).cancelButtonLabel,
              style: const TextStyle(color: Colors.white70),
            ),
          ),
        ],
      ),
    );
    if (target == null) return;

    _moveItemBetweenSections(
        _sections.indexOf(fromSection), item.key, _sections.indexOf(target));
  }

  /// Detaches the item (and its contiguous subtree block) from one section and
  /// appends it to another as a top-level row.
  void _moveItemBetweenSections(int fromIndex, String itemKey, int toIndex) {
    if (fromIndex == toIndex) return;
    if (fromIndex < 0 || fromIndex >= _sections.length) return;
    if (toIndex < 0 || toIndex >= _sections.length) return;

    setState(() {
      final from = _sections[fromIndex];
      final to = _sections[toIndex];
      final startIndex = from.tiles.indexWhere((tile) => tile.key == itemKey);
      if (startIndex < 0) return;

      final endIndex = _subtreeEnd(from, startIndex);
      final block = from.tiles.sublist(startIndex, endIndex);
      from.tiles.removeRange(startIndex, endIndex);
      block.first.parentKey = null;
      to.tiles.addAll(block);
      _repairAndArrange(from);
      _repairAndArrange(to);
    });
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final messenger = ScaffoldMessenger.of(context);

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

      // `updateNavConfig` already writes the server's canonical copy back into
      // BootstrapCache (applyNav -> notifyListeners), so the drawer / rail /
      // top-bar rebuild immediately. Follow it with a full bootstrap refresh
      // so a reload or a second device also converges without an app restart.
      await _repository.updateNavConfig(NavConfig(
        sections: [
          for (var index = 0; index < _sections.length; index++)
            NavSectionOrder(
              key: _sections[index].key,
              order: index,
              customTitle: _sections[index].customTitle.trim().isNotEmpty
                  ? _sections[index].customTitle.trim()
                  : (_sections[index].tiles.isNotEmpty
                      ? _sections[index].tiles.first.label
                      : _sections[index].label),
            ),
        ],
        items: items,
      ));
      if (!mounted) return;
      // Wait for the bootstrap response that contains the newly decorated
      // menu_structure. Applying the flat nav config alone does not replace
      // the drawer's current section tree, so returning early could show the
      // old layout until a later restart/locale refresh.
      await context.read<LocaleProvider>().refreshFromServer();
      if (!mounted) return;
      messenger.showSnackBar(
          SnackBar(content: Text(context.tr('Navigation menu updated.'))));
    } on ApiException catch (error) {
      messenger.showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Widget _dragProxy(Widget child, int index, Animation<double> animation) {
    final entries = _unifiedEntries();
    final isSectionHeader =
        index >= 0 && index < entries.length && entries[index].isSectionHeader;
    if (isSectionHeader) {
      return AnimatedBuilder(
        animation: animation,
        child: child,
        builder: (context, proxyChild) => Material(
          elevation: 3 + animation.value * 5,
          color: Colors.transparent,
          borderRadius: BorderRadius.circular(12),
          child: proxyChild,
        ),
      );
    }

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

  Widget _buildItemRow(int sectionIndex, int itemIndex, int unifiedIndex) {
    final section = _sections[sectionIndex];
    final tile = section.tiles[itemIndex];
    final actualDepth = _depthOf(section, tile.key);
    final isDragging =
        _dragSectionIndex == sectionIndex && _dragItemKey == tile.key;
    final displayDepth = isDragging ? _dragPreviewDepth : actualDepth;
    final colorScheme = Theme.of(context).colorScheme;

    return AnimatedContainer(
      key: ValueKey('nav-item-${tile.key}'),
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
          PopupMenuButton<int>(
            key: ValueKey('nav-level-${tile.key}'),
            tooltip: 'Change menu level',
            initialValue: actualDepth,
            icon: const Icon(Icons.format_indent_increase, size: 18),
            onSelected: (level) => _changeItemDepth(section, tile, level),
            itemBuilder: (context) {
              final maximumDepth = itemIndex == 0
                  ? 0
                  : math.min(
                      _maximumNavigationLevel -
                          _subtreeHeight(section, itemIndex),
                      _depthOf(section, section.tiles[itemIndex - 1].key) + 1,
                    );
              return [
                for (var level = 0; level <= maximumDepth; level++)
                  PopupMenuItem<int>(
                    key: ValueKey('nav-level-option-${tile.key}-$level'),
                    value: level,
                    child: Text(navigationLevelLabel(level)),
                  ),
              ];
            },
          ),
          IconButton(
            key: ValueKey('nav-move-${tile.key}'),
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
              key: ValueKey('nav-drag-${tile.key}'),
              index: unifiedIndex,
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

  Widget _buildSectionHeader(int sectionIndex, int unifiedIndex) {
    final section = _sections[sectionIndex];
    final scheme = Theme.of(context).colorScheme;

    return Card(
      key: ValueKey('nav-section-${section.key}'),
      margin: const EdgeInsets.fromLTRB(0, 10, 0, 4),
      color: scheme.surfaceContainerHighest.withValues(alpha: 0.55),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(
          color: scheme.outlineVariant.withValues(alpha: 0.55),
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 8, 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    section.label,
                    style: TextStyle(
                      color: scheme.onSurfaceVariant,
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  TextFormField(
                    key: ValueKey('section-title-${section.key}'),
                    initialValue: section.customTitle,
                    maxLength: 120,
                    decoration: InputDecoration(
                      labelText: 'Custom section title',
                      hintText: section.tiles.isNotEmpty
                          ? section.tiles.first.label
                          : section.label,
                      helperText: section.tiles.isEmpty
                          ? 'Empty section — drop an item below this header'
                          : null,
                      counterText: '',
                      isDense: true,
                    ),
                    onChanged: (value) => section.customTitle = value,
                  ),
                ],
              ),
            ),
            ReorderableDragStartListener(
              key: ValueKey('nav-section-drag-${section.key}'),
              index: unifiedIndex,
              child: Tooltip(
                message: 'Drag section boundary',
                child: Padding(
                  padding: const EdgeInsets.all(10),
                  child: Icon(Icons.drag_handle, color: Colors.grey.shade400),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    _ensureSectionsLoaded();
    final embeddedInSduiScrollView = widget.schema != null;
    final entries = _unifiedEntries();

    final sectionList = ReorderableListView(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
      shrinkWrap: embeddedInSduiScrollView,
      physics: embeddedInSduiScrollView
          ? const NeverScrollableScrollPhysics()
          : null,
      buildDefaultDragHandles: false,
      proxyDecorator: _dragProxy,
      onReorderStart: _onUnifiedDragStart,
      onReorderEnd: (_) => _scheduleFinishItemDrag(),
      onReorderItem: _onUnifiedReorder,
      children: [
        for (var unifiedIndex = 0;
            unifiedIndex < entries.length;
            unifiedIndex++)
          if (entries[unifiedIndex].isSectionHeader)
            _buildSectionHeader(
              _sections.indexOf(entries[unifiedIndex].section),
              unifiedIndex,
            )
          else
            _buildItemRow(
              _sections.indexOf(entries[unifiedIndex].section),
              entries[unifiedIndex]
                  .section
                  .tiles
                  .indexOf(entries[unifiedIndex].item!),
              unifiedIndex,
            ),
      ],
    );

    final content = Column(
      mainAxisSize:
          embeddedInSduiScrollView ? MainAxisSize.min : MainAxisSize.max,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (!embeddedInSduiScrollView)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            child: Wrap(
              spacing: 6,
              runSpacing: 6,
              children: const [
                _IndentLegend(label: 'Main Menu', offset: '0 px'),
                _IndentLegend(label: 'Sub-Menu', offset: '30 px'),
                _IndentLegend(label: 'Sub-Sub-Menu', offset: '60 px'),
              ],
            ),
          ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 6),
          child: Text(
            'Tip: drag any item across a section header, or use its move button for a quick transfer.',
            style: TextStyle(
              fontSize: 11,
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
          ),
        ),
        if (embeddedInSduiScrollView)
          sectionList
        else
          Expanded(child: sectionList),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
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

    final hasScaffold = Scaffold.maybeOf(context) != null;
    if (!hasScaffold) {
      return Scaffold(
        appBar: AppBar(
          title: Text(context.tr(
            widget.schema?['title']?.toString() ?? 'Navigation Menu',
          )),
        ),
        body: content,
      );
    }

    return content;
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
