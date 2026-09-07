import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/api/api_exception.dart';
import 'package:zoom_pos_mobile/core/config/app_config.dart';
import 'package:zoom_pos_mobile/core/storage/secure_storage_service.dart';
import 'package:zoom_pos_mobile/features/auth/auth_provider.dart';
import 'package:zoom_pos_mobile/features/auth/auth_repository.dart';

/// Mobile-side regression guard for the "Sign In throws HTTP 302" crash.
///
/// - A valid email+password on an unverified account must come back as a
///   `requiresOtp` outcome the login screen routes to the verify screen on,
///   never a thrown "missing token" error.
/// - A raw redirect surfacing from [ApiClient] as an [ApiException] must be
///   handled by [AuthProvider.login] as an ordinary error (false + message),
///   not an unhandled exception.
class _GateApiClient extends Fake implements ApiClient {
  _GateApiClient({this.loginResponse, this.loginThrows});

  final Map<String, dynamic>? loginResponse;
  final Object? loginThrows;

  @override
  void Function()? onUnauthenticated;

  @override
  Future<String> currentBaseUrl() async => 'https://saas.zoomnearby.com';

  @override
  Future<Map<String, dynamic>> post(String path, {dynamic data}) async {
    if (path == ApiEndpoints.login) {
      if (loginThrows != null) throw loginThrows!;
      return loginResponse ?? {'success': true};
    }
    return {'success': true};
  }

  @override
  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) async =>
      {'success': true};
}

class _FakeSecureStorage extends Fake implements SecureStorageService {
  String? _token;

  @override
  Future<void> saveToken(String token) async => _token = token;

  @override
  Future<String?> readToken() async => _token;

  @override
  Future<void> clearToken() async => _token = null;
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('login email-verification gate', () {
    test('AuthRepository.login maps requires_verification to requiresOtp without throwing', () async {
      final repo = AuthRepository(_GateApiClient(loginResponse: {
        'success': true,
        'status': 'requires_verification',
        'requires_otp': true,
        'email': 'test_otp@gmail.com',
        'expires_in': 600,
        'route': '/api/tenant/views/verify-otp',
        'message': 'Please verify your email address to continue.',
      }));

      final result = await repo.login(
        email: 'test_otp@gmail.com',
        password: 'password',
      );

      expect(result.requiresOtp, isTrue);
      expect(result.email, 'test_otp@gmail.com');
      expect(result.expiresIn, 600);
      expect(result.token, isNull);
    });

    test('AuthProvider.login exposes pendingEmailVerification and does not set an error', () async {
      final api = _GateApiClient(loginResponse: {
        'success': true,
        'status': 'requires_verification',
        'requires_otp': true,
        'email': 'test_otp@gmail.com',
        'expires_in': 600,
      });
      final provider = AuthProvider(
        authRepository: AuthRepository(api),
        secureStorage: _FakeSecureStorage(),
        apiClient: api,
      );

      final ok = await provider.login(
        email: 'test_otp@gmail.com',
        password: 'password',
      );

      expect(ok, isFalse);
      expect(provider.errorMessage, isNull);
      expect(provider.status, AuthStatus.unauthenticated);
      expect(provider.pendingEmailVerification, isNotNull);
      expect(provider.pendingEmailVerification!.email, 'test_otp@gmail.com');
    });

    test('AuthProvider.login turns a redirect ApiException into a plain error, not a crash', () async {
      final api = _GateApiClient(
        loginThrows: ApiException(
          'The server redirected the request instead of returning data.',
          statusCode: 302,
        ),
      );
      final provider = AuthProvider(
        authRepository: AuthRepository(api),
        secureStorage: _FakeSecureStorage(),
        apiClient: api,
      );

      final ok = await provider.login(email: 'a@b.com', password: 'x');

      expect(ok, isFalse);
      expect(provider.pendingEmailVerification, isNull);
      expect(provider.errorMessage, contains('redirected'));
      expect(provider.status, AuthStatus.unauthenticated);
    });
  });
}
