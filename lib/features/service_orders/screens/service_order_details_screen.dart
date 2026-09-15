import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/service_order_model.dart';
import '../../../core/services/thermal/thermal_printer_service.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../pos/screens/invoice_actions_sheet.dart';
import '../service_orders_repository.dart';
import 'service_order_form_sheet.dart';

/// Read-only details view for a single service order (repair/warranty
/// ticket): customer, device/item info, diagnosis, parts/charges, and a
/// visual status timeline, with quick access to edit/status-change/delete.
///
/// Pops `true` if the order was edited/deleted/its status changed, so the
/// list screen that pushed this route knows to reload.
class ServiceOrderDetailsScreen extends StatefulWidget {
  const ServiceOrderDetailsScreen({
    super.key,
    required this.repository,
    required this.formatter,
    required this.order,
  });

  final ServiceOrdersRepository repository;
  final CurrencyFormatter formatter;
  final ServiceOrderModel order;

  @override
  State<ServiceOrderDetailsScreen> createState() =>
      _ServiceOrderDetailsScreenState();
}

class _ServiceOrderDetailsScreenState extends State<ServiceOrderDetailsScreen> {
  late ServiceOrderModel _order;
  bool _isLoading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _order = widget.order;
  }

  Future<void> _refresh() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final fresh = await widget.repository.fetchOrder(_order.id);
      if (mounted) setState(() => _order = fresh);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _edit() async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ServiceOrderFormSheet(
          repository: widget.repository,
          formatter: widget.formatter,
          order: _order),
    );
    if (saved == true) {
      await _refresh();
    }
  }

  void _openInvoiceActions({Map<String, dynamic>? envelope}) {
    final postData = envelope ?? _order.postSaleSheet?['data'];
    InvoiceActionsData data;

    if (postData is Map<String, dynamic>) {
      final lines = <ReceiptLine>[];
      final rawLines = postData['lines'];
      if (rawLines is List) {
        for (final l in rawLines) {
          if (l is Map) {
            lines.add(ReceiptLine(
              name: (l['name'] ?? 'Item').toString(),
              quantity: ((l['quantity'] as num?) ?? 1).toDouble(),
              unitPrice: ((l['unit_price'] as num?) ?? 0).toDouble(),
              lineTotal: ((l['line_total'] as num?) ?? 0).toDouble(),
            ));
          }
        }
      }

      data = InvoiceActionsData(
        documentType: 'invoice',
        documentId:
            (postData['sale_id'] ?? _order.saleId ?? _order.id).toString(),
        documentNumber: (postData['invoice_number'] ??
                _order.invoiceNumber ??
                'SO-${_order.orderNumber}')
            .toString(),
        batchDispatchEndpoint:
            postData['batch_dispatch_endpoint']?.toString() ??
                '/api/v1/tenant/dispatch/batch-send',
        companyName: (postData['company_name'] ?? 'Service Center').toString(),
        lines: lines,
        subtotal: ((postData['subtotal'] as num?) ??
                (_order.partsTotal + _order.laborCost))
            .toDouble(),
        discount:
            ((postData['discount'] as num?) ?? _order.discount).toDouble(),
        tax: ((postData['tax'] as num?) ?? _order.taxAmount).toDouble(),
        total: ((postData['total'] as num?) ?? _order.totalAmount).toDouble(),
        customerName:
            postData['customer_name']?.toString() ?? _order.customerName,
        customerPhone:
            postData['customer_phone']?.toString() ?? _order.customerPhone,
        customerEmail:
            postData['customer_email']?.toString() ?? _order.customerEmail,
        currencySymbol: postData['currency_symbol']?.toString() ?? '\$',
        taxRate: ((postData['tax_rate'] as num?) ?? _order.taxRate).toDouble(),
      );
    } else {
      final lines = <ReceiptLine>[];
      for (final p in _order.partsUsed) {
        final q = ((p['quantity'] as num?) ?? 1).toDouble();
        final u = ((p['unit_price'] as num?) ?? 0).toDouble();
        lines.add(ReceiptLine(
          name: (p['name'] ?? 'Part').toString(),
          quantity: q,
          unitPrice: u,
          lineTotal: q * u,
        ));
      }
      if (_order.laborCost > 0) {
        lines.add(ReceiptLine(
          name: 'Labor Charges',
          quantity: 1,
          unitPrice: _order.laborCost,
          lineTotal: _order.laborCost,
        ));
      }

      data = InvoiceActionsData(
        documentType: 'invoice',
        documentId: _order.saleId ?? _order.id,
        documentNumber: _order.invoiceNumber ?? 'SO-${_order.orderNumber}',
        batchDispatchEndpoint: '/api/v1/tenant/dispatch/batch-send',
        companyName: 'Service Center',
        lines: lines,
        subtotal: _order.partsTotal + _order.laborCost,
        discount: _order.discount,
        tax: _order.taxAmount,
        total: _order.totalAmount,
        customerName: _order.customerName,
        customerPhone: _order.customerPhone,
        customerEmail: _order.customerEmail,
        currencySymbol: '\$',
        taxRate: _order.taxRate,
      );
    }

    showInvoiceActionsSheet(context, data);
  }

  Future<void> _changeStatus(String status) async {
    try {
      final updated = await widget.repository.updateStatus(_order.id, status);
      if (mounted) {
        setState(() => _order = updated);
        if (status == 'delivered_settled' || updated.showPostSaleSheet) {
          _openInvoiceActions(envelope: updated.postSaleSheet?['data']);
        }
      }
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _delete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Delete order #${_order.orderNumber}?'),
        content: const Text('This cannot be undone.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Cancel')),
          TextButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text('Delete')),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await widget.repository.deleteOrder(_order.id);
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('#${_order.orderNumber}'),
        actions: [
          IconButton(
              icon: const Icon(Icons.edit_outlined),
              tooltip: 'Edit',
              onPressed: _edit),
          PopupMenuButton<String>(
            onSelected: (value) =>
                value == 'delete' ? _delete() : _changeStatus(value),
            itemBuilder: (context) => [
              for (final s in kServiceOrderStatuses)
                if (s != _order.status)
                  PopupMenuItem(
                      value: s,
                      child: Text('Mark ${kServiceOrderStatusLabels[s]}')),
              const PopupMenuDivider(),
              const PopupMenuItem(value: 'delete', child: Text('Delete')),
            ],
          ),
        ],
      ),
      body: _isLoading
          ? const LoadingIndicator()
          : _error != null
              ? ErrorView(message: _error!, onRetry: _refresh)
              : RefreshIndicator(
                  onRefresh: _refresh,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      _StatusTimeline(status: _order.status),
                      if (_order.status == 'delivered_settled') ...[
                        const SizedBox(height: 16),
                        Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: Colors.green.shade50,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: Colors.green.shade200),
                          ),
                          child: Row(
                            children: [
                              Icon(Icons.check_circle,
                                  color: Colors.green.shade700, size: 28),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Delivered & Settled',
                                      style: TextStyle(
                                        fontWeight: FontWeight.bold,
                                        color: Colors.green.shade900,
                                      ),
                                    ),
                                    Text(
                                      _order.invoiceNumber != null
                                          ? 'Linked to Invoice #${_order.invoiceNumber}'
                                          : 'Payment settled and invoice generated',
                                      style: TextStyle(
                                        fontSize: 12,
                                        color: Colors.green.shade800,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              ElevatedButton.icon(
                                onPressed: () => _openInvoiceActions(),
                                icon: const Icon(Icons.share, size: 16),
                                label: const Text('Invoice'),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.green.shade700,
                                  foregroundColor: Colors.white,
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 12, vertical: 8),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                      const SizedBox(height: 20),
                      _SectionCard(
                        title: 'Customer',
                        icon: Icons.person_outline,
                        children: [
                          _InfoRow(
                              'Name',
                              _order.customerName.isEmpty
                                  ? '—'
                                  : _order.customerName),
                          if (_order.customerPhone.isNotEmpty)
                            _InfoRow('Phone', _order.customerPhone),
                          if (_order.customerEmail.isNotEmpty)
                            _InfoRow('Email', _order.customerEmail),
                        ],
                      ),
                      const SizedBox(height: 12),
                      _SectionCard(
                        title: 'Device / Item',
                        icon: Icons.devices_other_outlined,
                        children: [
                          _InfoRow(
                              'Equipment',
                              _order.equipmentName.isEmpty
                                  ? '—'
                                  : _order.equipmentName),
                          if (_order.brandModel.isNotEmpty)
                            _InfoRow('Brand / Model', _order.brandModel),
                          if (_order.serialNumber.isNotEmpty)
                            _InfoRow('Serial Number', _order.serialNumber),
                        ],
                      ),
                      if (_order.extraAttributes.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        _SectionCard(
                          title: 'Specifications',
                          icon: Icons.tune_outlined,
                          children: [
                            for (final entry in _order.extraAttributes.entries)
                              _InfoRow(
                                  entry.key, entry.value?.toString() ?? '—',
                                  emphasize: true),
                          ],
                        ),
                      ],
                      const SizedBox(height: 12),
                      _SectionCard(
                        title: 'Diagnosis',
                        icon: Icons.medical_information_outlined,
                        children: [
                          _InfoRow(
                              'Status',
                              _order.statusLabel.isEmpty
                                  ? kServiceOrderStatusLabels[_order.status] ??
                                      _order.status
                                  : _order.statusLabel),
                          _InfoRow('Priority', _order.priority),
                          const SizedBox(height: 8),
                          const Text('Reported Issue',
                              style: TextStyle(
                                  fontWeight: FontWeight.w600, fontSize: 12)),
                          const SizedBox(height: 2),
                          Text(_order.reportedDefect.isEmpty
                              ? 'No issue description provided.'
                              : _order.reportedDefect),
                          if (_order.technicalDiagnosis.isNotEmpty) ...[
                            const SizedBox(height: 8),
                            const Text('Technical Diagnosis',
                                style: TextStyle(
                                    fontWeight: FontWeight.w600, fontSize: 12)),
                            const SizedBox(height: 2),
                            Text(_order.technicalDiagnosis),
                          ],
                          if ((_order.notes ?? '').isNotEmpty) ...[
                            const SizedBox(height: 8),
                            const Text('Notes',
                                style: TextStyle(
                                    fontWeight: FontWeight.w600, fontSize: 12)),
                            const SizedBox(height: 2),
                            Text(_order.notes!),
                          ],
                        ],
                      ),
                      const SizedBox(height: 12),
                      _SectionCard(
                        title: 'Charges',
                        icon: Icons.receipt_long_outlined,
                        children: [
                          for (final part in _order.partsUsed)
                            Padding(
                              padding: const EdgeInsets.symmetric(vertical: 2),
                              child: Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  Expanded(
                                    child: Text(
                                      '${part['name'] ?? 'Part'} × ${part['quantity'] ?? 1}',
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                  Text(widget.formatter.format(
                                    ((part['quantity'] as num?) ?? 1) *
                                        ((part['unit_price'] as num?) ?? 0),
                                  )),
                                ],
                              ),
                            ),
                          if (_order.partsUsed.isNotEmpty)
                            const Divider(height: 16),
                          _InfoRow('Parts Total',
                              widget.formatter.format(_order.partsTotal)),
                          _InfoRow('Labor Cost',
                              widget.formatter.format(_order.laborCost)),
                          if (_order.discount > 0)
                            _InfoRow('Discount',
                                '-${widget.formatter.format(_order.discount)}'),
                          if (_order.taxAmount > 0 || _order.taxRate > 0)
                            _InfoRow(
                              _order.isTaxInclusive
                                  ? 'Tax (${_order.taxRate.toStringAsFixed(0)}% incl.)'
                                  : 'Tax (${_order.taxRate.toStringAsFixed(0)}%)',
                              _order.isTaxInclusive
                                  ? widget.formatter.format(_order.taxAmount)
                                  : '+${widget.formatter.format(_order.taxAmount)}',
                            ),
                          const Divider(height: 16),
                          _InfoRow(
                            'Total Amount',
                            widget.formatter.format(_order.totalAmount),
                            emphasize: true,
                          ),
                        ],
                      ),
                      if (_order.warrantyPeriod.isNotEmpty ||
                          _order.warrantyTerms.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        _SectionCard(
                          title: 'Warranty',
                          icon: Icons.verified_user_outlined,
                          children: [
                            if (_order.warrantyPeriod.isNotEmpty)
                              _InfoRow('Period', _order.warrantyPeriod),
                            if (_order.warrantyTerms.isNotEmpty)
                              _InfoRow('Terms', _order.warrantyTerms),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard(
      {required this.title, required this.icon, required this.children});

  final String title;
  final IconData icon;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon,
                    size: 18, color: Theme.of(context).colorScheme.primary),
                const SizedBox(width: 8),
                Text(title,
                    style: const TextStyle(
                        fontWeight: FontWeight.bold, fontSize: 15)),
              ],
            ),
            const SizedBox(height: 12),
            ...children,
          ],
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow(this.label, this.value, {this.emphasize = false});

  final String label;
  final String value;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label,
              style: TextStyle(
                  color: Colors.grey.shade600, fontSize: emphasize ? 14 : 13)),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: TextStyle(
                  fontWeight: emphasize ? FontWeight.bold : FontWeight.w500,
                  fontSize: emphasize ? 16 : 13),
            ),
          ),
        ],
      ),
    );
  }
}

/// A visual stepper across the non-terminal statuses, showing progress up to
/// the order's current status. There is no server-side status-change log, so
/// this reflects position within the standard workflow rather than a
/// timestamped history. Cancelled orders get a distinct single-step badge.
class _StatusTimeline extends StatelessWidget {
  const _StatusTimeline({required this.status});

  final String status;

  static const _flow = [
    'received',
    'under_diagnosis',
    'waiting_parts_approval',
    'ready_for_pickup',
    'delivered_settled',
  ];

  @override
  Widget build(BuildContext context) {
    if (status == 'cancelled') {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
        decoration: BoxDecoration(
            color: Colors.red.shade50, borderRadius: BorderRadius.circular(10)),
        child: Row(
          children: [
            Icon(Icons.cancel_outlined, color: Colors.red.shade700, size: 18),
            const SizedBox(width: 8),
            Text('Cancelled',
                style: TextStyle(
                    color: Colors.red.shade700, fontWeight: FontWeight.w600)),
          ],
        ),
      );
    }

    final currentIndex = _flow.indexOf(status);
    final primary = Theme.of(context).colorScheme.primary;

    return Row(
      children: [
        for (var i = 0; i < _flow.length; i++) ...[
          Expanded(
            child: Column(
              children: [
                Container(
                  width: 22,
                  height: 22,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: i <= currentIndex ? primary : Colors.grey.shade300,
                  ),
                  child: i < currentIndex
                      ? const Icon(Icons.check, size: 14, color: Colors.white)
                      : null,
                ),
                const SizedBox(height: 6),
                Text(
                  kServiceOrderStatusLabels[_flow[i]] ?? _flow[i],
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight:
                        i == currentIndex ? FontWeight.bold : FontWeight.normal,
                    color: i <= currentIndex
                        ? Colors.black87
                        : Colors.grey.shade500,
                  ),
                ),
              ],
            ),
          ),
          if (i < _flow.length - 1)
            Padding(
              padding: const EdgeInsets.only(bottom: 20),
              child: Container(
                width: 16,
                height: 2,
                color: i < currentIndex ? primary : Colors.grey.shade300,
              ),
            ),
        ],
      ],
    );
  }
}
