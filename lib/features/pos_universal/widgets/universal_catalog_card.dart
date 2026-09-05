import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../../../core/utils/currency_formatter.dart';
import '../../../core/utils/image_url.dart';
import '../pos_screen_model.dart';

/// Visual counterpart to `SduiProductCard`
/// (`core/widgets/sdui/sdui_catalog_layouts.dart`) for the generic
/// `pos_screen` catalog item shape, which `ProductModel` can't represent
/// (no subtitle/badge/on_tap slot).
class UniversalCatalogCard extends StatelessWidget {
  const UniversalCatalogCard({
    super.key,
    required this.item,
    required this.formatter,
    required this.onTap,
    this.baseUrl,
  });

  final PosCatalogItem item;
  final CurrencyFormatter formatter;
  final VoidCallback? onTap;
  final String? baseUrl;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final resolvedImageUrl = resolveImageUrl(item.imageUrl, baseUrl: baseUrl);
    final badge = item.badge;

    return Card(
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: item.isOutOfStock ? null : onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: SizedBox.expand(
                    child: resolvedImageUrl == null
                        ? Container(
                            color: Colors.grey.shade100,
                            child: Center(
                              child: Icon(Icons.inventory_2_outlined,
                                  size: 36, color: Colors.grey.shade400),
                            ),
                          )
                        : CachedNetworkImage(
                            imageUrl: resolvedImageUrl,
                            fit: BoxFit.cover,
                            placeholder: (_, __) => const Center(
                              child: SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(strokeWidth: 2)),
                            ),
                            errorWidget: (_, __, ___) => Container(
                              color: Colors.grey.shade100,
                              child: Icon(Icons.broken_image_outlined,
                                  color: Colors.grey.shade400),
                            ),
                          ),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      item.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                    ),
                  ),
                  if (badge != null) ...[
                    const SizedBox(width: 4),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: badge.color.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        badge.text,
                        style: TextStyle(
                            color: badge.color, fontSize: 10, fontWeight: FontWeight.w700),
                      ),
                    ),
                  ],
                ],
              ),
              if (item.subtitle != null && item.subtitle!.isNotEmpty) ...[
                const SizedBox(height: 2),
                Text(
                  item.subtitle!,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: Colors.grey.shade600, fontSize: 11),
                ),
              ],
              const SizedBox(height: 4),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    formatter.format(item.price),
                    style: TextStyle(
                        color: theme.colorScheme.primary,
                        fontWeight: FontWeight.bold,
                        fontSize: 13),
                  ),
                  if (item.isOutOfStock)
                    Text('Out of stock', style: TextStyle(color: Colors.red.shade400, fontSize: 11))
                  else if (item.isLowStock)
                    Text('Low stock', style: TextStyle(color: Colors.orange.shade700, fontSize: 11)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
