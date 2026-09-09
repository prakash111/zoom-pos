import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/config/app_config.dart';
import '../../core/models/category_model.dart';
import '../../core/models/product_model.dart';
import '../../core/models/settings_models.dart';
import '../../core/services/sync/offline_writeable.dart';
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
class InventoryRepository with OfflineWriteable {
  InventoryRepository(this._client, {AppDatabase? database})
      : _database = database ?? AppDatabase.instance;

  final ApiClient _client;
  final AppDatabase _database;

  @override
  AppDatabase get offlineDb => _database;

  /// Fetches the live catalog and refreshes the offline cache, or — if the
  /// request fails (no connectivity, server unreachable) and a cache exists
  /// from a previous successful fetch — falls back to that cache so the POS
  /// screen keeps working offline instead of showing an error.
  Future<InventoryCatalog> fetchCatalog() async {
    try {
      final response = await _client.get(ApiEndpoints.inventory).timeout(
            AppConfig.catalogFetchTimeout,
            onTimeout: () => throw ApiException('Loading products timed out. Check your connection and try again.'),
          );

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
    final isCreate = externalId == null;
    final ext = externalId ?? newExternalId();

    final body = <String, dynamic>{
      // On an edit, let the server match its own row by id first (the cached
      // id may be a numeric server id for a web-created product); still send
      // external_id so an offline replay agrees on identity.
      if (!isCreate) 'id': ext,
      'external_id': ext,
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
    };

    final cachedRow =
        isCreate ? null : await _database.readCacheItem('products', ext);
    final baseUpdatedAt = DateTime.tryParse(
        cachedRow?['updated_at']?.toString() ?? '');

    // Shape matches ProductModel.fromJson so the POS grid can render an
    // offline-created/edited product straight from cache.
    final optimisticRow = <String, dynamic>{
      'id': ext,
      'name': name,
      'barcode': barcode ?? '',
      'sku': sku ?? '',
      'sale_price': salePrice,
      'cost_price': costPrice,
      'current_stock': currentStock,
      'minimum_stock': minimumStock,
      'unit': unit,
      'category_name': categoryName,
      'brand_name': brandName ?? '',
      if (imageUrl != null) 'image_url': imageUrl,
      'tax_rate': taxRate,
      'active': true,
      'updated_at': DateTime.now().toUtc().toIso8601String(),
    };

    return writeThrough<String?>(
      op: isCreate ? 'create' : 'update',
      entity: 'product',
      externalId: ext,
      endpoint: ApiEndpoints.inventoryStoreProduct,
      payload: body,
      cacheBucket: 'products',
      optimisticRow: optimisticRow,
      baseUpdatedAt: baseUpdatedAt,
      online: () async {
        final response =
            await _client.post(ApiEndpoints.inventoryStoreProduct, data: body);
        final product = response['product'];
        if (product is Map && product['id'] != null) {
          return product['id'].toString();
        }
        return ext;
      },
      offlineResult: () => ext,
    );
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
  }) async {
    final adjustmentId = newExternalId();
    final body = <String, dynamic>{
      'product_id': productId,
      'type': type,
      'quantity': quantity,
      if (reason != null && reason.isNotEmpty) 'reason': reason,
    };

    await writeThrough<void>(
      op: 'create',
      entity: 'adjustment',
      externalId: adjustmentId,
      endpoint: ApiEndpoints.inventoryAdjustStock,
      payload: body,
      // The optimistic write here targets a *different* row (the product,
      // keyed by productId) than the mutation's own externalId, so it's
      // applied by hand rather than through writeThrough's cacheBucket.
      online: () => _client.post(ApiEndpoints.inventoryAdjustStock, data: body),
      offlineResult: () async {
        final cached = await _database.readCacheItem('products', productId);
        if (cached == null) return;
        final current = (cached['current_stock'] as num?)?.toDouble() ?? 0;
        final next = switch (type) {
          'add' => current + quantity,
          'subtract' => current - quantity,
          'set' => quantity,
          _ => current,
        };
        await _database.upsertCacheItems(
            'products', [{...cached, 'current_stock': next}]);
      },
    );
  }

  Future<void> deleteProduct(String id) {
    return writeThrough<void>(
      op: 'delete',
      entity: 'product',
      externalId: id,
      endpoint: ApiEndpoints.inventoryDeleteProduct(id),
      method: 'DELETE',
      payload: const {},
      cacheBucket: 'products',
      online: () => _client.delete(ApiEndpoints.inventoryDeleteProduct(id)),
      offlineResult: () {},
    );
  }
}
