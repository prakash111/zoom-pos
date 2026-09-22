import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/stores/store_provider.dart';
import '../../widgets/inputs/phone_number_field.dart';
import '../../widgets/modals/create_store_modal.dart';

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
    final name = _name.text.trim();
    if (name.isEmpty) {
      setState(() => _error = 'Store name is required');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final provider = context.read<StoreProvider>();
      if (widget.store == null) {
        await provider.create(
          name: name,
          code: _code.text.trim(),
        );
      } else {
        await provider.update(
          widget.store!,
          name: name,
          code: _code.text.trim(),
          phone: _phone,
          address: _address.text.trim(),
          taxId: _taxId.text.trim(),
          isActive: _active,
        );
      }
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) setState(() => _error = error.toString().replaceFirst(RegExp(r'^(Exception|StateError):\s*'), ''));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Widget _buildFieldBox({
    required String label,
    required TextEditingController controller,
    required String hintText,
    bool isRequired = false,
    int maxLines = 1,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(
              label,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: Color(0xFF94A3B8),
              ),
            ),
            if (isRequired)
              const Text(
                ' *',
                style: TextStyle(
                  color: Color(0xFFEF4444),
                  fontWeight: FontWeight.bold,
                  fontSize: 13,
                ),
              ),
          ],
        ),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          decoration: BoxDecoration(
            color: const Color(0xFF0B1120),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: const Color(0xFF1E293B),
              width: 1.0,
            ),
          ),
          child: TextField(
            controller: controller,
            enabled: !_saving,
            maxLines: maxLines,
            style: const TextStyle(
              fontSize: 14,
              color: Colors.white,
              fontWeight: FontWeight.w500,
            ),
            cursorColor: const Color(0xFF38BDF8),
            decoration: InputDecoration(
              isDense: true,
              contentPadding: EdgeInsets.zero,
              border: InputBorder.none,
              hintText: hintText,
              hintStyle: const TextStyle(
                color: Color(0xFF475569),
                fontSize: 14,
              ),
            ),
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    if (widget.store == null) {
      return const CreateStoreModal();
    }

    return Dialog(
      backgroundColor: const Color(0xFF0F172A),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: Color(0xFF1E293B), width: 1),
      ),
      insetPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 440),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Edit Branch Details',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close, size: 20, color: Color(0xFF64748B)),
                        onPressed: _saving ? null : () => Navigator.of(context).pop(false),
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  _buildFieldBox(
                    label: 'Store name',
                    controller: _name,
                    hintText: 'Branch name',
                    isRequired: true,
                  ),
                  const SizedBox(height: 14),
                  _buildFieldBox(
                    label: 'Branch code',
                    controller: _code,
                    hintText: 'e.g. BLR-01',
                  ),
                  const SizedBox(height: 14),
                  PhoneNumberField(
                    initialValue: _phone,
                    initialDialCode: context.read<StoreProvider>().defaultDialCode,
                    label: 'Contact phone',
                    enabled: !_saving,
                    onChanged: (full, local, dial) => _phone = local.isEmpty ? '' : full,
                  ),
                  const SizedBox(height: 14),
                  _buildFieldBox(
                    label: 'Address',
                    controller: _address,
                    hintText: 'Branch physical address',
                    maxLines: 2,
                  ),
                  const SizedBox(height: 14),
                  _buildFieldBox(
                    label: 'Tax number (GSTIN / VAT)',
                    controller: _taxId,
                    hintText: 'Store fiscal ID',
                  ),
                  if (!widget.store!.isPrimary) ...[
                    const SizedBox(height: 14),
                    SwitchListTile(
                      title: const Text('Branch active', style: TextStyle(color: Colors.white, fontSize: 14)),
                      subtitle: const Text(
                        'Close cash registers before deactivating a branch.',
                        style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                      ),
                      value: _active,
                      onChanged: _saving ? null : (value) => setState(() => _active = value),
                    ),
                  ],
                  if (_error != null) ...[
                    const SizedBox(height: 14),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      decoration: BoxDecoration(
                        color: const Color(0xFFEF4444).withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: const Color(0xFFEF4444).withValues(alpha: 0.3)),
                      ),
                      child: Text(
                        _error!,
                        style: const TextStyle(color: Color(0xFFFCA5A5), fontSize: 12),
                      ),
                    ),
                  ],
                  const SizedBox(height: 24),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: _saving ? null : () => Navigator.pop(context, false),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: const Color(0xFF94A3B8),
                            side: const BorderSide(color: Color(0xFF334155)),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            padding: const EdgeInsets.symmetric(vertical: 14),
                          ),
                          child: const Text('Cancel', style: TextStyle(fontWeight: FontWeight.w600)),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        flex: 2,
                        child: ElevatedButton(
                          onPressed: _saving ? null : _save,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF2563EB),
                            foregroundColor: Colors.white,
                            elevation: 0,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            padding: const EdgeInsets.symmetric(vertical: 14),
                          ),
                          child: _saving
                              ? const SizedBox(
                                  height: 18,
                                  width: 18,
                                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                )
                              : const Text('Save branch', style: TextStyle(fontWeight: FontWeight.w700)),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
