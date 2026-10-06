import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/models/user_model.dart';
import 'package:zoom_pos_mobile/features/auth/auth_provider.dart';
import 'package:zoom_pos_mobile/screens/chat/chat_conversation_screen.dart';

class MockChatApiClient extends Fake implements ApiClient {
  final List<Map<String, dynamic>> sentRequests = [];

  @override
  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) async {
    if (path.contains('/messages')) {
      return {
        'success': true,
        'messages': [
          {
            'id': 1,
            'conversation_id': 10,
            'sender_id': 'user_2',
            'sender_type': 'user',
            'message': 'Hello from staff member! 👋',
            'created_at': '2026-10-06T12:00:00Z',
            'sender': {
              'id': 'user_2',
              'name': 'Sarah Staff',
              'role': 'cashier',
            },
          },
        ],
        'ai_suggestions': ['Got it!', 'Checking stock now'],
      };
    }
    return {'success': true};
  }

  @override
  Future<Map<String, dynamic>> post(String path, {dynamic data}) async {
    sentRequests.add({'path': path, 'data': data});
    return {
      'success': true,
      'message': {
        'id': 2,
        'conversation_id': 10,
        'sender_id': 'user_1',
        'sender_type': 'user',
        'message': data is Map ? data['message'] : 'sent',
        'created_at': '2026-10-06T12:01:00Z',
      },
    };
  }
}

class MockChatAuthProvider extends ChangeNotifier implements AuthProvider {
  @override
  UserModel? get user => UserModel(
        id: 'user_1',
        name: 'Demo Admin',
        email: 'admin@demo.test',
        role: 'administrator',
        companyId: '1',
        permissions: const {},
      );

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  Widget buildChatTestWidget({
    required MockChatApiClient apiClient,
    required MockChatAuthProvider authProvider,
  }) {
    return MultiProvider(
      providers: [
        Provider<ApiClient>.value(value: apiClient),
        ChangeNotifierProvider<AuthProvider>.value(value: authProvider),
      ],
      child: const MaterialApp(
        home: ChatConversationScreen(
          conversationId: 10,
          title: 'Staff Chat & Support',
        ),
      ),
    );
  }

  group('ChatConversationScreen Emoji and Attachment functionality', () {
    testWidgets('renders input bar with attachment and emoji action buttons', (tester) async {
      final client = MockChatApiClient();
      final auth = MockChatAuthProvider();

      await tester.pumpWidget(buildChatTestWidget(apiClient: client, authProvider: auth));
      await tester.pump();

      // Verify the AppBar and title
      expect(find.text('Staff Chat & Support'), findsOneWidget);

      // Verify Attachment Button (paperclip) is present
      expect(find.byIcon(Icons.attach_file_rounded), findsOneWidget);

      // Verify Emoji Button (smiley) is present
      expect(find.byIcon(Icons.emoji_emotions_outlined), findsOneWidget);

      // Verify Text Field and Send button are present
      expect(find.byType(TextField), findsOneWidget);
      expect(find.byIcon(Icons.send_rounded), findsOneWidget);
    });

    testWidgets('toggling emoji button opens the docked emoji keyboard drawer', (tester) async {
      final client = MockChatApiClient();
      final auth = MockChatAuthProvider();

      await tester.pumpWidget(buildChatTestWidget(apiClient: client, authProvider: auth));
      await tester.pump();

      // Initially, emoji drawer category tabs should not be visible
      expect(find.text('Quick'), findsNothing);
      expect(find.text('Smileys'), findsNothing);

      // Tap the Emoji button
      await tester.tap(find.byIcon(Icons.emoji_emotions_outlined));
      await tester.pump();

      // Emoji drawer is now open: categories matching Laravel are visible
      expect(find.text('Quick'), findsOneWidget);
      expect(find.text('Smileys'), findsOneWidget);
      expect(find.text('Business'), findsOneWidget);
      expect(find.text('Symbols'), findsOneWidget);

      // Verify quick reaction emojis are displayed (e.g. thumbs up 👍, fire 🔥)
      expect(find.text('👍'), findsOneWidget);
      expect(find.text('🔥'), findsOneWidget);
      expect(find.text('🚀'), findsOneWidget);
    });

    testWidgets('selecting an emoji inserts it directly into the text field', (tester) async {
      tester.view.physicalSize = const Size(800, 1200);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final client = MockChatApiClient();
      final auth = MockChatAuthProvider();

      await tester.pumpWidget(buildChatTestWidget(apiClient: client, authProvider: auth));
      await tester.pump();

      // Open Emoji Drawer
      await tester.tap(find.byIcon(Icons.emoji_emotions_outlined));
      await tester.pump();

      // Tap thumbs up emoji
      await tester.tap(find.text('👍'));
      await tester.pump();

      // Verify text field now contains the emoji
      expect(find.text('👍'), findsWidgets);

      // Tap rocket emoji
      await tester.tap(find.text('🚀'));
      await tester.pump();

      // Verify text field now contains both emojis
      expect(find.text('👍🚀'), findsOneWidget);
    });

    testWidgets('switching emoji tabs shows category specific emojis', (tester) async {
      tester.view.physicalSize = const Size(800, 1200);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final client = MockChatApiClient();
      final auth = MockChatAuthProvider();

      await tester.pumpWidget(buildChatTestWidget(apiClient: client, authProvider: auth));
      await tester.pump();

      // Open Emoji Drawer
      await tester.tap(find.byIcon(Icons.emoji_emotions_outlined));
      await tester.pump();

      // Tap "Smileys" tab
      await tester.tap(find.text('Smileys'));
      await tester.pump();

      // Verify smileys emojis appear (e.g. 😂, 😍)
      expect(find.text('😂'), findsOneWidget);
      expect(find.text('😍'), findsOneWidget);

      // Tap "Business" tab
      await tester.tap(find.text('Business'));
      await tester.pump();

      // Verify retail/business emojis appear (e.g. 📦, 💰, 🧾)
      expect(find.text('📦'), findsOneWidget);
      expect(find.text('💰'), findsOneWidget);
      expect(find.text('🧾'), findsOneWidget);
    });

    testWidgets('tapping attachment button opens bottom sheet with photo, gallery, and document options', (tester) async {
      final client = MockChatApiClient();
      final auth = MockChatAuthProvider();

      await tester.pumpWidget(buildChatTestWidget(apiClient: client, authProvider: auth));
      await tester.pump();

      // Tap Attachment Button
      await tester.tap(find.byIcon(Icons.attach_file_rounded));
      await tester.pump();

      // Verify bottom sheet options
      expect(find.text('Take Photo'), findsOneWidget);
      expect(find.text('Upload Photo from Gallery'), findsOneWidget);
      expect(find.text('Select Document / PDF'), findsOneWidget);
    });
  });
}
