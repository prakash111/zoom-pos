import 'package:flutter/material.dart';

class AppTheme {
  AppTheme._();

  /// Default brand colour — the lime green from the inventory dashboard
  /// design. A tenant's own brand colour still overrides this at runtime.
  static const Color primary = Color(0xFF7CC518);

  /// Secondary accent.
  static const Color accent = Color(0xFF16A34A);

  static const Color surface = Color(0xFFF6F7F9);

  /// Scaffold / card grounds for the user-selectable dark theme.
  static const Color darkSurface = Color(0xFF0F1115);
  static const Color darkCard = Color(0xFF1A1D23);

  /// Black or white, whichever reads on [bg] — for text/icons sitting on a
  /// brand-coloured surface.
  static Color _readableOn(Color bg) =>
      ThemeData.estimateBrightnessForColor(bg) == Brightness.dark
          ? Colors.white
          : Colors.black;

  /// Builds a [ColorScheme] for [brightness] with [primary]/[secondary] pinned
  /// to the exact tenant-chosen colours on top of the `fromSeed` tonal ramp.
  static ColorScheme _scheme({
    required Color brand,
    required Color secondary,
    required Brightness brightness,
  }) {
    return ColorScheme.fromSeed(seedColor: brand, brightness: brightness)
        .copyWith(
      primary: brand,
      onPrimary: _readableOn(brand),
      secondary: secondary,
      onSecondary: _readableOn(secondary),
    );
  }

  static ThemeData light(
      {Color? seedColor, Color? accentColor, Color? drawerBg}) {
    final brand = seedColor ?? primary;

    // Material 3's `fromSeed` tonally *remaps* the seed, so the tenant's
    // chosen brand colour would never actually be the one painted on buttons,
    // FABs, links or selected states. Keep `fromSeed` for the surface /
    // container ramp, but pin `primary` (and `secondary` when an accent is
    // set) to the exact colour the tenant picked so "change brand colour"
    // visibly takes effect.
    final colorScheme = _scheme(
      brand: brand,
      secondary: accentColor ?? accent,
      brightness: Brightness.light,
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

  /// Dark counterpart of [light] for the user-selectable "Dark" theme mode.
  /// The tenant brand [seedColor]/[accentColor] still drive `primary`/
  /// `secondary`; only the surfaces flip to dark.
  static ThemeData dark(
      {Color? seedColor, Color? accentColor, Color? drawerBg}) {
    final brand = seedColor ?? primary;
    final colorScheme = _scheme(
      brand: brand,
      secondary: accentColor ?? accent,
      brightness: Brightness.dark,
    ).copyWith(surface: darkCard);

    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: darkSurface,
      drawerTheme: DrawerThemeData(
        backgroundColor: drawerBg ?? darkCard,
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: darkCard,
        foregroundColor: Colors.white,
        elevation: 0,
        centerTitle: false,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: darkCard,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: Colors.white24),
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
        color: darkCard,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: Colors.white12),
        ),
      ),
    );
  }
}
