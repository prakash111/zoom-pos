import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Wraps the OS keystore/keychain for the one secret the app holds: the
/// tenant API token minted by POST /auth/login (a `zk_live_...` TenantApiKey
/// token, or a legacy TenantSession token — the server treats both the same
/// way behind a single Bearer header).
class SecureStorageService {
  SecureStorageService()
      : _storage = const FlutterSecureStorage(
          aOptions: AndroidOptions(
            encryptedSharedPreferences: true,
            resetOnError: true,
          ),
          iOptions: IOSOptions(
            accessibility: KeychainAccessibility.first_unlock,
          ),
        );

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'zoom_pos.auth_token';

  Future<void> saveToken(String token) async {
    try {
      await _storage.write(key: _tokenKey, value: token);
    } catch (e) {
      debugPrint('SecureStorageService.saveToken error: $e');
      try {
        await _storage.deleteAll();
        await _storage.write(key: _tokenKey, value: token);
      } catch (_) {}
    }
  }

  Future<String?> readToken() async {
    try {
      return await _storage.read(key: _tokenKey);
    } catch (e) {
      debugPrint('SecureStorageService.readToken error: $e');
      try {
        await _storage.deleteAll();
      } catch (_) {}
      return null;
    }
  }

  Future<void> clearToken() async {
    try {
      await _storage.delete(key: _tokenKey);
    } catch (e) {
      debugPrint('SecureStorageService.clearToken error: $e');
      try {
        await _storage.deleteAll();
      } catch (_) {}
    }
  }
}
