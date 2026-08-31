import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';

import '../../../features/customers/customers_repository.dart';
import '../../../features/inventory/inventory_repository.dart';
import '../../../features/pos/sales_repository.dart';
import '../../../features/taxes/taxes_repository.dart';
import '../../api/api_exception.dart';
import '../../storage/app_database.dart';

/// Drives the app's offline-first sync cycle: pushes sales queued in the
/// `outbox_sync` table while the device was offline, and refreshes the
/// local read-through caches (products, categories, payment methods,
/// customers, tax rules) that the POS screen falls back to when it can't
/// reach the server.
///
/// One instance lives for the lifetime of the app (created in `main.dart`,
/// same as `AuthProvider`/`ThemeProvider`) so the connectivity subscription
/// is set up exactly once and the unsynced-count badge stays consistent
/// across every screen.
class SyncEngine extends ChangeNotifier {
  SyncEngine({
    required AppDatabase database,
    required SalesRepository salesRepository,
    required InventoryRepository inventoryRepository,
    required CustomersRepository customersRepository,
    required TaxesRepository taxesRepository,
  })  : _database = database,
        _salesRepository = salesRepository,
        _inventoryRepository = inventoryRepository,
        _customersRepository = customersRepository,
        _taxesRepository = taxesRepository {
    _connectivitySubscription = Connectivity().onConnectivityChanged.listen(_onConnectivityChanged);
  }

  static const _lastSyncedMetaKey = 'last_synced_at';

  final AppDatabase _database;
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

  /// Loads the persisted "last synced" timestamp and unsynced count so the
  /// status badge has something to show immediately at app start, before
  /// the first sync of this session has run.
  Future<void> init() async {
    if (_initialized) return;
    _initialized = true;

    final storedTimestamp = await _database.getMeta(_lastSyncedMetaKey);
    if (storedTimestamp != null) {
      lastSyncedAt = DateTime.tryParse(storedTimestamp);
    }
    await refreshUnsyncedCount();

    if (await _isOnline()) {
      unawaited(syncNow());
    }
  }

  Future<void> refreshUnsyncedCount() async {
    unsyncedCount = await _database.unsyncedOutboxCount();
    notifyListeners();
  }

  /// Queues a sale recorded while offline (or right after an online push
  /// failed for connectivity reasons) so it's picked up by the next
  /// [syncNow] — see [PosProvider.checkout].
  Future<void> queueOfflineSale({
    required String id,
    required Map<String, dynamic> payload,
    required DateTime createdAt,
  }) async {
    await _database.insertOutboxSale(id: id, payload: payload, createdAt: createdAt);
    await refreshUnsyncedCount();
  }

  Future<bool> _isOnline() async {
    final results = await Connectivity().checkConnectivity();
    return results.any((r) => r != ConnectivityResult.none);
  }

  void _onConnectivityChanged(List<ConnectivityResult> results) {
    final isOnline = results.any((r) => r != ConnectivityResult.none);
    if (isOnline) {
      unawaited(syncNow());
    }
  }

  /// Pushes every pending offline sale, then refreshes the catalog caches.
  /// Safe to call repeatedly (e.g. from a manual "Sync Now" button) — it's a
  /// no-op re-entry guard while a sync is already running, and every step is
  /// independently try/caught so one failing table never blocks the rest.
  Future<void> syncNow() async {
    if (isSyncing) return;
    isSyncing = true;
    lastError = null;
    notifyListeners();

    final errors = <String>[];

    try {
      await _pushPendingSales(errors);
      await _pullCatalog(errors);

      lastSyncedAt = DateTime.now();
      await _database.setMeta(_lastSyncedMetaKey, lastSyncedAt!.toIso8601String());
      lastError = errors.isEmpty ? null : errors.join('; ');
    } finally {
      isSyncing = false;
      await refreshUnsyncedCount();
    }
  }

  Future<void> _pushPendingSales(List<String> errors) async {
    final pending = await _database.pendingOutboxSales();
    if (pending.isEmpty) return;

    try {
      final syncedIds = await _salesRepository.pushSalesBatch(pending.map((row) => row.payload).toList());
      await _database.markOutboxSynced(syncedIds);
    } on ApiException catch (e) {
      // Leave every row queued — the whole batch is retried on the next
      // sync, and the server's idempotency-by-id guard means a partially
      // applied retry never double-counts a sale.
      for (final row in pending) {
        await _database.recordOutboxFailure(row.id, e.message);
      }
      errors.add('Sales: ${e.message}');
    }
  }

  Future<void> _pullCatalog(List<String> errors) async {
    try {
      final catalog = await _inventoryRepository.fetchCatalog();
      await _database.replaceCacheBucket('products', catalog.products.map((p) => p.toJson()).toList());
      await _database.replaceCacheBucket('categories', catalog.categories.map((c) => c.toJson()).toList());
      await _database.replaceCacheBucket(
        'payment_methods',
        catalog.paymentMethods.map((p) => p.toJson()).toList(),
      );
    } on ApiException catch (e) {
      errors.add('Catalog: ${e.message}');
    }

    try {
      final customers = await _customersRepository.fetchCustomers();
      await _database.replaceCacheBucket('customers', customers.map((c) => c.toJson()).toList());
    } on ApiException catch (e) {
      errors.add('Customers: ${e.message}');
    }

    try {
      final taxes = await _taxesRepository.fetchTaxes();
      await _database.replaceCacheBucket('taxes', taxes.map((t) => t.toJson()).toList());
    } on ApiException catch (e) {
      errors.add('Taxes: ${e.message}');
    }
  }

  @override
  void dispose() {
    _connectivitySubscription?.cancel();
    super.dispose();
  }
}
