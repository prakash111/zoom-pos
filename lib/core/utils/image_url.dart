import '../config/app_config.dart';

/// Resolves a product/brand/category `image_url` value coming from the
/// backend into an absolute URL. The backend may return either an absolute
/// URL or a storage-relative path (e.g. `/storage/products/xyz.jpg`), and the
/// server host itself is user-configurable per install
/// (see `AppPreferences.readBaseUrl`), so callers that already hold the
/// resolved base URL (e.g. via `ApiClient`) should pass it through; when
/// omitted this falls back to [AppConfig.defaultBaseUrl].
String? resolveImageUrl(String? rawUrl, {String? baseUrl}) {
  if (rawUrl == null || rawUrl.trim().isEmpty) return null;
  final value = rawUrl.trim();
  if (value.startsWith('http://')) {
    return 'https://${value.substring(7)}';
  }
  if (value.startsWith('https://') || value.startsWith('data:image/')) {
    return value;
  }
  if (value.startsWith('//')) {
    return 'https:$value';
  }

  var host = (baseUrl == null || baseUrl.trim().isEmpty)
      ? AppConfig.defaultBaseUrl
      : baseUrl.trim();
  if (host.startsWith('http://')) {
    host = 'https://${host.substring(7)}';
  }
  host = host.replaceAll(RegExp(r'/+$'), '');
  host = host.replaceFirst(RegExp(r'/api/v1/pos$', caseSensitive: false), '');
  host = host.replaceFirst(RegExp(r'/api/v1$', caseSensitive: false), '');
  host = host.replaceFirst(RegExp(r'/api$', caseSensitive: false), '');

  final normalizedPath = value.startsWith('/') ? value : '/$value';
  return '$host$normalizedPath';
}
