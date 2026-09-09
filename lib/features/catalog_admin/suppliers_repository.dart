import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/config/app_config.dart';
import '../../core/models/supplier_model.dart';
import '../../core/services/sync/offline_writeable.dart';
import '../../core/storage/app_database.dart';

/// Talks to CatalogAdminApiController's supplier endpoints:
/// GET/POST /suppliers, PUT/DELETE /suppliers/{id}.
class SuppliersRepository with OfflineWriteable {
  SuppliersRepository(this._client, {AppDatabase? database})
      : _database = database ?? AppDatabase.instance;

  final ApiClient _client;
  final AppDatabase _database;

  @override
  AppDatabase get offlineDb => _database;

  Future<List<SupplierModel>> fetchSuppliers({String? search}) async {
    try {
      final response = await _client.get(ApiEndpoints.suppliers, query: {
        if (search != null && search.isNotEmpty) 'search': search,
      });
      final suppliers = (response['suppliers'] as List? ?? [])
          .map((e) => SupplierModel.fromJson(e as Map<String, dynamic>))
          .toList();
      // Only a full (unfiltered) fetch owns the cache bucket; a search result
      // is a subset and must not clobber it.
      if (search == null || search.isEmpty) {
        await _database.replaceCacheBucket(
            'suppliers', suppliers.map((s) => s.toJson()).toList());
      }
      return suppliers;
    } on ApiException {
      final cached = await _database.readCacheBucket('suppliers');
      if (cached.isEmpty) rethrow;
      final all = cached.map(SupplierModel.fromJson).toList();
      final q = search?.trim().toLowerCase() ?? '';
      if (q.isEmpty) return all;
      return all
          .where((s) =>
              s.name.toLowerCase().contains(q) ||
              s.legalName.toLowerCase().contains(q) ||
              s.taxId.toLowerCase().contains(q) ||
              s.email.toLowerCase().contains(q) ||
              s.phone.toLowerCase().contains(q))
          .toList();
    }
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
    final isCreate = id == null;
    final ext = id ?? newExternalId();
    final data = <String, dynamic>{
      if (!isCreate) 'id': ext,
      'external_id': ext,
      'name': name,
      if (legalName != null && legalName.isNotEmpty) 'legal_name': legalName,
      if (taxId != null && taxId.isNotEmpty) 'tax_id': taxId,
      if (email != null && email.isNotEmpty) 'email': email,
      if (phone != null && phone.isNotEmpty) 'phone': phone,
      if (city != null && city.isNotEmpty) 'city': city,
      if (state != null && state.isNotEmpty) 'state': state,
      'active': active,
    };

    return writeThrough<void>(
      op: isCreate ? 'create' : 'update',
      entity: 'supplier',
      externalId: ext,
      endpoint: isCreate ? ApiEndpoints.suppliers : ApiEndpoints.supplier(ext),
      method: isCreate ? 'POST' : 'PUT',
      payload: data,
      cacheBucket: 'suppliers',
      optimisticRow: {
        'id': ext,
        'name': name,
        'legal_name': legalName ?? '',
        'tax_id': taxId ?? '',
        'email': email ?? '',
        'phone': phone ?? '',
        'city': city ?? '',
        'state': state ?? '',
        'active': active,
        'updated_at': DateTime.now().toUtc().toIso8601String(),
      },
      online: () => isCreate
          ? _client.post(ApiEndpoints.suppliers, data: data)
          : _client.put(ApiEndpoints.supplier(ext), data: data),
      offlineResult: () {},
    );
  }

  Future<void> deleteSupplier(String id) => writeThrough<void>(
        op: 'delete',
        entity: 'supplier',
        externalId: id,
        endpoint: ApiEndpoints.supplier(id),
        method: 'DELETE',
        payload: const {},
        cacheBucket: 'suppliers',
        online: () => _client.delete(ApiEndpoints.supplier(id)),
        offlineResult: () {},
      );
}
