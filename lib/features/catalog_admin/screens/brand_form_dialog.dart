import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/brand_model.dart';
import '../brands_repository.dart';

/// Dialog for POST/PUT /brands. Pops with `true` when saved.
class BrandFormDialog extends StatefulWidget {
  const BrandFormDialog({super.key, required this.repository, this.brand});

  final BrandsRepository repository;
  final BrandModel? brand;

  @override
  State<BrandFormDialog> createState() => _BrandFormDialogState();
}

class _BrandFormDialogState extends State<BrandFormDialog> {
  late final TextEditingController _nameController;
  bool _isSaving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController(text: widget.brand?.name ?? '');
  }

  @override
  void dispose() {
    _nameController.dispose();
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
      await widget.repository.saveBrand(id: widget.brand?.id, name: name);
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
      title: Text(widget.brand == null ? 'New brand' : 'Edit brand'),
      content: TextField(
        controller: _nameController,
        decoration: InputDecoration(labelText: 'Name', errorText: _error),
        autofocus: true,
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
