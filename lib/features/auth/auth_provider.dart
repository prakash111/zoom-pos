import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/config/bootstrap_cache.dart';
import '../../core/models/company_model.dart';
import '../../core/models/user_model.dart';
import '../../core/services/tenant_time_service.dart';
import '../../core/storage/secure_storage_service.dart';
import 'auth_repository.dart';

enum AuthStatus { unknown, authenticating, authenticated, unauthenticated }

/// Owns the login/logout lifecycle and the signed-in user + company for the
/// rest of the app. [restoreSession] runs once at startup so a device that
/// already has a stored token skips straight past the login screen.
class AuthProvider extends ChangeNotifier {
  AuthProvider({
    required AuthRepository authRepository,
    required SecureStorageService secureStorage,
    required ApiClient apiClient,
  })  : _authRepository = authRepository,
        _secureStorage = secureStorage,
        _apiClient = apiClient {
    apiClient.onUnauthenticated = _handleUnauthenticated;
  }

  final AuthRepository _authRepository;
  final SecureStorageService _secureStorage;
  final ApiClient _apiClient;

  AuthStatus _status = AuthStatus.unknown;
  UserModel? _user;
  CompanyModel? _company;
  String? _errorMessage;

  Future<void> Function()? onBeforeLogout;

  AuthStatus get status => _status;
  UserModel? get user => _user;
  CompanyModel? get company => _company;
  String? get errorMessage => _errorMessage;
  bool get isBusy => _status == AuthStatus.authenticating;

  /// Sets [_company] and keeps [TenantTimeService] (order timestamps, prep
  /// timers, KOT logs) in sync with it — every call site that assigns
  /// `_company` goes through here rather than the field directly, so none
  /// of them can forget this.
  void _applyCompany(CompanyModel? company) {
    _company = company;
    TenantTimeService.instance.setTimezone(company?.timezone);
  }

  Future<void> restoreSession() async {
    try {
      final token = await _secureStorage.readToken().timeout(
            const Duration(seconds: 4),
            onTimeout: () => null,
          );

      if (token == null || token.isEmpty) {
        _status = AuthStatus.unauthenticated;
        return;
      }

      final result = await _authRepository.session().timeout(
            const Duration(seconds: 6),
            onTimeout: () => throw ApiException('Session restore timed out'),
          );
      _user = result.user;
      _applyCompany(result.company);
      try {
        await BootstrapCache.instance
            .hydrate(forceRefresh: false, client: _apiClient);
      } catch (_) {}
      _status = AuthStatus.authenticated;
    } on ApiException {
      await _secureStorage.clearToken();
      _status = AuthStatus.unauthenticated;
    } catch (e) {
      debugPrint('AuthProvider.restoreSession error: $e');
      _status = AuthStatus.unauthenticated;
    } finally {
      notifyListeners();
    }
  }

  Future<bool> login({
    required String email,
    required String password,
    String? accountId,
  }) {
    return _attempt(() => _authRepository.login(
          email: email,
          password: password,
          accountId: accountId,
        ));
  }

  RegisterResult? _lastRegisterResult;
  RegisterResult? get lastRegisterResult => _lastRegisterResult;

  Future<RegisterResult?> register({
    required String storeName,
    required String ownerName,
    required String email,
    required String password,
    String? phone,
    String? currency,
    String posMode = 'general',
  }) async {
    _status = AuthStatus.authenticating;
    _errorMessage = null;
    notifyListeners();

    try {
      final result = await _authRepository.register(
        storeName: storeName,
        ownerName: ownerName,
        email: email,
        password: password,
        phone: phone,
        currency: currency,
        posMode: posMode,
      );

      _lastRegisterResult = result;

      if (result.requiresOtp) {
        _status = AuthStatus.unauthenticated;
        notifyListeners();
        return result;
      }

      if (result.token != null && result.token!.isNotEmpty) {
        await _handleLoginSuccess(LoginResult(
          token: result.token!,
          user: result.user ??
              UserModel.fromJson({
                'id': '0',
                'name': ownerName,
                'email': email,
                'role': 'admin',
                'company_id': '0',
                'permissions': {'*': true},
              }),
          company: result.company ??
              CompanyModel.fromJson({
                'id': '0',
                'name': storeName,
                'trade_name': storeName,
                'currency': currency ?? 'USD',
                'currency_symbol': '\$',
                'plan_name': 'trial',
              }),
        ));
      }

      return result;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return null;
    } catch (e, stackTrace) {
      debugPrint('AuthProvider.register unexpected error: $e');
      debugPrintStack(stackTrace: stackTrace);
      _errorMessage = 'Registration failed (${e.runtimeType}): $e';
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return null;
    }
  }

  Future<bool> verifyOtp({
    required String email,
    required String otp,
  }) {
    return _attempt(() => _authRepository.verifyOtp(
          email: email,
          otp: otp,
        ));
  }

  Future<void> resendOtp({required String email}) async {
    try {
      await _authRepository.resendOtp(email: email);
    } catch (e) {
      debugPrint('AuthProvider.resendOtp error: $e');
      rethrow;
    }
  }

  Future<bool> loginWithToken(String token, {UserModel? user, CompanyModel? company}) async {
    _status = AuthStatus.authenticating;
    _errorMessage = null;
    notifyListeners();

    try {
      await _secureStorage.saveToken(token);
      if (user != null) _user = user;
      if (company != null) _applyCompany(company);

      try {
        final session = await _authRepository.session();
        _applyCompany(session.company);
        if (session.user != null) _user = session.user;
      } catch (_) {}

      try {
        await BootstrapCache.instance
            .hydrate(forceRefresh: true, client: _apiClient);
      } catch (_) {}

      _status = AuthStatus.authenticated;
      notifyListeners();
      return true;
    } catch (e) {
      _errorMessage = 'Authentication failed: $e';
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    }
  }

  Future<void> _handleLoginSuccess(LoginResult result) async {
    await _secureStorage.saveToken(result.token);
    _user = result.user;
    _applyCompany(result.company);

    // The login/register responses omit a few company fields (notably
    // pos_mode/restaurant_mode_locked) that only GET /auth/session
    // returns in full — refresh from there so mode-dependent UI (e.g.
    // the dashboard's retail vs. restaurant POS branch) is correct
    // immediately after signing in, not just after an app restart.
    try {
      final refreshed = await _authRepository.session();
      _applyCompany(refreshed.company);
      if (refreshed.user != null) _user = refreshed.user;
    } catch (_) {
      // Keep the company from the login/register response if this fails.
    }

    // Pre-hydrate bootstrap menu and theme before setting status to authenticated
    // so first login / signup renders the populated server-driven drawer instantly!
    try {
      await BootstrapCache.instance
          .hydrate(forceRefresh: true, client: _apiClient);
    } catch (e) {
      debugPrint('Bootstrap pre-hydration error: $e');
    }

    _status = AuthStatus.authenticated;
    notifyListeners();
  }

  Future<bool> _attempt(Future<LoginResult> Function() action) async {
    _status = AuthStatus.authenticating;
    _errorMessage = null;
    notifyListeners();

    try {
      final result = await action();
      await _handleLoginSuccess(result);
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    } catch (e, stackTrace) {
      debugPrint('AuthProvider._attempt unexpected error: $e');
      debugPrintStack(stackTrace: stackTrace);
      _errorMessage = 'Sign-in failed (${e.runtimeType}): $e';
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    await onBeforeLogout?.call();
    await _secureStorage.clearToken();
    _user = null;
    _applyCompany(null);
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  void _handleUnauthenticated() {
    if (_status != AuthStatus.authenticated) return;
    _secureStorage.clearToken();
    _user = null;
    _applyCompany(null);
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }
}
