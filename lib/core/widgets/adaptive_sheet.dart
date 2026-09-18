import 'dart:math' as math;
import 'package:flutter/material.dart';

import '../utils/responsive.dart';

/// Shows [builder] as a centered modal dialog on tablet/desktop/web-sized windows
/// (where a full-width bottom drawer reads as an awkward mobile-only pattern),
/// or as a rounded modal bottom sheet on mobile viewports.
///
/// Wraps wide presentations in a concrete [SizedBox] with bounded height so both
/// standard scrollables and [DraggableScrollableSheet]s render properly without
/// collapsing to zero height.
Future<T?> showAdaptiveSheet<T>(
  BuildContext context, {
  required WidgetBuilder builder,
  bool isScrollControlled = true,
  Color? backgroundColor,
  double? maxWidth,
  double? maxHeight,
  bool useRootNavigator = true,
  bool isDismissible = true,
  bool enableDrag = true,
}) {
  if (isWide(context)) {
    return showDialog<T>(
      context: context,
      useRootNavigator: useRootNavigator,
      barrierDismissible: isDismissible,
      builder: (dialogContext) {
        final screenHeight = MediaQuery.sizeOf(dialogContext).height;
        final dialogHeight = maxHeight ?? math.min(780.0, screenHeight * 0.90);
        final dialogWidth = maxWidth ?? 580.0;
        final surfaceColor = backgroundColor ??
            Theme.of(dialogContext).colorScheme.surface;

        return Dialog(
          backgroundColor: surfaceColor,
          surfaceTintColor: Colors.transparent,
          clipBehavior: Clip.antiAlias,
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          child: SizedBox(
            width: dialogWidth,
            height: dialogHeight,
            child: builder(dialogContext),
          ),
        );
      },
    );
  }

  return showModalBottomSheet<T>(
    context: context,
    useRootNavigator: useRootNavigator,
    isScrollControlled: isScrollControlled,
    isDismissible: isDismissible,
    enableDrag: enableDrag,
    useSafeArea: true,
    backgroundColor: backgroundColor ?? Theme.of(context).colorScheme.surface,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
    ),
    builder: builder,
  );
}
