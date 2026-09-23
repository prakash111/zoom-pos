import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/config/app_config.dart';
import 'package:zoom_pos_mobile/core/config/locale_provider.dart';
import 'package:zoom_pos_mobile/core/config/nav_dock_provider.dart';
import 'package:zoom_pos_mobile/core/config/platform_branding_provider.dart';
import 'package:zoom_pos_mobile/core/config/theme_provider.dart';
import 'package:zoom_pos_mobile/core/services/dynamic_string_service.dart';
import 'package:zoom_pos_mobile/core/services/sync/sync_engine.dart';
import 'package:zoom_pos_mobile/core/storage/app_preferences.dart';
import 'package:zoom_pos_mobile/core/storage/secure_storage_service.dart';
import 'package:zoom_pos_mobile/features/auth/auth_provider.dart';
import 'package:zoom_pos_mobile/features/auth/auth_repository.dart';
import 'package:zoom_pos_mobile/features/auth/screens/login_screen.dart';
import 'package:zoom_pos_mobile/features/auth/screens/verify_otp_screen.dart';
import 'package:zoom_pos_mobile/features/pos/held_carts_store.dart';
import 'package:zoom_pos_mobile/l10n/app_localizations.dart';

class MockOtpApiClient extends Fake implements ApiClient {
  @override
  void Function()? onUnauthenticated;

  bool postVerifyCalled = false;
  bool postResendCalled = false;

  @override
  Future<String> currentBaseUrl() async => 'https://saas.zoomnearby.com';

  @override
  Future<Map<String, dynamic>> get(String path,
      {Map<String, dynamic>? query}) async {
    if (path == ApiEndpoints.authConfig) {
      return {
        'success': true,
        'social_login': {
          'google': true,
          'facebook': true,
        },
        'demo_mode': true,
        'demo_accounts': [
          {
            'label': 'Retail',
            'store_type': 'RETAIL',
            'email': 'retail@demo.com',
            'password': 'demo1234',
          },
          {
            'label': 'Pharmacy',
            'store_type': 'PHARMACY',
            'email': 'pharmacy@demo.com',
            'password': 'demo1234',
          },
        ],
      };
    }
    return {'brand_logo_url': ''};
  }

  @override
  Future<Map<String, dynamic>> post(String path, {dynamic data}) async {
    if (path == ApiEndpoints.register) {
      // Simulate backend response requiring email OTP
      return {
        'success': true,
        'status': 'requires_verification',
        'requires_otp': true,
        'email': data['email'],
        'expires_in': 600,
        'action': 'navigate',
        'route': '/api/tenant/views/verify-otp',
      };
    }
    if (path == ApiEndpoints.verifyOtp) {
      postVerifyCalled = true;
      return {
        'success': true,
        'status': 'success',
        'token': 'zk_live_mock_token_123',
        'user': {
          'id': '10',
          'name': 'Test Owner',
          'email': data['email'],
          'role': 'admin',
          'company_id': '5',
          'permissions': {'*': true},
        },
        'company': {
          'id': '5',
          'name': 'Test Store',
          'trade_name': 'Test Store',
          'currency': 'USD',
          'currency_symbol': '\$',
          'plan_name': 'starter',
        },
      };
    }
    if (path == ApiEndpoints.resendOtp) {
      postResendCalled = true;
      return {'success': true, 'message': 'Resent'};
    }
    return {'token': 'fallback_token'};
  }
}

class MockSecureStorage extends Fake implements SecureStorageService {
  String? savedToken;

  @override
  Future<void> saveToken(String token) async {
    savedToken = token;
  }

  @override
  Future<String?> readToken() async => savedToken;
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('Registration OTP & Social Auth Tests', () {
    late MockOtpApiClient mockApi;
    late MockSecureStorage mockStorage;
    late AuthRepository authRepo;
    late AuthProvider authProvider;

    setUp(() {
      mockApi = MockOtpApiClient();
      mockStorage = MockSecureStorage();
      authRepo = AuthRepository(mockApi);
      authProvider = AuthProvider(
        authRepository: authRepo,
        secureStorage: mockStorage,
        apiClient: mockApi,
      );
    });

    test(
        'AuthRepository handles requires_otp cleanly without throwing missing token error',
        () async {
      final result = await authRepo.register(
        storeName: 'Sunrise Bakery',
        ownerName: 'Chef Pierre',
        email: 'pierre@bakery.test',
        password: 'password123',
      );

      expect(result.requiresOtp, isTrue);
      expect(result.email, 'pierre@bakery.test');
      expect(result.expiresIn, 600);
      expect(result.token, isNull);
    });

    test(
        'AuthProvider.register preserves requires_otp state for screen transition',
        () async {
      final result = await authProvider.register(
        storeName: 'Sunrise Bakery',
        ownerName: 'Chef Pierre',
        email: 'pierre@bakery.test',
        password: 'password123',
      );

      expect(result, isNotNull);
      expect(result!.requiresOtp, isTrue);
      expect(authProvider.status, AuthStatus.unauthenticated);
      expect(authProvider.errorMessage, isNull);
    });

    test('AuthRepository verifyOtp exchanges 6-digit code for token session',
        () async {
      final loginResult = await authRepo.verifyOtp(
        email: 'pierre@bakery.test',
        otp: '654321',
      );

      expect(loginResult.token, 'zk_live_mock_token_123');
      expect(loginResult.user.email, 'pierre@bakery.test');
      expect(loginResult.company.name, 'Test Store');
    });

    testWidgets(
        'VerifyOtpScreen renders 6-digit input, activate button, and cooldown link',
        (tester) async {
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<AuthProvider>.value(value: authProvider),
            ChangeNotifierProvider<PlatformBrandingProvider>.value(
              value: PlatformBrandingProvider(),
            ),
          ],
          child: const MaterialApp(
            home: VerifyOtpScreen(
              email: 'pierre@bakery.test',
            ),
          ),
        ),
      );
      await tester.pump();

      expect(find.text('Verify Your Email'), findsOneWidget);
      expect(find.textContaining('pierre@bakery.test'), findsOneWidget);
      expect(find.byType(TextFormField), findsOneWidget);
      expect(find.text('Verify & Activate Account'), findsOneWidget);
      expect(find.textContaining('Resend'), findsOneWidget);
    });

    testWidgets(
        'LoginScreen renders Social OAuth divider and Google & Facebook buttons',
        (tester) async {
      final preferences = FakeAppPreferences();
      final syncEngine = FakeSyncEngine();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            Provider<ApiClient>.value(value: mockApi),
            Provider<AppPreferences>.value(value: preferences),
            ChangeNotifierProvider<AuthProvider>.value(value: authProvider),
            ChangeNotifierProvider<HeldCartsStore>.value(
                value: HeldCartsStore()),
            ChangeNotifierProvider<ThemeProvider>.value(value: ThemeProvider()),
            ChangeNotifierProvider<PlatformBrandingProvider>.value(
              value: PlatformBrandingProvider(),
            ),
            ChangeNotifierProvider<LocaleProvider>.value(
              value:
                  LocaleProvider(preferences: preferences, apiClient: mockApi),
            ),
            ChangeNotifierProvider<NavDockProvider>.value(
              value: NavDockProvider(preferences: preferences),
            ),
            ChangeNotifierProvider<SyncEngine>.value(value: syncEngine),
            ChangeNotifierProvider<DynamicStringService>.value(
              value: DynamicStringService.instance,
            ),
          ],
          child: const MaterialApp(
            localizationsDelegates: [
              AppLocalizations.delegate,
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            home: LoginScreen(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Or continue with'), findsOneWidget);
      expect(find.text('Continue with Google'), findsOneWidget);
      expect(find.text('Continue with Facebook'), findsOneWidget);

      // "POS SYSTEMS" reference layout: "Welcome back" card + the
      // store-account-ID call-to-action. No hardcoded marketing headline —
      // it only appears when the Superadmin sets one.
      expect(find.text('Run your business smarter.'), findsNothing);
      expect(find.textContaining('Sales, inventory & orders'), findsNothing);
      // Superadmin "Platform Title / App Name" is shown in the header.
      expect(find.text('Zoom Sales CRM & Inventory'), findsWidgets);
      expect(find.text('Welcome back'), findsOneWidget);
      expect(find.text('Have a store account ID?'), findsOneWidget);
      expect(find.text('Forgot password?'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('renders DEMO_MODE quick-fill chips and auto-submits on tap',
        (tester) async {
      final preferences = FakeAppPreferences();
      final syncEngine = FakeSyncEngine();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            Provider<ApiClient>.value(value: mockApi),
            Provider<AppPreferences>.value(value: preferences),
            ChangeNotifierProvider<AuthProvider>.value(value: authProvider),
            ChangeNotifierProvider<HeldCartsStore>.value(
                value: HeldCartsStore()),
            ChangeNotifierProvider<ThemeProvider>.value(value: ThemeProvider()),
            ChangeNotifierProvider<PlatformBrandingProvider>.value(
                value: PlatformBrandingProvider()),
            ChangeNotifierProvider<LocaleProvider>.value(
              value:
                  LocaleProvider(preferences: preferences, apiClient: mockApi),
            ),
            ChangeNotifierProvider<NavDockProvider>.value(
                value: NavDockProvider(preferences: preferences)),
            ChangeNotifierProvider<SyncEngine>.value(value: syncEngine),
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
            home: LoginScreen(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Try a demo store'), findsOneWidget);
      final chip = find.widgetWithText(ActionChip, 'Retail');
      expect(chip, findsOneWidget);
      expect(find.widgetWithText(ActionChip, 'Pharmacy'), findsOneWidget);

      await tester.tap(chip);
      await tester.pump();

      // The tap filled the credentials (and fired _submit()).
      expect(find.widgetWithText(TextFormField, 'retail@demo.com'),
          findsOneWidget);
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    });
  });
}

class FakeAppPreferences extends Fake implements AppPreferences {
  @override
  Future<String> readLocale() async => 'en';

  @override
  Future<String?> readNavDockPosition() async => 'left';

  @override
  Future<String> readBaseUrl() async => 'https://saas.zoomnearby.com';
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
