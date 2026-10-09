import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/sdui/sdui_icon_registry.dart';
import 'package:zoom_pos_mobile/screens/main_shell/widgets/drawer_item_tile.dart';
import 'package:zoom_pos_mobile/utils/icon_helper.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('Staff Chat & Support SDUI Icon Resolution', () {
    test('getSduiIcon correctly maps live_chat to speech bubble icon', () {
      expect(getSduiIcon('live_chat'), equals(Icons.chat_bubble_outline_rounded));
      expect(getSduiIcon('chat'), equals(Icons.chat_bubble_outline_rounded));
      expect(getSduiIcon('message'), equals(Icons.chat_bubble_outline_rounded));
      expect(getSduiIcon('chat_messages'), equals(Icons.chat_bubble_outline_rounded));
    });

    test('getSduiIcon correctly maps notifications_active to alert bell icon', () {
      expect(
        getSduiIcon('notifications_active'),
        equals(Icons.notifications_active_outlined),
      );
      expect(
        getSduiIcon('send_staff_notification'),
        equals(Icons.notifications_active_outlined),
      );
      expect(
        getSduiIcon('send_to_mobile'),
        equals(Icons.notifications_active_outlined),
      );
      expect(
        getSduiIcon('notification'),
        equals(Icons.notifications_active_outlined),
      );
    });

    test('SduiIconRegistry.resolve correctly resolves live_chat and notifications_active', () {
      expect(
        SduiIconRegistry.resolve('live_chat'),
        equals(Icons.chat_bubble_outline_rounded),
      );
      expect(
        SduiIconRegistry.resolve('notifications_active'),
        equals(Icons.notifications_active_outlined),
      );
      expect(
        SduiIconRegistry.resolve('send_staff_notification'),
        equals(Icons.notifications_active_outlined),
      );
    });

    testWidgets('DrawerItemTile displays chat_bubble icon for Live Staff Chat', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: DrawerItemTile(
              item: const {
                'id': 'live_staff_chat',
                'title': 'Live Staff Chat',
                'icon': 'live_chat',
              },
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Live Staff Chat'), findsOneWidget);
      expect(find.byIcon(Icons.chat_bubble_outline_rounded), findsOneWidget);
      // Fallback grid icon must NOT be present
      expect(find.byIcon(Icons.grid_view_rounded), findsNothing);
      expect(find.byIcon(Icons.widgets_outlined), findsNothing);
    });

    testWidgets('DrawerItemTile displays alert bell icon for Send Staff Notification', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: DrawerItemTile(
              item: const {
                'id': 'send_staff_notification',
                'title': 'Send Staff Notification',
                'icon': 'notifications_active',
              },
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Send Staff Notification'), findsOneWidget);
      expect(find.byIcon(Icons.notifications_active_outlined), findsOneWidget);
      // Fallback grid icon must NOT be present
      expect(find.byIcon(Icons.grid_view_rounded), findsNothing);
      expect(find.byIcon(Icons.widgets_outlined), findsNothing);
    });
  });
}
