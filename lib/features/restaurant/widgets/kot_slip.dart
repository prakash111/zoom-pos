import 'package:flutter/material.dart';

import '../../../core/models/restaurant_models.dart';
import '../../../core/services/thermal/thermal_printer_service.dart';

/// Plain-text body lines for a Kitchen Order Ticket thermal slip — mirrors the
/// web `KotController::print()` layout: table / service / server, then each
/// item as `2 x Truffle Mushroom Burger` with any variant / spice / modifier /
/// note indented beneath it, the kitchen note, and the sent-at timestamp.
List<String> kitchenTicketSlipLines(KitchenTicketModel kot) {
  String qty(double q) =>
      q == q.roundToDouble() ? q.toStringAsFixed(0) : q.toStringAsFixed(1);
  String pretty(String s) => s.replaceAll('_', ' ');
  String two(int n) => n.toString().padLeft(2, '0');

  final lines = <String>[
    'Table: ${kot.tableName ?? pretty(kot.serviceType)}',
    'Service: ${pretty(kot.serviceType)}',
    if ((kot.serverName ?? '').isNotEmpty) 'Server: ${kot.serverName}',
    '',
  ];

  for (final item in kot.items) {
    lines.add('${qty(item.quantity)} x ${item.name}');
    if ((item.variant ?? '').isNotEmpty) lines.add('   - ${item.variant}');
    if ((item.spiceLevel ?? '').isNotEmpty) {
      lines.add('   - Spice: ${item.spiceLevel}');
    }
    for (final m in item.modifiers) {
      final label = (m['name'] ?? m['label'] ?? '').toString().trim();
      if (label.isNotEmpty) lines.add('   + $label');
    }
    if (item.note.trim().isNotEmpty) lines.add('   * ${item.note.trim()}');
  }

  if ((kot.kitchenNotes ?? '').trim().isNotEmpty) {
    lines
      ..add('')
      ..add('Note: ${kot.kitchenNotes!.trim()}');
  }

  final stamp = kot.createdAt?.toLocal();
  if (stamp != null) {
    lines
      ..add('')
      ..add('Sent: ${stamp.year}-${two(stamp.month)}-${two(stamp.day)} '
          '${two(stamp.hour)}:${two(stamp.minute)}');
  }
  if (kot.prepMinutes != null) lines.add('Prep target: ${kot.prepMinutes} min');

  return lines;
}

/// A dismissible bottom sheet that shows the KOT as a monospace thermal
/// ticket (white paper facsimile, dashed rules) with **Print KOT** and
/// **Close** — replaces the transient "sent to kitchen" snackbar.
Future<void> showKotTicketSheet(BuildContext context, KitchenTicketModel kot) {
  final lines = kitchenTicketSlipLines(kot);
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (sheetContext) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Icon(Icons.receipt_long, size: 20),
                const SizedBox(width: 8),
                Text('Kitchen Order Ticket',
                    style: Theme.of(sheetContext).textTheme.titleMedium),
              ],
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 8),
              decoration: BoxDecoration(
                color: Theme.of(sheetContext).colorScheme.surfaceContainerHighest,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: [
                  _KotChannel(icon: Icons.print, label: 'Thermal', color: Colors.green, onTap: () => printKitchenTicket(context, kot)),
                  _KotChannel(icon: Icons.chat, label: 'WhatsApp', color: const Color(0xFF25D366), onTap: () => _kotUnavailable(context, 'WhatsApp')),
                  _KotChannel(icon: Icons.sms, label: 'SMS', color: Colors.blue, onTap: () => _kotUnavailable(context, 'SMS')),
                  _KotChannel(icon: Icons.email, label: 'Email', color: Colors.indigo, onTap: () => _kotUnavailable(context, 'Email')),
                  _KotChannel(icon: Icons.picture_as_pdf, label: 'PDF', color: Colors.red, onTap: () => _kotUnavailable(context, 'PDF')),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white, // a thermal receipt is white paper
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFCBD5E1)),
              ),
              child: DefaultTextStyle(
                style: const TextStyle(
                    fontFamily: 'monospace',
                    fontFamilyFallback: ['Courier', 'monospace'],
                    fontSize: 12.5,
                    height: 1.5,
                    color: Color(0xFF0F172A)),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Center(
                        child: Text('KITCHEN ORDER TICKET',
                            style: TextStyle(
                                fontFamily: 'monospace',
                                fontWeight: FontWeight.bold))),
                    Center(
                        child: Text(kot.kotNumber,
                            style: const TextStyle(
                                fontFamily: 'monospace',
                                fontWeight: FontWeight.bold))),
                    const Text('--------------------------------'),
                    for (final l in lines) Text(l.isEmpty ? ' ' : l),
                    const Text('--------------------------------'),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.of(sheetContext).pop(),
                    child: const Text('Close'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  flex: 2,
                  child: FilledButton.icon(
                    onPressed: () {
                      Navigator.of(sheetContext).pop();
                      printKitchenTicket(context, kot);
                    },
                    icon: const Icon(Icons.print_outlined, size: 18),
                    label: const Text('Print KOT'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class _KotChannel extends StatelessWidget {
  const _KotChannel({required this.icon, required this.label, required this.color, required this.onTap});
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 3),
          child: Column(children: [Icon(icon, color: color, size: 19), const SizedBox(height: 3), Text(label, style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w600))]),
        ),
      );
}

void _kotUnavailable(BuildContext context, String channel) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$channel dispatch can be configured in Settings.')));
}

/// Prints [kot] to the saved Bluetooth thermal printer and reports the outcome
/// through the nearest [ScaffoldMessenger]. Safe to call from any screen that
/// has a Scaffold above it.
Future<void> printKitchenTicket(
    BuildContext context, KitchenTicketModel kot) async {
  final messenger = ScaffoldMessenger.of(context);
  messenger.showSnackBar(SnackBar(
    content: Text('Printing ${kot.kotNumber}…'),
    duration: const Duration(seconds: 1),
  ));

  final ok = await ThermalPrinterService().printToken(
    heading: 'KITCHEN ORDER TICKET',
    title: kot.kotNumber,
    lines: kitchenTicketSlipLines(kot),
  );

  if (!context.mounted) return;
  messenger
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(
      content: Text(ok
          ? '${kot.kotNumber} sent to the kitchen printer.'
          : 'No thermal printer connected — set one up in '
              'Settings → Printer & Hardware.'),
    ));
}
