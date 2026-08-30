import 'package:flutter/material.dart';

import '../utils/responsive.dart';

/// Drop-in replacement for [showModalBottomSheet] that instead shows
/// [builder] as a centered dialog on tablet/desktop/web-sized windows, where
/// a full-width bottom sheet reads as a mobile-only pattern. Only suitable
/// for sheets whose content sizes itself normally (a `Column`/`ListView` of
/// fixed or scrollable height) — a sheet built around `DraggableScrollableSheet`
/// expects the full viewport height and should keep using
/// [showModalBottomSheet] directly.
Future<T?> showAdaptiveSheet<T>(
  BuildContext context, {
  required WidgetBuilder builder,
  bool isScrollControlled = false,
}) {
  if (isWide(context)) {
    return showDialog<T>(
      context: context,
      builder: (dialogContext) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 480),
          child: builder(dialogContext),
        ),
      ),
    );
  }

  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: isScrollControlled,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
    builder: builder,
  );
}
