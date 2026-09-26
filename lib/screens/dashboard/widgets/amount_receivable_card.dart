import 'package:flutter/material.dart';
import '../../../core/models/dashboard_summary_model.dart';
import '../../../core/services/dynamic_string_service.dart';

class AmountReceivableCard extends StatelessWidget {
  const AmountReceivableCard({
    super.key,
    required this.receivables,
    this.onOpenTransactions,
  });

  final ReceivablesSummaryData receivables;
  final VoidCallback? onOpenTransactions;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: isDark ? Colors.white.withValues(alpha: 0.08) : const Color(0xFFE2E8F0),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.03),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header
          Wrap(
            alignment: WrapAlignment.spaceBetween,
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 8,
            runSpacing: 6,
            children: [
              Text(
                context.tr('Amount Receivable'),
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                  color: isDark ? Colors.white : const Color(0xFF0F172A),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFFEF4444).withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  '${receivables.outstandingInvoicesCount} ${context.tr('Invoices')}',
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFFEF4444),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Big Total Outstanding Figure
          Text(
            receivables.formatted,
            style: TextStyle(
              fontSize: 26,
              fontWeight: FontWeight.w900,
              letterSpacing: -0.5,
              color: isDark ? Colors.white : const Color(0xFF0F172A),
            ),
          ),
          Text(
            context.tr('Total outstanding pending customer payment'),
            style: TextStyle(
              fontSize: 11,
              color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
            ),
          ),
          const SizedBox(height: 16),
          const Divider(height: 1),
          const SizedBox(height: 14),

          // 1. Overdue Amount Row
          InkWell(
            borderRadius: BorderRadius.circular(8),
            onTap: () {
              Navigator.pushNamed(
                context,
                '/sales',
                arguments: {'initial_due_filter': 'overdue'},
              );
            },
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 4.0, horizontal: 2.0),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(5),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEF4444).withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: const Icon(Icons.error_outline, color: Color(0xFFEF4444), size: 15),
                  ),
                  const SizedBox(width: 8),
                  Text(context.tr('Overdue Amount'), style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 13)),
                  const Spacer(),
                  Text(
                    receivables.formattedOverdue,
                    style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(width: 4),
                  const Icon(Icons.chevron_right, color: Color(0xFF64748B), size: 16),
                ],
              ),
            ),
          ),

          const SizedBox(height: 6),

          // 2. Due Today Row
          InkWell(
            borderRadius: BorderRadius.circular(8),
            onTap: () {
              Navigator.pushNamed(
                context,
                '/sales',
                arguments: {'initial_due_filter': 'due_today'},
              );
            },
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 4.0, horizontal: 2.0),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(5),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF59E0B).withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: const Icon(Icons.access_time, color: Color(0xFFF59E0B), size: 15),
                  ),
                  const SizedBox(width: 8),
                  Text(context.tr('Due Today'), style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 13)),
                  const Spacer(),
                  Text(
                    receivables.formattedDueToday,
                    style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(width: 4),
                  const Icon(Icons.chevron_right, color: Color(0xFF64748B), size: 16),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          // Action Button
          if (onOpenTransactions != null)
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: onOpenTransactions,
                icon: const Icon(Icons.send_outlined, size: 14),
                label: Text(context.tr('Send Payment Reminders'), style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                style: OutlinedButton.styleFrom(
                  foregroundColor: const Color(0xFF2563EB),
                  side: const BorderSide(color: Color(0xFF2563EB)),
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
