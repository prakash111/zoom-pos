import 'package:flutter/material.dart';

import '../../core/services/thermal/thermal_printer_service.dart';
import '../../core/widgets/unified_document_dispatch_sheet.dart';
import '../../features/pos/screens/invoice_preview_screen.dart';

/// Screen presenting the A4/thermal invoice view with direct routing
/// to UnifiedDispatchBottomSheet upon tapping any share action.
class InvoiceViewScreen extends StatelessWidget {
  const InvoiceViewScreen({
    super.key,
    required this.data,
  });

  final InvoicePreviewData data;

  void _openUnifiedDispatch(BuildContext context) {
    final dispatchData = UnifiedDocumentDispatchData(
      documentType: data.documentType,
      documentId: 'invoice-view',
      documentNumber: '${data.documentType.toUpperCase()}-INV',
      companyName: data.companyName,
      customerName: data.customerName,
      customerPhone: data.customerPhone,
      customerEmail: data.customerEmail,
      currencySymbol: data.currencySymbol,
      subtotal: data.subtotal,
      discount: data.discount,
      tax: data.taxTotal,
      total: data.grandTotal,
      paidAmount: data.paidAmount ?? data.grandTotal,
      dueAmount: data.dueAmount,
      notes: data.notes,
      lines: data.items
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
        title: Text('${data.documentType} View'),
        elevation: 0,
        backgroundColor: isDark ? const Color(0xFF1E293B) : Colors.white,
        actions: [
          // Intercept share trigger: open Unified Dispatch Bottom Sheet directly
          IconButton(
            icon: const Icon(Icons.share_outlined),
            tooltip: 'Dispatch & Share',
            onPressed: () => _openUnifiedDispatch(context),
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Center(
                child: Container(
                  width: 480,
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.06),
                        blurRadius: 15,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                data.companyName,
                                style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: Color(0xFF0F172A)),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                'TAX INVOICE',
                                style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Theme.of(context).primaryColor, letterSpacing: 0.5),
                              ),
                            ],
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(
                              color: const Color(0xFFECFDF5),
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(color: const Color(0xFFA7F3D0)),
                            ),
                            child: const Text('CONFIRMED', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFF065F46))),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      const Divider(height: 1),
                      const SizedBox(height: 14),
                      Text('Customer: ${data.customerName ?? "Walk-in Client"}', style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF1E293B))),
                      if ((data.customerPhone ?? '').isNotEmpty)
                        Text('Phone: ${data.customerPhone}', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                      if ((data.customerEmail ?? '').isNotEmpty)
                        Text('Email: ${data.customerEmail}', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                      const SizedBox(height: 16),
                      Table(
                        columnWidths: const {
                          0: FlexColumnWidth(4),
                          1: FlexColumnWidth(1),
                          2: FlexColumnWidth(2),
                          3: FlexColumnWidth(2),
                        },
                        children: [
                          TableRow(
                            decoration: const BoxDecoration(color: Color(0xFFF1F5F9)),
                            children: const [
                              Padding(padding: EdgeInsets.all(8), child: Text('ITEM', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11))),
                              Padding(padding: EdgeInsets.all(8), child: Text('QTY', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11))),
                              Padding(padding: EdgeInsets.all(8), child: Text('PRICE', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11))),
                              Padding(padding: EdgeInsets.all(8), child: Text('TOTAL', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11))),
                            ],
                          ),
                          for (final item in data.items)
                            TableRow(
                              children: [
                                Padding(padding: const EdgeInsets.all(8), child: Text(item.product.name, style: const TextStyle(fontSize: 12))),
                                Padding(padding: const EdgeInsets.all(8), child: Text('${item.quantity}', style: const TextStyle(fontSize: 12))),
                                Padding(padding: const EdgeInsets.all(8), child: Text('${data.currencySymbol}${item.product.salePrice.toStringAsFixed(2)}', style: const TextStyle(fontSize: 12))),
                                Padding(padding: const EdgeInsets.all(8), child: Text('${data.currencySymbol}${item.lineTotal.toStringAsFixed(2)}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600))),
                              ],
                            ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      Align(
                        alignment: Alignment.centerRight,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text('Subtotal: ${data.currencySymbol}${data.subtotal.toStringAsFixed(2)}', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                            if (data.discount > 0)
                              Text('Discount: -${data.currencySymbol}${data.discount.toStringAsFixed(2)}', style: const TextStyle(fontSize: 12, color: Color(0xFFEF4444))),
                            if (data.taxTotal > 0)
                              Text('Tax: ${data.currencySymbol}${data.taxTotal.toStringAsFixed(2)}', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                            const SizedBox(height: 4),
                            Text('Total: ${data.currencySymbol}${data.grandTotal.toStringAsFixed(2)}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: Color(0xFF0F172A))),
                          ],
                        ),
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
                onPressed: () => _openUnifiedDispatch(context),
                icon: const Icon(Icons.share_outlined),
                label: const Text('Dispatch / Share to Channels'),
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
