import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/unit_model.dart';

/// Talks to CatalogAdminApiController's unit endpoints:
/// GET/POST /units, PUT/DELETE /units/{id}.
class UnitsRepository {
  UnitsRepository(this._client);

  final ApiClient _client;

  Future<List<UnitModel>> fetchUnits() async {
    final response = await _client.get(ApiEndpoints.units);
    return (response['units'] as List? ?? []).map((e) => UnitModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> saveUnit({String? id, required String name, String? abbreviation}) {
    final data = {
      'name': name,
      if (abbreviation != null && abbreviation.isNotEmpty) 'abbreviation': abbreviation,
    };
    return id == null ? _client.post(ApiEndpoints.units, data: data) : _client.put(ApiEndpoints.unit(id), data: data);
  }

  Future<void> deleteUnit(String id) => _client.delete(ApiEndpoints.unit(id));
}
