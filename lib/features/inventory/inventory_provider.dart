import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/models/category_model.dart';
import '../../core/models/product_model.dart';
import 'inventory_repository.dart';

enum CatalogStatus { loading, loaded, error }

/// Drives the inventory screen: the product catalog, search/category
/// filtering, and product create/edit/stock-adjust actions. Every mutation
/// re-fetches the catalog afterwards rather than patching local state, since
/// the save/adjust endpoints only echo back a partial product.
class InventoryProvider extends ChangeNotifier {
  InventoryProvider({required InventoryRepository repository}) : _repository = repository;

  final InventoryRepository _repository;

  CatalogStatus status = CatalogStatus.loading;
  String? error;
  List<ProductModel> _products = [];
  List<CategoryModel> categories = [];

  String searchQuery = '';
  String? selectedCategoryId;

  bool isSaving = false;
  String? actionError;

  List<ProductModel> get filteredProducts {
    final query = searchQuery.trim().toLowerCase();
    return _products.where((p) {
      if (selectedCategoryId != null && p.categoryId != selectedCategoryId) return false;
      if (query.isEmpty) return true;
      return p.name.toLowerCase().contains(query) ||
          p.sku.toLowerCase().contains(query) ||
          p.barcode.toLowerCase().contains(query);
    }).toList();
  }

  Future<void> loadCatalog() async {
    status = CatalogStatus.loading;
    notifyListeners();

    try {
      final catalog = await _repository.fetchCatalog();
      _products = catalog.products;
      categories = catalog.categories;
      status = CatalogStatus.loaded;
    } on ApiException catch (e) {
      error = e.message;
      status = CatalogStatus.error;
    }
    notifyListeners();
  }

  void setSearchQuery(String value) {
    searchQuery = value;
    notifyListeners();
  }

  void setCategory(String? categoryId) {
    selectedCategoryId = categoryId;
    notifyListeners();
  }

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
    isSaving = true;
    actionError = null;
    notifyListeners();

    try {
      final id = await _repository.saveProduct(
        externalId: externalId,
        name: name,
        salePrice: salePrice,
        costPrice: costPrice,
        currentStock: currentStock,
        minimumStock: minimumStock,
        barcode: barcode,
        sku: sku,
        unit: unit,
        categoryName: categoryName,
        brandName: brandName,
        taxRate: taxRate,
        imageUrl: imageUrl,
      );
      await loadCatalog();
      isSaving = false;
      notifyListeners();
      return id;
    } on ApiException catch (e) {
      actionError = e.message;
      isSaving = false;
      notifyListeners();
      return null;
    }
  }

  Future<bool> adjustStock({
    required String productId,
    required String type,
    required double quantity,
    String? reason,
  }) {
    return _runAction(() => _repository.adjustStock(
          productId: productId,
          type: type,
          quantity: quantity,
          reason: reason,
        ));
  }

  Future<bool> deleteProduct(String productId) {
    return _runAction(() => _repository.deleteProduct(productId));
  }

  Future<bool> _runAction(Future<void> Function() action) async {
    isSaving = true;
    actionError = null;
    notifyListeners();

    try {
      await action();
      await loadCatalog();
      isSaving = false;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      actionError = e.message;
      isSaving = false;
      notifyListeners();
      return false;
    }
  }
}
