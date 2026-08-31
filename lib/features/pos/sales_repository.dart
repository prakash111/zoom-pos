import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/sale_model.dart';

/// Pushes a single completed sale through POST /sync-push
/// (PosSyncApiController::syncPush / processSalesBatch), the same endpoint
/// the offline-sync engine uses to ingest sales recorded while disconnected.
/// The server treats [id] as an idempotency key, so retries are safe.
///
/// There's no dedicated sales-history endpoint — [fetchRecentSales] reads
/// the `sales` delta off GET /sync-pull (PosSyncApiController::syncPull),
/// the same feed the desktop client uses to backfill its local database,
/// capped server-side at the 200 most recent sales.
class SalesRepository {
  SalesRepository(this._client);

  final ApiClient _client;

  Future<List<SaleModel>> fetchRecentSales() async {
    final response = await _client.get(ApiEndpoints.syncPull);
    return (response['sales'] as List? ?? [])
        .map((e) => SaleModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> pushSale({
    required String id,
    required double total,
    required double discount,
    required String paymentMethod,
    double taxAmount = 0,
    String? taxName,
    double? taxRate,
    String? customerId,
    String? customerName,
    required List<Map<String, dynamic>> items,
    DateTime? createdAt,
  }) {
    return _client.post(ApiEndpoints.syncPush, data: {
      'sales': [
        _salePayload(
          id: id,
          total: total,
          discount: discount,
          taxAmount: taxAmount,
          taxName: taxName,
          taxRate: taxRate,
          paymentMethod: paymentMethod,
          customerId: customerId,
          customerName: customerName,
          items: items,
          createdAt: createdAt,
        ),
      ],
    });
  }

  /// Pushes every already-built sale payload (see [OutboxSale.payload], via
  /// [AppDatabase.pendingOutboxSales]) in a single request. The server
  /// treats each sale's `id` as an idempotency key (matched against
  /// `external_id`), so replaying the same batch after a partial failure —
  /// or a previous run that succeeded but never got acknowledged locally —
  /// never creates a duplicate sale. Returns the ids the server confirms it
  /// ingested (or already had), so the caller knows which outbox rows to
  /// mark synced.
  Future<List<String>> pushSalesBatch(List<Map<String, dynamic>> payloads) async {
    if (payloads.isEmpty) return const [];
    final response = await _client.post(ApiEndpoints.syncPush, data: {'sales': payloads});
    return (response['synced_ids'] as List? ?? []).map((e) => e.toString()).toList();
  }

  /// Builds the exact JSON shape [PosSyncApiController::syncPush] expects
  /// for one sale — shared by the immediate online path ([pushSale]) and the
  /// offline outbox, which stores this same shape and replays it verbatim.
  static Map<String, dynamic> _salePayload({
    required String id,
    required double total,
    required double discount,
    required String paymentMethod,
    double taxAmount = 0,
    String? taxName,
    double? taxRate,
    String? customerId,
    String? customerName,
    required List<Map<String, dynamic>> items,
    DateTime? createdAt,
  }) {
    return {
      'id': id,
      'total': total,
      'discount': discount,
      'tax_amount': taxAmount,
      if (taxName != null) 'tax_name': taxName,
      if (taxRate != null) 'tax_rate': taxRate,
      'payment_method': paymentMethod,
      if (customerId != null) 'customer_id': customerId,
      if (customerName != null) 'customer_name': customerName,
      'items': items,
      // Preserves the real sale time for a sale recorded offline and
      // replayed later — otherwise the server would stamp it with the sync
      // time instead of when the sale actually happened.
      if (createdAt != null) 'createdAt': createdAt.toIso8601String(),
    };
  }

  /// Builds the payload an offline sale should be queued with (see
  /// [PosProvider.checkout]) — the same shape [pushSale] sends immediately
  /// when online, so the outbox can replay it later via [pushSalesBatch]
  /// without either path drifting out of sync with the other.
  static Map<String, dynamic> buildOfflineSalePayload({
    required String id,
    required double total,
    required double discount,
    required String paymentMethod,
    double taxAmount = 0,
    String? taxName,
    double? taxRate,
    String? customerId,
    String? customerName,
    required List<Map<String, dynamic>> items,
    required DateTime createdAt,
  }) {
    return _salePayload(
      id: id,
      total: total,
      discount: discount,
      taxAmount: taxAmount,
      taxName: taxName,
      taxRate: taxRate,
      paymentMethod: paymentMethod,
      customerId: customerId,
      customerName: customerName,
      items: items,
      createdAt: createdAt,
    );
  }
}
