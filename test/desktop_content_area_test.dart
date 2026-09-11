import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/widgets/responsive/desktop_content_area.dart';

const _contentKey = Key('content');

void main() {
  Future<void> setSize(WidgetTester tester, Size size) async {
    tester.view.physicalSize = size;
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
  }

  group('DesktopContentArea', () {
    testWidgets(
        'below the desktop breakpoint, the child fills the width untouched (mobile unchanged)',
        (tester) async {
      await setSize(tester, const Size(390, 800));
      await tester.pumpWidget(const MaterialApp(
        home: Scaffold(
          body: DesktopContentArea(child: SizedBox.expand(key: _contentKey)),
        ),
      ));

      final size = tester.getSize(find.byKey(_contentKey));
      expect(size.width, 390);
    });

    testWidgets('at desktop width, content is centered and capped at maxWidth',
        (tester) async {
      await setSize(tester, const Size(1920, 1080));
      await tester.pumpWidget(const MaterialApp(
        home: Scaffold(
          body: DesktopContentArea(
            maxWidth: 1400,
            padding: EdgeInsets.zero,
            child: SizedBox.expand(key: _contentKey),
          ),
        ),
      ));

      final size = tester.getSize(find.byKey(_contentKey));
      expect(size.width, 1400);

      // Centered: equal empty space on both sides of the capped content.
      final topLeft = tester.getTopLeft(find.byKey(_contentKey));
      expect(topLeft.dx, closeTo((1920 - 1400) / 2, 0.5));
    });

    testWidgets(
        'a 1024x768 desktop window (just past the breakpoint) still centers and caps',
        (tester) async {
      await setSize(tester, const Size(1024, 768));
      await tester.pumpWidget(const MaterialApp(
        home: Scaffold(
          body: DesktopContentArea(
            maxWidth: 900,
            padding: EdgeInsets.zero,
            child: SizedBox.expand(key: _contentKey),
          ),
        ),
      ));

      final size = tester.getSize(find.byKey(_contentKey));
      expect(size.width, 900);
    });
  });

  group('DesktopBoundedField', () {
    testWidgets(
        'below the desktop breakpoint, the field stretches to fill its parent (mobile unchanged)',
        (tester) async {
      await setSize(tester, const Size(390, 800));
      await tester.pumpWidget(const MaterialApp(
        home: Scaffold(
          body: DesktopBoundedField(
              child: SizedBox(
                  key: _contentKey, width: double.infinity, height: 48)),
        ),
      ));

      final size = tester.getSize(find.byKey(_contentKey));
      expect(size.width, 390);
    });

    testWidgets(
        'at desktop width, the field is capped and left-aligned, not stretched',
        (tester) async {
      await setSize(tester, const Size(1920, 1080));
      await tester.pumpWidget(const MaterialApp(
        home: Scaffold(
          body: DesktopBoundedField(
              maxWidth: 420,
              child: SizedBox(
                  key: _contentKey, width: double.infinity, height: 48)),
        ),
      ));

      final size = tester.getSize(find.byKey(_contentKey));
      expect(size.width, 420);
      expect(tester.getTopLeft(find.byKey(_contentKey)).dx, 0);
    });
  });
}
