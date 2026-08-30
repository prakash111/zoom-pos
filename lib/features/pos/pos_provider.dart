import 'package:flutter/foundation.dart';
import 'package:uuid/uuid.dart';

import '../../core/api/api_exception.dart';
import '../../core/models/category_model.dart';
import '../../core/models/customer_model.dart';
import '../../core/models/product_model.dart';
import '../cash_register/cash_register_repository.dart';
import '../inventory/inventory_repository.dart';
import 'cart_item.dart';
import 'sales_repository.dart';

enum CatalogStatus { loading, loaded, error }

/// Snapshot of a just-completed sale, handed back to the UI so it can open
/// the post-checkout invoice actions sheet — by the time [PosProvider.checkout]
/// returns, the cart itself has already been cleared.
class PosCheckoutResult {
  PosCheckoutResult({
    required this.saleId,
    required this.saleNumber,
    required this.items,
    required this.subtotal,
    required this.discount,
    required this.tax,
    required this.total,
    this.customerName,
  });

  final String saleId;
  final String saleNumber;
  final List<CartItem> items;
  final double subtotal;
  final double discount;
  final double tax;
  final double total;
  final String? customerName;
}

/// Drives the point-of-sale screen: loads the product catalog, filters it by
/// search/category, holds the in-memory cart, and submits the sale through
/// [SalesRepository] on checkout.
class PosProvider extends ChangeNotifier {
  PosProvider({
    required InventoryRepository inventoryRepository,
    required SalesRepository salesRepository,
    required CashRegisterRepository cashRegisterRepository,
  })  : _inventoryRepository = inventoryRepository,
        _salesRepository = salesRepository,
        _cashRegisterRepository = cashRegisterRepository;

  final InventoryRepository _inventoryRepository;
  final SalesRepository _salesRepository;
  final CashRegisterRepository _cashRegisterRepository;
  static final Uuid _uuid = Uuid();

  /// Null until the first [checkRegisterStatus] call resolves — the register
  /// banner and checkout gate stay hidden/permissive until then so a slow
  /// network doesn't block a cashier who already has a shift open.
  bool? registerOpen;

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
  double get taxTotal => _cart.values.fold<double>(0, (sum, item) => sum + item.taxAmount);
  double get discount => 0;
  double get grandTotal => subtotal - discount + taxTotal;
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

  Future<void> checkRegisterStatus() async {
    try {
      final register = await _cashRegisterRepository.fetchCurrent();
      registerOpen = register?.isOpen == true;
    } on ApiException {
      // Leave registerOpen as-is (null on first load) rather than block
      // checkout on a transient status-check failure.
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
  /// Returns a snapshot of what was sold (for the post-checkout invoice
  /// actions sheet) on success, or null on failure — see [checkoutError].
  Future<PosCheckoutResult?> checkout() async {
    if (_cart.isEmpty) return null;

    if (registerOpen == false) {
      checkoutError = 'Open a cash register before completing a sale.';
      notifyListeners();
      return null;
    }

    isCheckingOut = true;
    checkoutError = null;
    notifyListeners();

    final saleId = _uuid.v4();
    final soldItems = _cart.values.toList();
    final soldSubtotal = subtotal;
    final soldTax = taxTotal;
    final soldDiscount = discount;
    final soldTotal = grandTotal;
    final soldCustomerName = selectedCustomer?.name;

    try {
      await _salesRepository.pushSale(
        id: saleId,
        total: soldTotal,
        discount: soldDiscount,
        taxAmount: soldTax,
        taxName: soldTax > 0 ? 'Tax' : null,
        paymentMethod: paymentMethod,
        customerId: selectedCustomer?.id,
        customerName: soldCustomerName,
        items: soldItems
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
      return PosCheckoutResult(
        saleId: saleId,
        saleNumber: 'POS-${saleId.substring(0, 8).toUpperCase()}',
        items: soldItems,
        subtotal: soldSubtotal,
        discount: soldDiscount,
        tax: soldTax,
        total: soldTotal,
        customerName: soldCustomerName,
      );
    } on ApiException catch (e) {
      checkoutError = e.message;
      isCheckingOut = false;
      notifyListeners();
      return null;
    }
  }
}
