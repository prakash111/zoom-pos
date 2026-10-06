import 'dart:async';
import 'dart:typed_data';
import 'package:audioplayers/audioplayers.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
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
  Set<int> _dismissedPromoIds = {};

  // Attachment state
  List<int>? _selectedFileBytes;
  String? _selectedFileName;
  String? _selectedFileType; // 'image' or 'document'
  int? _selectedFileSize;
  bool _isSending = false;
  bool _isAutoCorrecting = false;

  // Emoji picker state
  bool _showEmojiPicker = false;
  String _activeEmojiTab = 'quick';

  // Emoji categories matching Laravel
  static const Map<String, Map<String, dynamic>> _emojiCategories = {
    'quick': {
      'name': 'Quick',
      'icon': '⚡',
      'emojis': ['👍', '👎', '👏', '🙌', '🤝', '❤️', '🔥', '🎉', '✅', '❌', '💯', '🚀'],
    },
    'smileys': {
      'name': 'Smileys',
      'icon': '😊',
      'emojis': ['😊', '😂', '😃', '😄', '😁', '😆', '😎', '🤔', '😅', '😍', '🥳', '😉', '😇', '🤫', '😋', '😜', '🤤', '🤠', '🤩', '🥺', '😢', '😭', '👀', '🙏'],
    },
    'business': {
      'name': 'Business',
      'icon': '🏪',
      'emojis': ['📦', '💰', '🧾', '🏷️', '🛒', '💳', '🏪', '🛍️', '🚚', '📋', '⚡', '🔔', '📍', '📱', '💻', '💵', '🪙', '📈', '📊', '⏰', '⏳', '💡', '🎯', '📢'],
    },
    'symbols': {
      'name': 'Symbols',
      'icon': '⭐',
      'emojis': ['⭐', '🌟', '✨', '💬', '📞', '🔒', '🔑', '📌', '🎁', '☕', '🍽️', '🥇', '🏆', '⚠️', '🚨', '❓', '❗', '🆗', '💪', '✌️', '👋', '🍕', '🛡️'],
    },
  };

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      _currentUserId = context.read<AuthProvider>().user?.id;
      await _loadDismissedPromotions();
      _loadMessages();
      _startPolling();
    });
  }

  Future<void> _loadDismissedPromotions() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final list = prefs.getStringList('dismissed_promotions') ?? [];
      _dismissedPromoIds = list.map((e) => int.tryParse(e)).whereType<int>().toSet();
    } catch (_) {}
  }

  Future<void> _dismissPromotion(int? promoId) async {
    if (promoId == null) {
      setState(() => _promotion = null);
      return;
    }

    setState(() {
      _dismissedPromoIds.add(promoId);
      _promotion = null;
    });

    try {
      final prefs = await SharedPreferences.getInstance();
      final list = prefs.getStringList('dismissed_promotions') ?? [];
      final idStr = promoId.toString();
      if (!list.contains(idStr)) {
        list.add(idStr);
        await prefs.setStringList('dismissed_promotions', list);
      }
    } catch (_) {}

    try {
      final client = context.read<ApiClient>();
      await client.post('/chat/promotions/$promoId/dismiss');
    } catch (_) {}
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
      final res = await client.get('/chat/conversations/${widget.conversationId}/messages');
      if (!mounted) return;

      if (res['success'] == true) {
        final newMsgs = List<dynamic>.from(res['messages'] ?? []);
        Map<String, dynamic>? newPromo = res['active_promotion'] as Map<String, dynamic>?;
        final newAi = List<String>.from(res['ai_suggestions'] ?? []);

        final promoId = newPromo?['id'] is int
            ? newPromo!['id'] as int
            : int.tryParse(newPromo?['id']?.toString() ?? '');
        if (promoId != null && _dismissedPromoIds.contains(promoId)) {
          newPromo = null;
        }

        if (isPoll && newMsgs.length > _messages.length) {
          final lastMsg = newMsgs.isNotEmpty ? newMsgs.last : null;
          final senderId = lastMsg?['sender_id']?.toString();
          if (senderId != null && senderId != _currentUserId) {
            _playAlertChime();
            _showIncomingBannerAlert(lastMsg?['message']?.toString() ?? 'New message received');
          }
        }

        setState(() {
          // Store reversed for reverse: true ListView
          _messages = newMsgs.reversed.toList();
          _promotion = newPromo;
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

  // --------------------------------------------------------------------------
  // Attachment Handlers
  // --------------------------------------------------------------------------
  void _clearAttachment() {
    setState(() {
      _selectedFileBytes = null;
      _selectedFileName = null;
      _selectedFileType = null;
      _selectedFileSize = null;
    });
  }

  Future<void> _showAttachmentPicker() async {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final choice = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: isDark ? const Color(0xFF1E293B) : Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetCtx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                margin: const EdgeInsets.only(bottom: 12),
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF475569) : const Color(0xFFCBD5E1),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              ListTile(
                leading: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFF10B981).withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.photo_camera_rounded, color: Color(0xFF10B981), size: 20),
                ),
                title: Text(
                  'Take Photo',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: isDark ? Colors.white : const Color(0xFF0F172A),
                  ),
                ),
                subtitle: const Text('Capture photo with camera', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
                onTap: () => Navigator.pop(sheetCtx, 'camera'),
              ),
              ListTile(
                leading: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0284C7).withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.photo_library_rounded, color: Color(0xFF0284C7), size: 20),
                ),
                title: Text(
                  'Upload Photo from Gallery',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: isDark ? Colors.white : const Color(0xFF0F172A),
                  ),
                ),
                subtitle: const Text('Select JPG, PNG, WEBP', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
                onTap: () => Navigator.pop(sheetCtx, 'gallery'),
              ),
              ListTile(
                leading: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFF8B5CF6).withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.description_rounded, color: Color(0xFF8B5CF6), size: 20),
                ),
                title: Text(
                  'Select Document / PDF',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: isDark ? Colors.white : const Color(0xFF0F172A),
                  ),
                ),
                subtitle: const Text('PDF, Word document, Excel sheet, Text file', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
                onTap: () => Navigator.pop(sheetCtx, 'document'),
              ),
            ],
          ),
        ),
      ),
    );

    if (choice == null) return;

    try {
      if (choice == 'camera' || choice == 'gallery') {
        final source = choice == 'camera' ? ImageSource.camera : ImageSource.gallery;
        final picked = await ImagePicker().pickImage(
          source: source,
          imageQuality: 85,
          maxWidth: 2400,
        );
        if (picked != null) {
          final bytes = await picked.readAsBytes();
          if (bytes.length > 10 * 1024 * 1024) {
            _showSnack('File is larger than 10MB limit.', isError: true);
            return;
          }
          setState(() {
            _selectedFileBytes = bytes;
            _selectedFileName = picked.name;
            _selectedFileType = 'image';
            _selectedFileSize = bytes.length;
          });
        }
      } else if (choice == 'document') {
        final result = await FilePicker.platform.pickFiles(
          type: FileType.custom,
          allowedExtensions: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'png', 'jpg', 'jpeg'],
          withData: true,
        );
        if (result != null && result.files.isNotEmpty) {
          final file = result.files.first;
          final bytes = file.bytes;
          if (bytes != null) {
            if (bytes.length > 10 * 1024 * 1024) {
              _showSnack('File is larger than 10MB limit.', isError: true);
              return;
            }
            final ext = (file.extension ?? '').toLowerCase();
            final isImg = ['jpg', 'jpeg', 'png', 'webp', 'gif'].contains(ext);
            setState(() {
              _selectedFileBytes = bytes;
              _selectedFileName = file.name;
              _selectedFileType = isImg ? 'image' : 'document';
              _selectedFileSize = bytes.length;
            });
          }
        }
      }
    } catch (e) {
      _showSnack('Could not pick file: $e', isError: true);
    }
  }

  // --------------------------------------------------------------------------
  // Emoji Picker Logic
  // --------------------------------------------------------------------------
  void _toggleEmojiPicker() {
    if (!_showEmojiPicker) {
      // Dismiss soft keyboard when opening emoji picker
      FocusScope.of(context).unfocus();
    }
    setState(() {
      _showEmojiPicker = !_showEmojiPicker;
    });
  }

  void _insertEmoji(String emoji) {
    final text = _msgController.text;
    final selection = _msgController.selection;
    final start = selection.start >= 0 ? selection.start : text.length;
    final end = selection.end >= 0 ? selection.end : text.length;
    final newText = text.replaceRange(start, end, emoji);
    _msgController.value = TextEditingValue(
      text: newText,
      selection: TextSelection.collapsed(offset: start + emoji.length),
    );
  }

  // --------------------------------------------------------------------------
  // AI Polish & Auto-Correct
  // --------------------------------------------------------------------------
  Future<void> _autoCorrectMessage() async {
    final text = _msgController.text.trim();
    if (text.isEmpty || _isAutoCorrecting) return;

    setState(() => _isAutoCorrecting = true);
    final client = context.read<ApiClient>();

    try {
      final res = await client.post('/chat/ai/autocorrect', data: {
        'message': text,
      });

      if (res['success'] == true && res['corrected'] != null) {
        final corrected = res['corrected'].toString();
        _msgController.text = corrected;
        _msgController.selection = TextSelection.collapsed(offset: corrected.length);
        _showSnack('Polished with AI ✨');
      }
    } catch (_) {} finally {
      if (mounted) {
        setState(() => _isAutoCorrecting = false);
      }
    }
  }

  void _showSnack(String msg, {bool isError = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg),
        backgroundColor: isError ? Colors.red.shade700 : const Color(0xFF10B981),
        duration: const Duration(seconds: 2),
      ),
    );
  }

  // --------------------------------------------------------------------------
  // WhatsApp Style Reactions
  // --------------------------------------------------------------------------
  Future<void> _toggleReaction(dynamic messageId, String emoji) async {
    final client = context.read<ApiClient>();
    try {
      final res = await client.post('/chat/messages/$messageId/reactions', data: {
        'emoji': emoji,
      });
      if (res['success'] == true) {
        await _loadMessages(isPoll: false);
      }
    } catch (_) {}
  }

  void _showReactionPicker(BuildContext context, Map<String, dynamic> msg) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    const quickEmojis = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        margin: const EdgeInsets.all(16),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF1E293B) : Colors.white,
          borderRadius: BorderRadius.circular(30),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.2),
              blurRadius: 16,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: quickEmojis.map((emoji) {
            return InkWell(
              onTap: () {
                Navigator.pop(ctx);
                _toggleReaction(msg['id'], emoji);
              },
              borderRadius: BorderRadius.circular(20),
              child: Padding(
                padding: const EdgeInsets.all(6),
                child: Text(emoji, style: const TextStyle(fontSize: 26)),
              ),
            );
          }).toList(),
        ),
      ),
    );
  }

  // --------------------------------------------------------------------------
  // Interactive Image Zoom Dialog
  // --------------------------------------------------------------------------
  void _showZoomImage(BuildContext context, String url, String? name) {
    showDialog(
      context: context,
      barrierColor: Colors.black.withValues(alpha: 0.92),
      builder: (ctx) => Scaffold(
        backgroundColor: Colors.transparent,
        body: SafeArea(
          child: Stack(
            children: [
              Center(
                child: InteractiveViewer(
                  minScale: 0.5,
                  maxScale: 4.0,
                  child: Image.network(
                    url,
                    fit: BoxFit.contain,
                    errorBuilder: (_, __, ___) => const Text(
                      'Could not load image',
                      style: TextStyle(color: Colors.white70),
                    ),
                  ),
                ),
              ),
              Positioned(
                top: 10,
                left: 16,
                right: 16,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Flexible(
                      child: Text(
                        name ?? 'Image Preview',
                        style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.close, color: Colors.white),
                      onPressed: () => Navigator.pop(ctx),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // --------------------------------------------------------------------------
  // In-App PDF Preview Dialog
  // --------------------------------------------------------------------------
  void _openPdfViewer(BuildContext context, String url, String? name) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        final isDark = Theme.of(context).brightness == Brightness.dark;
        return Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                margin: const EdgeInsets.only(bottom: 16),
                decoration: BoxDecoration(
                  color: Colors.grey.shade400,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const Icon(Icons.picture_as_pdf, color: Colors.red, size: 48),
              const SizedBox(height: 12),
              Text(
                name ?? 'PDF Document',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                  color: isDark ? Colors.white : Colors.black87,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () {
                        Navigator.pop(ctx);
                        _openUrl(url);
                      },
                      icon: const Icon(Icons.open_in_browser, size: 18),
                      label: const Text('Open / View'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton.icon(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF10B981),
                        foregroundColor: Colors.white,
                      ),
                      onPressed: () {
                        Navigator.pop(ctx);
                        _openUrl(url);
                      },
                      icon: const Icon(Icons.download, size: 18),
                      label: const Text('Download'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  // --------------------------------------------------------------------------
  // Send Message (Text, Emoji, and/or Attachment)
  // --------------------------------------------------------------------------
  Future<void> _sendMessage() async {
    final text = _msgController.text.trim();
    if (text.isEmpty && _selectedFileBytes == null) return;
    if (_isSending) return;

    setState(() => _isSending = true);

    final client = context.read<ApiClient>();
    final fileBytes = _selectedFileBytes;
    final fileName = _selectedFileName;

    // Clear local inputs
    _msgController.clear();
    _clearAttachment();
    setState(() {
      _aiSuggestions = [];
      _showEmojiPicker = false;
    });

    try {
      Map<String, dynamic> res;

      if (fileBytes != null) {
        res = await client.postMultipartWithFields(
          '/chat/messages',
          fields: {
            'conversation_id': widget.conversationId.toString(),
            'message': text,
          },
          fileField: 'attachment',
          bytes: fileBytes,
          filename: fileName ?? 'attachment.jpg',
        );
      } else {
        res = await client.post('/chat/messages', data: {
          'conversation_id': widget.conversationId,
          'message': text,
        });
      }

      if (res['success'] == true && res['message'] != null) {
        setState(() {
          _messages.insert(0, res['message']);
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to send message: $e')),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _isSending = false);
      }
    }
  }

  String _formatFileSize(int? bytes) {
    if (bytes == null) return '';
    if (bytes < 1024) return '$bytes B';
    if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(1)} KB';
    return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
  }

  // --------------------------------------------------------------------------
  // UI Builders
  // --------------------------------------------------------------------------

  // Selected File Preview Chip Bar
  Widget _buildAttachmentPreviewBar(BuildContext context) {
    if (_selectedFileBytes == null) return const SizedBox.shrink();
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : const Color(0xFFF1F5F9),
        border: Border(
          top: BorderSide(color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0)),
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF0F172A) : Colors.white,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: isDark ? const Color(0xFF334155) : const Color(0xFFCBD5E1)),
            ),
            child: _selectedFileType == 'image'
                ? ClipRRect(
                    borderRadius: BorderRadius.circular(7),
                    child: Image.memory(
                      Uint8List.fromList(_selectedFileBytes!),
                      fit: BoxFit.cover,
                    ),
                  )
                : const Icon(Icons.description, color: Color(0xFF0284C7), size: 20),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  _selectedFileName ?? 'Selected Attachment',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: isDark ? Colors.white : const Color(0xFF0F172A),
                  ),
                ),
                Text(
                  _formatFileSize(_selectedFileSize),
                  style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8)),
                ),
              ],
            ),
          ),
          IconButton(
            icon: const Icon(Icons.close, size: 18),
            color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
            onPressed: _clearAttachment,
            constraints: const BoxConstraints(),
            padding: const EdgeInsets.all(4),
          ),
        ],
      ),
    );
  }

  // Docked Emoji Keyboard Drawer (Matching Laravel Emojis)
  Widget _buildEmojiPickerDrawer(BuildContext context) {
    if (!_showEmojiPicker) return const SizedBox.shrink();
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final currentCat = _emojiCategories[_activeEmojiTab] ?? _emojiCategories['quick']!;
    final emojis = List<String>.from(currentCat['emojis'] ?? []);

    return Container(
      height: 220,
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC),
        border: Border(
          top: BorderSide(color: isDark ? const Color(0xFF1E293B) : const Color(0xFFE2E8F0)),
        ),
      ),
      child: Column(
        children: [
          // Header Category Pills Switcher
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF1E293B).withValues(alpha: 0.6) : Colors.white.withValues(alpha: 0.8),
              border: Border(
                bottom: BorderSide(color: isDark ? const Color(0xFF1E293B) : const Color(0xFFE2E8F0)),
              ),
            ),
            child: Row(
              children: [
                Expanded(
                  child: SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: _emojiCategories.entries.map((entry) {
                        final key = entry.key;
                        final data = entry.value;
                        final isSelected = _activeEmojiTab == key;

                        return Padding(
                          padding: const EdgeInsets.only(right: 6),
                          child: InkWell(
                            onTap: () => setState(() => _activeEmojiTab = key),
                            borderRadius: BorderRadius.circular(10),
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: isSelected
                                    ? (isDark ? const Color(0xFF1E293B) : Colors.white)
                                    : Colors.transparent,
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(
                                  color: isSelected
                                      ? (isDark ? const Color(0xFF334155) : const Color(0xFFCBD5E1))
                                      : Colors.transparent,
                                ),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(data['icon'], style: const TextStyle(fontSize: 13)),
                                  const SizedBox(width: 4),
                                  Text(
                                    data['name'],
                                    style: TextStyle(
                                      fontSize: 11,
                                      fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                                      color: isSelected
                                          ? (isDark ? const Color(0xFF38BDF8) : const Color(0xFF0284C7))
                                          : (isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B)),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        );
                      }).toList(),
                    ),
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close, size: 16),
                  color: isDark ? const Color(0xFF64748B) : const Color(0xFF94A3B8),
                  onPressed: () => setState(() => _showEmojiPicker = false),
                  constraints: const BoxConstraints(),
                  padding: const EdgeInsets.all(4),
                ),
              ],
            ),
          ),

          // Emojis Grid
          Expanded(
            child: GridView.builder(
              padding: const EdgeInsets.all(8),
              gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                maxCrossAxisExtent: 48,
                mainAxisSpacing: 6,
                crossAxisSpacing: 6,
                childAspectRatio: 1.0,
              ),
              itemCount: emojis.length,
              itemBuilder: (context, idx) {
                final emo = emojis[idx];
                return InkWell(
                  onTap: () => _insertEmoji(emo),
                  borderRadius: BorderRadius.circular(10),
                  child: Center(
                    child: Text(
                      emo,
                      style: const TextStyle(fontSize: 22),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  // Promotional Announcement Card
  Widget _buildPromotionalCard(BuildContext context) {
    if (_promotion == null) return const SizedBox.shrink();
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: isDark
              ? const [Color(0xFF1E293B), Color(0xFF0F172A)]
              : const [Color(0xFFFFFBEB), Color(0xFFFEF3C7)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: const Color(0xFFF59E0B).withValues(alpha: isDark ? 0.5 : 0.8),
        ),
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
                  style: TextStyle(
                    color: isDark ? Colors.white : const Color(0xFF92400E),
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                  ),
                ),
              ),
              IconButton(
                icon: Icon(
                  Icons.close,
                  color: isDark ? const Color(0xFF64748B) : const Color(0xFFB45309),
                  size: 16,
                ),
                onPressed: () {
                  final promoId = _promotion?['id'] is int
                      ? _promotion!['id'] as int
                      : int.tryParse(_promotion?['id']?.toString() ?? '');
                  _dismissPromotion(promoId);
                },
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
            style: TextStyle(
              color: isDark ? const Color(0xFFCBD5E1) : const Color(0xFF78350F),
              fontSize: 12,
            ),
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

  // AI Smart Reply Quick Chips
  Widget _buildAiSuggestionChips(BuildContext context) {
    if (_aiSuggestions.isEmpty) return const SizedBox.shrink();
    final isDark = Theme.of(context).brightness == Brightness.dark;

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
            backgroundColor: isDark ? const Color(0xFF1E293B) : const Color(0xFFF1F5F9),
            side: const BorderSide(color: Color(0xFF38BDF8), width: 0.8),
            avatar: const Icon(Icons.auto_awesome, color: Color(0xFF0284C7), size: 14),
            label: Text(
              replyText,
              style: TextStyle(
                color: isDark ? Colors.white : const Color(0xFF0F172A),
                fontSize: 11,
              ),
            ),
            onPressed: () {
              _msgController.text = replyText;
            },
          );
        },
      ),
    );
  }

  // Chat Bubble
  Widget _buildChatBubble(BuildContext context, Map<String, dynamic> msg, bool isMe) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final isSuperAdmin = msg['sender_type'] == 'super_admin';

    final bubbleBg = isMe
        ? (isDark ? const Color(0xFF059669) : const Color(0xFF10B981))
        : (isSuperAdmin
            ? (isDark ? const Color(0xFF1E3A8A).withValues(alpha: 0.5) : const Color(0xFFE0F2FE))
            : (isDark ? const Color(0xFF1E293B) : const Color(0xFFFFFFFF)));

    final textColor = isMe
        ? Colors.white
        : (isDark ? const Color(0xFFF8FAFC) : const Color(0xFF0F172A));

    final senderColor = isMe
        ? Colors.white70
        : (isSuperAdmin
            ? const Color(0xFF0284C7)
            : (isDark ? const Color(0xFF38BDF8) : const Color(0xFF0284C7)));

    final borderColor = isMe
        ? null
        : (isSuperAdmin
            ? const Color(0xFF38BDF8).withValues(alpha: 0.3)
            : (isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0)));

    String timeStr = '';
    if (msg['created_at'] != null) {
      try {
        final dt = DateTime.parse(msg['created_at'].toString()).toLocal();
        timeStr = '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
      } catch (_) {
        timeStr = '';
      }
    }

    return Align(
      alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
      child: GestureDetector(
        onLongPress: () => _showReactionPicker(context, msg),
        child: Container(
          margin: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
          padding: const EdgeInsets.all(12),
          constraints: BoxConstraints(
            maxWidth: MediaQuery.of(context).size.width * 0.75,
          ),
          decoration: BoxDecoration(
            color: bubbleBg,
            borderRadius: BorderRadius.circular(12),
            border: borderColor != null ? Border.all(color: borderColor) : null,
            boxShadow: isDark || isMe
                ? null
                : [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.04),
                      blurRadius: 4,
                      offset: const Offset(0, 1),
                    ),
                  ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (!isMe)
                Padding(
                  padding: const EdgeInsets.only(bottom: 3),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        isSuperAdmin
                            ? 'Platform Support (Super Admin)'
                            : (msg['sender']?['name'] ?? ''),
                        style: TextStyle(
                          color: senderColor,
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      if (isSuperAdmin) ...[
                        const SizedBox(width: 4),
                        const Icon(Icons.verified, size: 12, color: Color(0xFF0284C7)),
                      ],
                    ],
                  ),
                ),
              if (msg['attachment_url'] != null) ...[
                if (msg['attachment_type'] == 'image')
                  GestureDetector(
                    onTap: () => _showZoomImage(context, msg['attachment_url'], msg['attachment_name']),
                    child: Stack(
                      alignment: Alignment.bottomRight,
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Image.network(
                            msg['attachment_url'],
                            height: 180,
                            width: double.infinity,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => const Text('Could not load image', style: TextStyle(fontSize: 11)),
                          ),
                        ),
                        Container(
                          margin: const EdgeInsets.all(6),
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: Colors.black.withValues(alpha: 0.6),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.zoom_in, color: Colors.white, size: 13),
                              SizedBox(width: 2),
                              Text('Zoom', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                            ],
                          ),
                        ),
                      ],
                    ),
                  )
                else if (msg['attachment_type'] == 'pdf' || (msg['attachment_url']?.toString().toLowerCase().endsWith('.pdf') ?? false))
                  InkWell(
                    onTap: () => _openPdfViewer(context, msg['attachment_url'], msg['attachment_name']),
                    child: Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: isDark ? const Color(0xFF0F172A) : const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.picture_as_pdf, color: Colors.red, size: 20),
                          const SizedBox(width: 8),
                          Flexible(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  msg['attachment_name'] ?? 'PDF Document',
                                  style: TextStyle(
                                    color: textColor,
                                    decoration: TextDecoration.underline,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                                const Text(
                                  'Tap to preview document',
                                  style: TextStyle(color: Color(0xFF10B981), fontSize: 10, fontWeight: FontWeight.w500),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  )
                else
                  InkWell(
                    onTap: () => _openUrl(msg['attachment_url']),
                    child: Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: isDark ? const Color(0xFF0F172A) : const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.description, color: Color(0xFF0284C7), size: 18),
                          const SizedBox(width: 6),
                          Flexible(
                            child: Text(
                              msg['attachment_name'] ?? 'Attachment',
                              style: TextStyle(
                                color: textColor,
                                decoration: TextDecoration.underline,
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                const SizedBox(height: 6),
              ],
              if ((msg['message'] ?? '').isNotEmpty)
                Text(
                  msg['message'] ?? '',
                  style: TextStyle(color: textColor, fontSize: 13),
                ),
              // Meta time row & double checkmarks
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    if (timeStr.isNotEmpty)
                      Text(
                        timeStr,
                        style: TextStyle(
                          color: isMe ? Colors.white.withValues(alpha: 0.7) : Colors.grey,
                          fontSize: 9,
                        ),
                      ),
                    if (isMe) ...[
                      const SizedBox(width: 3),
                      const Text('✓✓', style: TextStyle(color: Color(0xFF53BDEB), fontSize: 10, fontWeight: FontWeight.bold)),
                    ],
                  ],
                ),
              ),
              // WhatsApp Style Reaction Badges
              if (msg['reactions'] != null && (msg['reactions'] as List).isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Wrap(
                    spacing: 4,
                    runSpacing: 4,
                    children: (msg['reactions'] as List).map<Widget>((r) {
                      final emoji = r['emoji']?.toString() ?? '👍';
                      final count = r['count']?.toString() ?? '1';
                      return InkWell(
                        onTap: () => _toggleReaction(msg['id'], emoji),
                        borderRadius: BorderRadius.circular(12),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: isDark ? const Color(0xFF0F172A) : const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: isDark ? const Color(0xFF334155) : const Color(0xFFCBD5E1)),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(emoji, style: const TextStyle(fontSize: 11)),
                              const SizedBox(width: 3),
                              Text(count, style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: textColor)),
                            ],
                          ),
                        ),
                      );
                    }).toList(),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  // Input Area with Attachment Button, Emoji Button, AI Polish Button, and Send
  Widget _buildInputArea(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final inputFill = isDark ? const Color(0xFF1E293B) : Colors.white;
    final borderColor = isDark ? const Color(0xFF334155) : const Color(0xFFCBD5E1);
    final textColor = isDark ? Colors.white : const Color(0xFF0F172A);
    final hintColor = isDark ? const Color(0xFF64748B) : const Color(0xFF94A3B8);

    return Container(
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC),
        border: Border(
          top: BorderSide(
            color: isDark ? const Color(0xFF1E293B) : const Color(0xFFE2E8F0),
          ),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          // Attachment Button (Paperclip)
          IconButton(
            icon: const Icon(Icons.attach_file_rounded),
            color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
            tooltip: 'Attach photo or document',
            onPressed: _showAttachmentPicker,
            constraints: const BoxConstraints(),
            padding: const EdgeInsets.all(8),
          ),

          // Emoji Picker Toggle Button
          IconButton(
            icon: Icon(
              _showEmojiPicker ? Icons.keyboard_rounded : Icons.emoji_emotions_outlined,
              color: _showEmojiPicker ? const Color(0xFFF59E0B) : (isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B)),
            ),
            tooltip: 'Insert emoji',
            onPressed: _toggleEmojiPicker,
            constraints: const BoxConstraints(),
            padding: const EdgeInsets.all(8),
          ),

          // Text Field
          Expanded(
            child: TextField(
              controller: _msgController,
              style: TextStyle(color: textColor, fontSize: 13),
              decoration: InputDecoration(
                hintText: 'Type message...',
                hintStyle: TextStyle(color: hintColor),
                filled: true,
                fillColor: inputFill,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(24),
                  borderSide: BorderSide(color: borderColor),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(24),
                  borderSide: BorderSide(color: borderColor),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(24),
                  borderSide: const BorderSide(color: Color(0xFF10B981), width: 1.5),
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              ),
              onTap: () {
                if (_showEmojiPicker) {
                  setState(() => _showEmojiPicker = false);
                }
              },
              onSubmitted: (_) => _sendMessage(),
            ),
          ),

          // AI Polish Button (✨)
          ValueListenableBuilder<TextEditingValue>(
            valueListenable: _msgController,
            builder: (context, val, _) {
              if (val.text.trim().isEmpty) return const SizedBox.shrink();
              return IconButton(
                icon: _isAutoCorrecting
                    ? const SizedBox(
                        width: 14,
                        height: 14,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF0284C7)),
                      )
                    : const Icon(Icons.auto_awesome, color: Color(0xFF0284C7), size: 18),
                tooltip: 'Polish with AI',
                onPressed: _autoCorrectMessage,
                constraints: const BoxConstraints(),
                padding: const EdgeInsets.all(6),
              );
            },
          ),

          const SizedBox(width: 4),

          // Send Button
          IconButton(
            icon: _isSending
                ? const SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF10B981)),
                  )
                : const Icon(Icons.send_rounded, color: Color(0xFF10B981)),
            onPressed: _sendMessage,
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: Text(
          widget.title,
          style: TextStyle(
            fontSize: 16,
            color: isDark ? Colors.white : const Color(0xFF0F172A),
            fontWeight: FontWeight.w600,
          ),
        ),
        backgroundColor: isDark ? const Color(0xFF1E293B) : Colors.white,
        iconTheme: IconThemeData(color: isDark ? Colors.white : const Color(0xFF0F172A)),
        elevation: isDark ? 0 : 0.5,
      ),
      body: Column(
        children: [
          _buildPromotionalCard(context),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: Color(0xFF10B981)))
                : _messages.isEmpty
                    ? Center(
                        child: Text(
                          'No messages yet. Send a greeting!',
                          style: TextStyle(
                            color: isDark ? const Color(0xFF64748B) : const Color(0xFF94A3B8),
                            fontSize: 13,
                          ),
                        ),
                      )
                    : ListView.builder(
                        reverse: true,
                        itemCount: _messages.length,
                        itemBuilder: (context, index) {
                          final msg = _messages[index];
                          final senderId = msg['sender_id']?.toString();
                          final isMe = senderId != null && senderId == _currentUserId;

                          return _buildChatBubble(context, msg, isMe);
                        },
                      ),
          ),
          _buildAiSuggestionChips(context),
          _buildAttachmentPreviewBar(context),
          _buildInputArea(context),
          _buildEmojiPickerDrawer(context),
        ],
      ),
    );
  }
}
