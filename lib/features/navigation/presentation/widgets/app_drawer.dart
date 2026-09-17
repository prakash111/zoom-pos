import 'package:flutter/material.dart';

class RawMenuItem {
  const RawMenuItem({
    required this.id,
    required this.title,
    this.customTitle,
    this.icon,
    this.route,
    this.level = 0,
    this.indent = 0,
    this.parentId,
  });

  final String id;
  final String title;
  final String? customTitle;
  final dynamic icon;
  final String? route;
  final int level;
  final int indent;
  final String? parentId;
}

class NavGroupItem {
  NavGroupItem({
    required this.id,
    required this.title,
    this.icon,
    this.route,
    List<NavGroupItem>? children,
  }) : children = children ?? [];

  final String id;
  final String title;
  final dynamic icon;
  final String? route;
  final List<NavGroupItem> children;
}

class NavMenuItem {
  const NavMenuItem({
    required this.id,
    required this.title,
    this.customTitle,
    this.iconData,
    this.icon,
    this.route,
    this.color,
    this.children = const [],
  });

  final String id;
  final String title;
  final String? customTitle;
  final IconData? iconData;
  final dynamic icon;
  final String? route;
  final dynamic color;
  final List<NavMenuItem> children;

  String get displayTitle =>
      customTitle?.isNotEmpty == true ? customTitle! : title;

  IconData get effectiveIcon =>
      iconData ?? (icon is IconData ? icon as IconData : Icons.circle_outlined);
}

/// Builds hierarchical drawer items from a flat list stored in local state or API JSON.
/// Breaks out of the active parent whenever an item has level == 0 (or indent == 0 / parentId == null).
List<NavGroupItem> buildDrawerHierarchy(List<RawMenuItem> flatList) {
  final List<NavGroupItem> rootItems = [];
  NavGroupItem? activeParent;

  for (final item in flatList) {
    // Consignments is always a root sibling of Quotations, including when a
    // stale cached menu still carries a parent id or indentation.
    final bool isConsignments = item.id == 'consignments';
    final bool hasParent = !isConsignments &&
        item.parentId != null &&
        item.parentId!.isNotEmpty;
    final bool isSubMenu = !isConsignments &&
        (item.level > 0 || item.indent > 0) &&
        hasParent;
    final bool isMainMenu =
        !isSubMenu && (item.level == 0 || item.indent == 0 || !hasParent);

    if (isMainMenu) {
      // Create new root node; stops trapping items inside previous parent
      activeParent = NavGroupItem(
        id: item.id,
        title: item.customTitle?.isNotEmpty == true
            ? item.customTitle!
            : item.title,
        icon: item.icon,
        route: item.route,
        children: [],
      );
      rootItems.add(activeParent);
    } else {
      // Sub-menu item
      final subItem = NavGroupItem(
        id: item.id,
        title: item.customTitle?.isNotEmpty == true
            ? item.customTitle!
            : item.title,
        icon: item.icon,
        route: item.route,
        children: [],
      );

      if (activeParent != null) {
        activeParent.children.add(subItem);
      } else {
        rootItems.add(subItem);
      }
    }
  }

  return rootItems;
}

/// If SDUI items contain an explicit color in JSON, ignore hardcoded generic orange defaults:
Color resolveItemColor(dynamic itemColorPayload, Color dynamicPrefColor) {
  if (itemColorPayload == null) return dynamicPrefColor;

  if (itemColorPayload is Color) {
    final hex = itemColorPayload.toARGB32().toRadixString(16).toLowerCase();
    if (hex.endsWith('f97316') || hex.endsWith('ea580c')) {
      return dynamicPrefColor;
    }
    return itemColorPayload;
  }

  if (itemColorPayload is String && itemColorPayload.trim().isNotEmpty) {
    final clean = itemColorPayload.trim().toLowerCase();
    // If backend sent generic default orange "#F97316" or "#EA580C", override with user preference
    if (clean == '#f97316' ||
        clean == '#ea580c' ||
        clean == 'f97316' ||
        clean == 'ea580c') {
      return dynamicPrefColor;
    }
    try {
      final hex = clean.replaceFirst('#', '');
      return Color(int.parse(hex.length == 6 ? 'ff$hex' : hex, radix: 16));
    } catch (_) {
      return dynamicPrefColor;
    }
  }

  return dynamicPrefColor;
}

/// Builds a primary drawer menu tile inheriting the active preference palette for both icon & text.
Widget buildDrawerItemTile(
  BuildContext context, {
  required String title,
  IconData? iconData,
  dynamic icon,
  required Color activeColor,
  VoidCallback? onTap,
  bool isSelected = false,
  Color? selectedColor,
  EdgeInsetsGeometry? contentPadding,
}) {
  final IconData effectiveIcon =
      iconData ?? (icon is IconData ? icon as IconData : Icons.circle_outlined);
  final Color effectiveColor =
      isSelected ? (selectedColor ?? activeColor) : activeColor;

  return ListTile(
    contentPadding: contentPadding,
    leading: Icon(
      effectiveIcon,
      size: 22,
      color: effectiveColor, // Inherits selected palette (purple, green, dark slate, etc.)
    ),
    title: Text(
      title,
      style: TextStyle(
        color: effectiveColor, // Inherits selected palette
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
    ),
    selected: isSelected,
    selectedColor: selectedColor ?? activeColor,
    onTap: onTap,
  );
}

/// Builds a sub-menu drawer tile with indented structure and softened variant of the selected color.
Widget buildSubMenuItemTile(
  BuildContext context, {
  required String title,
  IconData? iconData,
  dynamic icon,
  required Color activeColor,
  VoidCallback? onTap,
  bool isSelected = false,
  Color? selectedColor,
  EdgeInsetsGeometry? contentPadding,
}) {
  final IconData effectiveIcon = iconData ??
      (icon is IconData ? icon as IconData : Icons.subdirectory_arrow_right);
  final Color effectiveColor = isSelected
      ? (selectedColor ?? activeColor)
      : activeColor.withValues(alpha: 0.85);

  return ListTile(
    dense: true,
    contentPadding:
        contentPadding ?? const EdgeInsets.only(left: 32.0, right: 16.0),
    leading: Icon(
      effectiveIcon,
      size: 16,
      color: effectiveColor, // Softened variant of the same selected color
    ),
    title: Text(
      title,
      style: TextStyle(
        color: isSelected ? (selectedColor ?? activeColor) : activeColor,
        fontSize: 13,
      ),
    ),
    selected: isSelected,
    selectedColor: selectedColor ?? activeColor,
    onTap: onTap,
  );
}
