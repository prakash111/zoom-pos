import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/models/restaurant_models.dart';
import '../../../core/api/api_client.dart';
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
  // In production the API client is always available, so use the shared
  // server-driven sheet. The local fallback keeps isolated widget tests and
  // offline startup usable until bootstrap has provided the client.
  ApiClient? apiClient;
  try { apiClient = context.read<ApiClient>(); } catch (_) {}
  if (apiClient == null) {
    return showAdaptiveSheet<void>(
      context,
      backgroundColor: const Color(0xFF131D2D),
      builder: (_) => KotUnifiedDispatchSheet(
        kotNumber: kot.kotNumber,
        tableDetails: _kotTableDetails(kot),
        onPreviewPdf: onPreviewPdf ?? _noop,
        onThermalPrint: () => printKitchenTicket(context, kot),
        onDispatch: onDispatch ?? (_, __) {},
      ),
    );
  }
  final endpoint =
      '/api/v1/tenant/documents/kot/${Uri.encodeComponent(kot.id)}/preview-modal';
  return showAdaptiveSheet<void>(
    context,
    backgroundColor: const Color(0xFF131D2D),
    builder: (_) => DynamicSchemaPage(endpoint: endpoint, apiClient: apiClient, embedded: true),
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
class KotUnifiedDispatchSheet extends StatefulWidget {
  const KotUnifiedDispatchSheet({
    super.key,
    required this.kotNumber,
    required this.tableDetails,
    required this.onPreviewPdf,
    required this.onThermalPrint,
    required this.onDispatch,
  });

  final String kotNumber;
  final String tableDetails;
  final VoidCallback onPreviewPdf;
  final VoidCallback onThermalPrint;
  final void Function(bool sendWhatsApp, bool sendEmail) onDispatch;

  @override
  State<KotUnifiedDispatchSheet> createState() =>
      _KotUnifiedDispatchSheetState();
}

class _KotUnifiedDispatchSheetState extends State<KotUnifiedDispatchSheet> {
  bool _sendWhatsApp = true;
  bool _sendEmail = false;

  @override
  Widget build(BuildContext context) {
    const muted = Color(0xFF94A3B8);
    const accent = Color(0xFF10B981);
    return SafeArea(
      child: SingleChildScrollView(
        padding: EdgeInsets.fromLTRB(
          20,
          12,
          20,
          MediaQuery.of(context).viewInsets.bottom + 20,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 36,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.white24,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 18),
            Center(
              child: Column(
                children: [
                  Text(widget.kotNumber,
                      style: const TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          letterSpacing: .5)),
                  const SizedBox(height: 4),
                  Text(widget.tableDetails,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: muted, fontSize: 12)),
                ],
              ),
            ),
            const SizedBox(height: 24),
            _KotActionTile(
              icon: Icons.picture_as_pdf_outlined,
              title: 'Preview & Print',
              subtitle: 'View ticket details, print, or share',
              onTap: () {
                Navigator.of(context).pop();
                widget.onPreviewPdf();
              },
            ),
            _KotActionTile(
              icon: Icons.print_outlined,
              title: 'Print on receipt printer',
              subtitle: 'Bluetooth / Network thermal printer',
              onTap: () {
                Navigator.of(context).pop();
                widget.onThermalPrint();
              },
            ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: _sendWhatsApp,
              activeColor: accent,
              checkColor: Colors.white,
              controlAffinity: ListTileControlAffinity.leading,
              onChanged: (value) =>
                  setState(() => _sendWhatsApp = value ?? false),
              title: const Text('Send via WhatsApp',
                  style: TextStyle(color: Colors.white, fontSize: 14)),
              subtitle: const Text('Enter phone number or kitchen group',
                  style: TextStyle(color: muted, fontSize: 12)),
              secondary: const Icon(Icons.chat, color: Color(0xFF25D366), size: 20),
            ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: _sendEmail,
              activeColor: accent,
              checkColor: Colors.white,
              controlAffinity: ListTileControlAffinity.leading,
              onChanged: (value) => setState(() => _sendEmail = value ?? false),
              title: const Text('Send via Email',
                  style: TextStyle(color: Colors.white, fontSize: 14)),
              subtitle: const Text('Enter kitchen or manager email address',
                  style: TextStyle(color: muted, fontSize: 12)),
              secondary: const Icon(Icons.email_outlined,
                  color: Color(0xFF6366F1), size: 20),
            ),
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: () {
                Navigator.of(context).pop();
                widget.onDispatch(_sendWhatsApp, _sendEmail);
              },
              icon: const Icon(Icons.send_rounded, size: 16),
              label: const Text('Send to Selected Channels',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
              style: FilledButton.styleFrom(
                backgroundColor: accent,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _KotActionTile extends StatelessWidget {
  const _KotActionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => ListTile(
        contentPadding: EdgeInsets.zero,
        leading: Icon(icon, color: Colors.white70, size: 22),
        title: Text(title,
            style: const TextStyle(
                color: Colors.white,
                fontSize: 14,
                fontWeight: FontWeight.w500)),
        subtitle: Text(subtitle,
            style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12)),
        onTap: onTap,
      );
}

void _noop() {}

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
