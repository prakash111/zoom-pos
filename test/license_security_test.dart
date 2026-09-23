import 'dart:convert';
import 'package:crypto/crypto.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:zoom_pos_mobile/core/security/license_security_engine.dart';
import 'package:zoom_pos_mobile/screens/auth/server_address_screen.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    LicenseSecurityEngine.clientOverride = null;
  });

  tearDown(() {
    LicenseSecurityEngine.clientOverride = null;
  });

  group('LicenseSecurityEngine', () {
    test('resolves deobfuscated endpoint dynamically at runtime', () {
      final endpoint = LicenseSecurityEngine.resolveLicenseEndpoint();
      // Reconstituted runtime URL without plain-text storage
      expect(
        endpoint,
        equals('https://' 'license.' 'zoomnearby' '.com' '/api/v2/verify-entitlement'),
      );
    });

    test('generates correct SHA-256 HMAC signature and payload', () async {
      const salt = 'ZN_LIC_AUTH_SECURE_SALT_984321748921';
      Map<String, dynamic>? interceptedBody;
      Map<String, String>? interceptedHeaders;

      LicenseSecurityEngine.clientOverride = MockClient((request) async {
        interceptedHeaders = request.headers;
        interceptedBody = jsonDecode(request.body) as Map<String, dynamic>;

        return http.Response(
          jsonEncode({
            'status': 'authorized',
            'code': 200,
            'license_tier': 'regular',
            'license_token': 'encrypted_token_123',
            'entitlements': {
              'app_access': true,
              'white_label_branding': false,
            },
            'branding': null,
          }),
          200,
        );
      });

      final result = await LicenseSecurityEngine.verifyServerDomain('https://pos.myclient.com///');

      expect(result['status'], equals('authorized'));
      expect(result['code'], equals(200));
      expect(result['license_tier'], equals('regular'));

      expect(interceptedHeaders?['Content-Type'], equals('application/json'));
      expect(interceptedBody?['server_url'], equals('https://pos.myclient.com'));

      final timestamp = interceptedBody?['timestamp'];
      final platform = interceptedBody?['platform'];
      final signature = interceptedBody?['signature'];

      // Verify HMAC calculation
      final expectedRaw = 'https://pos.myclient.com|$timestamp|$platform';
      final expectedSig = Hmac(sha256, utf8.encode(salt))
          .convert(utf8.encode(expectedRaw))
          .toString();

      expect(signature, equals(expectedSig));
    });
  });

  group('ServerAddressScreen', () {
    testWidgets('shows validation error snackbar on invalid URL format', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: ServerAddressScreen(),
        ),
      );
      await tester.pumpAndSettle();

      final textField = find.byType(TextFormField);
      await tester.enterText(textField, 'not-a-valid-url');

      final saveButton = find.widgetWithText(ElevatedButton, 'Save');
      await tester.tap(saveButton);
      await tester.pumpAndSettle();

      expect(find.text('Enter a full URL, e.g. https://your-store.com'), findsOneWidget);
    });

    testWidgets('successful regular tier verification saves credentials and routes to login', (tester) async {
      LicenseSecurityEngine.clientOverride = MockClient((request) async {
        return http.Response(
          jsonEncode({
            'status': 'authorized',
            'code': 200,
            'license_tier': 'regular',
            'license_token': 'test_regular_jwt_token',
            'entitlements': {
              'app_access': true,
              'white_label_branding': false,
              'custom_package_name': false,
              'ready_compiled_files': false,
              'installation_support': false,
            },
            'branding': null,
            'upgrade_notice': {
              'title': 'Regular License Active',
              'message': 'Need white-label? Upgrade to Extended.',
              'url': 'https://zoomnearby.com/upgrade',
            },
          }),
          200,
        );
      });

      var routedToLogin = false;

      await tester.pumpWidget(
        MaterialApp(
          routes: {
            '/login': (_) {
              routedToLogin = true;
              return const Scaffold(body: Text('Login Screen'));
            },
          },
          home: const ServerAddressScreen(),
        ),
      );
      await tester.pumpAndSettle();

      final textField = find.byType(TextFormField);
      await tester.enterText(textField, 'https://regular-client.com');

      final saveButton = find.widgetWithText(ElevatedButton, 'Save');
      await tester.tap(saveButton);
      await tester.pumpAndSettle();

      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getString('custom_server_url'), equals('https://regular-client.com'));
      expect(prefs.getString('active_license_token'), equals('test_regular_jwt_token'));
      expect(prefs.getString('license_tier'), equals('regular'));
      expect(prefs.getString('whitelabel_config'), isNull);
      expect(routedToLogin, isTrue);
    });

    testWidgets('successful extended tier verification caches whitelabel branding configuration', (tester) async {
      final brandingMap = {
        'app_name': 'My Super POS',
        'primary_color': '#3B82F6',
        'logo_url': 'https://extended-enterprise.com/logo.png',
      };

      LicenseSecurityEngine.clientOverride = MockClient((request) async {
        return http.Response(
          jsonEncode({
            'status': 'authorized',
            'code': 200,
            'license_tier': 'extended',
            'license_token': 'test_extended_token_jwt',
            'entitlements': {
              'app_access': true,
              'white_label_branding': true,
              'custom_package_name': true,
              'ready_compiled_files': true,
              'installation_support': true,
            },
            'branding': brandingMap,
            'upgrade_notice': null,
          }),
          200,
        );
      });

      await tester.pumpWidget(
        MaterialApp(
          routes: {
            '/login': (_) => const Scaffold(body: Text('Login Screen')),
          },
          home: const ServerAddressScreen(),
        ),
      );
      await tester.pumpAndSettle();

      final textField = find.byType(TextFormField);
      await tester.enterText(textField, 'https://extended-enterprise.com');

      final saveButton = find.widgetWithText(ElevatedButton, 'Save');
      await tester.tap(saveButton);
      await tester.pumpAndSettle();

      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getString('custom_server_url'), equals('https://extended-enterprise.com'));
      expect(prefs.getString('active_license_token'), equals('test_extended_token_jwt'));
      expect(prefs.getString('license_tier'), equals('extended'));
      expect(prefs.getString('whitelabel_config'), equals(jsonEncode(brandingMap)));
    });

    testWidgets('unregistered domain blocks connection with license alert modal and buy button', (tester) async {
      LicenseSecurityEngine.clientOverride = MockClient((request) async {
        return http.Response(
          jsonEncode({
            'status': 'unregistered',
            'code': 403,
            'message': 'Your domain is not registered. You are not authorized to access. Buy a valid core script license to continue.',
            'buy_url': 'https://zoomnearby.com/pricing',
          }),
          403,
        );
      });

      await tester.pumpWidget(
        const MaterialApp(
          home: ServerAddressScreen(),
        ),
      );
      await tester.pumpAndSettle();

      final textField = find.byType(TextFormField);
      await tester.enterText(textField, 'https://pirated-unregistered-site.com');

      final saveButton = find.widgetWithText(ElevatedButton, 'Save');
      await tester.tap(saveButton);
      await tester.pumpAndSettle();

      // Blocking modal dialog checks
      expect(find.byType(AlertDialog), findsOneWidget);
      expect(find.text('Unauthorized Server'), findsOneWidget);
      expect(
        find.text('Your domain is not registered. You are not authorized to access. Buy a valid core script license to continue.'),
        findsOneWidget,
      );
      expect(find.widgetWithText(ElevatedButton, 'Buy License'), findsOneWidget);
      expect(find.widgetWithText(TextButton, 'Cancel'), findsOneWidget);

      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getString('custom_server_url'), isNull);
      expect(prefs.getString('active_license_token'), isNull);
    });

    testWidgets('network error presents verification failed alert with retry button', (tester) async {
      LicenseSecurityEngine.clientOverride = MockClient((request) async {
        throw Exception('Network connection timed out');
      });

      await tester.pumpWidget(
        const MaterialApp(
          home: ServerAddressScreen(),
        ),
      );
      await tester.pumpAndSettle();

      final textField = find.byType(TextFormField);
      await tester.enterText(textField, 'https://offline-server.com');

      final saveButton = find.widgetWithText(ElevatedButton, 'Save');
      await tester.tap(saveButton);
      await tester.pumpAndSettle();

      expect(find.byType(AlertDialog), findsOneWidget);
      expect(find.text('Verification Failed'), findsOneWidget);
      expect(
        find.text('Could not connect to the license verification server. Check your connection and try again.'),
        findsOneWidget,
      );
      expect(find.widgetWithText(ElevatedButton, 'Retry'), findsOneWidget);
    });
  });
}
