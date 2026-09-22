import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/config/bootstrap_cache.dart';
import '../../core/models/company_model.dart';
import '../../core/models/user_model.dart';
import '../../core/services/push_notification_service.dart';
import '../../core/services/tenant_time_service.dart';
import '../../core/storage/secure_storage_service.dart';
import '../../core/storage/session_cache.dart';
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
    // When connectivity returns, re-validate a session that was restored from
    // the local cache while offline (below) — promote it to a fully verified
    // session, or drop it if the server now rejects the token.
    _connectivitySub = Connectivity().onConnectivityChanged.listen((results) {
      final online = results.any((r) => r != ConnectivityResult.none);
      if (online) unawaited(refreshSessionIfOffline());
    });
  }

  final AuthRepository _authRepository;
  final SecureStorageService _secureStorage;
  final ApiClient _apiClient;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySub;

  AuthStatus _status = AuthStatus.unknown;
  UserModel? _user;
  CompanyModel? _company;
  String? _errorMessage;

  /// True while the current signed-in state was restored from [SessionCache]
  /// because the server was unreachable at startup — the token has not been
  /// re-validated yet this launch. Cleared by [refreshSessionIfOffline] once
  /// the server confirms it.
  bool _offlineSession = false;
  bool get isOfflineSession => _offlineSession;

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
    if (_apiClient.activeTenantId != company?.id) {
      _apiClient.activeStoreId = null;
    }
    _company = company;
    _apiClient.activeTenantId = company?.id;
    TenantTimeService.instance.setTimezone(company?.timezone);
  }

  void _applyUser(UserModel? user) {
    _user = user;
    _apiClient.activeUserId = user?.id;
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

      try {
        final result = await _authRepository.session().timeout(
              const Duration(seconds: 6),
              onTimeout: () => throw ApiException('Session restore timed out'),
            );
        _applyUser(result.user);
        _applyCompany(result.company);
        await SessionCache.instance
            .save(user: result.user, company: result.company);
        _offlineSession = false;
        try {
          await BootstrapCache.instance
              .hydrate(forceRefresh: false, client: _apiClient);
        } catch (_) {}
        _status = AuthStatus.authenticated;
      } on ApiException catch (e) {
        // A real 401 means the token itself is dead — wipe everything and
        // fall back to the login screen (unchanged behaviour).
        if (e.isUnauthenticated) {
          await _secureStorage.clearToken();
          await SessionCache.instance.clear();
          _offlineSession = false;
          _status = AuthStatus.unauthenticated;
          return;
        }

        // Otherwise the server was simply unreachable (timeout / no route /
        // 5xx). If we have a cached session from a previous online run,
        // restore straight into it and keep working offline — the token is
        // retained and re-checked by [refreshSessionIfOffline] on reconnect.
        final cached = await SessionCache.instance.load();
        if (cached != null && cached.company.id.isNotEmpty) {
          _applyUser(cached.user);
          _applyCompany(cached.company);
          _offlineSession = true;
          try {
            await BootstrapCache.instance
                .hydrate(forceRefresh: false, client: _apiClient);
          } catch (_) {}
          _status = AuthStatus.authenticated;
          return;
        }

        // Token present but nothing cached and the server is unreachable —
        // we can't prove who this is, so show the login screen. The token is
        // kept so a normal restore works once connectivity returns.
        _status = AuthStatus.unauthenticated;
      }
    } catch (e) {
      debugPrint('AuthProvider.restoreSession error: $e');
      _status = AuthStatus.unauthenticated;
    } finally {
      notifyListeners();
    }
  }

  /// Re-validates a session that [restoreSession] restored from cache while
  /// offline. On success the session is promoted to fully verified; on a real
  /// 401 it is dropped; a still-unreachable server leaves it as-is for the
  /// next attempt. No-op unless we're currently in an offline session.
  Future<void> refreshSessionIfOffline() async {
    if (!_offlineSession || _status != AuthStatus.authenticated) return;
    try {
      final result = await _authRepository.session();
      _applyUser(result.user);
      _applyCompany(result.company);
      await SessionCache.instance
          .save(user: result.user, company: result.company);
      _offlineSession = false;
      try {
        await BootstrapCache.instance
            .hydrate(forceRefresh: true, client: _apiClient);
      } catch (_) {}
      notifyListeners();
    } on ApiException catch (e) {
      if (e.isUnauthenticated) {
        await _secureStorage.clearToken();
        await SessionCache.instance.clear();
        _offlineSession = false;
        _applyUser(null);
        _applyCompany(null);
        _status = AuthStatus.unauthenticated;
        notifyListeners();
      }
      // A still-unreachable server: keep the offline session, try again later.
    } catch (_) {}
  }

  /// Reloads session and refreshed company/user details from the server,
  /// updating SessionCache and BootstrapCache.
  Future<void> reloadSession() async {
    if (_status != AuthStatus.authenticated) return;
    try {
      final result = await _authRepository.session();
      _applyUser(result.user);
      _applyCompany(result.company);
      await SessionCache.instance
          .save(user: result.user, company: result.company);
      try {
        await BootstrapCache.instance
            .hydrate(forceRefresh: true, client: _apiClient);
      } catch (_) {}
      notifyListeners();
    } catch (_) {}
  }

  /// Mutates the active company in memory immediately (e.g. after uploading a logo)
  /// and persists it to SessionCache so changes reflect in the UI instantly.
  void updateCompany(CompanyModel Function(CompanyModel current) updater) {
    if (_company == null) return;
    final updated = updater(_company!);
    _applyCompany(updated);
    if (_user != null) {
      SessionCache.instance.save(user: _user, company: updated);
    }
    notifyListeners();
  }

  /// Set when a valid email+password belongs to an account that never
  /// completed email OTP verification. The login screen reads this (on a
  /// `false` return from [login]) and pushes the verify screen instead of
  /// showing an error snackbar. Cleared at the start of every [login].
  RegisterResult? _pendingEmailVerification;
  RegisterResult? get pendingEmailVerification => _pendingEmailVerification;

  Future<bool> login({
    required String email,
    required String password,
    String? accountId,
  }) async {
    _status = AuthStatus.authenticating;
    _errorMessage = null;
    _pendingEmailVerification = null;
    notifyListeners();

    try {
      final result = await _authRepository.login(
        email: email,
        password: password,
        accountId: accountId,
        fcmToken: PushNotificationService.instance.token,
      );

      if (result.requiresOtp) {
        _pendingEmailVerification = result;
        _status = AuthStatus.unauthenticated;
        notifyListeners();
        return false;
      }

      await _handleLoginSuccess(result.toLoginResult());
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    } catch (e, stackTrace) {
      debugPrint('AuthProvider.login unexpected error: $e');
      debugPrintStack(stackTrace: stackTrace);
      _errorMessage = 'Sign-in failed (${e.runtimeType}): $e';
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    }
  }

  Future<Map<String, dynamic>> checkSubdomain(String subdomain) async {
    return await _authRepository.checkSubdomain(subdomain);
  }

  Future<RegisterResult?> register({
    required String storeName,
    required String ownerName,
    required String email,
    required String password,
    String? phone,
    String? currency,
    String? country,
    String? timezone,
    String posMode = 'general',
    String? subdomain,
    String? customDomain,
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
        country: country,
        timezone: timezone,
        posMode: posMode,
        subdomain: subdomain,
        customDomain: customDomain,
      );

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
                if (country != null && country.isNotEmpty) 'country': country,
                if (timezone != null && timezone.isNotEmpty)
                  'timezone': timezone,
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

  Future<bool> loginWithToken(String token,
      {UserModel? user, CompanyModel? company}) async {
    _status = AuthStatus.authenticating;
    _errorMessage = null;
    notifyListeners();

    try {
      await _secureStorage.saveToken(token);
      if (user != null) _applyUser(user);
      if (company != null) _applyCompany(company);

      try {
        final session = await _authRepository.session();
        _applyCompany(session.company);
        if (session.user != null) _applyUser(session.user);
      } catch (_) {}

      try {
        await BootstrapCache.instance
            .hydrate(forceRefresh: true, client: _apiClient);
      } catch (_) {}

      if (_company != null) {
        await SessionCache.instance.save(user: _user, company: _company!);
      }
      _offlineSession = false;
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
    _applyUser(result.user);
    _applyCompany(result.company);

    // The login/register responses omit a few company fields (notably
    // pos_mode/restaurant_mode_locked) that only GET /auth/session
    // returns in full — refresh from there so mode-dependent UI (e.g.
    // the dashboard's retail vs. restaurant POS branch) is correct
    // immediately after signing in, not just after an app restart.
    try {
      final refreshed = await _authRepository.session();
      _applyCompany(refreshed.company);
      if (refreshed.user != null) _applyUser(refreshed.user);
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

    if (_company != null) {
      await SessionCache.instance.save(user: _user, company: _company!);
    }
    _offlineSession = false;
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
    _apiClient.activeStoreId = null;
    await onBeforeLogout?.call();
    await _secureStorage.clearToken();
    await SessionCache.instance.clear();
    _applyUser(null);
    _applyCompany(null);
    _offlineSession = false;
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  void _handleUnauthenticated() {
    if (_status != AuthStatus.authenticated) return;
    _apiClient.activeStoreId = null;
    _secureStorage.clearToken();
    unawaited(SessionCache.instance.clear());
    _applyUser(null);
    _applyCompany(null);
    _offlineSession = false;
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  @override
  void dispose() {
    _connectivitySub?.cancel();
    super.dispose();
  }
}
