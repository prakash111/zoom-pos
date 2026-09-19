import 'package:flutter/material.dart';

/// DrawerItemParser provides parser and builder utilities for navigation drawer items.
///
/// Strict rules enforced:
/// 1. Never renders an [ExpansionTile] or dropdown arrow unless `item['children']` is strictly
///    a non-empty [List].
/// 2. If `children` is empty, null, or not a [List], ALWAYS renders a flat [ListTile].
/// 3. If `indent == 0` (or `level == 0`), renders without indentation or '↳'.
/// 4. Indentation and '↳' only render when `indent > 0` or when explicitly placed inside a parent's children.
/// 5. No client-side heuristic auto-nesting is performed; items without parents remain independent flat roots.
class DrawerItemParser {
  const DrawerItemParser._();

  /// Defensive map cloning preventing minified JS cast errors.
  static Map<String, dynamic> safeMap(dynamic value) {
    if (value is! Map) return <String, dynamic>{};
    final result = <String, dynamic>{};
    value.forEach((k, v) {
      result[k.toString()] = v;
    });
    return result;
  }

  /// Returns true if the menu item payload contains a non-empty children list.
  static bool hasChildren(Map<String, dynamic> item) {
    final children = item['children'];
    if (children is! Iterable) return false;
    return List.from(children).whereType<Map>().isNotEmpty;
  }

  /// Extracts the item's indent or level integer (clamped to >= 0).
  static int extractIndent(Map<String, dynamic> item) {
    final indent = item['indent'] ?? item['level'];
    if (indent is int) return indent < 0 ? 0 : indent;
    if (indent is num) return indent.toInt() < 0 ? 0 : indent.toInt();
    if (indent is String) return int.tryParse(indent) ?? 0;
    return 0;
  }

  /// Builds a drawer menu item according to the strict parser rules.
  static Widget buildDrawerMenuItem(
    BuildContext context,
    Map<String, dynamic> item, {
    required Color activeColor,
    Color? selectedColor,
    VoidCallback? onTap,
    void Function(Map<String, dynamic> child)? onChildTap,
    bool isSelected = false,
  }) {
    final int indent = extractIndent(item);
    final bool isNested = indent > 0;
    final dynamic rawChildren = item['children'];
    final List<Map<String, dynamic>> validChildren = (rawChildren is Iterable)
        ? List.from(rawChildren)
            .whereType<Map>()
            .map((e) => safeMap(e))
            .where((m) => m.isNotEmpty)
            .toList()
        : const <Map<String, dynamic>>[];
    final bool hasValidChildren = validChildren.isNotEmpty;

    final String title = item['title']?.toString() ??
        item['custom_title']?.toString() ??
        item['label']?.toString() ??
        '';
    final dynamic icon = item['icon'];
    final IconData? iconData = icon is IconData
        ? icon
        : (item['icon_data'] is IconData
            ? item['icon_data'] as IconData
            : null);

    // Flat ListTile: empty children, null children, or non-list children ALWAYS render flat.
    // Never render an ExpansionTile or dropdown arrow for flat items.
    if (!hasValidChildren) {
      final padding = EdgeInsets.only(
        left: 16.0 + (indent * 24.0),
        right: 16.0,
      );

      final effectiveColor = isSelected
          ? (selectedColor ?? activeColor)
          : (isNested ? activeColor.withValues(alpha: 0.85) : activeColor);

      final IconData effectiveIcon = iconData ??
          (icon is IconData
              ? icon
              : (isNested
                  ? Icons.subdirectory_arrow_right
                  : Icons.circle_outlined));

      return ListTile(
        key: ValueKey('drawer-item-${item['key'] ?? item['id'] ?? title}'),
        contentPadding: padding,
        dense: isNested,
        leading: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Indentation indicator '↳' ONLY renders when indent > 0
            if (isNested) ...[
              Text(
                '↳',
                style: TextStyle(
                  color: effectiveColor,
                  fontSize: 13,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(width: 6),
            ],
            Icon(
              effectiveIcon,
              size: isNested ? 18 : 22,
              color: effectiveColor,
            ),
          ],
        ),
        title: Text(
          title,
          style: TextStyle(
            color: effectiveColor,
            fontSize: isNested ? 13 : 14,
            fontWeight: isSelected
                ? FontWeight.w700
                : (isNested ? FontWeight.w500 : FontWeight.w600),
          ),
        ),
        selected: isSelected,
        selectedColor: selectedColor ?? activeColor,
        onTap: onTap,
      );
    }

    // Expandable ExpansionTile ONLY when children is strictly a non-empty List
    return ExpansionTile(
      key: PageStorageKey(
          'drawer-expandable-${item['key'] ?? item['id'] ?? title}'),
      initiallyExpanded:
          item['initially_expanded'] == true || item['expanded'] == true,
      tilePadding: EdgeInsets.only(
        left: 16.0 + (indent * 24.0),
        right: 16.0,
      ),
      leading: Icon(
        iconData ?? (icon is IconData ? icon : Icons.circle_outlined),
        size: 22,
        color: isSelected ? (selectedColor ?? activeColor) : activeColor,
      ),
      title: Text(
        title,
        style: TextStyle(
          color: isSelected ? (selectedColor ?? activeColor) : activeColor,
          fontSize: 14,
          fontWeight: FontWeight.w600,
        ),
      ),
      children: validChildren.map((childMap) {
        if (!childMap.containsKey('indent') && !childMap.containsKey('level')) {
          childMap['indent'] = indent + 1;
        }
        return buildDrawerMenuItem(
          context,
          childMap,
          activeColor: activeColor,
          selectedColor: selectedColor,
          onTap: () => onChildTap?.call(childMap),
          onChildTap: onChildTap,
        );
      }).toList(),
    );
  }
}

/// Convenience function for rendering drawer menu items with strict 1:1 parity.
Widget buildDrawerMenuItem(
  BuildContext context,
  Map<String, dynamic> item, {
  required Color activeColor,
  Color? selectedColor,
  VoidCallback? onTap,
  void Function(Map<String, dynamic> child)? onChildTap,
  bool isSelected = false,
}) {
  return DrawerItemParser.buildDrawerMenuItem(
    context,
    item,
    activeColor: activeColor,
    selectedColor: selectedColor,
    onTap: onTap,
    onChildTap: onChildTap,
    isSelected: isSelected,
  );
}
