import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/config/theme.dart';

void main() {
  test('brand seed colour is used verbatim as colorScheme.primary', () {
    const brand = Color(0xFF16A34A); // a green the tenant picked
    final theme = AppTheme.light(seedColor: brand);

    // Material 3 fromSeed would tonally remap this; we pin it instead so the
    // chosen brand colour is the one buttons / FABs / links actually paint.
    expect(theme.colorScheme.primary, brand);
    expect(
        theme.elevatedButtonTheme.style?.backgroundColor
            ?.resolve(<WidgetState>{}),
        brand);
    // onPrimary stays readable on a mid-tone green.
    expect(theme.colorScheme.onPrimary, Colors.white);
  });

  test('accent colour becomes colorScheme.secondary', () {
    const brand = Color(0xFF2563EB);
    const accent = Color(0xFFD7F24E); // pale lime -> needs dark onSecondary
    final theme = AppTheme.light(seedColor: brand, accentColor: accent);

    expect(theme.colorScheme.primary, brand);
    expect(theme.colorScheme.secondary, accent);
    expect(theme.colorScheme.onSecondary, Colors.black);
  });

  test('falls back to the default brand when no seed is given', () {
    expect(AppTheme.light().colorScheme.primary, AppTheme.primary);
  });
}
