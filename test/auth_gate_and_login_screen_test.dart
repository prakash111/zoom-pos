import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/models/company_model.dart';
import 'package:zoom_pos_mobile/core/models/user_model.dart';
import 'package:zoom_pos_mobile/core/storage/app_preferences.dart';
import 'package:zoom_pos_mobile/features/auth/auth_provider.dart';
import 'package:zoom_pos_mobile/features/auth/screens/auth_gate.dart';
import 'package:zoom_pos_mobile/features/auth/screens/login_screen.dart';
import 'package:zoom_pos_mobile/l10n/app_localizations.dart';

class FakeAuthProvider extends ChangeNotifier implements AuthProvider {
  AuthStatus _status = AuthStatus.unknown;
  String? _errorMessage;

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
  CompanyModel? get company => null;

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
  Future<bool> register({
    required String storeName,
    required String ownerName,
    required String email,
    required String password,
    String? phone,
    String? currency,
    String posMode = 'general',
  }) async => true;

  @override
  Future<void> logout() async {}

  @override
  Future<void> restoreSession() async {
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }
}

class FakeApiClient extends Fake implements ApiClient {
  @override
  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? query,
  }) async {
    return <String, dynamic>{};
  }
}

class FakeAppPreferences extends Fake implements AppPreferences {}

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

    Widget buildTestApp({required Widget child}) {
      return MultiProvider(
        providers: [
          ChangeNotifierProvider<AuthProvider>.value(value: fakeAuth),
          Provider<ApiClient>.value(value: fakeApi),
          Provider<AppPreferences>.value(value: fakePreferences),
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

    setUp(() {
      fakeAuth = FakeAuthProvider();
      fakeApi = FakeApiClient();
      fakePreferences = FakeAppPreferences();
    });

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
  });
}
