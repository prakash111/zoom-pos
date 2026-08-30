import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/models/company_model.dart';
import '../../core/models/user_model.dart';
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
        _secureStorage = secureStorage {
    apiClient.onUnauthenticated = _handleUnauthenticated;
  }

  final AuthRepository _authRepository;
  final SecureStorageService _secureStorage;

  AuthStatus _status = AuthStatus.unknown;
  UserModel? _user;
  CompanyModel? _company;
  String? _errorMessage;

  AuthStatus get status => _status;
  UserModel? get user => _user;
  CompanyModel? get company => _company;
  String? get errorMessage => _errorMessage;
  bool get isBusy => _status == AuthStatus.authenticating;

  Future<void> restoreSession() async {
    final token = await _secureStorage.readToken();
    if (token == null) {
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return;
    }

    try {
      final result = await _authRepository.session();
      _user = result.user;
      _company = result.company;
      _status = AuthStatus.authenticated;
    } on ApiException {
      await _secureStorage.clearToken();
      _status = AuthStatus.unauthenticated;
    }
    notifyListeners();
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

  Future<bool> register({
    required String storeName,
    required String ownerName,
    required String email,
    required String password,
    String? phone,
    String? currency,
  }) {
    return _attempt(() => _authRepository.register(
          storeName: storeName,
          ownerName: ownerName,
          email: email,
          password: password,
          phone: phone,
          currency: currency,
        ));
  }

  Future<bool> _attempt(Future<LoginResult> Function() action) async {
    _status = AuthStatus.authenticating;
    _errorMessage = null;
    notifyListeners();

    try {
      final result = await action();
      await _secureStorage.saveToken(result.token);
      _user = result.user;
      _company = result.company;
      _status = AuthStatus.authenticated;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    await _secureStorage.clearToken();
    _user = null;
    _company = null;
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  void _handleUnauthenticated() {
    if (_status != AuthStatus.authenticated) return;
    _secureStorage.clearToken();
    _user = null;
    _company = null;
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }
}
