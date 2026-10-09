import 'package:flutter/material.dart';

import '../../../widgets/dynamic_sdui_icon.dart';

/// Drawer Item Tile component using DynamicSduiIcon.
class DrawerItemTile extends StatelessWidget {
  const DrawerItemTile({
    super.key,
    required this.item,
    this.onTap,
  });

  final Map<String, dynamic> item;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return _buildDrawerItemTile(
      item,
      onNavigation: onTap != null ? (_) => onTap!() : null,
    );
  }
}

/// Standalone builder matching Section 3 specification.
Widget buildDrawerItemTile(
  Map<String, dynamic> item, {
  void Function(Map<String, dynamic> item)? onNavigation,
}) =>
    _buildDrawerItemTile(item, onNavigation: onNavigation);

Widget _buildDrawerItemTile(
  Map<String, dynamic> item, {
  void Function(Map<String, dynamic> item)? onNavigation,
}) {
  return ListTile(
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
    leading: DynamicSduiIcon(
      iconData: item['icon'], // Receives dynamic code_point or remote URL from server
      color: const Color(0xFF94A3B8),
      size: 20,
    ),
    title: Text(
      item['title']?.toString() ?? '',
      style: const TextStyle(
        color: Color(0xFFE2E8F0),
        fontSize: 13,
        fontWeight: FontWeight.w500,
      ),
    ),
    onTap: () {
      if (onNavigation != null) {
        onNavigation(item);
      }
    },
  );
}
