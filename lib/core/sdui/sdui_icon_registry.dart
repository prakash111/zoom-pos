import 'package:flutter/material.dart';

/// Centralized registry resolving server icon names to Flutter [IconData].
class SduiIconRegistry {
  SduiIconRegistry._();

  static const Map<String, IconData> _icons = {
    // Sales & POS
    'point_of_sale': Icons.point_of_sale_outlined,
    'shopping_cart_checkout': Icons.shopping_cart_checkout,
    'shopping_cart': Icons.shopping_cart_outlined,
    'receipt_long': Icons.receipt_long_outlined,
    'description': Icons.description_outlined,
    'local_shipping': Icons.local_shipping_outlined,
    'handyman': Icons.handyman_outlined,
    'people': Icons.people_outline,
    'person': Icons.person_outline,
    'person_add': Icons.person_add_outlined,
    'person_add_outlined': Icons.person_add_outlined,
    'person_pin': Icons.person_pin_outlined,
    'qr_code_scanner': Icons.qr_code_scanner,
    'phone': Icons.phone_outlined,
    'phone_outlined': Icons.phone_outlined,
    'call': Icons.phone_outlined,
    'email': Icons.email_outlined,
    'email_outlined': Icons.email_outlined,
    'business': Icons.business_outlined,
    'corporate_fare': Icons.corporate_fare_outlined,
    'notes': Icons.notes_outlined,
    'filter_list': Icons.filter_list,
    'search': Icons.search,
    'trending_up': Icons.trending_up,
    'trending_down': Icons.trending_down,
    'check': Icons.check,
    'alarm': Icons.alarm_outlined,
    'access_time': Icons.access_time_outlined,

    // Restaurant & Kitchen
    'restaurant': Icons.restaurant_outlined,
    'table_restaurant': Icons.table_restaurant_outlined,
    'soup_kitchen': Icons.soup_kitchen_outlined,
    'restaurant_menu': Icons.restaurant_menu_outlined,

    // Pharmacy & Health
    'local_pharmacy': Icons.local_pharmacy_outlined,
    'medication': Icons.medication_outlined,
    'medical_services': Icons.medical_services_outlined,
    'medical_information': Icons.medical_information_outlined,
    'personal_injury': Icons.personal_injury_outlined,
    'note_add': Icons.note_add_outlined,
    'post_add': Icons.post_add_outlined,
    'assignment_add': Icons.assignment_outlined,

    // Service & Salon
    'spa': Icons.spa_outlined,
    'content_cut': Icons.content_cut_outlined,
    'cut': Icons.content_cut_outlined,
    'event_available': Icons.event_available_outlined,
    'schedule': Icons.schedule_outlined,
    'calendar_month': Icons.calendar_month_outlined,
    'add_task': Icons.add_task_outlined,
    'edit_calendar': Icons.edit_calendar_outlined,
    'bookmark_add': Icons.bookmark_add_outlined,

    // Financial
    'savings': Icons.savings_outlined,
    'notifications_active': Icons.notifications_active_outlined,
    'notifications_none': Icons.notifications_none_outlined,
    'sync': Icons.sync,
    'request_quote': Icons.request_quote_outlined,
    'flag': Icons.flag_outlined,
    'insights': Icons.insights_outlined,
    'bar_chart': Icons.bar_chart_outlined,
    'payments': Icons.payments_outlined,
    'credit_card': Icons.credit_card_outlined,
    'qr_code_2': Icons.qr_code_2_outlined,
    'account_balance': Icons.account_balance_outlined,
    'account_balance_wallet': Icons.account_balance_wallet_outlined,

    // Products & Inventory
    'inventory': Icons.inventory_2_outlined,
    'inventory_2': Icons.inventory_2_outlined,
    'sell': Icons.sell_outlined,
    'auto_awesome': Icons.auto_awesome_outlined,
    'straighten': Icons.straighten_outlined,
    'percent': Icons.percent_outlined,
    'qr_code': Icons.qr_code_outlined,

    // Admin & Settings
    'workspace_premium': Icons.workspace_premium_outlined,
    'settings': Icons.settings_outlined,
    'translate': Icons.translate_outlined,
    'badge': Icons.badge_outlined,
    'devices_other': Icons.devices_other_outlined,
    'tune': Icons.tune_outlined,
    'storefront': Icons.storefront_outlined,
    'receipt': Icons.receipt_outlined,
    'monetization_on': Icons.monetization_on_outlined,
    'api': Icons.api_outlined,
    'vpn_key': Icons.vpn_key_outlined,
    'key': Icons.key_outlined,
    'chat': Icons.chat_outlined,
    'sms': Icons.sms_outlined,
    'textsms': Icons.textsms_outlined,
    'mail': Icons.mail_outlined,
    'webhook': Icons.webhook_outlined,
    'hub': Icons.hub_outlined,
    'slack': Icons.forum_outlined,
    'telegram': Icons.send_outlined,
    'discord': Icons.forum_outlined,
    'bell': Icons.notifications_none_outlined,
    'megaphone': Icons.campaign_outlined,
    'arrow_forward_ios': Icons.arrow_forward_ios,
    'menu_open': Icons.menu_open_outlined,
    'lock': Icons.lock_outline,
    'lock_reset': Icons.lock_reset_outlined,
    'password': Icons.password_outlined,
    'format_list_bulleted': Icons.format_list_bulleted_outlined,
    'add_circle': Icons.add_circle_outline,
    'add_circle_outline': Icons.add_circle_outline,

    // Action pills & dialogs
    'pause_circle': Icons.pause_circle_outline,
    'pause_circle_outline': Icons.pause_circle_outline,
    'edit_note': Icons.edit_note,
    'local_offer': Icons.local_offer_outlined,
    'local_offer_outlined': Icons.local_offer_outlined,
    'event': Icons.event_outlined,
    'notification_add': Icons.notification_add_outlined,
    'notification_add_outlined': Icons.notification_add_outlined,

    // Status badges
    'check_circle': Icons.check_circle_outline,
    'timelapse': Icons.timelapse_outlined,
    'pending_actions': Icons.pending_actions_outlined,
    'cancel': Icons.cancel_outlined,
    'drafts': Icons.drafts_outlined,
    'assignment_return': Icons.assignment_return_outlined,
    'commute': Icons.commute_outlined,
    'verified': Icons.verified_outlined,
    'task_alt': Icons.task_alt,
    'block': Icons.block_outlined,
    'send': Icons.send_outlined,
    'thumb_up': Icons.thumb_up_outlined,
    'thumb_down': Icons.thumb_down_outlined,
    'engineering': Icons.engineering_outlined,
    'done_all': Icons.done_all,
    'widgets': Icons.widgets_outlined,
    'category': Icons.category_outlined,
    'circle': Icons.circle_outlined,
    'store': Icons.storefront_outlined,
    'mark_email_read': Icons.mark_email_read_outlined,
    'picture_as_pdf': Icons.picture_as_pdf_outlined,
    'print': Icons.print_outlined,
    'mail_lock': Icons.mail_lock_outlined,
    'facebook': Icons.facebook,
    'google': Icons.g_mobiledata,
    'rocket_launch': Icons.rocket_launch_outlined,
    'today': Icons.today_outlined,
    'chevron_left': Icons.chevron_left,
    'chevron_right': Icons.chevron_right,
    'arrow_forward': Icons.arrow_forward_rounded,
    'arrow_forward_rounded': Icons.arrow_forward_rounded,
    'arrow_back': Icons.arrow_back_rounded,
    'check_circle_outline': Icons.check_circle_outline,
    'add': Icons.add,
    'login': Icons.login,
  };

  /// Direct icon lookup alias matching `SduiIconRegistry.get(...)`.
  static IconData get(String? iconName,
          {IconData fallback = Icons.widgets_outlined}) =>
      resolve(iconName, fallback: fallback);

  /// Resolves an icon name from server payload to an [IconData].
  static IconData resolve(String? iconName,
      {IconData fallback = Icons.widgets_outlined}) {
    if (iconName == null || iconName.isEmpty) return fallback;
    final normalized = iconName.toLowerCase().trim().replaceAll('-', '_');
    return _icons[normalized] ?? fallback;
  }

  /// Parses a hex color string (e.g. '#15803d', '15803d') into a Flutter [Color].
  static Color parseColor(String? hexString,
      {Color fallback = const Color(0xFF2563EB)}) {
    if (hexString == null || hexString.isEmpty) return fallback;
    try {
      final buffer = StringBuffer();
      final cleaned = hexString.replaceAll('#', '').trim();
      if (cleaned.length == 6) buffer.write('ff');
      buffer.write(cleaned);
      return Color(int.parse(buffer.toString(), radix: 16));
    } catch (_) {
      return fallback;
    }
  }
}
