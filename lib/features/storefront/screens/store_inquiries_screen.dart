import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/api/api_client.dart';

class StoreInquiriesScreen extends StatefulWidget {
  const StoreInquiriesScreen({super.key});

  @override
  State<StoreInquiriesScreen> createState() => _StoreInquiriesScreenState();
}

class _StoreInquiriesScreenState extends State<StoreInquiriesScreen> {
  bool _isLoading = false;
  List<Map<String, dynamic>> _inquiries = [];
  int _unreadCount = 0;
  int _totalCount = 0;
  String _selectedStatus = 'all';
  final _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadInquiries();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadInquiries() async {
    if (!mounted) return;
    setState(() => _isLoading = true);

    try {
      final client = context.read<ApiClient>();
      final queryParams = <String, String>{};
      if (_selectedStatus != 'all') {
        queryParams['status'] = _selectedStatus;
      }
      final search = _searchController.text.trim();
      if (search.isNotEmpty) {
        queryParams['search'] = search;
      }

      final res = await client.get(
        '/tenant/storefront/inquiries',
        query: queryParams,
      );

      if (!mounted) return;

      if (res is Map && res['success'] == true) {
        final data = res['data'] as Map<String, dynamic>? ?? {};
        final rawList = data['inquiries'] as List? ?? [];
        setState(() {
          _inquiries = rawList.map((e) => Map<String, dynamic>.from(e as Map)).toList();
          _unreadCount = (data['unread_count'] as num?)?.toInt() ?? 0;
          _totalCount = (data['total_count'] as num?)?.toInt() ?? _inquiries.length;
          _isLoading = false;
        });
      } else {
        setState(() => _isLoading = false);
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to load inquiries: $e')),
      );
    }
  }

  Future<void> _updateStatus(String id, String newStatus) async {
    try {
      final client = context.read<ApiClient>();
      final res = await client.put(
        '/tenant/storefront/inquiries/$id/status',
        data: {'status': newStatus},
      );

      if (!mounted) return;

      if (res is Map && res['success'] == true) {
        setState(() {
          final idx = _inquiries.indexWhere((item) => item['id']?.toString() == id);
          if (idx != -1) {
            _inquiries[idx]['status'] = newStatus;
          }
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Inquiry marked as $newStatus')),
        );
        _loadInquiries();
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to update status: $e')),
      );
    }
  }

  Future<void> _deleteInquiry(String id) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Inquiry'),
        content: const Text('Are you sure you want to permanently delete this customer inquiry?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    try {
      final client = context.read<ApiClient>();
      await client.delete('/tenant/storefront/inquiries/$id');

      if (!mounted) return;

      setState(() {
        _inquiries.removeWhere((item) => item['id']?.toString() == id);
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Inquiry deleted successfully')),
      );
      _loadInquiries();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to delete inquiry: $e')),
      );
    }
  }

  void _callPhone(String? phone) {
    if (phone == null || phone.trim().isEmpty) return;
    launchUrl(Uri.parse('tel:${phone.trim()}'));
  }

  void _chatWhatsApp(String? phone) {
    if (phone == null || phone.trim().isEmpty) return;
    final digits = phone.replaceAll(RegExp(r'[^0-9]'), '');
    if (digits.isNotEmpty) {
      launchUrl(Uri.parse('https://wa.me/$digits'), mode: LaunchMode.externalApplication);
    }
  }

  void _sendEmail(String? email, String? subject) {
    if (email == null || email.trim().isEmpty) return;
    final cleanSubject = Uri.encodeComponent(subject != null ? 'Re: $subject' : 'Store Inquiry');
    launchUrl(Uri.parse('mailto:${email.trim()}?subject=$cleanSubject'));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            const Text('Store Inquiries'),
            if (_unreadCount > 0) ...[
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.red,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  '$_unreadCount new',
                  style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
            onPressed: _loadInquiries,
          ),
        ],
      ),
      body: Column(
        children: [
          // Search & Filter header
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Search by customer name, phone, or message...',
                prefixIcon: const Icon(Icons.search, size: 20),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _searchController.clear();
                          _loadInquiries();
                        },
                      )
                    : null,
                isDense: true,
                contentPadding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
              ),
              onSubmitted: (_) => _loadInquiries(),
            ),
          ),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Row(
              children: [
                _filterChip('all', 'All ($_totalCount)'),
                const SizedBox(width: 8),
                _filterChip('unread', 'Unread ($_unreadCount)'),
                const SizedBox(width: 8),
                _filterChip('contacted', 'Contacted'),
                const SizedBox(width: 8),
                _filterChip('closed', 'Closed'),
              ],
            ),
          ),
          const Divider(height: 12),

          // Inquiry Cards List
          Expanded(
            child: _isLoading && _inquiries.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : RefreshIndicator(
                    onRefresh: _loadInquiries,
                    child: _inquiries.isEmpty
                        ? Center(
                            child: SingleChildScrollView(
                              physics: const AlwaysScrollableScrollPhysics(),
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.inbox_outlined, size: 56, color: theme.colorScheme.onSurface.withValues(alpha: 0.3)),
                                  const SizedBox(height: 12),
                                  Text(
                                    _selectedStatus == 'all'
                                        ? 'No customer inquiries yet.'
                                        : 'No ${_selectedStatus.toUpperCase()} inquiries.',
                                    style: TextStyle(fontSize: 16, color: theme.colorScheme.onSurface.withValues(alpha: 0.7)),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    'When visitors ask questions on your storefront, they appear here.',
                                    style: TextStyle(fontSize: 13, color: theme.colorScheme.onSurface.withValues(alpha: 0.5)),
                                  ),
                                ],
                              ),
                            ),
                          )
                        : ListView.builder(
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            itemCount: _inquiries.length,
                            itemBuilder: (context, index) {
                              final item = _inquiries[index];
                              return _buildInquiryCard(item);
                            },
                          ),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _filterChip(String status, String label) {
    final isSelected = _selectedStatus == status;
    return ChoiceChip(
      label: Text(label),
      selected: isSelected,
      onSelected: (sel) {
        if (sel) {
          setState(() => _selectedStatus = status);
          _loadInquiries();
        }
      },
    );
  }

  Widget _buildInquiryCard(Map<String, dynamic> item) {
    final id = item['id']?.toString() ?? '';
    final name = item['name']?.toString() ?? 'Customer';
    final email = item['email']?.toString();
    final phone = item['phone']?.toString();
    final subject = item['subject']?.toString() ?? 'Inquiry';
    final message = item['message']?.toString() ?? '';
    final status = (item['status']?.toString() ?? 'unread').toLowerCase();
    final timeHuman = item['created_at_human']?.toString() ?? item['created_at']?.toString() ?? '';

    Color statusBgColor;
    Color statusTextColor;
    String statusLabel;

    switch (status) {
      case 'contacted':
        statusBgColor = Colors.amber.withValues(alpha: 0.15);
        statusTextColor = Colors.amber.shade900;
        statusLabel = 'CONTACTED';
        break;
      case 'closed':
        statusBgColor = Colors.green.withValues(alpha: 0.15);
        statusTextColor = Colors.green.shade800;
        statusLabel = 'CLOSED';
        break;
      case 'unread':
      default:
        statusBgColor = Colors.red.withValues(alpha: 0.15);
        statusTextColor = Colors.red.shade700;
        statusLabel = 'UNREAD';
        break;
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(
          color: status == 'unread'
              ? Colors.red.withValues(alpha: 0.3)
              : Theme.of(context).dividerColor.withValues(alpha: 0.15),
          width: status == 'unread' ? 1.5 : 1,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Top row: Name, Badge, Date
            Row(
              children: [
                CircleAvatar(
                  radius: 16,
                  backgroundColor: Theme.of(context).colorScheme.primaryContainer,
                  child: Text(
                    name.isNotEmpty ? name[0].toUpperCase() : 'C',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: Theme.of(context).colorScheme.onPrimaryContainer,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        name,
                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
                      ),
                      if (timeHuman.isNotEmpty)
                        Text(
                          timeHuman,
                          style: TextStyle(fontSize: 12, color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.5)),
                        ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: statusBgColor,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    statusLabel,
                    style: TextStyle(color: statusTextColor, fontSize: 11, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),

            // Subject & Message
            Text(
              subject,
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            const SizedBox(height: 6),
            Text(
              message,
              style: TextStyle(fontSize: 13, height: 1.4, color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.8)),
            ),
            const SizedBox(height: 12),

            // Contact Info
            if ((phone != null && phone.isNotEmpty) || (email != null && email.isNotEmpty))
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.4),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  children: [
                    if (phone != null && phone.isNotEmpty) ...[
                      const Icon(Icons.phone_outlined, size: 14),
                      const SizedBox(width: 4),
                      Text(phone, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500)),
                      const SizedBox(width: 16),
                    ],
                    if (email != null && email.isNotEmpty) ...[
                      const Icon(Icons.email_outlined, size: 14),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          email,
                          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            const Divider(height: 20),

            // Action Buttons
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    if (phone != null && phone.isNotEmpty) ...[
                      IconButton.filledTonal(
                        icon: const Icon(Icons.call, size: 18),
                        tooltip: 'Call Customer',
                        onPressed: () => _callPhone(phone),
                      ),
                      const SizedBox(width: 8),
                      IconButton.filledTonal(
                        icon: const Icon(Icons.chat, size: 18),
                        tooltip: 'WhatsApp Chat',
                        style: IconButton.styleFrom(foregroundColor: const Color(0xFF25D366)),
                        onPressed: () => _chatWhatsApp(phone),
                      ),
                      const SizedBox(width: 8),
                    ],
                    if (email != null && email.isNotEmpty)
                      IconButton.filledTonal(
                        icon: const Icon(Icons.email_outlined, size: 18),
                        tooltip: 'Send Email',
                        onPressed: () => _sendEmail(email, subject),
                      ),
                  ],
                ),
                Row(
                  children: [
                    PopupMenuButton<String>(
                      tooltip: 'Change Status',
                      icon: const Icon(Icons.more_vert),
                      onSelected: (newStatus) => _updateStatus(id, newStatus),
                      itemBuilder: (ctx) => [
                        if (status != 'unread')
                          const PopupMenuItem(value: 'unread', child: Text('Mark as Unread')),
                        if (status != 'contacted')
                          const PopupMenuItem(value: 'contacted', child: Text('Mark as Contacted')),
                        if (status != 'closed')
                          const PopupMenuItem(value: 'closed', child: Text('Mark as Closed')),
                      ],
                    ),
                    IconButton(
                      icon: const Icon(Icons.delete_outline, color: Colors.red, size: 20),
                      tooltip: 'Delete Inquiry',
                      onPressed: () => _deleteInquiry(id),
                    ),
                  ],
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
