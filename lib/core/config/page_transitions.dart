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

/// Smooth horizontal slide (new page in from the right, old page parallaxes
/// left slightly), with the platform back-swipe still handled by the route.
class _SlidePageTransitionsBuilder extends PageTransitionsBuilder {
  const _SlidePageTransitionsBuilder();

  @override
  Duration get transitionDuration => const Duration(milliseconds: 260);

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    const curve = Curves.easeOutCubic;
    final enter = Tween<Offset>(
      begin: const Offset(0.06, 0),
      end: Offset.zero,
    ).animate(CurvedAnimation(parent: animation, curve: curve));
    final exit = Tween<Offset>(
      begin: Offset.zero,
      end: const Offset(-0.04, 0),
    ).animate(CurvedAnimation(parent: secondaryAnimation, curve: curve));
    return SlideTransition(
      position: exit,
      child: SlideTransition(
        position: enter,
        child: FadeTransition(opacity: animation, child: child),
      ),
    );
  }
}

/// Material-3-style fade-through: outgoing page fades + scales down a touch,
/// incoming page fades + scales up into place.
class _FadeThroughPageTransitionsBuilder extends PageTransitionsBuilder {
  const _FadeThroughPageTransitionsBuilder();

  @override
  Duration get transitionDuration => const Duration(milliseconds: 280);

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    final fade = CurvedAnimation(
      parent: animation,
      curve: const Interval(0.35, 1, curve: Curves.easeOut),
      reverseCurve: const Interval(0.0, 0.3, curve: Curves.easeIn),
    );
    final scale = Tween<double>(begin: 0.97, end: 1).animate(
      CurvedAnimation(parent: animation, curve: Curves.easeOutCubic),
    );
    return FadeTransition(
      opacity: fade,
      child: ScaleTransition(scale: scale, child: child),
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
