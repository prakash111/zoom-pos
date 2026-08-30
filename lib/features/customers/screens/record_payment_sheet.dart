import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/utils/currency_formatter.dart';
import '../customers_repository.dart';

const _paymentMethods = [
  ('cash', 'Cash', Icons.payments_outlined),
  ('card', 'Card', Icons.credit_card_outlined),
  ('bank_transfer', 'Bank transfer', Icons.account_balance_outlined),
];

/// Bottom sheet for POST /customers/{id}/payment — applies a receivable
/// payment against a customer's outstanding balance (PosSyncApiController::
/// customerRecordPayment). Pops with `true` when the payment is saved.
class RecordPaymentSheet extends StatefulWidget {
  const RecordPaymentSheet({
    super.key,
    required this.repository,
    required this.customerId,
    required this.balanceDue,
    required this.formatter,
  });

  final CustomersRepository repository;
  final String customerId;
  final double balanceDue;
  final CurrencyFormatter formatter;

  @override
  State<RecordPaymentSheet> createState() => _RecordPaymentSheetState();
}

class _RecordPaymentSheetState extends State<RecordPaymentSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _amountController;
  final _referenceController = TextEditingController();
  final _notesController = TextEditingController();
  String _method = 'cash';
  bool _isSaving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _amountController = TextEditingController(text: widget.balanceDue.toStringAsFixed(2));
  }

  @override
  void dispose() {
    _amountController.dispose();
    _referenceController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isSaving = true;
      _error = null;
    });

    try {
      await widget.repository.recordPayment(
        customerId: widget.customerId,
        amount: double.parse(_amountController.text),
        paymentMethod: _method,
        reference: _referenceController.text.trim(),
        notes: _notesController.text.trim(),
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _isSaving = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Record payment', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 4),
              Text(
                'Balance due: ${widget.formatter.format(widget.balanceDue)}',
                style: TextStyle(color: Colors.grey.shade600),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _amountController,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(labelText: 'Amount', prefixText: widget.formatter.symbol),
                validator: (value) {
                  final parsed = double.tryParse(value ?? '');
                  if (parsed == null || parsed <= 0) return 'Enter a valid amount';
                  return null;
                },
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 8,
                children: [
                  for (final method in _paymentMethods)
                    ChoiceChip(
                      label: Text(method.$2),
                      avatar: Icon(method.$3, size: 16),
                      selected: _method == method.$1,
                      onSelected: (_) => setState(() => _method = method.$1),
                    ),
                ],
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _referenceController,
                decoration: const InputDecoration(labelText: 'Reference (optional)'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _notesController,
                decoration: const InputDecoration(labelText: 'Notes (optional)'),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(_error!, style: TextStyle(color: Colors.red.shade400)),
              ],
              const SizedBox(height: 20),
              ElevatedButton(
                onPressed: _isSaving ? null : _submit,
                child: _isSaving
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : const Text('Save payment'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
