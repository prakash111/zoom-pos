import 'package:flutter/material.dart';

import '../../../core/models/customer_model.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../customers/customers_repository.dart';

/// Pops with the selected [CustomerModel], or null if dismissed without a
/// selection. Clearing an already-selected customer is handled by the
/// caller (CartSheet), not from within this picker.
class CustomerPickerSheet extends StatefulWidget {
  const CustomerPickerSheet({super.key, required this.customersRepository});

  final CustomersRepository customersRepository;

  @override
  State<CustomerPickerSheet> createState() => _CustomerPickerSheetState();
}

class _CustomerPickerSheetState extends State<CustomerPickerSheet> {
  late Future<List<CustomerModel>> _future;
  String _query = '';

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
        return Column(
          children: [
            const SizedBox(height: 12),
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2)),
            ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: TextField(
                decoration: const InputDecoration(hintText: 'Search customers', prefixIcon: Icon(Icons.search)),
                onChanged: (value) => setState(() => _query = value.toLowerCase()),
              ),
            ),
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
                    return const Center(child: Text('No customers found.'));
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
          ],
        );
      },
    );
  }
}
