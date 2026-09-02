import 'dart:convert';

import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/models/settings_models.dart';
import '../settings_repository.dart';

const _kEventTypeLabels = {
  'invoice': 'Invoice',
  'quotation': 'Quotation',
  'due_reminder': 'Due Reminder',
};

/// Custom Notification Channel manager (GET/POST /settings/notification-channels,
/// PUT/DELETE/{id}) — mirrors the web Store Admin's webhook-channel settings so
/// a tenant can dispatch invoice/quotation/due-reminder events to their own
/// endpoint (Twilio, Fast2SMS, a custom gateway, etc.).
class CustomNotificationChannelSettingsScreen extends StatefulWidget {
  const CustomNotificationChannelSettingsScreen({super.key, required this.repository});

  final SettingsRepository repository;

  @override
  State<CustomNotificationChannelSettingsScreen> createState() => _CustomNotificationChannelSettingsScreenState();
}

class _CustomNotificationChannelSettingsScreenState extends State<CustomNotificationChannelSettingsScreen> {
  late Future<List<CustomNotificationChannelModel>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.repository.fetchNotificationChannels();
  }

  void _reload() => setState(() => _future = widget.repository.fetchNotificationChannels());

  Future<void> _openForm({CustomNotificationChannelModel? channel}) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => _ChannelFormScreen(repository: widget.repository, channel: channel),
      ),
    );
    if (saved == true) _reload();
  }

  Future<void> _toggleActive(CustomNotificationChannelModel channel) async {
    try {
      await widget.repository.saveNotificationChannel(
        id: channel.id,
        name: channel.name,
        url: channel.url,
        method: channel.method,
        headers: channel.headers,
        authType: channel.authType,
        payloadTemplate: channel.payloadTemplate,
        eventTypes: channel.eventTypes,
        isActive: !channel.isActive,
      );
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _delete(CustomNotificationChannelModel channel) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Delete ${channel.name}?'),
        content: const Text('This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Delete')),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await widget.repository.deleteNotificationChannel(channel.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Custom Notification Channels')),
      floatingActionButton: FloatingActionButton(onPressed: () => _openForm(), child: const Icon(Icons.add)),
      body: FutureBuilder<List<CustomNotificationChannelModel>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text('Could not load notification channels.'),
                  const SizedBox(height: 8),
                  OutlinedButton(onPressed: _reload, child: const Text('Retry')),
                ],
              ),
            );
          }
          final channels = snapshot.data ?? [];
          if (channels.isEmpty) {
            return const Center(
              child: Padding(
                padding: EdgeInsets.all(24),
                child: Text(
                  'No custom notification channels yet.\nAdd one to send invoices, quotations, or due reminders to your own endpoint (Twilio, Fast2SMS, a custom gateway, etc.).',
                  textAlign: TextAlign.center,
                ),
              ),
            );
          }

          return ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 80),
            itemCount: channels.length,
            separatorBuilder: (_, __) => const SizedBox(height: 8),
            itemBuilder: (context, index) {
              final channel = channels[index];
              return Card(
                child: ListTile(
                  onTap: () => _openForm(channel: channel),
                  title: Text(channel.name.isEmpty ? '(unnamed channel)' : channel.name),
                  subtitle: Text(
                    channel.url,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  trailing: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Switch(value: channel.isActive, onChanged: (_) => _toggleActive(channel)),
                      IconButton(icon: const Icon(Icons.delete_outline), onPressed: () => _delete(channel)),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class _ChannelFormScreen extends StatefulWidget {
  const _ChannelFormScreen({required this.repository, this.channel});

  final SettingsRepository repository;
  final CustomNotificationChannelModel? channel;

  @override
  State<_ChannelFormScreen> createState() => _ChannelFormScreenState();
}

class _ChannelFormScreenState extends State<_ChannelFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _nameController;
  late final TextEditingController _urlController;
  late final TextEditingController _headersController;
  late final TextEditingController _authValueController;
  late final TextEditingController _payloadController;
  late String _method;
  late String _authType;
  late Set<String> _eventTypes;
  late bool _isActive;
  bool _isSaving = false;
  String? _error;

  bool get _isEditing => widget.channel != null;

  @override
  void initState() {
    super.initState();
    final channel = widget.channel;
    _nameController = TextEditingController(text: channel?.name ?? '');
    _urlController = TextEditingController(text: channel?.url ?? '');
    _headersController = TextEditingController(
      text: channel?.headers == null || channel!.headers!.isEmpty ? '' : const JsonEncoder.withIndent('  ').convert(channel.headers),
    );
    _authValueController = TextEditingController();
    _payloadController = TextEditingController(text: channel?.payloadTemplate ?? '');
    _method = channel?.method ?? 'POST';
    _authType = channel?.authType ?? 'none';
    _eventTypes = (channel?.eventTypes ?? const []).toSet();
    _isActive = channel?.isActive ?? true;
  }

  @override
  void dispose() {
    for (final c in [_nameController, _urlController, _headersController, _authValueController, _payloadController]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;

    Map<String, dynamic>? headers;
    final headersText = _headersController.text.trim();
    if (headersText.isNotEmpty) {
      try {
        final decoded = jsonDecode(headersText);
        if (decoded is! Map<String, dynamic>) throw const FormatException('Headers must be a JSON object.');
        headers = decoded;
      } catch (_) {
        setState(() => _error = 'Headers must be valid JSON, e.g. {"Content-Type": "application/json"}');
        return;
      }
    }

    setState(() {
      _isSaving = true;
      _error = null;
    });

    try {
      await widget.repository.saveNotificationChannel(
        id: widget.channel?.id,
        name: _nameController.text.trim(),
        url: _urlController.text.trim(),
        method: _method,
        headers: headers,
        authType: _authType,
        authValue: _authValueController.text.isEmpty ? null : _authValueController.text,
        payloadTemplate: _payloadController.text,
        eventTypes: _eventTypes.toList(),
        isActive: _isActive,
      );
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_isEditing ? 'Edit Channel' : 'New Channel')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
          children: [
            if (_error != null) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(8)),
                child: Text(_error!, style: TextStyle(color: Colors.red.shade700)),
              ),
              const SizedBox(height: 12),
            ],
            TextFormField(
              controller: _nameController,
              decoration: const InputDecoration(labelText: 'Channel Name', hintText: 'e.g. Twilio SMS, Fast2SMS'),
              validator: (v) => (v == null || v.trim().isEmpty) ? 'Name is required' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _urlController,
              decoration: const InputDecoration(labelText: 'Endpoint URL'),
              keyboardType: TextInputType.url,
              validator: (v) => (v == null || v.trim().isEmpty) ? 'Endpoint URL is required' : null,
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _method,
              decoration: const InputDecoration(labelText: 'HTTP Method'),
              items: const [
                DropdownMenuItem(value: 'POST', child: Text('POST')),
                DropdownMenuItem(value: 'GET', child: Text('GET')),
              ],
              onChanged: (v) => setState(() => _method = v ?? 'POST'),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _headersController,
              decoration: const InputDecoration(
                labelText: 'Headers (JSON, optional)',
                hintText: '{"Content-Type": "application/json"}',
                alignLabelWithHint: true,
              ),
              maxLines: 3,
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _authType,
              decoration: const InputDecoration(labelText: 'Authentication'),
              items: const [
                DropdownMenuItem(value: 'none', child: Text('None')),
                DropdownMenuItem(value: 'bearer', child: Text('Bearer Token')),
                DropdownMenuItem(value: 'api_key', child: Text('API Key')),
              ],
              onChanged: (v) => setState(() => _authType = v ?? 'none'),
            ),
            if (_authType != 'none') ...[
              const SizedBox(height: 12),
              TextFormField(
                controller: _authValueController,
                decoration: InputDecoration(
                  labelText: _authType == 'bearer' ? 'Bearer Token' : 'API Key',
                  hintText: widget.channel?.hasAuthValue == true ? 'Secret is set — leave blank to keep it' : null,
                ),
                obscureText: true,
              ),
            ],
            const SizedBox(height: 12),
            TextFormField(
              controller: _payloadController,
              decoration: const InputDecoration(
                labelText: 'Webhook Payload Template',
                hintText: 'Placeholders: {customer_name} {invoice_no} {due_amount} {due_date} {receipt_link}',
                alignLabelWithHint: true,
              ),
              maxLines: 5,
            ),
            const SizedBox(height: 16),
            const Text('Dispatch On', style: TextStyle(fontWeight: FontWeight.w600)),
            for (final entry in _kEventTypeLabels.entries)
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(entry.value),
                value: _eventTypes.contains(entry.key),
                onChanged: (checked) => setState(() {
                  if (checked == true) {
                    _eventTypes.add(entry.key);
                  } else {
                    _eventTypes.remove(entry.key);
                  }
                }),
              ),
            const SizedBox(height: 8),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Active'),
              value: _isActive,
              onChanged: (v) => setState(() => _isActive = v),
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: _isSaving ? null : _save,
              child: _isSaving
                  ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Save'),
            ),
          ],
        ),
      ),
    );
  }
}
