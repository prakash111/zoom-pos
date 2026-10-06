import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api/api_client.dart';

class NotificationsBottomSheet extends StatefulWidget {
  const NotificationsBottomSheet({super.key});

  static Future<void> show(BuildContext context) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => const NotificationsBottomSheet(),
    );
  }

  @override
  State<NotificationsBottomSheet> createState() =>
      _NotificationsBottomSheetState();
}

class _NotificationsBottomSheetState extends State<NotificationsBottomSheet> {
  bool _isLoading = true;
  List<Map<String, dynamic>> _items = [];
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _fetchNotifications();
  }

  Future<void> _fetchNotifications() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final client = context.read<ApiClient>();
      final res = await client.get('/api/v1/pos/notifications');
      final rawItems = res['items'] as List<dynamic>? ?? const [];
      if (!mounted) return;
      setState(() {
        _items = rawItems
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList();
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = e.toString();
      });
    }
  }

  Future<void> _dismissItem(dynamic id) async {
    final idStr = id?.toString() ?? '';
    if (idStr.isEmpty) return;

    // Remove immediately from memory
    setState(() {
      _items.removeWhere((item) => item['id']?.toString() == idStr);
    });

    try {
      final client = context.read<ApiClient>();
      await client.post('/api/v1/pos/notifications/dismiss', data: {'id': idStr});
    } catch (_) {}
  }

  Future<void> _clearAll() async {
    setState(() {
      _items.clear();
    });

    try {
      final client = context.read<ApiClient>();
      await client.post('/api/v1/pos/notifications/clear-all');
    } catch (_) {}
  }

  Future<void> _openUrl(String? url) async {
    if (url == null || url.isEmpty) return;
    try {
      final uri = Uri.parse(url);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    } catch (_) {}
  }

  Color _parseColor(dynamic hex, {Color fallback = const Color(0xFF38BDF8)}) {
    if (hex == null) return fallback;
    final str = hex.toString().replaceAll('#', '').trim();
    if (str.length == 6) {
      final val = int.tryParse('0xFF$str');
      if (val != null) return Color(val);
    } else if (str.length == 8) {
      final val = int.tryParse('0x$str');
      if (val != null) return Color(val);
    }
    return fallback;
  }

  Widget _buildNotificationItem(BuildContext context, Map<String, dynamic> item) {
    final isAnnouncement = item['type'] == 'announcement' ||
        item['type'] == 'staff_notice' ||
        item['type'] == 'promotional';
    final bannerUrl = item['banner_image_url']?.toString();
    final pdfUrl = item['pdf_url']?.toString();
    final ctaLabel = item['cta_label']?.toString();
    final ctaUrl = item['cta_url']?.toString();
    final badgeColor = _parseColor(
      item['badge_color'],
      fallback: isAnnouncement ? const Color(0xFF38BDF8) : const Color(0xFF10B981),
    );

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: isAnnouncement
              ? badgeColor.withValues(alpha: 0.4)
              : const Color(0xFF334155).withValues(alpha: 0.5),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top Header Row with Badge & Dismiss
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: badgeColor.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  item['badge'] ?? (isAnnouncement ? 'Announcement' : 'Notice'),
                  style: TextStyle(
                    color: badgeColor,
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              IconButton(
                icon: const Icon(Icons.close, color: Color(0xFF64748B), size: 16),
                onPressed: () => _dismissItem(item['id']),
                constraints: const BoxConstraints(),
                padding: EdgeInsets.zero,
              ),
            ],
          ),

          const SizedBox(height: 8),

          // Title & Message
          Text(
            item['title'] ?? '',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 14,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            item['message'] ?? '',
            style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
          ),

          // Optional Banner for Super Admin Announcements
          if (bannerUrl != null && bannerUrl.isNotEmpty) ...[
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: Image.network(
                bannerUrl,
                height: 120,
                width: double.infinity,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => const SizedBox.shrink(),
              ),
            ),
          ],

          // Optional PDF Brochure Link
          if (pdfUrl != null && pdfUrl.isNotEmpty) ...[
            const SizedBox(height: 10),
            ElevatedButton.icon(
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFEF4444),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              icon: const Icon(Icons.picture_as_pdf, size: 14, color: Colors.white),
              label: const Text(
                'Download PDF Brochure',
                style: TextStyle(color: Colors.white, fontSize: 11),
              ),
              onPressed: () => _openUrl(pdfUrl),
            ),
          ],

          // Optional CTA Button
          if (ctaLabel != null && ctaLabel.isNotEmpty && ctaUrl != null && ctaUrl.isNotEmpty) ...[
            const SizedBox(height: 10),
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0284C7),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              onPressed: () => _openUrl(ctaUrl),
              child: Text(
                ctaLabel,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.of(context).size.height * 0.85,
      ),
      decoration: const BoxDecoration(
        color: Color(0xFF0F172A),
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      padding: EdgeInsets.fromLTRB(
        16,
        16,
        16,
        MediaQuery.of(context).viewInsets.bottom + 16,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Drag handle
          Center(
            child: Container(
              width: 36,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFF334155),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          const SizedBox(height: 14),

          // Header Row
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Notifications & Activity Alerts (${_items.length})',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
              if (_items.isNotEmpty)
                TextButton.icon(
                  onPressed: _clearAll,
                  icon: const Icon(Icons.delete_sweep, size: 16, color: Color(0xFFEF4444)),
                  label: const Text(
                    'Clear All',
                    style: TextStyle(color: Color(0xFFEF4444), fontSize: 12),
                  ),
                ),
            ],
          ),
          const Divider(color: Color(0xFF1E293B), height: 20),

          // Content List
          Flexible(
            child: _isLoading
                ? const Center(
                    child: Padding(
                      padding: EdgeInsets.all(32),
                      child: CircularProgressIndicator(color: Color(0xFF10B981)),
                    ),
                  )
                : _errorMessage != null
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Text(
                            _errorMessage!,
                            style: const TextStyle(color: Colors.redAccent, fontSize: 13),
                          ),
                        ),
                      )
                    : _items.isEmpty
                        ? const Center(
                            child: Padding(
                              padding: EdgeInsets.all(36),
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.notifications_none, size: 48, color: Color(0xFF475569)),
                                  SizedBox(height: 12),
                                  Text(
                                    'No active alerts or announcements.',
                                    style: TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
                                  ),
                                ],
                              ),
                            ),
                          )
                        : ListView.builder(
                            shrinkWrap: true,
                            itemCount: _items.length,
                            itemBuilder: (ctx, idx) =>
                                _buildNotificationItem(ctx, _items[idx]),
                          ),
          ),
        ],
      ),
    );
  }
}
