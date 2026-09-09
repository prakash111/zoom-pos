import 'package:flutter/material.dart';

import 'dashboard_kit.dart';

/// One entry in the [DashboardShell] top navigation bar.
class DashNavItem {
  const DashNavItem(this.label, {this.icon});

  final String label;
  final IconData? icon;
}

/// Windows desktop dashboard chrome, matching the reference admin
/// screenshots: a dark horizontal top bar (bold brand name on the left,
/// text nav links, trailing actions on the right), a light slate page, and
/// the page body centred in a `contentMaxWidth` column with comfortable
/// padding. Screens compose their content from `DashCard` and friends
/// (`dashboard_kit.dart`).
class DashboardShell extends StatelessWidget {
  const DashboardShell({
    super.key,
    required this.brand,
    required this.child,
    this.destinations = const [],
    this.selectedIndex = 0,
    this.onSelect,
    this.trailing = const [],
    this.footer,
    this.scrollable = true,
  });

  final String brand;
  final List<DashNavItem> destinations;
  final int selectedIndex;
  final ValueChanged<int>? onSelect;

  /// Right-aligned top-bar widgets (e.g. a "Sign out" text button).
  final List<Widget> trailing;

  /// Optional strip pinned under the content (e.g. the sync status bar).
  final Widget? footer;

  final Widget child;
  final bool scrollable;

  @override
  Widget build(BuildContext context) {
    final content = Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: DashTokens.contentMaxWidth),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 28),
          child: child,
        ),
      ),
    );

    return Scaffold(
      backgroundColor: DashTokens.pageBg,
      body: Column(
        children: [
          _TopBar(
            brand: brand,
            destinations: destinations,
            selectedIndex: selectedIndex,
            onSelect: onSelect,
            trailing: trailing,
          ),
          Expanded(
            child: scrollable ? SingleChildScrollView(child: content) : content,
          ),
          if (footer != null) footer!,
        ],
      ),
    );
  }
}

class _TopBar extends StatelessWidget {
  const _TopBar({
    required this.brand,
    required this.destinations,
    required this.selectedIndex,
    required this.onSelect,
    required this.trailing,
  });

  final String brand;
  final List<DashNavItem> destinations;
  final int selectedIndex;
  final ValueChanged<int>? onSelect;
  final List<Widget> trailing;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 60,
      color: DashTokens.topBar,
      padding: const EdgeInsets.symmetric(horizontal: 24),
      child: Row(
        children: [
          Text(
            brand,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w800,
              fontSize: 16,
              letterSpacing: -0.2,
            ),
          ),
          const SizedBox(width: 28),
          for (var i = 0; i < destinations.length; i++)
            _NavLink(
              item: destinations[i],
              selected: i == selectedIndex,
              onTap: onSelect == null ? null : () => onSelect!(i),
            ),
          const Spacer(),
          ...trailing,
        ],
      ),
    );
  }
}

class _NavLink extends StatelessWidget {
  const _NavLink({
    required this.item,
    required this.selected,
    required this.onTap,
  });

  final DashNavItem item;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? Colors.white : const Color(0xFF94A3B8);
    return Padding(
      padding: const EdgeInsets.only(right: 6),
      child: Material(
        type: MaterialType.transparency,
        child: InkWell(
          borderRadius: BorderRadius.circular(8),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 18),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (item.icon != null) ...[
                  Icon(item.icon, size: 16, color: color),
                  const SizedBox(width: 7),
                ],
                Text(
                  item.label,
                  style: TextStyle(
                    color: color,
                    fontSize: 13.5,
                    fontWeight: selected ? FontWeight.w700 : FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
