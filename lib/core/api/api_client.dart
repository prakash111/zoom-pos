import 'package:dio/dio.dart';

import '../config/app_config.dart';
import '../storage/app_preferences.dart';
import '../storage/secure_storage_service.dart';
import 'api_exception.dart';

/// Thin wrapper around Dio for the `/api/v1/pos/*` sync API.
///
/// Every response the backend sends back is a JSON object with a `success`
/// boolean (see PosSyncApiController) — this unwraps that envelope so
/// feature repositories work with plain maps and never see Dio types.
class ApiClient {
  ApiClient({
    required SecureStorageService secureStorage,
    required AppPreferences preferences,
  })  : _secureStorage = secureStorage,
        _preferences = preferences,
        _dio = Dio(BaseOptions(
          connectTimeout: AppConfig.connectTimeout,
          receiveTimeout: AppConfig.receiveTimeout,
          contentType: 'application/json',
          headers: const {'Accept': 'application/json'},
        ));

  final Dio _dio;
  final SecureStorageService _secureStorage;
  final AppPreferences _preferences;

  /// Invoked whenever the server rejects the stored token as unauthenticated,
  /// so the app can drop back to the login screen. Set by AuthProvider.
  void Function()? onUnauthenticated;

  /// The configured server host (no `/api/v1/pos` suffix), for building
  /// asset URLs (product images) or opening public web pages from the app.
  Future<String> currentBaseUrl() => _preferences.readBaseUrl();

  /// Runs once before every request. Each read is bounded by
  /// [AppConfig.localReadTimeout]: these are platform-channel calls (OS
  /// keystore, SharedPreferences), not HTTP, so Dio's own timeouts don't
  /// apply — a stuck native call here would otherwise hang every request in
  /// the app with no exception ever thrown to catch. A timed-out read falls
  /// back to a safe default so the request still goes out (as unauthenticated
  /// / against the default server / in English) rather than never happening.
  Future<void> _prepare() async {
    final baseUrl = await _preferences.readBaseUrl().timeout(
        AppConfig.localReadTimeout,
        onTimeout: () => AppConfig.defaultBaseUrl);
    _dio.options.baseUrl = '$baseUrl${AppConfig.apiPrefix}';

    // On timeout, keep whatever Authorization header a previous successful
    // _prepare() already set (a token read taking >5s doesn't mean the
    // stored token itself changed) rather than downgrading to unauthenticated
    // and forcing a spurious logout on top of the slow/stuck read.
    final previousAuth = _dio.options.headers['Authorization'];
    final token = await _secureStorage
        .readToken()
        .timeout(AppConfig.localReadTimeout, onTimeout: () => null);
    _dio.options.headers['Authorization'] =
        token != null ? 'Bearer $token' : previousAuth;

    final locale = await _preferences
        .readLocale()
        .timeout(AppConfig.localReadTimeout, onTimeout: () => 'en');
    _dio.options.headers['Accept-Language'] = locale;
  }

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) {
    return _send(() => _dio.get(path, queryParameters: query));
  }

  /// Like [get], but for endpoints outside the `/api/v1/pos` prefix (e.g.
  /// `/api/v1/tax/*`). [path] must be a full path starting with `/api/...` —
  /// Dio treats it as absolute (ignoring `_dio.options.baseUrl`) once it's
  /// prefixed with the server's scheme, while [_prepare] still attaches the
  /// same bearer token, which [AuthenticateTenantApi] accepts on both route
  /// groups.
  Future<Map<String, dynamic>> getAbsolute(String path,
      {Map<String, dynamic>? query}) {
    return _send(() async {
      final base = await currentBaseUrl();
      return _dio.get('$base$path', queryParameters: query);
    });
  }

  /// Like [get], but for endpoints that return a raw text body (e.g. the CSV
  /// report export) instead of the JSON success/data envelope.
  Future<String> getRaw(String path, {Map<String, dynamic>? query}) async {
    await _prepare();
    try {
      final response = await _dio.get<String>(
        path,
        queryParameters: query,
        options: Options(responseType: ResponseType.plain),
      );
      return response.data ?? '';
    } on DioException catch (e) {
      throw _mapDioError(e);
    }
  }

  /// Like [get], but for endpoints that return raw binary bytes (the invoice
  /// PDF endpoints) instead of the JSON success/data envelope.
  Future<List<int>> getBytes(String path, {Map<String, dynamic>? query}) async {
    await _prepare();
    try {
      final response = await _dio.get<List<int>>(
        path,
        queryParameters: query,
        options: Options(responseType: ResponseType.bytes),
      );
      return response.data ?? const [];
    } on DioException catch (e) {
      throw _mapDioError(e);
    }
  }

  Future<Map<String, dynamic>> post(String path, {Map<String, dynamic>? data}) {
    return _send(() => _dio.post(path, data: data));
  }

  /// Like [post], but for endpoints outside the `/api/v1/pos` prefix — see
  /// [getAbsolute].
  Future<Map<String, dynamic>> postAbsolute(String path,
      {Map<String, dynamic>? data}) {
    return _send(() async {
      final base = await currentBaseUrl();
      return _dio.post('$base$path', data: data);
    });
  }

  /// Like [put], but for endpoints outside the `/api/v1/pos` prefix.
  Future<Map<String, dynamic>> putAbsolute(String path,
      {Map<String, dynamic>? data}) {
    return _send(() async {
      final base = await currentBaseUrl();
      return _dio.put('$base$path', data: data);
    });
  }

  /// Generic executor used by declarative SDUI actions.
  ///
  /// Only API paths on the configured server origin are accepted. This keeps
  /// a malformed schema from forwarding the bearer token to another host.
  Future<Map<String, dynamic>> requestAbsolute(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? data,
    Map<String, dynamic>? query,
  }) {
    return _send(() async {
      final base = await currentBaseUrl();
      final normalizedMethod = method.toUpperCase();
      const allowedMethods = {'GET', 'POST', 'PUT', 'PATCH', 'DELETE'};
      if (!allowedMethods.contains(normalizedMethod)) {
        throw ApiException(
            'Unsupported SDUI request method: $normalizedMethod');
      }

      final baseUri = Uri.parse(base);
      final requestedUri = Uri.tryParse(path);
      if (requestedUri == null ||
          (!requestedUri.hasScheme && !path.startsWith('/api/'))) {
        throw ApiException('SDUI endpoints must start with /api/.');
      }

      final resolved = requestedUri.hasScheme
          ? requestedUri
          : baseUri.resolveUri(requestedUri);
      if (resolved.scheme != baseUri.scheme ||
          resolved.host != baseUri.host ||
          resolved.port != baseUri.port ||
          !resolved.path.startsWith('/api/')) {
        throw ApiException(
            'SDUI endpoint is outside the configured API origin.');
      }

      return _dio.request(
        resolved.toString(),
        data: data,
        queryParameters: query,
        options: Options(method: normalizedMethod),
      );
    });
  }

  /// Like [post], but for multipart file uploads (product images, business
  /// logo/favicon). Dio sets the correct `multipart/form-data` content-type
  /// and boundary itself whenever [data] is a [FormData] instance,
  /// overriding this client's default JSON content-type for that request.
  Future<Map<String, dynamic>> postMultipart(
    String path, {
    required String fieldName,
    required List<int> bytes,
    required String filename,
  }) {
    return _send(() => _dio.post(
          path,
          data: FormData.fromMap({
            fieldName: MultipartFile.fromBytes(bytes, filename: filename),
          }),
        ));
  }

  Future<Map<String, dynamic>> put(String path, {Map<String, dynamic>? data}) {
    return _send(() => _dio.put(path, data: data));
  }

  Future<Map<String, dynamic>> delete(String path,
      {Map<String, dynamic>? data}) {
    return _send(() => _dio.delete(path, data: data));
  }

  Future<Map<String, dynamic>> _send(
      Future<Response> Function() request) async {
    await _prepare();
    try {
      final response = await request();
      final body = response.data;
      if (body is Map) {
        final json = Map<String, dynamic>.from(body);
        if (json['success'] == false) {
          throw ApiException(
            _extractErrorMessage(json),
            statusCode: response.statusCode,
            details: _asStringMap(json['details']),
          );
        }
        return json;
      }
      return {'success': true, 'data': body};
    } on DioException catch (e) {
      throw _mapDioError(e);
    }
  }

  static String _extractErrorMessage(Map<String, dynamic> body) {
    final details = body['details'];
    if (details is Map && details.isNotEmpty) {
      final first = details.values.first;
      if (first is List && first.isNotEmpty) {
        return first.first.toString();
      } else if (first is String && first.isNotEmpty) {
        return first;
      }
    }
    return (body['error'] ?? body['message'] ?? 'Request failed').toString();
  }

  ApiException _mapDioError(DioException e) {
    final status = e.response?.statusCode;
    final body = e.response?.data;

    if (status == 401) {
      onUnauthenticated?.call();
    }

    if (body is Map) {
      final json = Map<String, dynamic>.from(body);
      return ApiException(
        _extractErrorMessage(json),
        statusCode: status,
        details: _asStringMap(json['details']),
      );
    }

    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return ApiException(
            'The server took too long to respond. Check your connection and try again.');
      case DioExceptionType.connectionError:
        return ApiException(
            'Could not reach the server. Check the server address in Settings and your connection.');
      default:
        return ApiException(e.message ?? 'Something went wrong.',
            statusCode: status);
    }
  }

  static Map<String, dynamic>? _asStringMap(Object? value) {
    return value is Map ? Map<String, dynamic>.from(value) : null;
  }
}
