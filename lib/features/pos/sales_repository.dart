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
    String? customerId,
    String? customerName,
    required List<Map<String, dynamic>> items,
  }) {
    return _client.post(ApiEndpoints.syncPush, data: {
      'sales': [
        {
          'id': id,
          'total': total,
          'discount': discount,
          'payment_method': paymentMethod,
          if (customerId != null) 'customer_id': customerId,
          if (customerName != null) 'customer_name': customerName,
          'items': items,
        },
      ],
    });
  }
}
