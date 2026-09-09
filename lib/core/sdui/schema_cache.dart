import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Read-through cache of server-driven UI schema responses (the JSON layout
/// `DynamicSchemaPage` renders). Every successful `GET <endpoint>` for a
/// schema is stored here so the same screen still opens — from its last known
/// layout — when the device is offline.
///
/// One SharedPreferences key holds a `{ normalizedEndpoint: {schema, cached_at} }`
/// map so a write stays atomic. Entries are keyed by the endpoint **path only**
/// (query string stripped) so a filtered re-open (`?search=…`) still resolves
/// to the base screen offline. The map is capped — the oldest entries are
/// evicted once it grows past [_maxEntries].
class SchemaCache {
  SchemaCache._();

  static final SchemaCache instance = SchemaCache._();

  static const _key = 'zoom_pos.sdui_schema_cache.v1';
  static const _maxEntries = 80;

  Map<String, dynamic>? _memory;

  /// Reduces an endpoint (absolute URL or `/api/...` path, with or without a
  /// query string) to a stable cache key: the path alone.
  static String normalizeKey(String endpoint) {
    final uri = Uri.tryParse(endpoint.trim());
    if (uri == null) return endpoint.trim();
    return uri.path.isEmpty ? endpoint.trim() : uri.path;
  }

  Future<Map<String, dynamic>> _all() async {
    if (_memory != null) return _memory!;
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_key);
      if (raw == null || raw.isEmpty) return _memory = {};
      final decoded = jsonDecode(raw);
      return _memory =
          decoded is Map ? Map<String, dynamic>.from(decoded) : {};
    } catch (e) {
      debugPrint('SchemaCache read error: $e');
      return _memory = {};
    }
  }

  /// Stores [schema] as the last-known layout for [endpoint].
  Future<void> put(String endpoint, Map<String, dynamic> schema) async {
    final key = normalizeKey(endpoint);
    if (key.isEmpty) return;
    try {
      final all = await _all();
      all[key] = {
        'schema': schema,
        'cached_at': DateTime.now().toIso8601String(),
      };

      if (all.length > _maxEntries) {
        final entries = all.entries.toList()
          ..sort((a, b) {
            final at = DateTime.tryParse(
                    (a.value as Map?)?['cached_at']?.toString() ?? '') ??
                DateTime.fromMillisecondsSinceEpoch(0);
            final bt = DateTime.tryParse(
                    (b.value as Map?)?['cached_at']?.toString() ?? '') ??
                DateTime.fromMillisecondsSinceEpoch(0);
            return at.compareTo(bt);
          });
        for (final stale
            in entries.take(all.length - _maxEntries).map((e) => e.key)) {
          all.remove(stale);
        }
      }

      _memory = all;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_key, jsonEncode(all));
    } catch (e) {
      debugPrint('SchemaCache.put error: $e');
    }
  }

  /// The last-known schema for [endpoint], or null if this screen has never
  /// been loaded online on this device.
  Future<CachedSchema?> get(String endpoint) async {
    final key = normalizeKey(endpoint);
    final all = await _all();
    final entry = all[key];
    if (entry is! Map) return null;
    final rawSchema = entry['schema'];
    if (rawSchema is! Map) return null;
    return CachedSchema(
      schema: Map<String, dynamic>.from(rawSchema),
      cachedAt: DateTime.tryParse(entry['cached_at']?.toString() ?? '') ??
          DateTime.fromMillisecondsSinceEpoch(0),
    );
  }

  Future<void> clear() async {
    _memory = null;
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_key);
    } catch (e) {
      debugPrint('SchemaCache.clear error: $e');
    }
  }
}

class CachedSchema {
  CachedSchema({required this.schema, required this.cachedAt});

  final Map<String, dynamic> schema;
  final DateTime cachedAt;
}
