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
  if (value.startsWith('http://') || value.startsWith('https://')) {
    return value;
  }
  final host = (baseUrl == null || baseUrl.trim().isEmpty) ? AppConfig.defaultBaseUrl : baseUrl.trim();
  final normalizedHost = host.endsWith('/') ? host.substring(0, host.length - 1) : host;
  final normalizedPath = value.startsWith('/') ? value : '/$value';
  return '$normalizedHost$normalizedPath';
}
