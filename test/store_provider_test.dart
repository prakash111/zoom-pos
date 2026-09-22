import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/stores/store_provider.dart';

class FakeStoreApi extends Fake implements ApiClient {
  @override
  String? activeTenantId = 'tenant-1';
  @override
  String? activeUserId = 'admin';
  @override
  int? activeStoreId;

  bool offline = false;
  final requests = <String>[];

  @override
  Future<Map<String, dynamic>> getAbsolute(String path,
      {Map<String, dynamic>? query}) async {
    requests.add('GET $path');
    if (offline) throw StateError('offline');
    return {
      'stores': [
        {'id': 1, 'name': 'Main', 'code': 'main', 'is_primary': true},
        {'id': 2, 'name': 'North', 'code': 'north', 'is_primary': false},
      ],
      'current_store_id': 1,
      'store_limit': 2,
      'store_count': 2,
      'can_create': true,
    };
  }

  @override
  Future<Map<String, dynamic>> postAbsolute(String path,
      {Map<String, dynamic>? data}) async {
    requests.add('POST $path ${data?['store_id']}');
    return {'success': true};
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test(
      'store switching updates request context and offline cache stays with its user',
      () async {
    SharedPreferences.setMockInitialValues({});
    final api = FakeStoreApi();
    final adminStores = StoreProvider(api);
    await adminStores.load();
    expect(adminStores.current?.id, 1);
    expect(adminStores.stores.length, 2);

    await adminStores.switchTo(adminStores.stores.last);
    expect(api.requests.last, 'POST /api/v1/tenant/stores/switch 2');
    expect(api.activeStoreId, 2);

    api.offline = true;
    final cachedAdminStores = StoreProvider(api);
    await cachedAdminStores.load();
    expect(cachedAdminStores.current?.id, 2);

    api.activeUserId = 'staff';
    final staffStores = StoreProvider(api);
    await staffStores.load();
    expect(staffStores.stores, isEmpty);
    expect(staffStores.current, isNull);
    expect(api.activeStoreId, isNull);
  });
}
