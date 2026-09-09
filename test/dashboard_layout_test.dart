import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/widgets/dashboard_kit.dart';
import 'package:zoom_pos_mobile/core/widgets/dashboard_shell.dart';
import 'package:zoom_pos_mobile/features/dashboard/screens/dashboard_layout_screen.dart';

void main() {
  Widget host(Widget child) => MaterialApp(home: child);

  testWidgets('POWRSALE shell: sidebar brand + nav switches the content page',
      (tester) async {
    tester.view.physicalSize = const Size(1500, 1000);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(host(const DashboardLayoutScreen()));
    await tester.pumpAndSettle();

    // Sidebar shows the brand and every destination.
    expect(find.text('POWRSALE'), findsOneWidget);
    for (final label in ['Overview', 'Shop', 'Transaction', 'Wallet']) {
      expect(find.text(label), findsWidgets);
    }
    // Selected-shop chip + logout.
    expect(find.text('Techshop'), findsOneWidget);
    expect(find.text('Logout'), findsOneWidget);

    // Default page is the Overview hero.
    expect(find.text('Hi, Ebenezer Ghanney'), findsOneWidget);

    // Switch to Transactions.
    await tester.tap(find.text('Transaction'));
    await tester.pumpAndSettle();
    expect(find.text('History'), findsOneWidget);
    expect(find.text('Upcoming'), findsOneWidget);
    expect(find.text('Crazyshop USA'), findsOneWidget);

    // Switch to Wallet.
    await tester.tap(find.text('Wallet').first);
    await tester.pumpAndSettle();
    expect(find.text('Available'), findsOneWidget);
    expect(find.text('\$2,595.00'), findsOneWidget);
  });

  testWidgets('right rail renders profile + messages on a wide window',
      (tester) async {
    tester.view.physicalSize = const Size(1500, 1000);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(host(const DashboardLayoutScreen()));
    await tester.pumpAndSettle();

    expect(find.text('My profile'), findsOneWidget);
    expect(find.text('Ebenezer Ghanney'), findsOneWidget);
    expect(find.text('Albert Flores'), findsOneWidget);
  });

  testWidgets('PowrButton uppercases its label and reports taps',
      (tester) async {
    var taps = 0;
    await tester.pumpWidget(host(Scaffold(
      body: Center(
        child: PowrButton(label: 'Send request', onPressed: () => taps++),
      ),
    )));

    expect(find.text('SEND REQUEST'), findsOneWidget);
    await tester.tap(find.text('SEND REQUEST'));
    expect(taps, 1);
  });

  testWidgets('PowrButton shows a spinner and is inert while busy',
      (tester) async {
    await tester.pumpWidget(host(const Scaffold(
      body: Center(
        child: PowrButton(label: 'Save', busy: true, onPressed: null),
      ),
    )));
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    expect(find.text('SAVE'), findsNothing);
  });

  testWidgets('PowrHeroBanner renders the greeting and subtitle',
      (tester) async {
    await tester.pumpWidget(host(const Scaffold(
      body: PowrHeroBanner(greeting: 'Hi, Sam', subtitle: 'Welcome back'),
    )));
    expect(find.text('Hi, Sam'), findsOneWidget);
    expect(find.text('Welcome back'), findsOneWidget);
  });
}
