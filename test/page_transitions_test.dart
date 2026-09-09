import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/config/page_transitions.dart';
import 'package:zoom_pos_mobile/core/config/theme.dart';

void main() {
  Future<void> pushAndSettle(
      WidgetTester tester, AppPageTransition style) async {
    tester.view.physicalSize = const Size(1400, 900);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(MaterialApp(
      theme: AppTheme.light(pageTransitions: style),
      home: Builder(
        builder: (context) => Scaffold(
          body: Center(
            child: ElevatedButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => const Scaffold(body: Text('page two')),
                ),
              ),
              child: const Text('go'),
            ),
          ),
        ),
      ),
    ));
    await tester.tap(find.text('go'));
    await tester.pump(); // start the route
    await tester.pump(const Duration(milliseconds: 60)); // mid-transition
  }

  testWidgets('slide style drives a SlideTransition on the pushed route',
      (tester) async {
    await pushAndSettle(tester, AppPageTransition.slide);
    expect(find.byType(SlideTransition), findsWidgets);
    await tester.pumpAndSettle();
    expect(find.text('page two'), findsOneWidget);
  });

  testWidgets('fade style drives a FadeTransition on the pushed route',
      (tester) async {
    await pushAndSettle(tester, AppPageTransition.fade);
    expect(find.byType(FadeTransition), findsWidgets);
    await tester.pumpAndSettle();
    expect(find.text('page two'), findsOneWidget);
  });

  testWidgets('instant style completes with a zero-length transition',
      (tester) async {
    await pushAndSettle(tester, AppPageTransition.none);
    // No frames needed — the new page is already fully present.
    expect(find.text('page two'), findsOneWidget);
    await tester.pumpAndSettle();
  });

  test('every style yields a builder for the Windows desktop target', () {
    for (final style in AppPageTransition.values) {
      expect(style.theme.builders[TargetPlatform.windows], isNotNull,
          reason: '${style.name} has no Windows page-transition builder');
    }
  });
}
