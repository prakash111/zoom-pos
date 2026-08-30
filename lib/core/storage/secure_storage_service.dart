import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Wraps the OS keystore/keychain for the one secret the app holds: the
/// tenant API token minted by POST /auth/login (a `zk_live_...` TenantApiKey
/// token, or a legacy TenantSession token — the server treats both the same
/// way behind a single Bearer header).
class SecureStorageService {
  SecureStorageService() : _storage = const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'zoom_pos.auth_token';

  Future<void> saveToken(String token) => _storage.write(key: _tokenKey, value: token);

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<void> clearToken() => _storage.delete(key: _tokenKey);
}
