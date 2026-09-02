import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/api/api_exception.dart';
import '../staff_repository.dart';

/// Bottom sheet for POST /users/invite. On success, shows the invitation
/// code/link/WhatsApp share option (mirrors the web page's post-invite
/// share panel) before closing.
class InviteUserSheet extends StatefulWidget {
  const InviteUserSheet({super.key, required this.repository, required this.roles});

  final StaffRepository repository;
  final Map<String, String> roles;

  @override
  State<InviteUserSheet> createState() => _InviteUserSheetState();
}

class _InviteUserSheetState extends State<InviteUserSheet> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _commissionController = TextEditingController(text: '0');
  late String _role;
  String _commissionType = 'percentage';
  bool _sendViaEmail = true;
  bool _isSaving = false;
  String? _error;
  InviteResult? _result;

  @override
  void initState() {
    super.initState();
    _role = widget.roles.keys.contains('cashier') ? 'cashier' : widget.roles.keys.first;
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _commissionController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isSaving = true;
      _error = null;
    });

    try {
      final result = await widget.repository.inviteUser(
        name: _nameController.text.trim(),
        email: _emailController.text.trim(),
        phone: _phoneController.text.trim(),
        role: _role,
        commissionRate: double.tryParse(_commissionController.text) ?? 0,
        commissionType: _commissionType,
        sendViaEmail: _sendViaEmail,
      );
      if (!mounted) return;
      setState(() {
        _result = result;
        _isSaving = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _isSaving = false;
      });
    }
  }

  Future<void> _copy(String text) async {
    await Clipboard.setData(ClipboardData(text: text));
    if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Copied.')));
  }

  @override
  Widget build(BuildContext context) {
    final result = _result;

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: result != null ? _buildSuccess(result) : _buildForm(),
      ),
    );
  }

  Widget _buildSuccess(InviteResult result) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            const Icon(Icons.check_circle, color: Colors.green),
            const SizedBox(width: 8),
            Expanded(child: Text('${result.user.name} invited', style: Theme.of(context).textTheme.titleLarge)),
          ],
        ),
        const SizedBox(height: 8),
        if (result.emailSent)
          const Text('Invitation email sent.')
        else
          const Text('Could not send email automatically — share the code or link below.'),
        const SizedBox(height: 16),
        ListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Invitation code'),
          subtitle: Text(result.invitationCode, style: const TextStyle(fontWeight: FontWeight.bold, letterSpacing: 2)),
          trailing: IconButton(icon: const Icon(Icons.copy_outlined), onPressed: () => _copy(result.invitationCode)),
        ),
        ListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Invitation link'),
          subtitle: Text(result.invitationLink, maxLines: 2, overflow: TextOverflow.ellipsis),
          trailing: IconButton(icon: const Icon(Icons.copy_outlined), onPressed: () => _copy(result.invitationLink)),
        ),
        const SizedBox(height: 16),
        ElevatedButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Done')),
      ],
    );
  }

  Widget _buildForm() {
    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Invite team member', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 16),
          TextFormField(
            controller: _nameController,
            decoration: const InputDecoration(labelText: 'Name'),
            validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _emailController,
            keyboardType: TextInputType.emailAddress,
            decoration: const InputDecoration(labelText: 'Email'),
            validator: (v) => (v == null || !v.contains('@')) ? 'Enter a valid email' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(controller: _phoneController, decoration: const InputDecoration(labelText: 'Phone (optional, for WhatsApp share)')),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: _role,
            decoration: const InputDecoration(labelText: 'Role'),
            items: [for (final entry in widget.roles.entries) DropdownMenuItem(value: entry.key, child: Text(entry.value))],
            onChanged: (value) => setState(() => _role = value ?? _role),
          ),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(
              child: TextFormField(
                controller: _commissionController,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(labelText: 'Commission'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: DropdownButtonFormField<String>(
                initialValue: _commissionType,
                decoration: const InputDecoration(labelText: 'Type'),
                items: const [
                  DropdownMenuItem(value: 'percentage', child: Text('Percentage')),
                  DropdownMenuItem(value: 'fixed', child: Text('Fixed')),
                  DropdownMenuItem(value: 'profit_percentage', child: Text('Profit %')),
                  DropdownMenuItem(value: 'profit', child: Text('Profit')),
                ],
                onChanged: (value) => setState(() => _commissionType = value ?? _commissionType),
              ),
            ),
          ]),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Send invitation email'),
            value: _sendViaEmail,
            onChanged: (value) => setState(() => _sendViaEmail = value),
          ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Text(_error!, style: TextStyle(color: Colors.red.shade400)),
          ],
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: _isSaving ? null : _submit,
            child: _isSaving
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Text('Send invitation'),
          ),
        ],
      ),
    );
  }
}
