import 'package:flutter/material.dart';

import '../core/sdui/sdui_icon_registry.dart';

/// Resolves an icon name, code point, or payload from SDUI or navigation schemas to Flutter [IconData].
IconData getSduiIcon(dynamic iconName) {
  if (iconName == null) return Icons.grid_view_rounded;
  return SduiIconRegistry.resolve(iconName, fallback: Icons.grid_view_rounded);
}
