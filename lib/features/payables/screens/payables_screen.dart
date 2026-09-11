import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/models/vendor_bill_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/utils/responsive.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../../core/widgets/responsive/desktop_content_area.dart';
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
      create: (_) => PayablesProvider(repository: PayablesRepository(apiClient))
        ..loadBills(),
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
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ChangeNotifierProvider.value(
          value: payables, child: BillFormSheet(bill: bill)),
    );
  }

  void _openPayment(
      BuildContext context, VendorBillModel bill, CurrencyFormatter formatter) {
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
      body: DesktopContentArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  Expanded(
                    child: _SummaryCard(
                        label: 'Total payable',
                        value: formatter.format(payables.totalPayable)),
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
                  return ErrorView(
                      message: payables.error ?? 'Could not load bills.',
                      onRetry: payables.loadBills);
                }

                final bills = payables.filteredBills;
                if (bills.isEmpty) {
                  return const Center(child: Text('No bills found.'));
                }

                return RefreshIndicator(
                  onRefresh: payables.loadBills,
                  child: MediaQuery.sizeOf(context).width >= Breakpoints.desktop
                      ? _DesktopPayablesTable(
                          bills: bills,
                          formatter: formatter,
                          onTap: (b) => _openForm(context, bill: b),
                          onPay: (b) => _openPayment(context, b, formatter),
                        )
                      : ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 12, 16, 80),
                          itemCount: bills.length,
                          separatorBuilder: (_, __) =>
                              const SizedBox(height: 8),
                          itemBuilder: (context, index) {
                            final bill = bills[index];
                            return Card(
                              child: ListTile(
                                onTap: () => _openForm(context, bill: bill),
                                title: Text(bill.vendorName),
                                subtitle: Text(
                                    '${bill.category} · ${bill.billNumber}'),
                                trailing: Column(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  crossAxisAlignment: CrossAxisAlignment.end,
                                  children: [
                                    Text(formatter.format(bill.totalAmount),
                                        style: const TextStyle(
                                            fontWeight: FontWeight.w600)),
                                    if (!bill.isPaid)
                                      TextButton(
                                        onPressed: () => _openPayment(
                                            context, bill, formatter),
                                        style: TextButton.styleFrom(
                                            padding: EdgeInsets.zero,
                                            minimumSize: const Size(0, 24)),
                                        child: Text(
                                          'Due ${formatter.format(bill.dueAmount)}',
                                          style: TextStyle(
                                              fontSize: 12,
                                              color: bill.isOverdue
                                                  ? Colors.red
                                                  : Colors.orange.shade800),
                                        ),
                                      )
                                    else
                                      const Text('Paid',
                                          style: TextStyle(
                                              fontSize: 12,
                                              color: Colors.green)),
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
      ),
    );
  }
}

/// Desktop composition for accounts payable: Vendor | Bill | Category |
/// Amount | Due/Paid — falls back to the mobile card list below the desktop
/// breakpoint.
class _DesktopPayablesTable extends StatelessWidget {
  const _DesktopPayablesTable(
      {required this.bills,
      required this.formatter,
      required this.onTap,
      required this.onPay});

  final List<VendorBillModel> bills;
  final CurrencyFormatter formatter;
  final void Function(VendorBillModel) onTap;
  final void Function(VendorBillModel) onPay;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final headerStyle = TextStyle(
        fontWeight: FontWeight.w600,
        fontSize: 12.5,
        color: scheme.onSurfaceVariant);

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(0, 4, 0, 80),
      itemCount: bills.length + 1,
      itemBuilder: (context, index) {
        if (index == 0) {
          return Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
                border:
                    Border(bottom: BorderSide(color: scheme.outlineVariant))),
            child: Row(
              children: [
                Expanded(flex: 3, child: Text('VENDOR', style: headerStyle)),
                Expanded(flex: 3, child: Text('BILL', style: headerStyle)),
                Expanded(
                    flex: 2,
                    child: Text('AMOUNT',
                        textAlign: TextAlign.end, style: headerStyle)),
                Expanded(
                    flex: 2,
                    child: Text('STATUS',
                        textAlign: TextAlign.end, style: headerStyle)),
              ],
            ),
          );
        }

        final bill = bills[index - 1];
        return InkWell(
          onTap: () => onTap(bill),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
              border: Border(
                  bottom: BorderSide(
                      color: scheme.outlineVariant.withValues(alpha: 0.5))),
            ),
            child: Row(
              children: [
                Expanded(
                  flex: 3,
                  child: Text(bill.vendorName,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                          fontWeight: FontWeight.w600, fontSize: 13.5)),
                ),
                Expanded(
                  flex: 3,
                  child: Text('${bill.category} · ${bill.billNumber}',
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                          fontSize: 13, color: scheme.onSurfaceVariant)),
                ),
                Expanded(
                  flex: 2,
                  child: Text(
                    formatter.format(bill.totalAmount),
                    textAlign: TextAlign.end,
                    style: const TextStyle(
                        fontWeight: FontWeight.w600, fontSize: 13.5),
                  ),
                ),
                Expanded(
                  flex: 2,
                  child: bill.isPaid
                      ? const Text('Paid',
                          textAlign: TextAlign.end,
                          style: TextStyle(
                              fontSize: 12.5,
                              color: Colors.green,
                              fontWeight: FontWeight.w600))
                      : Align(
                          alignment: Alignment.centerRight,
                          child: TextButton(
                            onPressed: () => onPay(bill),
                            style: TextButton.styleFrom(
                                padding: EdgeInsets.zero,
                                minimumSize: const Size(0, 24)),
                            child: Text(
                              'Due ${formatter.format(bill.dueAmount)}',
                              style: TextStyle(
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w600,
                                  color: bill.isOverdue
                                      ? Colors.red
                                      : Colors.orange.shade800),
                            ),
                          ),
                        ),
                ),
              ],
            ),
          ),
        );
      },
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
            Text(label,
                style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
            const SizedBox(height: 4),
            Text(value,
                style:
                    const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          ],
        ),
      ),
    );
  }
}
