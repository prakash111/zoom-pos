import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/services/thermal/thermal_printer_service.dart';
import '../settings/screens/printer_selection_dialog.dart';

bool get _supportsThermalPrint =>
    !kIsWeb &&
    (defaultTargetPlatform == TargetPlatform.android ||
        defaultTargetPlatform == TargetPlatform.iOS);

/// Native bottom sheet shown after a repair ticket is created, instead of a
/// forced `wa.me` app switch. Nothing leaves the app until the user picks a
/// row. Resolves when the sheet is dismissed.
Future<void> showTicketShareSheet(
  BuildContext context,
  Map<String, dynamic> ticket,
) {
  final id = ticket['id']?.toString() ?? '';
  final device = ticket['device']?.toString() ?? '';
  final status = ticket['status']?.toString() ?? '';
  final shareText = ticket['share_text']?.toString() ?? '';
  final whatsappUrl = ticket['whatsapp_url']?.toString() ?? '';
  final customerName = ticket['customer_name']?.toString() ?? '';
  final customerPhone = ticket['customer_phone']?.toString() ?? '';
  final defect = ticket['defect']?.toString() ?? '';

  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
    ),
    builder: (sheetContext) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                    color: Colors.grey.shade300,
                    borderRadius: BorderRadius.circular(2)),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: const BoxDecoration(
                      color: Color(0xFFDCFCE7), shape: BoxShape.circle),
                  child: const Icon(Icons.check_rounded,
                      color: Color(0xFF16A34A), size: 24),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    id.isEmpty ? 'Ticket Created' : 'Ticket #$id Created',
                    style: const TextStyle(
                        fontSize: 17, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
            if (device.isNotEmpty) ...[
              const SizedBox(height: 12),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: Colors.grey.shade100,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  [device, if (status.isNotEmpty) _statusLabel(status)]
                      .join('  •  '),
                  style: TextStyle(
                      fontSize: 12.5, color: Colors.grey.shade700),
                ),
              ),
            ],
            const SizedBox(height: 12),
            const Divider(height: 1),
            _Tile(
              icon: Icons.chat_rounded,
              iconColor: const Color(0xFF25D366),
              title: 'Share via WhatsApp',
              subtitle: customerPhone.isEmpty
                  ? 'Opens WhatsApp with the message'
                  : 'Send to $customerPhone',
              onTap: () async {
                Navigator.pop(sheetContext);
                await _launch(context, whatsappUrl.isNotEmpty
                    ? whatsappUrl
                    : 'https://wa.me/?text=${Uri.encodeComponent(shareText)}');
              },
            ),
            if (_supportsThermalPrint)
              _Tile(
                icon: Icons.print_rounded,
                title: 'Print Thermal Receipt / Token',
                subtitle: 'Paired ESC/POS Bluetooth printer',
                onTap: () async {
                  final messenger = ScaffoldMessenger.of(context);
                  Navigator.pop(sheetContext);
                  // Let the operator pick / confirm the Bluetooth printer the
                  // first time — no silent "no printer" failure.
                  final target =
                      await PrinterSelectionDialog.ensureSelected(context);
                  if (target == null) return;
                  messenger.showSnackBar(
                      const SnackBar(content: Text('Printing token…')));
                  final ok = await ThermalPrinterService().printToken(
                    heading: customerName.isEmpty ? null : customerName,
                    title: id.isEmpty ? 'REPAIR TICKET' : 'TICKET #$id',
                    lines: [
                      if (device.isNotEmpty) 'Device: $device',
                      if (customerName.isNotEmpty) 'Customer: $customerName',
                      if (customerPhone.isNotEmpty) 'Phone: $customerPhone',
                      if (defect.isNotEmpty) 'Reported: $defect',
                      if (status.isNotEmpty) 'Status: ${_statusLabel(status)}',
                    ],
                  );
                  messenger.showSnackBar(SnackBar(
                    content: Text(ok
                        ? 'Token sent to printer'
                        : 'No paired printer found — set one up in Settings'),
                  ));
                },
              ),
            _Tile(
              icon: Icons.ios_share_rounded,
              title: 'System Share (SMS / Other Apps)',
              subtitle: 'Send the tracking link anywhere',
              onTap: () async {
                Navigator.pop(sheetContext);
                if (shareText.isNotEmpty) {
                  await Share.share(shareText);
                }
              },
            ),
            const Divider(height: 1),
            _Tile(
              icon: Icons.check_circle_outline_rounded,
              title: 'Done',
              subtitle: 'Back to the ticket register',
              onTap: () => Navigator.pop(sheetContext),
            ),
          ],
        ),
      );
    },
  );
}

String _statusLabel(String raw) {
  final s = raw.replaceAll('_', ' ').trim();
  if (s.isEmpty) return raw;
  return s[0].toUpperCase() + s.substring(1);
}

Future<void> _launch(BuildContext context, String url) async {
  final uri = Uri.tryParse(url);
  if (uri == null) return;
  try {
    final ok =
        await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!ok && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not open WhatsApp.')),
      );
    }
  } catch (_) {
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not open WhatsApp.')),
      );
    }
  }
}

class _Tile extends StatelessWidget {
  const _Tile({
    required this.icon,
    required this.title,
    required this.onTap,
    this.subtitle,
    this.iconColor,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final VoidCallback onTap;
  final Color? iconColor;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Icon(icon, color: iconColor ?? Colors.grey.shade700),
      title: Text(title,
          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14.5)),
      subtitle: subtitle == null
          ? null
          : Text(subtitle!, style: const TextStyle(fontSize: 12)),
      onTap: onTap,
    );
  }
}
