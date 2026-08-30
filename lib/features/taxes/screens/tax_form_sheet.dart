import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../taxes_repository.dart';

/// Bottom sheet for POST /taxes. Creation only — see [TaxesRepository].
/// Pops with `true` when a new rule is saved.
class TaxFormSheet extends StatefulWidget {
  const TaxFormSheet({super.key, required this.repository});

  final TaxesRepository repository;

  @override
  State<TaxFormSheet> createState() => _TaxFormSheetState();
}

class _TaxFormSheetState extends State<TaxFormSheet> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _rateController = TextEditingController();
  bool _isDefault = false;
  bool _active = true;
  bool _isSaving = false;
  String? _error;

  @override
  void dispose() {
    _nameController.dispose();
    _rateController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isSaving = true;
      _error = null;
    });

    try {
      await widget.repository.createTax(
        name: _nameController.text.trim(),
        rate: double.parse(_rateController.text),
        isDefault: _isDefault,
        active: _active,
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
              Text('New tax rule', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 16),
              TextFormField(
                controller: _nameController,
                decoration: const InputDecoration(labelText: 'Name'),
                validator: (value) => (value == null || value.trim().isEmpty) ? 'Required' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _rateController,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(labelText: 'Rate', suffixText: '%'),
                validator: (value) {
                  final parsed = double.tryParse(value ?? '');
                  if (parsed == null || parsed < 0 || parsed > 100) return 'Enter a rate between 0 and 100';
                  return null;
                },
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Set as default'),
                subtitle: const Text('Applied automatically to new products'),
                value: _isDefault,
                onChanged: (value) => setState(() => _isDefault = value),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Active'),
                value: _active,
                onChanged: (value) => setState(() => _active = value),
              ),
              if (_error != null) ...[
                const SizedBox(height: 8),
                Text(_error!, style: TextStyle(color: Colors.red.shade400)),
              ],
              const SizedBox(height: 12),
              ElevatedButton(
                onPressed: _isSaving ? null : _submit,
                child: _isSaving
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : const Text('Create tax rule'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
