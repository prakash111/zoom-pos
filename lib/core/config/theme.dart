import 'package:flutter/material.dart';

class AppTheme {
  AppTheme._();

  /// Default brand accent — the bright cyan CTA colour from the dashboard
  /// design. A tenant's own brand colour still overrides this at runtime.
  static const Color primary = Color(0xFF23B7F0);

  /// Secondary accent — the violet used for the hero banner, the "+" create
  /// button and chat pills.
  static const Color accent = Color(0xFF6D28D9);

  static const Color surface = Color(0xFFF5F6FC);

  /// Black or white, whichever reads on [bg] — for text/icons sitting on a
  /// brand-coloured surface.
  static Color _readableOn(Color bg) =>
      ThemeData.estimateBrightnessForColor(bg) == Brightness.dark
          ? Colors.white
          : Colors.black;

  static ThemeData light(
      {Color? seedColor, Color? accentColor, Color? drawerBg}) {
    final brand = seedColor ?? primary;

    // Material 3's `fromSeed` tonally *remaps* the seed, so the tenant's
    // chosen brand colour would never actually be the one painted on buttons,
    // FABs, links or selected states. Keep `fromSeed` for the surface /
    // container ramp, but pin `primary` (and `secondary` when an accent is
    // set) to the exact colour the tenant picked so "change brand colour"
    // visibly takes effect.
    var colorScheme = ColorScheme.fromSeed(
      seedColor: brand,
      brightness: Brightness.light,
    ).copyWith(
      primary: brand,
      onPrimary: _readableOn(brand),
    );
    final secondary = accentColor ?? accent;
    colorScheme = colorScheme.copyWith(
      secondary: secondary,
      onSecondary: _readableOn(secondary),
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: surface,
      drawerTheme: DrawerThemeData(
        backgroundColor: drawerBg ?? Colors.white,
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: Colors.black87,
        elevation: 0,
        centerTitle: false,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: Colors.grey.shade300),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: colorScheme.primary,
          foregroundColor: colorScheme.onPrimary,
          padding: const EdgeInsets.symmetric(vertical: 14),
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: Colors.grey.shade200),
        ),
      ),
    );
  }
}
