import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../core/stores/store_provider.dart';

/// Displays the compact 4-character store display name for the dashboard top bar.
class DashboardHeaderStoreName extends StatelessWidget {
  const DashboardHeaderStoreName({
    super.key,
    this.storeProvider,
    this.fallbackName = 'Zoom',
    this.textStyle,
    this.maxLines = 1,
    this.overflow = TextOverflow.ellipsis,
  });

  final StoreProvider? storeProvider;
  final String fallbackName;
  final TextStyle? textStyle;
  final int maxLines;
  final TextOverflow overflow;

  @override
  Widget build(BuildContext context) {
    StoreProvider? provider = storeProvider;
    if (provider == null) {
      try {
        provider = Provider.of<StoreProvider>(context);
      } catch (_) {}
    }

    final store = provider?.currentStore ?? provider?.current;
    final storeName = store?.shortName.isNotEmpty == true
        ? store!.shortName
        : (store?.name ?? fallbackName);

    // Strictly limit dashboard header store name to the first 4 characters
    final displayHeaderName =
        storeName.length > 4 ? storeName.substring(0, 4) : storeName;

    return Text(
      displayHeaderName, // Displays "Zoom"
      maxLines: maxLines,
      overflow: overflow,
      style: textStyle ??
          const TextStyle(
            color: Colors.white,
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
    );
  }
}

/// Dashboard top application bar implementing the 4-character store name display.
class DashboardAppBar extends StatelessWidget implements PreferredSizeWidget {
  const DashboardAppBar({
    super.key,
    this.storeProvider,
    this.onStoreTap,
    this.leading,
    this.actions,
    this.bottom,
    this.backgroundColor,
    this.elevation = 0,
    this.avatar,
  });

  final StoreProvider? storeProvider;
  final VoidCallback? onStoreTap;
  final Widget? leading;
  final List<Widget>? actions;
  final PreferredSizeWidget? bottom;
  final Color? backgroundColor;
  final double elevation;
  final Widget? avatar;

  @override
  Size get preferredSize =>
      Size.fromHeight(kToolbarHeight + (bottom?.preferredSize.height ?? 0.0));

  @override
  Widget build(BuildContext context) {
    return AppBar(
      leading: leading,
      backgroundColor: backgroundColor,
      elevation: elevation,
      title: InkWell(
        onTap: onStoreTap,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (avatar != null) ...[
              avatar!,
              const SizedBox(width: 8),
            ],
            Flexible(
              child: DashboardHeaderStoreName(
                storeProvider: storeProvider,
              ),
            ),
            const SizedBox(width: 4),
            const Icon(Icons.keyboard_arrow_down, size: 18),
          ],
        ),
      ),
      actions: actions,
      bottom: bottom,
    );
  }
}
