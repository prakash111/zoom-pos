import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'core/api/api_client.dart';
import 'core/config/bootstrap_cache.dart';
import 'core/config/locale_provider.dart';
import 'core/config/nav_dock_provider.dart';
import 'core/config/theme.dart';
import 'core/config/theme_provider.dart';
import 'core/sdui/app_router.dart';
import 'core/services/desktop/window_close_guard.dart';
import 'core/services/dynamic_string_service.dart';
import 'core/services/sync/sync_engine.dart';
import 'core/storage/app_database.dart';
import 'core/storage/app_preferences.dart';
import 'core/storage/secure_storage_service.dart';
import 'features/auth/auth_provider.dart';
import 'features/auth/auth_repository.dart';
import 'features/auth/screens/auth_gate.dart';
import 'features/customers/customers_repository.dart';
import 'features/inventory/inventory_repository.dart';
import 'features/pos/held_carts_store.dart';
import 'features/pos/sales_repository.dart';
import 'features/taxes/taxes_repository.dart';
import 'l10n/app_localizations.dart';

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

  final heldCartsStore = HeldCartsStore()..load();
  final themeProvider = ThemeProvider();
  await themeProvider.load();
  BootstrapCache.globalThemeProvider = themeProvider;
  await BootstrapCache.instance.loadFromDisk();
  final localeProvider =
      LocaleProvider(preferences: preferences, apiClient: apiClient)..load();
  final navDockProvider = NavDockProvider(preferences: preferences)..load();

  final syncEngine = SyncEngine(
    database: AppDatabase.instance,
    salesRepository: SalesRepository(apiClient),
    inventoryRepository: InventoryRepository(apiClient),
    customersRepository: CustomersRepository(apiClient),
    taxesRepository: TaxesRepository(apiClient),
  )..init();

  // Restore session in background, then re-fetch translations now that
  // requests carry a token (LocaleProvider's own initial load may have run
  // before restoreSession finished).
  authProvider
      .restoreSession()
      .then((_) => localeProvider.refreshFromServer())
      .catchError((_) {});

  // Windows only: clear the session when the window is closed so the next
  // launch always starts at the login screen. No-op on Android.
  await WindowCloseGuard(authProvider).install();

  runApp(ZoomPosApp(
    preferences: preferences,
    apiClient: apiClient,
    authProvider: authProvider,
    themeProvider: themeProvider,
    localeProvider: localeProvider,
    heldCartsStore: heldCartsStore,
    navDockProvider: navDockProvider,
    syncEngine: syncEngine,
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
    required this.heldCartsStore,
    required this.navDockProvider,
    required this.syncEngine,
  });

  final AppPreferences preferences;
  final ApiClient apiClient;
  final AuthProvider authProvider;
  final ThemeProvider themeProvider;
  final LocaleProvider localeProvider;
  final HeldCartsStore heldCartsStore;
  final NavDockProvider navDockProvider;
  final SyncEngine syncEngine;

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider<AppPreferences>.value(value: preferences),
        Provider<ApiClient>.value(value: apiClient),
        ChangeNotifierProvider<BootstrapCache>.value(
            value: BootstrapCache.instance),
        ChangeNotifierProvider<AuthProvider>.value(value: authProvider),
        ChangeNotifierProvider<HeldCartsStore>.value(value: heldCartsStore),
        ChangeNotifierProvider<ThemeProvider>.value(value: themeProvider),
        ChangeNotifierProvider<LocaleProvider>.value(value: localeProvider),
        ChangeNotifierProvider<NavDockProvider>.value(value: navDockProvider),
        ChangeNotifierProvider<SyncEngine>.value(value: syncEngine),
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
            supportedLocales: {
              locale,
              const Locale('en'),
              const Locale('es'),
              const Locale('fr'),
              const Locale('ar'),
              const Locale('hi'),
              const Locale('bn'),
              const Locale('ta'),
              const Locale('te'),
              const Locale('mr'),
              const Locale('gu'),
              const Locale('kn'),
              const Locale('ml'),
              const Locale('pa'),
              const Locale('ur'),
              const Locale('zh'),
              const Locale('ja'),
              const Locale('de'),
              const Locale('pt'),
              const Locale('ru'),
              const Locale('it'),
              const Locale('ko'),
              const Locale('tr'),
              const Locale('vi'),
              const Locale('th'),
              const Locale('id'),
            }.toList(),
            localizationsDelegates: const [
              AppLocalizations.delegate,
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
