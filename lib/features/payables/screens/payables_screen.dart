import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/models/vendor_bill_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../payables_provider.dart';
import '../payables_repository.dart';
import 'bill_form_sheet.dart';
import 'record_bill_payment_sheet.dart';

const _statusOptions = ['pending', 'partially_paid', 'paid'];

/// Accounts Payable: vendor bills, filterable by status, with create/edit
/// and record-payment actions. Owns a [PayablesProvider] scoped to this route.
class PayablesScreen extends StatelessWidget {
  const PayablesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final apiClient = context.read<ApiClient>();

    return ChangeNotifierProvider(
      create: (_) => PayablesProvider(repository: PayablesRepository(apiClient))..loadBills(),
      child: const _PayablesScreenBody(),
    );
  }
}

class _PayablesScreenBody extends StatelessWidget {
  const _PayablesScreenBody();

  void _openForm(BuildContext context, {VendorBillModel? bill}) {
    final payables = context.read<PayablesProvider>();
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ChangeNotifierProvider.value(value: payables, child: BillFormSheet(bill: bill)),
    );
  }

  void _openPayment(BuildContext context, VendorBillModel bill, CurrencyFormatter formatter) {
    final payables = context.read<PayablesProvider>();
    showDialog(
      context: context,
      builder: (_) => ChangeNotifierProvider.value(
        value: payables,
        child: RecordBillPaymentSheet(bill: bill, formatter: formatter),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final payables = context.watch<PayablesProvider>();
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    return Scaffold(
      appBar: AppBar(title: const Text('Accounts Payable')),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _openForm(context),
        child: const Icon(Icons.add),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Expanded(
                  child: _SummaryCard(label: 'Total payable', value: formatter.format(payables.totalPayable)),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _SummaryCard(
                    label: 'Overdue',
                    value: formatter.format(payables.overduePayable),
                    color: Colors.red.shade50,
                  ),
                ),
              ],
            ),
          ),
          SizedBox(
            height: 40,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              children: [
                ChoiceChip(
                  label: const Text('All'),
                  selected: payables.statusFilter == null,
                  onSelected: (_) => payables.setStatusFilter(null),
                ),
                for (final status in _statusOptions)
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: ChoiceChip(
                      label: Text(status.replaceAll('_', ' ')),
                      selected: payables.statusFilter == status,
                      onSelected: (_) => payables.setStatusFilter(status),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: Builder(builder: (context) {
              if (payables.status == PayablesStatus.loading) {
                return const LoadingIndicator();
              }
              if (payables.status == PayablesStatus.error) {
                return ErrorView(message: payables.error ?? 'Could not load bills.', onRetry: payables.loadBills);
              }

              final bills = payables.filteredBills;
              if (bills.isEmpty) {
                return const Center(child: Text('No bills found.'));
              }

              return RefreshIndicator(
                onRefresh: payables.loadBills,
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 80),
                  itemCount: bills.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 8),
                  itemBuilder: (context, index) {
                    final bill = bills[index];
                    return Card(
                      child: ListTile(
                        onTap: () => _openForm(context, bill: bill),
                        title: Text(bill.vendorName),
                        subtitle: Text('${bill.category} · ${bill.billNumber}'),
                        trailing: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(formatter.format(bill.totalAmount), style: const TextStyle(fontWeight: FontWeight.w600)),
                            if (!bill.isPaid)
                              TextButton(
                                onPressed: () => _openPayment(context, bill, formatter),
                                style: TextButton.styleFrom(padding: EdgeInsets.zero, minimumSize: const Size(0, 24)),
                                child: Text(
                                  'Due ${formatter.format(bill.dueAmount)}',
                                  style: TextStyle(fontSize: 12, color: bill.isOverdue ? Colors.red : Colors.orange.shade800),
                                ),
                              )
                            else
                              const Text('Paid', style: TextStyle(fontSize: 12, color: Colors.green)),
                          ],
                        ),
                      ),
                    );
                  },
                ),
              );
            }),
          ),
        ],
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.label, required this.value, this.color});

  final String label;
  final String value;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Card(
      color: color,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
            const SizedBox(height: 4),
            Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          ],
        ),
      ),
    );
  }
}
