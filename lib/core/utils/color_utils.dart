import 'package:flutter/material.dart';

/// Parses a `#RRGGBB` (or `#AARRGGBB`) hex string into a [Color]. Returns
/// null for anything that isn't a well-formed hex color, so callers can fall
/// back to a default instead of crashing on a malformed server value or
/// partially-typed input.
Color? parseHexColor(String hex) {
  var value = hex.trim();
  if (value.startsWith('#')) value = value.substring(1);
  if (value.length == 6) value = 'FF$value';
  if (value.length != 8) return null;

  final parsed = int.tryParse(value, radix: 16);
  return parsed == null ? null : Color(parsed);
}

/// Formats a [Color] as `#RRGGBB` (alpha channel dropped — the backend field
/// only stores an opaque brand color).
String toHexColor(Color color) {
  return '#${color.toARGB32().toRadixString(16).padLeft(8, '0').substring(2).toUpperCase()}';
}
