import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/widgets/dashboard_kit.dart';
import 'package:zoom_pos_mobile/core/widgets/dashboard_shell.dart';
import 'package:zoom_pos_mobile/features/dashboard/screens/dashboard_layout_screen.dart';

void main() {
  Widget host(Widget child) => MaterialApp(home: child);

  testWidgets('DashboardShell shows the brand and switches pages via top nav',
      (tester) async {
    tester.view.physicalSize = const Size(1400, 1000);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);

    await tester.pumpWidget(host(const DashboardLayoutScreen()));

    // Brand + all three nav links render in the dark top bar.
    expect(find.text('Sales & Inventory'), findsOneWidget);
    expect(find.text('Overview'), findsWidgets);
    expect(find.text('Platform'), findsOneWidget);
    expect(find.text('Sign out'), findsOneWidget);

    // Default page is Overview.
    expect(find.text('Today\'s Sales'), findsOneWidget);

    // Navigate to Licenses.
    await tester.tap(find.text('Licenses').first);
    await tester.pumpAndSettle();
    expect(find.text('Issue a license'), findsOneWidget);
    expect(find.text('Issue license'), findsOneWidget);
    expect(find.text('Reset domain'), findsOneWidget);

    // Navigate to Platform and toggle a module card.
    await tester.tap(find.text('Platform').first);
    await tester.pumpAndSettle();
    expect(find.text('5 Active'), findsOneWidget);
    expect(find.text('Save Platform Settings'), findsOneWidget);

    await tester.tap(find.text('Pharmacy POS'));
    await tester.pumpAndSettle();
    expect(find.text('4 Active'), findsOneWidget);
  });

  testWidgets('DashCard renders an emoji header and a trailing pill',
      (tester) async {
    await tester.pumpWidget(host(
      const Scaffold(
        body: DashCard(
          emoji: '🧩',
          title: 'Module Governance',
          subtitle: 'Toggle store types',
          trailing: DashPill('5 Active'),
          child: Text('body'),
        ),
      ),
    ));

    expect(find.textContaining('Module Governance'), findsOneWidget);
    expect(find.text('5 Active'), findsOneWidget);
    expect(find.text('body'), findsOneWidget);
  });

  testWidgets('DashPrimaryButton reports taps and disables while busy',
      (tester) async {
    var taps = 0;
    await tester.pumpWidget(host(Scaffold(
      body: Center(
        child: DashPrimaryButton(
          label: 'Issue license',
          onPressed: () => taps++,
        ),
      ),
    )));

    await tester.tap(find.text('Issue license'));
    expect(taps, 1);

    await tester.pumpWidget(host(const Scaffold(
      body: Center(
        child: DashPrimaryButton(
            label: 'Issue license', busy: true, onPressed: null),
      ),
    )));
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    expect(find.text('Issue license'), findsNothing);
  });

  testWidgets('DashboardShell renders a footer strip when provided',
      (tester) async {
    await tester.pumpWidget(host(const DashboardShell(
      brand: 'Acme',
      footer: SizedBox(height: 30, child: ColoredBox(color: Color(0xFF000000))),
      child: Text('content'),
    )));
    expect(find.text('Acme'), findsOneWidget);
    expect(find.text('content'), findsOneWidget);
  });
}
