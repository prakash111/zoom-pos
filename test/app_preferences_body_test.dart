import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/config/nav_dock_provider.dart';
import 'package:zoom_pos_mobile/core/config/theme_provider.dart';
import 'package:zoom_pos_mobile/core/models/company_model.dart';
import 'package:zoom_pos_mobile/core/storage/app_preferences.dart';
import 'package:zoom_pos_mobile/features/auth/auth_provider.dart';
import 'package:zoom_pos_mobile/features/settings/screens/app_preferences_screen.dart';
import 'package:zoom_pos_mobile/l10n/app_localizations.dart';

class _FakeApiClient extends Fake implements ApiClient {}

class _FakeAuthProvider extends ChangeNotifier implements AuthProvider {
  @override
  CompanyModel? get company => CompanyModel(
        id: '1',
        name: 'Test Store',
        tradeName: 'Test Store',
        currency: 'USD',
        currencySymbol: r'$',
        planName: 'trial',
      );

  @override
  dynamic noSuchMethod(Invocation invocation) => null;
}

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  Widget host(Widget child) => MultiProvider(
        providers: [
          Provider<ApiClient>.value(value: _FakeApiClient()),
          ChangeNotifierProvider<ThemeProvider>.value(value: ThemeProvider()),
          ChangeNotifierProvider<NavDockProvider>.value(
              value: NavDockProvider(preferences: AppPreferences())),
          ChangeNotifierProvider<AuthProvider>.value(value: _FakeAuthProvider()),
        ],
        child: MaterialApp(
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
          ],
          supportedLocales: const [Locale('en')],
          home: Scaffold(body: child),
        ),
      );

  testWidgets('App Preferences shows real controls, not a notice card',
      (tester) async {
    tester.view.physicalSize = const Size(1000, 3000);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(host(const AppPreferencesBody()));
    await tester.pumpAndSettle();

    // The old placeholder text is gone.
    expect(find.textContaining('stored on each device'), findsNothing);
    expect(find.textContaining('Theme, animations & layout'), findsNothing);

    // All the functional groups are present.
    expect(find.text('Brand colour'), findsOneWidget);
    expect(find.text('Dashboard layout'), findsOneWidget);
    expect(find.text('Theme'), findsOneWidget);
    expect(find.text('Page transition'), findsOneWidget);
    expect(find.text('Match device'), findsOneWidget);
    expect(find.text('Slide'), findsOneWidget);
  });

  testWidgets('tapping a brand swatch recolours the theme instantly',
      (tester) async {
    final theme = ThemeProvider();
    await tester.pumpWidget(
      MultiProvider(
        providers: [
          Provider<ApiClient>.value(value: _FakeApiClient()),
          ChangeNotifierProvider<ThemeProvider>.value(value: theme),
          ChangeNotifierProvider<NavDockProvider>.value(
              value: NavDockProvider(preferences: AppPreferences())),
          ChangeNotifierProvider<AuthProvider>.value(value: _FakeAuthProvider()),
        ],
        child: MaterialApp(
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
          ],
          supportedLocales: const [Locale('en')],
          home: const Scaffold(body: AppPreferencesBody()),
        ),
      ),
    );
    await tester.pumpAndSettle();

    final before = theme.seedColor.toARGB32();
    // The violet preset swatch.
    await tester.tap(find.byWidgetPredicate((w) =>
        w is Container &&
        w.decoration is BoxDecoration &&
        (w.decoration as BoxDecoration).color?.toARGB32() ==
            const Color(0xFF7C3AED).toARGB32()));
    await tester.pump();

    expect(theme.seedColor.toARGB32(), const Color(0xFF7C3AED).toARGB32());
    expect(theme.seedColor.toARGB32(), isNot(before));
  });
}
