import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/config/bootstrap_cache.dart';
import 'package:zoom_pos_mobile/core/config/locale_provider.dart';
import 'package:zoom_pos_mobile/core/config/nav_dock_provider.dart';
import 'package:zoom_pos_mobile/core/config/platform_branding_provider.dart';
import 'package:zoom_pos_mobile/core/config/theme_provider.dart';
import 'package:zoom_pos_mobile/core/models/company_model.dart';
import 'package:zoom_pos_mobile/core/models/settings_models.dart';
import 'package:zoom_pos_mobile/core/models/user_model.dart';
import 'package:zoom_pos_mobile/core/sdui/models/sdui_models.dart';
import 'package:zoom_pos_mobile/core/services/dynamic_string_service.dart';
import 'package:zoom_pos_mobile/core/services/sync/sync_engine.dart';
import 'package:zoom_pos_mobile/core/storage/app_preferences.dart';
import 'package:zoom_pos_mobile/features/auth/auth_provider.dart';
import 'package:zoom_pos_mobile/features/auth/auth_repository.dart';
import 'package:zoom_pos_mobile/features/dashboard/dashboard_screen.dart';
import 'package:zoom_pos_mobile/features/pos/held_carts_store.dart';
import 'package:zoom_pos_mobile/l10n/app_localizations.dart';

class _FakeAuthProvider extends ChangeNotifier implements AuthProvider {
  AuthStatus _status = AuthStatus.authenticated;
  UserModel? _user;
  CompanyModel? _company;

  @override
  Future<void> Function()? onBeforeLogout;

  @override
  AuthStatus get status => _status;

  @override
  UserModel? get user => _user;

  @override
  CompanyModel? get company => _company;

  void setAuth({required UserModel user, required CompanyModel company}) {
    _user = user;
    _company = company;
    _status = AuthStatus.authenticated;
    notifyListeners();
  }

  @override
  String? get errorMessage => null;

  @override
  bool get isBusy => false;

  @override
  RegisterResult? get lastRegisterResult => null;

  @override
  RegisterResult? get pendingEmailVerification => null;

  @override
  Future<bool> login(
          {required String email,
          required String password,
          String? accountId}) async =>
      true;

  @override
  Future<bool> loginWithToken(String token,
          {UserModel? user, CompanyModel? company, Map<String, dynamic>? rawUser}) async =>
      true;

  @override
  Future<void> logout() async {}

  @override
  Future<RegisterResult?> register(
          {required String storeName,
          required String ownerName,
          required String email,
          required String password,
          String? phone,
          String? currency,
          String? country,
          String? timezone,
          String posMode = 'general'}) async =>
      null;

  @override
  Future<void> resendOtp({required String email}) async {}

  @override
  Future<bool> restoreSession() async => true;

  @override
  Future<bool> verifyOtp({required String email, required String otp}) async =>
      true;

  @override
  bool get isOfflineSession => false;

  @override
  Future<void> refreshSessionIfOffline() async {}

  @override
  void updateCompany(CompanyModel Function(CompanyModel current) updater) {
    if (_company != null) {
      _company = updater(_company!);
      notifyListeners();
    }
  }

  @override
  Future<void> reloadSession() async {}
}

class _FakeApiClient extends Fake implements ApiClient {
  @override
  Future<String> currentBaseUrl() async => 'https://saas.zoomnearby.com';

  @override
  Future<Map<String, dynamic>> getAbsolute(
    String path, {
    Map<String, dynamic>? query,
  }) async =>
      get(path, query: query);

  @override
  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? query,
  }) async {
    return <String, dynamic>{
      'today_revenue': 1250.0,
      'today_orders_count': 15,
      'today_profit': 340.0,
      'low_stock_count': 2,
      'total_receivables': 0.0,
      'revenue_trend': <dynamic>[],
      'top_products': <dynamic>[],
      'payment_breakdown': <dynamic>[],
    };
  }
}

class _FakeAppPreferences extends Fake implements AppPreferences {
  @override
  Future<String> readLocale() async => 'en';

  @override
  Future<String?> readNavDockPosition() async => 'left';
}

class _FakeSyncEngine extends Fake implements SyncEngine {
  @override
  bool isSyncing = false;

  @override
  int unsyncedCount = 0;

  @override
  DateTime? lastSyncedAt;

  @override
  String? lastError;

  @override
  Future<void> init() async {}

  @override
  Future<void> syncNow() async {}

  @override
  void addListener(VoidCallback listener) {}

  @override
  void removeListener(VoidCallback listener) {}

  @override
  bool get hasListeners => false;
}

Widget _buildTestApp({
  required UserModel user,
  required CompanyModel company,
}) {
  final fakeAuth = _FakeAuthProvider();
  fakeAuth.setAuth(user: user, company: company);

  final fakeApi = _FakeApiClient();
  final fakePrefs = _FakeAppPreferences();
  final fakeSync = _FakeSyncEngine();
  final heldCartsStore = HeldCartsStore();
  final themeProvider = ThemeProvider();
  final localeProvider =
      LocaleProvider(preferences: fakePrefs, apiClient: fakeApi);
  final navDockProvider = NavDockProvider(preferences: fakePrefs);

  return MultiProvider(
    providers: [
      Provider<AppPreferences>.value(value: fakePrefs),
      Provider<ApiClient>.value(value: fakeApi),
      ChangeNotifierProvider<BootstrapCache>.value(
          value: BootstrapCache.instance),
      ChangeNotifierProvider<AuthProvider>.value(value: fakeAuth),
      ChangeNotifierProvider<HeldCartsStore>.value(value: heldCartsStore),
      ChangeNotifierProvider<ThemeProvider>.value(value: themeProvider),
      ChangeNotifierProvider<PlatformBrandingProvider>.value(
          value: PlatformBrandingProvider()),
      ChangeNotifierProvider<LocaleProvider>.value(value: localeProvider),
      ChangeNotifierProvider<NavDockProvider>.value(value: navDockProvider),
      ChangeNotifierProvider<SyncEngine>.value(value: fakeSync),
      ChangeNotifierProvider<DynamicStringService>.value(
          value: DynamicStringService.instance),
    ],
    child: const MaterialApp(
      localizationsDelegates: [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: DashboardScreen(),
    ),
  );
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    BootstrapCache.instance.menuStructure = [];
    BootstrapCache.instance.navConfig = const NavConfig(sections: [], items: []);
  });

  tearDown(() {
    BootstrapCache.instance.menuStructure = [];
    BootstrapCache.instance.navConfig = const NavConfig(sections: [], items: []);
  });

  group('Drawer Menu & SDUI Resolution Tests', () {
    test('CompanyModel and TenantSchema round-trip businessType and planFeatures', () {
      final company = CompanyModel.fromJson({
        'id': 'c1',
        'name': 'ZoomNearby',
        'plan_name': 'Pro',
        'business_type': 'RETAIL',
        'plan_features': ['pos', 'sales', 'quotes', 'leads', 'consignments', 'customers'],
      });

      expect(company.businessType, 'RETAIL');
      expect(company.planFeatures, contains('quotes'));
      expect(company.planFeatures, contains('leads'));

      final tenant = TenantSchema.fromJson({
        'id': 'c1',
        'name': 'ZoomNearby',
        'business_type': 'RETAIL',
        'plan_features': ['pos', 'sales', 'quotes', 'leads'],
      });
      expect(tenant.businessType, 'RETAIL');
      expect(tenant.planFeatures, contains('quotes'));
    });

    test('UserModel.can handles permission aliases and admin bypass', () {
      final adminUser = UserModel(
        id: 'u1',
        companyId: 'c1',
        name: 'prakash',
        email: 'prakash@example.com',
        role: 'admin',
        permissions: const {},
      );
      expect(adminUser.can('quotes.view'), isTrue);
      expect(adminUser.can('leads.view'), isTrue);
      expect(adminUser.can('consignments.view'), isTrue);
      expect(adminUser.can('customers.view'), isTrue);

      final cashierUser = UserModel(
        id: 'u2',
        companyId: 'c1',
        name: 'cashier',
        email: 'cashier@example.com',
        role: 'cashier',
        permissions: const {
          'quotes.view': true,
          'leads.view': true,
          'consignments.view': true,
          'customers.view': true,
        },
      );
      expect(cashierUser.can('quotations.view'), isTrue);
      expect(cashierUser.can('lead_management.view'), isTrue);
      expect(cashierUser.can('quotes.view'), isTrue);
      expect(cashierUser.can('leads.view'), isTrue);
    });

    test('BootstrapCache defaults activeMode and activeModule gracefully', () async {
      final cache = BootstrapCache.instance;
      expect(cache.activeMode, 'retail');
      expect(cache.activeModule.title, 'Retail');

      await cache.loadFromDisk();
      final fallbacks = cache.effectiveSections;
      final cashierSec = fallbacks.firstWhere((s) => s.key == 'cashier_sales');
      final itemKeys = cashierSec.items.map((i) => i.key).toList();
      expect(itemKeys, containsAll([
        'pos',
        'sales',
        'quotations',
        'lead_management',
        'consignments',
        'customers',
      ]));
    });

    testWidgets('Drawer renders all 6 cashier items, RETAIL badge, and user avatar',
        (tester) async {
      tester.view.physicalSize = const Size(400, 900);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      final user = UserModel(
        id: 'u1',
        companyId: 'c1',
        name: 'prakash',
        email: 'prakash@example.com',
        role: 'admin',
        permissions: const {'*': true},
      );

      final company = CompanyModel(
        id: 'c1',
        name: 'ZoomNearby',
        planName: 'Pro',
        tradeName: 'ZoomNearby',
        businessType: 'RETAIL',
        posMode: 'general',
        currency: 'USD',
        currencySymbol: '\$',
      );

      // Simulate a corrupt legacy nav_config where quotations and customers were nested under pos
      BootstrapCache.instance.navConfig = NavConfig(
        sections: const [NavSectionOrder(key: 'cashier_sales', order: 0)],
        items: [
          const NavItemConfig(key: 'pos', section: 'cashier_sales', level: 0, order: 0, visible: true),
          NavItemConfig.fromJson({
            'key': 'quotations',
            'section': 'cashier_sales',
            'parent': 'pos',
            'parent_id': 'pos',
            'level': 1,
            'order': 1,
            'visible': true,
          }),
          NavItemConfig.fromJson({
            'key': 'customers',
            'section': 'cashier_sales',
            'parent': 'pos',
            'parent_id': 'pos',
            'level': 1,
            'order': 2,
            'visible': true,
          }),
        ],
      );

      await tester.pumpWidget(_buildTestApp(user: user, company: company));
      await tester.pumpAndSettle();

      // Open mobile drawer
      final scaffoldState = tester.state<ScaffoldState>(find.byType(Scaffold));
      scaffoldState.openDrawer();
      await tester.pumpAndSettle();

      // 1. Verify tenant business type badge renders RETAIL (not empty oval)
      expect(find.text('RETAIL'), findsOneWidget);

      // 2. Verify all 6 Cashier & Sales items are present as ListTiles
      expect(find.byKey(const ValueKey('drawer-item-pos')), findsOneWidget);
      expect(find.byKey(const ValueKey('drawer-item-sales')), findsOneWidget);
      expect(find.byKey(const ValueKey('drawer-item-quotations')), findsOneWidget);
      expect(find.byKey(const ValueKey('drawer-item-lead_management')), findsOneWidget);
      expect(find.byKey(const ValueKey('drawer-item-consignments')), findsOneWidget);
      expect(find.byKey(const ValueKey('drawer-item-customers')), findsOneWidget);

      // 3. Verify Point of Sale is NOT an ExpansionTile
      expect(find.byKey(const PageStorageKey<String>('drawer-branch-cashier_sales-pos')), findsNothing);

      // 4. Verify user avatar and logout button are rendered
      expect(find.text('prakash'), findsOneWidget);
      expect(find.text('prakash@example.com'), findsOneWidget);
      expect(find.widgetWithText(ListTile, 'Log Out'), findsOneWidget);
    });
  });
}
