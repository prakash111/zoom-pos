import 'package:flutter/material.dart';

import '../../core/services/thermal/thermal_printer_service.dart';
import '../../core/widgets/unified_document_dispatch_sheet.dart';
import '../../features/pos/screens/invoice_preview_screen.dart';

/// Screen presenting the live receipt preview with direct routing to
/// the Unified Dispatch Bottom Sheet instead of OS-level share dialogs.
class ReceiptPreviewScreen extends StatefulWidget {
  const ReceiptPreviewScreen({
    super.key,
    required this.data,
  });

  final InvoicePreviewData data;

  @override
  State<ReceiptPreviewScreen> createState() => _ReceiptPreviewScreenState();
}

class _ReceiptPreviewScreenState extends State<ReceiptPreviewScreen> {
  ReceiptFormat _format = ReceiptFormat.thermal80;

  void _openUnifiedDispatch() {
    final d = widget.data;
    final dispatchData = UnifiedDocumentDispatchData(
      documentType: 'Receipt',
      documentId: 'receipt-preview',
      documentNumber: 'RCP-PREVIEW',
      companyName: d.companyName,
      customerName: d.customerName,
      customerPhone: d.customerPhone,
      customerEmail: d.customerEmail,
      currencySymbol: d.currencySymbol,
      subtotal: d.subtotal,
      discount: d.discount,
      tax: d.taxTotal,
      total: d.grandTotal,
      paidAmount: d.paidAmount ?? d.grandTotal,
      dueAmount: d.dueAmount,
      notes: d.notes,
      lines: d.items
          .map((item) => ReceiptLine(
                name: item.product.name,
                quantity: item.quantity,
                unitPrice: item.product.salePrice,
                lineTotal: item.lineTotal,
              ))
          .toList(),
    );

    showUnifiedDocumentDispatchSheet(context, dispatchData);
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Receipt Preview'),
        elevation: 0,
        backgroundColor: isDark ? const Color(0xFF1E293B) : Colors.white,
        actions: [
          // Intercept Share trigger: open Unified Dispatch Bottom Sheet directly
          IconButton(
            icon: const Icon(Icons.share_outlined),
            tooltip: 'Dispatch & Share',
            onPressed: _openUnifiedDispatch,
          ),
        ],
      ),
      body: Column(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            color: isDark ? const Color(0xFF1E293B) : Colors.white,
            child: Row(
              children: [
                Expanded(
                  child: SegmentedButton<ReceiptFormat>(
                    segments: const [
                      ButtonSegment(
                        value: ReceiptFormat.thermal80,
                        label: Text('80mm POS'),
                      ),
                      ButtonSegment(
                        value: ReceiptFormat.thermal58,
                        label: Text('58mm'),
                      ),
                      ButtonSegment(
                        value: ReceiptFormat.a4,
                        label: Text('A4 Slip'),
                      ),
                    ],
                    selected: {_format},
                    onSelectionChanged: (selected) {
                      setState(() => _format = selected.first);
                    },
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Center(
                child: Container(
                  width: _format == ReceiptFormat.thermal58
                      ? 280
                      : (_format == ReceiptFormat.thermal80 ? 360 : 420),
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(12),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.08),
                        blurRadius: 15,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Text(
                        widget.data.companyName,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF0F172A),
                        ),
                      ),
                      const SizedBox(height: 4),
                      const Text(
                        '*** RECEIPT PREVIEW ***',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF64748B),
                          letterSpacing: 0.5,
                        ),
                      ),
                      const SizedBox(height: 14),
                      const Divider(height: 1),
                      const SizedBox(height: 12),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Customer:', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                          Text(
                            widget.data.customerName ?? 'Walk-in Client',
                            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      const Divider(height: 1),
                      const SizedBox(height: 8),
                      for (final item in widget.data.items) ...[
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 4),
                          child: Row(
                            children: [
                              Expanded(
                                child: Text(
                                  item.product.name,
                                  style: const TextStyle(fontSize: 13, color: Color(0xFF0F172A)),
                                ),
                              ),
                              Text(
                                '${item.quantity} × ${widget.data.currencySymbol}${item.product.salePrice.toStringAsFixed(2)}',
                                style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                              ),
                              const SizedBox(width: 8),
                              Text(
                                '${widget.data.currencySymbol}${item.lineTotal.toStringAsFixed(2)}',
                                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
                              ),
                            ],
                          ),
                        ),
                      ],
                      const SizedBox(height: 8),
                      const Divider(height: 1),
                      const SizedBox(height: 8),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Grand Total', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Color(0xFF0F172A))),
                          Text(
                            '${widget.data.currencySymbol}${widget.data.grandTotal.toStringAsFixed(2)}',
                            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: Color(0xFF059669)),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF1E293B) : Colors.white,
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.05),
                  blurRadius: 10,
                  offset: const Offset(0, -3),
                ),
              ],
            ),
            child: SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: _openUnifiedDispatch,
                icon: const Icon(Icons.share_outlined),
                label: const Text('Dispatch / Share Receipt'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF2563EB),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
