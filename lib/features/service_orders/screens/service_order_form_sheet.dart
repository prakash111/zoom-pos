import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/customer_model.dart';
import '../../../core/models/service_order_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../customers/customers_repository.dart';
import '../../inventory/inventory_repository.dart';
import '../../pos/screens/customer_picker_sheet.dart';
import '../../quotations/screens/product_picker_sheet.dart';
import '../service_orders_repository.dart';

/// Bottom sheet for POST/PUT /service-orders, used for both creating a new
/// ticket ([order] is null) and editing an existing one.
class ServiceOrderFormSheet extends StatefulWidget {
  const ServiceOrderFormSheet({super.key, required this.repository, required this.formatter, this.order});

  final ServiceOrdersRepository repository;
  final CurrencyFormatter formatter;
  final ServiceOrderModel? order;

  @override
  State<ServiceOrderFormSheet> createState() => _ServiceOrderFormSheetState();
}

class _ServiceOrderFormSheetState extends State<ServiceOrderFormSheet> {
  final _formKey = GlobalKey<FormState>();
  CustomerModel? _customer;
  late final TextEditingController _customerNameController;
  late final TextEditingController _customerPhoneController;
  late final TextEditingController _equipmentController;
  late final TextEditingController _brandModelController;
  late final TextEditingController _serialController;
  late final TextEditingController _defectController;
  late final TextEditingController _diagnosisController;
  late final TextEditingController _laborController;
  late final TextEditingController _discountController;
  late final TextEditingController _notesController;
  late List<Map<String, dynamic>> _parts;
  late String _status;
  late String _priority;
  bool _isSaving = false;
  String? _error;

  bool get _isEditing => widget.order != null;

  @override
  void initState() {
    super.initState();
    final order = widget.order;
    _customerNameController = TextEditingController(text: order?.customerName ?? '');
    _customerPhoneController = TextEditingController(text: order?.customerPhone ?? '');
    _equipmentController = TextEditingController(text: order?.equipmentName ?? '');
    _brandModelController = TextEditingController(text: order?.brandModel ?? '');
    _serialController = TextEditingController(text: order?.serialNumber ?? '');
    _defectController = TextEditingController(text: order?.reportedDefect ?? '');
    _diagnosisController = TextEditingController(text: order?.technicalDiagnosis ?? '');
    _laborController = TextEditingController(text: (order?.laborCost ?? 0).toStringAsFixed(2));
    _discountController = TextEditingController(text: (order?.discount ?? 0).toStringAsFixed(2));
    _notesController = TextEditingController(text: order?.notes ?? '');
    _parts = order?.partsUsed.map((e) => Map<String, dynamic>.from(e)).toList() ?? [];
    _status = order?.status ?? 'received';
    _priority = order?.priority ?? 'normal';
  }

  @override
  void dispose() {
    for (final c in [
      _customerNameController, _customerPhoneController, _equipmentController, _brandModelController,
      _serialController, _defectController, _diagnosisController, _laborController, _discountController, _notesController,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  double get _partsTotal => _parts.fold(0.0, (sum, p) => sum + ((p['quantity'] as num) * (p['unit_price'] as num)));

  double get _grandTotal {
    final labor = double.tryParse(_laborController.text) ?? 0;
    final discount = double.tryParse(_discountController.text) ?? 0;
    return (_partsTotal + labor - discount).clamp(0, double.infinity).toDouble();
  }

  Future<void> _pickCustomer() async {
    final customer = await showModalBottomSheet<CustomerModel>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => CustomerPickerSheet(customersRepository: CustomersRepository(context.read<ApiClient>())),
    );
    if (customer != null) {
      setState(() {
        _customer = customer;
        _customerNameController.text = customer.name;
        _customerPhoneController.text = customer.phone;
      });
    }
  }

  Future<void> _addPart() async {
    final product = await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ProductPickerSheet(inventoryRepository: InventoryRepository(context.read<ApiClient>())),
    );
    if (product == null) return;
    setState(() {
      _parts.add({'product_id': product.id, 'name': product.name, 'quantity': 1.0, 'unit_price': product.salePrice});
    });
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isSaving = true;
      _error = null;
    });

    try {
      await widget.repository.saveOrder(
        id: widget.order?.id,
        customerId: _customer?.id ?? widget.order?.customerId,
        customerName: _customerNameController.text.trim(),
        customerPhone: _customerPhoneController.text.trim(),
        equipmentName: _equipmentController.text.trim(),
        brandModel: _brandModelController.text.trim(),
        serialNumber: _serialController.text.trim(),
        reportedDefect: _defectController.text.trim(),
        technicalDiagnosis: _diagnosisController.text.trim(),
        partsUsed: _parts,
        laborCost: double.tryParse(_laborController.text) ?? 0,
        discount: double.tryParse(_discountController.text) ?? 0,
        status: _status,
        priority: _priority,
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

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: DraggableScrollableSheet(
        initialChildSize: 0.92,
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
                  Text(_isEditing ? 'Edit service order' : 'New service order', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: _customerNameController,
                          decoration: const InputDecoration(labelText: 'Customer name'),
                          validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                        ),
                      ),
                      IconButton(icon: const Icon(Icons.person_search_outlined), onPressed: _pickCustomer),
                    ],
                  ),
                  TextFormField(controller: _customerPhoneController, decoration: const InputDecoration(labelText: 'Phone')),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _equipmentController,
                    decoration: const InputDecoration(labelText: 'Equipment'),
                    validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  Row(children: [
                    Expanded(child: TextFormField(controller: _brandModelController, decoration: const InputDecoration(labelText: 'Brand / model'))),
                    const SizedBox(width: 12),
                    Expanded(child: TextFormField(controller: _serialController, decoration: const InputDecoration(labelText: 'Serial number'))),
                  ]),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _defectController,
                    decoration: const InputDecoration(labelText: 'Reported defect'),
                    maxLines: 2,
                    validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(controller: _diagnosisController, decoration: const InputDecoration(labelText: 'Technical diagnosis (optional)'), maxLines: 2),
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('Parts used', style: Theme.of(context).textTheme.titleMedium),
                      TextButton.icon(onPressed: _addPart, icon: const Icon(Icons.add), label: const Text('Add part')),
                    ],
                  ),
                  ..._parts.asMap().entries.map((entry) {
                    final index = entry.key;
                    final part = entry.value;
                    final qty = ((part['quantity'] as num?) ?? 1).toDouble();
                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: Row(
                        children: [
                          Expanded(child: Text(part['name'] as String? ?? '', overflow: TextOverflow.ellipsis)),
                          IconButton(
                            icon: const Icon(Icons.remove_circle_outline, size: 20),
                            onPressed: () => setState(() {
                              if (qty > 1) {
                                part['quantity'] = qty - 1;
                              } else {
                                _parts.removeAt(index);
                              }
                            }),
                          ),
                          Text(qty.toStringAsFixed(0)),
                          IconButton(
                            icon: const Icon(Icons.add_circle_outline, size: 20),
                            onPressed: () => setState(() => part['quantity'] = qty + 1),
                          ),
                          Text(widget.formatter.format(qty * (part['unit_price'] as num))),
                        ],
                      ),
                    );
                  }),
                  const SizedBox(height: 12),
                  Row(children: [
                    Expanded(
                      child: TextFormField(
                        controller: _laborController,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: const InputDecoration(labelText: 'Labor cost'),
                        onChanged: (_) => setState(() {}),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: TextFormField(
                        controller: _discountController,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: const InputDecoration(labelText: 'Discount'),
                        onChanged: (_) => setState(() {}),
                      ),
                    ),
                  ]),
                  const SizedBox(height: 12),
                  Row(children: [
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: _status,
                        decoration: const InputDecoration(labelText: 'Status'),
                        items: [for (final s in kServiceOrderStatuses) DropdownMenuItem(value: s, child: Text(kServiceOrderStatusLabels[s] ?? s))],
                        onChanged: (value) => setState(() => _status = value ?? _status),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: _priority,
                        decoration: const InputDecoration(labelText: 'Priority'),
                        items: [for (final p in kServiceOrderPriorities) DropdownMenuItem(value: p, child: Text(p))],
                        onChanged: (value) => setState(() => _priority = value ?? _priority),
                      ),
                    ),
                  ]),
                  const SizedBox(height: 12),
                  TextFormField(controller: _notesController, decoration: const InputDecoration(labelText: 'Notes (optional)'), maxLines: 2),
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Total', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                      Text(widget.formatter.format(_grandTotal), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
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
                        : Text(_isEditing ? 'Save changes' : 'Create service order'),
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
