import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/models/quotation_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../inventory/inventory_repository.dart';
import '../quotations_provider.dart';
import 'product_picker_sheet.dart';

/// Bottom sheet for POST/PUT /quotations, used for both creating a new
/// quote ([quotation] is null) and editing an existing one.
class QuotationFormSheet extends StatefulWidget {
  const QuotationFormSheet({super.key, this.quotation, required this.formatter});

  final QuotationModel? quotation;
  final CurrencyFormatter formatter;

  @override
  State<QuotationFormSheet> createState() => _QuotationFormSheetState();
}

class _QuotationFormSheetState extends State<QuotationFormSheet> {
  late final TextEditingController _customerNameController;
  late final TextEditingController _discountController;
  late final TextEditingController _taxController;
  late final TextEditingController _notesController;
  late final TextEditingController _termsController;
  late List<Map<String, dynamic>> _items;

  bool get _isEditing => widget.quotation != null;

  @override
  void initState() {
    super.initState();
    final quote = widget.quotation;
    _customerNameController = TextEditingController(text: quote?.customerName ?? '');
    _discountController = TextEditingController(text: (quote?.discount ?? 0).toStringAsFixed(2));
    _taxController = TextEditingController(text: (quote?.tax ?? 0).toStringAsFixed(2));
    _notesController = TextEditingController(text: quote?.notes ?? '');
    _termsController = TextEditingController(text: quote?.terms ?? '');
    _items = quote?.items.map((e) => Map<String, dynamic>.from(e)).toList() ?? [];
  }

  @override
  void dispose() {
    _customerNameController.dispose();
    _discountController.dispose();
    _taxController.dispose();
    _notesController.dispose();
    _termsController.dispose();
    super.dispose();
  }

  double get _subtotal => _items.fold(0.0, (sum, item) {
        final qty = (item['quantity'] as num?)?.toDouble() ?? 0;
        final price = (item['price'] as num?)?.toDouble() ?? 0;
        return sum + (qty * price);
      });

  Future<void> _addProduct() async {
    final product = await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ProductPickerSheet(inventoryRepository: InventoryRepository(context.read<ApiClient>())),
    );

    if (product == null) return;
    setState(() {
      _items.add({
        'id': product.id,
        'product_id': product.id,
        'name': product.name,
        'price': product.salePrice,
        'quantity': 1.0,
      });
    });
  }

  Future<void> _submit() async {
    if (_customerNameController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Enter a customer name.')));
      return;
    }
    if (_items.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Add at least one item.')));
      return;
    }

    final quotations = context.read<QuotationsProvider>();
    final success = await quotations.saveQuotation(
      id: widget.quotation?.id,
      customerName: _customerNameController.text.trim(),
      items: _items,
      discount: double.tryParse(_discountController.text) ?? 0,
      tax: double.tryParse(_taxController.text) ?? 0,
      notes: _notesController.text.trim(),
      terms: _termsController.text.trim(),
    );

    if (!mounted) return;
    if (success) {
      Navigator.of(context).pop();
    } else if (quotations.actionError != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(quotations.actionError!)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final quotations = context.watch<QuotationsProvider>();
    final discount = double.tryParse(_discountController.text) ?? 0;
    final tax = double.tryParse(_taxController.text) ?? 0;
    final total = (_subtotal - discount + tax).clamp(0, double.infinity);

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: DraggableScrollableSheet(
        initialChildSize: 0.9,
        minChildSize: 0.5,
        maxChildSize: 0.95,
        expand: false,
        builder: (context, scrollController) {
          return SingleChildScrollView(
            controller: scrollController,
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(_isEditing ? 'Edit quotation' : 'New quotation', style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _customerNameController,
                  decoration: const InputDecoration(labelText: 'Customer name'),
                ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Items', style: Theme.of(context).textTheme.titleMedium),
                    TextButton.icon(
                      onPressed: _addProduct,
                      icon: const Icon(Icons.add),
                      label: const Text('Add product'),
                    ),
                  ],
                ),
                if (_items.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 12),
                    child: Text('No items yet.', style: TextStyle(color: Colors.grey)),
                  )
                else
                  ..._items.asMap().entries.map((entry) {
                    final index = entry.key;
                    final item = entry.value;
                    final qty = (item['quantity'] as num?)?.toDouble() ?? 0;
                    final price = (item['price'] as num?)?.toDouble() ?? 0;

                    return Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: Padding(
                        padding: const EdgeInsets.all(8),
                        child: Row(
                          children: [
                            Expanded(
                              flex: 3,
                              child: Text(item['name'] as String? ?? 'Item', overflow: TextOverflow.ellipsis),
                            ),
                            IconButton(
                              icon: const Icon(Icons.remove_circle_outline, size: 20),
                              onPressed: () => setState(() {
                                if (qty > 1) {
                                  item['quantity'] = qty - 1;
                                } else {
                                  _items.removeAt(index);
                                }
                              }),
                            ),
                            Text(qty.toStringAsFixed(qty.truncateToDouble() == qty ? 0 : 2)),
                            IconButton(
                              icon: const Icon(Icons.add_circle_outline, size: 20),
                              onPressed: () => setState(() => item['quantity'] = qty + 1),
                            ),
                            SizedBox(
                              width: 70,
                              child: Text(
                                widget.formatter.format(qty * price),
                                textAlign: TextAlign.right,
                                style: const TextStyle(fontWeight: FontWeight.w600),
                              ),
                            ),
                            IconButton(
                              icon: const Icon(Icons.delete_outline, size: 20, color: Colors.redAccent),
                              onPressed: () => setState(() => _items.removeAt(index)),
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _discountController,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: const InputDecoration(labelText: 'Discount'),
                        onChanged: (_) => setState(() {}),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: TextFormField(
                        controller: _taxController,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: const InputDecoration(labelText: 'Tax'),
                        onChanged: (_) => setState(() {}),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _notesController,
                  decoration: const InputDecoration(labelText: 'Notes (optional)'),
                  maxLines: 2,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _termsController,
                  decoration: const InputDecoration(labelText: 'Terms (optional)'),
                  maxLines: 2,
                ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Total', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                    Text(widget.formatter.format(total), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  ],
                ),
                const SizedBox(height: 20),
                ElevatedButton(
                  onPressed: quotations.isSaving ? null : _submit,
                  child: quotations.isSaving
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : Text(_isEditing ? 'Save changes' : 'Create quotation'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
