import 'package:flutter/material.dart';

/// Launches the styled POS date range picker modal matching the Sales screen.
/// Features a dark slate surface (`0xFF0F172A`) and emerald green highlight (`0xFF10B981`).
Future<DateTimeRange?> showPosDateRangePicker({
  required BuildContext context,
  DateTimeRange? initialDateRange,
  DateTime? firstDate,
  DateTime? lastDate,
}) async {
  final now = DateTime.now();
  return showDateRangePicker(
    context: context,
    firstDate: firstDate ?? DateTime(now.year - 2),
    lastDate: lastDate ?? DateTime(now.year + 2),
    initialDateRange: initialDateRange ??
        DateTimeRange(
          start: now.subtract(const Duration(days: 7)),
          end: now,
        ),
    builder: (context, child) {
      return Theme(
        data: Theme.of(context).copyWith(
          colorScheme: const ColorScheme.dark(
            primary: Color(0xFF10B981), // Emerald green highlight
            onPrimary: Colors.white,
            surface: Color(0xFF0F172A), // Dark slate dialog background
            onSurface: Colors.white,
          ),
          dialogBackgroundColor: const Color(0xFF0F172A),
        ),
        child: child ?? const SizedBox(),
      );
    },
  );
}
