import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/models/customer_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../customers_provider.dart';
import '../customers_repository.dart';
import 'customer_form_sheet.dart';
import 'customer_ledger_screen.dart';

/// Customer directory: list/search, create, edit, and — via
/// [CustomerLedgerScreen] — the ledger and payment history for one customer.
/// Owns a [CustomersProvider] scoped to this route.
class CustomersScreen extends StatelessWidget {
  const CustomersScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final apiClient = context.read<ApiClient>();

    return ChangeNotifierProvider(
      create: (_) => CustomersProvider(repository: CustomersRepository(apiClient))..loadCustomers(),
      child: const _CustomersScreenBody(),
    );
  }
}

class _CustomersScreenBody extends StatefulWidget {
  const _CustomersScreenBody();

  @override
  State<_CustomersScreenBody> createState() => _CustomersScreenBodyState();
}

class _CustomersScreenBodyState extends State<_CustomersScreenBody> {
  final _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _openCustomerForm(BuildContext context, {CustomerModel? customer}) {
    final customers = context.read<CustomersProvider>();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ChangeNotifierProvider.value(
        value: customers,
        child: CustomerFormSheet(customer: customer),
      ),
    );
  }

  Future<void> _openLedger(BuildContext context, CustomerModel customer) async {
    final repository = CustomersRepository(context.read<ApiClient>());
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CustomerLedgerScreen(customer: customer, repository: repository),
      ),
    );
    if (context.mounted) {
      context.read<CustomersProvider>().loadCustomers();
    }
  }

  @override
  Widget build(BuildContext context) {
    final customers = context.watch<CustomersProvider>();
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    return Scaffold(
      appBar: AppBar(title: const Text('Customers')),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _openCustomerForm(context),
        child: const Icon(Icons.add),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              onChanged: customers.setSearchQuery,
              decoration: InputDecoration(
                hintText: 'Search customers or phone',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: customers.searchQuery.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchController.clear();
                          customers.setSearchQuery('');
                        },
                      ),
              ),
            ),
          ),
          Expanded(child: _buildBody(context, customers, formatter)),
        ],
      ),
    );
  }

  Widget _buildBody(BuildContext context, CustomersProvider customers, CurrencyFormatter formatter) {
    switch (customers.status) {
      case CustomersStatus.loading:
        return const LoadingIndicator();
      case CustomersStatus.error:
        return ErrorView(message: customers.error ?? 'Could not load customers.', onRetry: customers.loadCustomers);
      case CustomersStatus.loaded:
        final list = customers.filteredCustomers;
        if (list.isEmpty) {
          return const Center(child: Text('No customers found.'));
        }
        return RefreshIndicator(
          onRefresh: customers.loadCustomers,
          child: ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 80),
            itemCount: list.length,
            separatorBuilder: (_, __) => const SizedBox(height: 8),
            itemBuilder: (context, index) {
              final customer = list[index];
              return _CustomerTile(
                customer: customer,
                formatter: formatter,
                onTap: () => _openLedger(context, customer),
                onEdit: () => _openCustomerForm(context, customer: customer),
              );
            },
          ),
        );
    }
  }
}

class _CustomerTile extends StatelessWidget {
  const _CustomerTile({
    required this.customer,
    required this.formatter,
    required this.onTap,
    required this.onEdit,
  });

  final CustomerModel customer;
  final CurrencyFormatter formatter;
  final VoidCallback onTap;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              const CircleAvatar(child: Icon(Icons.person_outline)),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(customer.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                    if (customer.phone.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(customer.phone, style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
                    ],
                    if (customer.customFields.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Wrap(
                        spacing: 6,
                        runSpacing: 4,
                        children: customer.customFields.entries.map((e) {
                          return Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.6),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              '${e.key}: ${e.value}',
                              style: TextStyle(
                                fontSize: 11,
                                color: Theme.of(context).colorScheme.onSurfaceVariant,
                              ),
                            ),
                          );
                        }).toList(),
                      ),
                    ],
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  if (customer.hasBalanceDue)
                    Text(
                      '${formatter.format(customer.balanceDue)} due',
                      style: TextStyle(color: Colors.red.shade400, fontWeight: FontWeight.w600, fontSize: 13),
                    )
                  else
                    Text('Settled', style: TextStyle(color: Colors.grey.shade500, fontSize: 13)),
                  IconButton(
                    icon: const Icon(Icons.edit_outlined, size: 18),
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                    visualDensity: VisualDensity.compact,
                    onPressed: onEdit,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
