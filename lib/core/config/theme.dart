import 'package:flutter/material.dart';

import 'page_transitions.dart';

class AppTheme {
  AppTheme._();

  /// Default brand colour — the lime green from the inventory dashboard
  /// design. A tenant's own brand colour still overrides this at runtime.
  static const Color primary = Color(0xFF7CC518);

  /// Secondary accent.
  static const Color accent = Color(0xFF16A34A);

  // --- Light enterprise-desktop palette (slate) -----------------------------
  static const Color lightBg = Color(0xFFF8FAFC); // slate-50
  static const Color lightCard = Color(0xFFFFFFFF);
  static const Color lightBorder = Color(0xFFE2E8F0); // slate-200
  static const Color lightHeading = Color(0xFF0F172A); // slate-900
  static const Color lightBody = Color(0xFF334155); // slate-700
  static const Color lightMuted = Color(0xFF64748B); // slate-500

  /// Kept for backwards compatibility with older call sites.
  static const Color surface = lightBg;

  // --- Dark enterprise-desktop palette -------------------------------------
  static const Color darkBg = Color(0xFF0F172A); // slate-900
  static const Color darkCard = Color(0xFF1E293B); // slate-800
  static const Color darkBorder = Color(0xFF334155); // slate-700
  static const Color darkHeading = Color(0xFFF8FAFC); // slate-50
  static const Color darkBody = Color(0xFFCBD5E1); // slate-300
  static const Color darkMuted = Color(0xFF94A3B8); // slate-400

  static const Color darkSurface = darkBg;

  /// Black or white, whichever reads on [bg] — for text/icons sitting on a
  /// brand-coloured surface.
  static Color _readableOn(Color bg) =>
      ThemeData.estimateBrightnessForColor(bg) == Brightness.dark
          ? Colors.white
          : Colors.black;

  /// Only honour a server-/user-configured surface override (`drawer_bg`,
  /// canvas) when it actually reads on the active [brightness]. `drawer_bg` is
  /// a single mode-agnostic value, so without this guard a pale cream drawer
  /// colour would paint a light drawer under the dark scaffold (and vice
  /// versa). `null` falls back to the mode's own surface.
  static Color? _overrideFor(Color? c, Brightness brightness) {
    if (c == null) return null;
    return ThemeData.estimateBrightnessForColor(c) == brightness ? c : null;
  }

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

  static ThemeData light({
    Color? seedColor,
    Color? accentColor,
    Color? drawerBg,
    Color? canvasColor,
    AppPageTransition? pageTransitions,
  }) {
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
    ).copyWith(
      surface: lightCard,
      onSurface: lightHeading,
      onSurfaceVariant: lightMuted,
      outlineVariant: lightBorder,
    );

    return _build(
      colorScheme: colorScheme,
      scaffoldBg: _overrideFor(canvasColor, Brightness.light) ?? lightBg,
      cardColor: lightCard,
      borderColor: lightBorder,
      appBarBg: lightCard,
      appBarFg: lightHeading,
      inputFill: lightCard,
      drawerBg: _overrideFor(drawerBg, Brightness.light) ?? lightCard,
      bodyColor: lightBody,
      headingColor: lightHeading,
      pageTransitions: pageTransitions,
    );
  }

  /// Dark counterpart of [light] for the user-selectable "Dark" theme mode.
  /// The tenant brand [seedColor]/[accentColor] still drive `primary`/
  /// `secondary`; only the surfaces flip to dark.
  static ThemeData dark({
    Color? seedColor,
    Color? accentColor,
    Color? drawerBg,
    Color? canvasColor,
    AppPageTransition? pageTransitions,
  }) {
    final brand = seedColor ?? primary;
    final colorScheme = _scheme(
      brand: brand,
      secondary: accentColor ?? accent,
      brightness: Brightness.dark,
    ).copyWith(
      surface: darkCard,
      onSurface: darkHeading,
      onSurfaceVariant: darkMuted,
      outlineVariant: darkBorder,
    );

    return _build(
      colorScheme: colorScheme,
      scaffoldBg: _overrideFor(canvasColor, Brightness.dark) ?? darkBg,
      cardColor: darkCard,
      borderColor: darkBorder,
      appBarBg: darkCard,
      appBarFg: darkHeading,
      inputFill: darkCard,
      drawerBg: _overrideFor(drawerBg, Brightness.dark) ?? darkCard,
      bodyColor: darkBody,
      headingColor: darkHeading,
      pageTransitions: pageTransitions,
    );
  }

  static ThemeData _build({
    required ColorScheme colorScheme,
    required Color scaffoldBg,
    required Color cardColor,
    required Color borderColor,
    required Color appBarBg,
    required Color appBarFg,
    required Color inputFill,
    required Color drawerBg,
    required Color bodyColor,
    required Color headingColor,
    AppPageTransition? pageTransitions,
  }) {
    final base = ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: scaffoldBg,
    );
    return base.copyWith(
      pageTransitionsTheme: pageTransitions?.theme,
      canvasColor: cardColor,
      // Desktop: always-visible scrollbar thumbs (no hover-to-reveal).
      scrollbarTheme: ScrollbarThemeData(
        thumbVisibility: WidgetStateProperty.all(true),
        thickness: WidgetStateProperty.all(8),
        radius: const Radius.circular(4),
        thumbColor: WidgetStateProperty.all(
            colorScheme.onSurfaceVariant.withValues(alpha: 0.45)),
      ),
      dividerColor: borderColor,
      dividerTheme:
          DividerThemeData(color: borderColor, space: 1, thickness: 1),
      drawerTheme: DrawerThemeData(backgroundColor: drawerBg),
      appBarTheme: AppBarTheme(
        backgroundColor: appBarBg,
        foregroundColor: appBarFg,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: false,
      ),
      textTheme: base.textTheme.apply(
        bodyColor: bodyColor,
        displayColor: headingColor,
      ),
      listTileTheme: ListTileThemeData(
        iconColor: colorScheme.onSurfaceVariant,
        textColor: bodyColor,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: inputFill,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: borderColor),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: borderColor),
        ),
      ),
      // Desktop-standard control metrics: >=44px tall, real horizontal
      // padding, 12px min gap between adjacent buttons enforced at call
      // sites. `EdgeInsets.symmetric(vertical:)` alone was collapsing the
      // horizontal padding to zero, jamming label text against the edges.
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: colorScheme.primary,
          foregroundColor: colorScheme.onPrimary,
          disabledBackgroundColor:
              colorScheme.onSurface.withValues(alpha: 0.12),
          disabledForegroundColor:
              colorScheme.onSurface.withValues(alpha: 0.38),
          minimumSize: const Size(0, 44),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(0, 44),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: colorScheme.primary,
          minimumSize: const Size(0, 44),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
          side: BorderSide(color: borderColor),
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: colorScheme.primary,
          minimumSize: const Size(0, 40),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
        ),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: cardColor,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: borderColor),
        ),
      ),
      popupMenuTheme: PopupMenuThemeData(
        color: cardColor,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: borderColor),
        ),
      ),
      dropdownMenuTheme: DropdownMenuThemeData(
        textStyle: TextStyle(color: headingColor, fontSize: 14),
        menuStyle: MenuStyle(
          backgroundColor: WidgetStatePropertyAll(cardColor),
          surfaceTintColor: const WidgetStatePropertyAll(Colors.transparent),
          elevation: const WidgetStatePropertyAll(8),
          shape: WidgetStatePropertyAll(
            RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
              side: BorderSide(color: borderColor),
            ),
          ),
        ),
      ),
      menuTheme: MenuThemeData(
        style: MenuStyle(
          backgroundColor: WidgetStatePropertyAll(cardColor),
          surfaceTintColor: const WidgetStatePropertyAll(Colors.transparent),
          shape: WidgetStatePropertyAll(
            RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
              side: BorderSide(color: borderColor),
            ),
          ),
        ),
      ),
      // Transient surfaces (toasts, dialogs, bottom sheets) — M3's defaults
      // pull from `inverseSurface` / a tinted `surface`, which render pale in
      // dark mode. Pin them to the mode's own container colours instead.
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: colorScheme.brightness == Brightness.dark
            ? darkBorder // slate-700 — sits above the dark scaffold/cards
            : headingColor, // slate-900 — the familiar dark toast in light mode
        contentTextStyle: TextStyle(
          color: colorScheme.brightness == Brightness.dark
              ? darkHeading
              : Colors.white,
          fontSize: 13.5,
        ),
        actionTextColor: colorScheme.brightness == Brightness.dark
            ? colorScheme.primary
            : colorScheme.secondary,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: cardColor,
        surfaceTintColor: Colors.transparent,
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: cardColor,
        surfaceTintColor: Colors.transparent,
        showDragHandle: true,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
        ),
      ),
    );
  }
}

/// Theme-aware semantic status colours (success / warning / info / danger),
/// each with a low-chroma container fill and a readable on-container tone that
/// both flip correctly between light and dark. Use these instead of hardcoded
/// `Colors.green.shade50` / `Colors.amber.shade50` fills, which leave white
/// text stranded on a pale surface in dark mode.
extension StatusPalette on ColorScheme {
  bool get _isDark => brightness == Brightness.dark;

  Color get successContainer =>
      _isDark ? const Color(0xFF14351F) : const Color(0xFFE7F6EC);
  Color get onSuccessContainer =>
      _isDark ? const Color(0xFF86EFAC) : const Color(0xFF14532D);
  Color get successAccent =>
      _isDark ? const Color(0xFF4ADE80) : const Color(0xFF16A34A);

  Color get warningContainer =>
      _isDark ? const Color(0xFF3A2E10) : const Color(0xFFFEF4E2);
  Color get onWarningContainer =>
      _isDark ? const Color(0xFFFCD34D) : const Color(0xFF854D0E);
  Color get warningAccent =>
      _isDark ? const Color(0xFFFBBF24) : const Color(0xFFD97706);

  Color get infoContainer =>
      _isDark ? const Color(0xFF152A44) : const Color(0xFFE8F1FE);
  Color get onInfoContainer =>
      _isDark ? const Color(0xFF93C5FD) : const Color(0xFF1E40AF);
  Color get infoAccent =>
      _isDark ? const Color(0xFF60A5FA) : const Color(0xFF2563EB);

  Color get dangerContainer =>
      _isDark ? const Color(0xFF3B1618) : const Color(0xFFFDECEC);
  Color get onDangerContainer =>
      _isDark ? const Color(0xFFFCA5A5) : const Color(0xFF991B1B);
  Color get dangerAccent =>
      _isDark ? const Color(0xFFF87171) : const Color(0xFFDC2626);
}
