import 'dart:async';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../dashboard/dashboard_screen.dart';
import '../../../core/config/platform_branding_provider.dart';
import '../../../core/services/dynamic_string_service.dart';
import '../../landing/screens/landing_screen.dart';
import '../auth_provider.dart';
import 'login_screen.dart';

/// Root switch between the splash state, the login/register flow, and the
/// signed-in app, driven entirely by [AuthProvider.status].
class AuthGate extends StatefulWidget {
  const AuthGate({super.key});

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  Timer? _safetyTimer;
  bool _timedOut = false;

  @override
  void initState() {
    super.initState();
    // Safety fallback: if status stays unknown for > 4s, force display login screen
    _safetyTimer = Timer(const Duration(seconds: 4), () {
      if (mounted &&
          context.read<AuthProvider>().status == AuthStatus.unknown) {
        setState(() {
          _timedOut = true;
        });
      }
    });
  }

  @override
  void dispose() {
    _safetyTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final status = context.watch<AuthProvider>().status;
    final branding = context.watch<PlatformBrandingProvider>();
    final showLanding = kIsWeb && branding.landingPageEnabled;

    if (_timedOut && status == AuthStatus.unknown) {
      return showLanding ? const LandingScreen() : const LoginScreen();
    }

    switch (status) {
      case AuthStatus.unknown:
        // Splash background + brand come from the Superadmin global settings
        // (GET /auth/branding). Tenant theme only applies once authenticated.
        final bg = branding.splashBgColor;
        final onBg = bg.computeLuminance() < 0.5
            ? Colors.white
            : const Color(0xFF0F172A);
        return Scaffold(
          backgroundColor: bg,
          body: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                if (branding.hasLogo)
                  Image.network(
                    branding.brandLogoUrl!,
                    height: 72,
                    errorBuilder: (_, __, ___) =>
                        Icon(Icons.storefront, size: 64, color: onBg),
                  )
                else
                  Icon(Icons.storefront, size: 64, color: onBg),
                const SizedBox(height: 16),
                Text(
                  branding.platformName.isNotEmpty
                      ? branding.platformName
                      : t('Sales & Inventory'),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: onBg,
                      ),
                ),
                const SizedBox(height: 24),
                CircularProgressIndicator(color: onBg),
              ],
            ),
          ),
        );
      case AuthStatus.authenticated:
        // The full-featured dashboard: analytics bound to the live API plus
        // the complete hierarchical navigation (drawer + expandable desktop
        // rail). Alternate visual layouts are offered as a Settings picker
        // rather than being forced on by platform.
        return const DashboardScreen();
      case AuthStatus.authenticating:
      case AuthStatus.unauthenticated:
        return showLanding ? const LandingScreen() : const LoginScreen();
    }
  }
}
