import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:share_plus/share_plus.dart';

import '../../../core/models/restaurant_models.dart';

/// "QR Stand" preview/print/share sheet for one dining table — mirrors the
/// web tenant's printable stand card (TableOrderController::qrCard() /
/// resources/views/restaurant/table-qr-card.blade.php) so the same
/// `qr_order_url` (the public "scan to view menu & order" link) is
/// available as a native, shareable/printable card on mobile too.
class QrStandDialog extends StatefulWidget {
  const QrStandDialog({super.key, required this.table, required this.businessName});

  final DiningTableModel table;
  final String businessName;

  @override
  State<QrStandDialog> createState() => _QrStandDialogState();
}

class _QrStandDialogState extends State<QrStandDialog> {
  final _boundaryKey = GlobalKey();
  bool _isBusy = false;

  Future<Uint8List?> _captureCard() async {
    final boundary = _boundaryKey.currentContext?.findRenderObject() as RenderRepaintBoundary?;
    if (boundary == null) return null;
    final image = await boundary.toImage(pixelRatio: 3);
    final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
    return byteData?.buffer.asUint8List();
  }

  Future<void> _share() async {
    setState(() => _isBusy = true);
    try {
      final bytes = await _captureCard();
      if (bytes == null) return;
      await Share.shareXFiles(
        [XFile.fromData(bytes, name: '${widget.table.tableNumber}-qr-stand.png', mimeType: 'image/png')],
        text: 'Scan to view menu & order — ${widget.table.tableNumber}',
      );
    } finally {
      if (mounted) setState(() => _isBusy = false);
    }
  }

  Future<void> _print() async {
    setState(() => _isBusy = true);
    try {
      final bytes = await _captureCard();
      if (bytes == null) return;
      final image = pw.MemoryImage(bytes);
      final doc = pw.Document();
      doc.addPage(
        pw.Page(
          pageFormat: PdfPageFormat.a6,
          build: (context) => pw.Center(child: pw.Image(image)),
        ),
      );
      await Printing.layoutPdf(onLayout: (_) => doc.save(), name: '${widget.table.tableNumber}-qr-stand.pdf');
    } finally {
      if (mounted) setState(() => _isBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final orderUrl = widget.table.qrOrderUrl;

    return Dialog(
      insetPadding: const EdgeInsets.all(24),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Stand: ${widget.table.tableNumber}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.of(context).pop()),
              ],
            ),
            const SizedBox(height: 12),
            if (orderUrl == null || orderUrl.isEmpty)
              const Padding(
                padding: EdgeInsets.all(24),
                child: Text('No QR code is available for this table yet.'),
              )
            else ...[
              RepaintBoundary(
                key: _boundaryKey,
                child: Container(
                  width: 260,
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: Colors.green.shade300, width: 2),
                  ),
                  child: Column(
                    children: [
                      Text(widget.businessName, textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Colors.black)),
                      const SizedBox(height: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                        decoration: BoxDecoration(color: Colors.green.shade600, borderRadius: BorderRadius.circular(20)),
                        child: Text(widget.table.tableNumber, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                      ),
                      const SizedBox(height: 10),
                      const Text('SCAN TO VIEW MENU & ORDER', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Colors.black54, letterSpacing: 0.5)),
                      const SizedBox(height: 10),
                      QrImageView(data: orderUrl, size: 160, backgroundColor: Colors.white),
                      const SizedBox(height: 10),
                      const Text(
                        "Point your phone's camera at the QR code above.\nBrowse our digital menu & order directly from your table!",
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 10, color: Colors.black54),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  OutlinedButton.icon(
                    onPressed: _isBusy ? null : _print,
                    icon: const Icon(Icons.print_outlined, size: 18),
                    label: const Text('Print'),
                  ),
                  const SizedBox(width: 10),
                  FilledButton.icon(
                    onPressed: _isBusy ? null : _share,
                    icon: const Icon(Icons.share_outlined, size: 18),
                    label: const Text('Share / Download'),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}
