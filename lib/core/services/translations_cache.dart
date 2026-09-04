import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../api/api_client.dart';
import '../config/app_config.dart';

/// Persistent, version-aware storage for dictionaries delivered by Laravel.
class TranslationsCache {
  TranslationsCache._();

  static final TranslationsCache instance = TranslationsCache._();
  static const _cacheKeyPrefix = 'zoom_pos.translations.v2.';

  final Map<String, Map<String, String>> _byLocale = {};
  final Map<String, String> _versions = {};

  Map<String, String> forLocale(String locale) =>
      _byLocale[_normalize(locale)] ?? const {};

  String? versionFor(String locale) => _versions[_normalize(locale)];

  Future<void> loadFromDisk(String locale) async {
    final code = _normalize(locale);
    if (_byLocale.containsKey(code)) return;

    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString('$_cacheKeyPrefix$code');
      if (raw == null) {
        _byLocale[code] = const {};
        return;
      }

      final decoded = jsonDecode(raw);
      if (decoded is! Map) return;
      final envelope = Map<String, dynamic>.from(decoded);
      final rawStrings = envelope['strings'] is Map
          ? Map<String, dynamic>.from(envelope['strings'] as Map)
          : envelope;
      _byLocale[code] = rawStrings.map(
        (key, value) => MapEntry(key, value.toString()),
      );
      final version = envelope['version']?.toString();
      if (version != null && version.isNotEmpty) _versions[code] = version;
    } catch (_) {
      _byLocale[code] = const {};
    }
  }

  Future<bool> refresh(String locale, ApiClient client) async {
    final code = _normalize(locale);
    await loadFromDisk(code);

    try {
      final response = await client.getAbsolute(
        ApiEndpoints.appTranslationsAbsolute,
        query: {
          'lang': code,
          if (_versions[code] != null) 'version': _versions[code],
        },
      );
      if (response['not_modified'] == true) return false;
      final raw = response['translations'];
      if (raw is! Map) return false;
      await applyFetched(
        code,
        raw.map((key, value) => MapEntry(key.toString(), value.toString())),
        version:
            response['version']?.toString() ?? response['etag']?.toString(),
      );
      return true;
    } catch (_) {
      return false;
    }
  }

  Future<void> applyFetched(
    String locale,
    Map<String, String> translations, {
    String? version,
  }) async {
    final code = _normalize(locale);
    _byLocale[code] = Map.unmodifiable(translations);
    if (version != null && version.isNotEmpty) _versions[code] = version;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      '$_cacheKeyPrefix$code',
      jsonEncode({
        'version': _versions[code],
        'strings': translations,
      }),
    );
  }

  static String _normalize(String locale) =>
      locale.trim().toLowerCase().split(RegExp('[-_]')).first;
}
