import 'dart:async';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../dashboard/dashboard_screen.dart';
import '../../../core/config/platform_branding_provider.dart';
import '../../../core/services/dynamic_string_service.dart';
import '../../../core/utils/web_ready/web_ready.dart';
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
  bool _signaledReady = false;

  @override
  void initState() {
    super.initState();
    // Safety fallback: if status stays unknown for > 15s, force display login screen
    // Extended from 4s to 15s so network handshake/token restore does not prematurely abort
    _safetyTimer = Timer(const Duration(seconds: 15), () {
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

  void _checkAndSignalReady(AuthStatus status) {
    if ((status != AuthStatus.unknown || _timedOut) && !_signaledReady) {
      _signaledReady = true;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        signalFlutterAppReady();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final status = context.watch<AuthProvider>().status;
    final branding = context.watch<PlatformBrandingProvider>();
    final showLanding = kIsWeb && branding.landingPageEnabled;

    _checkAndSignalReady(status);

    if (_timedOut && status == AuthStatus.unknown) {
      return showLanding ? const LandingScreen() : const LoginScreen();
    }

    switch (status) {
      case AuthStatus.unknown:
        final bg = branding.splashBgColor;
        final onBg = bg.computeLuminance() < 0.5
            ? Colors.white
            : const Color(0xFF0F172A);
        final onBgMuted = onBg.withValues(alpha: 0.7);
        return Scaffold(
          backgroundColor: bg,
          body: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                if (branding.hasLogo && branding.brandLogoUrl != null)
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
                      : t('Zoom Sales CRM & Inventory'),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: onBg,
                      ),
                ),
                const SizedBox(height: 6),
                Text(
                  t('Point of Sale & Business Operations'),
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: onBgMuted,
                        fontSize: 13,
                      ),
                ),
                const SizedBox(height: 24),
                CircularProgressIndicator(color: onBg),
                const SizedBox(height: 16),
                Text(
                  t('Loading workspace & syncing database...'),
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: onBgMuted,
                        fontSize: 12,
                      ),
                ),
              ],
            ),
          ),
        );
      case AuthStatus.authenticated:
        return const DashboardScreen();
      case AuthStatus.authenticating:
      case AuthStatus.unauthenticated:
        return showLanding ? const LandingScreen() : const LoginScreen();
    }
  }
}


