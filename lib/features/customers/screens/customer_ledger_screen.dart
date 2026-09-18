import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';

import '../../../core/models/customer_model.dart';
import '../../../core/models/ledger_entry_model.dart';
import '../../../core/services/tenant_time_service.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/adaptive_sheet.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../customers_repository.dart';
import 'record_payment_sheet.dart';

final _dateFormat = DateFormat('MMM d, y');

/// One customer's transaction history — GET /customers/{id}/ledger — plus
/// the entry point for recording a receivable payment. Manages its own
/// loading state locally rather than through [CustomersProvider] since it's
/// scoped to a single customer (see CustomersProvider's doc comment).
class CustomerLedgerScreen extends StatefulWidget {
  const CustomerLedgerScreen(
      {super.key, required this.customer, required this.repository});

  final CustomerModel customer;
  final CustomersRepository repository;

  @override
  State<CustomerLedgerScreen> createState() => _CustomerLedgerScreenState();
}

class _CustomerLedgerScreenState extends State<CustomerLedgerScreen> {
  late Future<CustomerLedger> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = widget.repository.fetchLedger(widget.customer.id);
  }

  Future<void> _recordPayment(
      CustomerModel customer, CurrencyFormatter formatter) async {
    final saved = await showAdaptiveSheet<bool>(
      context,
      builder: (_) => RecordPaymentSheet(
        repository: widget.repository,
        customerId: customer.id,
        balanceDue: customer.balanceDue,
        formatter: formatter,
      ),
    );

    if (saved == true && mounted) {
      setState(_load);
    }
  }

  @override
  Widget build(BuildContext context) {
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    return Scaffold(
      appBar: AppBar(title: Text(widget.customer.name)),
      body: FutureBuilder<CustomerLedger>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const LoadingIndicator();
          }
          if (snapshot.hasError) {
            return ErrorView(
              message: 'Could not load this customer\'s ledger.',
              onRetry: () => setState(_load),
            );
          }

          final ledger = snapshot.data!;
          return RefreshIndicator(
            onRefresh: () async => setState(_load),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
              children: [
                _BalanceCard(customer: ledger.customer, formatter: formatter),
                const SizedBox(height: 12),
                if (ledger.customer.hasBalanceDue)
                  ElevatedButton.icon(
                    onPressed: () => _recordPayment(ledger.customer, formatter),
                    icon: const Icon(Icons.add),
                    label: const Text('Record payment'),
                  ),
                const SizedBox(height: 20),
                Text('Transaction history',
                    style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                if (ledger.entries.isEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 24),
                    child: Center(
                        child: Text('No transactions yet.',
                            style: TextStyle(color: Colors.grey.shade600))),
                  )
                else
                  for (final entry in ledger.entries) ...[
                    _LedgerTile(entry: entry, formatter: formatter),
                    const SizedBox(height: 8),
                  ],
              ],
            ),
          );
        },
      ),
    );
  }
}

class _BalanceCard extends StatelessWidget {
  const _BalanceCard({required this.customer, required this.formatter});

  final CustomerModel customer;
  final CurrencyFormatter formatter;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Balance due',
                        style: TextStyle(color: Colors.grey.shade600)),
                    Text(
                      formatter.format(customer.balanceDue),
                      style: TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.bold,
                        color: customer.hasBalanceDue
                            ? Colors.red.shade400
                            : Colors.green.shade600,
                      ),
                    ),
                  ],
                ),
                if (customer.loyaltyPoints > 0)
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text('Loyalty points',
                          style: TextStyle(color: Colors.grey.shade600)),
                      Text('${customer.loyaltyPoints}',
                          style: const TextStyle(
                              fontSize: 18, fontWeight: FontWeight.bold)),
                    ],
                  ),
              ],
            ),
            if (customer.phone.isNotEmpty || customer.email.isNotEmpty) ...[
              const Divider(height: 24),
              if (customer.phone.isNotEmpty) Text(customer.phone),
              if (customer.email.isNotEmpty) Text(customer.email),
            ],
          ],
        ),
      ),
    );
  }
}

class _LedgerTile extends StatelessWidget {
  const _LedgerTile({required this.entry, required this.formatter});

  final LedgerEntryModel entry;
  final CurrencyFormatter formatter;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final date = entry.date == null
        ? ''
        : _dateFormat
            .format(TenantTimeService.instance.toTenantTime(entry.date!));
    final invoiceSurface =
        isDark ? const Color(0xFF182230) : theme.colorScheme.surface;
    final paymentSurface =
        isDark ? const Color(0xFF132A24) : Colors.green.shade50;
    final invoiceBorder = isDark ? const Color(0xFF334155) : theme.dividerColor;
    final paymentBorder = isDark
        ? const Color(0xFF10B981).withValues(alpha: 0.3)
        : Colors.green.shade200;
    final primaryText =
        isDark ? const Color(0xFFF8FAFC) : theme.colorScheme.onSurface;
    final secondaryText =
        isDark ? const Color(0xFFCBD5E1) : theme.colorScheme.onSurfaceVariant;

    if (entry.isInvoice) {
      return Card(
        color: invoiceSurface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
          side: BorderSide(color: invoiceBorder),
        ),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Icon(Icons.receipt_long_outlined,
                  color: Theme.of(context).colorScheme.primary),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Sale #${entry.saleNumber}',
                      style: TextStyle(
                        color: primaryText,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    Text(date,
                        style: TextStyle(color: secondaryText, fontSize: 12)),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    formatter.format(entry.total ?? 0),
                    style: TextStyle(
                      color: primaryText,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  if ((entry.dueAmount ?? 0) > 0)
                    Text('${formatter.format(entry.dueAmount!)} due',
                        style:
                            TextStyle(color: Colors.red.shade400, fontSize: 12))
                  else
                    Text('Paid',
                        style: TextStyle(
                            color: Colors.green.shade600, fontSize: 12)),
                ],
              ),
            ],
          ),
        ),
      );
    }

    return Card(
      color: paymentSurface,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: BorderSide(color: paymentBorder),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Icon(
              Icons.payments_outlined,
              color: isDark ? const Color(0xFF34D399) : Colors.green.shade700,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Payment · Sale #${entry.saleNumber}',
                    style: TextStyle(
                      color: primaryText,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  Text(
                    [
                      date,
                      if (entry.paymentMethod != null) entry.paymentMethod!
                    ].join(' · '),
                    style: TextStyle(color: secondaryText, fontSize: 12),
                  ),
                ],
              ),
            ),
            Text(
              '+${formatter.format(entry.amount ?? 0)}',
              style: TextStyle(
                fontWeight: FontWeight.w600,
                color: isDark ? const Color(0xFF6EE7B7) : Colors.green.shade700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
