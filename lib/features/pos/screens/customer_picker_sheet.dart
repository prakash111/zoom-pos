import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/customer_model.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../customers/customers_repository.dart';

/// Pops with the selected [CustomerModel], or null if dismissed without a
/// selection. Clearing an already-selected customer is handled by the
/// caller (CartSheet), not from within this picker.
///
/// Also supports quick-adding a brand new customer inline (Name, Phone,
/// Email) without leaving the sheet — on success the new customer is
/// returned the same way an existing one would be, so the caller
/// auto-selects it into the cart and closes the picker.
class CustomerPickerSheet extends StatefulWidget {
  const CustomerPickerSheet({super.key, required this.customersRepository});

  final CustomersRepository customersRepository;

  @override
  State<CustomerPickerSheet> createState() => _CustomerPickerSheetState();
}

class _CustomerPickerSheetState extends State<CustomerPickerSheet> {
  late Future<List<CustomerModel>> _future;
  String _query = '';
  bool _showQuickAdd = false;

  @override
  void initState() {
    super.initState();
    _future = widget.customersRepository.fetchCustomers();
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.7,
      minChildSize: 0.4,
      maxChildSize: 0.95,
      expand: false,
      builder: (context, scrollController) {
        return Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
          child: SafeArea(
            top: false,
            child: Column(children: [
            const SizedBox(height: 12),
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2)),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      decoration: const InputDecoration(hintText: 'Search customers', prefixIcon: Icon(Icons.search)),
                      onChanged: (value) => setState(() => _query = value.toLowerCase()),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    tooltip: 'Add new customer',
                    icon: Icon(_showQuickAdd ? Icons.close : Icons.person_add_alt_1),
                    onPressed: () => setState(() => _showQuickAdd = !_showQuickAdd),
                  ),
                ],
              ),
            ),
            if (_showQuickAdd)
              Expanded(
                child: SingleChildScrollView(
                  controller: scrollController,
                  child: _QuickAddCustomerForm(
                    repository: widget.customersRepository,
                    onCreated: (customer) => Navigator.of(context).pop(customer),
                  ),
                ),
              )
            else
              Expanded(
                child: FutureBuilder<List<CustomerModel>>(
                  future: _future,
                  builder: (context, snapshot) {
                    if (snapshot.connectionState != ConnectionState.done) {
                      return const LoadingIndicator();
                    }
                    if (snapshot.hasError) {
                      return ErrorView(
                        message: 'Could not load customers.',
                        onRetry: () => setState(() => _future = widget.customersRepository.fetchCustomers()),
                      );
                    }

                    final customers = (snapshot.data ?? [])
                        .where((c) => _query.isEmpty || c.name.toLowerCase().contains(_query))
                        .toList();

                    if (customers.isEmpty) {
                      return Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Text('No customers found.'),
                            const SizedBox(height: 8),
                            TextButton.icon(
                              onPressed: () => setState(() => _showQuickAdd = true),
                              icon: const Icon(Icons.person_add_alt_1, size: 18),
                              label: const Text('Create new customer'),
                            ),
                          ],
                        ),
                      );
                    }

                    return ListView.builder(
                      controller: scrollController,
                      itemCount: customers.length,
                      itemBuilder: (context, index) {
                        final customer = customers[index];
                        return ListTile(
                          leading: const CircleAvatar(child: Icon(Icons.person_outline)),
                          title: Text(customer.name),
                          subtitle: customer.phone.isEmpty ? null : Text(customer.phone),
                          onTap: () => Navigator.of(context).pop(customer),
                        );
                      },
                    );
                  },
                ),
              ),
          ]),
          ),
        );
      },
    );
  }
}

/// Inline quick-add form (Name, Phone, Email) pinned below the search bar.
/// POSTs straight to the customer creation API and hands the newly created
/// customer back via [onCreated] so the caller can auto-select it.
class _QuickAddCustomerForm extends StatefulWidget {
  const _QuickAddCustomerForm({required this.repository, required this.onCreated});

  final CustomersRepository repository;
  final ValueChanged<CustomerModel> onCreated;

  @override
  State<_QuickAddCustomerForm> createState() => _QuickAddCustomerFormState();
}

class _QuickAddCustomerFormState extends State<_QuickAddCustomerForm> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  bool _isSaving = false;
  String? _error;

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isSaving = true;
      _error = null;
    });

    try {
      final customer = await widget.repository.saveCustomer(
        name: _nameController.text.trim(),
        phone: _phoneController.text.trim(),
        email: _emailController.text.trim(),
      );
      if (!mounted) return;
      widget.onCreated(customer);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _isSaving = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('New customer', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            TextFormField(
              controller: _nameController,
              autofocus: true,
              decoration: const InputDecoration(labelText: 'Name'),
              validator: (value) => (value == null || value.trim().isEmpty) ? 'Required' : null,
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: _phoneController,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(labelText: 'Phone number'),
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(labelText: 'Email'),
            ),
            if (_error != null) ...[
              const SizedBox(height: 8),
              Text(_error!, style: TextStyle(color: Colors.red.shade700, fontSize: 12)),
            ],
            const SizedBox(height: 14),
            ElevatedButton(
              onPressed: _isSaving ? null : _submit,
              child: _isSaving
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const Text('Create & select customer'),
            ),
          ],
        ),
      ),
    );
  }
}
