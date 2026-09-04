import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'core/api/api_client.dart';
import 'core/config/bootstrap_cache.dart';
import 'core/config/locale_provider.dart';
import 'core/config/theme.dart';
import 'core/config/theme_provider.dart';
import 'core/sdui/app_router.dart';
import 'core/services/desktop/window_close_guard.dart';
import 'core/services/dynamic_string_service.dart';
import 'core/storage/app_preferences.dart';
import 'core/storage/secure_storage_service.dart';
import 'features/auth/auth_provider.dart';
import 'features/auth/auth_repository.dart';
import 'features/auth/screens/auth_gate.dart';

final GlobalKey<NavigatorState> appNavigatorKey = GlobalKey<NavigatorState>();
final GlobalKey<ScaffoldMessengerState> appMessengerKey =
    GlobalKey<ScaffoldMessengerState>();

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  final secureStorage = SecureStorageService();
  final preferences = AppPreferences();
  final apiClient =
      ApiClient(secureStorage: secureStorage, preferences: preferences);
  final authRepository = AuthRepository(apiClient);

  final authProvider = AuthProvider(
    authRepository: authRepository,
    secureStorage: secureStorage,
    apiClient: apiClient,
  );

  final themeProvider = ThemeProvider()..load();
  BootstrapCache.globalThemeProvider = themeProvider;
  final localeProvider =
      LocaleProvider(preferences: preferences, apiClient: apiClient)..load();

  // Restore session in background, then re-fetch translations now that
  // requests carry a token (LocaleProvider's own initial load may have run
  // before restoreSession finished).
  authProvider.restoreSession().then((_) => localeProvider.refreshFromServer());

  // Windows only: clear the session when the window is closed so the next
  // launch always starts at the login screen. No-op on Android.
  await WindowCloseGuard(authProvider).install();

  runApp(ZoomPosApp(
    preferences: preferences,
    apiClient: apiClient,
    authProvider: authProvider,
    themeProvider: themeProvider,
    localeProvider: localeProvider,
  ));
}

class ZoomPosApp extends StatelessWidget {
  const ZoomPosApp({
    super.key,
    required this.preferences,
    required this.apiClient,
    required this.authProvider,
    required this.themeProvider,
    required this.localeProvider,
  });

  final AppPreferences preferences;
  final ApiClient apiClient;
  final AuthProvider authProvider;
  final ThemeProvider themeProvider;
  final LocaleProvider localeProvider;

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider<AppPreferences>.value(value: preferences),
        Provider<ApiClient>.value(value: apiClient),
        ChangeNotifierProvider<BootstrapCache>.value(
            value: BootstrapCache.instance),
        ChangeNotifierProvider<AuthProvider>.value(value: authProvider),
        ChangeNotifierProvider<ThemeProvider>.value(value: themeProvider),
        ChangeNotifierProvider<LocaleProvider>.value(value: localeProvider),
        ChangeNotifierProvider<DynamicStringService>.value(
            value: DynamicStringService.instance),
      ],
      child: Consumer<ThemeProvider>(
        builder: (context, theme, _) {
          final locale = context.watch<LocaleProvider>().locale;
          return MaterialApp(
            navigatorKey: appNavigatorKey,
            scaffoldMessengerKey: appMessengerKey,
            title: 'Sales & Inventory',
            debugShowCheckedModeBanner: false,
            theme: AppTheme.light(
              seedColor: theme.seedColor,
              accentColor: theme.accentColor,
              drawerBg: theme.drawerBg,
            ),
            locale: locale,
            supportedLocales: [locale],
            localizationsDelegates: const [
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            onGenerateRoute: AppRouter.onGenerateRoute,
            home: const AuthGate(),
          );
        },
      ),
    );
  }
}
