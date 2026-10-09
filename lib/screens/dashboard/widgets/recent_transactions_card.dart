import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../core/config/theme_provider.dart';

/// Reusable Theme-Aware Recent Transactions Card widget.
///
/// Binds customer initials avatar badges dynamically to [ThemeProvider.brandColor].
class RecentTransactionsCard extends StatelessWidget {
  const RecentTransactionsCard({
    super.key,
    this.transactions,
    this.onViewAll,
  });

  final List<Map<String, dynamic>>? transactions;
  final VoidCallback? onViewAll;

  @override
  Widget build(BuildContext context) {
    final list = transactions ?? _defaultTransactions;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.08),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Recent Transactions',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 16,
                ),
              ),
              if (onViewAll != null)
                TextButton(
                  onPressed: onViewAll,
                  child: const Text('View All'),
                ),
            ],
          ),
          const SizedBox(height: 12),
          for (final tx in list)
            _buildTransactionItem(context, tx),
        ],
      ),
    );
  }

  static const List<Map<String, dynamic>> _defaultTransactions = [
    {
      'customer_name': 'Walk-in Customer',
      'created_at': 'Today, 02:45 PM',
      'total_formatted': '\$48.50',
      'status': 'Completed',
    },
    {
      'customer_name': 'Peter Parker',
      'created_at': 'Today, 01:20 PM',
      'total_formatted': '\$112.00',
      'status': 'Completed',
    },
    {
      'customer_name': 'Sarah Connor',
      'created_at': 'Today, 11:05 AM',
      'total_formatted': '\$35.75',
      'status': 'Completed',
    },
  ];
}

/// Standalone avatar builder helper matching Section 2 C specification.
Widget buildTransactionAvatar(BuildContext context, String initials) =>
    _buildTransactionAvatar(context, initials);

Widget _buildTransactionAvatar(BuildContext context, String initials) {
  // Listen to the selected brand color
  final brandColor = Provider.of<ThemeProvider>(context).brandColor;

  return Container(
    width: 40,
    height: 40,
    decoration: BoxDecoration(
      color: brandColor.withValues(alpha: 0.18), // DYNAMIC TINT BACKGROUND
      shape: BoxShape.circle,
      border: Border.all(
        color: brandColor.withValues(alpha: 0.45), // DYNAMIC BORDER ACCENT
        width: 1,
      ),
    ),
    alignment: Alignment.center,
    child: Text(
      initials,
      style: TextStyle(
        color: brandColor, // DYNAMIC INITIALS TEXT (e.g. Orange for #EA580C)
        fontSize: 13,
        fontWeight: FontWeight.bold,
        letterSpacing: 0.5,
      ),
    ),
  );
}

/// Helper to extract initials from customer name.
String _getInitials(String name) {
  final trimmed = name.trim();
  if (trimmed.isEmpty) return 'W';
  final parts = trimmed.split(RegExp(r'\s+'));
  if (parts.length >= 2 && parts[0].isNotEmpty && parts[1].isNotEmpty) {
    return '${parts[0][0]}${parts[1][0]}'.toUpperCase();
  }
  return trimmed.substring(0, trimmed.length >= 2 ? 2 : 1).toUpperCase();
}

/// Standalone transaction list item builder matching Section 2 C specification.
Widget buildTransactionItem(BuildContext context, Map<String, dynamic> tx) =>
    _buildTransactionItem(context, tx);

// Usage in Transaction List Tile:
Widget _buildTransactionItem(BuildContext context, Map<String, dynamic> tx) {
  final name = tx['customer_name'] ?? 'Walk-in';
  final initials = _getInitials(name);

  return Container(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
    margin: const EdgeInsets.only(bottom: 8),
    decoration: BoxDecoration(
      color: const Color(0xFF1E293B).withValues(alpha: 0.6),
      borderRadius: BorderRadius.circular(12),
      border: Border.all(
        color: const Color(0xFF334155).withValues(alpha: 0.3),
      ),
    ),
    child: Row(
      children: [
        // Theme-Aware Avatar Icon
        _buildTransactionAvatar(context, initials),
        const SizedBox(width: 12),
        // Customer Details
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                name,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                tx['created_at'] ?? 'Just now',
                style: const TextStyle(
                  color: Color(0xFF94A3B8),
                  fontSize: 11,
                ),
              ),
            ],
          ),
        ),
        // Amount & Status
        Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(
              tx['total_formatted'] ?? '\$0.00',
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.bold,
                fontSize: 14,
              ),
            ),
            const SizedBox(height: 2),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(
                color: const Color(0xFF10B981).withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Text(
                tx['status'] ?? 'Completed',
                style: const TextStyle(
                  color: Color(0xFF10B981),
                  fontSize: 10,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      ],
    ),
  );
}
