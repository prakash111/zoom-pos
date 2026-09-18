import 'dart:io';
import 'dart:math';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/config/app_config.dart';
import '../../../core/models/product_model.dart';
import '../../../core/models/tax_rule_model.dart';
import '../../../core/utils/responsive.dart';
import '../../../core/widgets/barcode_scanner_screen.dart';
import '../../taxes/taxes_repository.dart';
import '../inventory_provider.dart';
import '../inventory_repository.dart';

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
  late final TaxesRepository _taxesRepository;
  List<TaxRuleModel> _taxRules = [];
  TaxRuleModel? _selectedTaxRule;
  XFile? _pickedImage;
  String? _aiImageUrl;
  bool _aiAvailable = false;
  bool _isGeneratingAi = false;

  bool get _isEditing => widget.product != null;

  @override
  void initState() {
    super.initState();
    _taxesRepository = TaxesRepository(context.read<ApiClient>());
    context.read<ApiClient>().get(ApiEndpoints.aiImageAvailability).then((response) {
      if (mounted) setState(() => _aiAvailable = response['available'] as bool? ?? false);
    }).catchError((_) {});
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
    _loadTaxRules();
  }

  Future<void> _loadTaxRules() async {
    try {
      final rules = await _taxesRepository.fetchTaxes();
      if (!mounted) return;

      TaxRuleModel? matched;
      if (_isEditing) {
        for (final rule in rules) {
          if ((rule.rate - widget.product!.taxRate).abs() < 0.001) {
            matched = rule;
            break;
          }
        }
      } else {
        for (final rule in rules) {
          if (rule.isDefault) {
            matched = rule;
            break;
          }
        }
      }

      setState(() {
        _taxRules = rules;
        _selectedTaxRule = matched;
        if (matched != null) _taxRateController.text = matched.rate.toStringAsFixed(2);
      });
    } on ApiException {
      // Tax rules are a convenience picker on top of the free-text rate
      // field below — if they fail to load, leave the free-text field usable.
    }
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
    final productId = await inventory.saveProduct(
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
      imageUrl: _pickedImage == null ? _aiImageUrl : null,
    );

    if (!mounted) return;
    if (productId != null) {
      final pickedImage = _pickedImage;
      if (pickedImage != null) {
        try {
          final bytes = await pickedImage.readAsBytes();
          await InventoryRepository(context.read<ApiClient>()).uploadProductImage(productId, bytes, pickedImage.name);
        } on ApiException catch (e) {
          if (mounted) {
            ScaffoldMessenger.of(context)
                .showSnackBar(SnackBar(content: Text('Product saved, but image upload failed: ${e.message}')));
          }
        }
      }
      if (mounted) Navigator.of(context).pop();
    } else if (inventory.actionError != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(inventory.actionError!)));
    }
  }

  Future<void> _generateAiImage() async {
    final name = _nameController.text.trim();
    if (name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Enter a product name first.')));
      return;
    }
    setState(() => _isGeneratingAi = true);
    try {
      final response = await context.read<ApiClient>().post(ApiEndpoints.aiImageGenerate, data: {
        'name': name,
        if (_categoryController.text.trim().isNotEmpty) 'category': _categoryController.text.trim(),
      });
      final url = response['image_url']?.toString();
      if (mounted) {
        setState(() {
          if (url != null && url.isNotEmpty) {
            _aiImageUrl = url;
            _pickedImage = null;
          }
        });
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _isGeneratingAi = false);
    }
  }

  Future<void> _pickImage(ImageSource source) async {
    final picked = await ImagePicker().pickImage(source: source, imageQuality: 85);
    if (picked != null) setState(() => _pickedImage = picked);
  }

  Future<void> _scanBarcode() async {
    final code = await Navigator.of(context).push<String>(
      MaterialPageRoute(builder: (_) => const BarcodeScannerScreen()),
    );
    if (code != null && code.isNotEmpty) {
      setState(() => _barcodeController.text = code);
    }
  }

  /// Mirrors PosSyncApiController::generateIdentifiers()'s algorithm (GS1
  /// India-style EAN-13 barcode + category-prefixed SKU) client-side — only
  /// fills fields that are currently blank, same as the backend's guard.
  void _autoGenerateIdentifiers() {
    setState(() {
      if (_barcodeController.text.trim().isEmpty) _barcodeController.text = _generateBarcode();
      if (_skuController.text.trim().isEmpty) _skuController.text = _generateSku();
    });
  }

  String _generateBarcode() {
    final rnd = Random();
    final base = '890${rnd.nextInt(1000000000).toString().padLeft(9, '0')}';
    var sum = 0;
    for (var i = 0; i < base.length; i++) {
      sum += int.parse(base[i]) * (i.isEven ? 1 : 3);
    }
    final checkDigit = (10 - (sum % 10)) % 10;
    return '$base$checkDigit';
  }

  String _generateSku() {
    final raw = _categoryController.text.trim().toUpperCase().replaceAll(RegExp(r'[^A-Z0-9]'), '');
    final prefix = (raw.isEmpty ? 'PRD' : raw).padRight(3, 'X').substring(0, 3);
    final number = 100000 + Random().nextInt(900000);
    return '$prefix-$number';
  }

  Future<void> _confirmDelete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Delete ${widget.product!.name}?'),
        content: const Text(
          'This will archive/remove the item from the active POS catalog. Past sales references will not be affected.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    final inventory = context.read<InventoryProvider>();
    final success = await inventory.deleteProduct(widget.product!.id);
    if (!mounted) return;
    if (success) {
      Navigator.of(context).pop(true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Product deleted successfully.')),
      );
    } else if (inventory.actionError != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(inventory.actionError!)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final inventory = context.watch<InventoryProvider>();
    final wide = isWide(context);

    Widget formBody(ScrollController? scrollController) {
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
                  Center(
                    child: Stack(
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: Container(
                            width: 96,
                            height: 96,
                            color: Colors.grey.shade100,
                            child: _pickedImage != null
                                ? (kIsWeb
                                    ? Image.network(_pickedImage!.path, fit: BoxFit.cover)
                                    : Image.file(File(_pickedImage!.path), fit: BoxFit.cover))
                                : (_aiImageUrl ?? '').isNotEmpty
                                    ? CachedNetworkImage(imageUrl: _aiImageUrl!, fit: BoxFit.cover)
                                    : (widget.product?.imageUrl ?? '').isNotEmpty
                                        ? CachedNetworkImage(imageUrl: widget.product!.imageUrl!, fit: BoxFit.cover)
                                        : Icon(Icons.inventory_2_outlined, size: 36, color: Colors.grey.shade400),
                          ),
                        ),
                        if (_isGeneratingAi)
                          const Positioned.fill(
                            child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
                          ),
                        if (_pickedImage != null || (_aiImageUrl ?? '').isNotEmpty)
                          Positioned(
                            top: -8,
                            right: -8,
                            child: IconButton(
                              icon: const Icon(Icons.cancel, color: Colors.redAccent),
                              onPressed: () => setState(() {
                                _pickedImage = null;
                                _aiImageUrl = null;
                              }),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      TextButton.icon(
                        onPressed: () => _pickImage(ImageSource.camera),
                        icon: const Icon(Icons.photo_camera_outlined, size: 18),
                        label: const Text('Camera'),
                      ),
                      const SizedBox(width: 8),
                      TextButton.icon(
                        onPressed: () => _pickImage(ImageSource.gallery),
                        icon: const Icon(Icons.photo_library_outlined, size: 18),
                        label: const Text('Gallery'),
                      ),
                      if (_aiAvailable) ...[
                        const SizedBox(width: 8),
                        TextButton.icon(
                          onPressed: _isGeneratingAi ? null : _generateAiImage,
                          icon: const Icon(Icons.auto_awesome, size: 18),
                          label: const Text('Generate with AI'),
                        ),
                      ],
                    ],
                  ),
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
                          decoration: InputDecoration(
                            labelText: 'Barcode',
                            suffixIcon: IconButton(
                              icon: const Icon(Icons.qr_code_scanner, size: 20),
                              tooltip: 'Scan barcode',
                              onPressed: _scanBarcode,
                            ),
                          ),
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
                  Align(
                    alignment: Alignment.centerLeft,
                    child: TextButton.icon(
                      onPressed: _autoGenerateIdentifiers,
                      icon: const Icon(Icons.auto_awesome_outlined, size: 16),
                      label: const Text('Auto-generate barcode & SKU'),
                    ),
                  ),
                  const SizedBox(height: 4),
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
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            DropdownButtonFormField<TaxRuleModel?>(
                              initialValue: _selectedTaxRule,
                              isExpanded: true,
                              decoration: const InputDecoration(labelText: 'Tax rule'),
                              items: [
                                for (final rule in _taxRules)
                                  DropdownMenuItem(
                                    value: rule,
                                    child: Text('${rule.name} (${rule.rate.toStringAsFixed(0)}%)', overflow: TextOverflow.ellipsis),
                                  ),
                                const DropdownMenuItem(value: null, child: Text('Custom %')),
                              ],
                              onChanged: (rule) {
                                setState(() {
                                  _selectedTaxRule = rule;
                                  if (rule != null) _taxRateController.text = rule.rate.toStringAsFixed(2);
                                });
                              },
                            ),
                            if (_selectedTaxRule == null) ...[
                              const SizedBox(height: 12),
                              TextFormField(
                                controller: _taxRateController,
                                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                decoration: const InputDecoration(labelText: 'Tax rate %'),
                              ),
                            ],
                          ],
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
                  if (_isEditing) ...[
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: inventory.isSaving ? null : _confirmDelete,
                      icon: const Icon(Icons.delete_outline, color: Colors.red),
                      label: const Text('Delete / Archive Product', style: TextStyle(color: Colors.red)),
                      style: OutlinedButton.styleFrom(
                        side: BorderSide(color: Colors.red.shade300),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          );
    }

    if (wide) {
      return Padding(
        padding:
            EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
        child: formBody(null),
      );
    }

    return Padding(
      padding:
          EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: DraggableScrollableSheet(
        initialChildSize: 0.85,
        minChildSize: 0.5,
        maxChildSize: 0.95,
        expand: false,
        builder: (context, scrollController) => formBody(scrollController),
      ),
    );
  }
}
