import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_client.dart';
import 'chat_conversation_screen.dart';

class InternalStaffChatListScreen extends StatefulWidget {
  const InternalStaffChatListScreen({super.key});

  @override
  State<InternalStaffChatListScreen> createState() => _InternalStaffChatListScreenState();
}

class _InternalStaffChatListScreenState extends State<InternalStaffChatListScreen> {
  List<dynamic> _staffList = [];
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _fetchStaffList();
  }

  Future<void> _fetchStaffList() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final client = context.read<ApiClient>();

    try {
      final res = await client.get('/chat/staff');
      if (mounted) {
        if (res['success'] == true) {
          setState(() {
            _staffList = List<dynamic>.from(res['staff'] ?? []);
            _isLoading = false;
          });
        } else {
          setState(() {
            _errorMessage = res['message'] ?? 'Failed to load staff directory';
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Network connection issue: $e';
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _openSupportChat() async {
    final client = context.read<ApiClient>();
    try {
      final res = await client.post('/chat/conversations', data: {
        'type': 'support',
      });
      if (mounted && res['success'] == true && res['conversation_id'] != null) {
        final convId = res['conversation_id'] is int
            ? res['conversation_id'] as int
            : int.tryParse(res['conversation_id'].toString()) ?? 1;

        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => ChatConversationScreen(
              conversationId: convId,
              title: 'Live Support Desk',
            ),
          ),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not connect to support desk')),
        );
      }
    }
  }

  Future<void> _openStaffChat(Map<String, dynamic> staff) async {
    final client = context.read<ApiClient>();
    try {
      final res = await client.post('/chat/conversations', data: {
        'type': 'direct',
        'user_id': staff['id'],
      });

      if (mounted && res['success'] == true && res['conversation_id'] != null) {
        final convId = res['conversation_id'] is int
            ? res['conversation_id'] as int
            : int.tryParse(res['conversation_id'].toString()) ?? 1;

        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => ChatConversationScreen(
              conversationId: convId,
              title: staff['name'] ?? 'Staff Chat',
            ),
          ),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not open conversation')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        title: const Text('Internal Staff Chat & Support', style: TextStyle(color: Colors.white, fontSize: 16)),
        backgroundColor: const Color(0xFF1E293B),
        iconTheme: const IconThemeData(color: Colors.white),
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Colors.white),
            onPressed: _fetchStaffList,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _fetchStaffList,
        color: const Color(0xFF10B981),
        child: ListView(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
          children: [
            // 1. Live Support Desk Card
            InkWell(
              onTap: _openSupportChat,
              borderRadius: BorderRadius.circular(16),
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFF0284C7), Color(0xFF0369A1)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF0284C7).withValues(alpha: 0.25),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: const BoxDecoration(
                        color: Colors.white24,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.support_agent_rounded, color: Colors.white, size: 28),
                    ),
                    const SizedBox(width: 14),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Text(
                                'Help & Support Live Desk',
                                style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.bold),
                              ),
                              SizedBox(width: 8),
                              Icon(Icons.verified, color: Colors.white, size: 16),
                            ],
                          ),
                          SizedBox(height: 2),
                          Text(
                            'Chat instantly with central help desk',
                            style: TextStyle(color: Colors.white70, fontSize: 12),
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.arrow_forward_ios_rounded, color: Colors.white70, size: 14),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 20),

            // Header for Staff Directory
            Row(
              children: [
                const Text(
                  'Store Staff Members',
                  style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold),
                ),
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: const Color(0xFF1E293B),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    '${_staffList.length}',
                    style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 12),

            if (_isLoading)
              const Center(
                child: Padding(
                  padding: EdgeInsets.symmetric(vertical: 40),
                  child: CircularProgressIndicator(color: Color(0xFF10B981)),
                ),
              )
            else if (_errorMessage != null)
              Center(
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 30),
                  child: Column(
                    children: [
                      Text(_errorMessage!, style: const TextStyle(color: Color(0xFFF87171), fontSize: 12)),
                      const SizedBox(height: 8),
                      ElevatedButton(
                        onPressed: _fetchStaffList,
                        style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF1E293B)),
                        child: const Text('Try Again', style: TextStyle(color: Colors.white, fontSize: 12)),
                      ),
                    ],
                  ),
                ),
              )
            else if (_staffList.isEmpty)
              const Center(
                child: Padding(
                  padding: EdgeInsets.symmetric(vertical: 40),
                  child: Text(
                    'No other staff members found in this store.',
                    style: TextStyle(color: Color(0xFF64748B), fontSize: 13),
                  ),
                ),
              )
            else
              ..._staffList.map((staff) {
                final isOnline = staff['is_online'] == true;
                final statusLabel = staff['status_label']?.toString() ?? (isOnline ? 'Online' : 'Offline');

                return Container(
                  margin: const EdgeInsets.only(bottom: 10),
                  decoration: BoxDecoration(
                    color: const Color(0xFF1E293B),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: const Color(0xFF334155).withValues(alpha: 0.5)),
                  ),
                  child: ListTile(
                    onTap: () => _openStaffChat(Map<String, dynamic>.from(staff)),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                    leading: Stack(
                      children: [
                        CircleAvatar(
                          radius: 20,
                          backgroundColor: const Color(0xFF0F172A),
                          backgroundImage: staff['avatar'] != null ? NetworkImage(staff['avatar']) : null,
                          child: staff['avatar'] == null
                              ? Text(
                                  (staff['name'] ?? 'U').toString().substring(0, 1).toUpperCase(),
                                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                                )
                              : null,
                        ),
                        // Online presence indicator (green dot)
                        Positioned(
                          right: 0,
                          bottom: 0,
                          child: Container(
                            width: 11,
                            height: 11,
                            decoration: BoxDecoration(
                              color: isOnline ? const Color(0xFF10B981) : const Color(0xFF64748B),
                              shape: BoxShape.circle,
                              border: Border.all(color: const Color(0xFF1E293B), width: 2),
                            ),
                          ),
                        ),
                      ],
                    ),
                    title: Text(
                      staff['name'] ?? 'Staff Member',
                      style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600),
                    ),
                    subtitle: Text(
                      staff['role'] ?? 'Staff',
                      style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                    ),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: isOnline
                                ? const Color(0xFF10B981).withValues(alpha: 0.15)
                                : const Color(0xFF334155).withValues(alpha: 0.4),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            statusLabel,
                            style: TextStyle(
                              color: isOnline ? const Color(0xFF10B981) : const Color(0xFF94A3B8),
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        const Icon(Icons.chat_bubble_outline_rounded, color: Color(0xFF38BDF8), size: 18),
                      ],
                    ),
                  ),
                );
              }),
          ],
        ),
      ),
    );
  }
}
