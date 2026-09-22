import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/config/bootstrap_cache.dart';
import 'package:zoom_pos_mobile/core/stores/store_provider.dart';
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
          {UserModel? user,
          CompanyModel? company,
          Map<String, dynamic>? rawUser}) async =>
      true;

  @override
  Future<void> logout() async {}

  @override
  Future<Map<String, dynamic>> checkSubdomain(String subdomain) async => {
        'available': true,
        'subdomain': subdomain,
      };

  @override
  Future<RegisterResult?> register({
    required String storeName,
    required String ownerName,
    required String email,
    required String password,
    String? phone,
    String? currency,
    String? country,
    String? timezone,
    String posMode = 'general',
    String? subdomain,
    String? customDomain,
  }) async =>
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
  int? activeStoreId;
  @override
  String? activeTenantId = 'test';
  @override
  String? activeUserId = 'test-user';
  @override
  String cacheBucket(String bucket) =>
      '$bucket@test:test-user:${activeStoreId ?? 'primary'}';
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
      ChangeNotifierProvider<StoreProvider>(
          create: (_) => StoreProvider(fakeApi)),
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
    BootstrapCache.instance.navConfig =
        const NavConfig(sections: [], items: []);
  });

  tearDown(() {
    BootstrapCache.instance.menuStructure = [];
    BootstrapCache.instance.navConfig =
        const NavConfig(sections: [], items: []);
  });

  group('Drawer Menu & SDUI Resolution Tests', () {
    test(
        'CompanyModel and TenantSchema round-trip businessType and planFeatures',
        () {
      final company = CompanyModel.fromJson({
        'id': 'c1',
        'name': 'ZoomNearby',
        'plan_name': 'Pro',
        'business_type': 'RETAIL',
        'plan_features': [
          'pos',
          'sales',
          'quotes',
          'leads',
          'consignments',
          'customers'
        ],
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

    test('section schema prioritizes backend first_item parent hierarchy', () {
      final section = SduiNavSectionSchema.fromJson({
        'key': 'lead_ops',
        'title': 'Lead Management',
        'items': [
          {
            'key': 'legacy_lead_page',
            'title': 'Legacy Lead Page',
            'target_endpoint': '/api/tenant/views/legacy-leads',
          },
        ],
        'first_item': {
          'key': 'lead_dashboard',
          'title': 'Lead Dashboard',
          'icon': 'grid_view',
          'route': '/api/tenant/lead-module/views/dashboard',
        },
        'sub_items': [
          {
            'key': 'lead_pipeline',
            'title': 'All Leads Pipeline',
            'route': '/api/tenant/lead-module/views/leads',
          },
        ],
      });

      expect(section.firstItem?.key, 'lead_dashboard');
      expect(section.firstItem?.title, 'Lead Dashboard');
      expect(
          section.firstItem?.route, '/api/tenant/lead-module/views/dashboard');
      expect(section.subItems.map((item) => item.key),
          containsAllInOrder(['lead_pipeline', 'legacy_lead_page']));
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

      final disabledLeadAdmin = UserModel(
        id: 'u3',
        companyId: 'c1',
        name: 'prakash',
        email: 'prakashks.bbm@gmail.com',
        role: 'administrator',
        permissions: const {
          '*': true,
          'leads': false,
          'leads.view': false,
          'lead_management': false,
          'lead_management.view': false,
        },
      );
      expect(disabledLeadAdmin.can('leads.view'), isFalse);
      expect(disabledLeadAdmin.can('lead_management.view'), isFalse);
      expect(disabledLeadAdmin.can('pos.view'), isTrue);
    });

    test('BootstrapCache defaults activeMode and activeModule gracefully',
        () async {
      final cache = BootstrapCache.instance;
      expect(cache.activeMode, 'retail');
      expect(cache.activeModule.title, 'Retail');

      await cache.loadFromDisk();
      final fallbacks = cache.effectiveSections;
      final cashierSec = fallbacks.firstWhere((s) => s.key == 'cashier_sales');
      final itemKeys = cashierSec.items.map((i) => i.key).toList();
      expect(
          itemKeys,
          containsAll([
            'pos',
            'sales',
            'quotations',
            'consignments',
            'customers',
          ]));
      expect(itemKeys, isNot(contains('lead_management')));
    });

    testWidgets(
        'Drawer renders all 5 core cashier items without lead_management, RETAIL badge, and user avatar',
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
          const NavItemConfig(
              key: 'pos',
              section: 'cashier_sales',
              level: 0,
              order: 0,
              visible: true),
          NavItemConfig.fromJson({
            'key': 'sales',
            'section': 'cashier_sales',
            'parent': 'pos',
            'parent_id': 'pos',
            'level': 1,
            'order': 1,
            'visible': true,
          }),
          NavItemConfig.fromJson({
            'key': 'quotations',
            'section': 'cashier_sales',
            'parent': 'pos',
            'parent_id': 'pos',
            'level': 1,
            'order': 2,
            'visible': true,
          }),
          NavItemConfig.fromJson({
            'key': 'lead_management',
            'section': 'cashier_sales',
            'level': 0,
            'order': 2,
            'visible': true,
          }),
          NavItemConfig.fromJson({
            'key': 'customers',
            'section': 'cashier_sales',
            'parent': 'pos',
            'parent_id': 'pos',
            'level': 1,
            'order': 3,
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

      // 2. The custom section heading is separate from its actionable parent.
      expect(find.text('POINT OF SALE'), findsOneWidget);
      expect(find.byKey(const ValueKey('drawer-item-pos')), findsOneWidget);
      expect(find.byKey(const ValueKey('drawer-item-sales')), findsNothing);
      expect(find.byKey(const ValueKey('drawer-item-lead_management')),
          findsNothing);

      expect(find.byKey(const ValueKey('drawer-section-divider-cashier_sales')),
          findsOneWidget);
      final pointOfSaleParent = tester
          .widget<ListTile>(find.byKey(const ValueKey('drawer-item-pos')));
      expect(pointOfSaleParent.onTap, isNotNull);
      expect(pointOfSaleParent.trailing, isNull);

      // Only the independent chevron expands the section children.
      await tester.tap(find.byKey(const ValueKey('drawer-expand-pos')));
      await tester.pumpAndSettle();

      expect(find.byKey(const ValueKey('drawer-item-sales')), findsOneWidget);
      expect(
          find.byKey(const ValueKey('drawer-item-quotations')), findsOneWidget);
      expect(find.byKey(const ValueKey('drawer-item-consignments')),
          findsOneWidget);
      expect(
          find.byKey(const ValueKey('drawer-item-customers')), findsOneWidget);

      final salesChild = tester
          .widget<ListTile>(find.byKey(const ValueKey('drawer-item-sales')));
      expect(salesChild.contentPadding,
          const EdgeInsets.only(left: 46, right: 12));

      // 3. The parent no longer uses ExpansionTile's row-wide tap handler.
      expect(
          find.byKey(
              const PageStorageKey<String>('drawer-branch-cashier_sales-pos')),
          findsOneWidget);
      expect(find.byType(ExpansionTile), findsNothing);

      // 4. Verify user avatar is removed from footer and Help & Support button is rendered
      final drawer = find.byType(Drawer);
      expect(find.descendant(of: drawer, matching: find.text('prakash')),
          findsNothing);
      expect(
          find.descendant(
              of: drawer, matching: find.text('prakash@example.com')),
          findsNothing);
      expect(find.widgetWithText(ListTile, 'Help & Support'), findsOneWidget);
    });

    testWidgets(
        'Main Menu items with level 0 render as independent roots not trapped in Store Profile',
        (tester) async {
      tester.view.physicalSize = const Size(400, 1100);
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

      BootstrapCache.instance.menuStructure = const [
        SduiNavSectionSchema(
          key: 'administration',
          title: 'Administration & Settings',
          items: [
            SduiNavItemSchema(
              key: 'settings_profile',
              title: 'Store Profile',
              icon: 'storefront',
            ),
            SduiNavItemSchema(
              key: 'languages',
              title: 'Languages & Translations',
              icon: 'translate',
            ),
            SduiNavItemSchema(
              key: 'staff',
              title: 'Users & Permissions',
              icon: 'badge',
            ),
            SduiNavItemSchema(
              key: 'roles',
              title: 'Roles & Access Levels',
              icon: 'admin_panel_settings',
            ),
            SduiNavItemSchema(
              key: 'devices',
              title: 'Terminals & Devices',
              icon: 'devices_other',
            ),
            SduiNavItemSchema(
              key: 'hardware_printer',
              title: 'Printer & Hardware Setup',
              icon: 'print',
            ),
            SduiNavItemSchema(
              key: 'change_password',
              title: 'Change Password',
              icon: 'lock_reset',
            ),
          ],
        ),
      ];
      addTearDown(() => BootstrapCache.instance.menuStructure = []);

      BootstrapCache.instance.navConfig = NavConfig(
        sections: const [NavSectionOrder(key: 'administration', order: 0)],
        items: [
          const NavItemConfig(
            key: 'settings_profile',
            section: 'administration',
            level: 0,
            order: 0,
            visible: true,
          ),
          const NavItemConfig(
            key: 'languages',
            section: 'administration',
            level: 0,
            order: 1,
            visible: true,
          ),
          const NavItemConfig(
            key: 'staff',
            section: 'administration',
            parent: 'languages',
            level: 1,
            order: 2,
            visible: true,
          ),
          const NavItemConfig(
            key: 'roles',
            section: 'administration',
            parent: 'languages',
            level: 1,
            order: 3,
            visible: true,
          ),
          const NavItemConfig(
            key: 'devices',
            section: 'administration',
            level: 0,
            order: 4,
            visible: true,
          ),
          const NavItemConfig(
            key: 'hardware_printer',
            section: 'administration',
            level: 0,
            order: 5,
            visible: true,
          ),
          const NavItemConfig(
            key: 'change_password',
            section: 'administration',
            level: 0,
            order: 6,
            visible: true,
          ),
        ],
      );

      await tester.pumpWidget(_buildTestApp(user: user, company: company));
      await tester.pumpAndSettle();

      final scaffoldState = tester.state<ScaffoldState>(find.byType(Scaffold));
      scaffoldState.openDrawer();
      await tester.pumpAndSettle();

      // Store Profile is a standalone tile at depth 0, NOT an accordion containing the other items
      expect(find.byKey(const ValueKey('drawer-item-settings_profile')),
          findsOneWidget);
      expect(find.byKey(const ValueKey('drawer-expand-settings_profile')),
          findsNothing);

      // Languages & Translations, Devices, Hardware Printer, Change Password are all visible at root level
      expect(
          find.byKey(const ValueKey('drawer-item-languages')), findsOneWidget);
      expect(find.byKey(const ValueKey('drawer-item-devices')), findsOneWidget);
      expect(find.byKey(const ValueKey('drawer-item-hardware_printer')),
          findsOneWidget);
      expect(find.byKey(const ValueKey('drawer-item-change_password')),
          findsOneWidget);

      // Sub-items of languages (staff, roles) are not visible until languages is expanded
      expect(find.byKey(const ValueKey('drawer-item-staff')), findsNothing);
      expect(find.byKey(const ValueKey('drawer-item-roles')), findsNothing);

      // Expand languages
      await tester.tap(find.byKey(const ValueKey('drawer-expand-languages')));
      await tester.pumpAndSettle();

      // Now staff and roles appear
      expect(find.byKey(const ValueKey('drawer-item-staff')), findsOneWidget);
      expect(find.byKey(const ValueKey('drawer-item-roles')), findsOneWidget);

      // Verify indentation: staff has left padding 46 (16 + 1 * 30)
      final staffTile = tester
          .widget<ListTile>(find.byKey(const ValueKey('drawer-item-staff')));
      expect(
          staffTile.contentPadding, const EdgeInsets.only(left: 46, right: 12));
    });

    test(
        'CompanyModel and TenantSchema resolveLiveStoreUrl formats domain properly',
        () {
      final companyWithSlug = CompanyModel(
        id: '1',
        name: 'Demo Mart',
        tradeName: 'Demo Mart',
        currency: 'USD',
        currencySymbol: '\$',
        planName: 'pro',
        slug: 'metro-mart',
      );
      expect(companyWithSlug.resolveLiveStoreUrl(),
          'https://metro-mart.saas.zoomnearby.com');

      final companyWithCustomDomain = CompanyModel(
        id: '2',
        name: 'Custom Mart',
        tradeName: 'Custom Mart',
        currency: 'USD',
        currencySymbol: '\$',
        planName: 'pro',
        slug: 'custom-mart',
        customDomain: 'store.custommart.com',
      );
      expect(companyWithCustomDomain.resolveLiveStoreUrl(),
          'https://store.custommart.com');

      final tenantSchema = TenantSchema(
        id: '1',
        businessName: 'Test Business',
        activeMode: 'retail',
        subdomain: 'online-shop',
      );
      expect(tenantSchema.resolveLiveStoreUrl(),
          'https://online-shop.saas.zoomnearby.com');
    });
  });
}
