import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/quotation_model.dart';

/// Talks to QuotationApiController: GET/POST /quotations,
/// GET/PUT/DELETE /quotations/{id}, POST /quotations/{id}/convert.
class QuotationsRepository {
  QuotationsRepository(this._client);

  final ApiClient _client;

  Future<List<QuotationModel>> fetchQuotations({String? status, String? search}) async {
    final response = await _client.get(ApiEndpoints.quotations, query: {
      if (status != null && status.isNotEmpty) 'status': status,
      if (search != null && search.isNotEmpty) 'search': search,
    });
    return (response['quotations'] as List? ?? [])
        .map((e) => QuotationModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<QuotationModel> saveQuotation({
    String? id,
    String? customerId,
    required String customerName,
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

  Future<void> deleteQuotation(String id) {
    return _client.delete(ApiEndpoints.quotation(id));
  }

  Future<void> convertToSale(String id) {
    return _client.post(ApiEndpoints.quotationConvert(id));
  }
}
