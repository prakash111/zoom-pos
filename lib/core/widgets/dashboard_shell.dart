import 'package:flutter/material.dart';

import 'dashboard_kit.dart';

/// One entry in the [DashboardShell] icon rail.
class DashNavItem {
  const DashNavItem(this.label, {required this.icon});

  final String label;
  final IconData icon;
}

/// Windows desktop dashboard chrome styled after the green inventory-analytics
/// reference: a narrow icon-only left rail (brand mark, a divider, then a
/// vertical stack of line icons — the active one in a white shadowed pill)
/// and a light scrolling content area, all inside one large rounded panel.
class DashboardShell extends StatelessWidget {
  const DashboardShell({
    super.key,
    required this.child,
    this.destinations = const [],
    this.selectedIndex = 0,
    this.onSelect,
    this.brandMark,
  });

  final List<DashNavItem> destinations;
  final int selectedIndex;
  final ValueChanged<int>? onSelect;

  /// Small logo widget shown at the top of the rail; defaults to a green cube.
  final Widget? brandMark;

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFE9EAEE),
      body: SafeArea(
        child: Center(
          child: Container(
            margin: const EdgeInsets.fromLTRB(0, 0, 0, 0),
            constraints: const BoxConstraints(maxWidth: 1520, maxHeight: 1000),
            decoration: BoxDecoration(
              color: SpTokens.pageBg,
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(28),
                bottomLeft: Radius.circular(28),
              ),
              boxShadow: SpTokens.shadow,
            ),
            clipBehavior: Clip.antiAlias,
            child: Row(
              children: [
                _Rail(
                  destinations: destinations,
                  selectedIndex: selectedIndex,
                  onSelect: onSelect,
                  brandMark: brandMark,
                ),
                Expanded(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(36, 28, 36, 40),
                    child: child,
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

class _Rail extends StatelessWidget {
  const _Rail({
    required this.destinations,
    required this.selectedIndex,
    required this.onSelect,
    required this.brandMark,
  });

  final List<DashNavItem> destinations;
  final int selectedIndex;
  final ValueChanged<int>? onSelect;
  final Widget? brandMark;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 76,
      color: SpTokens.card,
      padding: const EdgeInsets.symmetric(vertical: 22),
      child: Column(
        children: [
          brandMark ??
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [Color(0xFFA3E635), Color(0xFF65A30D)],
                  ),
                  borderRadius: BorderRadius.circular(10),
                ),
                alignment: Alignment.center,
                child: const Text('S',
                    style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                        fontSize: 18)),
              ),
          const SizedBox(height: 18),
          const Divider(indent: 20, endIndent: 20, color: SpTokens.line),
          const SizedBox(height: 10),
          Expanded(
            child: ListView.builder(
              padding: EdgeInsets.zero,
              itemCount: destinations.length,
              itemBuilder: (context, i) {
                final selected = i == selectedIndex;
                return Padding(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                  child: Tooltip(
                    message: destinations[i].label,
                    child: Material(
                      color: selected ? SpTokens.card : Colors.transparent,
                      borderRadius: BorderRadius.circular(12),
                      elevation: selected ? 3 : 0,
                      shadowColor: Colors.black.withValues(alpha: 0.15),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(12),
                        onTap: onSelect == null ? null : () => onSelect!(i),
                        child: Padding(
                          padding: const EdgeInsets.all(11),
                          child: Icon(
                            destinations[i].icon,
                            size: 20,
                            color: selected
                                ? Theme.of(context).colorScheme.primary
                                : SpTokens.faint,
                          ),
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
