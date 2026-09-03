import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'core/api/api_client.dart';
import 'core/config/locale_provider.dart';
import 'core/config/nav_dock_provider.dart';
import 'core/config/theme.dart';
import 'core/config/theme_provider.dart';
import 'core/services/desktop/window_close_guard.dart';
import 'core/services/push_notification_service.dart';
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
  final themeProvider = ThemeProvider()..load();
  final localeProvider =
      LocaleProvider(preferences: preferences, apiClient: apiClient)..load();
  final navDockProvider = NavDockProvider(preferences: preferences)..load();

  // Restore session in background, then re-fetch translations now that
  // requests carry a token (LocaleProvider's own initial load may have run
  // before restoreSession finished).
  authProvider.restoreSession().then((_) => localeProvider.refreshFromServer());

  // Central push config is fetched from SuperAdmin-owned settings. Android
  // device registration follows automatically once the session is restored.
  PushNotificationService.instance
      .initialize(apiClient: apiClient, authProvider: authProvider);

  // Windows only: clear the session when the window is closed so the next
  // launch always starts at the login screen. No-op on Android.
  await WindowCloseGuard(authProvider).install();

  final syncEngine = SyncEngine(
    database: AppDatabase.instance,
    salesRepository: SalesRepository(apiClient),
    inventoryRepository: InventoryRepository(apiClient),
    customersRepository: CustomersRepository(apiClient),
    taxesRepository: TaxesRepository(apiClient),
  )..init();

  runApp(ZoomPosApp(
    preferences: preferences,
    apiClient: apiClient,
    authProvider: authProvider,
    heldCartsStore: heldCartsStore,
    themeProvider: themeProvider,
    localeProvider: localeProvider,
    syncEngine: syncEngine,
    navDockProvider: navDockProvider,
  ));
}

class ZoomPosApp extends StatelessWidget {
  const ZoomPosApp({
    super.key,
    required this.preferences,
    required this.apiClient,
    required this.authProvider,
    required this.heldCartsStore,
    required this.themeProvider,
    required this.localeProvider,
    required this.syncEngine,
    required this.navDockProvider,
  });

  final AppPreferences preferences;
  final ApiClient apiClient;
  final AuthProvider authProvider;
  final HeldCartsStore heldCartsStore;
  final ThemeProvider themeProvider;
  final LocaleProvider localeProvider;
  final SyncEngine syncEngine;
  final NavDockProvider navDockProvider;

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider<AppPreferences>.value(value: preferences),
        Provider<ApiClient>.value(value: apiClient),
        ChangeNotifierProvider<AuthProvider>.value(value: authProvider),
        ChangeNotifierProvider<HeldCartsStore>.value(value: heldCartsStore),
        ChangeNotifierProvider<ThemeProvider>.value(value: themeProvider),
        ChangeNotifierProvider<LocaleProvider>.value(value: localeProvider),
        ChangeNotifierProvider<SyncEngine>.value(value: syncEngine),
        ChangeNotifierProvider<NavDockProvider>.value(value: navDockProvider),
      ],
      child: Consumer<ThemeProvider>(
        builder: (context, theme, _) {
          final locale = context.watch<LocaleProvider>().locale;
          return MaterialApp(
            navigatorKey: appNavigatorKey,
            scaffoldMessengerKey: appMessengerKey,
            title: 'Sales & Inventory',
            debugShowCheckedModeBanner: false,
            theme: AppTheme.light(seedColor: theme.seedColor),
            locale: locale,
            supportedLocales: LocaleProvider.supportedCodes.map(Locale.new),
            localizationsDelegates: const [
              AppLocalizations.delegate,
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            home: const AuthGate(),
          );
        },
      ),
    );
  }
}
