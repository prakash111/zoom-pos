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

  Future<void> _prepare() async {
    final baseUrl = await _preferences.readBaseUrl();
    _dio.options.baseUrl = '$baseUrl${AppConfig.apiPrefix}';

    final token = await _secureStorage.readToken();
    _dio.options.headers['Authorization'] = token != null ? 'Bearer $token' : null;
  }

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) {
    return _send(() => _dio.get(path, queryParameters: query));
  }

  Future<Map<String, dynamic>> post(String path, {Map<String, dynamic>? data}) {
    return _send(() => _dio.post(path, data: data));
  }

  Future<Map<String, dynamic>> _send(Future<Response> Function() request) async {
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
        return ApiException('The server took too long to respond. Check your connection and try again.');
      case DioExceptionType.connectionError:
        return ApiException('Could not reach the server. Check the server address in Settings and your connection.');
      default:
        return ApiException(e.message ?? 'Something went wrong.', statusCode: status);
    }
  }

  static Map<String, dynamic>? _asStringMap(Object? value) {
    return value is Map ? Map<String, dynamic>.from(value) : null;
  }
}
