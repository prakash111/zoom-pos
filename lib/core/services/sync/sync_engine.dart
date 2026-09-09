import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:uuid/uuid.dart';

import '../../../features/customers/customers_repository.dart';
import '../../../features/inventory/inventory_repository.dart';
import '../../../features/pos/sales_repository.dart';
import '../../../features/taxes/taxes_repository.dart';
import '../../api/api_client.dart';
import '../../api/api_exception.dart';
import '../../config/app_config.dart';
import '../../storage/app_database.dart';

/// High-level state the desktop status bar / mobile badge render.
enum SyncState {
  /// Connected, nothing pending, last cycle succeeded.
  synced,

  /// Connected, changes still queued to push (or a pull is due).
  online,

  /// No connectivity — the app is running entirely from local data.
  offline,

  /// A push/pull cycle is running right now.
  syncing,

  /// Last cycle hit an error (server rejected the batch, partial pull, …).
  /// Queued changes are intact and will be retried.
  failed,
}

/// Drives the app's offline-first sync cycle.
///
///  - **Push:** replays the `outbox_mutations` queue (every offline
///    create/edit/delete of products, customers, categories, brands,
///    suppliers, units, quotations, plus stock adjustments and customer
///    payments) through `POST /sync-batch`, then the `outbox_sync` queue of
///    offline sales through `POST /sync-push`. Both are idempotent server-side
///    (keyed by the client UUID) so a replay after a crash never duplicates.
///  - **Pull:** refreshes the read-through caches every screen falls back to
///    offline — the POS catalog via the typed repositories (unchanged), plus
///    a `GET /sync-pull` delta for quotations / sales / suppliers / brands /
///    units and, for every bucket, prunes rows the server reports deleted
///    (`deleted_ids`).
///
/// One instance lives for the lifetime of the app (created in `main.dart`).
class SyncEngine extends ChangeNotifier {
  SyncEngine({
    required AppDatabase database,
    required ApiClient apiClient,
    required SalesRepository salesRepository,
    required InventoryRepository inventoryRepository,
    required CustomersRepository customersRepository,
    required TaxesRepository taxesRepository,
  })  : _database = database,
        _apiClient = apiClient,
        _salesRepository = salesRepository,
        _inventoryRepository = inventoryRepository,
        _customersRepository = customersRepository,
        _taxesRepository = taxesRepository {
    instance = this;
    _connectivitySubscription =
        Connectivity().onConnectivityChanged.listen(_onConnectivityChanged);
  }

  static const _lastSyncedMetaKey = 'last_synced_at';
  static const _pullCursorMetaKey = 'pull_cursor';
  static const _conflictsBucket = 'sync_conflicts';
  static final Uuid _uuid = Uuid();

  /// server plural key in `/sync-pull` -> local cache bucket. The first four
  /// buckets are owned by [_pullCatalog] (richer `/inventory` shape) and are
  /// only *pruned* here; the rest are fully cached from the delta.
  static const Map<String, String> _pullBuckets = {
    'quotations': 'quotations',
    'sales': 'sales',
    'suppliers': 'suppliers',
    'brands': 'brands',
    'units': 'units',
  };

  /// server `deleted_ids` key -> local cache bucket. Covers the buckets
  /// [_pullCatalog] owns (so their server-side deletions still get pruned)
  /// plus the name mismatch `tax_rules` -> `taxes`.
  static const Map<String, String> _tombstoneKeyToBucket = {
    'products': 'products',
    'categories': 'categories',
    'customers': 'customers',
    'tax_rules': 'taxes',
    'quotations': 'quotations',
    'brands': 'brands',
    'suppliers': 'suppliers',
    'units': 'units',
  };

  /// The single live engine, assigned in the constructor. Lets repositories
  /// reach `enqueueMutation` without threading an instance through every
  /// constructor + provider (same pattern as `AppDatabase.instance`,
  /// `BootstrapCache.instance`, `SessionCache.instance`). Null only before
  /// `main()` has built it (or in a unit test that doesn't).
  static SyncEngine? instance;

  final AppDatabase _database;
  final ApiClient _apiClient;
  final SalesRepository _salesRepository;
  final InventoryRepository _inventoryRepository;
  final CustomersRepository _customersRepository;
  final TaxesRepository _taxesRepository;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;

  bool isSyncing = false;
  int unsyncedCount = 0;
  DateTime? lastSyncedAt;
  String? lastError;
  bool _initialized = false;
  bool _online = true;
  bool _lastCycleFailed = false;

  /// Offline edits/deletes the server dropped because its own copy was newer
  /// (last-write-wins). Persisted in the `sync_conflicts` cache bucket and
  /// shown in the Sync panel so a lost change is never silent. Each row:
  /// `{id, entity, label, reason, server_updated_at, logged_at}`.
  List<Map<String, dynamic>> _conflicts = const [];
  List<Map<String, dynamic>> get conflicts => List.unmodifiable(_conflicts);
  int get conflictCount => _conflicts.length;

  /// What the UI shows. Derived, never stored.
  SyncState get state {
    if (isSyncing) return SyncState.syncing;
    if (!_online) return SyncState.offline;
    if (_lastCycleFailed) return SyncState.failed;
    if (unsyncedCount > 0) return SyncState.online;
    return lastSyncedAt != null ? SyncState.synced : SyncState.online;
  }

  bool get isOnline => _online;

  /// Total queued local changes waiting to reach the server (offline sales +
  /// every other offline write).
  int get pendingCount => unsyncedCount;

  /// Loads persisted state, recovers anything a previous crash left mid-push,
  /// and kicks a first sync if online.
  Future<void> init() async {
    if (_initialized) return;
    _initialized = true;

    final storedTimestamp = await _database.getMeta(_lastSyncedMetaKey);
    if (storedTimestamp != null) {
      lastSyncedAt = DateTime.tryParse(storedTimestamp);
    }

    // Crash recovery: an `inflight` (or previously `failed`) mutation is put
    // back to `pending` so the next cycle retries it — the server dedupes on
    // the client UUID, so replaying a batch that was actually applied is a
    // no-op.
    await _database.resetStuckMutations();
    await _database.pruneSyncedMutations();
    await _loadConflicts();

    _online = await _checkOnline();
    await refreshUnsyncedCount();

    if (_online) {
      unawaited(syncNow());
    }
  }

  Future<void> refreshUnsyncedCount() async {
    final sales = await _database.unsyncedOutboxCount();
    final mutations = await _database.pendingMutationCount();
    unsyncedCount = sales + mutations;
    notifyListeners();
  }

  Future<void> _loadConflicts() async {
    try {
      _conflicts = await _database.readCacheBucket(_conflictsBucket);
    } catch (_) {
      _conflicts = const [];
    }
  }

  /// Merges the `conflicts` the server reported for this batch into the
  /// `sync_conflicts` bucket (keyed by `entity:id`, so a repeat conflict for
  /// the same row just refreshes its entry).
  Future<void> _recordConflicts(dynamic raw) async {
    if (raw is! List || raw.isEmpty) return;
    final rows = raw.whereType<Map>().map((c) {
      final m = Map<String, dynamic>.from(c);
      return {
        ...m,
        'id': '${m['entity'] ?? 'row'}:${m['id'] ?? _uuid.v4()}',
        'logged_at': DateTime.now().toIso8601String(),
      };
    }).toList();
    if (rows.isEmpty) return;
    await _database.upsertCacheItems(_conflictsBucket, rows);
    await _loadConflicts();
    notifyListeners();
  }

  /// Dismisses the conflict log once the user has reviewed it.
  Future<void> clearConflicts() async {
    await _database.replaceCacheBucket(_conflictsBucket, const []);
    _conflicts = const [];
    notifyListeners();
  }

  /// Re-queues every mutation/sale a previous cycle left `failed` and runs a
  /// fresh sync. Server idempotency makes replaying an already-applied row a
  /// no-op, so this is always safe.
  Future<void> retryFailed() async {
    await _database.resetStuckMutations();
    await refreshUnsyncedCount();
    await syncNow();
  }

  /// Queues a sale recorded while offline — unchanged, still routed through
  /// the dedicated `outbox_sync` table so the POS path is untouched.
  Future<void> queueOfflineSale({
    required String id,
    required Map<String, dynamic> payload,
    required DateTime createdAt,
  }) async {
    await _database.insertOutboxSale(
        id: id, payload: payload, createdAt: createdAt);
    await refreshUnsyncedCount();
  }

  /// Queues one offline write for later replay through `/sync-batch`.
  ///
  /// [entity] is one of: product, customer, category, brand, supplier, unit,
  /// quotation, adjustment, payment. [op] is create | update | delete.
  /// [externalId] is the local row's UUID (the server idempotency key).
  /// [payload] is the body that would have been POSTed online.
  /// [baseUpdatedAt] is the cached row's `updated_at` at edit time, feeding
  /// the server's last-write-wins guard.
  Future<void> enqueueMutation({
    required String op,
    required String entity,
    required String externalId,
    required Map<String, dynamic> payload,
    String endpoint = '',
    String method = 'POST',
    DateTime? baseUpdatedAt,
  }) async {
    await _database.enqueueMutation(OutboxMutation(
      id: _uuid.v4(),
      op: op,
      entity: entity,
      externalId: externalId,
      endpoint: endpoint,
      method: method,
      payload: payload,
      baseUpdatedAt: baseUpdatedAt,
      createdAt: DateTime.now(),
    ));
    await refreshUnsyncedCount();
    if (_online && !isSyncing) unawaited(syncNow());
  }

  Future<bool> _checkOnline() async {
    final results = await Connectivity().checkConnectivity();
    return results.any((r) => r != ConnectivityResult.none);
  }

  void _onConnectivityChanged(List<ConnectivityResult> results) {
    final online = results.any((r) => r != ConnectivityResult.none);
    final was = _online;
    _online = online;
    if (online && !was) {
      unawaited(syncNow());
    } else {
      notifyListeners();
    }
  }

  /// Runs one full push-then-pull cycle. Re-entrant-safe (no-op while a cycle
  /// is already running) and every step is independently try/caught so one
  /// failing table never blocks the rest.
  Future<void> syncNow() async {
    if (isSyncing) return;
    isSyncing = true;
    lastError = null;
    notifyListeners();

    final errors = <String>[];

    try {
      await _pushPendingMutations(errors);
      await _pushPendingSales(errors);
      await _pullCatalog(errors);
      await _pullDeltas(errors);

      lastSyncedAt = DateTime.now();
      await _database.setMeta(
          _lastSyncedMetaKey, lastSyncedAt!.toIso8601String());
      lastError = errors.isEmpty ? null : errors.join('; ');
      _lastCycleFailed = errors.isNotEmpty;
    } finally {
      isSyncing = false;
      await refreshUnsyncedCount();
    }
  }

  // --- Push -----------------------------------------------------------------

  Future<void> _pushPendingMutations(List<String> errors) async {
    final pending = await _database.pendingMutations();
    if (pending.isEmpty) return;

    final ids = pending.map((m) => m.id).toList();
    await _database.markMutationsInflight(ids);

    final body = _buildSyncBatchBody(pending);

    try {
      final response =
          await _apiClient.post(ApiEndpoints.syncBatch, data: body);
      // The server processed the whole batch transactionally and dedupes on
      // the client UUID, so once it returns success every row we sent is
      // durably applied — mark them all, backfilling server ids from id_map.
      final idMap = <String, String>{};
      final rawIdMap = response['id_map'];
      if (rawIdMap is Map) {
        rawIdMap.forEach((k, v) => idMap[k.toString()] = v.toString());
      }
      await _database.markMutationsSynced(ids, idMap);
      // The server ACKed the batch but may have dropped some edits/deletes as
      // stale (last-write-wins). Log those so the Sync panel can show them;
      // the next _pullDeltas refreshes the local rows to the server's copy.
      await _recordConflicts(response['conflicts']);
    } on ApiException catch (e) {
      // Any failure — connectivity or a rejected batch — leaves every row
      // queued (marked `failed`, retried next cycle). A partially-applied
      // retry is safe: the server's idempotency ledger skips what it already
      // ingested.
      await _database.recordMutationFailure(ids, e.message);
      errors.add('Offline changes: ${e.message}');
    } catch (e) {
      await _database.recordMutationFailure(ids, e.toString());
      errors.add('Offline changes: $e');
    }
  }

  /// Groups the pending queue into the arrays `POST /sync-batch`
  /// (PosSyncApiController::syncBatch) already understands.
  Map<String, dynamic> _buildSyncBatchBody(List<OutboxMutation> pending) {
    // entity -> (create/update array key, delete array key). syncBatch reads
    // `created_<plural>` for products/customers/reference tables but a plain
    // `quotations` for quotations; every delete goes to `deleted_<plural>`.
    const createKey = {
      'product': 'created_products',
      'customer': 'created_customers',
      'category': 'created_categories',
      'brand': 'created_brands',
      'supplier': 'created_suppliers',
      'unit': 'created_units',
      'quotation': 'quotations',
    };
    const deleteKey = {
      'product': 'deleted_products',
      'customer': 'deleted_customers',
      'category': 'deleted_categories',
      'brand': 'deleted_brands',
      'supplier': 'deleted_suppliers',
      'unit': 'deleted_units',
      'quotation': 'deleted_quotations',
      'tax_rule': 'deleted_tax_rules',
    };

    final arrays = <String, List<Map<String, dynamic>>>{};
    final adjustments = <Map<String, dynamic>>[];
    final payments = <Map<String, dynamic>>[];
    final generic = <Map<String, dynamic>>[];

    for (final m in pending) {
      final stamp = (m.baseUpdatedAt ?? m.createdAt).toUtc().toIso8601String();

      if (m.entity == 'adjustment') {
        adjustments.add({...m.payload, 'id': m.externalId});
        continue;
      }
      if (m.entity == 'payment') {
        payments.add({...m.payload, 'id': m.externalId});
        continue;
      }

      if (m.op == 'delete') {
        final key = deleteKey[m.entity];
        if (key == null) {
          generic.add(_genericEntry(m, stamp));
          continue;
        }
        (arrays[key] ??= []).add({
          'id': m.externalId,
          'deleted_at': m.createdAt.toUtc().toIso8601String(),
          if (m.baseUpdatedAt != null) 'updated_at': stamp,
        });
        continue;
      }

      final key = createKey[m.entity];
      if (key == null) {
        generic.add(_genericEntry(m, stamp));
        continue;
      }
      (arrays[key] ??= []).add({
        ...m.payload,
        'id': m.externalId,
        'updated_at': stamp,
      });
    }

    return {
      ...arrays,
      if (adjustments.isNotEmpty) 'inventory_adjustments': adjustments,
      if (payments.isNotEmpty) 'customer_payments': payments,
      // Forward-compatible: the server ignores an unknown key today; a future
      // release adds a queued replay handler for it (plan Phase 4).
      if (generic.isNotEmpty) 'mutations': generic,
    };
  }

  Map<String, dynamic> _genericEntry(OutboxMutation m, String stamp) => {
        'idempotency_key': m.id,
        'op': m.op,
        'entity': m.entity,
        'external_id': m.externalId,
        'endpoint': m.endpoint,
        'method': m.method,
        'payload': m.payload,
        'client_updated_at': stamp,
      };

  Future<void> _pushPendingSales(List<String> errors) async {
    final pending = await _database.pendingOutboxSales();
    if (pending.isEmpty) return;

    try {
      final syncedIds = await _salesRepository
          .pushSalesBatch(pending.map((row) => row.payload).toList());
      await _database.markOutboxSynced(syncedIds);
    } on ApiException catch (e) {
      for (final row in pending) {
        await _database.recordOutboxFailure(row.id, e.message);
      }
      errors.add('Sales: ${e.message}');
    }
  }

  // --- Pull ---------------------------------------------------------------

  /// Existing behaviour, unchanged: full refresh of the POS catalog caches
  /// (products / categories / payment methods / customers / tax rules) using
  /// the typed endpoints whose response shape those screens' models expect.
  Future<void> _pullCatalog(List<String> errors) async {
    try {
      final catalog = await _inventoryRepository.fetchCatalog();
      await _database.replaceCacheBucket(
          'products', catalog.products.map((p) => p.toJson()).toList());
      await _database.replaceCacheBucket(
          'categories', catalog.categories.map((c) => c.toJson()).toList());
      await _database.replaceCacheBucket(
        'payment_methods',
        catalog.paymentMethods.map((p) => p.toJson()).toList(),
      );
    } on ApiException catch (e) {
      errors.add('Catalog: ${e.message}');
    }

    try {
      final customers = await _customersRepository.fetchCustomers();
      await _database.replaceCacheBucket(
          'customers', customers.map((c) => c.toJson()).toList());
    } on ApiException catch (e) {
      errors.add('Customers: ${e.message}');
    }

    try {
      final taxes = await _taxesRepository.fetchTaxes();
      await _database.replaceCacheBucket(
          'taxes', taxes.map((t) => t.toJson()).toList());
    } on ApiException catch (e) {
      errors.add('Taxes: ${e.message}');
    }
  }

  /// `GET /sync-pull` delta: caches the buckets [_pullCatalog] doesn't cover
  /// (quotations / sales / suppliers / brands / units) and, for **every**
  /// bucket, prunes rows the server reports deleted since the last cursor.
  Future<void> _pullDeltas(List<String> errors) async {
    try {
      final cursor = await _database.getMeta(_pullCursorMetaKey);
      final response = await _apiClient
          .get(
            ApiEndpoints.syncPull,
            query: cursor != null ? {'since': cursor} : null,
          )
          .timeout(
            AppConfig.receiveTimeout,
            onTimeout: () => throw ApiException('Sync pull timed out.'),
          );

      // New rows for the buckets we own here.
      for (final entry in _pullBuckets.entries) {
        final rows = (response[entry.key] as List? ?? [])
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList();
        if (rows.isEmpty) continue;
        if (cursor == null) {
          await _database.replaceCacheBucket(entry.value, rows);
        } else {
          await _database.upsertCacheItems(entry.value, rows);
        }
      }

      // Tombstones for every bucket, including the ones [_pullCatalog] owns.
      final deletedIds = response['deleted_ids'];
      if (deletedIds is Map) {
        for (final entry in _tombstoneKeyToBucket.entries) {
          final ids = (deletedIds[entry.key] as List? ?? [])
              .map((e) => e.toString())
              .toList();
          if (ids.isNotEmpty) {
            await _database.deleteCacheItems(entry.value, ids);
          }
        }
      }

      final serverTime = response['server_time'];
      if (serverTime is String && serverTime.isNotEmpty) {
        await _database.setMeta(_pullCursorMetaKey, serverTime);
      }
    } on ApiException catch (e) {
      errors.add('Sync pull: ${e.message}');
    } catch (e) {
      errors.add('Sync pull: $e');
    }
  }

  @override
  void dispose() {
    _connectivitySubscription?.cancel();
    super.dispose();
  }
}
