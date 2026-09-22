import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/config/app_config.dart';
import '../../core/models/quotation_model.dart';
import '../../core/services/sync/offline_writeable.dart';
import '../../core/storage/app_database.dart';

/// Talks to QuotationApiController: GET/POST /quotations,
/// GET/PUT/DELETE /quotations/{id}, POST /quotations/{id}/convert.
class QuotationsRepository with OfflineWriteable {
  QuotationsRepository(this._client, {AppDatabase? database})
      : _database = database ?? AppDatabase.instance;

  final ApiClient _client;
  final AppDatabase _database;

  String get _quotationsBucket => _client.cacheBucket('quotations');

  @override
  AppDatabase get offlineDb => _database;

  /// Pre-fill values for a brand-new quotation, sourced from the tenant's
  /// "Receipt Prefixes & Bank Terms" settings.
  Future<({String terms, String notes, String prefix})> fetchDefaults() async {
    final response = await _client.get(ApiEndpoints.quotationDefaults);
    final d = (response['defaults'] as Map<String, dynamic>? ?? {});
    return (
      terms: (d['terms'] ?? '').toString(),
      notes: (d['notes'] ?? '').toString(),
      prefix: (d['prefix'] ?? 'QUO-').toString(),
    );
  }

  /// Live list, refreshing the offline cache; falls back to the cache (kept
  /// current by the `/sync-pull` delta) when the request fails, filtering
  /// locally so the Quotations screen stays usable offline.
  Future<List<QuotationModel>> fetchQuotations({String? status, String? search}) async {
    try {
      final response = await _client.get(ApiEndpoints.quotations, query: {
        if (status != null && status.isNotEmpty) 'status': status,
        if (search != null && search.isNotEmpty) 'search': search,
      });
      final quotations = (response['quotations'] as List? ?? [])
          .map((e) => QuotationModel.fromJson(e as Map<String, dynamic>))
          .toList();
      // Only an unfiltered fetch owns the bucket; a filtered result is a
      // subset and must not overwrite it.
      if ((status == null || status.isEmpty) && (search == null || search.isEmpty)) {
        await _database.replaceCacheBucket(
            _quotationsBucket, quotations.map((q) => q.toJson()).toList());
      }
      return quotations;
    } on ApiException {
      final cached = await _database.readCacheBucket(_quotationsBucket);
      if (cached.isEmpty) rethrow;
      var all = cached.map(QuotationModel.fromJson).toList();
      if (status != null && status.isNotEmpty) {
        all = all.where((q) => q.status == status).toList();
      }
      final q = search?.trim().toLowerCase() ?? '';
      if (q.isNotEmpty) {
        all = all
            .where((row) =>
                row.customerName.toLowerCase().contains(q) ||
                row.quoteNumber.toLowerCase().contains(q))
            .toList();
      }
      return all;
    }
  }

  /// Online-only for now: a quotation's nested line items and server-computed
  /// numbering aren't part of the offline replay set yet (plan Phase 3.1).
  Future<QuotationModel> saveQuotation({
    String? id,
    String? customerId,
    required String customerName,
    String? leadId,
    required List<Map<String, dynamic>> items,
    required double discount,
    required double tax,
    String? notes,
    String? terms,
    DateTime? validUntil,
  }) async {
    final data = {
      if (customerId != null) 'customer_id': customerId,
      'customer_name': customerName,
      if (leadId != null && leadId.isNotEmpty) 'lead_id': leadId,
      'items': items,
      'discount': discount,
      'tax': tax,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
      if (terms != null && terms.isNotEmpty) 'terms': terms,
      if (validUntil != null) 'valid_until': validUntil.toIso8601String(),
    };

    final response = id == null
        ? await _client.post(ApiEndpoints.quotations, data: data)
        : await _client.put(ApiEndpoints.quotation(id), data: data);

    return QuotationModel.fromJson(response['quotation'] as Map<String, dynamic>);
  }

  Future<void> deleteQuotation(String id) => writeThrough<void>(
        op: 'delete',
        entity: 'quotation',
        externalId: id,
        endpoint: ApiEndpoints.quotation(id),
        method: 'DELETE',
        payload: const {},
        cacheBucket: _quotationsBucket,
        online: () => _client.delete(ApiEndpoints.quotation(id)),
        offlineResult: () {},
      );

  Future<void> convertToSale(String id) {
    return _client.post(ApiEndpoints.quotationConvert(id));
  }
}
