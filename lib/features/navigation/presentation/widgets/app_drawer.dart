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

/// Builds hierarchical drawer items from a flat list stored in local state or API JSON.
/// Breaks out of the active parent whenever an item has level == 0 (or indent == 0 / parentId == null).
List<NavGroupItem> buildDrawerHierarchy(List<RawMenuItem> flatList) {
  final List<NavGroupItem> rootItems = [];
  NavGroupItem? activeParent;

  for (final item in flatList) {
    final bool hasParent = item.parentId != null && item.parentId!.isNotEmpty;
    final bool isSubMenu = (item.level > 0 || item.indent > 0) && hasParent;
    final bool isMainMenu = !isSubMenu && (item.level == 0 || item.indent == 0 || !hasParent);

    if (isMainMenu) {
      // Create new root node; stops trapping items inside previous parent
      activeParent = NavGroupItem(
        id: item.id,
        title: item.customTitle?.isNotEmpty == true ? item.customTitle! : item.title,
        icon: item.icon,
        route: item.route,
        children: [],
      );
      rootItems.add(activeParent);
    } else {
      // Sub-menu item
      final subItem = NavGroupItem(
        id: item.id,
        title: item.customTitle?.isNotEmpty == true ? item.customTitle! : item.title,
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
