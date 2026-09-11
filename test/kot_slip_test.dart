import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
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

  testWidgets('showKotTicketSheet renders a modal ticket with Print / Close',
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

    expect(find.text('Kitchen Order Ticket'), findsOneWidget);
    expect(find.text('KITCHEN ORDER TICKET'), findsOneWidget); // slip header
    expect(find.text('KOT-DEMO-002'), findsOneWidget);
    expect(find.text('2 x Truffle Mushroom Burger'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Print KOT'), findsOneWidget);
    expect(find.widgetWithText(OutlinedButton, 'Close'), findsOneWidget);

    // No transient snackbar for the KOT feedback.
    expect(find.byType(SnackBar), findsNothing);

    await tester.tap(find.widgetWithText(OutlinedButton, 'Close'));
    await tester.pumpAndSettle();
    expect(find.text('Kitchen Order Ticket'), findsNothing);
  });
}
