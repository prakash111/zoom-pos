import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/models/customer_model.dart';
import '../../../core/utils/responsive.dart';
import '../customers_provider.dart';

/// Bottom sheet for POST /customers, used for both creating a new customer
/// ([customer] is null) and editing an existing one.
class CustomerFormSheet extends StatefulWidget {
  const CustomerFormSheet({super.key, this.customer});

  final CustomerModel? customer;

  @override
  State<CustomerFormSheet> createState() => _CustomerFormSheetState();
}

class _CustomerFormSheetState extends State<CustomerFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _nameController;
  late final TextEditingController _phoneController;
  late final TextEditingController _emailController;
  late final TextEditingController _documentController;
  late final TextEditingController _addressController;
  late final TextEditingController _cityController;
  late final TextEditingController _stateController;
  final List<MapEntry<TextEditingController, TextEditingController>> _customFieldControllers = [];

  bool get _isEditing => widget.customer != null;

  @override
  void initState() {
    super.initState();
    final customer = widget.customer;
    _nameController = TextEditingController(text: customer?.name ?? '');
    _phoneController = TextEditingController(text: customer?.phone ?? '');
    _emailController = TextEditingController(text: customer?.email ?? '');
    _documentController = TextEditingController(text: customer?.document ?? '');
    _addressController = TextEditingController(text: customer?.address ?? '');
    _cityController = TextEditingController(text: customer?.city ?? '');
    _stateController = TextEditingController(text: customer?.state ?? '');

    if (customer?.customFields != null && customer!.customFields.isNotEmpty) {
      customer.customFields.forEach((k, v) {
        _customFieldControllers.add(MapEntry(
          TextEditingController(text: k),
          TextEditingController(text: v?.toString() ?? ''),
        ));
      });
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _documentController.dispose();
    _addressController.dispose();
    _cityController.dispose();
    _stateController.dispose();
    for (final entry in _customFieldControllers) {
      entry.key.dispose();
      entry.value.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    final customFields = <String, dynamic>{};
    for (final entry in _customFieldControllers) {
      final k = entry.key.text.trim();
      final v = entry.value.text.trim();
      if (k.isNotEmpty && v.isNotEmpty) {
        customFields[k] = v;
      }
    }

    final customers = context.read<CustomersProvider>();
    final saved = await customers.saveCustomer(
      externalId: widget.customer?.id,
      name: _nameController.text.trim(),
      phone: _phoneController.text.trim(),
      email: _emailController.text.trim(),
      document: _documentController.text.trim(),
      address: _addressController.text.trim(),
      city: _cityController.text.trim(),
      state: _stateController.text.trim(),
      customFields: customFields.isNotEmpty ? customFields : null,
    );

    if (!mounted) return;
    if (saved != null) {
      Navigator.of(context).pop();
    } else if (customers.actionError != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(customers.actionError!)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final customers = context.watch<CustomersProvider>();
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
              Text(_isEditing ? 'Edit customer' : 'New customer', style: Theme.of(context).textTheme.titleLarge),
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
                          controller: _phoneController,
                          keyboardType: TextInputType.phone,
                          decoration: const InputDecoration(labelText: 'Phone'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: _emailController,
                          keyboardType: TextInputType.emailAddress,
                          decoration: const InputDecoration(labelText: 'Email'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _documentController,
                    decoration: const InputDecoration(labelText: 'Tax / ID document (optional)'),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _addressController,
                    decoration: const InputDecoration(labelText: 'Address'),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: _cityController,
                          decoration: const InputDecoration(labelText: 'City'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: _stateController,
                          decoration: const InputDecoration(labelText: 'State'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Custom Fields',
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.bold,
                            ),
                      ),
                      TextButton.icon(
                        onPressed: () {
                          setState(() {
                            _customFieldControllers.add(MapEntry(
                              TextEditingController(),
                              TextEditingController(),
                            ));
                          });
                        },
                        icon: const Icon(Icons.add, size: 18),
                        label: const Text('Add Field'),
                      ),
                    ],
                  ),
                  if (_customFieldControllers.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: Text(
                        'No custom fields added yet (e.g., GSTIN, Alternate Phone, Birthday).',
                        style: TextStyle(
                          fontSize: 12,
                          color: Theme.of(context).colorScheme.outline,
                        ),
                      ),
                    ),
                  ..._customFieldControllers.asMap().entries.map((entry) {
                    final index = entry.key;
                    final row = entry.value;
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Row(
                        children: [
                          Expanded(
                            flex: 4,
                            child: TextFormField(
                              controller: row.key,
                              decoration: const InputDecoration(
                                labelText: 'Field Name',
                                hintText: 'e.g. GSTIN',
                                isDense: true,
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            flex: 5,
                            child: TextFormField(
                              controller: row.value,
                              decoration: const InputDecoration(
                                labelText: 'Value',
                                isDense: true,
                              ),
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.close, size: 20, color: Colors.grey),
                            onPressed: () {
                              setState(() {
                                final removed = _customFieldControllers.removeAt(index);
                                removed.key.dispose();
                                removed.value.dispose();
                              });
                            },
                          ),
                        ],
                      ),
                    );
                  }),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: customers.isSaving ? null : _submit,
                    child: customers.isSaving
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : Text(_isEditing ? 'Save changes' : 'Create customer'),
                  ),
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
        initialChildSize: 0.8,
        minChildSize: 0.5,
        maxChildSize: 0.95,
        expand: false,
        builder: (context, scrollController) => formBody(scrollController),
      ),
    );
  }
}
