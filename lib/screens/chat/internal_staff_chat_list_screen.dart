import 'package:flutter/material.dart';

import '../../core/sdui/screens/dynamic_schema_page.dart';

/// Server-Driven Internal Staff Chat & Support Inbox Screen.
///
/// Fully driven by the backend schema from `/api/tenant/chat/views/staff-chat`.
/// Adapts dynamically to dark and light mode themes without requiring any compiled
/// Flutter code changes when modifying the inbox layout, search, filters, or cards.
class InternalStaffChatListScreen extends StatelessWidget {
  const InternalStaffChatListScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const DynamicSchemaPage(
      endpoint: '/api/tenant/chat/views/staff-chat',
      initialTitle: 'Inbox',
    );
  }
}
