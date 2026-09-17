import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/widgets/unified_document_dispatch_sheet.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets(
      'UnifiedDocumentDispatchSheet automatically fetches and displays all enabled channels from integration settings',
      (tester) async {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(450, 900);
    addTearDown(tester.view.resetDevicePixelRatio);
    addTearDown(tester.view.resetPhysicalSize);

    final api = _DispatchMockApiClient();

    final data = UnifiedDocumentDispatchData(
      documentType: 'sale',
      documentId: '101',
      documentNumber: 'INV-101',
      companyName: 'Tech Hub POS',
      customerName: 'Aarav Patel',
      customerPhone: '+919876500000',
      customerEmail: 'aarav@example.com',
      total: 500,
      dispatchEndpoint: '/api/v1/documents/dispatch',
      actionsPathOverride: '/api/v1/documents/sale/101/dispatch-options',
    );

    await tester.pumpWidget(
      Provider<ApiClient>.value(
        value: api,
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData.dark(),
          home: Scaffold(
            body: Builder(
              builder: (ctx) => ElevatedButton(
                onPressed: () => showUnifiedDocumentDispatchSheet(ctx, data),
                child: const Text('Open Dispatch'),
              ),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Open Dispatch'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pump();

    // Verify all 4 enabled channels (WhatsApp, SMS, Email, Webhook) are rendered
    expect(find.text('INV-101'), findsOneWidget);
    expect(find.text('Send via WhatsApp Business API'), findsOneWidget);
    expect(find.text('Send via SMS (Text Message)'), findsOneWidget);
    expect(find.text('Send via Email'), findsOneWidget);
    expect(find.text('Trigger External Webhook'), findsOneWidget);

    // Verify dispatch options endpoint was queried
    expect(api.requestedPaths, contains('/api/v1/documents/sale/101/dispatch-options'));

    // Ensure button is visible in scroll view and tap
    await tester.scrollUntilVisible(
      find.text('Send to Selected Channels'),
      150,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();

    // Tap "Send to Selected Channels"
    await tester.tap(find.text('Send to Selected Channels'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));

    // Verify dispatch request payload contains all selected channels
    expect(api.lastDispatchPayload, isNotNull);
    final channels = (api.lastDispatchPayload!['channels'] as List).cast<String>();
    expect(channels, contains('whatsapp'));
    expect(channels, contains('email'));
    expect(api.lastDispatchPayload!['phone'], '+919876500000');
    expect(api.lastDispatchPayload!['email'], 'aarav@example.com');

    debugDefaultTargetPlatformOverride = null;
  });
}

class _DispatchMockApiClient implements ApiClient {
  final List<String> requestedPaths = [];
  Map<String, dynamic>? lastDispatchPayload;

  @override
  Future<Map<String, dynamic>> requestAbsolute(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? data,
    Map<String, dynamic>? query,
  }) async {
    requestedPaths.add(path);

    if (path.contains('dispatch-options') || path.contains('channels')) {
      return {
        'success': true,
        'enabled_channels': [
          {
            'id': 'channel_whatsapp',
            'channel': 'whatsapp',
            'title': 'Send via WhatsApp Business API',
            'subtitle': 'to +919876500000',
            'icon': 'chat',
            'color': '#25D366',
            'available': true,
            'default': true,
          },
          {
            'id': 'channel_sms',
            'channel': 'sms',
            'title': 'Send via SMS (Text Message)',
            'subtitle': 'via Twilio to +919876500000',
            'icon': 'textsms',
            'color': '#38BDF8',
            'available': true,
            'default': true,
          },
          {
            'id': 'channel_email',
            'channel': 'email',
            'title': 'Send via Email',
            'subtitle': 'to aarav@example.com',
            'icon': 'email',
            'color': '#818CF8',
            'available': true,
            'default': true,
          },
          {
            'id': 'channel_webhook',
            'channel': 'webhook',
            'title': 'Trigger External Webhook',
            'subtitle': 'POST payload to configured destination',
            'icon': 'hub',
            'color': '#A855F7',
            'available': true,
            'default': false,
          },
        ],
      };
    }

    if (path.contains('dispatch')) {
      lastDispatchPayload = data;
      return {
        'success': true,
        'message': 'Dispatched successfully via selected channels.',
      };
    }

    return {'success': true};
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
