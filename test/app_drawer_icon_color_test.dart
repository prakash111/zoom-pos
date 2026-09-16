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
import 'package:zoom_pos_mobile/core/services/dynamic_string_service.dart';
import 'package:zoom_pos_mobile/core/services/sync/sync_engine.dart';
import 'package:zoom_pos_mobile/core/storage/app_preferences.dart';
import 'package:zoom_pos_mobile/features/auth/auth_provider.dart';
import 'package:zoom_pos_mobile/features/auth/auth_repository.dart';
import 'package:zoom_pos_mobile/features/dashboard/dashboard_screen.dart';
import 'package:zoom_pos_mobile/features/navigation/presentation/widgets/app_drawer.dart';
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
          {UserModel? user,
          CompanyModel? company,
          Map<String, dynamic>? rawUser}) async =>
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
  required ThemeProvider themeProvider,
}) {
  final fakeAuth = _FakeAuthProvider();
  fakeAuth.setAuth(user: user, company: company);

  final fakeApi = _FakeApiClient();
  final fakePrefs = _FakeAppPreferences();
  final fakeSync = _FakeSyncEngine();
  final heldCartsStore = HeldCartsStore();
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
    BootstrapCache.instance.navConfig =
        const NavConfig(sections: [], items: []);
  });

  tearDown(() {
    BootstrapCache.instance.menuStructure = [];
    BootstrapCache.instance.navConfig =
        const NavConfig(sections: [], items: []);
  });

  group('resolveItemColor unit tests', () {
    test('overrides default orange #F97316 and #EA580C with dynamic preference color', () {
      const purple = Color(0xFF8B5CF6);

      expect(resolveItemColor(null, purple), purple);
      expect(resolveItemColor('', purple), purple);
      expect(resolveItemColor('#F97316', purple), purple);
      expect(resolveItemColor('#f97316', purple), purple);
      expect(resolveItemColor('#EA580C', purple), purple);
      expect(resolveItemColor('#ea580c', purple), purple);
      expect(resolveItemColor(const Color(0xFFF97316), purple), purple);
      expect(resolveItemColor(const Color(0xFFEA580C), purple), purple);
    });

    test('retains explicit custom vertical accent colors', () {
      const purple = Color(0xFF8B5CF6);
      const green = Color(0xFF10B981);

      expect(resolveItemColor('#10B981', purple), green);
      expect(resolveItemColor(green, purple), green);
    });
  });

  group('Drawer item widget builders', () {
    testWidgets('buildDrawerItemTile renders icon and text in preference color', (tester) async {
      const purple = Color(0xFF8B5CF6);

      await tester.pumpWidget(MaterialApp(
        home: Scaffold(
          body: Builder(builder: (context) {
            return buildDrawerItemTile(
              context,
              title: 'Point of Sale',
              iconData: Icons.point_of_sale,
              activeColor: purple,
            );
          }),
        ),
      ));

      final iconWidget = tester.widget<Icon>(find.byIcon(Icons.point_of_sale));
      expect(iconWidget.color, purple);

      final textWidget = tester.widget<Text>(find.text('Point of Sale'));
      expect(textWidget.style?.color, purple);
    });

    testWidgets('buildSubMenuItemTile renders indented icon with softened variant', (tester) async {
      const purple = Color(0xFF8B5CF6);

      await tester.pumpWidget(MaterialApp(
        home: Scaffold(
          body: Builder(builder: (context) {
            return buildSubMenuItemTile(
              context,
              title: 'Sales & Invoices',
              iconData: Icons.receipt_long,
              activeColor: purple,
            );
          }),
        ),
      ));

      final iconWidget = tester.widget<Icon>(find.byIcon(Icons.receipt_long));
      expect(iconWidget.color, purple.withValues(alpha: 0.85));

      final textWidget = tester.widget<Text>(find.text('Sales & Invoices'));
      expect(textWidget.style?.color, purple);
    });
  });

  group('Dashboard Drawer integration test', () {
    testWidgets('Drawer icons bind directly to user-selected preference color and never hardcode orange', (tester) async {
      const purple = Color(0xFF8B5CF6);

      final user = UserModel(
        id: 'u1',
        companyId: 'c1',
        name: 'Admin User',
        email: 'admin@test.com',
        role: 'administrator',
        permissions: const {'*': true},
      );
      final company = CompanyModel.fromJson({
        'id': 'c1',
        'name': 'Test Store',
        'business_type': 'RETAIL',
        'plan_features': ['pos', 'sales'],
      });

      final themeProvider = ThemeProvider();
      await themeProvider.load();
      await themeProvider.setDrawerTextColor(purple);

      tester.view.physicalSize = const Size(400, 900);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      await tester.pumpWidget(
        _buildTestApp(
          user: user,
          company: company,
          themeProvider: themeProvider,
        ),
      );

      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      // Open drawer
      final ScaffoldState state = tester.state(find.byType(Scaffold));
      state.openDrawer();
      await tester.pumpAndSettle();

      expect(find.byType(Drawer), findsOneWidget);

      // Verify no icon inside Drawer is locked to the old hardcoded orange Color(0xFFF97316)
      final drawerFinder = find.byType(Drawer);
      final iconsInDrawer = tester.widgetList<Icon>(
        find.descendant(of: drawerFinder, matching: find.byType(Icon)),
      );

      expect(iconsInDrawer, isNotEmpty);
      for (final icon in iconsInDrawer) {
        expect(icon.color, isNot(const Color(0xFFF97316)));
      }

      // Check root icon (e.g. POS icon or Store Profile icon)
      // Root icons in the drawer inherit purple
      final posIcon = find.descendant(of: drawerFinder, matching: find.byIcon(Icons.point_of_sale));
      if (posIcon.evaluate().isNotEmpty) {
        final iconWidget = tester.widget<Icon>(posIcon);
        expect(iconWidget.color, purple);
      }
    });
  });
}
