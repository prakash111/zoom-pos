import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../../core/models/sale_model.dart';
import '../../../core/services/tenant_time_service.dart';
import '../../../core/services/thermal/thermal_printer_service.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../auth/auth_provider.dart';
import '../../pos/screens/invoice_actions_sheet.dart';

final _dateFormat = DateFormat('MMM d, y · h:mm a');

/// Read-only breakdown of one sale's line items and totals.
class SaleDetailScreen extends StatelessWidget {
  const SaleDetailScreen({super.key, required this.sale, required this.formatter});

  final SaleModel sale;
  final CurrencyFormatter formatter;

  InvoiceActionsData _actionsData(BuildContext context) {
    final company = context.read<AuthProvider>().company;
    return InvoiceActionsData(
      documentType: 'invoice',
      documentId: sale.id,
      documentNumber: sale.saleNumber,
      companyName: company?.tradeName ?? company?.name ?? '',
      customerName: sale.customerName,
      currencySymbol: company?.currencySymbol ?? '\$',
      subtotal: sale.total - sale.tax + sale.discount,
      discount: sale.discount,
      tax: sale.tax,
      total: sale.total,
      taxId: company?.taxId,
      taxLabel: company?.taxLabel ?? 'Tax',
      isIndia: company?.isIndia ?? false,
      taxRate: (sale.total - sale.tax) > 0 ? sale.tax / (sale.total - sale.tax) * 100 : 0,
      lines: sale.items
          .map((item) {
            final qty = ((item['quantity'] as num?) ?? (item['qty'] as num?) ?? 1).toDouble();
            final price = ((item['price'] as num?) ?? 0).toDouble();
            final total = ((item['total'] as num?) ?? (qty * price)).toDouble();
            return ReceiptLine(
              name: item['name']?.toString() ?? 'Item',
              quantity: qty,
              unitPrice: price,
              lineTotal: total,
            );
          })
          .toList(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final company = context.watch<AuthProvider>().company;
    final isIndia = company?.isIndia ?? false;

    return Scaffold(
      appBar: AppBar(
        title: Text('Sale #${sale.saleNumber}'),
        actions: [
          IconButton(
            icon: const Icon(Icons.ios_share_outlined),
            tooltip: 'Preview, print, or share',
            onPressed: () => showInvoiceActionsSheet(context, _actionsData(context)),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (sale.createdAt != null)
                    Text(_dateFormat.format(TenantTimeService.instance.toTenantTime(sale.createdAt!))),
                  if ((sale.customerName ?? '').isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text('Customer: ${sale.customerName}'),
                  ],
                  const SizedBox(height: 4),
                  Text('Payment: ${_titleCase(sale.paymentMethod)}'),
                  if (sale.isCancelled) ...[
                    const SizedBox(height: 8),
                    Text('Cancelled', style: TextStyle(color: Colors.red.shade400, fontWeight: FontWeight.w600)),
                  ],
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text('Items', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          if (sale.items.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Text('No item details available.', style: TextStyle(color: Colors.grey.shade600)),
            )
          else
            Card(
              child: Column(
                children: [
                  for (final item in sale.items) _ItemRow(item: item, formatter: formatter),
                ],
              ),
            ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  _TotalRow('Discount', formatter.format(sale.discount)),
                  if (sale.tax > 0)
                    if (isIndia) ...[
                      _TotalRow('CGST', formatter.format(sale.tax / 2)),
                      _TotalRow('SGST', formatter.format(sale.tax / 2)),
                    ] else
                      _TotalRow(company?.taxLabel ?? 'Tax', formatter.format(sale.tax)),
                  const Divider(),
                  _TotalRow('Total', formatter.format(sale.total), bold: true),
                  _TotalRow('Paid', formatter.format(sale.paidAmount)),
                  if (sale.dueAmount > 0)
                    _TotalRow('Due', formatter.format(sale.dueAmount), color: Colors.red.shade400),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _titleCase(String value) => value.isEmpty ? value : '${value[0].toUpperCase()}${value.substring(1)}';
}

class _ItemRow extends StatelessWidget {
  const _ItemRow({required this.item, required this.formatter});

  final Map<String, dynamic> item;
  final CurrencyFormatter formatter;

  @override
  Widget build(BuildContext context) {
    final name = item['name']?.toString() ?? 'Item';
    final qty = (item['quantity'] as num?) ?? (item['qty'] as num?) ?? 1;
    final price = (item['price'] as num?) ?? 0;
    final total = (item['total'] as num?) ?? (qty.toDouble() * price.toDouble());

    return ListTile(
      title: Text(name),
      subtitle: Text('${qty.toString()} × ${formatter.format(price)}'),
      trailing: Text(formatter.format(total), style: const TextStyle(fontWeight: FontWeight.w600)),
    );
  }
}

class _TotalRow extends StatelessWidget {
  const _TotalRow(this.label, this.value, {this.bold = false, this.color});

  final String label;
  final String value;
  final bool bold;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(fontWeight: bold ? FontWeight.bold : FontWeight.normal, color: color);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: style),
          Text(value, style: style),
        ],
      ),
    );
  }
}
