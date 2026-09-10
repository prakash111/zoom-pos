import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/config/app_config.dart';
import '../../core/models/company_model.dart';
import '../../core/models/user_model.dart';

class LoginResult {
  LoginResult({required this.token, required this.user, required this.company});

  final String token;
  final UserModel user;
  final CompanyModel company;
}

class RegisterResult {
  RegisterResult({
    required this.requiresOtp,
    this.token,
    this.user,
    this.company,
    this.email,
    this.expiresIn = 600,
    this.message,
    this.route,
  });

  final bool requiresOtp;
  final String? token;
  final UserModel? user;
  final CompanyModel? company;
  final String? email;
  final int expiresIn;
  final String? message;
  final String? route;

  LoginResult toLoginResult() {
    if (token == null || user == null || company == null) {
      throw ApiException('The server did not return an authentication token.');
    }
    return LoginResult(token: token!, user: user!, company: company!);
  }
}

/// Talks to POST /auth/login, /auth/register and GET /auth/session
/// (PosSyncApiController) — see that controller for the exact response shape.
class AuthRepository {
  AuthRepository(this._client);

  final ApiClient _client;

  /// Signs in against POST /auth/login.
  ///
  /// Returns a [RegisterResult] rather than a bare [LoginResult] because the
  /// backend can answer a valid email+password with a `requires_verification`
  /// payload (HTTP 200) when the account never completed email OTP — the same
  /// shape [register] can return. Callers check [RegisterResult.requiresOtp]
  /// and route to the verify screen; otherwise `.toLoginResult()` unwraps the
  /// token/user/company.
  Future<RegisterResult> login({
    required String email,
    required String password,
    String? accountId,
  }) async {
    final response = await _client.post(ApiEndpoints.login, data: {
      'email': email,
      'password': password,
      if (accountId != null && accountId.isNotEmpty) 'account_id': accountId,
    });

    return _authOutcome(response, email);
  }

  Future<RegisterResult> register({
    required String storeName,
    required String ownerName,
    required String email,
    required String password,
    String? phone,
    String? currency,
    String? country,
    String? timezone,
    String posMode = 'general',
  }) async {
    final response = await _client.post(ApiEndpoints.register, data: {
      'store_name': storeName,
      'name': ownerName,
      'email': email,
      'password': password,
      if (phone != null && phone.isNotEmpty) 'phone': phone,
      if (currency != null && currency.isNotEmpty) 'currency': currency,
      if (country != null && country.isNotEmpty) 'country': country,
      if (timezone != null && timezone.isNotEmpty) 'timezone': timezone,
      'pos_mode': posMode,
    });

    return _authOutcome(response, email);
  }

  /// Normalizes a /auth/login or /auth/register response into a
  /// [RegisterResult]. A `requires_verification` / `requires_otp` payload
  /// becomes `requiresOtp: true` (never throwing "missing token"); anything
  /// else is unwrapped as a completed token session.
  RegisterResult _authOutcome(
    Map<String, dynamic> response,
    String fallbackEmail,
  ) {
    final payload = _payload(response);
    final requiresOtp = payload['requires_otp'] == true ||
        payload['status'] == 'requires_verification' ||
        (payload['action'] == 'navigate' &&
            payload['route']?.toString().contains('verify-otp') == true);

    if (requiresOtp) {
      final userMap = payload['user'] is Map
          ? Map<String, dynamic>.from(payload['user'] as Map)
          : null;
      final companyMap = payload['company'] is Map
          ? Map<String, dynamic>.from(payload['company'] as Map)
          : null;
      final resolvedEmail = payload['email']?.toString() ??
          (payload['arguments'] is Map
              ? (payload['arguments'] as Map)['email']?.toString()
              : null) ??
          userMap?['email']?.toString() ??
          fallbackEmail;

      return RegisterResult(
        requiresOtp: true,
        email: resolvedEmail,
        expiresIn: payload['expires_in'] is int
            ? payload['expires_in'] as int
            : 600,
        message: payload['message']?.toString(),
        route: payload['route']?.toString(),
        token: payload['token']?.toString(),
        user: userMap != null && userMap['id'] != null
            ? UserModel.fromJson(userMap)
            : null,
        company: companyMap != null && companyMap['id'] != null
            ? CompanyModel.fromJson(companyMap)
            : null,
      );
    }

    final loginRes = _loginResult(response);
    return RegisterResult(
      requiresOtp: false,
      token: loginRes.token,
      user: loginRes.user,
      company: loginRes.company,
    );
  }

  Future<LoginResult> verifyOtp({
    required String email,
    required String otp,
  }) async {
    final response = await _client.post(ApiEndpoints.verifyOtp, data: {
      'email': email,
      'otp': otp,
    });

    return _loginResult(response);
  }

  Future<void> resendOtp({required String email}) async {
    await _client.post(ApiEndpoints.resendOtp, data: {
      'email': email,
    });
  }

  Future<({UserModel? user, CompanyModel company})> session() async {
    final response = _payload(await _client.get(ApiEndpoints.session));
    return (
      user: response['user'] is Map
          ? UserModel.fromJson(Map<String, dynamic>.from(response['user'] as Map))
          : null,
      company: CompanyModel.fromJson(_requiredMap(response, 'company')),
    );
  }

  LoginResult _loginResult(Map<String, dynamic> response) {
    final payload = _payload(response);
    final token = payload['token']?.toString() ?? '';
    if (token.isEmpty) {
      throw ApiException('The server did not return an authentication token.');
    }

    final userMap = _requiredMap(payload, 'user');
    final companyMap = payload['company'] is Map
        ? Map<String, dynamic>.from(payload['company'] as Map)
        : <String, dynamic>{
            'id': 0,
            'name': 'My Store',
            'slug': 'my-store',
            'currency': 'USD',
            'currency_symbol': '\$',
          };

    return LoginResult(
      token: token,
      user: UserModel.fromJson(userMap),
      company: CompanyModel.fromJson(companyMap),
    );
  }

  // Accept both the current flat response and the commonly used
  // {success: true, data: {...}} envelope, so server middleware/version
  // differences cannot turn a valid login into a Dart cast error.
  static Map<String, dynamic> _payload(Map<String, dynamic> response) {
    final data = response['data'];
    return data is Map ? Map<String, dynamic>.from(data) : response;
  }

  static Map<String, dynamic> _requiredMap(
    Map<String, dynamic> payload,
    String key,
  ) {
    final value = payload[key];
    if (value is Map) return Map<String, dynamic>.from(value);
    throw ApiException('The server returned an invalid authentication response.');
  }
}
