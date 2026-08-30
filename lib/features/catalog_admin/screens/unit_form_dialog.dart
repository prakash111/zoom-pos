import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/unit_model.dart';
import '../units_repository.dart';

/// Dialog for POST/PUT /units. Pops with `true` when saved.
class UnitFormDialog extends StatefulWidget {
  const UnitFormDialog({super.key, required this.repository, this.unit});

  final UnitsRepository repository;
  final UnitModel? unit;

  @override
  State<UnitFormDialog> createState() => _UnitFormDialogState();
}

class _UnitFormDialogState extends State<UnitFormDialog> {
  late final TextEditingController _nameController;
  late final TextEditingController _abbreviationController;
  bool _isSaving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController(text: widget.unit?.name ?? '');
    _abbreviationController = TextEditingController(text: widget.unit?.abbreviation ?? '');
  }

  @override
  void dispose() {
    _nameController.dispose();
    _abbreviationController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final name = _nameController.text.trim();
    if (name.isEmpty) {
      setState(() => _error = 'Required');
      return;
    }

    setState(() {
      _isSaving = true;
      _error = null;
    });

    try {
      await widget.repository.saveUnit(
        id: widget.unit?.id,
        name: name,
        abbreviation: _abbreviationController.text.trim(),
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
    return AlertDialog(
      title: Text(widget.unit == null ? 'New unit' : 'Edit unit'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            controller: _nameController,
            decoration: InputDecoration(labelText: 'Name', errorText: _error),
            autofocus: true,
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _abbreviationController,
            decoration: const InputDecoration(labelText: 'Abbreviation (e.g. kg)'),
          ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Cancel')),
        FilledButton(
          onPressed: _isSaving ? null : _submit,
          child: _isSaving
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : const Text('Save'),
        ),
      ],
    );
  }
}
