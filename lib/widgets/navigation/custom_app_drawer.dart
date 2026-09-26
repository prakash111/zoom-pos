import 'package:flutter/material.dart';

import '../../core/navigation/navigation_provider.dart';
import '../app_drawer.dart';

export '../app_drawer.dart';

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
      fallbackName: fallbackName,
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
