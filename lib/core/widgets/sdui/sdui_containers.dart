import 'package:flutter/material.dart';

import '../../sdui/models/sdui_models.dart';
import '../../sdui/sdui_icon_registry.dart';

/// Agnostic header bar for SDUI screens.
class SduiHeaderBar extends StatelessWidget implements PreferredSizeWidget {
  const SduiHeaderBar({
    super.key,
    required this.title,
    this.subtitle,
    this.activeMode,
    this.availableModes = const [],
    this.onModeSelected,
    this.actions = const [],
    this.leading,
    this.bottom,
  });

  final String title;
  final String? subtitle;
  final String? activeMode;
  final List<String> availableModes;
  final ValueChanged<String>? onModeSelected;
  final List<Widget> actions;
  final Widget? leading;
  final PreferredSizeWidget? bottom;

  @override
  Size get preferredSize => Size.fromHeight(kToolbarHeight + (bottom?.preferredSize.height ?? 0));

  @override
  Widget build(BuildContext context) {
    return AppBar(
      leading: leading,
      title: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
              if (availableModes.length > 1 && onModeSelected != null) ...[
                const SizedBox(width: 8),
                PopupMenuButton<String>(
                  initialValue: activeMode,
                  tooltip: 'Switch Operating Mode',
                  onSelected: onModeSelected,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primaryContainer,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          (activeMode ?? '').toUpperCase(),
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            color: Theme.of(context).colorScheme.onPrimaryContainer,
                          ),
                        ),
                        const Icon(Icons.arrow_drop_down, size: 14),
                      ],
                    ),
                  ),
                  itemBuilder: (context) => [
                    for (final mode in availableModes)
                      PopupMenuItem(
                        value: mode,
                        child: Text(mode.toUpperCase()),
                      ),
                  ],
                ),
              ],
            ],
          ),
          if (subtitle != null && subtitle!.isNotEmpty)
            Text(
              subtitle!,
              style: TextStyle(fontSize: 11, color: Theme.of(context).colorScheme.onSurfaceVariant),
            ),
        ],
      ),
      actions: actions,
      bottom: bottom,
    );
  }
}

/// Dynamic navigation tile with indentation for sub-menus and server-driven icon.
class SduiNavTile extends StatelessWidget {
  const SduiNavTile({
    super.key,
    required this.item,
    required this.isSelected,
    required this.onTap,
    this.indent = 0,
    this.badgeText,
  });

  final SduiNavItemSchema item;
  final bool isSelected;
  final VoidCallback onTap;
  final int indent;
  final String? badgeText;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final iconData = SduiIconRegistry.resolve(item.icon);

    return Padding(
      padding: EdgeInsets.only(left: 12.0 + (indent * 20.0), right: 12.0, top: 2, bottom: 2),
      child: ListTile(
        dense: true,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        selected: isSelected,
        selectedTileColor: theme.colorScheme.primaryContainer.withValues(alpha: 0.5),
        leading: Icon(
          iconData,
          size: 20,
          color: isSelected ? theme.colorScheme.primary : theme.colorScheme.onSurfaceVariant,
        ),
        title: Text(
          item.title,
          style: TextStyle(
            fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
            fontSize: 14,
            color: isSelected ? theme.colorScheme.primary : theme.colorScheme.onSurface,
          ),
        ),
        trailing: badgeText != null
            ? Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: theme.colorScheme.primary,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  badgeText!,
                  style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                ),
              )
            : null,
        onTap: onTap,
      ),
    );
  }
}

/// Fully server-driven side-drawer container.
class SduiSideDrawerContainer extends StatelessWidget {
  const SduiSideDrawerContainer({
    super.key,
    required this.sections,
    required this.selectedKey,
    required this.onItemTap,
    this.header,
    this.footer,
  });

  final List<SduiNavSectionSchema> sections;
  final String selectedKey;
  final ValueChanged<SduiNavItemSchema> onItemTap;
  final Widget? header;
  final Widget? footer;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Drawer(
      child: SafeArea(
        child: Column(
          children: [
            if (header != null) header!,
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(vertical: 8),
                children: [
                  for (final section in sections) ...[
                    if (section.title.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(20, 16, 16, 6),
                        child: Text(
                          section.title.toUpperCase(),
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                            letterSpacing: 0.8,
                            color: section.color != null
                                ? SduiIconRegistry.parseColor(section.color)
                                : theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                      ),
                    for (final item in section.items)
                      SduiNavTile(
                        item: item,
                        isSelected: item.key == selectedKey,
                        indent: item.parent != null ? 1 : 0,
                        onTap: () => onItemTap(item),
                      ),
                  ],
                ],
              ),
            ),
            if (footer != null) ...[
              const Divider(height: 1),
              footer!,
            ],
          ],
        ),
      ),
    );
  }
}
