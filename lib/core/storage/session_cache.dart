import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/company_model.dart';
import '../models/user_model.dart';

/// The last successfully-validated session, cached locally so the desktop app
/// can restore straight into the signed-in state when it starts with no
/// connectivity (see AuthProvider.restoreSession).
///
/// This is **only** a UI/gating convenience — it never grants access on its
/// own. The bearer token in the OS keystore is still the credential, and it
/// is still re-validated against `GET /auth/session` the moment the device is
/// back online. A real 401 clears both this cache and the token.
///
/// SharedPreferences-backed (same as BootstrapCache), keyed by a single JSON
/// blob so a write is atomic.
class SessionCache {
  SessionCache._();

  static final SessionCache instance = SessionCache._();

  static const _key = 'zoom_pos.session_cache.v1';

  CachedSession? _memory;

  /// Persists [user] + [company] as the current offline-restorable session.
  Future<void> save({UserModel? user, required CompanyModel company}) async {
    final session = CachedSession(
      user: user,
      company: company,
      savedAt: DateTime.now(),
    );
    _memory = session;
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_key, jsonEncode(session.toJson()));
    } catch (e) {
      debugPrint('SessionCache.save error: $e');
    }
  }

  /// The cached session, or null if none has ever been stored (or it was
  /// cleared by a logout / a real 401).
  Future<CachedSession?> load() async {
    if (_memory != null) return _memory;
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_key);
      if (raw == null || raw.isEmpty) return null;
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      return _memory =
          CachedSession.fromJson(Map<String, dynamic>.from(decoded));
    } catch (e) {
      debugPrint('SessionCache.load error: $e');
      return null;
    }
  }

  Future<void> clear() async {
    _memory = null;
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_key);
    } catch (e) {
      debugPrint('SessionCache.clear error: $e');
    }
  }
}

class CachedSession {
  CachedSession({
    required this.user,
    required this.company,
    required this.savedAt,
  });

  final UserModel? user;
  final CompanyModel company;
  final DateTime savedAt;

  Map<String, dynamic> toJson() => {
        'user': user?.toJson(),
        'company': company.toJson(),
        'saved_at': savedAt.toIso8601String(),
      };

  factory CachedSession.fromJson(Map<String, dynamic> json) => CachedSession(
        user: json['user'] is Map
            ? UserModel.fromJson(Map<String, dynamic>.from(json['user'] as Map))
            : null,
        company: CompanyModel.fromJson(
            Map<String, dynamic>.from((json['company'] as Map?) ?? const {})),
        savedAt: DateTime.tryParse((json['saved_at'] as String?) ?? '') ??
            DateTime.now(),
      );
}
