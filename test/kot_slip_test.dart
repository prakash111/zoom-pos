import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/models/restaurant_models.dart';
import 'package:zoom_pos_mobile/features/restaurant/widgets/kot_slip.dart';

KitchenTicketModel _kot(Map<String, dynamic> overrides) =>
    KitchenTicketModel.fromJson({
      'id': 'k1',
      'sale_id': 's1',
      'kot_number': 'KOT-DEMO-002',
      'table_name': 'T-04',
      'service_type': 'dine_in',
      'status': 'preparing',
      'server_name': 'Ada',
      'elapsed_minutes': 6,
      'created_at': '2026-09-10T14:05:00',
      'prep_minutes': 15,
      'kitchen_notes': 'No onions',
      'items': [
        {'name': 'Truffle Mushroom Burger', 'quantity': 2, 'price': 12.5},
        {
          'name': 'Wood-Fired Margherita Pizza',
          'quantity': 1,
          'price': 14.0,
          'spice_level': 'Medium',
          'note': 'Extra crispy',
        },
      ],
      ...overrides,
    });

void main() {
  test('slip lists every item as "<qty> x <name>" with details indented', () {
    final lines = kitchenTicketSlipLines(_kot(const {}));
    final body = lines.join('\n');

    expect(lines, contains('Table: T-04'));
    expect(lines, contains('Service: dine in'));
    expect(lines, contains('Server: Ada'));
    expect(lines, contains('2 x Truffle Mushroom Burger'));
    expect(lines, contains('1 x Wood-Fired Margherita Pizza'));
    expect(lines, contains('   - Spice: Medium'));
    expect(lines, contains('   * Extra crispy'));
    expect(lines, contains('Note: No onions'));
    expect(body, contains('Sent: 2026-09-10 14:05'));
    expect(lines, contains('Prep target: 15 min'));
  });

  test('falls back to the service type when there is no table', () {
    final lines = kitchenTicketSlipLines(
        _kot(const {'table_name': null, 'service_type': 'takeaway'}));
    expect(lines, contains('Table: takeaway'));
    expect(lines, contains('Service: takeaway'));
  });

  test('omits optional rows when the data is absent', () {
    final lines = kitchenTicketSlipLines(_kot(const {
      'server_name': null,
      'kitchen_notes': null,
      'created_at': null,
      'prep_minutes': null,
    }));
    expect(lines.any((l) => l.startsWith('Server:')), isFalse);
    expect(lines.any((l) => l.startsWith('Note:')), isFalse);
    expect(lines.any((l) => l.startsWith('Sent:')), isFalse);
    expect(lines.any((l) => l.startsWith('Prep target:')), isFalse);
    // Items still render.
    expect(lines, contains('2 x Truffle Mushroom Burger'));
  });

  testWidgets('showKotTicketSheet renders the unified dispatch sheet',
      (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: Builder(
          builder: (context) => ElevatedButton(
            onPressed: () => showKotTicketSheet(context, _kot(const {})),
            child: const Text('open'),
          ),
        ),
      ),
    ));

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.text('KOT-DEMO-002'), findsOneWidget);
    expect(find.text('Table: T-04 • Sent at 14:05'), findsOneWidget);
    expect(find.text('PDF Preview'), findsOneWidget);
    expect(find.text('Thermal Print'), findsOneWidget);
    expect(find.text('Open WhatsApp App'), findsOneWidget);
    expect(find.text('Open Mail App'), findsOneWidget);
    expect(find.text('Open Messages / SMS'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Send to Selected Channels'),
        findsOneWidget);

    // No transient snackbar for the KOT feedback.
    expect(find.byType(SnackBar), findsNothing);

    expect(
        tester
            .widget<FilledButton>(
                find.widgetWithText(FilledButton, 'Send to Selected Channels'))
            .onPressed,
        isNull);
  });

  testWidgets(
      'KOT dispatch sends all selected cloud channels through the shared API',
      (tester) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(450, 1100);
    addTearDown(tester.view.resetDevicePixelRatio);
    addTearDown(tester.view.resetPhysicalSize);
    final api = _KitchenDispatchApi();
    await tester.pumpWidget(Provider<ApiClient>.value(
      value: api,
      child: MaterialApp(
          home: Scaffold(
              body: Builder(
                  builder: (context) => ElevatedButton(
                        onPressed: () =>
                            showKotTicketSheet(context, _kot(const {})),
                        child: const Text('open'),
                      )))),
    ));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    expect(find.text('Open WhatsApp App'), findsOneWidget);
    await tester.tap(find.text('Send to Selected Channels'));
    await tester.pumpAndSettle();
    expect(api.dispatchEndpoint, '/api/v1/documents/dispatch');
    expect(api.payload!['document_type'], 'kot');
    expect(api.payload!['document_id'], 'k1');
    expect(api.payload!['channels'], ['email', 'sms']);
    expect(api.payload!['api_only'], true);
    expect(api.payload!['email'], 'kitchen@example.com');
    expect(api.payload!['phone'], '+15550001111');
  });
}

class _KitchenDispatchApi implements ApiClient {
  String? dispatchEndpoint;
  Map<String, dynamic>? payload;

  @override
  Future<Map<String, dynamic>> requestAbsolute(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? data,
    Map<String, dynamic>? query,
  }) async {
    if (method == 'GET') {
      return {
        'customer': {'phone': '+15550001111', 'email': 'kitchen@example.com'},
        'channels': {
          'whatsapp': {'api_enabled': false},
          'email': {'api_enabled': true, 'default': true},
          'sms': {'api_enabled': true, 'default': true},
        },
      };
    }
    dispatchEndpoint = path;
    payload = data;
    return {'success': true, 'message': 'Kitchen ticket sent.'};
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
