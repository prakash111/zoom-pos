import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/config/nav_dock_provider.dart';
import 'package:zoom_pos_mobile/features/dashboard/widgets/dock_rail_slot.dart';

void main() {
  group('dockRailWidth clamping', () {
    test('desktop widths resolve to the 264dp sidebar', () {
      expect(dockRailWidth(1440), 264);
      expect(dockRailWidth(1100), 264);
      expect(dockRailWidth(1024), 264);
    });

    test('tablet widths resolve to the 88dp compact rail', () {
      expect(dockRailWidth(900), 88);
      expect(dockRailWidth(600), 88);
    });

    test('never eats more than 40% of a narrow viewport', () {
      // 200 * 0.40 = 80, which is between the 72dp floor and the 88dp ideal.
      expect(dockRailWidth(200), 80);
    });

    test('never collapses below the 72dp Material minimum', () {
      expect(dockRailWidth(150), 72); // 150 * 0.40 = 60 -> floored to 72
      expect(dockRailWidth(50), 72);
    });
  });

  void sizeView(WidgetTester tester, Size size) {
    tester.view.physicalSize = size;
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
  }

  Widget shell({required NavDockPosition dock}) => MaterialApp(
        home: Scaffold(
          body: Row(
            children: [
              DockRailSlot(
                side: NavDockPosition.left,
                active: dock == NavDockPosition.left,
                builder: (_, width) => Text('rail:$width'),
              ),
              const Expanded(child: SizedBox()),
              DockRailSlot(
                side: NavDockPosition.right,
                active: dock == NavDockPosition.right,
                builder: (_, width) => Text('rail:$width'),
              ),
            ],
          ),
        ),
      );

  testWidgets('active slot is exactly the clamped width and shows the rail',
      (tester) async {
    sizeView(tester, const Size(1280, 800));

    await tester.pumpWidget(shell(dock: NavDockPosition.left));
    await tester.pumpAndSettle();

    expect(find.text('rail:264.0'), findsOneWidget);
    expect(find.byType(VerticalDivider), findsOneWidget);

    final leftSlot = tester.getSize(find.byType(DockRailSlot).first);
    expect(leftSlot.width, 264);
  });

  testWidgets('inactive slot collapses to zero width and never builds the rail',
      (tester) async {
    sizeView(tester, const Size(1280, 800));

    await tester.pumpWidget(shell(dock: NavDockPosition.left));
    await tester.pumpAndSettle();

    // Only the left rail exists; the right slot is collapsed.
    expect(find.text('rail:264.0'), findsOneWidget);
    expect(tester.getSize(find.byType(DockRailSlot).last).width, 0);
  });

  testWidgets('re-clamps when the viewport shrinks from desktop to tablet',
      (tester) async {
    sizeView(tester, const Size(1280, 800));
    await tester.pumpWidget(shell(dock: NavDockPosition.left));
    await tester.pumpAndSettle();
    expect(find.text('rail:264.0'), findsOneWidget);

    tester.view.physicalSize = const Size(720, 800);
    await tester.pumpWidget(shell(dock: NavDockPosition.left));
    await tester.pumpAndSettle();
    expect(find.text('rail:88.0'), findsOneWidget);
    expect(tester.getSize(find.byType(DockRailSlot).first).width, 88);
  });

  testWidgets('toggling Left -> Right moves the rail across with no stale slot',
      (tester) async {
    sizeView(tester, const Size(1280, 800));

    await tester.pumpWidget(shell(dock: NavDockPosition.left));
    await tester.pumpAndSettle();
    expect(find.text('rail:264.0'), findsOneWidget);
    expect(tester.getTopLeft(find.text('rail:264.0')).dx, lessThan(120));

    await tester.pumpWidget(shell(dock: NavDockPosition.right));
    await tester.pumpAndSettle();
    // Exactly one rail — the left slot did not keep a stale copy.
    expect(find.text('rail:264.0'), findsOneWidget);
    expect(tester.getTopLeft(find.text('rail:264.0')).dx, greaterThan(1000));
  });
}
