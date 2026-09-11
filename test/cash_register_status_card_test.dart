import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/features/cash_register/widgets/cash_register_status_card.dart';

void main() {
  Widget host(Brightness brightness) {
    return MaterialApp(
      theme: ThemeData(brightness: brightness),
      home: const Scaffold(
        body: CashRegisterStatusCard(
          terminalId: 'Main POS',
          openingFloat: '\$100.00',
          salesThisShift: '\$40.00',
          cashIn: '\$5.00',
          cashOut: '\$2.00',
          expectedCash: '\$143.00',
        ),
      ),
    );
  }

  Color? textColorOf(WidgetTester tester, String text) {
    return tester.widget<Text>(find.text(text)).style?.color;
  }

  double luminance(Color c) => c.computeLuminance();

  testWidgets(
      'dark mode: card fill is dark and every label/value color has strong contrast against it',
      (tester) async {
    await tester.pumpWidget(host(Brightness.dark));

    final card = tester.widget<Card>(find.byType(Card));
    final cardBg = card.color!;
    expect(luminance(cardBg), lessThan(0.2),
        reason: 'card must be a dark fill in dark mode, not pale green/white');

    for (final label in [
      'Opening float',
      'Sales this shift',
      'Cash in',
      'Cash out',
      'Expected cash'
    ]) {
      final color = textColorOf(tester, label);
      expect(color, isNotNull,
          reason:
              '$label must have an explicit color, not inherit ambient text color');
      expect((luminance(color!) - luminance(cardBg)).abs(), greaterThan(0.3),
          reason: '$label must contrast against the dark card background');
    }

    for (final value in [
      '\$100.00',
      '\$40.00',
      '\$5.00',
      '\$2.00',
      '\$143.00'
    ]) {
      final color = textColorOf(tester, value);
      expect(color, isNotNull);
      expect((luminance(color!) - luminance(cardBg)).abs(), greaterThan(0.3),
          reason: '$value must contrast against the dark card background');
    }

    // The "Open" badge text/icon must be a bright, legible emerald in dark
    // mode, not the plain Colors.green used for light mode.
    final badgeText = tester.widget<Text>(find.textContaining('— Open'));
    expect(badgeText.style?.color, const Color(0xFF34D399));
  });

  testWidgets(
      'light mode: card keeps its pale-green look with dark, legible text',
      (tester) async {
    await tester.pumpWidget(host(Brightness.light));

    final card = tester.widget<Card>(find.byType(Card));
    expect(luminance(card.color!), greaterThan(0.8));

    final valueColor = textColorOf(tester, '\$100.00');
    expect(valueColor, isNotNull);
    expect(luminance(valueColor!), lessThan(0.4),
        reason: 'value text must stay dark against the light card');
  });

  testWidgets('all five metric rows and the terminal/open badge render',
      (tester) async {
    await tester.pumpWidget(host(Brightness.dark));

    expect(find.textContaining('Main POS'), findsOneWidget);
    expect(find.text('Opening float'), findsOneWidget);
    expect(find.text('Sales this shift'), findsOneWidget);
    expect(find.text('Cash in'), findsOneWidget);
    expect(find.text('Cash out'), findsOneWidget);
    expect(find.text('Expected cash'), findsOneWidget);
    expect(find.byIcon(Icons.check_circle), findsOneWidget);
  });
}
