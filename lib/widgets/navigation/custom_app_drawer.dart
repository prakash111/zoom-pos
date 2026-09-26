import 'package:flutter/material.dart';

import '../../core/navigation/navigation_provider.dart';
import '../../core/stores/store_provider.dart';
import 'package:provider/provider.dart';
import '../app_drawer.dart';

export '../app_drawer.dart';

/// Renders the complete, untruncated business store name for the navigation drawer header.
class CustomDrawerHeader extends StatelessWidget {
  const CustomDrawerHeader({
    super.key,
    this.storeProvider,
    this.fallbackName = 'ZoomNearby Enterprise',
    this.tenant,
    this.logoUrl,
    this.coverUrl,
    this.onTap,
  });

  final StoreProvider? storeProvider;
  final String fallbackName;
  final Tenant? tenant;
  final String? logoUrl;
  final String? coverUrl;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    StoreProvider? provider = storeProvider;
    if (provider == null) {
      try {
        provider = Provider.of<StoreProvider>(context);
      } catch (_) {}
    }

    final fullStoreName = provider?.currentStore?.fullName ??
        provider?.currentStore?.name ??
        (tenant?.displayName.isNotEmpty == true ? tenant!.displayName : null) ??
        fallbackName;

    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Text(
          fullStoreName, // Displays full name (e.g., "ZoomNearby Enterprise Demo")
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 15,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
    );
  }
}

/// Custom application drawer with the Language Switcher option ("भाषाएँ और अनुवाद" /
/// "Languages & Translations") explicitly removed from drawer navigation.
class CustomAppDrawer extends StatelessWidget {
  const CustomAppDrawer({
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
    this.storeProvider,
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
  final StoreProvider? storeProvider;

  /// Returns true if an item is a language switcher item.
  static bool isLanguageItem(dynamic item) {
    if (item == null) return false;
    final key = (item is Map
            ? item['key'] ?? item['id']
            : (item is NavItem ? item.key : ''))
        .toString()
        .toLowerCase();
    final title = (item is Map
            ? item['title'] ?? item['custom_title'] ?? item['label']
            : (item is NavItem ? item.title : ''))
        .toString()
        .toLowerCase();
    return key == 'languages' ||
        key == 'language' ||
        key == 'lang' ||
        title.contains('languages & translations') ||
        title.contains('भाषाएँ और अनुवाद') ||
        title.contains('भाषा और अनुवाद') ||
        title.contains('languages and translations');
  }

  @override
  Widget build(BuildContext context) {
    String? effectiveName = fallbackName;
    if (effectiveName == null || effectiveName.isEmpty) {
      StoreProvider? provider = storeProvider;
      if (provider == null) {
        try {
          provider = Provider.of<StoreProvider>(context, listen: false);
        } catch (_) {}
      }
      final fullStoreName = provider?.currentStore?.fullName ??
          provider?.currentStore?.name ??
          'ZoomNearby Enterprise';
      if (fullStoreName.isNotEmpty) {
        effectiveName = fullStoreName;
      }
    }

    final filteredSections = sections?.map((sec) {
      return NavSection(
        key: sec.key,
        title: sec.title,
        color: sec.color,
        items: sec.items.where((i) => !isLanguageItem(i)).toList(),
      );
    }).toList();

    return AppDrawer(
      tenant: tenant,
      fallbackName: effectiveName,
      fallbackType: fallbackType,
      logoUrl: logoUrl,
      coverUrl: coverUrl,
      sections: filteredSections,
      onItemTap: onItemTap,
      activeColor: activeColor,
      selectedKey: selectedKey,
      headerOnTap: headerOnTap,
      footer: footer,
    );
  }
}
