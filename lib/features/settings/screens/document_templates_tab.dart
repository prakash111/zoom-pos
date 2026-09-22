import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../auth/auth_provider.dart';

class DocumentTemplatesScreen extends StatelessWidget {
  const DocumentTemplatesScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Invoice & Quotation Templates')),
        body: const DocumentTemplatesTab(),
      );
}

/// Edits the same tenant-owned invoice and quotation templates as the web app.
class DocumentTemplatesTab extends StatefulWidget {
  const DocumentTemplatesTab({super.key});

  @override
  State<DocumentTemplatesTab> createState() => _DocumentTemplatesTabState();
}

class _DocumentTemplatesTabState extends State<DocumentTemplatesTab> {
  String _type = 'invoice';
  bool _loading = true;
  bool _saving = false;
  String? _error;
  bool _attachPdf = true;
  bool _showQr = true;
  bool _showTax = true;
  String _logoPlacement = 'left';
  final _color = TextEditingController();
  final _title = TextEditingController();
  final _terms = TextEditingController();
  final _footer = TextEditingController();
  final _message = TextEditingController();

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().user;
    if (user?.can('templates.invoices.manage') == true ||
        user?.can('settings.view') == true) {
      _load();
    }
  }

  @override
  void dispose() {
    for (final controller in [_color, _title, _terms, _footer, _message]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await context
          .read<ApiClient>()
          .getAbsolute('/api/v1/tenant/templates/$_type');
      final template =
          Map<String, dynamic>.from(response['template'] as Map? ?? const {});
      if (!mounted) return;
      setState(() {
        _color.text = template['theme_color']?.toString() ?? '#10b981';
        _title.text = template['header_title']?.toString() ?? '';
        _terms.text = template['terms_conditions']?.toString() ?? '';
        _footer.text = template['footer_notes']?.toString() ?? '';
        _message.text = template['message_body_template']?.toString() ?? '';
        _logoPlacement = template['logo_placement']?.toString() ?? 'left';
        _showQr = template['show_qr_code'] == true;
        _showTax = template['show_tax_breakup'] == true;
        _attachPdf = template['send_as_attachment'] != false;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = error is ApiException
            ? error.message
            : 'Could not load the template.';
      });
    }
  }

  Future<void> _save() async {
    if (!RegExp(r'^#[0-9a-fA-F]{6}$').hasMatch(_color.text.trim())) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Enter a six digit hex color, such as #10b981.')));
      return;
    }
    setState(() => _saving = true);
    try {
      await context.read<ApiClient>().putAbsolute(
        '/api/v1/tenant/templates/$_type',
        data: {
          'theme_color': _color.text.trim(),
          'logo_placement': _logoPlacement,
          'header_title': _title.text.trim(),
          'terms_conditions': _terms.text,
          'footer_notes': _footer.text,
          'show_qr_code': _showQr,
          'show_tax_breakup': _showTax,
          'send_as_attachment': _attachPdf,
          'send_text_with_link': !_attachPdf,
          'message_body_template': _message.text,
        },
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(
              '${_type == 'invoice' ? 'Invoice' : 'Quotation'} template saved.')));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(error is ApiException
              ? error.message
              : 'Could not save the template.')));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    if (user?.can('templates.invoices.manage') != true &&
        user?.can('settings.view') != true) {
      return const Center(
          child:
              Text('You do not have permission to view document templates.'));
    }
    final canEdit = user?.can('templates.invoices.manage') == true;
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
        Text(_error!),
        TextButton(onPressed: _load, child: const Text('Retry')),
      ]));
    }

    Widget field(String label, TextEditingController controller,
            {int lines = 1, String? helper}) =>
        TextField(
          controller: controller,
          enabled: canEdit,
          maxLines: lines,
          decoration: InputDecoration(
              labelText: label,
              helperText: helper,
              border: const OutlineInputBorder()),
        );

    return ListView(padding: const EdgeInsets.all(16), children: [
      SegmentedButton<String>(
        segments: const [
          ButtonSegment(
              value: 'invoice',
              label: Text('Invoice'),
              icon: Icon(Icons.receipt_long_outlined)),
          ButtonSegment(
              value: 'quotation',
              label: Text('Quotation'),
              icon: Icon(Icons.request_quote_outlined)),
        ],
        selected: {_type},
        onSelectionChanged: (value) {
          _type = value.first;
          _load();
        },
      ),
      const SizedBox(height: 16),
      Card(
          child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text('Document design',
                      style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 12),
                  field('Header title', _title),
                  const SizedBox(height: 12),
                  field('Theme color', _color,
                      helper: 'Hex color, for example #10b981'),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    key: ValueKey('logo-$_type'),
                    initialValue: _logoPlacement,
                    decoration: const InputDecoration(
                        labelText: 'Logo placement',
                        border: OutlineInputBorder()),
                    items: const [
                      DropdownMenuItem(value: 'left', child: Text('Left')),
                      DropdownMenuItem(value: 'center', child: Text('Center')),
                      DropdownMenuItem(value: 'right', child: Text('Right')),
                      DropdownMenuItem(value: 'hidden', child: Text('Hidden')),
                    ],
                    onChanged: canEdit
                        ? (value) =>
                            setState(() => _logoPlacement = value ?? 'left')
                        : null,
                  ),
                  SwitchListTile(
                      title: const Text('Show verification QR code'),
                      value: _showQr,
                      onChanged: canEdit
                          ? (value) => setState(() => _showQr = value)
                          : null),
                  SwitchListTile(
                      title: const Text('Show tax breakdown'),
                      value: _showTax,
                      onChanged: canEdit
                          ? (value) => setState(() => _showTax = value)
                          : null),
                  field('Terms and conditions', _terms, lines: 4),
                  const SizedBox(height: 12),
                  field('Footer note', _footer, lines: 2),
                ],
              ))),
      const SizedBox(height: 12),
      Card(
          child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text('Sending',
                      style: Theme.of(context).textTheme.titleMedium),
                  RadioGroup<bool>(
                    groupValue: _attachPdf,
                    onChanged: canEdit
                        ? (value) => setState(() => _attachPdf = value!)
                        : (value) {},
                    child: Column(children: [
                      RadioListTile<bool>(
                          title: const Text('Attach PDF'),
                          subtitle: const Text(
                              'Send the document as a PDF attachment.'),
                          value: true,
                          enabled: canEdit),
                      RadioListTile<bool>(
                          title: const Text('Text with document link'),
                          subtitle: const Text(
                              'Send the message and a link to view the document.'),
                          value: false,
                          enabled: canEdit),
                    ]),
                  ),
                  const SizedBox(height: 8),
                  field('Message template', _message,
                      lines: 5,
                      helper:
                          '{customer_name}  {invoice_number}  {quotation_number}  {amount}  {due_date}  {document_link}'),
                ],
              ))),
      const SizedBox(height: 16),
      if (canEdit)
        FilledButton.icon(
          onPressed: _saving ? null : _save,
          icon: _saving
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2))
              : const Icon(Icons.save_outlined),
          label: const Text('Save template'),
        ),
    ]);
  }
}
