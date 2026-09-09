import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/config/app_config.dart';
import '../../core/models/category_model.dart';
import '../../core/services/sync/offline_writeable.dart';
import '../../core/storage/app_database.dart';

/// Talks to CatalogAdminApiController's category endpoints:
/// GET/POST /categories, PUT/DELETE /categories/{id}.
class CategoriesRepository with OfflineWriteable {
  CategoriesRepository(this._client, {AppDatabase? database})
      : _database = database ?? AppDatabase.instance;

  final ApiClient _client;
  final AppDatabase _database;

  @override
  AppDatabase get offlineDb => _database;

  /// Live list, refreshing the offline cache; falls back to the cache when the
  /// request fails and one exists from a previous fetch.
  Future<List<CategoryModel>> fetchCategories() async {
    try {
      final response = await _client.get(ApiEndpoints.categories);
      final categories = (response['categories'] as List? ?? [])
          .map((e) => CategoryModel.fromJson(e as Map<String, dynamic>))
          .toList();
      await _database.replaceCacheBucket(
          'categories', categories.map((c) => c.toJson()).toList());
      return categories;
    } on ApiException {
      final cached = await _database.readCacheBucket('categories');
      if (cached.isEmpty) rethrow;
      return cached.map(CategoryModel.fromJson).toList();
    }
  }

  Future<void> saveCategory(
      {String? id, required String name, String? color, String? description}) {
    final isCreate = id == null;
    final ext = id ?? newExternalId();
    final data = <String, dynamic>{
      if (!isCreate) 'id': ext,
      'external_id': ext,
      'name': name,
      if (color != null && color.isNotEmpty) 'color': color,
      if (description != null && description.isNotEmpty) 'description': description,
    };

    final optimisticRow = <String, dynamic>{
      'id': ext,
      'name': name,
      'type': null,
      'color': color,
      'description': description ?? '',
      'active': true,
      'updated_at': DateTime.now().toUtc().toIso8601String(),
    };

    return writeThrough<void>(
      op: isCreate ? 'create' : 'update',
      entity: 'category',
      externalId: ext,
      endpoint: isCreate ? ApiEndpoints.categories : ApiEndpoints.category(ext),
      method: isCreate ? 'POST' : 'PUT',
      payload: data,
      cacheBucket: 'categories',
      optimisticRow: optimisticRow,
      online: () => isCreate
          ? _client.post(ApiEndpoints.categories, data: data)
          : _client.put(ApiEndpoints.category(ext), data: data),
      offlineResult: () {},
    );
  }

  Future<void> deleteCategory(String id) => writeThrough<void>(
        op: 'delete',
        entity: 'category',
        externalId: id,
        endpoint: ApiEndpoints.category(id),
        method: 'DELETE',
        payload: const {},
        cacheBucket: 'categories',
        online: () => _client.delete(ApiEndpoints.category(id)),
        offlineResult: () {},
      );
}
