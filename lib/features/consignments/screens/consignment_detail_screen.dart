import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/consignment_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../consignments_repository.dart';

const _paymentMethods = ['cash', 'card', 'bank_transfer', 'upi'];

/// Consignment detail: dispatch (draft), reconcile returned/sold quantities
/// (dispatched), and finalize to a completed sale (reconciled or later).
/// Mirrors app/Livewire/Tenant/Consignments/Show.php's per-item math exactly.
class ConsignmentDetailScreen extends StatefulWidget {
  const ConsignmentDetailScreen({super.key, required this.consignmentId, required this.repository, required this.formatter});

  final String consignmentId;
  final ConsignmentsRepository repository;
  final CurrencyFormatter formatter;

  @override
  State<ConsignmentDetailScreen> createState() => _ConsignmentDetailScreenState();
}

class _ConsignmentDetailScreenState extends State<ConsignmentDetailScreen> {
  ConsignmentModel? _consignment;
  final Map<String, double> _returnedInputs = {};
  bool _loading = true;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final consignment = await widget.repository.fetchConsignment(widget.consignmentId);
      setState(() {
        _consignment = consignment;
        _returnedInputs
          ..clear()
          ..addEntries(consignment.items.map((i) => MapEntry(i.id, i.returnedQuantity)));
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  double _soldQty(ConsignmentItemModel item) {
    final returned = (_returnedInputs[item.id] ?? item.returnedQuantity).clamp(0, item.dispatchedQuantity);
    return (item.dispatchedQuantity - returned).clamp(0, item.dispatchedQuantity).toDouble();
  }

  double get _projectedSoldRevenue {
    final consignment = _consignment;
    if (consignment == null) return 0;
    return consignment.items.fold(0.0, (sum, item) => sum + (_soldQty(item) * item.unitPrice));
  }

  Future<void> _dispatch() async {
    setState(() => _busy = true);
    try {
      await widget.repository.dispatchConsignment(widget.consignmentId);
      await _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _saveReconciliation() async {
    final consignment = _consignment;
    if (consignment == null) return;

    setState(() => _busy = true);
    try {
      await widget.repository.reconcile(
        widget.consignmentId,
        consignment.items
            .map((item) => {'id': item.id, 'returned_qty': _returnedInputs[item.id] ?? item.returnedQuantity})
            .toList(),
      );
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Reconciliation saved.')));
      await _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _finalize() async {
    if (_projectedSoldRevenue <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Nothing to bill — all items are marked returned.')));
      return;
    }

    final method = await showDialog<String>(
      context: context,
      builder: (context) {
        String selected = _paymentMethods.first;
        return StatefulBuilder(
          builder: (context, setDialogState) => AlertDialog(
            title: const Text('Finalize to sale'),
            content: DropdownButtonFormField<String>(
              value: selected,
              decoration: const InputDecoration(labelText: 'Payment method'),
              items: [for (final m in _paymentMethods) DropdownMenuItem(value: m, child: Text(m.replaceAll('_', ' ')))],
              onChanged: (value) => setDialogState(() => selected = value ?? selected),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Cancel')),
              FilledButton(onPressed: () => Navigator.of(context).pop(selected), child: const Text('Finalize')),
            ],
          ),
        );
      },
    );
    if (method == null) return;

    setState(() => _busy = true);
    try {
      // Reconcile current inputs first so the finalize math matches what's shown.
      final consignment = _consignment!;
      await widget.repository.reconcile(
        widget.consignmentId,
        consignment.items
            .map((item) => {'id': item.id, 'returned_qty': _returnedInputs[item.id] ?? item.returnedQuantity})
            .toList(),
      );
      final sale = await widget.repository.finalize(widget.consignmentId, method);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Finalized to sale #${sale['sale_number']}.')));
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final consignment = _consignment;

    return Scaffold(
      appBar: AppBar(title: Text(consignment?.consignmentNumber ?? 'Consignment')),
      body: _loading
          ? const LoadingIndicator()
          : _error != null
              ? Center(child: Text(_error!))
              : consignment == null
                  ? const SizedBox.shrink()
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(consignment.customerName, style: Theme.of(context).textTheme.titleMedium),
                            Chip(label: Text(consignment.status.toUpperCase())),
                          ],
                        ),
                        const SizedBox(height: 16),
                        if (consignment.isDraft)
                          Card(
                            color: Colors.blue.shade50,
                            child: ListTile(
                              leading: const Icon(Icons.local_shipping_outlined),
                              title: const Text('Ready to dispatch'),
                              subtitle: const Text('Goods have not left the store yet.'),
                              trailing: ElevatedButton(onPressed: _busy ? null : _dispatch, child: const Text('Dispatch')),
                            ),
                          ),
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('Items', style: Theme.of(context).textTheme.titleMedium),
                                const Divider(),
                                ...consignment.items.map((item) {
                                  final returned = _returnedInputs[item.id] ?? item.returnedQuantity;
                                  final sold = _soldQty(item);
                                  final editable = consignment.isDispatched || consignment.isReconciled;

                                  return Padding(
                                    padding: const EdgeInsets.symmetric(vertical: 6),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(item.productName, style: const TextStyle(fontWeight: FontWeight.w600)),
                                        Text(
                                          'Dispatched ${item.dispatchedQuantity.toStringAsFixed(0)} · ${widget.formatter.format(item.unitPrice)} each',
                                          style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                                        ),
                                        if (editable)
                                          Row(
                                            children: [
                                              const Text('Returned: '),
                                              SizedBox(
                                                width: 70,
                                                child: TextFormField(
                                                  initialValue: returned.toStringAsFixed(0),
                                                  keyboardType: TextInputType.number,
                                                  decoration: const InputDecoration(isDense: true),
                                                  onChanged: (value) => setState(() {
                                                    _returnedInputs[item.id] = double.tryParse(value) ?? returned;
                                                  }),
                                                ),
                                              ),
                                              const SizedBox(width: 12),
                                              Text('Sold: ${sold.toStringAsFixed(0)} = ${widget.formatter.format(sold * item.unitPrice)}'),
                                            ],
                                          )
                                        else
                                          Text('Returned ${item.returnedQuantity.toStringAsFixed(0)} · Sold ${item.soldQuantity.toStringAsFixed(0)} = ${widget.formatter.format(item.soldTotal)}'),
                                      ],
                                    ),
                                  );
                                }),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),
                        if (consignment.isDispatched || consignment.isReconciled) ...[
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Projected sold revenue', style: TextStyle(fontWeight: FontWeight.bold)),
                              Text(widget.formatter.format(_projectedSoldRevenue), style: const TextStyle(fontWeight: FontWeight.bold)),
                            ],
                          ),
                          const SizedBox(height: 12),
                          OutlinedButton(onPressed: _busy ? null : _saveReconciliation, child: const Text('Save reconciliation')),
                          const SizedBox(height: 8),
                          ElevatedButton(onPressed: _busy ? null : _finalize, child: const Text('Finalize to sale')),
                        ],
                        if (consignment.isFinalized)
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Text('Sold total: ${widget.formatter.format(consignment.totalSoldAmount)}', style: const TextStyle(fontWeight: FontWeight.bold)),
                          ),
                      ],
                    ),
    );
  }
}
