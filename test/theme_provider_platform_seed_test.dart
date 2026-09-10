import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/core/config/theme_provider.dart';

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  test('hexToColor parses #RRGGBB / RRGGBB / #AARRGGBB and falls back safely',
      () {
    expect(ThemeProvider.hexToColor('#F95700'), const Color(0xFFF95700));
    expect(ThemeProvider.hexToColor('00A3FF'), const Color(0xFF00A3FF));
    expect(ThemeProvider.hexToColor('#8000A3FF'), const Color(0x8000A3FF));
    expect(ThemeProvider.hexToColor(null), const Color(0xFFF95700));
    expect(ThemeProvider.hexToColor('not-a-colour'), const Color(0xFFF95700));
    expect(
      ThemeProvider.hexToColor('', fallback: const Color(0xFF123456)),
      const Color(0xFF123456),
    );
  });

  test('applyPlatformSeed drives the seed until the tenant picks a colour',
      () async {
    final tp = ThemeProvider();
    await tp.load();

    var notified = 0;
    tp.addListener(() => notified++);

    // Superadmin primary flows straight through to the theme seed.
    tp.applyPlatformSeed(const Color(0xFF00A3FF));
    expect(tp.seedColor, const Color(0xFF00A3FF));
    expect(notified, 1);

    // A second Superadmin change also propagates immediately.
    tp.applyPlatformSeed(const Color(0xFFEF4444));
    expect(tp.seedColor, const Color(0xFFEF4444));
    expect(notified, 2);

    // Once the tenant sets their own brand colour, that wins…
    await tp.setColor(const Color(0xFF16A34A));
    expect(tp.seedColor, const Color(0xFF16A34A));

    // …and later Superadmin changes no longer override it (only recorded).
    tp.applyPlatformSeed(const Color(0xFF7C3AED));
    expect(tp.seedColor, const Color(0xFF16A34A));
    expect(tp.platformSeedColor, const Color(0xFF7C3AED));
  });
}
