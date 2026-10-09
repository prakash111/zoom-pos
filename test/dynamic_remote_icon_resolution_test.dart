import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/sdui/models/sdui_models.dart';
import 'package:zoom_pos_mobile/core/sdui/sdui_icon_registry.dart';
import 'package:zoom_pos_mobile/screens/main_shell/widgets/drawer_item_tile.dart';
import 'package:zoom_pos_mobile/widgets/dynamic_sdui_icon.dart';

void main() {
  group('Dynamic Remote Icon Resolution Tests', () {
    testWidgets('Renders dynamic Material code point from backend Map payload',
        (tester) async {
      final chatItem = {
        'id': 'live_staff_chat',
        'title': 'Live Staff Chat',
        'icon': {
          'type': 'material_code',
          'code_point': 0xe153, // chat_bubble_outline
          'font_family': 'MaterialIcons',
          'url': null,
        },
      };

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: DrawerItemTile(item: chatItem),
          ),
        ),
      );

      // Verify DynamicSduiIcon widget is rendered
      expect(find.byType(DynamicSduiIcon), findsOneWidget);
      // Verify Icon widget rendered with exact codePoint 0xe153
      final iconFinder = find.byType(Icon);
      expect(iconFinder, findsOneWidget);
      final icon = tester.widget<Icon>(iconFinder);
      expect(icon.icon?.codePoint, equals(0xe153));
    });

    testWidgets('Renders dynamic notification active icon from backend Map payload',
        (tester) async {
      final notifItem = {
        'id': 'send_staff_notification',
        'title': 'Send Staff Notification',
        'icon': {
          'type': 'material_code',
          'code_point': 0xe44e, // notifications_active
          'font_family': 'MaterialIcons',
          'url': null,
        },
      };

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: DrawerItemTile(item: notifItem),
          ),
        ),
      );

      final iconFinder = find.byType(Icon);
      expect(iconFinder, findsOneWidget);
      final icon = tester.widget<Icon>(iconFinder);
      expect(icon.icon?.codePoint, equals(0xe44e));
    });

    testWidgets('Renders direct hex string code point (0xe0ba for Staff Directory)',
        (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: DynamicSduiIcon(
              iconData: '0xe0ba',
              size: 24,
            ),
          ),
        ),
      );

      final iconFinder = find.byType(Icon);
      expect(iconFinder, findsOneWidget);
      final icon = tester.widget<Icon>(iconFinder);
      expect(icon.icon?.codePoint, equals(0xe0ba));
    });

    testWidgets('Renders direct integer code point (0xe481 for Payroll)',
        (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: DynamicSduiIcon(
              iconData: 0xe481,
              size: 24,
            ),
          ),
        ),
      );

      final iconFinder = find.byType(Icon);
      expect(iconFinder, findsOneWidget);
      final icon = tester.widget<Icon>(iconFinder);
      expect(icon.icon?.codePoint, equals(0xe481));
    });

    test('SduiIconRegistry.resolve handles structured map and hex strings', () {
      final mapResolved = SduiIconRegistry.resolve({
        'type': 'material_code',
        'code_point': 0xe041, // customer_wallet
        'font_family': 'MaterialIcons',
      });
      expect(mapResolved.codePoint, equals(0xe041));

      final hexResolved = SduiIconRegistry.resolve('0xe3d0'); // vip_tiers
      expect(hexResolved.codePoint, equals(0xe3d0));

      final intResolved = SduiIconRegistry.resolve(0xe54c); // point_of_sale
      expect(intResolved.codePoint, equals(0xe54c));
    });

    test('SduiNavItemSchema deserializes dynamic icon payload correctly', () {
      final json = {
        'key': 'live_staff_chat',
        'title': 'Live Staff Chat',
        'icon': {
          'type': 'material_code',
          'code_point': 0xe153,
          'font_family': 'MaterialIcons',
          'url': 'https://saas.zoomnearby.com/modules/chat/assets/icons/chat.svg',
        },
        'target_endpoint': '/chat/staff',
      };

      final schema = SduiNavItemSchema.fromJson(json);
      expect(schema.key, equals('live_staff_chat'));
      expect(schema.iconPayload, isA<Map>());
      expect((schema.iconPayload as Map)['code_point'], equals(0xe153));
      expect(schema.dynamicIcon, isA<Map>());

      final serialized = schema.toJson();
      expect(serialized['icon'], isA<Map>());
      expect(serialized['icon']['code_point'], equals(0xe153));
    });
  });
}
