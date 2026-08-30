import '../../core/models/product_model.dart';

class CartItem {
  CartItem({required this.product, this.quantity = 1});

  final ProductModel product;
  double quantity;

  double get lineTotal => product.salePrice * quantity;
}
