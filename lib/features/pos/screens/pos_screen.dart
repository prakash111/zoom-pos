import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/models/product_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../../customers/customers_repository.dart';
import '../../inventory/inventory_repository.dart';
import '../pos_provider.dart';
import '../sales_repository.dart';
import 'cart_sheet.dart';

/// Entry point for the POS module. Owns a [PosProvider] scoped to this route
/// so the cart resets whenever a fresh sale is started from the dashboard.
class PosScreen extends StatelessWidget {
  const PosScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final apiClient = context.read<ApiClient>();

    return ChangeNotifierProvider(
      create: (_) => PosProvider(
        inventoryRepository: InventoryRepository(apiClient),
        salesRepository: SalesRepository(apiClient),
      )..loadCatalog(),
      child: const _PosScreenBody(),
    );
  }
}

class _PosScreenBody extends StatefulWidget {
  const _PosScreenBody();

  @override
  State<_PosScreenBody> createState() => _PosScreenBodyState();
}

class _PosScreenBodyState extends State<_PosScreenBody> {
  final _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _openCart(BuildContext context) {
    final posProvider = context.read<PosProvider>();
    final customersRepository = CustomersRepository(context.read<ApiClient>());

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ChangeNotifierProvider.value(
        value: posProvider,
        child: CartSheet(customersRepository: customersRepository),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final pos = context.watch<PosProvider>();
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    return Scaffold(
      appBar: AppBar(title: const Text('Point of Sale')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              onChanged: pos.setSearchQuery,
              decoration: InputDecoration(
                hintText: 'Search products, SKU, or barcode',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: pos.searchQuery.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchController.clear();
                          pos.setSearchQuery('');
                        },
                      ),
              ),
            ),
          ),
          if (pos.categories.isNotEmpty)
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
                      selected: pos.selectedCategoryId == null,
                      onSelected: (_) => pos.setCategory(null),
                    ),
                  ),
                  for (final category in pos.categories)
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        label: Text(category.name),
                        selected: pos.selectedCategoryId == category.id,
                        onSelected: (_) => pos.setCategory(category.id),
                      ),
                    ),
                ],
              ),
            ),
          const SizedBox(height: 8),
          Expanded(child: _buildBody(pos, formatter)),
        ],
      ),
      bottomNavigationBar: pos.cartIsEmpty
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: ElevatedButton(
                  onPressed: () => _openCart(context),
                  child: Text(
                    'View cart · ${pos.cartCount} item${pos.cartCount == 1 ? '' : 's'} · ${formatter.format(pos.subtotal)}',
                  ),
                ),
              ),
            ),
    );
  }

  Widget _buildBody(PosProvider pos, CurrencyFormatter formatter) {
    switch (pos.catalogStatus) {
      case CatalogStatus.loading:
        return const LoadingIndicator();
      case CatalogStatus.error:
        return ErrorView(message: pos.catalogError ?? 'Could not load products.', onRetry: pos.loadCatalog);
      case CatalogStatus.loaded:
        final products = pos.filteredProducts;
        if (products.isEmpty) {
          return const Center(child: Text('No products found.'));
        }
        return GridView.builder(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: 0.85,
          ),
          itemCount: products.length,
          itemBuilder: (context, index) {
            final product = products[index];
            return _ProductCard(
              product: product,
              formatter: formatter,
              onTap: product.isOutOfStock ? null : () => pos.addToCart(product),
            );
          },
        );
    }
  }
}

class _ProductCard extends StatelessWidget {
  const _ProductCard({required this.product, required this.formatter, required this.onTap});

  final ProductModel product;
  final CurrencyFormatter formatter;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Center(
                  child: Icon(Icons.inventory_2_outlined, size: 36, color: Colors.grey.shade400),
                ),
              ),
              Text(
                product.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 4),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    formatter.format(product.salePrice),
                    style: TextStyle(color: Theme.of(context).colorScheme.primary, fontWeight: FontWeight.bold),
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
