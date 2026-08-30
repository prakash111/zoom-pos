import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/category_model.dart';
import '../../core/models/product_model.dart';
import '../../core/models/settings_models.dart';

class InventoryCatalog {
  InventoryCatalog({
    required this.products,
    required this.categories,
    this.paymentMethods = const [],
  });

  final List<ProductModel> products;
  final List<CategoryModel> categories;
  final List<PaymentMethodModel> paymentMethods;
}

/// Talks to the inventory endpoints on PosSyncApiController: GET /inventory,
/// POST /inventory/product, and POST /inventory/adjust.
class InventoryRepository {
  InventoryRepository(this._client);

  final ApiClient _client;

  Future<InventoryCatalog> fetchCatalog() async {
    final response = await _client.get(ApiEndpoints.inventory);

    final products = (response['products'] as List? ?? [])
        .whereType<Map>()
        .map((e) => ProductModel.fromJson(Map<String, dynamic>.from(e)))
        .toList();
    final categories = (response['categories'] as List? ?? [])
        .whereType<Map>()
        .map((e) => CategoryModel.fromJson(Map<String, dynamic>.from(e)))
        .toList();
    final paymentMethods = (response['payment_methods'] as List? ?? [])
        .whereType<Map>()
        .map((e) => PaymentMethodModel.fromJson(Map<String, dynamic>.from(e)))
        .toList();

    return InventoryCatalog(
      products: products,
      categories: categories,
      paymentMethods: paymentMethods,
    );
  }

  /// Creates a new product, or updates an existing one when [externalId] is
  /// passed (the same `id` value products come back with from
  /// [fetchCatalog]). The response only echoes a partial product, so callers
  /// should re-fetch the catalog afterwards rather than trust it directly.
  Future<void> saveProduct({
    String? externalId,
    required String name,
    required double salePrice,
    double costPrice = 0,
    double currentStock = 0,
    double minimumStock = 0,
    String? barcode,
    String? sku,
    String unit = 'pcs',
    String categoryName = 'General',
    String? brandName,
    double taxRate = 0,
  }) {
    return _client.post(ApiEndpoints.inventoryStoreProduct, data: {
      if (externalId != null) 'external_id': externalId,
      'name': name,
      'sale_price': salePrice,
      'cost_price': costPrice,
      'current_stock': currentStock,
      'minimum_stock': minimumStock,
      if (barcode != null && barcode.isNotEmpty) 'barcode': barcode,
      if (sku != null && sku.isNotEmpty) 'sku': sku,
      'unit': unit,
      'category_name': categoryName,
      if (brandName != null && brandName.isNotEmpty) 'brand_name': brandName,
      'tax_rate': taxRate,
    });
  }

  /// [type] is one of 'add', 'subtract', or 'set'.
  Future<void> adjustStock({
    required String productId,
    required String type,
    required double quantity,
    String? reason,
  }) {
    return _client.post(ApiEndpoints.inventoryAdjustStock, data: {
      'product_id': productId,
      'type': type,
      'quantity': quantity,
      if (reason != null && reason.isNotEmpty) 'reason': reason,
    });
  }
}
