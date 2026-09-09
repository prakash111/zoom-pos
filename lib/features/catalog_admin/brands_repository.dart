import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/config/app_config.dart';
import '../../core/models/brand_model.dart';
import '../../core/services/sync/offline_writeable.dart';
import '../../core/storage/app_database.dart';

/// Talks to CatalogAdminApiController's brand endpoints:
/// GET/POST /brands, PUT/DELETE /brands/{id}.
class BrandsRepository with OfflineWriteable {
  BrandsRepository(this._client, {AppDatabase? database})
      : _database = database ?? AppDatabase.instance;

  final ApiClient _client;
  final AppDatabase _database;

  @override
  AppDatabase get offlineDb => _database;

  Future<List<BrandModel>> fetchBrands() async {
    try {
      final response = await _client.get(ApiEndpoints.brands);
      final brands = (response['brands'] as List? ?? [])
          .map((e) => BrandModel.fromJson(e as Map<String, dynamic>))
          .toList();
      await _database.replaceCacheBucket(
          'brands', brands.map((b) => b.toJson()).toList());
      return brands;
    } on ApiException {
      final cached = await _database.readCacheBucket('brands');
      if (cached.isEmpty) rethrow;
      return cached.map(BrandModel.fromJson).toList();
    }
  }

  Future<void> saveBrand({String? id, required String name}) {
    final isCreate = id == null;
    final ext = id ?? newExternalId();
    final data = <String, dynamic>{
      if (!isCreate) 'id': ext,
      'external_id': ext,
      'name': name,
    };

    return writeThrough<void>(
      op: isCreate ? 'create' : 'update',
      entity: 'brand',
      externalId: ext,
      endpoint: isCreate ? ApiEndpoints.brands : ApiEndpoints.brand(ext),
      method: isCreate ? 'POST' : 'PUT',
      payload: data,
      cacheBucket: 'brands',
      optimisticRow: {
        'id': ext,
        'name': name,
        'active': true,
        'updated_at': DateTime.now().toUtc().toIso8601String(),
      },
      online: () => isCreate
          ? _client.post(ApiEndpoints.brands, data: data)
          : _client.put(ApiEndpoints.brand(ext), data: data),
      offlineResult: () {},
    );
  }

  Future<void> deleteBrand(String id) => writeThrough<void>(
        op: 'delete',
        entity: 'brand',
        externalId: id,
        endpoint: ApiEndpoints.brand(id),
        method: 'DELETE',
        payload: const {},
        cacheBucket: 'brands',
        online: () => _client.delete(ApiEndpoints.brand(id)),
        offlineResult: () {},
      );
}
