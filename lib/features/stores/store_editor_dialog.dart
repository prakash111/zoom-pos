import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/stores/store_provider.dart';
import '../../widgets/inputs/phone_number_field.dart';

class StoreEditorDialog extends StatefulWidget {
  const StoreEditorDialog({super.key, this.store});
  final StoreBranch? store;

  @override
  State<StoreEditorDialog> createState() => StoreEditorDialogState();
}

class StoreEditorDialogState extends State<StoreEditorDialog> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _code = TextEditingController();
  String _phone = '';
  bool _active = true;
  final _address = TextEditingController();
  final _taxId = TextEditingController();
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final store = widget.store;
    if (store != null) {
      _name.text = store.name;
      _code.text = store.code;
      _phone = store.phone;
      _address.text = store.address;
      _taxId.text = store.taxId;
      _active = store.isActive;
    }
  }

  @override
  void dispose() {
    for (final controller in [_name, _code, _address, _taxId]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final provider = context.read<StoreProvider>();
      if (widget.store == null) {
        await provider.create(
            name: _name.text.trim(),
            code: _code.text.trim(),
            phone: _phone,
            address: _address.text.trim(),
            taxId: _taxId.text.trim());
      } else {
        await provider.update(widget.store!,
            name: _name.text.trim(),
            code: _code.text.trim(),
            phone: _phone,
            address: _address.text.trim(),
            taxId: _taxId.text.trim(),
            isActive: _active);
      }
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
        title: Text(
            widget.store == null ? 'Add New Store / Branch' : 'Edit branch'),
        content: SizedBox(
          width: 420,
          child: SingleChildScrollView(
            child: Form(
              key: _formKey,
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                TextFormField(
                  controller: _name,
                  decoration: const InputDecoration(labelText: 'Store name'),
                  validator: (value) => value == null || value.trim().isEmpty
                      ? 'Store name is required'
                      : null,
                ),
                TextFormField(
                  controller: _code,
                  decoration: const InputDecoration(
                      labelText: 'Branch code (optional)'),
                  validator: (value) => value != null &&
                          value.trim().isNotEmpty &&
                          !RegExp(r'^[a-zA-Z0-9_-]+$').hasMatch(value.trim())
                      ? 'Use letters, numbers, dashes or underscores'
                      : null,
                ),
                PhoneNumberField(
                    initialValue: _phone,
                    initialDialCode:
                        context.read<StoreProvider>().defaultDialCode,
                    label: 'Contact phone',
                    enabled: !_saving,
                    onChanged: (full, local, dial) =>
                        _phone = local.isEmpty ? '' : full),
                TextFormField(
                    controller: _address,
                    decoration: const InputDecoration(labelText: 'Address'),
                    maxLines: 2),
                TextFormField(
                    controller: _taxId,
                    decoration: const InputDecoration(labelText: 'Tax number')),
                if (widget.store != null && !widget.store!.isPrimary)
                  SwitchListTile(
                      title: const Text('Branch active'),
                      subtitle: const Text(
                          'Close cash registers before deactivating a branch.'),
                      value: _active,
                      onChanged: _saving
                          ? null
                          : (value) => setState(() => _active = value)),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: Text(_error!,
                        style: TextStyle(
                            color: Theme.of(context).colorScheme.error)),
                  ),
              ]),
            ),
          ),
        ),
        actions: [
          TextButton(
              onPressed: _saving ? null : () => Navigator.pop(context, false),
              child: const Text('Cancel')),
          FilledButton(
              onPressed: _saving ? null : _save,
              child: Text(_saving
                  ? 'Saving…'
                  : widget.store == null
                      ? 'Create and switch'
                      : 'Save branch')),
        ],
      );
}
