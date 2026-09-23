import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/models/product_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/utils/responsive.dart';
import '../../../core/widgets/adaptive_sheet.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../../core/widgets/responsive/desktop_content_area.dart';
import '../../auth/auth_provider.dart';
import '../inventory_provider.dart';
import '../inventory_repository.dart';
import 'adjust_stock_sheet.dart';
import 'bulk_import_screen.dart';
import 'product_form_sheet.dart';

/// Product catalog management: list/search/filter, create, edit, and stock
/// adjustment. Owns an [InventoryProvider] scoped to this route.
class InventoryScreen extends StatelessWidget {
  const InventoryScreen({super.key, this.initialFilter});

  final String? initialFilter;

  @override
  Widget build(BuildContext context) {
    final apiClient = context.read<ApiClient>();

    return ChangeNotifierProvider(
      create: (_) {
        final provider =
            InventoryProvider(repository: InventoryRepository(apiClient))
              ..loadCatalog();
        if (initialFilter == 'low_stock') {
          provider.setFilterLowStock(true);
        }
        return provider;
      },
      child: _InventoryScreenBody(initialFilter: initialFilter),
    );
  }
}

class _InventoryScreenBody extends StatefulWidget {
  const _InventoryScreenBody({this.initialFilter});

  final String? initialFilter;

  @override
  State<_InventoryScreenBody> createState() => _InventoryScreenBodyState();
}

class _InventoryScreenBodyState extends State<_InventoryScreenBody> {
  final _searchController = TextEditingController();
  bool _initializedArgs = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_initializedArgs) {
      _initializedArgs = true;
      final args = ModalRoute.of(context)?.settings.arguments;
      if (args is Map &&
          (args['filter'] == 'low_stock' || widget.initialFilter == 'low_stock')) {
        context.read<InventoryProvider>().setFilterLowStock(true);
      }
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _openProductForm(BuildContext context, {ProductModel? product}) {
    final inventory = context.read<InventoryProvider>();

    showAdaptiveSheet(
      context,
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

    showAdaptiveSheet(
      context,
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
      body: DesktopContentArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              child: DesktopBoundedField(
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
            if (inventory.filterLowStock)
              Align(
                alignment: Alignment.centerLeft,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 4),
                  child: InputChip(
                    avatar: const Icon(Icons.warning_amber_rounded,
                        size: 16, color: Color(0xFFF59E0B)),
                    label: const Text('Filtered by Low Stock'),
                    onDeleted: () => inventory.setFilterLowStock(false),
                  ),
                ),
              ),
            const SizedBox(height: 8),
            Expanded(child: _buildBody(context, inventory, formatter)),
          ],
        ),
      ),
    );
  }

  Widget _buildBody(BuildContext context, InventoryProvider inventory,
      CurrencyFormatter formatter) {
    switch (inventory.status) {
      case CatalogStatus.loading:
        return const LoadingIndicator();
      case CatalogStatus.error:
        return ErrorView(
            message: inventory.error ?? 'Could not load products.',
            onRetry: inventory.loadCatalog);
      case CatalogStatus.loaded:
        final products = inventory.filteredProducts;
        if (products.isEmpty) {
          return const Center(child: Text('No products found.'));
        }
        if (MediaQuery.sizeOf(context).width >= Breakpoints.desktop) {
          return _DesktopProductsTable(
            products: products,
            formatter: formatter,
            onTap: (p) => _openProductForm(context, product: p),
            onAdjustStock: (p) => _openAdjustStock(context, p),
            onDelete: (p) => _deleteProduct(context, p),
          );
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
              onDelete: () => _deleteProduct(context, product),
            );
          },
        );
    }
  }

  Future<void> _deleteProduct(
      BuildContext context, ProductModel product) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Delete ${product.name}?'),
        content: const Text(
          'This will archive/remove the item from the active POS catalog. Past sales references will not be affected.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    if (!context.mounted) return;
    final inventory = context.read<InventoryProvider>();
    final success = await inventory.deleteProduct(product.id);
    if (!context.mounted) return;
    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Product deleted successfully.')),
      );
    } else if (inventory.actionError != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(inventory.actionError!)),
      );
    }
  }
}

/// Desktop composition for the product catalog: Product | SKU | Stock |
/// Price | Actions — falls back to [_ProductTile] cards below the desktop
/// breakpoint.
class _DesktopProductsTable extends StatelessWidget {
  const _DesktopProductsTable({
    required this.products,
    required this.formatter,
    required this.onTap,
    required this.onAdjustStock,
    required this.onDelete,
  });

  final List<ProductModel> products;
  final CurrencyFormatter formatter;
  final void Function(ProductModel) onTap;
  final void Function(ProductModel) onAdjustStock;
  final void Function(ProductModel) onDelete;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final headerStyle = TextStyle(
        fontWeight: FontWeight.w600,
        fontSize: 12.5,
        color: scheme.onSurfaceVariant);

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(0, 4, 0, 80),
      itemCount: products.length + 1,
      itemBuilder: (context, index) {
        if (index == 0) {
          return Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
                border:
                    Border(bottom: BorderSide(color: scheme.outlineVariant))),
            child: Row(
              children: [
                Expanded(flex: 4, child: Text('PRODUCT', style: headerStyle)),
                Expanded(flex: 2, child: Text('SKU', style: headerStyle)),
                Expanded(flex: 2, child: Text('STOCK', style: headerStyle)),
                Expanded(
                    flex: 2,
                    child: Text('PRICE',
                        textAlign: TextAlign.end, style: headerStyle)),
                const SizedBox(width: 40),
              ],
            ),
          );
        }

        final product = products[index - 1];
        final stockColor = product.isOutOfStock
            ? Colors.red.shade400
            : product.isLowStock
                ? Colors.orange.shade700
                : scheme.onSurfaceVariant;

        return InkWell(
          onTap: () => onTap(product),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
              border: Border(
                  bottom: BorderSide(
                      color: scheme.outlineVariant.withValues(alpha: 0.5))),
            ),
            child: Row(
              children: [
                Expanded(
                  flex: 4,
                  child: Text(product.name,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                          fontWeight: FontWeight.w600, fontSize: 13.5)),
                ),
                Expanded(
                  flex: 2,
                  child: Text(product.sku.isEmpty ? '—' : product.sku,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                          fontSize: 13, color: scheme.onSurfaceVariant)),
                ),
                Expanded(
                  flex: 2,
                  child: InkWell(
                    onTap: () => onAdjustStock(product),
                    child: Text(
                      '${product.currentStock.toStringAsFixed(0)} ${product.unit}',
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                          fontWeight: FontWeight.w600,
                          fontSize: 13,
                          color: stockColor),
                    ),
                  ),
                ),
                Expanded(
                  flex: 2,
                  child: Text(
                    formatter.format(product.salePrice),
                    textAlign: TextAlign.end,
                    style: TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 13.5,
                        color: scheme.primary),
                  ),
                ),
                SizedBox(
                  width: 40,
                  child: PopupMenuButton<String>(
                    icon: Icon(Icons.more_vert,
                        size: 18, color: scheme.onSurfaceVariant),
                    padding: EdgeInsets.zero,
                    onSelected: (action) {
                      if (action == 'edit') onTap(product);
                      if (action == 'adjust') onAdjustStock(product);
                      if (action == 'delete') onDelete(product);
                    },
                    itemBuilder: (context) => const [
                      PopupMenuItem(value: 'edit', child: Text('Edit Product')),
                      PopupMenuItem(
                          value: 'adjust', child: Text('Adjust Stock')),
                      PopupMenuDivider(),
                      PopupMenuItem(
                          value: 'delete',
                          child: Text('Delete / Archive',
                              style: TextStyle(color: Colors.red))),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _ProductTile extends StatelessWidget {
  const _ProductTile({
    required this.product,
    required this.formatter,
    required this.onTap,
    required this.onAdjustStock,
    required this.onDelete,
  });

  final ProductModel product;
  final CurrencyFormatter formatter;
  final VoidCallback onTap;
  final VoidCallback onAdjustStock;
  final VoidCallback onDelete;

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
                    Text(product.name,
                        style: const TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 2),
                    Text(
                      [
                        if (product.sku.isNotEmpty) 'SKU: ${product.sku}',
                        if (product.barcode.isNotEmpty) product.barcode,
                      ].join(' · '),
                      style:
                          TextStyle(color: Colors.grey.shade600, fontSize: 12),
                    ),
                    const SizedBox(height: 4),
                    Text(formatter.format(product.salePrice),
                        style: TextStyle(
                            color: Theme.of(context).colorScheme.primary)),
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
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 4),
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
                  const SizedBox(height: 4),
                  PopupMenuButton<String>(
                    icon: const Icon(Icons.more_vert,
                        size: 18, color: Colors.grey),
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                    onSelected: (action) {
                      if (action == 'edit') {
                        onTap();
                      } else if (action == 'adjust') {
                        onAdjustStock();
                      } else if (action == 'delete') {
                        onDelete();
                      }
                    },
                    itemBuilder: (context) => [
                      const PopupMenuItem(
                        value: 'edit',
                        child: Row(
                          children: [
                            Icon(Icons.edit_outlined, size: 18),
                            SizedBox(width: 8),
                            Text('Edit Product'),
                          ],
                        ),
                      ),
                      const PopupMenuItem(
                        value: 'adjust',
                        child: Row(
                          children: [
                            Icon(Icons.tune_outlined, size: 18),
                            SizedBox(width: 8),
                            Text('Adjust Stock'),
                          ],
                        ),
                      ),
                      const PopupMenuDivider(),
                      const PopupMenuItem(
                        value: 'delete',
                        child: Row(
                          children: [
                            Icon(Icons.delete_outline,
                                size: 18, color: Colors.red),
                            SizedBox(width: 8),
                            Text('Delete / Archive',
                                style: TextStyle(color: Colors.red)),
                          ],
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
