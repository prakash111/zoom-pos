import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../core/config/app_config.dart';
import '../core/widgets/adaptive_sheet.dart';

/// Opens the Change-log modal across mobile, tablet, desktop and web.
Future<void> showAppChangelogModal(BuildContext context) {
  return showAdaptiveSheet<void>(
    context,
    maxWidth: 680,
    maxHeight: 740,
    builder: (ctx) => const AppChangelogSheet(),
  );
}

class AppChangelogSheet extends StatefulWidget {
  const AppChangelogSheet({super.key});

  @override
  State<AppChangelogSheet> createState() => _AppChangelogSheetState();
}

class _AppChangelogSheetState extends State<AppChangelogSheet> {
  bool _isLoading = true;
  String _selectedFilter = 'all';
  List<Map<String, dynamic>> _changelogs = [];

  static const List<Map<String, dynamic>> _fallbackChangelogs = [
    {
      'version': '1.0.6',
      'release_date': '11 Oct 2026',
      'title': 'Navigation Scroll Restoration & Loyalty POS Guardrails',
      'is_latest': true,
      'items': [
        {'type': 'NEW', 'text': 'Preserved Drawer Scroll Controller State Across Closes/Re-opens'},
        {'type': 'NEW', 'text': 'Auto-scroll to Active Route Item (ensureVisible) with Centering'},
        {'type': 'NEW', 'text': 'Customer Store Wallet Tender Integration at POS Checkout Sheet'},
        {'type': 'NEW', 'text': 'Minimum Reward Points Threshold Validation & Warning Hint'},
        {'type': 'NEW', 'text': 'Cart Percentage Discount Cap & Real-time Max Points Calculation'},
        {'type': 'NEW', 'text': 'Real-time Points Accrual & Customer Ledger Auto-Sync on Completed Sale'},
        {'type': 'IMPROVED', 'text': 'Split Payment Tender Validation with Multi-method Settlement'},
        {'type': 'FIX', 'text': 'Drawer Item Selection Styling Highlight & Active Route State'},
      ],
    },
    {
      'version': '1.0.5',
      'release_date': '08 Oct 2026',
      'title': 'Customer Loyalty Rewards & Store Wallet Tender',
      'items': [
        {'type': 'NEW', 'text': 'Customer Prepaid Store Wallet Tender with Live Balance Check'},
        {'type': 'NEW', 'text': 'Reward Points Redemption Guardrails & Net Cart Percentage Cap'},
        {'type': 'IMPROVED', 'text': 'Customer Ledger Debit Transaction Audit on Completed Orders'},
      ],
    },
    {
      'version': '1.0.4',
      'release_date': '29 Sep 2026',
      'title': 'Split Payment Tenders & Offline Queue Sync',
      'items': [
        {'type': 'NEW', 'text': 'Multi-tender Split Payment (Cash + Card + UPI/Transfer)'},
        {'type': 'IMPROVED', 'text': 'Offline Sync Queue Resiliency with Exponential Backoff'},
        {'type': 'FIX', 'text': 'Receipt Bill Number Duplication on Rapid Tender Clicks'},
      ],
    },
    {
      'version': '1.0.3',
      'release_date': '15 Sep 2026',
      'title': 'Restaurant Floor Plan & Barcode Scanner Hardware',
      'items': [
        {'type': 'NEW', 'text': 'Restaurant Floor Plan, Table Management & Kitchen Order Ticket (KOT)'},
        {'type': 'IMPROVED', 'text': 'Barcode Scanner Hardware Integration for Desktop & USB Scanners'},
        {'type': 'FIX', 'text': 'Shift Register Cash Reconciliation & Drawer Opening Triggers'},
      ],
    },
    {
      'version': '1.0.2',
      'release_date': '01 Sep 2026',
      'title': 'Dynamic Cart Sheet & Inclusive Taxes',
      'items': [
        {'type': 'NEW', 'text': 'Dynamic SDUI Cart & Category Pills Ribbon'},
        {'type': 'IMPROVED', 'text': 'Camera Barcode Scanner Performance & Haptic Feedback'},
        {'type': 'FIX', 'text': 'Tax Calculation Modes (Inclusive vs Exclusive) Alignment'},
      ],
    },
    {
      'version': '1.0.1',
      'release_date': '15 Aug 2026',
      'title': 'Cashier PIN Lock & Bluetooth Printer Reconnect',
      'items': [
        {'type': 'NEW', 'text': 'Cashier PIN Lock & Fast User Switching'},
        {'type': 'IMPROVED', 'text': 'Local Offline Caching of Inventory Catalog'},
        {'type': 'FIX', 'text': 'Bluetooth ESC/POS Receipt Printer Auto-reconnect'},
      ],
    },
    {
      'version': '1.0.0',
      'release_date': '01 Aug 2026',
      'title': 'Initial Product Release',
      'items': [
        {'type': 'NEW', 'text': 'Cross-Platform ZooM-POS Client for Android, Windows & Web'},
        {'type': 'NEW', 'text': 'Fast Cashier POS Terminal with Offline-First Local Storage'},
        {'type': 'NEW', 'text': 'Receipt Printing, Multi-Store Switcher & Real-time Synchronization'},
      ],
    },
  ];

  @override
  void initState() {
    super.initState();
    _fetchRemoteChangelogs();
  }

  Future<void> _fetchRemoteChangelogs() async {
    try {
      final uri = Uri.parse('${AppConfig.defaultBaseUrl}/api/app/changelog?slug=flutter');
      final resp = await http.get(uri).timeout(const Duration(seconds: 4));
      if (resp.statusCode == 200) {
        final decoded = jsonDecode(resp.body);
        if (decoded is Map && decoded['data'] is List && (decoded['data'] as List).isNotEmpty) {
          final List<Map<String, dynamic>> parsedList = [];
          for (final item in decoded['data']) {
            if (item is Map) {
              parsedList.add({
                'version': item['version']?.toString() ?? '1.0.0',
                'release_date': item['release_date']?.toString() ?? '',
                'title': item['title']?.toString() ?? '',
                'items': item['items'] is List ? item['items'] : [],
              });
            }
          }
          if (mounted && parsedList.isNotEmpty) {
            setState(() {
              _changelogs = parsedList;
              _isLoading = false;
            });
            return;
          }
        }
      }
    } catch (_) {
      // Fallback to static bundled changelogs if offline or network unreachable
    }

    if (mounted) {
      setState(() {
        _changelogs = _fallbackChangelogs;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final surfaceColor = isDark ? const Color(0xFF0F172A) : Colors.white;
    final cardBg = isDark ? const Color(0xFF1E293B) : const Color(0xFFF8FAFC);
    final borderColor = isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0);
    final primaryColor = theme.colorScheme.primary;

    final displayedList = _selectedFilter == 'latest' && _changelogs.isNotEmpty
        ? [_changelogs.first]
        : _changelogs;

    return Container(
      decoration: BoxDecoration(
        color: surfaceColor,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(
        children: [
          // Drag handle on mobile
          Padding(
            padding: const EdgeInsets.only(top: 10, bottom: 6),
            child: Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: isDark ? Colors.white24 : Colors.black12,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
          ),

          // Header
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 6, 16, 12),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0284C7).withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Text('📜', style: TextStyle(fontSize: 20)),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Text(
                            'Change-log',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w800,
                              color: isDark ? Colors.white : const Color(0xFF0F172A),
                              letterSpacing: -0.3,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFF0284C7).withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Text(
                              'v1.0.6',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                                color: Color(0xFF0284C7),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Explore software release notes & updates',
                        style: TextStyle(
                          fontSize: 12,
                          color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close, size: 20),
                  tooltip: 'Close',
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ],
            ),
          ),

          // Filter pills
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
            child: Row(
              children: [
                _buildFilterChip('all', 'All Releases (${_changelogs.length})', isDark),
                const SizedBox(width: 8),
                _buildFilterChip('latest', 'Latest v1.0.6', isDark),
                const Spacer(),
                if (_isLoading)
                  const SizedBox(
                    width: 14,
                    height: 14,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
              ],
            ),
          ),

          const SizedBox(height: 8),
          Divider(height: 1, color: borderColor),

          // Content
          Expanded(
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(18, 14, 18, 24),
              itemCount: displayedList.length,
              separatorBuilder: (_, __) => const SizedBox(height: 14),
              itemBuilder: (context, index) {
                final release = displayedList[index];
                final version = release['version']?.toString() ?? '1.0.0';
                final date = release['release_date']?.toString() ?? '';
                final title = release['title']?.toString() ?? '';
                final items = (release['items'] as List?)?.whereType<Map>().toList() ?? [];
                final isCurrent = index == 0;

                return Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: cardBg,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: isCurrent
                          ? primaryColor.withValues(alpha: isDark ? 0.45 : 0.3)
                          : borderColor,
                      width: isCurrent ? 1.4 : 1,
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Release version & badge row
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(
                              color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Text(
                              'Version $version - $date',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                                color: isDark ? Colors.white : const Color(0xFF1E293B),
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: const Color(0xFFE0F2FE),
                              borderRadius: BorderRadius.circular(6),
                              border: Border.all(color: const Color(0xFFBAE6FD)),
                            ),
                            child: const Text(
                              '📱 Flutter POS Client',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                                color: Color(0xFF0369A1),
                              ),
                            ),
                          ),
                          if (isCurrent) ...[
                            const SizedBox(width: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: const Color(0xFFDCFCE7),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: const Text(
                                'LATEST',
                                style: TextStyle(
                                  fontSize: 9,
                                  fontWeight: FontWeight.w900,
                                  color: Color(0xFF15803D),
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),

                      if (title.isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Text(
                          title,
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: isDark ? const Color(0xFFCBD5E1) : const Color(0xFF334155),
                          ),
                        ),
                      ],

                      const SizedBox(height: 10),

                      // Item list
                      for (final item in items) ...[
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 3),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildItemBadge(item['type']?.toString() ?? 'NEW'),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  item['text']?.toString() ?? '',
                                  style: TextStyle(
                                    fontSize: 12,
                                    height: 1.35,
                                    color: isDark
                                        ? const Color(0xFFE2E8F0)
                                        : const Color(0xFF1E293B),
                                    fontWeight: FontWeight.w500,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterChip(String key, String label, bool isDark) {
    final isSelected = _selectedFilter == key;
    return InkWell(
      borderRadius: BorderRadius.circular(10),
      onTap: () => setState(() => _selectedFilter = key),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: isSelected
              ? (isDark ? const Color(0xFF334155) : const Color(0xFF0F172A))
              : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.bold,
            color: isSelected
                ? Colors.white
                : (isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B)),
          ),
        ),
      ),
    );
  }

  Widget _buildItemBadge(String rawType) {
    final type = rawType.toUpperCase().trim();
    Color bg;
    Color text;

    if (type == 'NEW') {
      bg = const Color(0xFFDCFCE7);
      text = const Color(0xFF15803D);
    } else if (type == 'IMPROVED' || type == 'UPDATE') {
      bg = const Color(0xFFE0E7FF);
      text = const Color(0xFF4338CA);
    } else {
      bg = const Color(0xFFFFE4E6);
      text = const Color(0xFFBE123C);
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        type,
        style: TextStyle(
          fontSize: 9,
          fontWeight: FontWeight.w900,
          color: text,
          letterSpacing: 0.4,
        ),
      ),
    );
  }
}
