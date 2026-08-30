import '../../core/api/api_client.dart';
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

    return LoginResult(
      token: response['token'] as String,
      user: UserModel.fromJson(response['user'] as Map<String, dynamic>),
      company: CompanyModel.fromJson(response['company'] as Map<String, dynamic>),
    );
  }

  Future<LoginResult> register({
    required String storeName,
    required String ownerName,
    required String email,
    required String password,
    String? phone,
    String? currency,
  }) async {
    final response = await _client.post(ApiEndpoints.register, data: {
      'store_name': storeName,
      'name': ownerName,
      'email': email,
      'password': password,
      if (phone != null && phone.isNotEmpty) 'phone': phone,
      if (currency != null && currency.isNotEmpty) 'currency': currency,
    });

    return LoginResult(
      token: response['token'] as String,
      user: UserModel.fromJson(response['user'] as Map<String, dynamic>),
      company: CompanyModel.fromJson(response['company'] as Map<String, dynamic>),
    );
  }

  Future<({UserModel? user, CompanyModel company})> session() async {
    final response = await _client.get(ApiEndpoints.session);
    return (
      user: response['user'] != null ? UserModel.fromJson(response['user'] as Map<String, dynamic>) : null,
      company: CompanyModel.fromJson(response['company'] as Map<String, dynamic>),
    );
  }
}
