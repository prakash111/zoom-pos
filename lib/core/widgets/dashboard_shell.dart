import 'package:flutter/material.dart';

import 'dashboard_kit.dart';

/// One entry in the [DashboardShell] left sidebar.
class DashNavItem {
  const DashNavItem(this.label, {required this.icon});

  final String label;
  final IconData icon;
}

/// Windows desktop dashboard chrome styled after the "POWRSALE" reference:
/// a soft lavender page holding one big floating rounded panel that is split
/// into a white left sidebar (logo, "Create New" button, nav list, shop
/// selector, logout), the light main content area, and an optional right
/// rail (profile / messages).
class DashboardShell extends StatelessWidget {
  const DashboardShell({
    super.key,
    required this.brand,
    required this.child,
    this.destinations = const [],
    this.selectedIndex = 0,
    this.onSelect,
    this.onCreate,
    this.createLabel = 'Create New\nTransaction',
    this.shopName,
    this.onLogout,
    this.rightRail,
  });

  final String brand;
  final List<DashNavItem> destinations;
  final int selectedIndex;
  final ValueChanged<int>? onSelect;

  final VoidCallback? onCreate;
  final String createLabel;
  final String? shopName;
  final VoidCallback? onLogout;

  /// Optional profile / messages column shown on wide windows.
  final Widget? rightRail;

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: PowrTokens.pageBg,
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFFF6F5FE), Color(0xFFEFF1FA), Color(0xFFF7F2FB)],
          ),
        ),
        child: SafeArea(
          child: LayoutBuilder(
            builder: (context, c) {
              final showRail = rightRail != null && c.maxWidth >= 1200;
              return Center(
                child: Container(
                  margin: const EdgeInsets.all(20),
                  constraints:
                      const BoxConstraints(maxWidth: 1440, maxHeight: 940),
                  decoration: BoxDecoration(
                    color: PowrTokens.card,
                    borderRadius: BorderRadius.circular(28),
                    boxShadow: PowrTokens.shadow,
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: Row(
                    children: [
                      _Sidebar(
                        brand: brand,
                        destinations: destinations,
                        selectedIndex: selectedIndex,
                        onSelect: onSelect,
                        onCreate: onCreate,
                        createLabel: createLabel,
                        shopName: shopName,
                        onLogout: onLogout,
                      ),
                      const VerticalDivider(width: 1, color: PowrTokens.line),
                      Expanded(
                        child: Container(
                          color: PowrTokens.pageBg,
                          child: SingleChildScrollView(
                            padding: const EdgeInsets.all(28),
                            child: child,
                          ),
                        ),
                      ),
                      if (showRail) ...[
                        const VerticalDivider(width: 1, color: PowrTokens.line),
                        SizedBox(
                          width: 300,
                          child: SingleChildScrollView(
                            padding: const EdgeInsets.all(22),
                            child: rightRail,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

class _Sidebar extends StatelessWidget {
  const _Sidebar({
    required this.brand,
    required this.destinations,
    required this.selectedIndex,
    required this.onSelect,
    required this.onCreate,
    required this.createLabel,
    required this.shopName,
    required this.onLogout,
  });

  final String brand;
  final List<DashNavItem> destinations;
  final int selectedIndex;
  final ValueChanged<int>? onSelect;
  final VoidCallback? onCreate;
  final String createLabel;
  final String? shopName;
  final VoidCallback? onLogout;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 236,
      color: PowrTokens.card,
      padding: const EdgeInsets.fromLTRB(20, 24, 16, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.blur_on, color: PowrTokens.primary, size: 22),
              const SizedBox(width: 8),
              Text(
                brand.toUpperCase(),
                style: const TextStyle(
                  color: PowrTokens.primary,
                  fontWeight: FontWeight.w900,
                  fontSize: 15,
                  letterSpacing: 0.5,
                ),
              ),
            ],
          ),
          const SizedBox(height: 26),
          if (onCreate != null)
            Material(
              color: PowrTokens.pageBg,
              borderRadius: PowrTokens.radiusSm,
              child: InkWell(
                borderRadius: PowrTokens.radiusSm,
                onTap: onCreate,
                child: Padding(
                  padding: const EdgeInsets.all(10),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          createLabel,
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: PowrTokens.muted,
                            height: 1.25,
                          ),
                        ),
                      ),
                      Container(
                        width: 30,
                        height: 30,
                        decoration: BoxDecoration(
                          color: PowrTokens.accent,
                          borderRadius: BorderRadius.circular(9),
                        ),
                        child: const Icon(Icons.add,
                            color: Colors.white, size: 18),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          const SizedBox(height: 20),
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                for (var i = 0; i < destinations.length; i++)
                  _NavRow(
                    item: destinations[i],
                    selected: i == selectedIndex,
                    onTap: onSelect == null ? null : () => onSelect!(i),
                  ),
              ],
            ),
          ),
          if (shopName != null) ...[
            const Text('Selected Shop',
                style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    color: PowrTokens.faint,
                    letterSpacing: 0.4)),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              decoration: BoxDecoration(
                color: PowrTokens.pageBg,
                borderRadius: PowrTokens.radiusSm,
              ),
              child: Row(children: [
                CircleAvatar(
                  radius: 12,
                  backgroundColor: PowrTokens.primary,
                  child: Text(
                    shopName!.characters.first.toUpperCase(),
                    style: const TextStyle(
                        color: Colors.white,
                        fontSize: 11,
                        fontWeight: FontWeight.w800),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(shopName!,
                      style: const TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w700,
                          color: PowrTokens.ink)),
                ),
                const Icon(Icons.keyboard_arrow_down,
                    size: 16, color: PowrTokens.muted),
              ]),
            ),
            const SizedBox(height: 14),
          ],
          if (onLogout != null)
            InkWell(
              onTap: onLogout,
              child: const Padding(
                padding: EdgeInsets.symmetric(vertical: 6),
                child: Row(children: [
                  Icon(Icons.logout, size: 17, color: PowrTokens.ink),
                  SizedBox(width: 10),
                  Text('Logout',
                      style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: PowrTokens.ink)),
                ]),
              ),
            ),
        ],
      ),
    );
  }
}

class _NavRow extends StatelessWidget {
  const _NavRow({
    required this.item,
    required this.selected,
    required this.onTap,
  });

  final DashNavItem item;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? PowrTokens.primary : PowrTokens.muted;
    return InkWell(
      borderRadius: PowrTokens.radiusSm,
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 11, horizontal: 6),
        child: Row(
          children: [
            Icon(item.icon, size: 18, color: color),
            const SizedBox(width: 12),
            Text(
              item.label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                color: color,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
