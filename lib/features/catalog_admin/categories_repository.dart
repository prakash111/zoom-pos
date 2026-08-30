import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/category_model.dart';

/// Talks to CatalogAdminApiController's category endpoints:
/// GET/POST /categories, PUT/DELETE /categories/{id}.
class CategoriesRepository {
  CategoriesRepository(this._client);

  final ApiClient _client;

  Future<List<CategoryModel>> fetchCategories() async {
    final response = await _client.get(ApiEndpoints.categories);
    return (response['categories'] as List? ?? [])
        .map((e) => CategoryModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> saveCategory({String? id, required String name, String? color, String? description}) {
    final data = {
      'name': name,
      if (color != null && color.isNotEmpty) 'color': color,
      if (description != null && description.isNotEmpty) 'description': description,
    };
    return id == null ? _client.post(ApiEndpoints.categories, data: data) : _client.put(ApiEndpoints.category(id), data: data);
  }

  Future<void> deleteCategory(String id) => _client.delete(ApiEndpoints.category(id));
}
