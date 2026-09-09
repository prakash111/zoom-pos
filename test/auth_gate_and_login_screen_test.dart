import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/config/bootstrap_cache.dart';
import 'package:zoom_pos_mobile/core/config/locale_provider.dart';
import 'package:zoom_pos_mobile/core/config/nav_dock_provider.dart';
import 'package:zoom_pos_mobile/core/config/theme_provider.dart';
import 'package:zoom_pos_mobile/core/models/company_model.dart';
import 'package:zoom_pos_mobile/core/models/user_model.dart';
import 'package:zoom_pos_mobile/core/services/dynamic_string_service.dart';
import 'package:zoom_pos_mobile/core/services/sync/sync_engine.dart';
import 'package:zoom_pos_mobile/core/storage/app_preferences.dart';
import 'package:zoom_pos_mobile/features/auth/auth_provider.dart';
import 'package:zoom_pos_mobile/features/auth/auth_repository.dart';
import 'package:zoom_pos_mobile/features/auth/screens/auth_gate.dart';
import 'package:zoom_pos_mobile/features/auth/screens/login_screen.dart';
import 'package:zoom_pos_mobile/features/dashboard/dashboard_screen.dart';
import 'package:zoom_pos_mobile/features/pos/held_carts_store.dart';
import 'package:zoom_pos_mobile/l10n/app_localizations.dart';

class FakeAuthProvider extends ChangeNotifier implements AuthProvider {
  AuthStatus _status = AuthStatus.unknown;
  String? _errorMessage;
  CompanyModel? _company;

  @override
  AuthStatus get status => _status;

  set status(AuthStatus value) {
    _status = value;
    notifyListeners();
  }

  @override
  String? get errorMessage => _errorMessage;

  @override
  bool get isBusy => _status == AuthStatus.authenticating;

  @override
  CompanyModel? get company => _company;

  set company(CompanyModel? value) {
    _company = value;
    notifyListeners();
  }

  @override
  UserModel? get user => null;

  @override
  Future<void> Function()? onBeforeLogout;

  @override
  Future<bool> login({
    required String email,
    required String password,
    String? accountId,
  }) async => true;

  @override
  RegisterResult? get lastRegisterResult => null;

  @override
  RegisterResult? get pendingEmailVerification => null;

  @override
  Future<RegisterResult?> register({
    required String storeName,
    required String ownerName,
    required String email,
    required String password,
    String? phone,
    String? currency,
    String posMode = 'general',
  }) async => RegisterResult(requiresOtp: false, token: 'fake_token');

  @override
  Future<bool> verifyOtp({
    required String email,
    required String otp,
  }) async => true;

  @override
  Future<void> resendOtp({required String email}) async {}

  @override
  Future<bool> loginWithToken(
    String token, {
    UserModel? user,
    CompanyModel? company,
  }) async => true;

  @override
  Future<void> logout() async {}

  @override
  Future<void> restoreSession() async {
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  @override
  bool get isOfflineSession => false;

  @override
  Future<void> refreshSessionIfOffline() async {}
}

class FakeApiClient extends Fake implements ApiClient {
  @override
  Future<String> currentBaseUrl() async => 'https://saas.zoomnearby.com';

  @override
  Future<Map<String, dynamic>> getAbsolute(
    String path, {
    Map<String, dynamic>? query,
  }) async => get(path, query: query);

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

class FakeAppPreferences extends Fake implements AppPreferences {
  @override
  Future<String> readLocale() async => 'en';

  @override
  Future<String?> readNavDockPosition() async => 'left';
}

class FakeSyncEngine extends Fake implements SyncEngine {
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

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('AppLocalizations null-safety fallback', () {
    testWidgets('AppLocalizations.of() falls back safely when delegate is absent',
        (tester) async {
      late AppLocalizations resolved;

      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) {
              resolved = AppLocalizations.of(context);
              return Text(resolved.signIn);
            },
          ),
        ),
      );
      await tester.pump();

      expect(resolved, isNotNull);
      expect(find.text(resolved.signIn), findsOneWidget);
    });

    testWidgets(
        'AppLocalizations.of() functions correctly when delegate is registered',
        (tester) async {
      late AppLocalizations resolved;
      await tester.pumpWidget(
        MaterialApp(
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          home: Builder(
            builder: (context) {
              resolved = AppLocalizations.of(context);
              return Text(resolved.signIn);
            },
          ),
        ),
      );
      await tester.pump();

      expect(resolved, isNotNull);
      expect(find.text(resolved.signIn), findsOneWidget);
    });
  });

  group('AuthGate and Splash Transition', () {
    late FakeAuthProvider fakeAuth;
    late FakeApiClient fakeApi;
    late FakeAppPreferences fakePreferences;
    late FakeSyncEngine fakeSync;
    late HeldCartsStore heldCartsStore;
    late ThemeProvider themeProvider;
    late LocaleProvider localeProvider;
    late NavDockProvider navDockProvider;

    setUp(() {
      fakeAuth = FakeAuthProvider();
      fakeApi = FakeApiClient();
      fakePreferences = FakeAppPreferences();
      fakeSync = FakeSyncEngine();
      heldCartsStore = HeldCartsStore();
      themeProvider = ThemeProvider();
      localeProvider = LocaleProvider(preferences: fakePreferences, apiClient: fakeApi);
      navDockProvider = NavDockProvider(preferences: fakePreferences);
    });

    Widget buildTestApp({required Widget child}) {
      return MultiProvider(
        providers: [
          Provider<AppPreferences>.value(value: fakePreferences),
          Provider<ApiClient>.value(value: fakeApi),
          ChangeNotifierProvider<BootstrapCache>.value(
              value: BootstrapCache.instance),
          ChangeNotifierProvider<AuthProvider>.value(value: fakeAuth),
          ChangeNotifierProvider<HeldCartsStore>.value(value: heldCartsStore),
          ChangeNotifierProvider<ThemeProvider>.value(value: themeProvider),
          ChangeNotifierProvider<LocaleProvider>.value(value: localeProvider),
          ChangeNotifierProvider<NavDockProvider>.value(value: navDockProvider),
          ChangeNotifierProvider<SyncEngine>.value(value: fakeSync),
          ChangeNotifierProvider<DynamicStringService>.value(
              value: DynamicStringService.instance),
        ],
        child: MaterialApp(
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          home: child,
        ),
      );
    }

    testWidgets('AuthGate renders splash indicator when status is unknown',
        (tester) async {
      fakeAuth.status = AuthStatus.unknown;

      await tester.pumpWidget(buildTestApp(child: const AuthGate()));
      await tester.pump();

      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(find.byIcon(Icons.storefront), findsOneWidget);
      expect(find.byType(LoginScreen), findsNothing);

      // Clean up timer by settling or advancing
      await tester.pump(const Duration(seconds: 5));
    });

    testWidgets(
        'AuthGate transitions to LoginScreen cleanly without white screen crash',
        (tester) async {
      fakeAuth.status = AuthStatus.unknown;

      await tester.pumpWidget(buildTestApp(child: const AuthGate()));
      await tester.pump();
      expect(find.byType(CircularProgressIndicator), findsOneWidget);

      // Simulate session restore finishing with unauthenticated state
      fakeAuth.status = AuthStatus.unauthenticated;
      await tester.pump();

      expect(find.byType(CircularProgressIndicator), findsNothing);
      expect(find.byType(LoginScreen), findsOneWidget);

      // Clean up timer
      await tester.pump(const Duration(seconds: 5));
    });

    testWidgets(
        'AuthGate transitions to DashboardScreen when authenticated without missing provider crash',
        (tester) async {
      fakeAuth.company = CompanyModel(
        id: '1',
        name: 'Acme Supermarket',
        tradeName: 'Acme Supermarket',
        currency: 'USD',
        currencySymbol: '\$',
        planName: 'Pro',
      );
      fakeAuth.status = AuthStatus.authenticated;

      await tester.pumpWidget(buildTestApp(child: const AuthGate()));
      await tester.pump();

      expect(tester.takeException(), isNull);
      expect(find.byType(DashboardScreen), findsOneWidget);
      expect(find.text('Acme Supermarket'), findsWidgets);

      // Settle analytics future
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(find.text('Acme Supermarket'), findsWidgets);
    });
  });
}
