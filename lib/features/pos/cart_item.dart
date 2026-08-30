import '../../core/models/product_model.dart';

class CartItem {
  CartItem({required this.product, this.quantity = 1});

  final ProductModel product;
  double quantity;

  double get lineTotal => product.salePrice * quantity;

  /// Tax on this line, computed from the product's configured tax rate
  /// (a percentage synced down with the catalog — see ProductModel.taxRate).
  double get taxAmount => lineTotal * (product.taxRate / 100);
}
