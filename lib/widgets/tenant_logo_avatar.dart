import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';

import '../core/utils/image_url.dart';

/// Platform-resilient tenant store logo and initial avatar widget.
///
/// Solves CanvasKit cross-origin (CORS) restriction errors on Flutter Web:
/// When rendering network images on CanvasKit, standard canvas draws can fail with
/// "SecurityError: The operation is insecure" or "Could not create Image data for
/// this image because access to it is restricted".
///
/// On Web:
/// Uses [Image.network] configured with [WebHtmlElementStrategy.prefer] so the image
/// is loaded and displayed inside a native DOM `<img>` element platform view, bypassing
/// CanvasKit pixel-tainting CORS blocks.
///
/// Fallback:
/// If the image URL is missing, invalid, or fails to load, it immediately renders an
/// emerald green avatar pill containing the tenant's first letter initial in bold white typography.
class TenantLogoAvatar extends StatelessWidget {
  const TenantLogoAvatar({
    super.key,
    this.imageUrl,
    this.logoUrl,
    this.tenantName,
    this.size = 48.0,
    this.borderRadius,
    this.fontSize,
    this.fit = BoxFit.contain,
    this.backgroundColor = Colors.white,
    this.showBorder = true,
  });

  final String? imageUrl;
  final String? logoUrl;
  final String? tenantName;
  final double size;
  final BorderRadius? borderRadius;
  final double? fontSize;
  final BoxFit fit;
  final Color backgroundColor;
  final bool showBorder;

  /// Extracts the uppercase first initial from [name], falling back to 'T'.
  static String extractInitial(String? name) {
    if (name == null || name.trim().isEmpty) return 'T';
    final trimmed = name.trim();
    return String.fromCharCode(trimmed.runes.first).toUpperCase();
  }

  /// Ensures that any image URL explicitly uses HTTPS and resolves relative paths.
  static String? normalizeHttps(String? rawUrl) {
    final resolved = resolveImageUrl(rawUrl);
    if (resolved == null || resolved.trim().isEmpty) return null;
    var url = resolved.trim();
    if (url.startsWith('http://')) {
      url = 'https://${url.substring(7)}';
    } else if (url.startsWith('//')) {
      url = 'https:$url';
    }
    return url;
  }

  /// Builds the emerald green avatar pill containing the tenant's first letter initial.
  Widget _buildInitialAvatar(BorderRadius effectiveRadius) {
    final initial = extractInitial(tenantName);
    final effectiveFontSize = fontSize ?? (size * 0.44);

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: const Color(0xFF10B981), // Emerald green (#10B981)
        borderRadius: effectiveRadius,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.15),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      alignment: Alignment.center,
      child: Text(
        initial,
        style: TextStyle(
          color: Colors.white,
          fontSize: effectiveFontSize,
          fontWeight: FontWeight.bold,
          height: 1.0,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final secureUrl = normalizeHttps(logoUrl ?? imageUrl);
    final effectiveRadius = borderRadius ?? BorderRadius.circular(size * 0.22);

    if (secureUrl == null || secureUrl.isEmpty) {
      return _buildInitialAvatar(effectiveRadius);
    }

    Widget imageWidget;
    if (kIsWeb) {
      // Flutter Web: Load via DOM element strategy to bypass CanvasKit CORS limitations
      imageWidget = Image.network(
        secureUrl,
        key: ValueKey('web-tenant-logo-$secureUrl'),
        width: size,
        height: size,
        fit: fit,
        webHtmlElementStrategy: WebHtmlElementStrategy.prefer,
        errorBuilder: (context, error, stackTrace) {
          return _buildInitialAvatar(effectiveRadius);
        },
        loadingBuilder: (context, child, loadingProgress) {
          if (loadingProgress == null) return child;
          return Container(
            width: size,
            height: size,
            color: Colors.grey.shade100,
            alignment: Alignment.center,
            child: SizedBox(
              width: size * 0.35,
              height: size * 0.35,
              child: const CircularProgressIndicator(strokeWidth: 2),
            ),
          );
        },
      );
    } else {
      // Native platforms: CachedNetworkImage
      imageWidget = CachedNetworkImage(
        imageUrl: secureUrl,
        width: size,
        height: size,
        fit: fit,
        errorWidget: (context, url, error) =>
            _buildInitialAvatar(effectiveRadius),
        placeholder: (context, url) => Container(
          width: size,
          height: size,
          color: Colors.grey.shade100,
          alignment: Alignment.center,
          child: SizedBox(
            width: size * 0.35,
            height: size * 0.35,
            child: const CircularProgressIndicator(strokeWidth: 2),
          ),
        ),
      );
    }

    final double innerRadiusValue =
        (effectiveRadius.resolve(TextDirection.ltr).topLeft.x - 2)
            .clamp(0.0, 100.0);

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: effectiveRadius,
        boxShadow: showBorder
            ? [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.12),
                  blurRadius: 5,
                  offset: const Offset(0, 2),
                ),
              ]
            : null,
      ),
      padding: const EdgeInsets.all(2.5),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(innerRadiusValue),
        child: imageWidget,
      ),
    );
  }
}
