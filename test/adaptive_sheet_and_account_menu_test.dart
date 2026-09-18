import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/widgets/adaptive_sheet.dart';

void main() {
  Widget testHost(Widget child) {
    return MaterialApp(
      home: Scaffold(
        body: Center(child: child),
      ),
    );
  }

  group('showAdaptiveSheet responsiveness', () {
    testWidgets('wide viewport presents sheet inside a bounded Dialog',
        (tester) async {
      tester.view.physicalSize = const Size(1280, 800);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      await tester.pumpWidget(testHost(
        Builder(builder: (context) {
          return ElevatedButton(
            onPressed: () {
              showAdaptiveSheet(
                context,
                builder: (_) => const Text('Adaptive Sheet Content'),
              );
            },
            child: const Text('Open Sheet'),
          );
        }),
      ));

      await tester.tap(find.text('Open Sheet'));
      await tester.pumpAndSettle();

      // On wide screens, it should be presented in a Dialog rather than a bottom sheet
      expect(find.byType(Dialog), findsOneWidget);
      expect(find.text('Adaptive Sheet Content'), findsOneWidget);

      // Verify the Dialog has bounded concrete size (width <= 580, height bounded)
      final sizedBoxes = tester.widgetList<SizedBox>(find.descendant(
        of: find.byType(Dialog),
        matching: find.byType(SizedBox),
      ));
      final hasBoundedSizedBox = sizedBoxes.any(
        (sb) => sb.width == 580.0 && sb.height != null && sb.height! > 0,
      );
      expect(hasBoundedSizedBox, isTrue);
    });

    testWidgets('mobile viewport presents sheet inside a ModalBottomSheet',
        (tester) async {
      tester.view.physicalSize = const Size(400, 800);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      await tester.pumpWidget(testHost(
        Builder(builder: (context) {
          return ElevatedButton(
            onPressed: () {
              showAdaptiveSheet(
                context,
                builder: (_) => const Text('Mobile Sheet Content'),
              );
            },
            child: const Text('Open Mobile Sheet'),
          );
        }),
      ));

      await tester.tap(find.text('Open Mobile Sheet'));
      await tester.pumpAndSettle();

      // On mobile screens, it should NOT be in a Dialog
      expect(find.byType(Dialog), findsNothing);
      expect(find.text('Mobile Sheet Content'), findsOneWidget);
      expect(find.byType(BottomSheet), findsOneWidget);
    });

    testWidgets('showInvoiceActionsSheet opens cleanly on wide viewport',
        (tester) async {
      tester.view.physicalSize = const Size(1280, 800);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      await tester.pumpWidget(testHost(
        Builder(builder: (context) {
          return ElevatedButton(
            onPressed: () {
              showAdaptiveSheet(
                context,
                builder: (_) => const Text('Invoice Actions Content'),
              );
            },
            child: const Text('Open Invoice Actions'),
          );
        }),
      ));

      await tester.tap(find.text('Open Invoice Actions'));
      await tester.pumpAndSettle();

      expect(find.byType(Dialog), findsOneWidget);
      expect(find.text('Invoice Actions Content'), findsOneWidget);
    });
  });
}
