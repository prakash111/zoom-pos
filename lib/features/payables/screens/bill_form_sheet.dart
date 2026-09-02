import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/models/vendor_bill_model.dart';
import '../payables_provider.dart';

const _categories = ['inventory', 'rent', 'utilities', 'salaries', 'marketing', 'equipment', 'other'];

/// Bottom sheet for POST/PUT /payables, used for both creating a new vendor
/// bill ([bill] is null) and editing an existing one.
class BillFormSheet extends StatefulWidget {
  const BillFormSheet({super.key, this.bill});

  final VendorBillModel? bill;

  @override
  State<BillFormSheet> createState() => _BillFormSheetState();
}

class _BillFormSheetState extends State<BillFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _vendorNameController;
  late final TextEditingController _billNumberController;
  late final TextEditingController _amountController;
  late final TextEditingController _taxController;
  late final TextEditingController _notesController;
  late String _category;
  late DateTime _billDate;
  DateTime? _dueDate;

  bool get _isEditing => widget.bill != null;

  @override
  void initState() {
    super.initState();
    final bill = widget.bill;
    _vendorNameController = TextEditingController(text: bill?.vendorName ?? '');
    _billNumberController = TextEditingController(text: bill?.billNumber ?? '');
    _amountController = TextEditingController(text: bill != null ? bill.amount.toStringAsFixed(2) : '');
    _taxController = TextEditingController(text: (bill?.taxAmount ?? 0).toStringAsFixed(2));
    _notesController = TextEditingController(text: bill?.notes ?? '');
    _category = bill?.category ?? _categories.first;
    _billDate = bill?.billDate ?? DateTime.now();
    _dueDate = bill?.dueDate;
  }

  @override
  void dispose() {
    _vendorNameController.dispose();
    _billNumberController.dispose();
    _amountController.dispose();
    _taxController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _pickDate({required bool isDue}) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: isDue ? (_dueDate ?? _billDate) : _billDate,
      firstDate: DateTime(2020),
      lastDate: DateTime(2100),
    );
    if (picked == null) return;
    setState(() => isDue ? _dueDate = picked : _billDate = picked);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    final payables = context.read<PayablesProvider>();
    final success = await payables.saveBill(
      id: widget.bill?.id,
      vendorName: _vendorNameController.text.trim(),
      billNumber: _billNumberController.text.trim(),
      category: _category,
      amount: double.tryParse(_amountController.text) ?? 0,
      taxAmount: double.tryParse(_taxController.text) ?? 0,
      billDate: _billDate,
      dueDate: _dueDate,
      notes: _notesController.text.trim(),
    );

    if (!mounted) return;
    if (success) {
      Navigator.of(context).pop();
    } else if (payables.actionError != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(payables.actionError!)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final payables = context.watch<PayablesProvider>();

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
                  Text(_isEditing ? 'Edit bill' : 'New vendor bill', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _vendorNameController,
                    decoration: const InputDecoration(labelText: 'Vendor name'),
                    validator: (value) => (value == null || value.trim().isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: _category,
                    decoration: const InputDecoration(labelText: 'Category'),
                    items: [for (final c in _categories) DropdownMenuItem(value: c, child: Text(c))],
                    onChanged: (value) => setState(() => _category = value ?? _category),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _billNumberController,
                    decoration: const InputDecoration(labelText: 'Bill number (optional)'),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: _amountController,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          decoration: const InputDecoration(labelText: 'Amount'),
                          validator: (value) => (double.tryParse(value ?? '') == null) ? 'Required' : null,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: _taxController,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          decoration: const InputDecoration(labelText: 'Tax'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: const Text('Bill date'),
                          subtitle: Text('${_billDate.year}-${_billDate.month.toString().padLeft(2, '0')}-${_billDate.day.toString().padLeft(2, '0')}'),
                          onTap: () => _pickDate(isDue: false),
                        ),
                      ),
                      Expanded(
                        child: ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: const Text('Due date'),
                          subtitle: Text(_dueDate == null
                              ? 'None'
                              : '${_dueDate!.year}-${_dueDate!.month.toString().padLeft(2, '0')}-${_dueDate!.day.toString().padLeft(2, '0')}'),
                          onTap: () => _pickDate(isDue: true),
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
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: payables.isSaving ? null : _submit,
                    child: payables.isSaving
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : Text(_isEditing ? 'Save changes' : 'Create bill'),
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
