import 'dart:convert';

import 'package:path/path.dart' as p;
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

/// The app's embedded local database (sqflite) — the offline-first
/// foundation for the POS module.
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
/// A bare singleton (rather than threading an instance through every
/// repository constructor) since every caller just needs "the one local
/// database" — the same shape as how `SharedPreferences.getInstance()` is
/// already used elsewhere in this app.
class AppDatabase {
  AppDatabase._();

  static final AppDatabase instance = AppDatabase._();

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

  // --- Read-through catalog cache -----------------------------------------

  /// Replaces the entire contents of [bucket] (e.g. `'products'`,
  /// `'customers'`) with [items] — the caches here mirror full-snapshot
  /// endpoints, so there's no partial-delta merge to reconcile.
  Future<void> replaceCacheBucket(String bucket, List<Map<String, dynamic>> items) async {
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
    final db = await _database;
    final result = await db.rawQuery('SELECT COUNT(*) AS count FROM outbox_sync WHERE is_synced = 0');
    return Sqflite.firstIntValue(result) ?? 0;
  }

  Future<void> markOutboxSynced(List<String> ids) async {
    if (ids.isEmpty) return;
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
    final db = await _database;
    await db.rawUpdate(
      'UPDATE outbox_sync SET attempt_count = attempt_count + 1, last_error = ? WHERE id = ?',
      [error, id],
    );
  }

  // --- Sync metadata (last-synced cursor, for UI + `since` pulls) ---------

  Future<void> setMeta(String key, String value) async {
    final db = await _database;
    await db.insert('sync_meta', {'key': key, 'value': value}, conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<String?> getMeta(String key) async {
    final db = await _database;
    final rows = await db.query('sync_meta', where: 'key = ?', whereArgs: [key], limit: 1);
    if (rows.isEmpty) return null;
    return rows.first['value'] as String?;
  }
}
