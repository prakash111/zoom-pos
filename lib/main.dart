import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/api/api_client.dart';
import 'core/config/theme.dart';
import 'core/storage/app_preferences.dart';
import 'core/storage/secure_storage_service.dart';
import 'features/auth/auth_provider.dart';
import 'features/auth/auth_repository.dart';
import 'features/auth/screens/auth_gate.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  final secureStorage = SecureStorageService();
  final preferences = AppPreferences();
  final apiClient = ApiClient(secureStorage: secureStorage, preferences: preferences);
  final authRepository = AuthRepository(apiClient);

  final authProvider = AuthProvider(
    authRepository: authRepository,
    secureStorage: secureStorage,
    apiClient: apiClient,
  );

  // Restore session in background
  authProvider.restoreSession();

  runApp(ZoomPosApp(
    preferences: preferences,
    apiClient: apiClient,
    authProvider: authProvider,
  ));
}

class ZoomPosApp extends StatelessWidget {
  const ZoomPosApp({
    super.key,
    required this.preferences,
    required this.apiClient,
    required this.authProvider,
  });

  final AppPreferences preferences;
  final ApiClient apiClient;
  final AuthProvider authProvider;

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider<AppPreferences>.value(value: preferences),
        Provider<ApiClient>.value(value: apiClient),
        ChangeNotifierProvider<AuthProvider>.value(value: authProvider),
      ],
      child: MaterialApp(
        title: 'Zoom POS',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        home: const AuthGate(),
      ),
    );
  }
}
