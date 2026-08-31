import 'dart:convert';
import 'dart:io' show Directory, File, Platform;

import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:sqflite/sqflite.dart';

/// One queued offline sale — a row in `outbox_sync`, storing the exact
/// payload [SalesRepository.pushSalesBatch] will POST once connectivity
/// returns. Kept in the outbox until the server confirms it was ingested
/// (see [AppDatabase.markOutboxSynced]) so a crash or a second offline
/// stretch before the first sync never drops a sale.
class OutboxSale {
  OutboxSale({
    required this.id,
    required this.payload,
    required this.createdAt,
    required this.attemptCount,
    this.lastError,
  });

  final String id;
  final Map<String, dynamic> payload;
  final DateTime createdAt;
  final int attemptCount;
  final String? lastError;
}

/// The app's embedded local database — the offline-first foundation for
/// the POS module.
///
/// Two kinds of local state live here:
///  - Read-through caches (`cache_items`) of server data the POS screen
///    needs to keep working offline: products, categories, payment methods,
///    customers, tax rules. Every bucket is a full snapshot (matching the
///    non-delta shape of the endpoints that fill it), so a successful online
///    fetch just replaces the whole bucket.
///  - The `outbox_sync` queue of sales recorded while offline, pushed to the
///    server (idempotently, keyed by the client-generated sale id) as soon
///    as connectivity returns or the user taps "Sync Now".
///
/// Backed by `sqflite` on Android/iOS, where it has a real platform
/// implementation. On Windows/Linux, `sqflite` has no implementation at
/// all (an unhandled `MissingPluginException`), and `sqflite_common_ffi` —
/// the usual FFI-backed replacement — has proven unusable in this project's
/// Windows CI toolchain across two different sqlite3 major versions (both
/// fail identically: the Flutter kernel compiler can't resolve
/// sqflite_common_ffi's own package file, regardless of whether sqlite3's
/// native-assets build_hooks path is involved). Desktop instead uses a
/// plain JSON file per table, written with `dart:io` — no native or FFI
/// plugin involved, so nothing to fail to load. The data here never needs
/// relational queries, so this is a straight swap with no loss of
/// capability, just a different storage engine per platform.
///
/// A bare singleton (rather than threading an instance through every
/// repository constructor) since every caller just needs "the one local
/// database" — the same shape as how `SharedPreferences.getInstance()` is
/// already used elsewhere in this app.
class AppDatabase {
  AppDatabase._();

  static final AppDatabase instance = AppDatabase._();

  static bool get _usesJsonStore => Platform.isWindows || Platform.isLinux;

  // --- sqflite backend (Android/iOS) ---------------------------------------

  Database? _db;

  Future<Database> get _database async {
    return _db ??= await _open();
  }

  Future<Database> _open() async {
    final path = p.join(await getDatabasesPath(), 'zoom_pos_offline.db');
    return openDatabase(
      path,
      version: 1,
      onCreate: (db, version) async {
        await db.execute('''
          CREATE TABLE cache_items (
            bucket TEXT NOT NULL,
            id TEXT NOT NULL,
            data TEXT NOT NULL,
            PRIMARY KEY (bucket, id)
          )
        ''');
        await db.execute('''
          CREATE TABLE outbox_sync (
            id TEXT PRIMARY KEY,
            payload TEXT NOT NULL,
            created_at TEXT NOT NULL,
            is_synced INTEGER NOT NULL DEFAULT 0,
            synced_at TEXT,
            attempt_count INTEGER NOT NULL DEFAULT 0,
            last_error TEXT
          )
        ''');
        await db.execute('''
          CREATE TABLE sync_meta (
            key TEXT PRIMARY KEY,
            value TEXT
          )
        ''');
      },
    );
  }

  // --- JSON file backend (Windows/Linux) -----------------------------------

  Directory? _jsonDir;

  Future<Directory> get _jsonDirectory async {
    final existing = _jsonDir;
    if (existing != null) return existing;
    final base = await getApplicationSupportDirectory();
    final dir = Directory(p.join(base.path, 'zoom_pos_offline'));
    if (!await dir.exists()) {
      await dir.create(recursive: true);
    }
    return _jsonDir = dir;
  }

  Future<File> _jsonFile(String name) async {
    final dir = await _jsonDirectory;
    return File(p.join(dir.path, '$name.json'));
  }

  /// Reads [name].json, decoded as JSON, or [fallback] if the file doesn't
  /// exist yet (first run) or is empty.
  Future<dynamic> _readJson(String name, dynamic fallback) async {
    final file = await _jsonFile(name);
    if (!await file.exists()) return fallback;
    final text = await file.readAsString();
    if (text.trim().isEmpty) return fallback;
    return jsonDecode(text);
  }

  Future<void> _writeJson(String name, dynamic data) async {
    final file = await _jsonFile(name);
    await file.writeAsString(jsonEncode(data));
  }

  // --- Read-through catalog cache -----------------------------------------

  /// Replaces the entire contents of [bucket] (e.g. `'products'`,
  /// `'customers'`) with [items] — the caches here mirror full-snapshot
  /// endpoints, so there's no partial-delta merge to reconcile.
  Future<void> replaceCacheBucket(String bucket, List<Map<String, dynamic>> items) async {
    if (_usesJsonStore) {
      final all = (await _readJson('cache_items', <String, dynamic>{}) as Map).cast<String, dynamic>();
      final bucketMap = <String, dynamic>{};
      for (final item in items) {
        final id = item['id']?.toString();
        if (id == null) continue;
        bucketMap[id] = item;
      }
      all[bucket] = bucketMap;
      await _writeJson('cache_items', all);
      return;
    }

    final db = await _database;
    await db.transaction((txn) async {
      await txn.delete('cache_items', where: 'bucket = ?', whereArgs: [bucket]);
      final batch = txn.batch();
      for (final item in items) {
        final id = item['id']?.toString();
        if (id == null) continue;
        batch.insert(
          'cache_items',
          {'bucket': bucket, 'id': id, 'data': jsonEncode(item)},
          conflictAlgorithm: ConflictAlgorithm.replace,
        );
      }
      await batch.commit(noResult: true);
    });
  }

  Future<List<Map<String, dynamic>>> readCacheBucket(String bucket) async {
    if (_usesJsonStore) {
      final all = (await _readJson('cache_items', <String, dynamic>{}) as Map).cast<String, dynamic>();
      final bucketMap = all[bucket];
      if (bucketMap is! Map) return [];
      return bucketMap.values.map((item) => Map<String, dynamic>.from(item as Map)).toList();
    }

    final db = await _database;
    final rows = await db.query('cache_items', columns: ['data'], where: 'bucket = ?', whereArgs: [bucket]);
    return rows.map((row) => Map<String, dynamic>.from(jsonDecode(row['data'] as String) as Map)).toList();
  }

  // --- Offline sales outbox ------------------------------------------------

  Future<void> insertOutboxSale({
    required String id,
    required Map<String, dynamic> payload,
    required DateTime createdAt,
  }) async {
    if (_usesJsonStore) {
      final list = ((await _readJson('outbox_sync', <dynamic>[])) as List).cast<Map<String, dynamic>>();
      list.add({
        'id': id,
        'payload': payload,
        'created_at': createdAt.toIso8601String(),
        'is_synced': 0,
        'synced_at': null,
        'attempt_count': 0,
        'last_error': null,
      });
      await _writeJson('outbox_sync', list);
      return;
    }

    final db = await _database;
    await db.insert('outbox_sync', {
      'id': id,
      'payload': jsonEncode(payload),
      'created_at': createdAt.toIso8601String(),
      'is_synced': 0,
      'attempt_count': 0,
    });
  }

  Future<List<OutboxSale>> pendingOutboxSales() async {
    if (_usesJsonStore) {
      final list = ((await _readJson('outbox_sync', <dynamic>[])) as List).cast<Map<String, dynamic>>();
      final pending = list.where((row) => (row['is_synced'] as int? ?? 0) == 0).toList()
        ..sort((a, b) => (a['created_at'] as String).compareTo(b['created_at'] as String));
      return pending
          .map((row) => OutboxSale(
                id: row['id'] as String,
                payload: Map<String, dynamic>.from(row['payload'] as Map),
                createdAt: DateTime.parse(row['created_at'] as String),
                attemptCount: row['attempt_count'] as int? ?? 0,
                lastError: row['last_error'] as String?,
              ))
          .toList();
    }

    final db = await _database;
    final rows = await db.query('outbox_sync', where: 'is_synced = 0', orderBy: 'created_at ASC');
    return rows
        .map((row) => OutboxSale(
              id: row['id'] as String,
              payload: Map<String, dynamic>.from(jsonDecode(row['payload'] as String) as Map),
              createdAt: DateTime.parse(row['created_at'] as String),
              attemptCount: row['attempt_count'] as int? ?? 0,
              lastError: row['last_error'] as String?,
            ))
        .toList();
  }

  Future<int> unsyncedOutboxCount() async {
    if (_usesJsonStore) {
      final list = ((await _readJson('outbox_sync', <dynamic>[])) as List).cast<Map<String, dynamic>>();
      return list.where((row) => (row['is_synced'] as int? ?? 0) == 0).length;
    }

    final db = await _database;
    final result = await db.rawQuery('SELECT COUNT(*) AS count FROM outbox_sync WHERE is_synced = 0');
    return Sqflite.firstIntValue(result) ?? 0;
  }

  Future<void> markOutboxSynced(List<String> ids) async {
    if (ids.isEmpty) return;

    if (_usesJsonStore) {
      final list = ((await _readJson('outbox_sync', <dynamic>[])) as List).cast<Map<String, dynamic>>();
      final now = DateTime.now().toIso8601String();
      for (final row in list) {
        if (ids.contains(row['id'])) {
          row['is_synced'] = 1;
          row['synced_at'] = now;
        }
      }
      await _writeJson('outbox_sync', list);
      return;
    }

    final db = await _database;
    final now = DateTime.now().toIso8601String();
    final batch = db.batch();
    for (final id in ids) {
      batch.update(
        'outbox_sync',
        {'is_synced': 1, 'synced_at': now},
        where: 'id = ?',
        whereArgs: [id],
      );
    }
    await batch.commit(noResult: true);
  }

  Future<void> recordOutboxFailure(String id, String error) async {
    if (_usesJsonStore) {
      final list = ((await _readJson('outbox_sync', <dynamic>[])) as List).cast<Map<String, dynamic>>();
      for (final row in list) {
        if (row['id'] == id) {
          row['attempt_count'] = (row['attempt_count'] as int? ?? 0) + 1;
          row['last_error'] = error;
        }
      }
      await _writeJson('outbox_sync', list);
      return;
    }

    final db = await _database;
    await db.rawUpdate(
      'UPDATE outbox_sync SET attempt_count = attempt_count + 1, last_error = ? WHERE id = ?',
      [error, id],
    );
  }

  // --- Sync metadata (last-synced cursor, for UI + `since` pulls) ---------

  Future<void> setMeta(String key, String value) async {
    if (_usesJsonStore) {
      final map = (await _readJson('sync_meta', <String, dynamic>{}) as Map).cast<String, dynamic>();
      map[key] = value;
      await _writeJson('sync_meta', map);
      return;
    }

    final db = await _database;
    await db.insert('sync_meta', {'key': key, 'value': value}, conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<String?> getMeta(String key) async {
    if (_usesJsonStore) {
      final map = (await _readJson('sync_meta', <String, dynamic>{}) as Map).cast<String, dynamic>();
      return map[key] as String?;
    }

    final db = await _database;
    final rows = await db.query('sync_meta', where: 'key = ?', whereArgs: [key], limit: 1);
    if (rows.isEmpty) return null;
    return rows.first['value'] as String?;
  }
}
