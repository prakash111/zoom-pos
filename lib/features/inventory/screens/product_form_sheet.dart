import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/models/product_model.dart';
import '../inventory_provider.dart';

/// Bottom sheet for POST /inventory/product, used for both creating a new
/// product ([product] is null) and editing an existing one. Stock is only
/// editable here as the initial count on create — once a product exists,
/// stock changes go through [AdjustStockSheet] so the server can log a
/// reason for the audit trail.
class ProductFormSheet extends StatefulWidget {
  const ProductFormSheet({super.key, this.product});

  final ProductModel? product;

  @override
  State<ProductFormSheet> createState() => _ProductFormSheetState();
}

class _ProductFormSheetState extends State<ProductFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _nameController;
  late final TextEditingController _salePriceController;
  late final TextEditingController _costPriceController;
  late final TextEditingController _stockController;
  late final TextEditingController _minStockController;
  late final TextEditingController _barcodeController;
  late final TextEditingController _skuController;
  late final TextEditingController _unitController;
  late final TextEditingController _categoryController;
  late final TextEditingController _brandController;
  late final TextEditingController _taxRateController;

  bool get _isEditing => widget.product != null;

  @override
  void initState() {
    super.initState();
    final product = widget.product;
    _nameController = TextEditingController(text: product?.name ?? '');
    _salePriceController = TextEditingController(text: product == null ? '' : product.salePrice.toStringAsFixed(2));
    _costPriceController = TextEditingController(text: product == null ? '' : product.costPrice.toStringAsFixed(2));
    _stockController = TextEditingController(text: product == null ? '0' : product.currentStock.toStringAsFixed(0));
    _minStockController =
        TextEditingController(text: product == null ? '0' : product.minimumStock.toStringAsFixed(0));
    _barcodeController = TextEditingController(text: product?.barcode ?? '');
    _skuController = TextEditingController(text: product?.sku ?? '');
    _unitController = TextEditingController(text: product?.unit ?? 'pcs');
    _categoryController = TextEditingController(text: product?.categoryName ?? '');
    _brandController = TextEditingController(text: product?.brandName ?? '');
    _taxRateController = TextEditingController(text: product == null ? '0' : product.taxRate.toStringAsFixed(2));
  }

  @override
  void dispose() {
    _nameController.dispose();
    _salePriceController.dispose();
    _costPriceController.dispose();
    _stockController.dispose();
    _minStockController.dispose();
    _barcodeController.dispose();
    _skuController.dispose();
    _unitController.dispose();
    _categoryController.dispose();
    _brandController.dispose();
    _taxRateController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    final inventory = context.read<InventoryProvider>();
    final success = await inventory.saveProduct(
      externalId: widget.product?.id,
      name: _nameController.text.trim(),
      salePrice: double.parse(_salePriceController.text),
      costPrice: double.tryParse(_costPriceController.text) ?? 0,
      currentStock: _isEditing ? widget.product!.currentStock : (double.tryParse(_stockController.text) ?? 0),
      minimumStock: double.tryParse(_minStockController.text) ?? 0,
      barcode: _barcodeController.text.trim(),
      sku: _skuController.text.trim(),
      unit: _unitController.text.trim().isEmpty ? 'pcs' : _unitController.text.trim(),
      categoryName: _categoryController.text.trim().isEmpty ? 'General' : _categoryController.text.trim(),
      brandName: _brandController.text.trim(),
      taxRate: double.tryParse(_taxRateController.text) ?? 0,
    );

    if (!mounted) return;
    if (success) {
      Navigator.of(context).pop();
    } else if (inventory.actionError != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(inventory.actionError!)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final inventory = context.watch<InventoryProvider>();

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: DraggableScrollableSheet(
        initialChildSize: 0.85,
        minChildSize: 0.5,
        maxChildSize: 0.95,
        expand: false,
        builder: (context, scrollController) {
          return SingleChildScrollView(
            controller: scrollController,
            padding: const EdgeInsets.all(20),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(_isEditing ? 'Edit product' : 'New product', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _nameController,
                    decoration: const InputDecoration(labelText: 'Name'),
                    validator: (value) => (value == null || value.trim().isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: _salePriceController,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          decoration: const InputDecoration(labelText: 'Sale price'),
                          validator: (value) {
                            final parsed = double.tryParse(value ?? '');
                            if (parsed == null || parsed < 0) return 'Required';
                            return null;
                          },
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: _costPriceController,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          decoration: const InputDecoration(labelText: 'Cost price'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  if (_isEditing)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Text(
                        'Current stock: ${widget.product!.currentStock.toStringAsFixed(0)} ${widget.product!.unit} '
                        '— use "Adjust stock" from the product list to change it.',
                        style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                      ),
                    )
                  else
                    Row(
                      children: [
                        Expanded(
                          child: TextFormField(
                            controller: _stockController,
                            keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            decoration: const InputDecoration(labelText: 'Initial stock'),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: TextFormField(
                            controller: _minStockController,
                            keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            decoration: const InputDecoration(labelText: 'Minimum stock'),
                          ),
                        ),
                      ],
                    ),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: _barcodeController,
                          decoration: const InputDecoration(labelText: 'Barcode'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: _skuController,
                          decoration: const InputDecoration(labelText: 'SKU'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: _unitController,
                          decoration: const InputDecoration(labelText: 'Unit', hintText: 'pcs'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: _taxRateController,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          decoration: const InputDecoration(labelText: 'Tax rate %'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: _categoryController,
                          decoration: const InputDecoration(labelText: 'Category'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: _brandController,
                          decoration: const InputDecoration(labelText: 'Brand (optional)'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: inventory.isSaving ? null : _submit,
                    child: inventory.isSaving
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : Text(_isEditing ? 'Save changes' : 'Create product'),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
