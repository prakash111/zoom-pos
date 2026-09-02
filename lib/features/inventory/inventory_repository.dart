import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/config/app_config.dart';
import '../../core/models/category_model.dart';
import '../../core/models/product_model.dart';
import '../../core/models/settings_models.dart';
import '../../core/storage/app_database.dart';

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
  InventoryRepository(this._client, {AppDatabase? database}) : _database = database ?? AppDatabase.instance;

  final ApiClient _client;
  final AppDatabase _database;

  /// Fetches the live catalog and refreshes the offline cache, or — if the
  /// request fails (no connectivity, server unreachable) and a cache exists
  /// from a previous successful fetch — falls back to that cache so the POS
  /// screen keeps working offline instead of showing an error.
  Future<InventoryCatalog> fetchCatalog() async {
    try {
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

      await _database.replaceCacheBucket('products', products.map((p) => p.toJson()).toList());
      await _database.replaceCacheBucket('categories', categories.map((c) => c.toJson()).toList());
      await _database.replaceCacheBucket('payment_methods', paymentMethods.map((p) => p.toJson()).toList());

      return InventoryCatalog(
        products: products,
        categories: categories,
        paymentMethods: paymentMethods,
      );
    } on ApiException {
      final cachedProducts = await _database.readCacheBucket('products');
      if (cachedProducts.isEmpty) rethrow;

      final cachedCategories = await _database.readCacheBucket('categories');
      final cachedPaymentMethods = await _database.readCacheBucket('payment_methods');

      return InventoryCatalog(
        products: cachedProducts.map(ProductModel.fromJson).toList(),
        categories: cachedCategories.map(CategoryModel.fromJson).toList(),
        paymentMethods: cachedPaymentMethods.map(PaymentMethodModel.fromJson).toList(),
      );
    }
  }

  /// Creates a new product, or updates an existing one when [externalId] is
  /// passed (the same `id` value products come back with from
  /// [fetchCatalog]). Returns the product's id (from the response for a
  /// create, or [externalId] itself for an edit) — the response only echoes
  /// a partial product otherwise, so callers should re-fetch the catalog
  /// afterwards rather than trust it directly for anything but this id.
  Future<String?> saveProduct({
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
    String? imageUrl,
  }) async {
    final response = await _client.post(ApiEndpoints.inventoryStoreProduct, data: {
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
      if (imageUrl != null && imageUrl.isNotEmpty) 'image_url': imageUrl,
    });
    final product = response['product'];
    if (product is Map && product['id'] != null) return product['id'].toString();
    return externalId;
  }

  /// Uploads or replaces a product's image. [productId] is the same id
  /// [saveProduct] returns (server id or external_id — the endpoint accepts
  /// either).
  Future<String?> uploadProductImage(String productId, List<int> bytes, String filename) async {
    final response = await _client.postMultipart(
      ApiEndpoints.inventoryProductImage(productId),
      fieldName: 'image',
      bytes: bytes,
      filename: filename,
    );
    return response['image_url'] as String?;
  }

  /// Bulk-creates products from a `.csv`/`.txt` file (columns: name,
  /// category, item_code, barcode, cost_price, sale_price, stock). Returns
  /// the number of products imported.
  Future<int> bulkImport(List<int> bytes, String filename) async {
    final response = await _client.postMultipart(
      ApiEndpoints.inventoryImport,
      fieldName: 'file',
      bytes: bytes,
      filename: filename,
    );
    return (response['imported'] as num?)?.toInt() ?? 0;
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
