import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/models/vendor_bill_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../payables_provider.dart';

const _paymentMethods = ['cash', 'bank_transfer', 'card', 'upi', 'cheque', 'other'];

/// Dialog for POST /payables/{id}/pay.
class RecordBillPaymentSheet extends StatefulWidget {
  const RecordBillPaymentSheet({super.key, required this.bill, required this.formatter});

  final VendorBillModel bill;
  final CurrencyFormatter formatter;

  @override
  State<RecordBillPaymentSheet> createState() => _RecordBillPaymentSheetState();
}

class _RecordBillPaymentSheetState extends State<RecordBillPaymentSheet> {
  late final TextEditingController _amountController;
  final _referenceController = TextEditingController();
  String _method = _paymentMethods.first;

  @override
  void initState() {
    super.initState();
    _amountController = TextEditingController(text: widget.bill.dueAmount.toStringAsFixed(2));
  }

  @override
  void dispose() {
    _amountController.dispose();
    _referenceController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final amount = double.tryParse(_amountController.text);
    if (amount == null || amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Enter a valid amount.')));
      return;
    }

    final payables = context.read<PayablesProvider>();
    final success = await payables.recordPayment(
      billId: widget.bill.id,
      amount: amount,
      paymentMethod: _method,
      paymentDate: DateTime.now(),
      referenceNumber: _referenceController.text.trim(),
    );

    if (!mounted) return;
    if (success) {
      Navigator.of(context).pop();
    } else if (payables.actionError != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(payables.actionError!)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final payables = context.watch<PayablesProvider>();

    return AlertDialog(
      title: Text('Pay ${widget.bill.vendorName}'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('Due: ${widget.formatter.format(widget.bill.dueAmount)}'),
          const SizedBox(height: 12),
          TextField(
            controller: _amountController,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Amount'),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _method,
            decoration: const InputDecoration(labelText: 'Payment method'),
            items: [for (final m in _paymentMethods) DropdownMenuItem(value: m, child: Text(m.replaceAll('_', ' ')))],
            onChanged: (value) => setState(() => _method = value ?? _method),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _referenceController,
            decoration: const InputDecoration(labelText: 'Reference (optional)'),
          ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Cancel')),
        FilledButton(
          onPressed: payables.isSaving ? null : _submit,
          child: payables.isSaving
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : const Text('Record payment'),
        ),
      ],
    );
  }
}
