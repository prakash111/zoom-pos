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

  static const Map<int, IconData> _standardCodePoints = {
    // Point of Sale & Retail
    0xe54c: Icons.point_of_sale,
    0xe547: Icons.shopping_cart,
    0xeb1f: Icons.shopping_cart_checkout,
    0xe8cc: Icons.shopping_bag,
    0xef64: Icons.receipt_long,
    0xeef2: Icons.receipt,

    // Staff Chat & Support
    0xe153: Icons.chat_bubble_outline,
    0xe24b: Icons.forum,
    0xe44e: Icons.notifications_active,

    // Human Resource Management (HRM)
    0xe0ba: Icons.badge,
    0xe055: Icons.access_time,
    0xe226: Icons.event_note,
    0xe227: Icons.event_busy,
    0xe481: Icons.payments,

    // Loyalty & Rewards
    0xe041: Icons.account_balance_wallet,
    0xe3d0: Icons.military_tech,
    0xe661: Icons.tune,

    // Common Core Navigation
    0xe88a: Icons.home,
    0xe871: Icons.grid_view,
    0xe9b0: Icons.grid_view,
    0xe8f4: Icons.inventory_2,
    0xe7fb: Icons.people,
    0xe85d: Icons.bar_chart,
    0xe8b8: Icons.settings,
    0xe8d1: Icons.storefront,
    0xe145: Icons.add,
    0xe1bd: Icons.widgets,
  };

  /// Resolves an integer code point, preferring const pre-compiled icons.
  static IconData resolveCodePoint(int codePoint, [String fontFamily = 'MaterialIcons']) {
    if (fontFamily == 'MaterialIcons' && _standardCodePoints.containsKey(codePoint)) {
      return _standardCodePoints[codePoint]!;
    }
    return IconData(codePoint, fontFamily: fontFamily);
  }

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
            resolveCodePoint(parsedPoint, fontFamily),
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
        resolveCodePoint(iconData as int),
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
          resolveCodePoint(parsedPoint, fontFamily),
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
        resolveCodePoint(codePoint),
        size: size,
        color: color,
      );
    }

    // Direct numeric string
    final numeric = int.tryParse(trimmed);
    if (numeric != null && numeric > 100) {
      return Icon(
        resolveCodePoint(numeric),
        size: size,
        color: color,
      );
    }

    // Resolve via SduiIconRegistry
    final resolved = SduiIconRegistry.resolve(trimmed);
    return Icon(resolved, size: size, color: color);
  }
}
