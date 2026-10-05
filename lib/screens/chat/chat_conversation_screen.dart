import 'dart:async';
import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/api_client.dart';
import '../../features/auth/auth_provider.dart';

class ChatConversationScreen extends StatefulWidget {
  final int conversationId;
  final String title;

  const ChatConversationScreen({
    super.key,
    required this.conversationId,
    required this.title,
  });

  @override
  State<ChatConversationScreen> createState() => _ChatConversationScreenState();
}

class _ChatConversationScreenState extends State<ChatConversationScreen> {
  final TextEditingController _msgController = TextEditingController();
  final AudioPlayer _audioPlayer = AudioPlayer();
  List<dynamic> _messages = [];
  Map<String, dynamic>? _promotion;
  List<String> _aiSuggestions = [];
  bool _isLoading = true;
  Timer? _pollTimer;
  String? _currentUserId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _currentUserId = context.read<AuthProvider>().user?.id;
      _loadMessages();
      _startPolling();
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _msgController.dispose();
    _audioPlayer.dispose();
    super.dispose();
  }

  void _startPolling() {
    _pollTimer = Timer.periodic(const Duration(seconds: 4), (_) {
      _loadMessages(isPoll: true);
    });
  }

  // Play incoming alert chime
  void _playAlertChime() async {
    try {
      await _audioPlayer.play(AssetSource('sounds/notification_alert.mp3'));
    } catch (_) {}
  }

  Future<void> _openUrl(String? urlStr) async {
    if (urlStr == null || urlStr.isEmpty) return;
    try {
      final uri = Uri.parse(urlStr);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    } catch (_) {}
  }

  Future<void> _loadMessages({bool isPoll = false}) async {
    if (!mounted) return;
    final client = context.read<ApiClient>();

    try {
      final res = await client.get('/api/chat/conversations/${widget.conversationId}/messages');
      if (!mounted) return;

      if (res['success'] == true) {
        final newMsgs = List<dynamic>.from(res['messages'] ?? []);
        final newPromo = res['active_promotion'] as Map<String, dynamic>?;
        final newAi = List<String>.from(res['ai_suggestions'] ?? []);

        if (isPoll && newMsgs.length > _messages.length) {
          final lastMsg = newMsgs.isNotEmpty ? newMsgs.last : null;
          final senderId = lastMsg?['sender_id']?.toString();
          if (senderId != null && senderId != _currentUserId) {
            _playAlertChime();
            _showIncomingBannerAlert(lastMsg?['message']?.toString() ?? 'New attachment received');
          }
        }

        setState(() {
          // Store reversed for reverse: true ListView
          _messages = newMsgs.reversed.toList();
          if (!isPoll || _promotion == null) {
            _promotion = newPromo;
          }
          _aiSuggestions = newAi;
          _isLoading = false;
        });
      }
    } catch (_) {
      if (mounted && !isPoll) {
        setState(() => _isLoading = false);
      }
    }
  }

  void _showIncomingBannerAlert(String text) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: const Color(0xFF1E293B),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: Color(0xFF10B981), width: 1),
        ),
        content: Row(
          children: [
            const Icon(Icons.chat_bubble_rounded, color: Color(0xFF10B981), size: 18),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                '${widget.title}: $text',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.white, fontSize: 12),
              ),
            ),
          ],
        ),
        duration: const Duration(seconds: 3),
      ),
    );
  }

  Future<void> _sendMessage() async {
    final text = _msgController.text.trim();
    if (text.isEmpty) return;

    _msgController.clear();
    setState(() => _aiSuggestions = []);

    final client = context.read<ApiClient>();

    try {
      final res = await client.post('/api/chat/messages', data: {
        'conversation_id': widget.conversationId,
        'message': text,
      });

      if (res['success'] == true && res['message'] != null) {
        setState(() {
          _messages.insert(0, res['message']);
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to send message')),
        );
      }
    }
  }

  // Build Super Admin Promotional Banner Card inside the chat
  Widget _buildPromotionalCard() {
    if (_promotion == null) return const SizedBox.shrink();

    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF1E293B), Color(0xFF0F172A)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFF59E0B).withValues(alpha: 0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.campaign_rounded, color: Color(0xFFF59E0B), size: 20),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  _promotion!['title'] ?? 'Official Announcement',
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                ),
              ),
              IconButton(
                icon: const Icon(Icons.close, color: Color(0xFF64748B), size: 16),
                onPressed: () => setState(() => _promotion = null),
                constraints: const BoxConstraints(),
                padding: EdgeInsets.zero,
              ),
            ],
          ),
          if (_promotion!['banner_image_url'] != null) ...[
            const SizedBox(height: 8),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: Image.network(
                _promotion!['banner_image_url'],
                height: 110,
                width: double.infinity,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => const SizedBox.shrink(),
              ),
            ),
          ],
          const SizedBox(height: 6),
          Text(
            _promotion!['message'] ?? '',
            style: const TextStyle(color: Color(0xFFCBD5E1), fontSize: 12),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 6,
            children: [
              if (_promotion!['pdf_url'] != null)
                ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFEF4444),
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  ),
                  icon: const Icon(Icons.picture_as_pdf, size: 14, color: Colors.white),
                  label: const Text('Download PDF Brochure', style: TextStyle(fontSize: 11, color: Colors.white)),
                  onPressed: () => _openUrl(_promotion!['pdf_url']),
                ),
              if (_promotion!['cta_url'] != null)
                ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF0284C7),
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  ),
                  icon: const Icon(Icons.open_in_new, size: 14, color: Colors.white),
                  label: Text(_promotion!['cta_label'] ?? 'Learn More', style: const TextStyle(fontSize: 11, color: Colors.white)),
                  onPressed: () => _openUrl(_promotion!['cta_url']),
                ),
            ],
          ),
        ],
      ),
    );
  }

  // Build AI Smart Reply Quick Chips
  Widget _buildAiSuggestionChips() {
    if (_aiSuggestions.isEmpty) return const SizedBox.shrink();

    return Container(
      height: 38,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: _aiSuggestions.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, idx) {
          final replyText = _aiSuggestions[idx];
          return ActionChip(
            backgroundColor: const Color(0xFF1E293B),
            side: const BorderSide(color: Color(0xFF38BDF8), width: 0.8),
            avatar: const Icon(Icons.auto_awesome, color: Color(0xFF38BDF8), size: 14),
            label: Text(replyText, style: const TextStyle(color: Colors.white, fontSize: 11)),
            onPressed: () {
              _msgController.text = replyText;
            },
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        title: Text(widget.title, style: const TextStyle(fontSize: 16, color: Colors.white)),
        backgroundColor: const Color(0xFF1E293B),
        iconTheme: const IconThemeData(color: Colors.white),
        elevation: 0,
      ),
      body: Column(
        children: [
          _buildPromotionalCard(),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: Color(0xFF10B981)))
                : _messages.isEmpty
                    ? const Center(
                        child: Text(
                          'No messages yet. Send a greeting!',
                          style: TextStyle(color: Color(0xFF64748B), fontSize: 13),
                        ),
                      )
                    : ListView.builder(
                        reverse: true,
                        itemCount: _messages.length,
                        itemBuilder: (context, index) {
                          final msg = _messages[index];
                          final senderId = msg['sender_id']?.toString();
                          final isMe = senderId != null && senderId == _currentUserId;

                          return Align(
                            alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
                            child: Container(
                              margin: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                              padding: const EdgeInsets.all(12),
                              constraints: BoxConstraints(
                                maxWidth: MediaQuery.of(context).size.width * 0.75,
                              ),
                              decoration: BoxDecoration(
                                color: isMe ? const Color(0xFF10B981) : const Color(0xFF1E293B),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  if (!isMe && msg['sender'] != null)
                                    Padding(
                                      padding: const EdgeInsets.only(bottom: 3),
                                      child: Text(
                                        msg['sender']['name'] ?? '',
                                        style: const TextStyle(
                                          color: Color(0xFF38BDF8),
                                          fontSize: 10,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                    ),
                                  if (msg['attachment_url'] != null) ...[
                                    if (msg['attachment_type'] == 'image')
                                      ClipRRect(
                                        borderRadius: BorderRadius.circular(8),
                                        child: Image.network(
                                          msg['attachment_url'],
                                          height: 180,
                                          fit: BoxFit.cover,
                                        ),
                                      )
                                    else
                                      InkWell(
                                        onTap: () => _openUrl(msg['attachment_url']),
                                        child: Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            const Icon(Icons.attach_file, color: Colors.white, size: 16),
                                            const SizedBox(width: 4),
                                            Flexible(
                                              child: Text(
                                                msg['attachment_name'] ?? 'Attachment',
                                                style: const TextStyle(
                                                  color: Colors.white,
                                                  decoration: TextDecoration.underline,
                                                  fontSize: 12,
                                                ),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    const SizedBox(height: 6),
                                  ],
                                  if ((msg['message'] ?? '').isNotEmpty)
                                    Text(
                                      msg['message'] ?? '',
                                      style: const TextStyle(color: Colors.white, fontSize: 13),
                                    ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
          ),
          _buildAiSuggestionChips(),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _msgController,
                    style: const TextStyle(color: Colors.white, fontSize: 13),
                    decoration: InputDecoration(
                      hintText: 'Type message...',
                      hintStyle: const TextStyle(color: Color(0xFF64748B)),
                      filled: true,
                      fillColor: const Color(0xFF1E293B),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(24),
                        borderSide: BorderSide.none,
                      ),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                    ),
                    onSubmitted: (_) => _sendMessage(),
                  ),
                ),
                const SizedBox(width: 6),
                IconButton(
                  icon: const Icon(Icons.send_rounded, color: Color(0xFF10B981)),
                  onPressed: _sendMessage,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
