import 'package:flutter/material.dart';

/// The page-move animation the user picked in Settings ▸ Appearance. Applied
/// app-wide through [ThemeData.pageTransitionsTheme], so every
/// `Navigator.push(MaterialPageRoute(...))` in the app honours it without
/// touching individual call sites.
enum AppPageTransition {
  slide,
  fade,
  zoom,
  none;

  static AppPageTransition fromName(String? name) => AppPageTransition.values
      .firstWhere((t) => t.name == name, orElse: () => AppPageTransition.slide);

  String get label => switch (this) {
        AppPageTransition.slide => 'Slide',
        AppPageTransition.fade => 'Fade through',
        AppPageTransition.zoom => 'Zoom',
        AppPageTransition.none => 'Instant',
      };

  String get description => switch (this) {
        AppPageTransition.slide => 'Horizontal slide, like a desktop app',
        AppPageTransition.fade => 'Material 3 cross-fade',
        AppPageTransition.zoom => 'Subtle scale-in',
        AppPageTransition.none => 'Zero latency for fast POS use',
      };

  IconData get icon => switch (this) {
        AppPageTransition.slide => Icons.swipe_left_alt_outlined,
        AppPageTransition.fade => Icons.gradient_outlined,
        AppPageTransition.zoom => Icons.zoom_out_map_outlined,
        AppPageTransition.none => Icons.bolt_outlined,
      };

  /// A [PageTransitionsTheme] that renders this style for every platform.
  PageTransitionsTheme get theme {
    final PageTransitionsBuilder builder = switch (this) {
      AppPageTransition.slide => const _SlidePageTransitionsBuilder(),
      AppPageTransition.fade => const _FadeThroughPageTransitionsBuilder(),
      AppPageTransition.zoom => const ZoomPageTransitionsBuilder(),
      AppPageTransition.none => const _NoPageTransitionsBuilder(),
    };
    return PageTransitionsTheme(
      builders: {
        for (final platform in TargetPlatform.values) platform: builder,
      },
    );
  }
}

/// A full-width horizontal slide: the new page comes all the way in from the
/// right while the page underneath parallaxes left and dims — the obvious
/// "next screen" motion from web / desktop apps.
class _SlidePageTransitionsBuilder extends PageTransitionsBuilder {
  const _SlidePageTransitionsBuilder();

  @override
  Duration get transitionDuration => const Duration(milliseconds: 320);

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    final enter = Tween<Offset>(
      begin: const Offset(1, 0),
      end: Offset.zero,
    ).animate(CurvedAnimation(
      parent: animation,
      curve: Curves.easeOutCubic,
      reverseCurve: Curves.easeInCubic,
    ));
    final leave = Tween<Offset>(
      begin: Offset.zero,
      end: const Offset(-0.28, 0),
    ).animate(CurvedAnimation(
      parent: secondaryAnimation,
      curve: Curves.easeOutCubic,
      reverseCurve: Curves.easeInCubic,
    ));
    return SlideTransition(
      position: leave,
      child: FadeTransition(
        opacity: Tween<double>(begin: 1, end: 0.6).animate(secondaryAnimation),
        child: SlideTransition(position: enter, child: child),
      ),
    );
  }
}

/// Material-style fade-through: the incoming page cross-fades from 0 → 1 and
/// scales up from 92% into place. No delayed interval, so the motion is
/// visible from the first frame.
class _FadeThroughPageTransitionsBuilder extends PageTransitionsBuilder {
  const _FadeThroughPageTransitionsBuilder();

  @override
  Duration get transitionDuration => const Duration(milliseconds: 300);

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    final curved = CurvedAnimation(
      parent: animation,
      curve: Curves.easeOut,
      reverseCurve: Curves.easeIn,
    );
    return FadeTransition(
      opacity: curved,
      child: ScaleTransition(
        scale: Tween<double>(begin: 0.92, end: 1).animate(curved),
        child: child,
      ),
    );
  }
}

/// No animation at all — the new page just appears. Best for rapid POS work.
class _NoPageTransitionsBuilder extends PageTransitionsBuilder {
  const _NoPageTransitionsBuilder();

  @override
  Duration get transitionDuration => Duration.zero;

  @override
  Duration get reverseTransitionDuration => Duration.zero;

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    return child;
  }
}
