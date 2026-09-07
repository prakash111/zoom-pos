import 'dart:typed_data';

import 'package:flutter/foundation.dart'
    show TargetPlatform, defaultTargetPlatform, kIsWeb;
import 'package:flutter/material.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

import '../../../core/services/thermal/thermal_printer_service.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../settings/screens/printer_selection_dialog.dart';
import '../cart_item.dart';

/// The page formats the preview can be rendered as. The two roll sizes are
/// the common thermal receipt widths; the paper "length" is left unbounded
/// (`double.infinity`) so the pdf package trims the page to fit the content,
/// the same way a real receipt roll is cut after printing.
enum ReceiptFormat { thermal58, thermal80, a4, letter }

extension on ReceiptFormat {
  PdfPageFormat get pdfFormat {
    switch (this) {
      case ReceiptFormat.thermal58:
        return PdfPageFormat(58 * PdfPageFormat.mm, double.infinity, marginAll: 4 * PdfPageFormat.mm);
      case ReceiptFormat.thermal80:
        return PdfPageFormat(80 * PdfPageFormat.mm, double.infinity, marginAll: 4 * PdfPageFormat.mm);
      case ReceiptFormat.a4:
        return PdfPageFormat.a4;
      case ReceiptFormat.letter:
        return PdfPageFormat.letter;
    }
  }

  bool get isThermal => this == ReceiptFormat.thermal58 || this == ReceiptFormat.thermal80;

  String get label {
    switch (this) {
      case ReceiptFormat.thermal58:
        return '58mm Roll';
      case ReceiptFormat.thermal80:
        return '80mm Roll';
      case ReceiptFormat.a4:
        return 'A4';
      case ReceiptFormat.letter:
        return 'Letter';
    }
  }
}

/// Everything needed to render the pre-checkout invoice preview, built
/// entirely from the in-memory cart. Unlike [InvoiceActionsData] (shown
/// after a sale exists on the server), nothing here has a document number
/// yet — the sale is only created once "Confirm & Complete Sale" is pressed.
class InvoicePreviewData {
  const InvoicePreviewData({
    required this.documentType,
    required this.companyName,
    required this.items,
    required this.subtotal,
    required this.discount,
    required this.taxTotal,
    required this.grandTotal,
    this.customerName,
    this.notes,
    this.currencySymbol = '\$',
    this.taxId,
    this.taxLabel = 'Tax',
    this.isIndia = false,
    this.paidAmount,
    this.dueAmount = 0,
  });

  final String documentType; // 'Invoice' | 'Quotation'
  final String companyName;
  final List<CartItem> items;
  final double subtotal;
  final double discount;
  final double taxTotal;
  final double grandTotal;
  final String? customerName;
  final String? notes;
  final String currencySymbol;
  final String? taxId;
  final String taxLabel;
  final bool isIndia;

  /// Null means "fully paid" (no separate Paid/Due breakdown needed).
  final double? paidAmount;
  final double dueAmount;
}

/// Shows a full-screen "Preview Invoice" step before a sale is finalized.
///
/// Returns `true` if the cashier tapped "Confirm & Complete Sale" (the
/// caller should then actually submit the sale), or `false`/`null` if they
/// backed out to keep editing the cart.
Future<bool?> showInvoicePreview(BuildContext context, InvoicePreviewData data) {
  return Navigator.of(context).push<bool>(
    MaterialPageRoute(builder: (_) => _InvoicePreviewScreen(data: data)),
  );
}

class _InvoicePreviewScreen extends StatefulWidget {
  const _InvoicePreviewScreen({required this.data});

  final InvoicePreviewData data;

  @override
  State<_InvoicePreviewScreen> createState() => _InvoicePreviewScreenState();
}

class _InvoicePreviewScreenState extends State<_InvoicePreviewScreen> {
  ReceiptFormat _format = ReceiptFormat.thermal80;

  Future<Uint8List> _build(PdfPageFormat _) => _buildReceiptPdf(widget.data, _format);

  static bool get _supportsThermalPrint =>
      !kIsWeb &&
      (defaultTargetPlatform == TargetPlatform.android ||
          defaultTargetPlatform == TargetPlatform.iOS);

  Future<void> _print() async {
    final bytes = await _build(_format.pdfFormat);
    if (!mounted) return;
    await Printing.layoutPdf(onLayout: (_) async => bytes, format: _format.pdfFormat);
  }

  Future<void> _export() async {
    final bytes = await _build(_format.pdfFormat);
    if (!mounted) return;
    await Printing.sharePdf(
      bytes: bytes,
      filename: '${widget.data.documentType.toLowerCase()}-preview.pdf',
    );
  }

  Future<void> _printThermal() async {
    final data = widget.data;
    final service = ThermalPrinterService();
    if (!mounted) return;
    final target = await PrinterSelectionDialog.ensureSelected(context);
    if (target == null || !mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    messenger.showSnackBar(const SnackBar(content: Text('Printing…')));
    final ok = await service.printReceipt(
      companyName: data.companyName,
      documentLabel: '${data.documentType} Preview',
      lines: [
        for (final item in data.items)
          ReceiptLine(
            name: item.product.name,
            quantity: item.quantity,
            unitPrice: item.product.salePrice,
            lineTotal: item.lineTotal,
          ),
      ],
      subtotal: data.subtotal,
      discount: data.discount,
      tax: data.taxTotal,
      total: data.grandTotal,
      customerName: data.customerName,
      currencySymbol: data.currencySymbol,
      taxId: data.taxId,
      taxLabel: data.taxLabel,
      isIndia: data.isIndia,
      paidAmount: data.paidAmount,
      dueAmount: data.dueAmount,
    );
    messenger.showSnackBar(SnackBar(
        content:
            Text(ok ? 'Sent to printer.' : 'Could not reach the printer.')));
  }

  /// The same unified bottom-sheet popup used after a sale is finalized —
  /// print / thermal / share all live inside it instead of as loose buttons.
  Future<void> _openActionsSheet() async {
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (sheetCtx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Text(
                '${widget.data.documentType} Preview',
                style: Theme.of(sheetCtx)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.bold),
              ),
            ),
            ListTile(
              leading: const Icon(Icons.picture_as_pdf_outlined),
              title: const Text('Preview & Print'),
              subtitle: const Text('View the PDF, print, or share the file'),
              onTap: () {
                Navigator.of(sheetCtx).pop();
                _print();
              },
            ),
            if (_supportsThermalPrint)
              ListTile(
                leading: const Icon(Icons.print_outlined),
                title: const Text('Print on receipt printer'),
                subtitle: const Text('Bluetooth thermal printer'),
                onTap: () {
                  Navigator.of(sheetCtx).pop();
                  _printThermal();
                },
              ),
            ListTile(
              leading: const Icon(Icons.ios_share),
              title: const Text('Share as PDF file'),
              subtitle: const Text('Send the invoice PDF via any app'),
              onTap: () {
                Navigator.of(sheetCtx).pop();
                _export();
              },
            ),
            const SizedBox(height: 4),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Preview ${widget.data.documentType}'),
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => Navigator.of(context).pop(false),
        ),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  for (final format in ReceiptFormat.values)
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        label: Text(format.label),
                        selected: _format == format,
                        onSelected: (_) => setState(() => _format = format),
                      ),
                    ),
                ],
              ),
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: PdfPreview(
              key: ValueKey(_format),
              build: _build,
              initialPageFormat: _format.pdfFormat,
              canChangePageFormat: false,
              canChangeOrientation: false,
              canDebug: false,
              useActions: false,
            ),
          ),
          const Divider(height: 1),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                children: [
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: _openActionsSheet,
                      icon: const Icon(Icons.print_outlined),
                      label: const Text('Print / Share'),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () => Navigator.of(context).pop(false),
                          icon: const Icon(Icons.edit_outlined),
                          label: const Text('Edit Cart'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        flex: 2,
                        child: ElevatedButton.icon(
                          style: ElevatedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
                          onPressed: () => Navigator.of(context).pop(true),
                          icon: const Icon(Icons.check_circle_outline),
                          label: const Text('Confirm & Complete Sale'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

Future<Uint8List> _buildReceiptPdf(InvoicePreviewData data, ReceiptFormat format) async {
  final doc = pw.Document();
  final currency = CurrencyFormatter(data.currencySymbol);

  // The base14 PDF fonts don't cover currency glyphs like ₹, so pull in a
  // Unicode-capable font. Falls back to the default font if offline.
  pw.Font? regularFont;
  pw.Font? boldFont;
  try {
    regularFont = await PdfGoogleFonts.notoSansRegular();
    boldFont = await PdfGoogleFonts.notoSansBold();
  } catch (_) {
    regularFont = null;
    boldFont = null;
  }

  doc.addPage(
    pw.Page(
      pageFormat: format.pdfFormat,
      theme: regularFont != null ? pw.ThemeData.withFont(base: regularFont, bold: boldFont) : null,
      build: (context) => format.isThermal ? _thermalLayout(data, currency) : _standardLayout(data, currency),
    ),
  );

  return doc.save();
}

String _formatQty(double quantity) =>
    quantity == quantity.roundToDouble() ? quantity.toStringAsFixed(0) : quantity.toStringAsFixed(2);

pw.Widget _thermalLayout(InvoicePreviewData data, CurrencyFormatter currency) {
  return pw.Column(
    crossAxisAlignment: pw.CrossAxisAlignment.stretch,
    children: [
      pw.Center(
        child: pw.Text(data.companyName, style: pw.TextStyle(fontSize: 13, fontWeight: pw.FontWeight.bold)),
      ),
      if ((data.taxId ?? '').isNotEmpty)
        pw.Center(
          child: pw.Text(
            '${data.isIndia ? 'GSTIN' : 'Tax ID'}: ${data.taxId}',
            style: const pw.TextStyle(fontSize: 8),
          ),
        ),
      pw.SizedBox(height: 4),
      pw.Center(
        child: pw.Text(
          '${data.documentType.toUpperCase()} PREVIEW',
          style: pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold),
        ),
      ),
      pw.Center(
        child: pw.Text('Pending — not yet finalized', style: const pw.TextStyle(fontSize: 7)),
      ),
      if ((data.customerName ?? '').isNotEmpty) ...[
        pw.SizedBox(height: 4),
        pw.Text('Customer: ${data.customerName}', style: const pw.TextStyle(fontSize: 8)),
      ],
      pw.SizedBox(height: 4),
      pw.Divider(thickness: 0.5),
      for (final item in data.items) ...[
        pw.Text(item.product.name, style: const pw.TextStyle(fontSize: 9)),
        pw.Row(
          mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
          children: [
            pw.Text(
              '${_formatQty(item.quantity)} x ${currency.format(item.product.salePrice)}',
              style: const pw.TextStyle(fontSize: 8),
            ),
            pw.Text(currency.format(item.lineTotal), style: const pw.TextStyle(fontSize: 9)),
          ],
        ),
      ],
      pw.Divider(thickness: 0.5),
      _thermalTotalRow('Subtotal', currency.format(data.subtotal)),
      if (data.discount > 0) _thermalTotalRow('Discount', '-${currency.format(data.discount)}'),
      if (data.taxTotal > 0)
        if (data.isIndia) ...[
          _thermalTotalRow('CGST', '+${currency.format(data.taxTotal / 2)}'),
          _thermalTotalRow('SGST', '+${currency.format(data.taxTotal / 2)}'),
        ] else
          _thermalTotalRow(data.taxLabel, '+${currency.format(data.taxTotal)}'),
      pw.Divider(thickness: 0.5),
      _thermalTotalRow('TOTAL', currency.format(data.grandTotal), bold: true),
      if (data.dueAmount > 0.001) ...[
        _thermalTotalRow('Paid', currency.format(data.paidAmount ?? data.grandTotal)),
        _thermalTotalRow('Due Balance', currency.format(data.dueAmount), bold: true),
      ],
      if ((data.notes ?? '').isNotEmpty) ...[
        pw.SizedBox(height: 6),
        pw.Text('Note: ${data.notes}', style: const pw.TextStyle(fontSize: 7)),
      ],
    ],
  );
}

pw.Widget _thermalTotalRow(String label, String value, {bool bold = false}) {
  final style = pw.TextStyle(fontSize: bold ? 10 : 8, fontWeight: bold ? pw.FontWeight.bold : pw.FontWeight.normal);
  return pw.Padding(
    padding: const pw.EdgeInsets.symmetric(vertical: 1),
    child: pw.Row(
      mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
      children: [pw.Text(label, style: style), pw.Text(value, style: style)],
    ),
  );
}

pw.Widget _standardLayout(InvoicePreviewData data, CurrencyFormatter currency) {
  return pw.Column(
    crossAxisAlignment: pw.CrossAxisAlignment.start,
    children: [
      pw.Row(
        mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              pw.Text(data.companyName, style: pw.TextStyle(fontSize: 18, fontWeight: pw.FontWeight.bold)),
              if ((data.taxId ?? '').isNotEmpty)
                pw.Text(
                  '${data.isIndia ? 'GSTIN' : 'Tax ID'}: ${data.taxId}',
                  style: const pw.TextStyle(fontSize: 10),
                ),
            ],
          ),
          pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.end,
            children: [
              pw.Text(
                '${data.documentType.toUpperCase()} PREVIEW',
                style: pw.TextStyle(fontSize: 16, fontWeight: pw.FontWeight.bold),
              ),
              pw.Text('Pending — not yet finalized', style: const pw.TextStyle(fontSize: 9)),
            ],
          ),
        ],
      ),
      pw.SizedBox(height: 16),
      if ((data.customerName ?? '').isNotEmpty)
        pw.Text('Bill to: ${data.customerName}', style: const pw.TextStyle(fontSize: 11)),
      pw.SizedBox(height: 16),
      pw.Table(
        border: const pw.TableBorder(
          top: pw.BorderSide(width: 0.5),
          bottom: pw.BorderSide(width: 0.5),
          horizontalInside: pw.BorderSide(width: 0.3, color: PdfColors.grey400),
        ),
        columnWidths: const {
          0: pw.FlexColumnWidth(4),
          1: pw.FlexColumnWidth(1.2),
          2: pw.FlexColumnWidth(1.5),
          3: pw.FlexColumnWidth(1.5),
        },
        children: [
          pw.TableRow(
            decoration: const pw.BoxDecoration(color: PdfColors.grey200),
            children: [
              _cell('Item', bold: true),
              _cell('Qty', bold: true, align: pw.TextAlign.right),
              _cell('Unit Price', bold: true, align: pw.TextAlign.right),
              _cell('Amount', bold: true, align: pw.TextAlign.right),
            ],
          ),
          for (final item in data.items)
            pw.TableRow(
              children: [
                _cell(item.product.name),
                _cell(_formatQty(item.quantity), align: pw.TextAlign.right),
                _cell(currency.format(item.product.salePrice), align: pw.TextAlign.right),
                _cell(currency.format(item.lineTotal), align: pw.TextAlign.right),
              ],
            ),
        ],
      ),
      pw.SizedBox(height: 16),
      pw.Align(
        alignment: pw.Alignment.centerRight,
        child: pw.SizedBox(
          width: 220,
          child: pw.Column(
            children: [
              _standardTotalRow('Subtotal', currency.format(data.subtotal)),
              if (data.discount > 0) _standardTotalRow('Discount', '-${currency.format(data.discount)}'),
              if (data.taxTotal > 0)
                if (data.isIndia) ...[
                  _standardTotalRow('CGST', '+${currency.format(data.taxTotal / 2)}'),
                  _standardTotalRow('SGST', '+${currency.format(data.taxTotal / 2)}'),
                ] else
                  _standardTotalRow(data.taxLabel, '+${currency.format(data.taxTotal)}'),
              pw.Divider(thickness: 0.5),
              _standardTotalRow('Grand Total', currency.format(data.grandTotal), bold: true),
              if (data.dueAmount > 0.001) ...[
                _standardTotalRow('Amount Paid', currency.format(data.paidAmount ?? data.grandTotal)),
                _standardTotalRow('Due Balance', currency.format(data.dueAmount), bold: true),
              ],
            ],
          ),
        ),
      ),
      if ((data.notes ?? '').isNotEmpty) ...[
        pw.SizedBox(height: 16),
        pw.Text('Notes: ${data.notes}', style: const pw.TextStyle(fontSize: 10)),
      ],
    ],
  );
}

pw.Widget _cell(String text, {bool bold = false, pw.TextAlign align = pw.TextAlign.left}) {
  return pw.Padding(
    padding: const pw.EdgeInsets.symmetric(horizontal: 6, vertical: 6),
    child: pw.Text(
      text,
      textAlign: align,
      style: pw.TextStyle(fontSize: 10, fontWeight: bold ? pw.FontWeight.bold : pw.FontWeight.normal),
    ),
  );
}

pw.Widget _standardTotalRow(String label, String value, {bool bold = false}) {
  final style = pw.TextStyle(fontSize: bold ? 13 : 11, fontWeight: bold ? pw.FontWeight.bold : pw.FontWeight.normal);
  return pw.Padding(
    padding: const pw.EdgeInsets.symmetric(vertical: 3),
    child: pw.Row(
      mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
      children: [pw.Text(label, style: style), pw.Text(value, style: style)],
    ),
  );
}
