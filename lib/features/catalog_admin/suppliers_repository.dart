import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/supplier_model.dart';

/// Talks to CatalogAdminApiController's supplier endpoints:
/// GET/POST /suppliers, PUT/DELETE /suppliers/{id}.
class SuppliersRepository {
  SuppliersRepository(this._client);

  final ApiClient _client;

  Future<List<SupplierModel>> fetchSuppliers({String? search}) async {
    final response = await _client.get(ApiEndpoints.suppliers, query: {
      if (search != null && search.isNotEmpty) 'search': search,
    });
    return (response['suppliers'] as List? ?? [])
        .map((e) => SupplierModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> saveSupplier({
    String? id,
    required String name,
    String? legalName,
    String? taxId,
    String? email,
    String? phone,
    String? city,
    String? state,
    bool active = true,
  }) {
    final data = {
      'name': name,
      if (legalName != null && legalName.isNotEmpty) 'legal_name': legalName,
      if (taxId != null && taxId.isNotEmpty) 'tax_id': taxId,
      if (email != null && email.isNotEmpty) 'email': email,
      if (phone != null && phone.isNotEmpty) 'phone': phone,
      if (city != null && city.isNotEmpty) 'city': city,
      if (state != null && state.isNotEmpty) 'state': state,
      'active': active,
    };
    return id == null
        ? _client.post(ApiEndpoints.suppliers, data: data)
        : _client.put(ApiEndpoints.supplier(id), data: data);
  }

  Future<void> deleteSupplier(String id) => _client.delete(ApiEndpoints.supplier(id));
}
