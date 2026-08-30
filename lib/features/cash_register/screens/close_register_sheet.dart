import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/utils/currency_formatter.dart';
import '../cash_register_provider.dart';

/// Dialog for POST /cash-register/{id}/close — the Z-Report close-out.
class CloseRegisterSheet extends StatefulWidget {
  const CloseRegisterSheet({super.key, required this.expectedCash, required this.formatter});

  final double expectedCash;
  final CurrencyFormatter formatter;

  @override
  State<CloseRegisterSheet> createState() => _CloseRegisterSheetState();
}

class _CloseRegisterSheetState extends State<CloseRegisterSheet> {
  late final TextEditingController _countedController;
  final _notesController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _countedController = TextEditingController(text: widget.expectedCash.toStringAsFixed(2));
  }

  @override
  void dispose() {
    _countedController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final counted = double.tryParse(_countedController.text);
    if (counted == null || counted < 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Enter a valid counted amount.')));
      return;
    }

    final register = context.read<CashRegisterProvider>();
    final closed = await register.closeRegister(
      countedClosingBalance: counted,
      notes: _notesController.text.trim(),
    );

    if (!mounted) return;
    if (closed != null) {
      Navigator.of(context).pop();
      final diff = closed.cashDifference ?? 0;
      final message = diff == 0
          ? 'Register closed. Cash matched exactly.'
          : 'Register closed. ${diff > 0 ? 'Over' : 'Short'} by ${widget.formatter.format(diff.abs())}.';
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    } else if (register.actionError != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(register.actionError!)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final register = context.watch<CashRegisterProvider>();
    final counted = double.tryParse(_countedController.text) ?? widget.expectedCash;
    final variance = counted - widget.expectedCash;

    return AlertDialog(
      title: const Text('Close register (Z-Report)'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [const Text('Expected cash'), Text(widget.formatter.format(widget.expectedCash))],
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _countedController,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Counted cash'),
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: 8),
          Text(
            variance == 0
                ? 'Matches expected cash.'
                : '${variance > 0 ? 'Over' : 'Short'} by ${widget.formatter.format(variance.abs())}',
            style: TextStyle(color: variance == 0 ? Colors.green : Colors.orange.shade800),
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
              : const Text('Close register'),
        ),
      ],
    );
  }
}
