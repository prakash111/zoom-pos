import 'package:flutter/material.dart';

import '../../../core/models/restaurant_models.dart';
import '../../../core/sdui/screens/dynamic_schema_page.dart';
import '../../../core/services/thermal/thermal_printer_service.dart';
import '../../../core/widgets/adaptive_sheet.dart';

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

/// Opens the same document-dispatch workflow used by invoices and due
/// payments. The ticket itself is deliberately not embedded as a white paper
/// card here; preview/print is an explicit action in the shared dark sheet.
Future<void> showKotTicketSheet(
  BuildContext context,
  KitchenTicketModel kot, {
  VoidCallback? onPreviewPdf,
  void Function(bool sendWhatsApp, bool sendEmail)? onDispatch,
}) {
  // KOT sharing is server-driven, just like invoice/quotation/POS sharing.
  // Keep the optional callbacks for source compatibility, but do not render
  // the legacy hardcoded sheet: its channel toggles could not dispatch.
  final endpoint =
      '/api/v1/tenant/documents/kot/${Uri.encodeComponent(kot.id)}/preview-modal';
  return showAdaptiveSheet<void>(
    context,
    backgroundColor: const Color(0xFF131D2D),
    builder: (_) => DynamicSchemaPage(endpoint: endpoint, embedded: true),
  );
}

String _kotTableDetails(KitchenTicketModel kot) {
  final table = (kot.tableName ?? kot.serviceType).trim();
  final sentAt = kot.createdAt?.toLocal();
  if (sentAt == null) return 'Table: $table';
  final hh = sentAt.hour.toString().padLeft(2, '0');
  final mm = sentAt.minute.toString().padLeft(2, '0');
  return 'Table: $table • Sent at $hh:$mm';
}

/// Standard document dispatch sheet for kitchen tickets. It intentionally
/// matches the invoice/due-payment sheet: centered document identity, stacked
/// actions, channel toggles, and one primary dispatch action.

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
