import 'package:flutter/material.dart';

import '../../utils/responsive.dart';

/// Centers [child] with a sensible maximum width once the window crosses the
/// desktop breakpoint ([Breakpoints.desktop]), instead of letting a mobile
/// layout stretch edge-to-edge across a wide Windows window. Below that
/// breakpoint this is a complete no-op — mobile and tablet render [child]
/// untouched, so wrapping an existing screen's body in this widget cannot
/// change its phone/tablet behaviour.
class DesktopContentArea extends StatelessWidget {
  const DesktopContentArea({
    super.key,
    required this.child,
    this.maxWidth = 1400,
    this.padding = const EdgeInsets.symmetric(horizontal: 32),
  });

  final Widget child;

  /// Content stays readable and grouped rather than sprawling across a
  /// 1920px window; 1400 sits in the middle of the brief's 1400–1500 range.
  final double maxWidth;

  /// Extra breathing room between the capped content and the window edge on
  /// desktop widths that are still narrower than [maxWidth] (e.g. 1280px).
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.sizeOf(context).width < Breakpoints.desktop) {
      return child;
    }
    return Center(
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxWidth),
        child: Padding(padding: padding, child: child),
      ),
    );
  }
}

/// Caps a search field (or similar single control) at a sane desktop width
/// instead of letting it stretch across the whole content area — the
/// "[very wide search field]" problem called out for list screens. A no-op
/// below the desktop breakpoint.
class DesktopBoundedField extends StatelessWidget {
  const DesktopBoundedField(
      {super.key, required this.child, this.maxWidth = 420});

  final Widget child;
  final double maxWidth;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.sizeOf(context).width < Breakpoints.desktop) return child;
    return Align(
      alignment: Alignment.centerLeft,
      child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: maxWidth), child: child),
    );
  }
}
