import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/models/product_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../inventory_provider.dart';
import '../inventory_repository.dart';
import 'adjust_stock_sheet.dart';
import 'bulk_import_screen.dart';
import 'product_form_sheet.dart';

/// Product catalog management: list/search/filter, create, edit, and stock
/// adjustment. Owns an [InventoryProvider] scoped to this route.
class InventoryScreen extends StatelessWidget {
  const InventoryScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final apiClient = context.read<ApiClient>();

    return ChangeNotifierProvider(
      create: (_) => InventoryProvider(repository: InventoryRepository(apiClient))..loadCatalog(),
      child: const _InventoryScreenBody(),
    );
  }
}

class _InventoryScreenBody extends StatefulWidget {
  const _InventoryScreenBody();

  @override
  State<_InventoryScreenBody> createState() => _InventoryScreenBodyState();
}

class _InventoryScreenBodyState extends State<_InventoryScreenBody> {
  final _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _openProductForm(BuildContext context, {ProductModel? product}) {
    final inventory = context.read<InventoryProvider>();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ChangeNotifierProvider.value(
        value: inventory,
        child: ProductFormSheet(product: product),
      ),
    );
  }

  Future<void> _openBulkImport(BuildContext context) async {
    final inventory = context.read<InventoryProvider>();
    final imported = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const BulkImportScreen()),
    );
    if (imported == true) inventory.loadCatalog();
  }

  void _openAdjustStock(BuildContext context, ProductModel product) {
    final inventory = context.read<InventoryProvider>();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ChangeNotifierProvider.value(
        value: inventory,
        child: AdjustStockSheet(product: product),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final inventory = context.watch<InventoryProvider>();
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    return Scaffold(
      appBar: AppBar(
        title: const Text('Inventory'),
        actions: [
          IconButton(
            tooltip: 'Bulk import',
            icon: const Icon(Icons.upload_file_outlined),
            onPressed: () => _openBulkImport(context),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _openProductForm(context),
        child: const Icon(Icons.add),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              onChanged: inventory.setSearchQuery,
              decoration: InputDecoration(
                hintText: 'Search products, SKU, or barcode',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: inventory.searchQuery.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchController.clear();
                          inventory.setSearchQuery('');
                        },
                      ),
              ),
            ),
          ),
          if (inventory.categories.isNotEmpty)
            SizedBox(
              height: 40,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                children: [
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: const Text('All'),
                      selected: inventory.selectedCategoryId == null,
                      onSelected: (_) => inventory.setCategory(null),
                    ),
                  ),
                  for (final category in inventory.categories)
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        label: Text(category.name),
                        selected: inventory.selectedCategoryId == category.id,
                        onSelected: (_) => inventory.setCategory(category.id),
                      ),
                    ),
                ],
              ),
            ),
          const SizedBox(height: 8),
          Expanded(child: _buildBody(context, inventory, formatter)),
        ],
      ),
    );
  }

  Widget _buildBody(BuildContext context, InventoryProvider inventory, CurrencyFormatter formatter) {
    switch (inventory.status) {
      case CatalogStatus.loading:
        return const LoadingIndicator();
      case CatalogStatus.error:
        return ErrorView(message: inventory.error ?? 'Could not load products.', onRetry: inventory.loadCatalog);
      case CatalogStatus.loaded:
        final products = inventory.filteredProducts;
        if (products.isEmpty) {
          return const Center(child: Text('No products found.'));
        }
        return ListView.separated(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 80),
          itemCount: products.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (context, index) {
            final product = products[index];
            return _ProductTile(
              product: product,
              formatter: formatter,
              onTap: () => _openProductForm(context, product: product),
              onAdjustStock: () => _openAdjustStock(context, product),
            );
          },
        );
    }
  }
}

class _ProductTile extends StatelessWidget {
  const _ProductTile({
    required this.product,
    required this.formatter,
    required this.onTap,
    required this.onAdjustStock,
  });

  final ProductModel product;
  final CurrencyFormatter formatter;
  final VoidCallback onTap;
  final VoidCallback onAdjustStock;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(product.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 2),
                    Text(
                      [
                        if (product.sku.isNotEmpty) 'SKU: ${product.sku}',
                        if (product.barcode.isNotEmpty) product.barcode,
                      ].join(' · '),
                      style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                    ),
                    const SizedBox(height: 4),
                    Text(formatter.format(product.salePrice), style: TextStyle(color: Theme.of(context).colorScheme.primary)),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  InkWell(
                    borderRadius: BorderRadius.circular(8),
                    onTap: onAdjustStock,
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      child: Row(
                        children: [
                          Icon(
                            Icons.inventory_2_outlined,
                            size: 16,
                            color: product.isOutOfStock
                                ? Colors.red.shade400
                                : product.isLowStock
                                    ? Colors.orange.shade700
                                    : Colors.grey.shade600,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            '${product.currentStock.toStringAsFixed(0)} ${product.unit}',
                            style: TextStyle(
                              fontWeight: FontWeight.w600,
                              color: product.isOutOfStock
                                  ? Colors.red.shade400
                                  : product.isLowStock
                                      ? Colors.orange.shade700
                                      : null,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const Icon(Icons.chevron_right, size: 18, color: Colors.grey),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
