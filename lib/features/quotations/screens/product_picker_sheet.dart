import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/models/product_model.dart';
import '../../../core/utils/image_url.dart';
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
  String? _baseUrl;

  @override
  void initState() {
    super.initState();
    _future = widget.inventoryRepository.fetchCatalog();
    context.read<ApiClient>().currentBaseUrl().then((url) {
      if (mounted) setState(() => _baseUrl = url);
    });
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
                      final resolvedImageUrl = resolveImageUrl(product.imageUrl, baseUrl: _baseUrl);
                      return ListTile(
                        leading: ClipOval(
                          child: SizedBox(
                            width: 40,
                            height: 40,
                            child: resolvedImageUrl == null
                                ? CircleAvatar(child: const Icon(Icons.inventory_2_outlined))
                                : CachedNetworkImage(
                                    imageUrl: resolvedImageUrl,
                                    fit: BoxFit.cover,
                                    placeholder: (context, url) => const Center(
                                      child: SizedBox(
                                        width: 14,
                                        height: 14,
                                        child: CircularProgressIndicator(strokeWidth: 2),
                                      ),
                                    ),
                                    errorWidget: (context, url, error) =>
                                        CircleAvatar(child: const Icon(Icons.inventory_2_outlined)),
                                  ),
                          ),
                        ),
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
