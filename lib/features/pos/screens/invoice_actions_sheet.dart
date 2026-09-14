import 'dart:typed_data';

import 'package:flutter/foundation.dart'
    show TargetPlatform, defaultTargetPlatform, kIsWeb;
import 'package:flutter/material.dart';
import 'package:printing/printing.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/api/api_client.dart';
import '../../../core/config/app_config.dart';
import '../../../core/sdui/sdui_icon_registry.dart';
import '../../../core/services/thermal/thermal_printer_service.dart';
import '../../../core/widgets/adaptive_sheet.dart';
import '../../settings/screens/printer_selection_dialog.dart';

/// Everything the actions sheet needs to preview/print/share a document,
/// independent of whether it backs a Sale or a Quotation.
class InvoiceActionsData {
  const InvoiceActionsData({
    required this.documentType,
    required this.documentId,
    required this.documentNumber,
    required this.companyName,
    required this.lines,
    required this.subtotal,
    required this.discount,
    required this.tax,
    required this.total,
    this.customerName,
    this.customerPhone,
    this.customerEmail,
    this.currencySymbol = '\$',
    this.taxId,
    this.taxLabel = 'Tax',
    this.isIndia = false,
    this.taxRate = 0,
    this.paidAmount,
    this.dueAmount = 0,
    this.pdfPathOverride,
  });

  final String documentType; // 'invoice' | 'quotation'
  final String documentId;
  final String documentNumber;
  final String companyName;
  final List<ReceiptLine> lines;
  final double subtotal;
  final double discount;
  final double tax;
  final double total;
  final String? customerName;
  final String? customerPhone;
  final String? customerEmail;
  final String currencySymbol;
  final String? taxId;
  final String taxLabel;
  final bool isIndia;

  /// Effective tax rate (%), used only to label the CGST/SGST split on
  /// thermal receipts — see [ThermalPrinterService.printReceipt].
  final double taxRate;

  /// Null means "fully paid" (no separate Paid/Due breakdown on the receipt).
  final double? paidAmount;
  final double dueAmount;

  /// When set, the exact API path "Preview & Print" fetches the PDF bytes
  /// from — used by the SDUI `show_post_sale_sheet` action, which points at
  /// the token-authed `/api/tenant/invoices/{id}/pdf-stream` route rather
  /// than the `/api/v1/pos` sale/quotation PDF endpoints.
  final String? pdfPathOverride;

  String get _pdfPath {
    if ((pdfPathOverride ?? '').isNotEmpty) return pdfPathOverride!;
    return documentType == 'quotation'
        ? ApiEndpoints.quotationPdf(documentId)
        : ApiEndpoints.salePdf(documentId);
  }

  /// A [_pdfPath] outside the `/api/v1/pos` prefix must be fetched with
  /// [ApiClient.getBytesAbsolute]; the sale/quotation defaults use
  /// [ApiClient.getBytes].
  bool get _pdfPathIsAbsolute =>
      _pdfPath.startsWith('/api/') || _pdfPath.startsWith('http');
}

bool get _supportsThermalPrint =>
    !kIsWeb &&
    (defaultTargetPlatform == TargetPlatform.android ||
        defaultTargetPlatform == TargetPlatform.iOS);

Future<void> showInvoiceActionsSheet(
    BuildContext context, InvoiceActionsData data) {
  final apiClient = context.read<ApiClient>();

  return showAdaptiveSheet(
    context,
    builder: (sheetContext) => _InvoiceActionsSheetContent(
      parentContext: context,
      apiClient: apiClient,
      data: data,
    ),
  );
}

class _InvoiceActionsSheetContent extends StatefulWidget {
  const _InvoiceActionsSheetContent({
    required this.parentContext,
    required this.apiClient,
    required this.data,
  });

  final BuildContext parentContext;
  final ApiClient apiClient;
  final InvoiceActionsData data;

  @override
  State<_InvoiceActionsSheetContent> createState() =>
      _InvoiceActionsSheetContentState();
}

class _InvoiceActionsSheetContentState
    extends State<_InvoiceActionsSheetContent> {
  List<Map<String, dynamic>> _channels = const [];
  bool _channelsLoading = true;
  String? _channelsError;

  @override
  void initState() {
    super.initState();
    _fetchChannels();
  }

  Future<void> _fetchChannels() async {
    if (mounted) {
      setState(() {
        _channelsLoading = true;
        _channelsError = null;
      });
    }

    try {
      final normalizedType = widget.data.documentType == 'quotation'
          ? 'quotation'
          : widget.data.documentType == 'sale'
              ? 'sale'
              : 'invoice';
      final documentId = Uri.encodeComponent(widget.data.documentId);
      final endpoint =
          '/api/v1/tenant/documents/$normalizedType/$documentId/preview-modal';
      final response = await widget.apiClient.requestAbsolute(
        endpoint,
        method: 'GET',
      );
      final rawSchema = response['schema'] is Map
          ? Map<String, dynamic>.from(response['schema'] as Map)
          : response;
      final rawComponents = rawSchema['components'];
      final channels = <Map<String, dynamic>>[];

      if (rawComponents is List) {
        for (final raw in rawComponents) {
          if (raw is! Map) continue;
          final component = Map<String, dynamic>.from(raw);
          if (component['type']?.toString() != 'list_tile') continue;

          final action = component['action'];
          final actionMap =
              action is Map ? Map<String, dynamic>.from(action) : const {};
          final channel = component['channel']?.toString().trim() ?? '';
          final endpoint = actionMap['endpoint']?.toString() ?? '';
          final actionType = (actionMap['type'] ?? component['action_type'])
              ?.toString()
              .toUpperCase();

          if (channel.isNotEmpty ||
              endpoint.contains('/dispatch/') ||
              actionType == 'OPEN_URL') {
            channels.add(component);
          }
        }
      }

      if (!mounted) return;
      setState(() {
        _channels = channels;
        _channelsLoading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _channels = const [];
        _channelsLoading = false;
        _channelsError = 'Could not load configured channels. Tap to retry.';
      });
    }
  }

  Map<String, dynamic> _map(dynamic value) =>
      value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

  Widget _buildChannelTile(
    BuildContext context,
    Map<String, dynamic> channel,
  ) {
    final leading = _map(channel['leading']);
    final icon = SduiIconRegistry.resolve(
      leading['icon']?.toString() ?? channel['icon']?.toString(),
      fallback: Icons.send_outlined,
    );
    final color = SduiIconRegistry.parseColor(
      leading['color']?.toString() ?? channel['color']?.toString(),
      fallback: Theme.of(context).colorScheme.primary,
    );

    return ListTile(
      key: ValueKey(channel['id'] ?? channel['channel'] ?? channel['title']),
      leading: Icon(icon, color: color, size: 22),
      title: Text(
        channel['title']?.toString() ?? 'Send document',
        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
      ),
      subtitle: channel['subtitle'] == null
          ? null
          : Text(
              channel['subtitle'].toString(),
              style: TextStyle(
                fontSize: 12,
                color: Theme.of(context).textTheme.bodySmall?.color,
              ),
            ),
      trailing: const Icon(Icons.send_outlined, size: 18),
      onTap: () => _executeChannel(context, channel),
    );
  }

  Future<void> _executeChannel(
    BuildContext sheetContext,
    Map<String, dynamic> channel,
  ) async {
    final action = _map(channel['action'] ?? channel['on_tap']);
    final actionType =
        (action['type'] ?? channel['action_type'])?.toString().toUpperCase() ??
            '';

    if (actionType == 'OPEN_URL') {
      final uri = Uri.tryParse(
          action['url']?.toString() ?? channel['url']?.toString() ?? '');
      if (uri == null) return;
      Navigator.of(sheetContext).pop();
      await launchUrl(uri, mode: LaunchMode.externalApplication);
      return;
    }

    final endpoint =
        action['endpoint']?.toString() ?? channel['endpoint']?.toString() ?? '';
    if (!endpoint.startsWith('/api/') && !endpoint.startsWith('http')) {
      ScaffoldMessenger.of(widget.parentContext).showSnackBar(
        const SnackBar(content: Text('This dispatch action is unavailable.')),
      );
      return;
    }

    final payload =
        _map(action['data'] ?? action['payload'] ?? channel['data']);
    final channelName =
        (payload['channel'] ?? channel['channel'] ?? channel['id'] ?? '')
            .toString()
            .toLowerCase();
    if (channelName == 'sms' ||
        channelName == 'whatsapp' ||
        channelName == 'email') {
      var recipient = payload['recipient']?.toString().trim() ?? '';
      if (recipient.isEmpty) {
        recipient = (channelName == 'email'
                    ? widget.data.customerEmail
                    : widget.data.customerPhone)
                ?.trim() ??
            '';
      }
      if (recipient.isEmpty) {
        final prompted = await showDialog<String>(
          context: widget.parentContext,
          builder: (_) => _RecipientDialog(
            type: channelName,
            initialValue: channelName == 'email'
                ? widget.data.customerEmail
                : widget.data.customerPhone,
          ),
        );
        recipient = prompted?.trim() ?? '';
      }
      if (recipient.isEmpty) return;
      payload['recipient'] = recipient;
    }

    if (sheetContext.mounted) Navigator.of(sheetContext).pop();
    final messenger = ScaffoldMessenger.of(widget.parentContext);
    try {
      final response = await widget.apiClient.requestAbsolute(
        endpoint,
        method: action['method']?.toString() ?? 'POST',
        data: payload,
      );
      final message = response['message']?.toString() ??
          action['feedback']?.toString() ??
          'Document dispatched.';
      messenger.showSnackBar(SnackBar(content: Text(message)));

      final returnedUrl =
          response['whatsapp_url']?.toString() ?? response['url']?.toString();
      final uri = Uri.tryParse(returnedUrl ?? '');
      if (uri != null) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    } catch (error) {
      messenger.showSnackBar(
        SnackBar(content: Text('Dispatch failed: $error')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final data = widget.data;
    final apiClient = widget.apiClient;

    return SafeArea(
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(height: 12),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Column(
                children: [
                  Text(
                    data.documentNumber,
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  if ((data.taxId ?? '').isNotEmpty)
                    Text(
                      '${data.isIndia ? 'GSTIN' : 'Tax ID'}: ${data.taxId}',
                      style:
                          TextStyle(color: Colors.grey.shade600, fontSize: 11),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            ListTile(
              leading: const Icon(Icons.picture_as_pdf_outlined),
              title: const Text('Preview & Print'),
              subtitle: const Text('View the PDF, print, or share the file'),
              onTap: () {
                Navigator.of(context).pop();
                Navigator.of(widget.parentContext).push(
                  MaterialPageRoute(
                      builder: (_) => _InvoicePreviewScreen(
                          apiClient: apiClient, data: data)),
                );
              },
            ),
            if (_supportsThermalPrint)
              ListTile(
                leading: const Icon(Icons.print_outlined),
                title: const Text('Print on receipt printer'),
                subtitle: const Text('Bluetooth thermal printer'),
                onTap: () async {
                  Navigator.of(context).pop();
                  await _printThermal(widget.parentContext, data);
                },
              ),
            if (_channelsLoading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 16),
                child: CircularProgressIndicator.adaptive(),
              )
            else if (_channelsError != null)
              ListTile(
                leading: const Icon(Icons.sync_problem_outlined),
                title: Text(_channelsError!),
                trailing: const Icon(Icons.refresh),
                onTap: _fetchChannels,
              )
            else if (_channels.isEmpty)
              const ListTile(
                leading: Icon(Icons.info_outline),
                title: Text('No delivery channels are configured'),
                subtitle: Text(
                    'Enable SMS, WhatsApp, SMTP, or a custom channel in Settings.'),
              )
            else
              for (final channel in _channels)
                _buildChannelTile(context, channel),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }
}

Future<void> _printThermal(
    BuildContext context, InvoiceActionsData data) async {
  final service = ThermalPrinterService();

  // Pick / confirm the Bluetooth printer if none is set yet.
  final target = await PrinterSelectionDialog.ensureSelected(context);
  if (target == null || !context.mounted) return;

  final messenger = ScaffoldMessenger.of(context);
  messenger.showSnackBar(const SnackBar(content: Text('Printing…')));
  final ok = await service.printReceipt(
    companyName: data.companyName,
    documentLabel:
        '${data.documentType == 'quotation' ? 'Quotation' : 'Sale'} #${data.documentNumber}',
    lines: data.lines,
    subtotal: data.subtotal,
    discount: data.discount,
    tax: data.tax,
    total: data.total,
    customerName: data.customerName,
    currencySymbol: data.currencySymbol,
    taxId: data.taxId,
    taxLabel: data.taxLabel,
    isIndia: data.isIndia,
    taxRate: data.taxRate,
    paidAmount: data.paidAmount,
    dueAmount: data.dueAmount,
  );
  messenger.showSnackBar(SnackBar(
      content: Text(ok ? 'Sent to printer.' : 'Could not reach the printer.')));
}

class _RecipientDialog extends StatefulWidget {
  const _RecipientDialog({required this.type, this.initialValue});

  final String type;
  final String? initialValue;

  @override
  State<_RecipientDialog> createState() => _RecipientDialogState();
}

class _RecipientDialogState extends State<_RecipientDialog> {
  late final _controller =
      TextEditingController(text: widget.initialValue ?? '');

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isEmail = widget.type == 'email';
    final isSms = widget.type == 'sms';
    final String titleText;
    if (isEmail) {
      titleText = 'Send via Email';
    } else if (isSms) {
      titleText = 'Send via SMS';
    } else {
      titleText = 'Share via WhatsApp';
    }
    return AlertDialog(
      title: Text(titleText),
      content: TextField(
        controller: _controller,
        autofocus: true,
        keyboardType:
            isEmail ? TextInputType.emailAddress : TextInputType.phone,
        decoration: InputDecoration(
            labelText: isEmail ? 'Recipient email' : 'Recipient phone number'),
      ),
      actions: [
        TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancel')),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(_controller.text),
          child: const Text('Send'),
        ),
      ],
    );
  }
}

class _InvoicePreviewScreen extends StatelessWidget {
  const _InvoicePreviewScreen({required this.apiClient, required this.data});

  final ApiClient apiClient;
  final InvoiceActionsData data;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(data.documentNumber)),
      body: PdfPreview(
        build: (format) async => Uint8List.fromList(
          data._pdfPathIsAbsolute
              ? await apiClient.getBytesAbsolute(data._pdfPath)
              : await apiClient.getBytes(data._pdfPath),
        ),
        allowPrinting: true,
        // The package's own share button hands off straight to the OS share
        // sheet with just the raw PDF bytes — no WhatsApp/email/thermal/
        // custom-channel choices, and no pre-filled customer contact info.
        // Replace it with the same actions sheet the post-settlement flow
        // uses, so every entry point into "share this document" behaves
        // identically.
        allowSharing: false,
        actions: [
          IconButton(
            icon: const Icon(Icons.share),
            tooltip: 'Share',
            onPressed: () => showInvoiceActionsSheet(context, data),
          ),
        ],
        canChangeOrientation: false,
        canChangePageFormat: false,
        // Keep the document at a realistic A4 width and centred, rather than
        // stretched across the whole desktop window.
        maxPageWidth: 820,
        padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 16),
        scrollViewDecoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surfaceContainerHighest,
        ),
        pdfPreviewPageDecoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(4),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.18),
              blurRadius: 18,
              offset: const Offset(0, 6),
            ),
          ],
        ),
      ),
    );
  }
}
