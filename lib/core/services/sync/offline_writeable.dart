import 'dart:async';

import 'package:uuid/uuid.dart';

import '../../api/api_exception.dart';
import '../../storage/app_database.dart';
import 'sync_engine.dart';

/// Shared "try online, otherwise queue it" behaviour for the feature
/// repositories (inventory, customers, categories, brands, suppliers, units,
/// …), so each create/edit/delete keeps working while the device is offline.
///
/// A repo mixes this in, exposes its [AppDatabase] as [offlineDb], and wraps
/// its network call in [writeThrough]:
///
/// ```dart
/// return writeThrough<String?>(
///   op: 'update', entity: 'product', externalId: id,
///   endpoint: ApiEndpoints.inventoryStoreProduct,
///   payload: body,
///   cacheBucket: 'products',
///   optimisticRow: optimisticProductJson,
///   baseUpdatedAt: previousUpdatedAt,
///   online: () => _postAndReturnId(body),
///   offlineResult: () => id,
/// );
/// ```
///
/// `online()` runs first. If it throws a **transport** [ApiException] (no
/// status code, or a 5xx) the write is applied to the local cache
/// optimistically and queued in `outbox_mutations` for the [SyncEngine] to
/// replay; [offlineResult] is returned. A **validation** error (4xx) is
/// rethrown unchanged so the user sees it.
mixin OfflineWriteable {
  static const _uuid = Uuid();

  /// Every repo assigns this in its constructor (`_database`).
  AppDatabase get offlineDb;

  /// A fresh client id for a brand-new offline row — pass this as
  /// `external_id` on the create so the online path and the queued replay
  /// agree on one identity.
  String newExternalId() => _uuid.v4();

  Future<T> writeThrough<T>({
    required String op,
    required String entity,
    required String externalId,
    required String endpoint,
    required Map<String, dynamic> payload,
    required Future<T> Function() online,
    required FutureOr<T> Function() offlineResult,
    String method = 'POST',
    String? cacheBucket,
    Map<String, dynamic>? optimisticRow,
    DateTime? baseUpdatedAt,
  }) async {
    try {
      return await online();
    } on ApiException catch (e) {
      // A real rejection from the server (validation, permission, conflict)
      // must surface — only a genuine "couldn't reach the server" is queued.
      final transient = e.statusCode == null || (e.statusCode ?? 0) >= 500;
      if (!transient) rethrow;

      if (cacheBucket != null) {
        if (op == 'delete') {
          await offlineDb.deleteCacheItems(cacheBucket, [externalId]);
        } else if (optimisticRow != null) {
          await offlineDb.upsertCacheItems(cacheBucket, [
            {...optimisticRow, 'id': externalId, '_pending_sync': true},
          ]);
        }
      }

      final mutation = OutboxMutation(
        id: newExternalId(),
        op: op,
        entity: entity,
        externalId: externalId,
        endpoint: endpoint,
        method: method,
        payload: payload,
        baseUpdatedAt: baseUpdatedAt,
        createdAt: DateTime.now(),
      );

      final engine = SyncEngine.instance;
      if (engine != null) {
        await engine.enqueueMutation(
          op: op,
          entity: entity,
          externalId: externalId,
          endpoint: endpoint,
          method: method,
          payload: payload,
          baseUpdatedAt: baseUpdatedAt,
        );
      } else {
        // No engine wired (early startup / a unit test) — still persist the
        // mutation so nothing is lost; it'll be picked up on the next cycle.
        await offlineDb.enqueueMutation(mutation);
      }

      return await offlineResult();
    }
  }

  /// Read helper: run [online], and if it throws a transport [ApiException]
  /// fall back to whatever [fromCache] can build from the local bucket.
  /// Rethrows if there's nothing cached (so the screen shows a real error
  /// rather than a blank list on a genuine server fault).
  Future<T> readThrough<T>({
    required Future<T> Function() online,
    required Future<T?> Function() fromCache,
  }) async {
    try {
      return await online();
    } on ApiException {
      final cached = await fromCache();
      if (cached == null) rethrow;
      return cached;
    }
  }
}
