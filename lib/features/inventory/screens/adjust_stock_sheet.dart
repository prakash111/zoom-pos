import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/models/product_model.dart';
import '../inventory_provider.dart';

const _adjustmentTypes = [
  ('add', 'Add stock', Icons.add_circle_outline),
  ('subtract', 'Remove stock', Icons.remove_circle_outline),
  ('set', 'Set exact count', Icons.edit_outlined),
];

/// Bottom sheet for POST /inventory/adjust — kept separate from the product
/// edit form so every stock change carries a [reason] the server logs to
/// the audit trail (see PosSyncApiController::inventoryAdjustStock).
class AdjustStockSheet extends StatefulWidget {
  const AdjustStockSheet({super.key, required this.product});

  final ProductModel product;

  @override
  State<AdjustStockSheet> createState() => _AdjustStockSheetState();
}

class _AdjustStockSheetState extends State<AdjustStockSheet> {
  final _formKey = GlobalKey<FormState>();
  final _quantityController = TextEditingController();
  final _reasonController = TextEditingController();
  String _type = 'add';

  @override
  void dispose() {
    _quantityController.dispose();
    _reasonController.dispose();
    super.dispose();
  }

  double get _quantity => double.tryParse(_quantityController.text) ?? 0;

  double get _previewStock {
    switch (_type) {
      case 'add':
        return widget.product.currentStock + _quantity;
      case 'subtract':
        return widget.product.currentStock - _quantity;
      default:
        return _quantity;
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    final inventory = context.read<InventoryProvider>();
    final success = await inventory.adjustStock(
      productId: widget.product.id,
      type: _type,
      quantity: _quantity,
      reason: _reasonController.text.trim(),
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
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Adjust stock', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 4),
              Text(widget.product.name, style: TextStyle(color: Colors.grey.shade600)),
              const SizedBox(height: 16),
              Wrap(
                spacing: 8,
                children: [
                  for (final type in _adjustmentTypes)
                    ChoiceChip(
                      label: Text(type.$2),
                      avatar: Icon(type.$3, size: 16),
                      selected: _type == type.$1,
                      onSelected: (_) => setState(() => _type = type.$1),
                    ),
                ],
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _quantityController,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(
                  labelText: _type == 'set' ? 'New stock count' : 'Quantity',
                  suffixText: widget.product.unit,
                ),
                validator: (value) {
                  final parsed = double.tryParse(value ?? '');
                  if (parsed == null || parsed < 0) return 'Enter a valid quantity';
                  return null;
                },
              ),
              const SizedBox(height: 8),
              Text(
                'Current: ${widget.product.currentStock.toStringAsFixed(0)} ${widget.product.unit} '
                '→ New: ${_previewStock.toStringAsFixed(0)} ${widget.product.unit}',
                style: TextStyle(color: Colors.grey.shade600),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _reasonController,
                decoration: const InputDecoration(labelText: 'Reason (optional)'),
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
                    : const Text('Save adjustment'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
