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

  Future<List<SaleModel>> fetchSales({
    String? query,
    String? filter,
    DateTime? startDate,
    DateTime? endDate,
    int? storeId,
  }) async {
    try {
      final queryParams = <String, String>{};
      if (query != null && query.trim().isNotEmpty) {
        queryParams['query'] = query.trim();
      }
      if (filter != null && filter.isNotEmpty) {
        queryParams['filter'] = filter;
      }
      if (startDate != null) {
        queryParams['start_date'] =
            startDate.toIso8601String().split('T').first;
      }
      if (endDate != null) {
        queryParams['end_date'] = endDate.toIso8601String().split('T').first;
      }
      if (storeId != null) {
        queryParams['store_id'] = storeId.toString();
      }

      final uri = queryParams.isEmpty
          ? ApiEndpoints.sales
          : Uri(
              path: ApiEndpoints.sales,
              queryParameters: queryParams,
            ).toString();

      final response = await _client.get(uri);
      final list =
          (response['data'] as List? ?? response['sales'] as List? ?? []);
      return list
          .map((e) => SaleModel.fromJson(e as Map<String, dynamic>))
          .toList();
    } catch (_) {
      final all = await fetchRecentSales();
      return _filterLocalSales(
        all,
        query: query,
        filter: filter,
        startDate: startDate,
        endDate: endDate,
      );
    }
  }

  List<SaleModel> _filterLocalSales(
    List<SaleModel> sales, {
    String? query,
    String? filter,
    DateTime? startDate,
    DateTime? endDate,
  }) {
    var result = sales;
    if (query != null && query.trim().isNotEmpty) {
      final q = query.trim().toLowerCase();
      result = result.where((s) {
        final matchNum = s.saleNumber.toLowerCase().contains(q);
        final matchCustomer =
            s.customerName?.toLowerCase().contains(q) ?? false;
        final matchItems = s.items.any((item) =>
            (item['product_name'] ?? item['name'] ?? '')
                .toString()
                .toLowerCase()
                .contains(q));
        return matchNum || matchCustomer || matchItems;
      }).toList();
    }

    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final in7Days = today.add(const Duration(days: 7));
    final in15Days = today.add(const Duration(days: 15));

    switch (filter) {
      case 'overdue':
        result = result.where((s) {
          if (s.dueAmount <= 0) return false;
          if (s.paymentStatus.toLowerCase() == 'paid') return false;
          if (s.dueDate == null) return false;
          final d =
              DateTime(s.dueDate!.year, s.dueDate!.month, s.dueDate!.day);
          return d.isBefore(today);
        }).toList();
        break;
      case 'due_today':
        result = result.where((s) {
          if (s.dueDate == null) return false;
          final d =
              DateTime(s.dueDate!.year, s.dueDate!.month, s.dueDate!.day);
          return d.isAtSameMomentAs(today) &&
              s.paymentStatus.toLowerCase() != 'paid';
        }).toList();
        break;
      case 'due_7_days':
        result = result.where((s) {
          if (s.dueDate == null) return false;
          final d =
              DateTime(s.dueDate!.year, s.dueDate!.month, s.dueDate!.day);
          return !d.isBefore(today) &&
              !d.isAfter(in7Days) &&
              s.paymentStatus.toLowerCase() != 'paid';
        }).toList();
        break;
      case 'due_15_days':
        result = result.where((s) {
          if (s.dueDate == null) return false;
          final d =
              DateTime(s.dueDate!.year, s.dueDate!.month, s.dueDate!.day);
          return !d.isBefore(today) &&
              !d.isAfter(in15Days) &&
              s.paymentStatus.toLowerCase() != 'paid';
        }).toList();
        break;
      case 'custom_date':
        if (startDate != null || endDate != null) {
          final sDate = startDate != null
              ? DateTime(startDate.year, startDate.month, startDate.day)
              : null;
          final eDate = endDate != null
              ? DateTime(
                  endDate.year, endDate.month, endDate.day, 23, 59, 59)
              : null;
          result = result.where((s) {
            final date = s.createdAt;
            if (date == null) return false;
            if (sDate != null && date.isBefore(sDate)) return false;
            if (eDate != null && date.isAfter(eDate)) return false;
            return true;
          }).toList();
        }
        break;
      case 'all':
      default:
        break;
    }

    return result;
  }

  Future<SaleModel> fetchSale(String id) async {
    final response = await _client.get(ApiEndpoints.sale(id));
    return SaleModel.fromJson(response['sale'] as Map<String, dynamic>);
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
    List<Map<String, dynamic>>? payments,
    double? paidAmount,
    double? tendered,
    double changeReturned = 0,
    DateTime? dueDate,
    DateTime? dueReminderAt,
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
          payments: payments,
          paidAmount: paidAmount,
          tendered: tendered,
          changeReturned: changeReturned,
          dueDate: dueDate,
          dueReminderAt: dueReminderAt,
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
  Future<List<String>> pushSalesBatch(List<Map<String, dynamic>> payloads,
      {int? storeId}) async {
    if (payloads.isEmpty) return const [];
    final response = storeId == null
        ? await _client.post(ApiEndpoints.syncPush, data: {'sales': payloads})
        : await _client.postForStore(ApiEndpoints.syncPush,
            storeId: storeId, data: {'sales': payloads});
    return (response['synced_ids'] as List? ?? [])
        .map((e) => e.toString())
        .toList();
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
    List<Map<String, dynamic>>? payments,
    double? paidAmount,
    double? tendered,
    double changeReturned = 0,
    DateTime? dueDate,
    DateTime? dueReminderAt,
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
      // Split-tender rows (PosSyncApiController::processSalesBatch sums
      // these for paid_amount when present, overriding everything below).
      if (payments != null && payments.isNotEmpty) 'payments': payments,
      // Explicit zero/partial single-tender override — ignored server-side
      // when `payments` above is non-empty.
      if (paidAmount != null) 'paid_amount': paidAmount,
      if (tendered != null) 'tendered': tendered,
      'change_returned': changeReturned,
      if (dueDate != null)
        'due_date': dueDate.toIso8601String().split('T').first,
      if (dueReminderAt != null)
        'due_reminder_at': dueReminderAt.toUtc().toIso8601String(),
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
    List<Map<String, dynamic>>? payments,
    double? paidAmount,
    double? tendered,
    double changeReturned = 0,
    DateTime? dueDate,
    DateTime? dueReminderAt,
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
      payments: payments,
      paidAmount: paidAmount,
      tendered: tendered,
      changeReturned: changeReturned,
      dueDate: dueDate,
      dueReminderAt: dueReminderAt,
    );
  }
}
