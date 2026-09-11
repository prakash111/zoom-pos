import 'package:flutter/material.dart';

/// The "shift is open" summary card on the Cash Register screen — terminal
/// name + open badge, opening float, sales/cash-in/cash-out for the shift,
/// and expected cash. Colors are derived from [Theme.of(context).brightness]
/// rather than fixed, so the card (and the text sitting on it) stays legible
/// in dark mode instead of remaining a hardcoded pale green fill with
/// theme-default text.
class CashRegisterStatusCard extends StatelessWidget {
  const CashRegisterStatusCard({
    super.key,
    required this.terminalId,
    required this.openingFloat,
    required this.salesThisShift,
    required this.cashIn,
    required this.cashOut,
    required this.expectedCash,
  });

  final String terminalId;
  final String openingFloat;
  final String salesThisShift;
  final String cashIn;
  final String cashOut;
  final String expectedCash;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardBg = isDark ? const Color(0xFF1E293B) : Colors.green.shade50;
    final cardBorder = isDark ? const Color(0xFF334155) : Colors.green.shade100;
    final badgeBg = isDark ? const Color(0xFF064E3B) : Colors.green.shade100;
    final badgeFg = isDark ? const Color(0xFF34D399) : Colors.green.shade800;
    final labelColor = isDark ? const Color(0xFF94A3B8) : Colors.black54;
    final valueColor = isDark ? const Color(0xFFF8FAFC) : Colors.black87;

    return Card(
      color: cardBg,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: cardBorder),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              decoration: BoxDecoration(
                  color: badgeBg, borderRadius: BorderRadius.circular(20)),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.check_circle, color: badgeFg, size: 16),
                  const SizedBox(width: 6),
                  Text('$terminalId — Open',
                      style: TextStyle(
                          fontWeight: FontWeight.w600, color: badgeFg)),
                ],
              ),
            ),
            const SizedBox(height: 12),
            _MetricRow(
                label: 'Opening float',
                value: openingFloat,
                labelColor: labelColor,
                valueColor: valueColor),
            _MetricRow(
                label: 'Sales this shift',
                value: salesThisShift,
                labelColor: labelColor,
                valueColor: valueColor),
            _MetricRow(
                label: 'Cash in',
                value: cashIn,
                labelColor: labelColor,
                valueColor: valueColor),
            _MetricRow(
                label: 'Cash out',
                value: cashOut,
                labelColor: labelColor,
                valueColor: valueColor),
            Divider(color: cardBorder),
            _MetricRow(
              label: 'Expected cash',
              value: expectedCash,
              bold: true,
              labelColor: labelColor,
              valueColor: valueColor,
            ),
          ],
        ),
      ),
    );
  }
}

class _MetricRow extends StatelessWidget {
  const _MetricRow({
    required this.label,
    required this.value,
    this.bold = false,
    this.labelColor,
    this.valueColor,
  });

  final String label;
  final String value;
  final bool bold;
  final Color? labelColor;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    final weight = bold ? FontWeight.bold : FontWeight.normal;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(fontWeight: weight, color: labelColor)),
          Text(value, style: TextStyle(fontWeight: weight, color: valueColor)),
        ],
      ),
    );
  }
}
