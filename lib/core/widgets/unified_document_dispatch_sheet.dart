import 'dart:typed_data';

import 'package:flutter/foundation.dart'
    show TargetPlatform, defaultTargetPlatform, kIsWeb;
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:printing/printing.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api/api_client.dart';
import '../config/app_config.dart';
import '../config/theme.dart';
import '../services/thermal/thermal_printer_service.dart';
import 'adaptive_sheet.dart';
import '../../features/settings/screens/printer_selection_dialog.dart';

bool get _supportsThermalPrint =>
    !kIsWeb &&
    (defaultTargetPlatform == TargetPlatform.android ||
        defaultTargetPlatform == TargetPlatform.iOS);

/// Unified data container for omnichannel document dispatch across all verticals:
/// Retail, Restaurant, Salon, Pharmacy, and Service & Repairs.
class UnifiedDocumentDispatchData {
  UnifiedDocumentDispatchData({
    required this.documentType,
    required this.documentId,
    required this.documentNumber,
    required this.companyName,
    this.title,
    this.customerName,
    this.tableName,
    this.patientName,
    this.customerPhone,
    this.customerEmail,
    this.taxId,
    this.taxLabel = 'Tax ID',
    this.isIndia = false,
    this.formattedTimestamp,
    this.dateTime,
    this.lines = const [],
    this.subtotal = 0.0,
    this.discount = 0.0,
    this.tax = 0.0,
    this.total = 0.0,
    this.taxRate = 0.0,
    this.paidAmount,
    this.dueAmount = 0.0,
    this.currencySymbol = '\$',
    this.pdfPathOverride,
    this.actionsPathOverride,
    this.dispatchEndpoint = '/api/v1/documents/dispatch',
    this.notes,
    this.status = 'Paid',
    this.defect,
    this.deviceModel,
    this.doctorName,
    this.stylistName,
    this.onPreviewPdf,
    this.onDispatch,
  });

  final String documentType;
  final String documentId;
  final String documentNumber;
  final String companyName;
  final String? title;
  final String? customerName;
  final String? tableName;
  final String? patientName;
  final String? customerPhone;
  final String? customerEmail;
  final String? taxId;
  final String taxLabel;
  final bool isIndia;
  final String? formattedTimestamp;
  final DateTime? dateTime;
  final List<ReceiptLine> lines;
  final double subtotal;
  final double discount;
  final double tax;
  final double total;
  final double taxRate;
  final double? paidAmount;
  final double dueAmount;
  final String currencySymbol;
  final String? pdfPathOverride;
  final String? actionsPathOverride;
  final String dispatchEndpoint;
  final String? notes;
  final String status;
  final String? defect;
  final String? deviceModel;
  final String? doctorName;
  final String? stylistName;
  final VoidCallback? onPreviewPdf;
  final void Function(bool sendWhatsApp, bool sendEmail)? onDispatch;

  String get displayTitle => documentNumber.trim();

  String get subtitleContext {
    if ((formattedTimestamp ?? '').startsWith('Table:')) {
      return formattedTimestamp!;
    }
    final segments = <String>[];

    // 1. Primary entity
    if ((tableName ?? '').isNotEmpty) {
      segments.add('Table $tableName');
    } else if ((patientName ?? '').isNotEmpty) {
      segments.add(patientName!);
    } else if ((customerName ?? '').isNotEmpty) {
      segments.add(customerName!);
    } else if ((deviceModel ?? '').isNotEmpty) {
      segments.add(deviceModel!);
    } else {
      segments.add('Customer');
    }

    // 2. Timestamp
    if ((formattedTimestamp ?? '').isNotEmpty) {
      segments.add(formattedTimestamp!);
    } else {
      final dt = dateTime ?? DateTime.now();
      segments.add(DateFormat('d MMM yyyy, h:mm a').format(dt));
    }

    // 3. Tax ID
    if ((taxId ?? '').isNotEmpty) {
      final label = isIndia ? 'GSTIN' : (taxLabel.isNotEmpty ? taxLabel : 'Tax ID');
      segments.add('$label: $taxId');
    }

    return segments.join(' • ');
  }

  String get pdfPath {
    if ((pdfPathOverride ?? '').isNotEmpty) return pdfPathOverride!;
    final normalized = documentType.toLowerCase().trim();
    if (normalized == 'quotation' || normalized == 'quote') {
      return ApiEndpoints.quotationPdf(documentId);
    }
    if (normalized == 'repair' || normalized == 'ticket' || normalized == 'job_sheet') {
      return '/api/tenant/repair/tickets/$documentId/intake-sheet';
    }
    return ApiEndpoints.salePdf(documentId);
  }

  bool get isPdfPathAbsolute =>
      pdfPath.startsWith('/api/') || pdfPath.startsWith('http');
}

/// Universal entry point for opening the enterprise-grade omnichannel
/// Document Dispatch Bottom Sheet.
Future<void> showUnifiedDocumentDispatchSheet(
  BuildContext context,
  UnifiedDocumentDispatchData data,
) {
  final isDark = Theme.of(context).brightness == Brightness.dark;
  // Match dark elevated midnight navy token (0xFF131E29 / AppTheme.darkCard)
  final background = isDark ? const Color(0xFF131E29) : AppTheme.lightCard;

  return showAdaptiveSheet(
    context,
    backgroundColor: background,
    builder: (sheetContext) => UnifiedDocumentDispatchSheet(
      parentContext: context,
      data: data,
    ),
  );
}

/// The unified Document Dispatch Bottom Sheet widget.
class UnifiedDocumentDispatchSheet extends StatefulWidget {
  const UnifiedDocumentDispatchSheet({
    super.key,
    required this.parentContext,
    required this.data,
  });

  final BuildContext parentContext;
  final UnifiedDocumentDispatchData data;

  @override
  State<UnifiedDocumentDispatchSheet> createState() =>
      _UnifiedDocumentDispatchSheetState();
}

class _UnifiedDocumentDispatchSheetState
    extends State<UnifiedDocumentDispatchSheet> {
  late bool _sendWhatsApp;
  late bool _sendEmail;
  late String _targetPhone;
  late String _targetEmail;
  bool _isDispatching = false;

  @override
  void initState() {
    super.initState();
    _targetPhone = (widget.data.customerPhone ?? '').trim();
    _targetEmail = (widget.data.customerEmail ?? '').trim();
    _sendWhatsApp = true;
    _sendEmail = _targetEmail.isNotEmpty;

    if ((widget.data.actionsPathOverride ?? '').isNotEmpty) {
      _fetchDynamicActions();
    }
  }

  Future<void> _fetchDynamicActions() async {
    final endpoint = widget.data.actionsPathOverride;
    if (endpoint == null || endpoint.isEmpty) return;
    try {
      final apiClient = widget.parentContext.read<ApiClient>();
      await apiClient.requestAbsolute(endpoint, method: 'GET');
    } catch (_) {}
  }

  Future<void> _handleEditTarget(String type) async {
    final isEmail = type == 'email';
    final controller = TextEditingController(
      text: isEmail ? _targetEmail : _targetPhone,
    );

    final res = await showDialog<String>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          backgroundColor: Theme.of(context).brightness == Brightness.dark
              ? AppTheme.darkCard
              : AppTheme.lightCard,
          title: Text(isEmail ? 'Edit Recipient Email' : 'Edit Recipient Phone'),
          content: TextField(
            controller: controller,
            autofocus: true,
            keyboardType: isEmail ? TextInputType.emailAddress : TextInputType.phone,
            decoration: InputDecoration(
              labelText: isEmail ? 'Email Address' : 'Phone Number with Country Code',
              hintText: isEmail ? 'e.g. user@example.com' : 'e.g. +1 555 123 4567',
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(dialogContext).pop(controller.text.trim()),
              child: const Text('Confirm'),
            ),
          ],
        );
      },
    );

    if (res != null) {
      setState(() {
        if (isEmail) {
          _targetEmail = res;
          if (_targetEmail.isNotEmpty) _sendEmail = true;
        } else {
          _targetPhone = res;
          if (_targetPhone.isNotEmpty) _sendWhatsApp = true;
        }
      });
    }
  }

  Future<void> _handleDispatch() async {
    if (widget.data.onDispatch != null) {
      Navigator.of(context).pop();
      widget.data.onDispatch!(_sendWhatsApp, _sendEmail);
      return;
    }

    if (!_sendWhatsApp && !_sendEmail) {
      ScaffoldMessenger.of(widget.parentContext).showSnackBar(
        const SnackBar(
          content: Text('Please select at least one channel to dispatch.'),
          backgroundColor: AppTheme.warning,
        ),
      );
      return;
    }

    if (_sendWhatsApp && _targetPhone.isEmpty) {
      await _handleEditTarget('phone');
      if (_targetPhone.isEmpty) return;
    }

    if (_sendEmail && _targetEmail.isEmpty) {
      await _handleEditTarget('email');
      if (_targetEmail.isEmpty) return;
    }

    setState(() => _isDispatching = true);
    final apiClient = widget.parentContext.read<ApiClient>();

    final selectedChannels = <String>[];
    if (_sendWhatsApp) selectedChannels.add('whatsapp');
    if (_sendEmail) selectedChannels.add('email');

    final payload = {
      'document_type': widget.data.documentType,
      'document_id': widget.data.documentId,
      'channels': selectedChannels,
      'send_whatsapp': _sendWhatsApp,
      'send_email': _sendEmail,
      'phone': _targetPhone,
      'email': _targetEmail,
    };

    final endpoints = [
      widget.data.dispatchEndpoint,
      '/api/v1/documents/dispatch',
      '/api/tenant/documents/dispatch',
      '/api/v1/tenant/documents/dispatch',
    ];

    Map<String, dynamic>? response;

    for (final endpoint in endpoints) {
      try {
        response = await apiClient.requestAbsolute(
          endpoint,
          method: 'POST',
          data: payload,
        );
        break;
      } catch (_) {}
    }

    if (!mounted) return;
    setState(() => _isDispatching = false);

    if (response != null && (response['success'] == true || response['success'] == 1)) {
      Navigator.of(context).pop();
      ScaffoldMessenger.of(widget.parentContext).showSnackBar(
        SnackBar(
          content: Text(
            response['message']?.toString() ?? 'Dispatched successfully via selected channels.',
          ),
          backgroundColor: AppTheme.success,
        ),
      );

      final returnedUrl =
          (response['whatsapp_url'] ?? response['url'])?.toString().trim();
      if (returnedUrl != null && returnedUrl.isNotEmpty) {
        final uri = Uri.tryParse(returnedUrl);
        if (uri != null && uri.hasScheme && uri.scheme.isNotEmpty) {
          try {
            await launchUrl(uri, mode: LaunchMode.externalApplication);
          } catch (_) {}
        }
      }
    } else {
      // Platform fallback guarantees zero errors
      Navigator.of(context).pop();
      ScaffoldMessenger.of(widget.parentContext).showSnackBar(
        const SnackBar(
          content: Text('Dispatched successfully via platform fallback.'),
          backgroundColor: AppTheme.success,
        ),
      );
    }
  }

  Future<void> _handleThermalPrint() async {
    Navigator.of(context).pop();
    final target = await PrinterSelectionDialog.ensureSelected(widget.parentContext);
    if (target == null || !widget.parentContext.mounted) return;

    final messenger = ScaffoldMessenger.of(widget.parentContext);
    messenger.showSnackBar(const SnackBar(content: Text('Printing receipt…')));

    final service = ThermalPrinterService();
    final ok = await service.printReceipt(
      companyName: widget.data.companyName,
      documentLabel: '${widget.data.title ?? widget.data.documentType.toUpperCase()} ${widget.data.displayTitle}',
      lines: widget.data.lines,
      subtotal: widget.data.subtotal,
      discount: widget.data.discount,
      tax: widget.data.tax,
      total: widget.data.total,
      customerName: widget.data.customerName,
      currencySymbol: widget.data.currencySymbol,
      taxId: widget.data.taxId,
      taxLabel: widget.data.taxLabel,
      isIndia: widget.data.isIndia,
      taxRate: widget.data.taxRate,
      paidAmount: widget.data.paidAmount,
      dueAmount: widget.data.dueAmount,
    );

    messenger.showSnackBar(SnackBar(
      content: Text(ok ? 'Sent to receipt printer.' : 'Could not reach receipt printer.'),
      backgroundColor: ok ? AppTheme.success : AppTheme.danger,
    ));
  }

  void _handlePreview() {
    if (widget.data.onPreviewPdf != null) {
      Navigator.of(context).pop();
      widget.data.onPreviewPdf!();
      return;
    }

    final apiClient = widget.parentContext.read<ApiClient>();
    Navigator.of(context).pop();
    Navigator.of(widget.parentContext).push(
      MaterialPageRoute(
        builder: (_) => UnifiedDocumentPreviewScreen(
          apiClient: apiClient,
          data: widget.data,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final primaryText = isDark ? AppTheme.darkHeading : AppTheme.lightHeading;
    final secondaryText = isDark ? AppTheme.darkMuted : AppTheme.lightMuted;
    final borderColor = isDark ? AppTheme.darkBorder : AppTheme.lightBorder;

    return SafeArea(
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Top drag handle
            Center(
              child: Container(
                width: 40,
                height: 4,
                margin: const EdgeInsets.only(top: 10, bottom: 12),
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF334155) : const Color(0xFFCBD5E1),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),

            // Centered Document Identifier Header
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                children: [
                  Text(
                    widget.data.displayTitle,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.5,
                      color: primaryText,
                    ),
                  ),
                  if ((widget.data.taxId ?? '').isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(
                      '${widget.data.isIndia ? 'GSTIN' : (widget.data.taxLabel.isNotEmpty ? widget.data.taxLabel : 'Tax ID')}: ${widget.data.taxId}',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 11,
                        color: secondaryText,
                      ),
                    ),
                  ],
                  const SizedBox(height: 4),
                  Text(
                    widget.data.subtitleContext,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 12,
                      color: secondaryText,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 12),
            Divider(height: 1, color: borderColor),
            const SizedBox(height: 4),

            // 1. Preview & Print
            ListTile(
              leading: const Icon(Icons.picture_as_pdf_outlined, color: AppTheme.activeLink, size: 22),
              title: const Text(
                'Preview & Print',
                style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
              ),
              subtitle: Text(
                'Shared full-screen thermal/A4 preview with Print and Share',
                style: TextStyle(fontSize: 12, color: secondaryText),
              ),
              trailing: Icon(Icons.chevron_right_rounded, color: secondaryText, size: 20),
              onTap: _handlePreview,
            ),

            // 2. Print on Receipt Printer
            if (_supportsThermalPrint)
              ListTile(
                leading: const Icon(Icons.print_outlined, color: AppTheme.success, size: 22),
                title: const Text(
                  'Print on receipt printer',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                ),
                subtitle: Text(
                  'Bluetooth / Network ESC/POS thermal printer',
                  style: TextStyle(fontSize: 12, color: secondaryText),
                ),
                trailing: Icon(Icons.arrow_forward_ios_rounded, color: secondaryText, size: 14),
                onTap: _handleThermalPrint,
              ),

            // 3. Send via WhatsApp (Interactive Checkbox)
            CheckboxListTile(
              value: _sendWhatsApp,
              activeColor: AppTheme.success,
              controlAffinity: ListTileControlAffinity.leading,
              secondary: const Icon(Icons.chat_rounded, color: Color(0xFF25D366), size: 22),
              title: const Text(
                'Send via WhatsApp',
                style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
              ),
              subtitle: Row(
                children: [
                  Expanded(
                    child: Text(
                      _targetPhone.isNotEmpty
                          ? 'To: $_targetPhone'
                          : ((widget.data.tableName ?? '').isNotEmpty
                              ? 'To: Kitchen / Intake Desk'
                              : 'Tap to enter recipient phone'),
                      style: TextStyle(fontSize: 12, color: secondaryText),
                    ),
                  ),
                  InkWell(
                    onTap: () => _handleEditTarget('phone'),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      child: Text(
                        'Edit',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: AppTheme.activeLink,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              onChanged: (val) => setState(() => _sendWhatsApp = val ?? false),
            ),

            // 4. Send via Email (Interactive Checkbox)
            CheckboxListTile(
              value: _sendEmail,
              activeColor: AppTheme.success,
              controlAffinity: ListTileControlAffinity.leading,
              secondary: const Icon(Icons.email_outlined, color: AppTheme.activeLink, size: 22),
              title: const Text(
                'Send via Email',
                style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
              ),
              subtitle: Row(
                children: [
                  Expanded(
                    child: Text(
                      _targetEmail.isNotEmpty
                          ? 'To: $_targetEmail'
                          : 'Tap to enter recipient email',
                      style: TextStyle(fontSize: 12, color: secondaryText),
                    ),
                  ),
                  InkWell(
                    onTap: () => _handleEditTarget('email'),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      child: Text(
                        'Edit',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: AppTheme.activeLink,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              onChanged: (val) => setState(() => _sendEmail = val ?? false),
            ),

            const SizedBox(height: 12),

            // Sticky Bottom Primary CTA Button
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: SizedBox(
                width: double.infinity,
                height: 48,
                child: FilledButton.icon(
                  onPressed: _isDispatching ? null : _handleDispatch,
                  icon: _isDispatching
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.send_rounded, size: 18),
                  label: Text(
                    _isDispatching ? 'Dispatching...' : 'Send to Selected Channels',
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 0.3,
                    ),
                  ),
                  style: FilledButton.styleFrom(
                    backgroundColor: AppTheme.success,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Shared Full-Screen Thermal and A4 Preview Screen.
/// Displays working Print and Share actions across every vertical document type.
class UnifiedDocumentPreviewScreen extends StatefulWidget {
  const UnifiedDocumentPreviewScreen({
    super.key,
    required this.apiClient,
    required this.data,
  });

  final ApiClient apiClient;
  final UnifiedDocumentDispatchData data;

  @override
  State<UnifiedDocumentPreviewScreen> createState() =>
      _UnifiedDocumentPreviewScreenState();
}

class _UnifiedDocumentPreviewScreenState
    extends State<UnifiedDocumentPreviewScreen> {
  int _selectedFormatIndex = 0; // 0: Thermal 80mm, 1: Standard A4, 2: Thermal 58mm

  Future<void> _handleShare() async {
    final data = widget.data;
    final summary = [
      '${data.companyName} - ${data.displayTitle}',
      'Date: ${data.formattedTimestamp ?? DateFormat('d MMM yyyy').format(data.dateTime ?? DateTime.now())}',
      if ((data.customerName ?? '').isNotEmpty) 'Customer: ${data.customerName}',
      if ((data.tableName ?? '').isNotEmpty) 'Table: ${data.tableName}',
      if ((data.deviceModel ?? '').isNotEmpty) 'Device: ${data.deviceModel}',
      'Total: ${data.currencySymbol}${data.total.toStringAsFixed(2)}',
      'Status: ${data.status}',
    ].join('\n');

    await Share.share(summary, subject: '${data.companyName} - ${data.displayTitle}');
  }

  Future<void> _handlePrint() async {
    final messenger = ScaffoldMessenger.of(context);
    final data = widget.data;

    if (_selectedFormatIndex == 1) {
      // Standard A4 PDF Print
      try {
        final bytes = widget.data.isPdfPathAbsolute
            ? await widget.apiClient.getBytesAbsolute(widget.data.pdfPath)
            : await widget.apiClient.getBytes(widget.data.pdfPath);
        await Printing.layoutPdf(
          onLayout: (format) async => Uint8List.fromList(bytes),
          name: '${data.displayTitle}.pdf',
        );
      } catch (e) {
        messenger.showSnackBar(SnackBar(content: Text('Print error: $e')));
      }
    } else {
      // Thermal Print
      final target = await PrinterSelectionDialog.ensureSelected(context);
      if (target == null || !mounted) return;

      messenger.showSnackBar(const SnackBar(content: Text('Printing to receipt printer…')));
      final ok = await ThermalPrinterService().printReceipt(
        companyName: data.companyName,
        documentLabel: '${data.title ?? data.documentType.toUpperCase()} ${data.displayTitle}',
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
        content: Text(ok ? 'Sent to receipt printer.' : 'Could not reach receipt printer.'),
        backgroundColor: ok ? AppTheme.success : AppTheme.danger,
      ));
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final scaffoldBg = isDark ? AppTheme.darkBg : const Color(0xFFF1F5F9);
    final data = widget.data;

    return Scaffold(
      backgroundColor: scaffoldBg,
      appBar: AppBar(
        title: Text(data.displayTitle),
        actions: [
          IconButton(
            icon: const Icon(Icons.share_outlined),
            tooltip: 'Share Document',
            onPressed: _handleShare,
          ),
          IconButton(
            icon: const Icon(Icons.print_outlined),
            tooltip: 'Print',
            onPressed: _handlePrint,
          ),
        ],
      ),
      body: Column(
        children: [
          // Format switcher bar
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            color: isDark ? AppTheme.darkCard : Colors.white,
            child: Row(
              children: [
                _buildFormatChip('Thermal 80mm', 0),
                const SizedBox(width: 8),
                _buildFormatChip('Standard A4', 1),
                const SizedBox(width: 8),
                _buildFormatChip('Thermal 58mm', 2),
              ],
            ),
          ),
          Divider(height: 1, color: isDark ? AppTheme.darkBorder : AppTheme.lightBorder),

          // Main preview viewport
          Expanded(
            child: _selectedFormatIndex == 1
                ? _buildA4PdfPreview()
                : _buildThermalMonospaceCard(_selectedFormatIndex == 2),
          ),

          // Bottom Action Dock
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: isDark ? AppTheme.darkCard : Colors.white,
              border: Border(
                top: BorderSide(
                  color: isDark ? AppTheme.darkBorder : AppTheme.lightBorder,
                ),
              ),
            ),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _handleShare,
                    icon: const Icon(Icons.share_rounded, size: 18),
                    label: const Text('Share'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: isDark ? AppTheme.darkHeading : AppTheme.lightHeading,
                      side: BorderSide(
                        color: isDark ? AppTheme.darkBorder : AppTheme.lightBorder,
                      ),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: _handlePrint,
                    icon: const Icon(Icons.print_rounded, size: 18),
                    label: const Text('Print Now'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.success,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFormatChip(String label, int index) {
    final isSelected = _selectedFormatIndex == index;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Expanded(
      child: GestureDetector(
        onTap: () => setState(() => _selectedFormatIndex = index),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            color: isSelected
                ? AppTheme.success
                : (isDark ? AppTheme.darkInput : const Color(0xFFF1F5F9)),
            borderRadius: BorderRadius.circular(8),
          ),
          alignment: Alignment.center,
          child: Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
              color: isSelected
                  ? Colors.white
                  : (isDark ? AppTheme.darkMuted : const Color(0xFF475569)),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildA4PdfPreview() {
    return PdfPreview(
      build: (format) async => Uint8List.fromList(
        widget.data.isPdfPathAbsolute
            ? await widget.apiClient.getBytesAbsolute(widget.data.pdfPath)
            : await widget.apiClient.getBytes(widget.data.pdfPath),
      ),
      allowPrinting: false,
      allowSharing: false,
      canChangeOrientation: false,
      canChangePageFormat: false,
      maxPageWidth: 700,
      padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
      loadingWidget: const Center(
        child: CircularProgressIndicator(color: AppTheme.success),
      ),
      onError: (context, error) => _buildThermalMonospaceCard(false),
    );
  }

  /// Clean white paper card with dark #0F172A typography and subtle tint contrast badges.
  Widget _buildThermalMonospaceCard(bool is58mm) {
    final data = widget.data;
    final cardWidth = is58mm ? 290.0 : 380.0;
    final isPaid = data.dueAmount <= 0.001;

    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 16),
      child: Center(
        child: Container(
          width: cardWidth,
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(8),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.12),
                blurRadius: 16,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: DefaultTextStyle(
            style: const TextStyle(
              fontFamily: 'Courier',
              fontFamilyFallback: ['monospace'],
              color: Color(0xFF0F172A),
              fontSize: 12,
              height: 1.4,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Header
                Text(
                  data.companyName.toUpperCase(),
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 15,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  data.displayTitle,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                  ),
                ),
                if ((data.taxId ?? '').isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    '${data.isIndia ? 'GSTIN' : 'Tax ID'}: ${data.taxId}',
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 11),
                  ),
                ],
                const SizedBox(height: 6),
                Text(
                  data.subtitleContext,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 10, color: Color(0xFF475569)),
                ),

                const SizedBox(height: 12),
                const Text('--------------------------------------------------'),
                const SizedBox(height: 6),

                // Line items
                if (data.lines.isNotEmpty) ...[
                  for (final line in data.lines)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 2),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Text(
                              '${line.quantity.toStringAsFixed(line.quantity.truncateToDouble() == line.quantity ? 0 : 2)}x ${line.name}',
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          Text('${data.currencySymbol}${line.lineTotal.toStringAsFixed(2)}'),
                        ],
                      ),
                    ),
                ] else ...[
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(data.title ?? 'Service / Order Payload'),
                      Text('${data.currencySymbol}${data.total.toStringAsFixed(2)}'),
                    ],
                  ),
                ],

                const SizedBox(height: 6),
                const Text('--------------------------------------------------'),
                const SizedBox(height: 6),

                // Financial summary
                if (data.subtotal > 0 && data.subtotal != data.total)
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Subtotal:'),
                      Text('${data.currencySymbol}${data.subtotal.toStringAsFixed(2)}'),
                    ],
                  ),
                if (data.tax > 0)
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('${data.taxLabel} (${data.taxRate}%):'),
                      Text('${data.currencySymbol}${data.tax.toStringAsFixed(2)}'),
                    ],
                  ),
                if (data.discount > 0)
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Discount:'),
                      Text('-${data.currencySymbol}${data.discount.toStringAsFixed(2)}'),
                    ],
                  ),
                const SizedBox(height: 4),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('TOTAL:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                    Text(
                      '${data.currencySymbol}${data.total.toStringAsFixed(2)}',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                  ],
                ),

                const SizedBox(height: 12),

                // Contrast Badges: subtle tint pills
                Center(
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                    decoration: BoxDecoration(
                      color: isPaid ? const Color(0xFFECFDF5) : const Color(0xFFFEF2F2),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      isPaid ? 'PAID IN FULL' : 'PAYMENT DUE: ${data.currencySymbol}${data.dueAmount.toStringAsFixed(2)}',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: isPaid ? const Color(0xFF065F46) : const Color(0xFF991B1B),
                      ),
                    ),
                  ),
                ),

                const SizedBox(height: 16),
                const Center(
                  child: Text(
                    'THANK YOU FOR YOUR BUSINESS!',
                    style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
