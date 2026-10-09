import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/config/theme_provider.dart';

/// Reusable Theme-Aware Main Bottom Navigation Bar.
///
/// Dynamically binds active tab items and the center action button (+)
/// to [ThemeProvider.brandColor] (e.g. Orange #EA580C).
class MainBottomNavBar extends StatelessWidget {
  const MainBottomNavBar({
    super.key,
    required this.currentIndex,
    required this.onTap,
    this.onQuickAction,
  });

  final int currentIndex;
  final ValueChanged<int> onTap;
  final VoidCallback? onQuickAction;

  @override
  Widget build(BuildContext context) {
    return _buildBottomNavigationBar(
      context,
      currentIndex,
      onTap,
      onQuickAction: onQuickAction,
    );
  }
}

/// Standalone builder matching Section 2 specification.
Widget buildBottomNavigationBar(
  BuildContext context,
  int currentIndex,
  void Function(int) onTap, {
  VoidCallback? onQuickAction,
}) =>
    _buildBottomNavigationBar(context, currentIndex, onTap,
        onQuickAction: onQuickAction);

Widget _buildBottomNavigationBar(
  BuildContext context,
  int currentIndex,
  void Function(int) onTap, {
  VoidCallback? onQuickAction,
}) {
  // Listen to the active brand color from provider
  final brandColor = Provider.of<ThemeProvider>(context).brandColor;

  return Container(
    decoration: BoxDecoration(
      color: const Color(0xFF1E293B), // Dark surface bar
      borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
      border: Border(
        top: BorderSide(
          color: const Color(0xFF334155).withValues(alpha: 0.5),
        ),
      ),
    ),
    child: SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: [
            // 1. Home Tab
            _buildNavItem(
              context: context,
              icon: Icons.home_rounded,
              label: 'Home',
              isSelected: currentIndex == 0,
              brandColor: brandColor,
              onTap: () => onTap(0),
            ),

            // 2. Sales Tab
            _buildNavItem(
              context: context,
              icon: Icons.receipt_long_rounded,
              label: 'Sales',
              isSelected: currentIndex == 1,
              brandColor: brandColor,
              onTap: () => onTap(1),
            ),

            // 3. Center Action Floating Button (+)
            _buildCenterActionButton(
              context,
              brandColor,
              onQuickAction: onQuickAction,
            ),

            // 4. Orders Tab
            _buildNavItem(
              context: context,
              icon: Icons.shopping_bag_outlined,
              label: 'Orders',
              isSelected: currentIndex == 2,
              brandColor: brandColor,
              onTap: () => onTap(2),
            ),

            // 5. More / Menu Tab
            _buildNavItem(
              context: context,
              icon: Icons.grid_view_rounded,
              label: 'More',
              isSelected: currentIndex == 3,
              brandColor: brandColor,
              onTap: () => onTap(3),
            ),
          ],
        ),
      ),
    ),
  );
}

// Nav Tab Item Helper
Widget _buildNavItem({
  required BuildContext context,
  required IconData icon,
  required String label,
  required bool isSelected,
  required Color brandColor,
  required VoidCallback onTap,
}) {
  final activeColor = brandColor;
  const inactiveColor = Color(0xFF64748B);

  return InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(12),
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            icon,
            size: 22,
            color: isSelected ? activeColor : inactiveColor, // DYNAMIC ACTIVE COLOR
          ),
          const SizedBox(height: 3),
          Text(
            label,
            style: TextStyle(
              fontSize: 11,
              fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
              color: isSelected ? activeColor : inactiveColor, // DYNAMIC ACTIVE TEXT
            ),
          ),
        ],
      ),
    ),
  );
}

// Center Floating '+' Button
Widget _buildCenterActionButton(
  BuildContext context,
  Color brandColor, {
  VoidCallback? onQuickAction,
}) {
  return GestureDetector(
    onTap: onQuickAction ?? () => _openQuickCheckoutModal(context),
    child: Container(
      width: 48,
      height: 48,
      decoration: BoxDecoration(
        color: brandColor, // DYNAMIC BRAND COLOR (e.g. #EA580C)
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(
            color: brandColor.withValues(alpha: 0.4),
            blurRadius: 12,
            spreadRadius: 2,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: const Icon(
        Icons.add_rounded,
        color: Colors.white,
        size: 28,
      ),
    ),
  );
}

void _openQuickCheckoutModal(BuildContext context) {
  try {
    Navigator.of(context).pushNamed('/pos');
  } catch (_) {}
}
