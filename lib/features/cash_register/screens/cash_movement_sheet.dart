import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../cash_register_provider.dart';

const _cashInCategories = ['suprimento', 'change_injection', 'other'];
const _cashOutCategories = ['sangria', 'petty_cash', 'supplier_payment', 'bank_drop', 'other'];

/// Dialog for POST /cash-register/{id}/transaction.
class CashMovementSheet extends StatefulWidget {
  const CashMovementSheet({super.key, required this.type});

  final String type; // cash_in | cash_out

  @override
  State<CashMovementSheet> createState() => _CashMovementSheetState();
}

class _CashMovementSheetState extends State<CashMovementSheet> {
  final _amountController = TextEditingController();
  final _reasonController = TextEditingController();
  late String _category;

  @override
  void initState() {
    super.initState();
    _category = widget.type == 'cash_in' ? _cashInCategories.first : _cashOutCategories.first;
  }

  @override
  void dispose() {
    _amountController.dispose();
    _reasonController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final amount = double.tryParse(_amountController.text);
    if (amount == null || amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Enter a valid amount.')));
      return;
    }

    final register = context.read<CashRegisterProvider>();
    final success = await register.recordTransaction(
      type: widget.type,
      category: _category,
      amount: amount,
      reason: _reasonController.text.trim(),
    );

    if (!mounted) return;
    if (success) {
      Navigator.of(context).pop();
    } else if (register.actionError != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(register.actionError!)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final register = context.watch<CashRegisterProvider>();
    final categories = widget.type == 'cash_in' ? _cashInCategories : _cashOutCategories;
    final title = widget.type == 'cash_in' ? 'Record cash in' : 'Record cash out';

    return AlertDialog(
      title: Text(title),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          DropdownButtonFormField<String>(
            value: _category,
            decoration: const InputDecoration(labelText: 'Category'),
            items: [for (final c in categories) DropdownMenuItem(value: c, child: Text(c.replaceAll('_', ' ')))],
            onChanged: (value) => setState(() => _category = value ?? _category),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _amountController,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Amount'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _reasonController,
            decoration: const InputDecoration(labelText: 'Reason (optional)'),
          ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Cancel')),
        FilledButton(
          onPressed: register.isSaving ? null : _submit,
          child: register.isSaving
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : const Text('Record'),
        ),
      ],
    );
  }
}
