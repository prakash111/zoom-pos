import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/published_catalog_model.dart';

/// Talks to CatalogApiController: GET/POST /catalog, DELETE /catalog/{id}.
class CatalogRepository {
  CatalogRepository(this._client);

  final ApiClient _client;

  Future<({List<PublishedCatalogModel> catalogs, List<CatalogProductOption> products})> fetchCatalogs() async {
    final response = await _client.get(ApiEndpoints.catalog);
    return (
      catalogs: (response['catalogs'] as List? ?? [])
          .map((e) => PublishedCatalogModel.fromJson(e as Map<String, dynamic>))
          .toList(),
      products: (response['products'] as List? ?? [])
          .map((e) => CatalogProductOption.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  Future<PublishedCatalogModel> publish({
    required String title,
    required List<String> productIds,
    required int ttlDays,
  }) async {
    final response = await _client.post(ApiEndpoints.catalog, data: {
      'title': title,
      'product_ids': productIds,
      'ttl_days': ttlDays,
    });
    return PublishedCatalogModel.fromJson(response['catalog'] as Map<String, dynamic>);
  }

  Future<void> revoke(String id) => _client.delete(ApiEndpoints.catalogLink(id));
}
