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
  const ProductPickerSheet({
    super.key,
    required this.inventoryRepository,
    this.partsOnly = false,
    this.excludeServices = false,
    this.allowedCategoryTypes,
    this.title,
  });

  final InventoryRepository inventoryRepository;
  final bool partsOnly;
  final bool excludeServices;
  final List<String>? allowedCategoryTypes;
  final String? title;

  @override
  State<ProductPickerSheet> createState() => _ProductPickerSheetState();
}

class _ProductPickerSheetState extends State<ProductPickerSheet> {
  late Future<InventoryCatalog> _future;
  String _query = '';
  String? _selectedCategoryId;
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
    final sheetTitle = widget.title ?? (widget.partsOnly ? 'Select Spare Part' : 'Select Product');
    final searchHint = widget.partsOnly ? 'Search spare parts, SKU, or barcode' : 'Search products, SKU, or barcode';

    return DraggableScrollableSheet(
      initialChildSize: 0.75,
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
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
              child: Row(
                children: [
                  Icon(
                    widget.partsOnly ? Icons.build_circle_outlined : Icons.inventory_2_outlined,
                    size: 20,
                    color: Theme.of(context).primaryColor,
                  ),
                  const SizedBox(width: 8),
                  Text(sheetTitle, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: TextField(
                decoration: InputDecoration(
                  hintText: searchHint,
                  prefixIcon: const Icon(Icons.search),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                ),
                onChanged: (value) => setState(() => _query = value.trim().toLowerCase()),
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

                  final allCategories = snapshot.data?.categories ?? [];
                  final availableCategories = allCategories.where((c) {
                    if (widget.partsOnly || widget.excludeServices) {
                      if (c.isSalon) return false;
                      final nameLower = c.name.toLowerCase();
                      if (nameLower.contains('hair') ||
                          nameLower.contains('styling') ||
                          nameLower.contains('facial') ||
                          nameLower.contains('spa') ||
                          nameLower.contains('massage')) {
                        return false;
                      }
                    }
                    if (widget.allowedCategoryTypes != null && widget.allowedCategoryTypes!.isNotEmpty) {
                      return widget.allowedCategoryTypes!.contains(c.type);
                    }
                    return true;
                  }).toList();

                  final products = (snapshot.data?.products ?? []).where((p) {
                    if (widget.partsOnly || widget.excludeServices) {
                      if (p.isService) return false;
                    }
                    if (widget.allowedCategoryTypes != null && widget.allowedCategoryTypes!.isNotEmpty) {
                      if (p.categoryType != null && !widget.allowedCategoryTypes!.contains(p.categoryType)) {
                        return false;
                      }
                    }
                    if (_selectedCategoryId != null && _selectedCategoryId!.isNotEmpty) {
                      if (p.categoryId != _selectedCategoryId && p.categoryName != _selectedCategoryId) {
                        return false;
                      }
                    }
                    if (_query.isNotEmpty) {
                      final matchesName = p.name.toLowerCase().contains(_query);
                      final matchesSku = p.sku.toLowerCase().contains(_query);
                      final matchesBarcode = p.barcode.toLowerCase().contains(_query);
                      final matchesCat = p.categoryName.toLowerCase().contains(_query);
                      if (!matchesName && !matchesSku && !matchesBarcode && !matchesCat) {
                        return false;
                      }
                    }
                    return true;
                  }).toList();

                  return Column(
                    children: [
                      if (availableCategories.isNotEmpty)
                        SizedBox(
                          height: 38,
                          child: ListView(
                            scrollDirection: Axis.horizontal,
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            children: [
                              Padding(
                                padding: const EdgeInsets.only(right: 8),
                                child: ChoiceChip(
                                  label: const Text('All'),
                                  selected: _selectedCategoryId == null,
                                  onSelected: (selected) {
                                    if (selected) setState(() => _selectedCategoryId = null);
                                  },
                                ),
                              ),
                              ...availableCategories.map(
                                (c) => Padding(
                                  padding: const EdgeInsets.only(right: 8),
                                  child: ChoiceChip(
                                    label: Text(c.name),
                                    selected: _selectedCategoryId == c.id,
                                    onSelected: (selected) {
                                      setState(() => _selectedCategoryId = selected ? c.id : null);
                                    },
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      const SizedBox(height: 8),
                      Expanded(
                        child: products.isEmpty
                            ? Center(
                                child: Padding(
                                  padding: const EdgeInsets.all(24),
                                  child: Text(
                                    widget.partsOnly
                                        ? 'No spare parts found. Salon services and non-part items are excluded.'
                                        : 'No products found.',
                                    textAlign: TextAlign.center,
                                    style: const TextStyle(color: Colors.grey),
                                  ),
                                ),
                              )
                            : ListView.builder(
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
                                            ? CircleAvatar(
                                                child: Icon(widget.partsOnly ? Icons.build_outlined : Icons.inventory_2_outlined),
                                              )
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
                                                errorWidget: (context, url, error) => CircleAvatar(
                                                  child: Icon(widget.partsOnly ? Icons.build_outlined : Icons.inventory_2_outlined),
                                                ),
                                              ),
                                      ),
                                    ),
                                    title: Text(product.name),
                                    subtitle: Text(
                                      '${product.salePrice.toStringAsFixed(2)} · Stock ${product.currentStock.toStringAsFixed(0)} · ${product.categoryName}',
                                    ),
                                    onTap: () => Navigator.of(context).pop(product),
                                  );
                                },
                              ),
                      ),
                    ],
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
