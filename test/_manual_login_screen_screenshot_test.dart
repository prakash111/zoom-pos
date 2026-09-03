// Throwaway manual verification — not part of the suite. Renders the real
// LoginScreen against the live backend (https://saas.zoomnearby.com) and
// saves a PNG so the brand-logo fetch (GET /auth/branding) can be checked
// visually. Delete after use.
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/storage/app_preferences.dart';
import 'package:zoom_pos_mobile/core/storage/secure_storage_service.dart';
import 'package:zoom_pos_mobile/features/auth/auth_provider.dart';
import 'package:zoom_pos_mobile/features/auth/auth_repository.dart';
import 'package:zoom_pos_mobile/features/auth/screens/login_screen.dart';
import 'package:zoom_pos_mobile/l10n/app_localizations.dart';

// AutomatedTestWidgetsFlutterBinding installs a fake HttpOverrides that
// short-circuits every HttpClient request to a 400 with no real socket —
// this test explicitly wants a REAL round trip to the live backend, so
// that override is cleared inside runAsync() below (the one place it can
// safely take effect) rather than working around the fake response.
class _RealHttpOverrides extends HttpOverrides {}

void main() {
  testWidgets('login screen renders brand logo from live backend', (tester) async {
    tester.view.physicalSize = const Size(430, 900);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    // flutter_cache_manager (behind CachedNetworkImage) reads disk-cache
    // directories via path_provider's platform channel, which has no
    // implementation in a plain VM test — stub it to a scratch temp dir.
    final cacheDir = Directory.systemTemp.createTempSync('flutter_cache_test');
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
      const MethodChannel('plugins.flutter.io/path_provider'),
      (call) async => cacheDir.path,
    );

    final preferences = AppPreferences();
    final secureStorage = SecureStorageService();
    final apiClient = ApiClient(secureStorage: secureStorage, preferences: preferences);
    final authProvider = AuthProvider(
      authRepository: AuthRepository(apiClient),
      secureStorage: secureStorage,
      apiClient: apiClient,
    );

    final boundaryKey = GlobalKey();

    await tester.runAsync(() async {
      HttpOverrides.global = _RealHttpOverrides();

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            Provider<AppPreferences>.value(value: preferences),
            Provider<ApiClient>.value(value: apiClient),
            ChangeNotifierProvider<AuthProvider>.value(value: authProvider),
          ],
          child: MaterialApp(
            localizationsDelegates: const [
              AppLocalizations.delegate,
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            supportedLocales: const [Locale('en')],
            home: RepaintBoundary(key: boundaryKey, child: const LoginScreen()),
          ),
        ),
      );

      // Real network round trip for GET /auth/branding + the subsequent
      // CachedNetworkImage fetch of brand_logo_url — give both a real chance
      // to complete against the live server.
      for (var i = 0; i < 20; i++) {
        await Future.delayed(const Duration(milliseconds: 500));
        await tester.pump();
      }
    });

    await tester.pump();

    final boundary = boundaryKey.currentContext!.findRenderObject() as RenderRepaintBoundary;
    final image = await boundary.toImage(pixelRatio: 2.0);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    File('/tmp/claude-1021/-home-zoomnearby-saas-htdocs-saas-zoomnearby-com/3f0deb63-ef62-4d0e-ba57-f5608ae80ba3/scratchpad/login_screen.png')
        .writeAsBytesSync(bytes!.buffer.asUint8List());
  });
}
