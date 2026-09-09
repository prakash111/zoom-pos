import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/config/app_config.dart';
import '../../core/models/unit_model.dart';
import '../../core/services/sync/offline_writeable.dart';
import '../../core/storage/app_database.dart';

/// Talks to CatalogAdminApiController's unit endpoints:
/// GET/POST /units, PUT/DELETE /units/{id}.
class UnitsRepository with OfflineWriteable {
  UnitsRepository(this._client, {AppDatabase? database})
      : _database = database ?? AppDatabase.instance;

  final ApiClient _client;
  final AppDatabase _database;

  @override
  AppDatabase get offlineDb => _database;

  Future<List<UnitModel>> fetchUnits() async {
    try {
      final response = await _client.get(ApiEndpoints.units);
      final units = (response['units'] as List? ?? [])
          .map((e) => UnitModel.fromJson(e as Map<String, dynamic>))
          .toList();
      await _database.replaceCacheBucket(
          'units', units.map((u) => u.toJson()).toList());
      return units;
    } on ApiException {
      final cached = await _database.readCacheBucket('units');
      if (cached.isEmpty) rethrow;
      return cached.map(UnitModel.fromJson).toList();
    }
  }

  Future<void> saveUnit({String? id, required String name, String? abbreviation}) {
    final isCreate = id == null;
    final ext = id ?? newExternalId();
    final data = <String, dynamic>{
      if (!isCreate) 'id': ext,
      'external_id': ext,
      'name': name,
      if (abbreviation != null && abbreviation.isNotEmpty)
        'abbreviation': abbreviation,
    };

    return writeThrough<void>(
      op: isCreate ? 'create' : 'update',
      entity: 'unit',
      externalId: ext,
      endpoint: isCreate ? ApiEndpoints.units : ApiEndpoints.unit(ext),
      method: isCreate ? 'POST' : 'PUT',
      payload: data,
      cacheBucket: 'units',
      optimisticRow: {
        'id': ext,
        'name': name,
        'abbreviation': abbreviation ?? '',
        'updated_at': DateTime.now().toUtc().toIso8601String(),
      },
      online: () => isCreate
          ? _client.post(ApiEndpoints.units, data: data)
          : _client.put(ApiEndpoints.unit(ext), data: data),
      offlineResult: () {},
    );
  }

  Future<void> deleteUnit(String id) => writeThrough<void>(
        op: 'delete',
        entity: 'unit',
        externalId: id,
        endpoint: ApiEndpoints.unit(id),
        method: 'DELETE',
        payload: const {},
        cacheBucket: 'units',
        online: () => _client.delete(ApiEndpoints.unit(id)),
        offlineResult: () {},
      );
}
