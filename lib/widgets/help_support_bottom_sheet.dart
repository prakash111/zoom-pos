import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../screens/chat/internal_staff_chat_list_screen.dart';

Future<void> _launchWhatsApp(String phone) async {
  final digits = phone.replaceAll(RegExp(r'[^0-9]'), '');
  final waUrl = Uri.parse(
    'https://wa.me/$digits?text=${Uri.encodeComponent('Hello, I need assistance with Zoom Sales CRM & Inventory.')}',
  );
  if (await canLaunchUrl(waUrl)) {
    await launchUrl(waUrl, mode: LaunchMode.externalApplication);
  }
}

Future<void> _launchDialer(String phone) async {
  final cleanPhone = phone.replaceAll(RegExp(r'[^0-9+]'), '');
  final telUrl = Uri.parse('tel:$cleanPhone');
  if (await canLaunchUrl(telUrl)) {
    await launchUrl(telUrl);
  }
}

Future<void> _launchEmail(String email) async {
  final mailUrl = Uri.parse(
    'mailto:$email?subject=${Uri.encodeComponent('Zoom Sales CRM & Inventory Support Inquiry')}',
  );
  if (await canLaunchUrl(mailUrl)) {
    await launchUrl(mailUrl);
  }
}

Widget buildHelpSupportDrawerSheet(BuildContext context, {String? customPhone, String? customEmail}) {
  final phone = (customPhone != null && customPhone.isNotEmpty) ? customPhone : '+9183535075196';
  final email = (customEmail != null && customEmail.isNotEmpty) ? customEmail : 'support@zoomnearby.com';

  return Container(
    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
    decoration: const BoxDecoration(
      color: Color(0xFF0F172A),
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Sheet Header
        Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFF10B981).withValues(alpha: 0.15),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.support_agent_rounded, color: Color(0xFF10B981), size: 28),
            ),
            const SizedBox(width: 14),
            const Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Help & Customer Support',
                  style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.bold),
                ),
                SizedBox(height: 2),
                Text(
                  'We are here to assist you anytime',
                  style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                ),
              ],
            ),
          ],
        ),
        const SizedBox(height: 24),

        // 1. LIVE CHAT SUPPORT (NEW ITEM)
        _buildSupportTile(
          context: context,
          icon: Icons.chat_bubble_outline_rounded,
          iconColor: const Color(0xFF38BDF8),
          title: 'Live Chat Support',
          subtitle: 'Chat live with staff or support team',
          badge: 'Online',
          badgeColor: const Color(0xFF10B981),
          onTap: () {
            Navigator.pop(context);
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const InternalStaffChatListScreen()),
            );
          },
        ),

        const SizedBox(height: 12),

        // 2. CHAT ON WHATSAPP (EXISTING)
        _buildSupportTile(
          context: context,
          icon: Icons.chat_outlined,
          iconColor: const Color(0xFF10B981),
          title: 'Chat on WhatsApp',
          subtitle: phone,
          onTap: () => _launchWhatsApp(phone),
        ),

        const SizedBox(height: 12),

        // 3. CALL SUPPORT HOTLINE (EXISTING)
        _buildSupportTile(
          context: context,
          icon: Icons.phone_outlined,
          iconColor: const Color(0xFF64748B),
          title: 'Call Support Hotline',
          subtitle: phone,
          onTap: () => _launchDialer(phone),
        ),

        const SizedBox(height: 12),

        // 4. EMAIL SUPPORT DESK (EXISTING)
        _buildSupportTile(
          context: context,
          icon: Icons.mail_outline_rounded,
          iconColor: const Color(0xFF64748B),
          title: 'Email Support Desk',
          subtitle: email,
          onTap: () => _launchEmail(email),
        ),
      ],
    ),
  );
}

Widget _buildSupportTile({
  required BuildContext context,
  required IconData icon,
  required Color iconColor,
  required String title,
  required String subtitle,
  String? badge,
  Color? badgeColor,
  required VoidCallback onTap,
}) {
  return InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(14),
    child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFF334155).withValues(alpha: 0.5)),
      ),
      child: Row(
        children: [
          Icon(icon, color: iconColor, size: 24),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(title, style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600)),
                    if (badge != null) ...[
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: (badgeColor ?? const Color(0xFF10B981)).withValues(alpha: 0.2),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          badge,
                          style: TextStyle(
                            color: badgeColor ?? const Color(0xFF10B981),
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 2),
                Text(subtitle, style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12)),
              ],
            ),
          ),
          const Icon(Icons.arrow_forward_ios_rounded, color: Color(0xFF64748B), size: 14),
        ],
      ),
    ),
  );
}
