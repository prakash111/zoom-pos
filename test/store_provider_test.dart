import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:zoom_pos_mobile/core/api/api_exception.dart';
import 'package:zoom_pos_mobile/features/stores/store_switcher_sheet.dart';
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

  @override
  String cacheBucket(String bucket) => 'test.$bucket';
  @override
  Future<Map<String, dynamic>> get(String path,
          {Map<String, dynamic>? query}) async =>
      {};

  bool offline = false;
  bool denied = false;
  Map<String, dynamic>? payload;
  final requests = <String>[];

  @override
  Future<Map<String, dynamic>> getAbsolute(String path,
      {Map<String, dynamic>? query}) async {
    requests.add('GET $path');
    if (denied) throw ApiException('Forbidden', statusCode: 403);
    if (offline) throw StateError('offline');
    return payload ??
        {
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
    requests.add('POST $path');
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
    expect(api.requests, contains('POST /api/v1/tenant/stores/2/switch'));
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

  Map<String, dynamic> canonical({int limit = 5, bool create = true}) => {
        'success': true,
        'data': [
          {'id': '1', 'name': 'Main', 'code': 'MAIN', 'is_primary': '1'},
          {
            'id': '2',
            'name': 'North',
            'code': 'NORTH',
            'address': 'North Street',
            'is_current': true
          },
        ],
        'meta': {
          'total_stores': 2,
          'max_allowed_stores': limit,
          'can_create_more': create && limit > 2,
          'can_create': create,
          'can_manage': create
        },
      };

  test('canonical list parses numeric strings and selected flag', () async {
    SharedPreferences.setMockInitialValues({});
    final api = FakeStoreApi()..payload = canonical();
    final provider = StoreProvider(api);
    await provider.load();
    expect(provider.current?.id, 2);
    expect(provider.current?.address, 'North Street');
    expect(provider.canCreateMore, isTrue);
    expect(api.activeStoreId, 2);
  });

  test('permission denial clears cached stores and creation grants', () async {
    SharedPreferences.setMockInitialValues({});
    final api = FakeStoreApi()..payload = canonical();
    final provider = StoreProvider(api);
    await provider.load();
    api.denied = true;
    await provider.load();
    expect(provider.stores, isEmpty);
    expect(provider.canCreateMore, isFalse);
    expect(provider.error, 'Forbidden');
    expect(api.activeStoreId, isNull);
  });

  testWidgets(
      'switcher shows branch address and create action only within quota and permissions',
      (tester) async {
    SharedPreferences.setMockInitialValues({});
    final api = FakeStoreApi()..payload = canonical();
    final provider = StoreProvider(api);
    await tester.pumpWidget(ChangeNotifierProvider.value(
        value: provider,
        child: const MaterialApp(home: Scaffold(body: StoreSwitcherSheet()))));
    await tester.pumpAndSettle();
    expect(find.textContaining('North Street'), findsOneWidget);
    expect(find.byIcon(Icons.check_circle), findsOneWidget);
    expect(find.text('Add New Store / Branch'), findsOneWidget);
    api.payload = canonical(limit: 2);
    await provider.load();
    await tester.pumpAndSettle();
    expect(find.text('Add New Store / Branch'), findsNothing);
    api.payload = canonical(create: false);
    await provider.load();
    await tester.pumpAndSettle();
    expect(find.text('Add New Store / Branch'), findsNothing);
  });
}
