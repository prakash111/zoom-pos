import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../dashboard/dashboard_screen.dart';
import '../auth_provider.dart';
import 'login_screen.dart';

/// Root switch between the splash state, the login/register flow, and the
/// signed-in app, driven entirely by [AuthProvider.status].
class AuthGate extends StatelessWidget {
  const AuthGate({super.key});

  @override
  Widget build(BuildContext context) {
    final status = context.watch<AuthProvider>().status;

    switch (status) {
      case AuthStatus.unknown:
        return const Scaffold(body: Center(child: CircularProgressIndicator()));
      case AuthStatus.authenticated:
        return const DashboardScreen();
      case AuthStatus.authenticating:
      case AuthStatus.unauthenticated:
        return const LoginScreen();
    }
  }
}
