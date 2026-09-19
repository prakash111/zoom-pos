import 'package:flutter/material.dart';
import '../../core/models/product_model.dart';
import '../../features/inventory/screens/product_form_sheet.dart';

export '../../features/inventory/screens/product_form_sheet.dart';

class ProductFormScreen extends StatelessWidget {
  final ProductModel? product;

  const ProductFormScreen({super.key, this.product});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(product == null ? 'Add Product' : 'Edit Product'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: ProductFormSheet(product: product),
        ),
      ),
    );
  }
}
