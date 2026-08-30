import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/tax_rule_model.dart';
import '../taxes_repository.dart';

/// Bottom sheet for POST/PUT /taxes, used for both creating a new tax rule
/// ([tax] is null) and editing an existing one. Pops with `true` when saved.
class TaxFormSheet extends StatefulWidget {
  const TaxFormSheet({super.key, required this.repository, this.tax});

  final TaxesRepository repository;
  final TaxRuleModel? tax;

  @override
  State<TaxFormSheet> createState() => _TaxFormSheetState();
}

class _TaxFormSheetState extends State<TaxFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _nameController;
  late final TextEditingController _rateController;
  late bool _isDefault;
  late bool _active;
  bool _isSaving = false;
  String? _error;

  bool get _isEditing => widget.tax != null;

  @override
  void initState() {
    super.initState();
    final tax = widget.tax;
    _nameController = TextEditingController(text: tax?.name ?? '');
    _rateController = TextEditingController(text: tax != null ? tax.rate.toStringAsFixed(2) : '');
    _isDefault = tax?.isDefault ?? false;
    _active = tax?.active ?? true;
  }

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
      if (_isEditing) {
        await widget.repository.updateTax(
          id: widget.tax!.id,
          name: _nameController.text.trim(),
          rate: double.parse(_rateController.text),
          isDefault: _isDefault,
          active: _active,
        );
      } else {
        await widget.repository.createTax(
          name: _nameController.text.trim(),
          rate: double.parse(_rateController.text),
          isDefault: _isDefault,
          active: _active,
        );
      }
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
              Text(_isEditing ? 'Edit tax rule' : 'New tax rule', style: Theme.of(context).textTheme.titleLarge),
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
                    : Text(_isEditing ? 'Save changes' : 'Create tax rule'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
