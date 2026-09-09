import 'package:flutter/material.dart';

/// Wraps [child] in a subtle "press depression" — it scales down slightly
/// while the pointer is held — on top of the normal Material ink ripple.
/// A lightweight micro-interaction for buttons, links and nav items.
class TappableScale extends StatefulWidget {
  const TappableScale({
    super.key,
    required this.child,
    this.onTap,
    this.pressedScale = 0.97,
    this.borderRadius,
    this.enableRipple = true,
  });

  final Widget child;
  final VoidCallback? onTap;
  final double pressedScale;
  final BorderRadius? borderRadius;
  final bool enableRipple;

  @override
  State<TappableScale> createState() => _TappableScaleState();
}

class _TappableScaleState extends State<TappableScale> {
  bool _down = false;

  void _set(bool value) {
    if (_down != value) setState(() => _down = value);
  }

  @override
  Widget build(BuildContext context) {
    Widget content = AnimatedScale(
      scale: _down ? widget.pressedScale : 1,
      duration: const Duration(milliseconds: 90),
      curve: Curves.easeOut,
      child: widget.child,
    );

    if (widget.enableRipple) {
      content = Material(
        type: MaterialType.transparency,
        child: InkWell(
          onTap: widget.onTap,
          onTapDown: (_) => _set(true),
          onTapUp: (_) => _set(false),
          onTapCancel: () => _set(false),
          borderRadius: widget.borderRadius,
          child: content,
        ),
      );
    } else {
      content = GestureDetector(
        onTap: widget.onTap,
        onTapDown: (_) => _set(true),
        onTapUp: (_) => _set(false),
        onTapCancel: () => _set(false),
        behavior: HitTestBehavior.opaque,
        child: content,
      );
    }

    return content;
  }
}
