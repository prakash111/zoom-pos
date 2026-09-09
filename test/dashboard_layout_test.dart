import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/widgets/dashboard_kit.dart';
import 'package:zoom_pos_mobile/core/widgets/dashboard_shell.dart';
import 'package:zoom_pos_mobile/features/dashboard/screens/dashboard_layout_screen.dart';

void main() {
  Widget host(Widget child) => MaterialApp(home: child);

  testWidgets('green dashboard: overview title, stat cards and chart render',
      (tester) async {
    tester.view.physicalSize = const Size(1500, 1000);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(host(const DashboardLayoutScreen()));
    await tester.pumpAndSettle();

    expect(find.text('Sales Performance Overview'), findsOneWidget);
    // Sample stat cards (no ApiClient in the tree -> fallback figures).
    expect(find.text('Total Stock'), findsOneWidget);
    expect(find.text('Low Stock Items'), findsOneWidget);
    expect(find.text('Incoming Orders'), findsOneWidget);
    expect(find.text('24,847'), findsOneWidget);

    expect(find.text('Stock In and Out'), findsOneWidget);
    expect(find.text('Investigate In Details'), findsOneWidget);
    expect(find.text('Live Stock Movement'), findsOneWidget);
    expect(find.text('Stock Distribution by Category'), findsOneWidget);
  });

  testWidgets('SpStatCard shows a green up-delta and a red down-delta',
      (tester) async {
    await tester.pumpWidget(host(const Scaffold(
      body: Row(children: [
        Expanded(
          child: SpStatCard(
            icon: Icons.inventory_2_outlined,
            tint: SpTokens.blue,
            value: '24,847',
            label: 'Total Stock',
            delta: '+8.2%',
          ),
        ),
        Expanded(
          child: SpStatCard(
            icon: Icons.error_outline,
            tint: SpTokens.amber,
            value: '12',
            label: 'Low Stock Items',
            delta: '+3',
            deltaPositive: false,
          ),
        ),
      ]),
    )));

    expect(find.text('24,847'), findsOneWidget);
    expect(find.byIcon(Icons.arrow_drop_up), findsOneWidget);
    expect(find.byIcon(Icons.arrow_drop_down), findsOneWidget);
  });

  testWidgets('SpButton follows the theme primary and reports taps',
      (tester) async {
    var taps = 0;
    await tester.pumpWidget(MaterialApp(
      theme: ThemeData(
          colorScheme:
              ColorScheme.fromSeed(seedColor: const Color(0xFF7CC518))),
      home: Scaffold(
        body: Center(child: SpButton('Save', onPressed: () => taps++)),
      ),
    ));
    await tester.tap(find.text('Save'));
    expect(taps, 1);
  });

  testWidgets('DashboardShell icon rail reports selections', (tester) async {
    var picked = -1;
    await tester.pumpWidget(host(DashboardShell(
      selectedIndex: 0,
      onSelect: (i) => picked = i,
      destinations: const [
        DashNavItem('Overview', icon: Icons.grid_view_rounded),
        DashNavItem('Analytics', icon: Icons.show_chart),
        DashNavItem('Settings', icon: Icons.settings_outlined),
      ],
      child: const Text('body'),
    )));

    expect(find.text('body'), findsOneWidget);
    await tester.tap(find.byIcon(Icons.settings_outlined));
    expect(picked, 2);
  });
}
