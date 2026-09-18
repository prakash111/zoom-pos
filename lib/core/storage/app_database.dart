import 'dart:convert';
import 'dart:io' show Directory, File, Platform;

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
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

/// One queued offline mutation — a row in `outbox_mutations`, the generic
/// counterpart to [OutboxSale] that covers every non-sale write the desktop
/// app makes while disconnected (create/edit/delete of products, customers,
/// categories, brands, suppliers, units, quotations, plus stock adjustments
/// and customer-ledger payments).
///
/// The [SyncEngine] groups pending rows by ([op], [entity]) into the
/// `created_<plural>` / `deleted_<plural>` / `inventory_adjustments` /
/// `customer_payments` arrays that `POST /sync-batch` already understands
/// (see PosSyncApiController::syncBatch). [externalId] is the client-generated
/// UUID the row is known by locally and is used verbatim as the server-side
/// idempotency key, so replaying a batch never duplicates a record.
class OutboxMutation {
  OutboxMutation({
    required this.id,
    required this.op,
    required this.entity,
    required this.externalId,
    required this.endpoint,
    required this.method,
    required this.payload,
    required this.createdAt,
    this.baseUpdatedAt,
    this.status = OutboxStatus.pending,
    this.attemptCount = 0,
    this.lastError,
    this.serverId,
    this.syncedAt,
  });

  /// Client UUID — also the idempotency key sent to the server.
  final String id;

  /// 'create' | 'update' | 'delete'.
  final String op;

  /// 'product' | 'customer' | 'category' | 'brand' | 'supplier' | 'unit' |
  /// 'quotation' | 'adjustment' | 'payment'.
  final String entity;

  /// The local UUID of the row this mutation targets. For a `create` this is
  /// the same UUID the new local row was inserted with.
  final String externalId;

  /// The `/api/v1/pos` path this mutation would have hit online — recorded
  /// so a future generic replay (SDUI actions) can use it; the typed
  /// entities are routed through `/sync-batch` instead.
  final String endpoint;

  /// 'POST' | 'PUT' | 'DELETE'.
  final String method;

  /// The request body that would have been sent online.
  final Map<String, dynamic> payload;

  /// `updated_at` of the cached row at the moment the edit was made — feeds
  /// the server's last-write-wins guard (`clientRowIsNewer`). Null for a
  /// brand-new offline create.
  final DateTime? baseUpdatedAt;

  final String status;
  final int attemptCount;
  final String? lastError;

  /// Server primary key, filled in from the `sync-batch` response `id_map`
  /// once the mutation has been accepted.
  final String? serverId;
  final DateTime createdAt;
  final DateTime? syncedAt;

  Map<String, dynamic> toJson() => {
        'id': id,
        'op': op,
        'entity': entity,
        'external_id': externalId,
        'endpoint': endpoint,
        'method': method,
        'payload': payload,
        'base_updated_at': baseUpdatedAt?.toIso8601String(),
        'status': status,
        'attempt_count': attemptCount,
        'last_error': lastError,
        'server_id': serverId,
        'created_at': createdAt.toIso8601String(),
        'synced_at': syncedAt?.toIso8601String(),
      };

  factory OutboxMutation.fromJson(Map<String, dynamic> row) => OutboxMutation(
        id: row['id'] as String,
        op: row['op'] as String,
        entity: row['entity'] as String,
        externalId: row['external_id'] as String,
        endpoint: (row['endpoint'] as String?) ?? '',
        method: (row['method'] as String?) ?? 'POST',
        payload: Map<String, dynamic>.from(
            (row['payload'] as Map?) ?? const <String, dynamic>{}),
        baseUpdatedAt: row['base_updated_at'] != null
            ? DateTime.tryParse(row['base_updated_at'] as String)
            : null,
        status: (row['status'] as String?) ?? OutboxStatus.pending,
        attemptCount: (row['attempt_count'] as num?)?.toInt() ?? 0,
        lastError: row['last_error'] as String?,
        serverId: row['server_id']?.toString(),
        createdAt: DateTime.tryParse((row['created_at'] as String?) ?? '') ??
            DateTime.now(),
        syncedAt: row['synced_at'] != null
            ? DateTime.tryParse(row['synced_at'] as String)
            : null,
      );
}

/// Lifecycle of an [OutboxMutation]. `inflight` is the crash-recovery
/// journal: a row is flipped to `inflight` immediately before a `/sync-batch`
/// POST and back to `synced` only once the server acknowledges its
/// `external_id`. Anything still `inflight` at the next app start (the app
/// died mid-push) is reset to `pending` and retried — the server's
/// idempotency guarantee makes the replay harmless.
class OutboxStatus {
  OutboxStatus._();

  static const String pending = 'pending';
  static const String inflight = 'inflight';
  static const String synced = 'synced';
  static const String failed = 'failed';
}

/// The app's embedded local database — the offline-first foundation for the
/// whole desktop app (was: just the POS module).
///
/// Three kinds of local state live here:
///  - Read-through caches (`cache_items`) of server data every screen needs
///    to keep working offline: products, categories, payment methods,
///    customers, tax rules, brands, suppliers, units, quotations, sales.
///    A first sync replaces the whole bucket ([replaceCacheBucket]); every
///    delta sync afterwards merges rows in ([upsertCacheItems]) and prunes
///    server-side deletions ([deleteCacheItems]).
///  - The `outbox_sync` queue of sales recorded while offline (unchanged).
///  - The `outbox_mutations` queue of every other offline write, replayed
///    through `/sync-batch` once connectivity returns or the user taps
///    "Sync Now".
///  - `sync_meta` — the last-synced cursor / timestamps the status UI and
///    the delta `?since=` pulls read.
///
/// Backed by `sqflite` on Android/iOS. On Windows/Linux, `sqflite` has no
/// implementation and its FFI replacement has proven unusable in this
/// project's Windows toolchain, so desktop uses a plain JSON file per table
/// (`dart:io`, no native/FFI plugin). The data here never needs relational
/// queries, so this is a straight swap.
class AppDatabase {
  AppDatabase._();

  static final AppDatabase instance = AppDatabase._();

  static bool get _usesJsonStore =>
      kIsWeb || (!kIsWeb && (Platform.isWindows || Platform.isLinux));

  /// Bump when the sqflite schema changes (see [_open] onUpgrade). The JSON
  /// store is schemaless so it ignores this.
  static const int _schemaVersion = 2;

  // --- sqflite backend (Android/iOS) ---------------------------------------

  Database? _db;

  Future<Database> get _database async {
    return _db ??= await _open();
  }

  Future<Database> _open() async {
    final path = p.join(await getDatabasesPath(), 'zoom_pos_offline.db');
    return openDatabase(
      path,
      version: _schemaVersion,
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
        await _createOutboxMutations(db);
      },
      onUpgrade: (db, oldVersion, newVersion) async {
        if (oldVersion < 2) {
          await _createOutboxMutations(db);
        }
      },
    );
  }

  Future<void> _createOutboxMutations(Database db) async {
    await db.execute('''
      CREATE TABLE IF NOT EXISTS outbox_mutations (
        id TEXT PRIMARY KEY,
        op TEXT NOT NULL,
        entity TEXT NOT NULL,
        external_id TEXT NOT NULL,
        endpoint TEXT NOT NULL DEFAULT '',
        method TEXT NOT NULL DEFAULT 'POST',
        payload TEXT NOT NULL,
        base_updated_at TEXT,
        status TEXT NOT NULL DEFAULT 'pending',
        attempt_count INTEGER NOT NULL DEFAULT 0,
        last_error TEXT,
        server_id TEXT,
        created_at TEXT NOT NULL,
        synced_at TEXT
      )
    ''');
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
    if (kIsWeb) {
      final prefs = await SharedPreferences.getInstance();
      final text = prefs.getString('offline_db_$name');
      if (text == null || text.trim().isEmpty) return fallback;
      try {
        return jsonDecode(text);
      } catch (_) {
        return fallback;
      }
    }
    final file = await _jsonFile(name);
    if (!await file.exists()) return fallback;
    final text = await file.readAsString();
    if (text.trim().isEmpty) return fallback;
    return jsonDecode(text);
  }

  /// Writes [name].json atomically: the encoded payload goes to a `.tmp`
  /// sibling first and is then renamed over the target, so a crash mid-write
  /// can never leave a half-written (unparseable) file — the previous good
  /// copy stays intact until the rename completes.
  Future<void> _writeJson(String name, dynamic data) async {
    if (kIsWeb) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('offline_db_$name', jsonEncode(data));
      return;
    }
    final file = await _jsonFile(name);
    final tmp = File('${file.path}.tmp');
    await tmp.writeAsString(jsonEncode(data), flush: true);
    await tmp.rename(file.path);
  }

  Future<List<Map<String, dynamic>>> _readJsonList(String name) async {
    final raw = await _readJson(name, <dynamic>[]);
    if (raw is! List) return [];
    return raw
        .whereType<Map>()
        .map((row) => Map<String, dynamic>.from(row))
        .toList();
  }

  // --- Read-through catalog cache -----------------------------------------

  /// Replaces the entire contents of [bucket] with [items] — used for the
  /// very first sync of a bucket (or a "reset & re-download"), where the
  /// server sent a full snapshot rather than a delta.
  Future<void> replaceCacheBucket(
      String bucket, List<Map<String, dynamic>> items) async {
    if (_usesJsonStore) {
      final all = (await _readJson('cache_items', <String, dynamic>{}) as Map)
          .cast<String, dynamic>();
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

  /// Merges [items] into [bucket] by `id`, leaving every other row untouched
  /// — the delta-sync path (`GET /sync-pull?since=`). A row already present
  /// with the same `id` is overwritten with the server's newer copy.
  Future<void> upsertCacheItems(
      String bucket, List<Map<String, dynamic>> items) async {
    if (items.isEmpty) return;

    if (_usesJsonStore) {
      final all = (await _readJson('cache_items', <String, dynamic>{}) as Map)
          .cast<String, dynamic>();
      final bucketMap = (all[bucket] is Map)
          ? Map<String, dynamic>.from(all[bucket] as Map)
          : <String, dynamic>{};
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
    final batch = db.batch();
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
  }

  /// Removes rows from [bucket] whose `id` is in [ids] — the server-side
  /// deletions reported by `sync-pull`'s `deleted_ids`.
  Future<void> deleteCacheItems(String bucket, List<String> ids) async {
    if (ids.isEmpty) return;
    final idSet = ids.toSet();

    if (_usesJsonStore) {
      final all = (await _readJson('cache_items', <String, dynamic>{}) as Map)
          .cast<String, dynamic>();
      final bucketMap = all[bucket];
      if (bucketMap is! Map) return;
      final next = Map<String, dynamic>.from(bucketMap)
        ..removeWhere((key, _) => idSet.contains(key));
      all[bucket] = next;
      await _writeJson('cache_items', all);
      return;
    }

    final db = await _database;
    final batch = db.batch();
    for (final id in idSet) {
      batch.delete('cache_items',
          where: 'bucket = ? AND id = ?', whereArgs: [bucket, id]);
    }
    await batch.commit(noResult: true);
  }

  Future<List<Map<String, dynamic>>> readCacheBucket(String bucket) async {
    if (_usesJsonStore) {
      final all = (await _readJson('cache_items', <String, dynamic>{}) as Map)
          .cast<String, dynamic>();
      final bucketMap = all[bucket];
      if (bucketMap is! Map) return [];
      return bucketMap.values
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
    }

    final db = await _database;
    final rows = await db.query('cache_items',
        columns: ['data'], where: 'bucket = ?', whereArgs: [bucket]);
    return rows
        .map((row) =>
            Map<String, dynamic>.from(jsonDecode(row['data'] as String) as Map))
        .toList();
  }

  /// A single cached row by its `id`, or null — for the read-through fetch
  /// of a detail screen while offline.
  Future<Map<String, dynamic>?> readCacheItem(String bucket, String id) async {
    if (_usesJsonStore) {
      final all = (await _readJson('cache_items', <String, dynamic>{}) as Map)
          .cast<String, dynamic>();
      final bucketMap = all[bucket];
      if (bucketMap is! Map) return null;
      final row = bucketMap[id];
      return row is Map ? Map<String, dynamic>.from(row) : null;
    }

    final db = await _database;
    final rows = await db.query('cache_items',
        columns: ['data'],
        where: 'bucket = ? AND id = ?',
        whereArgs: [bucket, id],
        limit: 1);
    if (rows.isEmpty) return null;
    return Map<String, dynamic>.from(
        jsonDecode(rows.first['data'] as String) as Map);
  }

  // --- Offline sales outbox (unchanged) ---------------------------------

  Future<void> insertOutboxSale({
    required String id,
    required Map<String, dynamic> payload,
    required DateTime createdAt,
  }) async {
    if (_usesJsonStore) {
      final list = await _readJsonList('outbox_sync');
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
      final list = await _readJsonList('outbox_sync');
      final pending =
          list.where((row) => (row['is_synced'] as int? ?? 0) == 0).toList()
            ..sort((a, b) => (a['created_at'] as String)
                .compareTo(b['created_at'] as String));
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
    final rows = await db.query('outbox_sync',
        where: 'is_synced = 0', orderBy: 'created_at ASC');
    return rows
        .map((row) => OutboxSale(
              id: row['id'] as String,
              payload: Map<String, dynamic>.from(
                  jsonDecode(row['payload'] as String) as Map),
              createdAt: DateTime.parse(row['created_at'] as String),
              attemptCount: row['attempt_count'] as int? ?? 0,
              lastError: row['last_error'] as String?,
            ))
        .toList();
  }

  Future<int> unsyncedOutboxCount() async {
    if (_usesJsonStore) {
      final list = await _readJsonList('outbox_sync');
      return list.where((row) => (row['is_synced'] as int? ?? 0) == 0).length;
    }

    final db = await _database;
    final result = await db
        .rawQuery('SELECT COUNT(*) AS count FROM outbox_sync WHERE is_synced = 0');
    return Sqflite.firstIntValue(result) ?? 0;
  }

  Future<void> markOutboxSynced(List<String> ids) async {
    if (ids.isEmpty) return;

    if (_usesJsonStore) {
      final list = await _readJsonList('outbox_sync');
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
      final list = await _readJsonList('outbox_sync');
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

  // --- Generic offline mutations outbox --------------------------------

  /// Queues one offline write. Same-row collapsing keeps the outbox tight:
  ///  - an offline create then edit of the same row → only the latest
  ///    payload is replayed;
  ///  - deleting a row that was created offline and never synced → the
  ///    pending create is dropped and nothing is queued at all (there is
  ///    no server row to remove).
  /// Adjustments and payments are additive events, never collapsed.
  Future<void> enqueueMutation(OutboxMutation mutation) async {
    final collapsible =
        mutation.entity != 'adjustment' && mutation.entity != 'payment';

    if (_usesJsonStore) {
      final list = await _readJsonList('outbox_mutations');
      var priorWasUnsyncedCreate = false;
      if (collapsible) {
        list.removeWhere((row) {
          final match = row['external_id'] == mutation.externalId &&
              row['entity'] == mutation.entity &&
              (row['status'] as String? ?? OutboxStatus.pending) !=
                  OutboxStatus.synced;
          if (match && row['op'] == 'create') priorWasUnsyncedCreate = true;
          return match;
        });
      }
      if (!(mutation.op == 'delete' && priorWasUnsyncedCreate)) {
        list.add(mutation.toJson());
      }
      await _writeJson('outbox_mutations', list);
      return;
    }

    final db = await _database;
    var priorWasUnsyncedCreate = false;
    if (collapsible) {
      final prior = await db.query('outbox_mutations',
          columns: ['op'],
          where: 'external_id = ? AND entity = ? AND status != ?',
          whereArgs: [mutation.externalId, mutation.entity, OutboxStatus.synced]);
      priorWasUnsyncedCreate = prior.any((r) => r['op'] == 'create');
      await db.delete('outbox_mutations',
          where: 'external_id = ? AND entity = ? AND status != ?',
          whereArgs: [
            mutation.externalId,
            mutation.entity,
            OutboxStatus.synced
          ]);
    }
    if (!(mutation.op == 'delete' && priorWasUnsyncedCreate)) {
      await db.insert('outbox_mutations', _mutationRow(mutation),
          conflictAlgorithm: ConflictAlgorithm.replace);
    }
  }

  Map<String, Object?> _mutationRow(OutboxMutation m) => {
        'id': m.id,
        'op': m.op,
        'entity': m.entity,
        'external_id': m.externalId,
        'endpoint': m.endpoint,
        'method': m.method,
        'payload': jsonEncode(m.payload),
        'base_updated_at': m.baseUpdatedAt?.toIso8601String(),
        'status': m.status,
        'attempt_count': m.attemptCount,
        'last_error': m.lastError,
        'server_id': m.serverId,
        'created_at': m.createdAt.toIso8601String(),
        'synced_at': m.syncedAt?.toIso8601String(),
      };

  /// Every mutation not yet accepted by the server, oldest first (FIFO
  /// replay order preserves create-before-edit dependencies).
  Future<List<OutboxMutation>> pendingMutations() async {
    if (_usesJsonStore) {
      final list = await _readJsonList('outbox_mutations');
      list.sort((a, b) => (a['created_at'] as String? ?? '')
          .compareTo(b['created_at'] as String? ?? ''));
      return list
          .where((row) =>
              (row['status'] as String? ?? OutboxStatus.pending) !=
              OutboxStatus.synced)
          .map(OutboxMutation.fromJson)
          .toList();
    }

    final db = await _database;
    final rows = await db.query('outbox_mutations',
        where: 'status != ?',
        whereArgs: [OutboxStatus.synced],
        orderBy: 'created_at ASC');
    return rows.map((row) {
      final decoded = Map<String, dynamic>.from(row);
      decoded['payload'] = jsonDecode(row['payload'] as String);
      return OutboxMutation.fromJson(decoded);
    }).toList();
  }

  Future<int> pendingMutationCount() async {
    if (_usesJsonStore) {
      final list = await _readJsonList('outbox_mutations');
      return list
          .where((row) =>
              (row['status'] as String? ?? OutboxStatus.pending) !=
              OutboxStatus.synced)
          .length;
    }

    final db = await _database;
    final result = await db.rawQuery(
        'SELECT COUNT(*) AS count FROM outbox_mutations WHERE status != ?',
        [OutboxStatus.synced]);
    return Sqflite.firstIntValue(result) ?? 0;
  }

  Future<void> markMutationsInflight(List<String> ids) async {
    await _setMutationStatus(ids, OutboxStatus.inflight);
  }

  /// Marks the given mutation ids accepted by the server, stamping each with
  /// its [serverIds] entry (from the `/sync-batch` `id_map`) when present.
  Future<void> markMutationsSynced(
      List<String> ids, Map<String, String> serverIds) async {
    if (ids.isEmpty) return;
    final now = DateTime.now().toIso8601String();

    if (_usesJsonStore) {
      final list = await _readJsonList('outbox_mutations');
      for (final row in list) {
        if (ids.contains(row['id'])) {
          row['status'] = OutboxStatus.synced;
          row['synced_at'] = now;
          row['last_error'] = null;
          final serverId = serverIds[row['external_id']];
          if (serverId != null) row['server_id'] = serverId;
        }
      }
      await _writeJson('outbox_mutations', list);
      return;
    }

    final db = await _database;
    final batch = db.batch();
    for (final id in ids) {
      batch.rawUpdate(
        'UPDATE outbox_mutations SET status = ?, synced_at = ?, last_error = NULL WHERE id = ?',
        [OutboxStatus.synced, now, id],
      );
    }
    await batch.commit(noResult: true);
    for (final entry in serverIds.entries) {
      await db.update('outbox_mutations', {'server_id': entry.value},
          where: 'external_id = ?', whereArgs: [entry.key]);
    }
  }

  Future<void> recordMutationFailure(List<String> ids, String error) async {
    if (ids.isEmpty) return;

    if (_usesJsonStore) {
      final list = await _readJsonList('outbox_mutations');
      for (final row in list) {
        if (ids.contains(row['id'])) {
          row['status'] = OutboxStatus.failed;
          row['attempt_count'] = ((row['attempt_count'] as num?)?.toInt() ?? 0) + 1;
          row['last_error'] = error;
        }
      }
      await _writeJson('outbox_mutations', list);
      return;
    }

    final db = await _database;
    final batch = db.batch();
    for (final id in ids) {
      batch.rawUpdate(
        'UPDATE outbox_mutations SET status = ?, attempt_count = attempt_count + 1, last_error = ? WHERE id = ?',
        [OutboxStatus.failed, error, id],
      );
    }
    await batch.commit(noResult: true);
  }

  /// Crash recovery — call once at startup. Anything left `inflight` (the app
  /// died mid-push) or parked as `failed` from a previous session is put back
  /// to `pending` so the next sync retries it. Idempotent server-side.
  Future<void> resetStuckMutations() async {
    if (_usesJsonStore) {
      final list = await _readJsonList('outbox_mutations');
      var changed = false;
      for (final row in list) {
        final status = row['status'] as String? ?? OutboxStatus.pending;
        if (status == OutboxStatus.inflight || status == OutboxStatus.failed) {
          row['status'] = OutboxStatus.pending;
          changed = true;
        }
      }
      if (changed) await _writeJson('outbox_mutations', list);
      return;
    }

    final db = await _database;
    await db.rawUpdate(
      'UPDATE outbox_mutations SET status = ? WHERE status IN (?, ?)',
      [OutboxStatus.pending, OutboxStatus.inflight, OutboxStatus.failed],
    );
  }

  /// Housekeeping — drop `synced` mutation rows older than [keep].
  Future<void> pruneSyncedMutations({Duration keep = const Duration(days: 3)}) async {
    final cutoff = DateTime.now().subtract(keep).toIso8601String();

    if (_usesJsonStore) {
      final list = await _readJsonList('outbox_mutations');
      list.removeWhere((row) =>
          (row['status'] as String? ?? '') == OutboxStatus.synced &&
          (row['synced_at'] as String? ?? '9999').compareTo(cutoff) < 0);
      await _writeJson('outbox_mutations', list);
      return;
    }

    final db = await _database;
    await db.delete('outbox_mutations',
        where: 'status = ? AND synced_at IS NOT NULL AND synced_at < ?',
        whereArgs: [OutboxStatus.synced, cutoff]);
  }

  Future<void> _setMutationStatus(List<String> ids, String status) async {
    if (ids.isEmpty) return;

    if (_usesJsonStore) {
      final list = await _readJsonList('outbox_mutations');
      for (final row in list) {
        if (ids.contains(row['id'])) row['status'] = status;
      }
      await _writeJson('outbox_mutations', list);
      return;
    }

    final db = await _database;
    final batch = db.batch();
    for (final id in ids) {
      batch.update('outbox_mutations', {'status': status},
          where: 'id = ?', whereArgs: [id]);
    }
    await batch.commit(noResult: true);
  }

  // --- Sync metadata (last-synced cursor, for UI + `since` pulls) ---------

  Future<void> setMeta(String key, String value) async {
    if (_usesJsonStore) {
      final map = (await _readJson('sync_meta', <String, dynamic>{}) as Map)
          .cast<String, dynamic>();
      map[key] = value;
      await _writeJson('sync_meta', map);
      return;
    }

    final db = await _database;
    await db.insert('sync_meta', {'key': key, 'value': value},
        conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<String?> getMeta(String key) async {
    if (_usesJsonStore) {
      final map = (await _readJson('sync_meta', <String, dynamic>{}) as Map)
          .cast<String, dynamic>();
      return map[key] as String?;
    }

    final db = await _database;
    final rows = await db
        .query('sync_meta', where: 'key = ?', whereArgs: [key], limit: 1);
    if (rows.isEmpty) return null;
    return rows.first['value'] as String?;
  }
}
