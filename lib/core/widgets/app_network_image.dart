import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';

import '../utils/image_url.dart';

/// Platform-resilient network image widget.
///
/// On Flutter Web:
/// Uses [Image.network] configured with [WebHtmlElementStrategy.prefer]. This
/// forces the browser to render the image via a native HTML `<img>` element,
/// avoiding CanvasKit/WebGL cross-origin (CORS) decode failures, canvas-tainting
/// exceptions, and broken image icons after browser page reloads.
///
/// On Native platforms (Android, iOS, Windows, Linux, macOS):
/// Uses [CachedNetworkImage] for persistent local disk caching, offline usage,
/// and smooth image transitions.
class AppNetworkImage extends StatefulWidget {
  const AppNetworkImage({
    super.key,
    required this.imageUrl,
    this.baseUrl,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.borderRadius,
    this.fallbackIcon = Icons.inventory_2_outlined,
    this.placeholder,
    this.errorWidget,
    this.alignment = Alignment.center,
  });

  final String? imageUrl;
  final String? baseUrl;
  final double? width;
  final double? height;
  final BoxFit fit;
  final BorderRadius? borderRadius;
  final IconData fallbackIcon;
  final Widget Function(BuildContext context, String url)? placeholder;
  final Widget Function(BuildContext context, String url, dynamic error)? errorWidget;
  final Alignment alignment;

  @override
  State<AppNetworkImage> createState() => _AppNetworkImageState();
}

class _AppNetworkImageState extends State<AppNetworkImage> {
  bool _retryAttempted = false;

  @override
  void didUpdateWidget(AppNetworkImage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.imageUrl != widget.imageUrl || oldWidget.baseUrl != widget.baseUrl) {
      _retryAttempted = false;
    }
  }

  Widget _buildFallback(BuildContext context, {dynamic error}) {
    if (widget.errorWidget != null && widget.imageUrl != null) {
      return widget.errorWidget!(context, widget.imageUrl!, error);
    }
    return Container(
      width: widget.width,
      height: widget.height,
      color: Colors.grey.shade100,
      alignment: Alignment.center,
      child: Icon(
        widget.fallbackIcon,
        size: (widget.width != null && widget.width! < 40) ? 20 : 28,
        color: Colors.grey.shade400,
      ),
    );
  }

  Widget _buildDefaultLoading(BuildContext context) {
    if (widget.placeholder != null && widget.imageUrl != null) {
      return widget.placeholder!(context, widget.imageUrl!);
    }
    return Container(
      width: widget.width,
      height: widget.height,
      color: Colors.grey.shade100,
      alignment: Alignment.center,
      child: const SizedBox(
        width: 18,
        height: 18,
        child: CircularProgressIndicator(strokeWidth: 2),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final rawUrl = widget.imageUrl;
    final resolvedUrl = resolveImageUrl(rawUrl, baseUrl: widget.baseUrl);

    Widget content;
    if (resolvedUrl == null || resolvedUrl.trim().isEmpty) {
      content = _buildFallback(context);
    } else if (kIsWeb) {
      // On Web, use the native DOM <img> element to bypass CanvasKit CORS/caching bugs
      final effectiveUrl = _retryAttempted
          ? (resolvedUrl.contains('?') ? '$resolvedUrl&_t=1' : '$resolvedUrl?_t=1')
          : resolvedUrl;

      content = Image.network(
        effectiveUrl,
        key: ValueKey(effectiveUrl),
        width: widget.width,
        height: widget.height,
        fit: widget.fit,
        alignment: widget.alignment,
        webHtmlElementStrategy: WebHtmlElementStrategy.prefer,
        loadingBuilder: (context, child, loadingProgress) {
          if (loadingProgress == null) return child;
          return _buildDefaultLoading(context);
        },
        errorBuilder: (context, error, stackTrace) {
          if (!_retryAttempted) {
            WidgetsBinding.instance.addPostFrameCallback((_) {
              if (mounted) setState(() => _retryAttempted = true);
            });
            return _buildDefaultLoading(context);
          }
          return _buildFallback(context, error: error);
        },
      );
    } else {
      // Native platforms: CachedNetworkImage with local disk cache
      content = CachedNetworkImage(
        imageUrl: resolvedUrl,
        width: widget.width,
        height: widget.height,
        fit: widget.fit,
        alignment: widget.alignment,
        placeholder: (ctx, _) => _buildDefaultLoading(ctx),
        errorWidget: (ctx, _, err) => _buildFallback(ctx, error: err),
      );
    }

    if (widget.borderRadius != null) {
      return ClipRRect(
        borderRadius: widget.borderRadius!,
        child: content,
      );
    }

    return content;
  }
}
