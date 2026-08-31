import 'dart:typed_data';

import 'package:flutter/foundation.dart' show TargetPlatform, defaultTargetPlatform, kIsWeb;
import 'package:flutter/material.dart';
import 'package:printing/printing.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/config/app_config.dart';
import '../../../core/services/thermal/thermal_printer_service.dart';
import '../../../core/widgets/adaptive_sheet.dart';
import '../../settings/screens/printer_settings_screen.dart';

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
    this.currencySymbol = '\$',
    this.taxId,
    this.taxLabel = 'Tax',
    this.isIndia = false,
    this.taxRate = 0,
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
  final String currencySymbol;
  final String? taxId;
  final String taxLabel;
  final bool isIndia;

  /// Effective tax rate (%), used only to label the CGST/SGST split on
  /// thermal receipts — see [ThermalPrinterService.printReceipt].
  final double taxRate;

  String get _pdfPath =>
      documentType == 'quotation' ? ApiEndpoints.quotationPdf(documentId) : ApiEndpoints.salePdf(documentId);
}

bool get _supportsThermalPrint =>
    !kIsWeb && (defaultTargetPlatform == TargetPlatform.android || defaultTargetPlatform == TargetPlatform.iOS);

Future<void> showInvoiceActionsSheet(BuildContext context, InvoiceActionsData data) {
  final apiClient = context.read<ApiClient>();

  return showAdaptiveSheet(
    context,
    builder: (sheetContext) {
      return SafeArea(
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
                    style: Theme.of(sheetContext).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  if ((data.taxId ?? '').isNotEmpty)
                    Text(
                      '${data.isIndia ? 'GSTIN' : 'Tax ID'}: ${data.taxId}',
                      style: TextStyle(color: Colors.grey.shade600, fontSize: 11),
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
                Navigator.of(sheetContext).pop();
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => _InvoicePreviewScreen(apiClient: apiClient, data: data)),
                );
              },
            ),
            if (_supportsThermalPrint)
              ListTile(
                leading: const Icon(Icons.print_outlined),
                title: const Text('Print on receipt printer'),
                subtitle: const Text('Bluetooth thermal printer'),
                onTap: () async {
                  Navigator.of(sheetContext).pop();
                  await _printThermal(context, data);
                },
              ),
            ListTile(
              leading: const Icon(Icons.chat_outlined),
              title: const Text('Share via WhatsApp'),
              onTap: () async {
                Navigator.of(sheetContext).pop();
                await _sendDelivery(context, apiClient, data, type: 'whatsapp');
              },
            ),
            ListTile(
              leading: const Icon(Icons.email_outlined),
              title: const Text('Send via Email'),
              onTap: () async {
                Navigator.of(sheetContext).pop();
                await _sendDelivery(context, apiClient, data, type: 'email');
              },
            ),
            const SizedBox(height: 8),
          ],
        ),
      );
    },
  );
}

Future<void> _printThermal(BuildContext context, InvoiceActionsData data) async {
  final service = ThermalPrinterService();
  final saved = await service.savedDeviceAddress();
  if (!context.mounted) return;

  if (saved == null) {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PrinterSettingsScreen()));
    return;
  }

  final messenger = ScaffoldMessenger.of(context);
  messenger.showSnackBar(const SnackBar(content: Text('Printing…')));
  final ok = await service.printReceipt(
    companyName: data.companyName,
    documentLabel: '${data.documentType == 'quotation' ? 'Quotation' : 'Sale'} #${data.documentNumber}',
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
  );
  messenger.showSnackBar(SnackBar(content: Text(ok ? 'Sent to printer.' : 'Could not reach the printer.')));
}

Future<void> _sendDelivery(
  BuildContext context,
  ApiClient apiClient,
  InvoiceActionsData data, {
  required String type,
}) async {
  final recipient = await showDialog<String>(
    context: context,
    builder: (dialogContext) => _RecipientDialog(type: type),
  );
  if (recipient == null || recipient.trim().isEmpty) return;
  if (!context.mounted) return;

  final messenger = ScaffoldMessenger.of(context);
  try {
    final response = await apiClient.post(ApiEndpoints.sendDelivery, data: {
      'type': type,
      'document_type': data.documentType,
      'recipient': recipient.trim(),
      'document_id': data.documentId,
    });

    final message = response['message']?.toString() ?? 'Sent.';
    messenger.showSnackBar(SnackBar(content: Text(message)));

    final whatsappUrl = response['whatsapp_url']?.toString();
    if (type == 'whatsapp' && whatsappUrl != null && whatsappUrl.isNotEmpty) {
      final uri = Uri.tryParse(whatsappUrl);
      if (uri != null) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    }
  } on ApiException catch (e) {
    messenger.showSnackBar(SnackBar(content: Text(e.message)));
  }
}

class _RecipientDialog extends StatefulWidget {
  const _RecipientDialog({required this.type});

  final String type;

  @override
  State<_RecipientDialog> createState() => _RecipientDialogState();
}

class _RecipientDialogState extends State<_RecipientDialog> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isEmail = widget.type == 'email';
    return AlertDialog(
      title: Text(isEmail ? 'Send via Email' : 'Share via WhatsApp'),
      content: TextField(
        controller: _controller,
        autofocus: true,
        keyboardType: isEmail ? TextInputType.emailAddress : TextInputType.phone,
        decoration: InputDecoration(labelText: isEmail ? 'Recipient email' : 'Recipient phone number'),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Cancel')),
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
        build: (format) async => Uint8List.fromList(await apiClient.getBytes(data._pdfPath)),
        allowPrinting: true,
        allowSharing: true,
        canChangeOrientation: false,
        canChangePageFormat: false,
      ),
    );
  }
}
