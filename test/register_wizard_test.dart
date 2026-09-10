import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/config/app_config.dart';
import 'package:zoom_pos_mobile/core/config/locale_provider.dart';
import 'package:zoom_pos_mobile/core/config/platform_branding_provider.dart';
import 'package:zoom_pos_mobile/core/config/theme_provider.dart';
import 'package:zoom_pos_mobile/core/services/dynamic_string_service.dart';
import 'package:zoom_pos_mobile/core/storage/app_preferences.dart';
import 'package:zoom_pos_mobile/core/storage/secure_storage_service.dart';
import 'package:zoom_pos_mobile/features/auth/auth_provider.dart';
import 'package:zoom_pos_mobile/features/auth/auth_repository.dart';
import 'package:zoom_pos_mobile/features/auth/screens/register_screen.dart';
import 'package:zoom_pos_mobile/l10n/app_localizations.dart';

class _RegisterApiClient extends Fake implements ApiClient {
  Map<String, dynamic>? capturedRegister;

  @override
  void Function()? onUnauthenticated;

  @override
  Future<String> currentBaseUrl() async => 'https://saas.zoomnearby.com';

  @override
  Future<Map<String, dynamic>> getAbsolute(String url,
      {Map<String, dynamic>? query}) async {
    return {
      'registration_modes': [
        {'key': 'retail', 'title': 'Retail', 'icon': 'store'},
        {'key': 'restaurant', 'title': 'Restaurant', 'icon': 'restaurant'},
      ],
      'default_mode': 'retail',
    };
  }

  @override
  Future<Map<String, dynamic>> get(String path,
      {Map<String, dynamic>? query}) async {
    return {'brand_logo_url': ''};
  }

  @override
  Future<Map<String, dynamic>> post(String path, {dynamic data}) async {
    if (path == ApiEndpoints.register) {
      capturedRegister = Map<String, dynamic>.from(data as Map);
      return {
        'success': true,
        'status': 'requires_verification',
        'requires_otp': true,
        'email': data['email'],
        'expires_in': 600,
      };
    }
    return {'token': 'fallback'};
  }
}

class _MemStorage extends Fake implements SecureStorageService {
  String? _t;
  @override
  Future<void> saveToken(String token) async => _t = token;
  @override
  Future<String?> readToken() async => _t;
}

class _FakePreferences extends Fake implements AppPreferences {
  @override
  Future<String> readLocale() async => 'en';
  @override
  Future<String> readBaseUrl() async => 'https://saas.zoomnearby.com';
}

void main() {
  Widget host(Widget child, _RegisterApiClient api, AuthProvider auth) {
    final prefs = _FakePreferences();
    return MultiProvider(
      providers: [
        Provider<ApiClient>.value(value: api),
        Provider<AppPreferences>.value(value: prefs),
        ChangeNotifierProvider<AuthProvider>.value(value: auth),
        ChangeNotifierProvider<ThemeProvider>.value(value: ThemeProvider()),
        ChangeNotifierProvider<PlatformBrandingProvider>.value(
            value: PlatformBrandingProvider()),
        ChangeNotifierProvider<LocaleProvider>.value(
            value: LocaleProvider(preferences: prefs, apiClient: api)),
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
        supportedLocales: const [Locale('en')],
        home: child,
      ),
    );
  }

  testWidgets('registration is a 3-step wizard that collects locale + currency',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(1000, 1600));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final api = _RegisterApiClient();
    final auth = AuthProvider(
      authRepository: AuthRepository(api),
      secureStorage: _MemStorage(),
      apiClient: api,
    );

    await tester.pumpWidget(host(const RegisterScreen(), api, auth));
    await tester.pumpAndSettle();

    // --- Step 1 ---------------------------------------------------------
    expect(find.text('Step 1 of 3'), findsOneWidget);
    expect(find.text('Account credentials'), findsOneWidget);
    expect(find.text('Continue'), findsOneWidget);
    expect(find.text('Create store'), findsNothing);

    await tester.enterText(
        find.widgetWithText(TextFormField, 'Your name'), 'Ada Lovelace');
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Email'), 'ada@corner.test');
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Password'), 'sup3rsecret');
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // --- Step 2 ---------------------------------------------------------
    expect(find.text('Step 2 of 3'), findsOneWidget);
    expect(find.text('Store profile'), findsOneWidget);
    await tester.enterText(
        find.widgetWithText(TextFormField, 'Store name'), 'Corner Cafe');
    await tester.tap(find.text('Restaurant'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // --- Step 3 ---------------------------------------------------------
    expect(find.text('Step 3 of 3'), findsOneWidget);
    expect(find.text('Localization & currency'), findsOneWidget);
    expect(find.text('Create store'), findsOneWidget);

    // Country is required — submitting without it is blocked.
    await tester.tap(find.text('Create store'));
    await tester.pumpAndSettle();
    expect(api.capturedRegister, isNull);
    expect(find.text('Select a country'), findsOneWidget);

    // Pick a country from the searchable sheet.
    await tester.tap(find.byKey(const ValueKey<String>('picker_Country')));
    await tester.pumpAndSettle();
    await tester.enterText(
        find.byType(TextField).last, 'Brazil');
    await tester.pumpAndSettle();
    await tester.tap(find.text('Brazil (BR)').last);
    await tester.pumpAndSettle();

    await tester.tap(find.text('Create store'));
    await tester.pumpAndSettle();

    final sent = api.capturedRegister;
    expect(sent, isNotNull);
    expect(sent!['store_name'], 'Corner Cafe');
    expect(sent['name'], 'Ada Lovelace');
    expect(sent['pos_mode'], 'restaurant');
    expect(sent['country'], 'BR');
    expect(sent['currency'], 'USD');
    expect((sent['timezone'] as String).isNotEmpty, isTrue);
  });
}
