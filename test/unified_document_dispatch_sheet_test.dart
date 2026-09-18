import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/api/api_exception.dart';
import 'package:zoom_pos_mobile/core/sdui/screens/dynamic_schema_page.dart';
import 'package:zoom_pos_mobile/features/repair/ticket_share_sheet.dart';

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
    expect(api.requestedPaths,
        contains('/api/v1/documents/sale/101/dispatch-options'));

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
    final channels =
        (api.lastDispatchPayload!['channels'] as List).cast<String>();
    expect(channels, contains('whatsapp'));
    expect(channels, contains('email'));
    expect(api.lastDispatchPayload!['phone'], '+919876500000');
    expect(api.lastDispatchPayload!['email'], 'aarav@example.com');

    debugDefaultTargetPlatformOverride = null;
  });

  testWidgets(
      'repair sharing omits print preview and still dispatches with bound customer data',
      (tester) async {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(450, 900);
    addTearDown(tester.view.resetDevicePixelRatio);
    addTearDown(tester.view.resetPhysicalSize);

    final api = _DispatchMockApiClient();

    final ticket = {
      'id': '99',
      'ticket_number': 'REP-99',
      'company_name': 'Zoom Fixers',
      'brand': 'Apple',
      'model': 'iPhone 15 Pro',
      'status': 'In Progress',
      'customer': {
        'name': 'Carlos Rivera',
        'phone': '+1555888999',
        'email': 'carlos@example.com',
      },
    };

    await tester.pumpWidget(
      Provider<ApiClient>.value(
        value: api,
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData.dark(),
          home: Scaffold(
            body: Builder(
              builder: (ctx) => ElevatedButton(
                onPressed: () => showRepairUnifiedDispatchSheet(ctx, ticket),
                child: const Text('Open Repair Dispatch'),
              ),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Open Repair Dispatch'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pump();

    // Verify repair ticket header & context
    expect(find.text('#REP-99'), findsOneWidget);
    expect(find.textContaining('Carlos Rivera • Apple iPhone 15 Pro'),
        findsOneWidget);

    expect(find.text('PDF Preview'), findsNothing);
    expect(find.text('Thermal Print'), findsOneWidget);

    // Verify channels are displayed
    expect(find.text('Send via WhatsApp Business API'), findsOneWidget);
    expect(find.text('Send via Email'), findsOneWidget);

    // Ensure button is visible and tap
    await tester.scrollUntilVisible(
      find.text('Send to Selected Channels'),
      150,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Send to Selected Channels'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));

    // Verify customer contact was bound and dispatched without manual entry
    expect(api.lastDispatchPayload, isNotNull);
    expect(api.lastDispatchPayload!['phone'], '+1555888999');
    expect(api.lastDispatchPayload!['email'], 'carlos@example.com');
    expect(api.lastDispatchPayload!['document_type'], 'repair');
    expect(api.lastDispatchPayload!['document_id'], '99');

    debugDefaultTargetPlatformOverride = null;
  });

  testWidgets(
      'disabled WhatsApp remains an app row alongside SMTP and SMS checkboxes',
      (tester) async {
    const message =
        'Hello prakash Kumar Singh, repair ticket #REP-2026-0016 for your samsung ZX10R has been received at Repair. Advance Paid: \$60.00. Track progress: https://saas.zoomnearby.com/portal/repair/REP-2026-0016';
    final api = _DispatchMockApiClient(options: {
      'channels': {
        'whatsapp': {
          'api_enabled': false,
          'mode': 'local_intent',
          'action': {
            'url':
                'whatsapp://send?phone=919876543210&text=${Uri.encodeComponent(message)}'
          },
        },
        'email': {
          'api_enabled': true,
          'default': true,
          'title': 'Send via Email [Cloud API]'
        },
        'sms': {
          'api_enabled': true,
          'default': true,
          'title': 'Send via SMS [Cloud API]'
        },
      },
      // The API-only subset must not suppress WhatsApp from the full map.
      'enabled_channels': [
        {'channel': 'email', 'api_enabled': true},
        {'channel': 'sms', 'api_enabled': true},
        {
          'channel': 'whatsapp',
          'api_enabled': true
        }, // stale subset loses to the full map
      ],
    });
    final launches = <MethodCall>[];
    const channel = MethodChannel('plugins.flutter.io/url_launcher');
    tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(channel,
        (call) async {
      launches.add(call);
      return true;
    });
    addTearDown(() => tester.binding.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null));
    await _openSheet(tester, api);

    expect(find.text('Thermal Print'), findsOneWidget);
    expect(find.text('PDF Preview'), findsOneWidget);
    expect(find.text('Open WhatsApp App'), findsOneWidget);
    expect(find.byType(CheckboxListTile), findsNWidgets(2));
    final whatsapp = find.byKey(const ValueKey('channel_whatsapp'));
    expect(tester.widget(whatsapp), isA<ListTile>());
    await tester
        .tap(find.descendant(of: whatsapp, matching: find.text('Open')));
    await tester.pumpAndSettle();

    final launched =
        Uri.parse((launches.single.arguments as Map)['url'] as String);
    expect(launched.scheme, 'whatsapp');
    expect(launched.queryParameters['phone'], '15551234567');
    expect(launched.queryParameters['text'], message);
    expect((launches.single.arguments as Map)['useWebView'], false);
    expect(api.lastDispatchPayload, isNull);
    expect(find.text('Dispatching...'), findsNothing);
    expect(find.text('INV-101'), findsOneWidget);

    await tester.tap(find.text('Send to Selected Channels'));
    await tester.pumpAndSettle();
    expect(api.lastDispatchPayload!['channels'], ['email', 'sms']);
    expect(api.lastDispatchPayload!['api_only'], true);
    expect(api.postCount, 1);
    expect(api.requestedPaths.last, '/api/v1/documents/dispatch');
  });

  testWidgets(
      'unconfigured integrations all stay visible and open device composers',
      (tester) async {
    final api = _DispatchMockApiClient(options: {
      'enabled_channels': [],
      'channels': {
        for (final type in ['whatsapp', 'email', 'sms'])
          type: {'api_enabled': false, 'available': false, 'default': true},
      },
    });
    final urls = <Uri>[];
    const channel = MethodChannel('plugins.flutter.io/url_launcher');
    tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(channel,
        (call) async {
      urls.add(Uri.parse((call.arguments as Map)['url'] as String));
      return true;
    });
    addTearDown(() => tester.binding.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null));
    await _openSheet(tester, api);
    expect(find.byType(CheckboxListTile), findsNothing);
    expect(find.text('Open WhatsApp App'), findsOneWidget);
    expect(find.text('Open Mail App'), findsOneWidget);
    expect(find.text('Open Messages / SMS'), findsOneWidget);
    expect(find.text('Thermal Print'), findsOneWidget);
    expect(find.text('PDF Preview'), findsOneWidget);
    expect(
        tester
            .widget<FilledButton>(
                find.widgetWithText(FilledButton, 'Send to Selected Channels'))
            .onPressed,
        isNull);

    for (final type in ['whatsapp', 'email', 'sms']) {
      await tester.tap(find.descendant(
          of: find.byKey(ValueKey('channel_$type')),
          matching: find.text('Open')));
      await tester.pumpAndSettle();
    }
    expect(urls.map((url) => url.scheme), ['whatsapp', 'mailto', 'sms']);
    expect(Uri.decodeComponent(urls[1].path), 'customer@example.com');
    expect(urls[1].queryParameters['subject'], 'sale INV-101');
    expect(urls[1].queryParameters['body'], contains('INV-101'));
    expect(urls[2].path, '+15551234567');
    expect(urls[2].queryParameters['body'], contains('INV-101'));
    expect(api.postCount, 0);
  });

  testWidgets(
      'legacy API-only responses keep missing channels as local launch rows',
      (tester) async {
    final api = _DispatchMockApiClient(options: {
      'enabled_channels': [
        {'channel': 'email', 'api_enabled': true, 'default': false}
      ],
    });
    await _openSheet(tester, api);
    expect(find.byType(CheckboxListTile), findsOneWidget);
    expect(tester.widget<CheckboxListTile>(find.byType(CheckboxListTile)).value,
        false);
    expect(find.text('Open WhatsApp App'), findsOneWidget);
    expect(find.text('Open Messages / SMS'), findsOneWidget);
  });

  testWidgets(
      'failed dispatch stays open and does not retry another endpoint or claim success',
      (tester) async {
    final api = _DispatchMockApiClient(options: {
      'enabled_channels': [
        {'channel': 'email', 'api_enabled': true, 'default': true}
      ],
    }, dispatchError: ApiException('SMTP connection failed', statusCode: 422));
    await _openSheet(tester, api);
    await tester.tap(find.text('Send to Selected Channels'));
    await tester.pumpAndSettle();
    expect(api.postCount, 1);
    expect(find.text('INV-101'), findsOneWidget);
    expect(find.text('SMTP connection failed'), findsOneWidget);
    expect(find.text('Dispatching...'), findsNothing);
  });
  testWidgets(
      'repair PDF preview uses the document schema instead of treating intake HTML as PDF',
      (tester) async {
    final api = _DispatchMockApiClient(options: {
      'channels': {
        for (final type in ['whatsapp', 'email', 'sms'])
          type: {'api_enabled': false},
      },
    });
    await _openSheet(tester, api, documentType: 'repair');
    await tester.tap(find.text('PDF Preview'));
    await tester.pumpAndSettle();
    expect(find.byType(DynamicSchemaPage), findsOneWidget);
    expect(
        tester
            .widget<DynamicSchemaPage>(find.byType(DynamicSchemaPage))
            .endpoint,
        '/api/v1/tenant/documents/repair/101/preview-modal?format=a4&preview_document=1');
    expect(api.requestedPaths.last,
        '/api/v1/tenant/documents/repair/101/preview-modal?format=a4&preview_document=1');
    expect(api.postCount, 0);
  });
}

Future<void> _openSheet(WidgetTester tester, _DispatchMockApiClient api,
    {String documentType = 'sale'}) async {
  tester.view.devicePixelRatio = 1;
  tester.view.physicalSize = const Size(450, 1100);
  addTearDown(tester.view.resetDevicePixelRatio);
  addTearDown(tester.view.resetPhysicalSize);
  await tester.pumpWidget(Provider<ApiClient>.value(
    value: api,
    child: MaterialApp(
        home: Scaffold(
            body: Builder(
                builder: (context) => ElevatedButton(
                      onPressed: () => showUnifiedDocumentDispatchSheet(
                          context,
                          UnifiedDocumentDispatchData(
                            documentType: documentType,
                            documentId: '101',
                            documentNumber: 'INV-101',
                            companyName: 'Zoom Store',
                            customerName: 'Customer',
                            customerPhone: '+15551234567',
                            customerEmail: 'customer@example.com',
                          )),
                      child: const Text('Open Dispatch'),
                    )))),
  ));
  await tester.tap(find.text('Open Dispatch'));
  await tester.pumpAndSettle();
}

class _DispatchMockApiClient implements ApiClient {
  _DispatchMockApiClient({this.options, this.dispatchError});
  final Map<String, dynamic>? options;
  final Exception? dispatchError;
  final List<String> requestedPaths = [];
  Map<String, dynamic>? lastDispatchPayload;
  int postCount = 0;

  @override
  Future<Map<String, dynamic>> requestAbsolute(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? data,
    Map<String, dynamic>? query,
  }) async {
    requestedPaths.add(path);

    if (path.contains('preview-modal')) {
      return {
        'schema': {
          'type': 'page',
          'title': 'Repair intake preview',
          'components': []
        }
      };
    }

    if (path.contains('dispatch-options') || path.contains('channels')) {
      if (options != null) return options!;
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
      postCount++;
      lastDispatchPayload = data;
      if (dispatchError != null) throw dispatchError!;
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
