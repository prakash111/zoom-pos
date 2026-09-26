import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/stores/store_provider.dart';
import 'package:zoom_pos_mobile/screens/dashboard/widgets/dashboard_app_bar.dart';
import 'package:zoom_pos_mobile/widgets/navigation/custom_app_drawer.dart';

class _FakeApiClient extends Fake implements ApiClient {
  @override
  String? activeTenantId = 'tenant-1';
  @override
  String? activeUserId = 'admin';
  @override
  int? activeStoreId = 1;
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('Store Display Name Formatting (4-Char Header vs Full Drawer)', () {
    test('StoreBranch correctly initializes short_name (4 chars) and full_name', () {
      final jsonPayload = {
        'id': 1,
        'name': 'ZoomNearby Enterprise Demo',
        'code': 'ZNE-DEMO',
        'is_primary': true,
        'short_name': 'Zoom',
        'full_name': 'ZoomNearby Enterprise Demo',
      };

      final branch = StoreBranch.fromJson(jsonPayload);
      expect(branch.shortName, 'Zoom');
      expect(branch.name, 'ZoomNearby Enterprise Demo');
      expect(branch.fullName, 'ZoomNearby Enterprise Demo');
      expect(branch.effectiveShortName, 'Zoom');
      expect(branch.effectiveFullName, 'ZoomNearby Enterprise Demo');
    });

    test('StoreBranch falls back to 4-character truncation when short_name is absent', () {
      final jsonPayload = {
        'id': 2,
        'name': 'ZoomNearby Enterprise Demo',
        'code': 'ZNE-DEMO',
        'is_primary': false,
      };

      final branch = StoreBranch.fromJson(jsonPayload);
      expect(branch.shortName, 'Zoom');
      expect(branch.name, 'ZoomNearby Enterprise Demo');
      expect(branch.fullName, 'ZoomNearby Enterprise Demo');
      expect(branch.effectiveShortName, 'Zoom');
      expect(branch.effectiveFullName, 'ZoomNearby Enterprise Demo');
    });

    test('StoreBranch preserves names shorter than 4 characters without padding', () {
      final jsonPayload = {
        'id': 3,
        'name': 'AB',
        'code': 'AB-01',
        'is_primary': false,
      };

      final branch = StoreBranch.fromJson(jsonPayload);
      expect(branch.shortName, 'AB');
      expect(branch.name, 'AB');
      expect(branch.fullName, 'AB');
    });

    testWidgets('DashboardHeaderStoreName and DashboardAppBar display strictly 4 characters ("Zoom")',
        (tester) async {
      final fakeApi = _FakeApiClient();
      final provider = StoreProvider(fakeApi);
      provider.stores = [
        const StoreBranch(
          id: 1,
          name: 'ZoomNearby Enterprise Demo',
          fullName: 'ZoomNearby Enterprise Demo',
          shortName: 'Zoom',
          code: 'ZNE-DEMO',
          isPrimary: true,
          isActive: true,
          isCurrent: true,
        ),
      ];
      provider.current = provider.stores.first;

      await tester.pumpWidget(
        ChangeNotifierProvider<StoreProvider>.value(
          value: provider,
          child: const MaterialApp(
            home: Scaffold(
              appBar: DashboardAppBar(),
              body: Center(
                child: DashboardHeaderStoreName(),
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Top bar header displays strictly the first 4 characters "Zoom"
      expect(find.text('Zoom'), findsNWidgets(2)); // Once in DashboardAppBar, once in body
      expect(find.text('ZoomNearby Enterprise Demo'), findsNothing);
      expect(find.text('Zoo'), findsNothing);
    });

    testWidgets('CustomDrawerHeader displays complete unmodified store name ("ZoomNearby Enterprise Demo")',
        (tester) async {
      final fakeApi = _FakeApiClient();
      final provider = StoreProvider(fakeApi);
      provider.stores = [
        const StoreBranch(
          id: 1,
          name: 'ZoomNearby Enterprise Demo',
          fullName: 'ZoomNearby Enterprise Demo',
          shortName: 'Zoom',
          code: 'ZNE-DEMO',
          isPrimary: true,
          isActive: true,
          isCurrent: true,
        ),
      ];
      provider.current = provider.stores.first;

      await tester.pumpWidget(
        ChangeNotifierProvider<StoreProvider>.value(
          value: provider,
          child: const MaterialApp(
            home: Scaffold(
              body: CustomDrawerHeader(),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Drawer header displays the complete store name
      expect(find.text('ZoomNearby Enterprise Demo'), findsOneWidget);
      expect(find.text('Zoom'), findsNothing);
      expect(find.text('Zoo'), findsNothing);
    });

    testWidgets('Header displays "Zoom" while Navigation Drawer reveals full business name in Scaffold',
        (tester) async {
      final fakeApi = _FakeApiClient();
      final provider = StoreProvider(fakeApi);
      provider.stores = [
        const StoreBranch(
          id: 1,
          name: 'ZoomNearby Enterprise Demo',
          fullName: 'ZoomNearby Enterprise Demo',
          shortName: 'Zoom',
          code: 'ZNE-DEMO',
          isPrimary: true,
          isActive: true,
          isCurrent: true,
        ),
      ];
      provider.current = provider.stores.first;

      await tester.pumpWidget(
        ChangeNotifierProvider<StoreProvider>.value(
          value: provider,
          child: MaterialApp(
            home: Scaffold(
              appBar: const DashboardAppBar(),
              drawer: const Drawer(
                child: SafeArea(
                  child: Column(
                    children: [
                      CustomDrawerHeader(),
                      ListTile(title: Text('Dashboard')),
                      ListTile(title: Text('POS')),
                    ],
                  ),
                ),
              ),
              body: const SizedBox(),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Top app bar displays "Zoom" (4 characters)
      expect(find.text('Zoom'), findsOneWidget);

      // Open drawer
      final scaffoldState = tester.state<ScaffoldState>(find.byType(Scaffold));
      scaffoldState.openDrawer();
      await tester.pumpAndSettle();

      // Navigation drawer displays full store name ("ZoomNearby Enterprise Demo")
      expect(find.text('ZoomNearby Enterprise Demo'), findsOneWidget);
    });
  });
}
