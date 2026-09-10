import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../core/config/nav_dock_provider.dart';
import '../../../core/utils/responsive.dart';

/// Width the persistent navigation rail is allowed to occupy for a given
/// viewport, clamped so a narrow tablet window, a split-screen pane or a
/// rotation can never leave the rail covering the content or stranded
/// off-screen.
///
/// * `>= Breakpoints.desktop` -> a full 264dp hierarchical sidebar.
/// * tablet widths            -> an 88dp icon+label rail.
/// * never more than 40% of the viewport, never below the Material 72dp
///   minimum tap column.
double dockRailWidth(double viewportWidth) {
  final extended = viewportWidth >= Breakpoints.desktop;
  final ideal = extended ? 264.0 : 88.0;
  final capped = math.min(ideal, viewportWidth * 0.40);
  return capped.clamp(72.0, ideal).toDouble();
}

/// One horizontal slot in the dashboard body `Row` that holds the docked side
/// rail. Exactly one of the Left / Right slots is [active] at a time (per
/// [NavDockProvider.position]); the other collapses to zero width so no stale
/// rail is ever left painted on the opposite edge.
///
/// The width change is animated ([duration], default 250ms) so switching
/// Left <-> Right in App Preferences slides the rail across instead of
/// snapping, and a viewport resize / rotation re-clamps the width through the
/// same [LayoutBuilder] + [AnimatedSize] path rather than leaving the rail at a
/// width the new viewport can't hold.
class DockRailSlot extends StatelessWidget {
  const DockRailSlot({
    super.key,
    required this.side,
    required this.active,
    required this.builder,
    this.duration = const Duration(milliseconds: 250),
  }) : assert(side == NavDockPosition.left || side == NavDockPosition.right);

  /// Which edge this slot sits on — [NavDockPosition.left] or
  /// [NavDockPosition.right].
  final NavDockPosition side;

  /// Whether the rail is currently docked to [side]. When false the slot
  /// animates down to zero width.
  final bool active;

  /// Builds the rail body for the resolved (clamped) [width].
  final Widget Function(BuildContext context, double width) builder;

  final Duration duration;

  bool get _onLeft => side == NavDockPosition.left;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final width = dockRailWidth(MediaQuery.sizeOf(context).width);
        final fullHeight =
            constraints.maxHeight.isFinite ? constraints.maxHeight : 0.0;

        return AnimatedSize(
          duration: duration,
          curve: Curves.easeOutCubic,
          alignment: _onLeft ? Alignment.centerLeft : Alignment.centerRight,
          clipBehavior: Clip.hardEdge,
          child: !active
              ? const SizedBox(width: 0, height: 0)
              : ConstrainedBox(
                  constraints: BoxConstraints(minHeight: fullHeight),
                  child: SizedBox(
                    width: width,
                    // Keep the rail clear of a notch / rounded corner on the
                    // docked edge in landscape, without padding the opposite
                    // side (which belongs to the content).
                    child: SafeArea(
                      left: _onLeft,
                      right: !_onLeft,
                      top: false,
                      bottom: false,
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: _onLeft
                            ? [
                                Expanded(child: builder(context, width)),
                                const VerticalDivider(width: 1),
                              ]
                            : [
                                const VerticalDivider(width: 1),
                                Expanded(child: builder(context, width)),
                              ],
                      ),
                    ),
                  ),
                ),
        );
      },
    );
  }
}
