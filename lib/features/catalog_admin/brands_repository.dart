import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/brand_model.dart';

/// Talks to CatalogAdminApiController's brand endpoints:
/// GET/POST /brands, PUT/DELETE /brands/{id}.
class BrandsRepository {
  BrandsRepository(this._client);

  final ApiClient _client;

  Future<List<BrandModel>> fetchBrands() async {
    final response = await _client.get(ApiEndpoints.brands);
    return (response['brands'] as List? ?? []).map((e) => BrandModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> saveBrand({String? id, required String name}) {
    final data = {'name': name};
    return id == null ? _client.post(ApiEndpoints.brands, data: data) : _client.put(ApiEndpoints.brand(id), data: data);
  }

  Future<void> deleteBrand(String id) => _client.delete(ApiEndpoints.brand(id));
}
