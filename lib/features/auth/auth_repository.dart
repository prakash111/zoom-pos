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

/// Talks to POST /auth/login, /auth/register and GET /auth/session
/// (PosSyncApiController) — see that controller for the exact response shape.
class AuthRepository {
  AuthRepository(this._client);

  final ApiClient _client;

  Future<LoginResult> login({
    required String email,
    required String password,
    String? accountId,
  }) async {
    final response = await _client.post(ApiEndpoints.login, data: {
      'email': email,
      'password': password,
      if (accountId != null && accountId.isNotEmpty) 'account_id': accountId,
    });

    return _loginResult(response);
  }

  Future<LoginResult> register({
    required String storeName,
    required String ownerName,
    required String email,
    required String password,
    String? phone,
    String? currency,
    String posMode = 'general',
  }) async {
    final response = await _client.post(ApiEndpoints.register, data: {
      'store_name': storeName,
      'name': ownerName,
      'email': email,
      'password': password,
      if (phone != null && phone.isNotEmpty) 'phone': phone,
      if (currency != null && currency.isNotEmpty) 'currency': currency,
      'pos_mode': posMode,
    });

    return _loginResult(response);
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

    return LoginResult(
      token: token,
      user: UserModel.fromJson(_requiredMap(payload, 'user')),
      company: CompanyModel.fromJson(_requiredMap(payload, 'company')),
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
