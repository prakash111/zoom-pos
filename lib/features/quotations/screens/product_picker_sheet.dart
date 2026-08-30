import 'package:flutter/material.dart';

import '../../../core/models/product_model.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../inventory/inventory_repository.dart';

/// Pops with the selected [ProductModel], or null if dismissed.
class ProductPickerSheet extends StatefulWidget {
  const ProductPickerSheet({super.key, required this.inventoryRepository});

  final InventoryRepository inventoryRepository;

  @override
  State<ProductPickerSheet> createState() => _ProductPickerSheetState();
}

class _ProductPickerSheetState extends State<ProductPickerSheet> {
  late Future<InventoryCatalog> _future;
  String _query = '';

  @override
  void initState() {
    super.initState();
    _future = widget.inventoryRepository.fetchCatalog();
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.7,
      minChildSize: 0.4,
      maxChildSize: 0.95,
      expand: false,
      builder: (context, scrollController) {
        return Column(
          children: [
            const SizedBox(height: 12),
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2)),
            ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: TextField(
                decoration: const InputDecoration(hintText: 'Search products', prefixIcon: Icon(Icons.search)),
                onChanged: (value) => setState(() => _query = value.toLowerCase()),
              ),
            ),
            Expanded(
              child: FutureBuilder<InventoryCatalog>(
                future: _future,
                builder: (context, snapshot) {
                  if (snapshot.connectionState != ConnectionState.done) {
                    return const LoadingIndicator();
                  }
                  if (snapshot.hasError) {
                    return ErrorView(
                      message: 'Could not load products.',
                      onRetry: () => setState(() => _future = widget.inventoryRepository.fetchCatalog()),
                    );
                  }

                  final products = (snapshot.data?.products ?? [])
                      .where((p) => _query.isEmpty || p.name.toLowerCase().contains(_query))
                      .toList();

                  if (products.isEmpty) {
                    return const Center(child: Text('No products found.'));
                  }

                  return ListView.builder(
                    controller: scrollController,
                    itemCount: products.length,
                    itemBuilder: (context, index) {
                      final product = products[index];
                      return ListTile(
                        leading: const CircleAvatar(child: Icon(Icons.inventory_2_outlined)),
                        title: Text(product.name),
                        subtitle: Text('${product.salePrice.toStringAsFixed(2)} · Stock ${product.currentStock}'),
                        onTap: () => Navigator.of(context).pop(product),
                      );
                    },
                  );
                },
              ),
            ),
          ],
        );
      },
    );
  }
}
