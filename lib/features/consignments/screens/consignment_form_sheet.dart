import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/customer_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../customers/customers_repository.dart';
import '../../inventory/inventory_repository.dart';
import '../../pos/screens/customer_picker_sheet.dart';
import '../../quotations/screens/product_picker_sheet.dart';
import '../consignments_repository.dart';

/// Bottom sheet for POST /consignments — create only (the web Create page
/// has no edit mode either; item reconciliation happens on the detail
/// screen instead). Pops with `true` when saved.
class ConsignmentFormSheet extends StatefulWidget {
  const ConsignmentFormSheet({super.key, required this.repository, required this.formatter});

  final ConsignmentsRepository repository;
  final CurrencyFormatter formatter;

  @override
  State<ConsignmentFormSheet> createState() => _ConsignmentFormSheetState();
}

class _ConsignmentFormSheetState extends State<ConsignmentFormSheet> {
  CustomerModel? _customer;
  DateTime _dueDate = DateTime.now().add(const Duration(days: 15));
  final _notesController = TextEditingController();
  final List<Map<String, dynamic>> _items = [];
  bool _isSaving = false;
  String? _error;

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _pickCustomer() async {
    final customer = await showModalBottomSheet<CustomerModel>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => CustomerPickerSheet(customersRepository: CustomersRepository(context.read<ApiClient>())),
    );
    if (customer != null) setState(() => _customer = customer);
  }

  Future<void> _addProduct() async {
    final product = await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ProductPickerSheet(inventoryRepository: InventoryRepository(context.read<ApiClient>())),
    );
    if (product == null) return;
    setState(() {
      _items.add({'product_id': product.id, 'name': product.name, 'quantity': 1.0, 'unit_price': product.salePrice});
    });
  }

  Future<void> _pickDueDate() async {
    final picked = await showDatePicker(context: context, initialDate: _dueDate, firstDate: DateTime.now(), lastDate: DateTime(2100));
    if (picked != null) setState(() => _dueDate = picked);
  }

  Future<void> _submit() async {
    if (_customer == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Select a customer.')));
      return;
    }
    if (_items.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Add at least one item.')));
      return;
    }

    setState(() {
      _isSaving = true;
      _error = null;
    });

    try {
      await widget.repository.createConsignment(
        customerId: _customer!.id,
        items: _items.map((i) => {'product_id': i['product_id'], 'quantity': i['quantity'], 'unit_price': i['unit_price']}).toList(),
        dueDate: _dueDate,
        notes: _notesController.text.trim(),
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _isSaving = false;
      });
    }
  }

  double get _total => _items.fold(0.0, (sum, i) => sum + ((i['quantity'] as num) * (i['unit_price'] as num)));

  @override
  Widget build(BuildContext context) {
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
                Text('New consignment', style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 16),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.person_outline),
                  title: Text(_customer?.name ?? 'Select customer'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: _pickCustomer,
                ),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.event_outlined),
                  title: const Text('Due date'),
                  subtitle: Text('${_dueDate.year}-${_dueDate.month.toString().padLeft(2, '0')}-${_dueDate.day.toString().padLeft(2, '0')}'),
                  onTap: _pickDueDate,
                ),
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Items', style: Theme.of(context).textTheme.titleMedium),
                    TextButton.icon(onPressed: _addProduct, icon: const Icon(Icons.add), label: const Text('Add product')),
                  ],
                ),
                if (_items.isEmpty)
                  const Padding(padding: EdgeInsets.symmetric(vertical: 8), child: Text('No items yet.', style: TextStyle(color: Colors.grey)))
                else
                  ..._items.asMap().entries.map((entry) {
                    final index = entry.key;
                    final item = entry.value;
                    final qty = item['quantity'] as double;

                    return Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: Padding(
                        padding: const EdgeInsets.all(8),
                        child: Row(
                          children: [
                            Expanded(flex: 3, child: Text(item['name'] as String, overflow: TextOverflow.ellipsis)),
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
                            Text(qty.toStringAsFixed(0)),
                            IconButton(
                              icon: const Icon(Icons.add_circle_outline, size: 20),
                              onPressed: () => setState(() => item['quantity'] = qty + 1),
                            ),
                            SizedBox(
                              width: 70,
                              child: Text(
                                widget.formatter.format(qty * (item['unit_price'] as num)),
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
                const SizedBox(height: 8),
                TextFormField(
                  controller: _notesController,
                  decoration: const InputDecoration(labelText: 'Notes (optional)'),
                  maxLines: 2,
                ),
                const SizedBox(height: 12),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Total dispatched', style: TextStyle(fontWeight: FontWeight.bold)),
                    Text(widget.formatter.format(_total), style: const TextStyle(fontWeight: FontWeight.bold)),
                  ],
                ),
                if (_error != null) ...[
                  const SizedBox(height: 8),
                  Text(_error!, style: TextStyle(color: Colors.red.shade400)),
                ],
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: _isSaving ? null : _submit,
                  child: _isSaving
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('Create consignment'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
