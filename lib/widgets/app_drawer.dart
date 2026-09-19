import 'package:flutter/material.dart';

import '../core/navigation/navigation_provider.dart';
import 'tenant_logo_avatar.dart';

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

/// Builds a resilient drawer header that never collapses to SizedBox.shrink().
///
/// Guaranteed behavior:
/// - Renders store logo (via [TenantLogoAvatar]), brand name, and store type badge pill.
/// - Falls back to 'ZoomNearby Enterprise' and 'RETAIL' if [tenant] is null or loading.
/// - Never collapses into SizedBox.shrink() or empty space.
/// - No web-specific suppression (renders consistently across web, desktop, and mobile).
Widget buildDrawerHeader(
  BuildContext context, {
  Tenant? tenant,
  String? fallbackName,
  String? fallbackType,
  String? logoUrl,
  String? coverUrl,
  Color? primaryColor,
  VoidCallback? onTap,
}) {
  final displayName = (tenant?.displayName.isNotEmpty == true)
      ? tenant!.displayName
      : (fallbackName != null && fallbackName.trim().isNotEmpty
          ? fallbackName.trim()
          : 'ZoomNearby Enterprise');

  final displayType = (tenant?.displayType.isNotEmpty == true)
      ? tenant!.displayType
      : (fallbackType != null && fallbackType.trim().isNotEmpty
          ? fallbackType.trim().toUpperCase()
          : 'RETAIL');

  final effectiveLogo = tenant?.logoUrl ?? logoUrl;
  final effectiveCover = tenant?.drawerCoverUrl ?? coverUrl;
  final hasCover = effectiveCover != null && effectiveCover.trim().isNotEmpty;

  return InkWell(
    onTap: onTap,
    child: Container(
      key: const ValueKey('app-drawer-header'),
      width: double.infinity,
      padding: EdgeInsets.fromLTRB(
        16,
        MediaQuery.of(context).padding.top + 16,
        16,
        16,
      ),
      decoration: BoxDecoration(
        color: const Color(0xFF0F172A), // Slate-900 brand surface
        image: hasCover
            ? DecorationImage(
                image: NetworkImage(
                  effectiveCover,
                  webHtmlElementStrategy: WebHtmlElementStrategy.prefer,
                ),
                fit: BoxFit.cover,
              )
            : null,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              TenantLogoAvatar(
                logoUrl: effectiveLogo,
                tenantName: displayName,
                size: 44,
                borderRadius: BorderRadius.circular(8),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      displayName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        letterSpacing: -0.2,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color: const Color(0xFF1E293B), // Slate-800
                        borderRadius: BorderRadius.circular(4),
                        border: Border.all(
                          color: const Color(0xFF334155),
                          width: 1,
                        ),
                      ),
                      child: Text(
                        displayType,
                        style: const TextStyle(
                          color: Color(0xFF38BDF8), // Light Sky Blue
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    ),
  );
}

/// Standalone resilient application drawer widget.
class AppDrawer extends StatelessWidget {
  const AppDrawer({
    super.key,
    this.tenant,
    this.fallbackName,
    this.fallbackType,
    this.logoUrl,
    this.coverUrl,
    this.sections,
    this.onItemTap,
    this.activeColor = const Color(0xFF1D4ED8),
    this.selectedKey,
    this.headerOnTap,
    this.footer,
  });

  final Tenant? tenant;
  final String? fallbackName;
  final String? fallbackType;
  final String? logoUrl;
  final String? coverUrl;
  final List<NavSection>? sections;
  final void Function(NavItem item)? onItemTap;
  final Color activeColor;
  final String? selectedKey;
  final VoidCallback? headerOnTap;
  final Widget? footer;

  @override
  Widget build(BuildContext context) {
    return Drawer(
      backgroundColor: const Color(0xFF0F172A),
      child: SafeArea(
        top: false,
        bottom: true,
        child: Column(
          children: [
            buildDrawerHeader(
              context,
              tenant: tenant,
              fallbackName: fallbackName,
              fallbackType: fallbackType,
              logoUrl: logoUrl,
              coverUrl: coverUrl,
              onTap: headerOnTap,
            ),
            Expanded(
              child: ListView(
                padding: EdgeInsets.zero,
                children: [
                  if (sections != null)
                    for (final section in sections!) ...[
                      if (section.title.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                          child: Text(
                            section.title.toUpperCase(),
                            style: const TextStyle(
                              color: Color(0xFF94A3B8),
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              letterSpacing: 0.8,
                            ),
                          ),
                        ),
                      for (final item in section.items)
                        DrawerItemParser.buildDrawerMenuItem(
                          context,
                          item.toJson(),
                          activeColor: activeColor,
                          isSelected: item.key == selectedKey,
                          onTap: () => onItemTap?.call(item),
                        ),
                    ],
                ],
              ),
            ),
            if (footer != null) footer!,
          ],
        ),
      ),
    );
  }
}
