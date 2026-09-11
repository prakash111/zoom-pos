import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/config/theme.dart';

void main() {
  group('AppTheme transient surfaces', () {
    test('dark theme gives toasts / dialogs / sheets dark container colours',
        () {
      final t = AppTheme.dark();

      expect(t.snackBarTheme.backgroundColor, isNotNull);
      // Not the pale M3 inverseSurface default.
      expect(
          t.snackBarTheme.backgroundColor, isNot(t.colorScheme.inverseSurface));
      expect(t.snackBarTheme.contentTextStyle?.color, AppTheme.darkHeading);

      expect(t.dialogTheme.backgroundColor, AppTheme.darkCard);
      expect(t.bottomSheetTheme.backgroundColor, AppTheme.darkCard);
    });

    test('light theme still gets an explicit snackbar theme', () {
      final t = AppTheme.light();
      expect(t.snackBarTheme.backgroundColor, AppTheme.lightHeading);
      expect(t.snackBarTheme.contentTextStyle?.color, Colors.white);
    });
  });

  group('AppTheme drawer/canvas override guard', () {
    test('a pale drawer_bg is ignored in dark mode', () {
      // e.g. the server default #FFF7ED cream.
      const cream = Color(0xFFFFF7ED);
      final dark = AppTheme.dark(drawerBg: cream, canvasColor: cream);

      expect(dark.drawerTheme.backgroundColor, AppTheme.darkCard);
      expect(dark.scaffoldBackgroundColor, AppTheme.darkBg);
    });

    test('a genuinely dark drawer_bg is still honoured in dark mode', () {
      const navy = Color(0xFF111827);
      final dark = AppTheme.dark(drawerBg: navy);
      expect(dark.drawerTheme.backgroundColor, navy);
    });

    test('a very dark override is ignored in light mode', () {
      const navy = Color(0xFF0B1220);
      final light = AppTheme.light(drawerBg: navy, canvasColor: navy);
      expect(light.drawerTheme.backgroundColor, AppTheme.lightCard);
      expect(light.scaffoldBackgroundColor, AppTheme.lightBg);
    });
  });
}
