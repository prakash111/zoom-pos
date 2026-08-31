import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:uuid/uuid.dart';

import '../../core/api/api_exception.dart';
import '../../core/models/category_model.dart';
import '../../core/models/customer_model.dart';
import '../../core/models/product_model.dart';
import '../../core/models/settings_models.dart';
import '../../core/services/sync/sync_engine.dart';
import '../cash_register/cash_register_repository.dart';
import '../inventory/inventory_repository.dart';
import 'cart_item.dart';
import 'held_carts_store.dart';
import 'sales_repository.dart';

enum CatalogStatus { loading, loaded, error }

/// A parked/held cart that can be resumed at any time.
class HeldCart {
  HeldCart({
    required this.id,
    required this.name,
    required this.cart,
    required this.customer,
    required this.notes,
    required this.discount,
    required this.isPercentDiscount,
    required this.heldAt,
  });

  final String id;
  final String name;
  final Map<String, CartItem> cart;
  final CustomerModel? customer;
  final String notes;
  final double discount;
  final bool isPercentDiscount;
  final DateTime heldAt;

  int get itemCount => cart.values.fold<int>(0, (sum, item) => sum + item.quantity.ceil());
  double get total => cart.values.fold<double>(0, (sum, item) => sum + item.lineTotal);

  factory HeldCart.fromJson(Map<String, dynamic> json) {
    final cartJson = json['cart'] as Map<String, dynamic>? ?? const {};
    return HeldCart(
      id: json['id'] as String? ?? '',
      name: json['name'] as String? ?? '',
      cart: cartJson.map((key, value) => MapEntry(key, CartItem.fromJson(value as Map<String, dynamic>))),
      customer: json['customer'] != null ? CustomerModel.fromJson(json['customer'] as Map<String, dynamic>) : null,
      notes: json['notes'] as String? ?? '',
      discount: (json['discount'] as num?)?.toDouble() ?? 0,
      isPercentDiscount: json['is_percent_discount'] as bool? ?? false,
      heldAt: DateTime.tryParse(json['held_at'] as String? ?? '') ?? DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'cart': cart.map((key, value) => MapEntry(key, value.toJson())),
      'customer': customer?.toJson(),
      'notes': notes,
      'discount': discount,
      'is_percent_discount': isPercentDiscount,
      'held_at': heldAt.toIso8601String(),
    };
  }
}

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
    this.notes,
    this.isPendingSync = false,
  });

  final String saleId;
  final String saleNumber;
  final List<CartItem> items;
  final double subtotal;
  final double discount;
  final double tax;
  final double total;
  final String? customerName;
  final String? notes;

  /// True when the sale couldn't reach the server (no connectivity) and was
  /// queued in the offline outbox instead — it will push automatically once
  /// the device is back online, or via a manual "Sync Now".
  final bool isPendingSync;
}

/// Drives the point-of-sale screen: loads the product catalog, filters it by
/// search/category, holds the in-memory cart, and submits the sale through
/// [SalesRepository] on checkout.
class PosProvider extends ChangeNotifier {
  PosProvider({
    required InventoryRepository inventoryRepository,
    required SalesRepository salesRepository,
    required CashRegisterRepository cashRegisterRepository,
    required HeldCartsStore heldCartsStore,
    required SyncEngine syncEngine,
  })  : _inventoryRepository = inventoryRepository,
        _salesRepository = salesRepository,
        _cashRegisterRepository = cashRegisterRepository,
        _heldCartsStore = heldCartsStore,
        _syncEngine = syncEngine;

  final InventoryRepository _inventoryRepository;
  final SalesRepository _salesRepository;
  final CashRegisterRepository _cashRegisterRepository;
  final SyncEngine _syncEngine;
  final HeldCartsStore _heldCartsStore;
  static final Uuid _uuid = Uuid();

  /// Null until the first [checkRegisterStatus] call resolves.
  bool? registerOpen;

  CatalogStatus catalogStatus = CatalogStatus.loading;
  String? catalogError;
  List<ProductModel> _products = [];
  List<CategoryModel> categories = [];
  List<PaymentMethodModel> paymentMethods = [];

  String searchQuery = '';
  String? selectedCategoryId;

  final Map<String, CartItem> _cart = {};

  /// Backed by [HeldCartsStore], which persists independently of this
  /// provider's lifecycle so held carts survive navigating away from POS.
  List<HeldCart> get heldCarts => _heldCartsStore.carts;
  CustomerModel? selectedCustomer;
  String paymentMethod = 'cash';
  String orderNotes = '';
  double customDiscount = 0;
  bool isPercentDiscount = false;

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

  double get discount {
    if (customDiscount <= 0) return 0;
    if (isPercentDiscount) {
      return (subtotal * (customDiscount / 100)).clamp(0, subtotal);
    }
    return customDiscount.clamp(0, subtotal);
  }

  double get grandTotal => (subtotal - discount + taxTotal).clamp(0, double.infinity);
  bool get cartIsEmpty => _cart.isEmpty;

  Future<void> loadCatalog() async {
    catalogStatus = CatalogStatus.loading;
    notifyListeners();

    try {
      final catalog = await _inventoryRepository.fetchCatalog();
      _products = catalog.products;
      categories = catalog.categories;
      paymentMethods = catalog.paymentMethods;
      if (paymentMethods.isNotEmpty && !paymentMethods.any((p) => p.code == paymentMethod || p.id == paymentMethod)) {
        paymentMethod = paymentMethods.first.code.isNotEmpty ? paymentMethods.first.code : paymentMethods.first.id;
      }
      catalogStatus = CatalogStatus.loaded;
    } on ApiException catch (e) {
      catalogError = e.message;
      catalogStatus = CatalogStatus.error;
    } catch (e) {
      catalogError = e.toString();
      catalogStatus = CatalogStatus.error;
    }
    notifyListeners();
  }

  Future<void> checkRegisterStatus() async {
    try {
      final register = await _cashRegisterRepository.fetchCurrent();
      registerOpen = register?.isOpen == true;
    } on ApiException {
      // Leave registerOpen as-is on transient failure.
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

  void setOrderNotes(String notes) {
    orderNotes = notes;
    notifyListeners();
  }

  void setDiscount(double amount, {bool isPercent = false}) {
    customDiscount = amount;
    isPercentDiscount = isPercent;
    notifyListeners();
  }

  void holdCurrentCart({String? label}) {
    if (_cart.isEmpty) return;
    final heldId = _uuid.v4().substring(0, 6).toUpperCase();
    final name = label != null && label.isNotEmpty
        ? label
        : (selectedCustomer?.name != null ? 'Cart (${selectedCustomer!.name})' : 'Held #$heldId');

    _heldCartsStore.add(
      HeldCart(
        id: heldId,
        name: name,
        cart: Map<String, CartItem>.from(_cart.map((k, v) => MapEntry(k, CartItem(product: v.product, quantity: v.quantity)))),
        customer: selectedCustomer,
        notes: orderNotes,
        discount: customDiscount,
        isPercentDiscount: isPercentDiscount,
        heldAt: DateTime.now(),
      ),
    );
    clearCart();
  }

  void resumeHeldCart(HeldCart held) {
    _cart.clear();
    for (final entry in held.cart.entries) {
      _cart[entry.key] = CartItem(product: entry.value.product, quantity: entry.value.quantity);
    }
    selectedCustomer = held.customer;
    orderNotes = held.notes;
    customDiscount = held.discount;
    isPercentDiscount = held.isPercentDiscount;
    _heldCartsStore.remove(held.id);
    notifyListeners();
  }

  void deleteHeldCart(String id) {
    _heldCartsStore.remove(id);
    notifyListeners();
  }

  void clearCart() {
    _cart.clear();
    selectedCustomer = null;
    orderNotes = '';
    customDiscount = 0;
    isPercentDiscount = false;
    if (paymentMethods.isNotEmpty) {
      paymentMethod = paymentMethods.first.code.isNotEmpty ? paymentMethods.first.code : paymentMethods.first.id;
    } else {
      paymentMethod = 'cash';
    }
    notifyListeners();
  }

  /// Records the current cart as a completed sale.
  ///
  /// [taxLabel] is only used to name the tax line on the persisted sale
  /// (e.g. "GST" for Indian tenants) — pass the caller's
  /// `company.taxLabel` where available.
  Future<PosCheckoutResult?> checkout({String? taxLabel}) async {
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
    final soldAt = DateTime.now();
    final soldItems = _cart.values.toList();
    final soldSubtotal = subtotal;
    final soldTax = taxTotal;
    final soldDiscount = discount;
    final soldTotal = grandTotal;
    final soldCustomerName = selectedCustomer?.name;
    final soldNotes = orderNotes;
    final taxName = soldTax > 0 ? (taxLabel ?? 'Tax') : null;
    final itemsPayload = soldItems
        .map((item) => {
              'id': item.product.id,
              'product_id': item.product.id,
              'name': item.product.name,
              'price': item.product.salePrice,
              'quantity': item.quantity,
              'tax_rate': item.product.taxRate,
            })
        .toList();

    Future<void> queueOffline() {
      final payload = SalesRepository.buildOfflineSalePayload(
        id: saleId,
        total: soldTotal,
        discount: soldDiscount,
        taxAmount: soldTax,
        taxName: taxName,
        paymentMethod: paymentMethod,
        customerId: selectedCustomer?.id,
        customerName: soldCustomerName,
        items: itemsPayload,
        createdAt: soldAt,
      );
      return _syncEngine.queueOfflineSale(id: saleId, payload: payload, createdAt: soldAt);
    }

    PosCheckoutResult buildResult({required bool isPendingSync}) {
      return PosCheckoutResult(
        saleId: saleId,
        saleNumber: 'POS-${saleId.substring(0, 8).toUpperCase()}',
        items: soldItems,
        subtotal: soldSubtotal,
        discount: soldDiscount,
        tax: soldTax,
        total: soldTotal,
        customerName: soldCustomerName,
        notes: soldNotes,
        isPendingSync: isPendingSync,
      );
    }

    final connectivity = await Connectivity().checkConnectivity();
    final isOnline = connectivity.any((r) => r != ConnectivityResult.none);

    if (!isOnline) {
      await queueOffline();
      clearCart();
      isCheckingOut = false;
      notifyListeners();
      return buildResult(isPendingSync: true);
    }

    try {
      await _salesRepository.pushSale(
        id: saleId,
        total: soldTotal,
        discount: soldDiscount,
        taxAmount: soldTax,
        taxName: taxName,
        paymentMethod: paymentMethod,
        customerId: selectedCustomer?.id,
        customerName: soldCustomerName,
        items: itemsPayload,
      );
      clearCart();
      isCheckingOut = false;
      notifyListeners();
      return buildResult(isPendingSync: false);
    } on ApiException catch (e) {
      // No status code means the request never reached the server (timeout,
      // no route to host, DNS failure, ...) rather than the server actively
      // rejecting it — that's a connectivity problem, not a checkout error,
      // so queue the sale instead of blocking the cashier.
      if (e.statusCode == null) {
        await queueOffline();
        clearCart();
        isCheckingOut = false;
        notifyListeners();
        return buildResult(isPendingSync: true);
      }

      checkoutError = e.message;
      isCheckingOut = false;
      notifyListeners();
      return null;
    }
  }
}
