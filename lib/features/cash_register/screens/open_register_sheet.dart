import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../cash_register_provider.dart';

/// Dialog for POST /cash-register/open.
class OpenRegisterSheet extends StatefulWidget {
  const OpenRegisterSheet({super.key});

  @override
  State<OpenRegisterSheet> createState() => _OpenRegisterSheetState();
}

class _OpenRegisterSheetState extends State<OpenRegisterSheet> {
  final _balanceController = TextEditingController(text: '0');
  final _notesController = TextEditingController();

  @override
  void dispose() {
    _balanceController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final balance = double.tryParse(_balanceController.text);
    if (balance == null || balance < 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Enter a valid opening balance.')));
      return;
    }

    final register = context.read<CashRegisterProvider>();
    final success = await register.openRegister(
      openingBalance: balance,
      openingNotes: _notesController.text.trim(),
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

    return AlertDialog(
      title: const Text('Open cash register'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            controller: _balanceController,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Opening float / starting cash'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _notesController,
            decoration: const InputDecoration(labelText: 'Notes (optional)'),
          ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Cancel')),
        FilledButton(
          onPressed: register.isSaving ? null : _submit,
          child: register.isSaving
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : const Text('Open'),
        ),
      ],
    );
  }
}
