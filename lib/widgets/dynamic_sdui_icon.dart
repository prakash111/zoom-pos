// ignore_for_file: non_const_argument_for_const_parameter
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../core/sdui/sdui_icon_registry.dart';

/// Universal Dynamic SDUI Icon Widget.
///
/// Resolves and renders icons dynamically from:
/// 1. Dynamic Map Payload ({type, code_point, font_family, url})
/// 2. Remote CDN / Network SVG or Image URLs
/// 3. Material Design code points (integer or '0x...' hex string)
/// 4. Direct Flutter [IconData]
/// 5. Standard icon string keys via [SduiIconRegistry]
class DynamicSduiIcon extends StatelessWidget {
  final dynamic iconData;
  final Color color;
  final double size;

  const DynamicSduiIcon({
    super.key,
    required this.iconData,
    this.color = const Color(0xFF94A3B8),
    this.size = 20.0,
  });

  @override
  Widget build(BuildContext context) {
    if (iconData == null) {
      return Icon(Icons.circle, size: size * 0.4, color: color);
    }

    // 0. Direct IconData
    if (iconData is IconData) {
      return Icon(iconData as IconData, size: size, color: color);
    }

    // 1. Dynamic Map Payload (code_point or remote URL)
    if (iconData is Map) {
      final map = Map<String, dynamic>.from(iconData as Map);

      // Option A: Remote SVG / Image Asset URL
      final dynamic rawUrl = map['url'] ?? map['icon_url'];
      final String? iconUrl = rawUrl?.toString();
      if (iconUrl != null && iconUrl.trim().isNotEmpty) {
        final trimmedUrl = iconUrl.trim();
        if (trimmedUrl.endsWith('.svg')) {
          return SvgPicture.network(
            trimmedUrl,
            width: size,
            height: size,
            colorFilter: ColorFilter.mode(color, BlendMode.srcIn),
            placeholderBuilder: (_) => SizedBox(width: size, height: size),
          );
        }
        return Image.network(
          trimmedUrl,
          width: size,
          height: size,
          color: color,
          errorBuilder: (_, __, ___) => _fallbackIcon(map),
        );
      }

      // Option B: Dynamic Material CodePoint from Backend
      final dynamic codePointVal = map['code_point'] ?? map['icon_code'];
      if (codePointVal != null) {
        final int? parsedPoint = codePointVal is int
            ? codePointVal
            : (codePointVal is String && codePointVal.startsWith('0x')
                ? int.tryParse(codePointVal)
                : int.tryParse(codePointVal.toString()));

        if (parsedPoint != null) {
          final String fontFamily =
              map['font_family']?.toString() ?? 'MaterialIcons';
          return Icon(
            IconData(parsedPoint, fontFamily: fontFamily),
            size: size,
            color: color,
          );
        }
      }

      // Option C: Nested icon name
      final dynamic nestedName =
          map['name'] ?? map['key'] ?? map['icon'] ?? map['identifier'];
      if (nestedName != null) {
        return _buildFromString(nestedName.toString());
      }
    }

    // 2. Direct Integer CodePoint
    if (iconData is int) {
      return Icon(
        IconData(iconData as int, fontFamily: 'MaterialIcons'),
        size: size,
        color: color,
      );
    }

    // 3. Direct String (Remote URL, Hex CodePoint, or Registry Identifier)
    if (iconData is String) {
      return _buildFromString(iconData as String);
    }

    // Fallback if data is unrecognized
    return Icon(Icons.circle, size: size * 0.4, color: color);
  }

  Widget _fallbackIcon(Map<String, dynamic> map) {
    final dynamic codePointVal = map['code_point'] ?? map['icon_code'];
    if (codePointVal != null) {
      final int? parsedPoint = codePointVal is int
          ? codePointVal
          : int.tryParse(codePointVal.toString());
      if (parsedPoint != null) {
        final String fontFamily =
            map['font_family']?.toString() ?? 'MaterialIcons';
        return Icon(
          IconData(parsedPoint, fontFamily: fontFamily),
          size: size,
          color: color,
        );
      }
    }
    return Icon(Icons.circle, size: size * 0.4, color: color);
  }

  Widget _buildFromString(String str) {
    final trimmed = str.trim();
    if (trimmed.isEmpty) {
      return Icon(Icons.circle, size: size * 0.4, color: color);
    }

    // Remote URL
    if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
      if (trimmed.endsWith('.svg')) {
        return SvgPicture.network(
          trimmed,
          width: size,
          height: size,
          colorFilter: ColorFilter.mode(color, BlendMode.srcIn),
          placeholderBuilder: (_) => SizedBox(width: size, height: size),
        );
      }
      return Image.network(
        trimmed,
        width: size,
        height: size,
        color: color,
        errorBuilder: (_, __, ___) =>
            Icon(Icons.circle, size: size * 0.4, color: color),
      );
    }

    // Hex String (e.g., "0xe153")
    if (trimmed.startsWith('0x') || trimmed.startsWith('0X')) {
      final codePoint = int.tryParse(trimmed) ?? 0xe1bd;
      return Icon(
        IconData(codePoint, fontFamily: 'MaterialIcons'),
        size: size,
        color: color,
      );
    }

    // Direct numeric string
    final numeric = int.tryParse(trimmed);
    if (numeric != null && numeric > 100) {
      return Icon(
        IconData(numeric, fontFamily: 'MaterialIcons'),
        size: size,
        color: color,
      );
    }

    // Resolve via SduiIconRegistry
    final resolved = SduiIconRegistry.resolve(trimmed);
    return Icon(resolved, size: size, color: color);
  }
}
