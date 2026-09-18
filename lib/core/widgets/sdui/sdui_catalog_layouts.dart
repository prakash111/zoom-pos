import 'package:flutter/material.dart';

import '../../models/product_model.dart';
import '../../utils/currency_formatter.dart';
import '../../utils/responsive.dart';
import '../app_network_image.dart';

/// Agnostic layout builder for catalog items supporting grid or list presentations.
class SduiCatalogLayout extends StatelessWidget {
  const SduiCatalogLayout({
    super.key,
    required this.layoutType, // 'standard_grid', 'catalog_list'
    required this.products,
    required this.formatter,
    this.baseUrl,
    required this.onProductSelected,
    this.emptyMessage = 'No products found.',
  });

  final String layoutType;
  final List<ProductModel> products;
  final CurrencyFormatter formatter;
  final String? baseUrl;
  final ValueChanged<ProductModel> onProductSelected;
  final String emptyMessage;

  @override
  Widget build(BuildContext context) {
    if (products.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Text(emptyMessage, style: const TextStyle(color: Colors.grey)),
        ),
      );
    }

    if (layoutType == 'catalog_list') {
      return ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        itemCount: products.length,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final product = products[index];
          return SduiProductListTile(
            product: product,
            formatter: formatter,
            baseUrl: baseUrl,
            onTap: product.isOutOfStock ? null : () => onProductSelected(product),
          );
        },
      );
    }

    // Default to standard_grid
    return LayoutBuilder(
      builder: (context, constraints) {
        return GridView.builder(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: gridColumnsFor(constraints.maxWidth),
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: 0.85,
          ),
          itemCount: products.length,
          itemBuilder: (context, index) {
            final product = products[index];
            return SduiProductCard(
              product: product,
              formatter: formatter,
              baseUrl: baseUrl,
              onTap: product.isOutOfStock ? null : () => onProductSelected(product),
            );
          },
        );
      },
    );
  }
}

/// Reusable product card for catalog grids.
class SduiProductCard extends StatelessWidget {
  const SduiProductCard({
    super.key,
    required this.product,
    required this.formatter,
    required this.onTap,
    this.baseUrl,
  });

  final ProductModel product;
  final CurrencyFormatter formatter;
  final VoidCallback? onTap;
  final String? baseUrl;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: SizedBox.expand(
                    child: AppNetworkImage(
                      imageUrl: product.imageUrl,
                      baseUrl: baseUrl,
                      fit: BoxFit.cover,
                      fallbackIcon: Icons.inventory_2_outlined,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                product.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
              ),
              const SizedBox(height: 4),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    formatter.format(product.salePrice),
                    style: TextStyle(color: theme.colorScheme.primary, fontWeight: FontWeight.bold, fontSize: 13),
                  ),
                  if (product.isOutOfStock)
                    Text('Out of stock', style: TextStyle(color: Colors.red.shade400, fontSize: 11))
                  else if (product.isLowStock)
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

/// Reusable product tile for catalog lists.
class SduiProductListTile extends StatelessWidget {
  const SduiProductListTile({
    super.key,
    required this.product,
    required this.formatter,
    required this.onTap,
    this.baseUrl,
  });

  final ProductModel product;
  final CurrencyFormatter formatter;
  final VoidCallback? onTap;
  final String? baseUrl;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: ListTile(
        leading: ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: SizedBox(
            width: 48,
            height: 48,
            child: AppNetworkImage(
              imageUrl: product.imageUrl,
              baseUrl: baseUrl,
              fit: BoxFit.cover,
              fallbackIcon: Icons.inventory_2_outlined,
            ),
          ),
        ),
        title: Text(product.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Row(
          children: [
            Text(formatter.format(product.salePrice), style: TextStyle(color: theme.colorScheme.primary, fontWeight: FontWeight.bold)),
            if (product.isOutOfStock) ...[
              const SizedBox(width: 8),
              Text('Out of stock', style: TextStyle(color: Colors.red.shade400, fontSize: 11)),
            ] else if (product.isLowStock) ...[
              const SizedBox(width: 8),
              Text('Low stock', style: TextStyle(color: Colors.orange.shade700, fontSize: 11)),
            ],
          ],
        ),
        trailing: IconButton(
          icon: const Icon(Icons.add_shopping_cart),
          onPressed: onTap,
        ),
        onTap: onTap,
      ),
    );
  }
}
