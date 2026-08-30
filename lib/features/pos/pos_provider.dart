import 'package:flutter/foundation.dart';
import 'package:uuid/uuid.dart';

import '../../core/api/api_exception.dart';
import '../../core/models/category_model.dart';
import '../../core/models/customer_model.dart';
import '../../core/models/product_model.dart';
import '../inventory/inventory_repository.dart';
import 'cart_item.dart';
import 'sales_repository.dart';

enum CatalogStatus { loading, loaded, error }

/// Drives the point-of-sale screen: loads the product catalog, filters it by
/// search/category, holds the in-memory cart, and submits the sale through
/// [SalesRepository] on checkout.
class PosProvider extends ChangeNotifier {
  PosProvider({
    required InventoryRepository inventoryRepository,
    required SalesRepository salesRepository,
  })  : _inventoryRepository = inventoryRepository,
        _salesRepository = salesRepository;

  final InventoryRepository _inventoryRepository;
  final SalesRepository _salesRepository;
  static final Uuid _uuid = Uuid();

  CatalogStatus catalogStatus = CatalogStatus.loading;
  String? catalogError;
  List<ProductModel> _products = [];
  List<CategoryModel> categories = [];

  String searchQuery = '';
  String? selectedCategoryId;

  final Map<String, CartItem> _cart = {};
  CustomerModel? selectedCustomer;
  String paymentMethod = 'cash';
  bool isCheckingOut = false;
  String? checkoutError;

  List<ProductModel> get filteredProducts {
    final query = searchQuery.trim().toLowerCase();
    return _products.where((p) {
      if (!p.active) return false;
      if (selectedCategoryId != null && p.categoryId != selectedCategoryId) return false;
      if (query.isEmpty) return true;
      return p.name.toLowerCase().contains(query) ||
          p.sku.toLowerCase().contains(query) ||
          p.barcode.toLowerCase().contains(query);
    }).toList();
  }

  List<CartItem> get cartItems => _cart.values.toList();
  int get cartCount => _cart.values.fold<int>(0, (sum, item) => sum + item.quantity.ceil());
  double get subtotal => _cart.values.fold<double>(0, (sum, item) => sum + item.lineTotal);
  bool get cartIsEmpty => _cart.isEmpty;

  Future<void> loadCatalog() async {
    catalogStatus = CatalogStatus.loading;
    notifyListeners();

    try {
      final catalog = await _inventoryRepository.fetchCatalog();
      _products = catalog.products;
      categories = catalog.categories;
      catalogStatus = CatalogStatus.loaded;
    } on ApiException catch (e) {
      catalogError = e.message;
      catalogStatus = CatalogStatus.error;
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

  void addToCart(ProductModel product) {
    final existing = _cart[product.id];
    if (existing != null) {
      existing.quantity += 1;
    } else {
      _cart[product.id] = CartItem(product: product);
    }
    notifyListeners();
  }

  void incrementQuantity(String productId) {
    final item = _cart[productId];
    if (item == null) return;
    item.quantity += 1;
    notifyListeners();
  }

  void decrementQuantity(String productId) {
    final item = _cart[productId];
    if (item == null) return;
    if (item.quantity <= 1) {
      _cart.remove(productId);
    } else {
      item.quantity -= 1;
    }
    notifyListeners();
  }

  void removeFromCart(String productId) {
    _cart.remove(productId);
    notifyListeners();
  }

  void setCustomer(CustomerModel? customer) {
    selectedCustomer = customer;
    notifyListeners();
  }

  void setPaymentMethod(String method) {
    paymentMethod = method;
    notifyListeners();
  }

  void clearCart() {
    _cart.clear();
    selectedCustomer = null;
    paymentMethod = 'cash';
    notifyListeners();
  }

  /// Records the current cart as a completed sale. The cart (and selected
  /// customer/payment method) is only cleared once the server accepts it.
  Future<bool> checkout() async {
    if (_cart.isEmpty) return false;

    isCheckingOut = true;
    checkoutError = null;
    notifyListeners();

    try {
      await _salesRepository.pushSale(
        id: _uuid.v4(),
        total: subtotal,
        discount: 0,
        paymentMethod: paymentMethod,
        customerId: selectedCustomer?.id,
        customerName: selectedCustomer?.name,
        items: _cart.values
            .map((item) => {
                  'id': item.product.id,
                  'product_id': item.product.id,
                  'name': item.product.name,
                  'price': item.product.salePrice,
                  'quantity': item.quantity,
                })
            .toList(),
      );
      clearCart();
      isCheckingOut = false;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      checkoutError = e.message;
      isCheckingOut = false;
      notifyListeners();
      return false;
    }
  }
}
