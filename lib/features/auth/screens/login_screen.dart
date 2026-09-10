import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/config/app_config.dart';
import '../../../core/storage/app_preferences.dart';
import '../../../l10n/app_localizations.dart';
import '../../settings/server_settings_screen.dart';
import '../auth_provider.dart';
import '../widgets/auth_scaffold.dart';
import '../widgets/auth_widgets.dart';
import 'forgot_password_screen.dart';
import 'register_screen.dart';
import 'verify_otp_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _accountIdController = TextEditingController();
  bool _obscurePassword = true;
  bool _showAccountId = false;
  bool _googleEnabled = true;
  bool _facebookEnabled = true;
  bool _socialLoading = false;

  @override
  void initState() {
    super.initState();
    context.read<ApiClient>().get(ApiEndpoints.authConfig).then((response) {
      final social = response['social_login'];
      if (social is Map && mounted) {
        setState(() {
          _googleEnabled = social['google'] == true;
          _facebookEnabled = social['facebook'] == true;
        });
      }
    }).catchError((_) {
      // Best-effort pre-auth social config fetch — default to true.
    });
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _accountIdController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    final auth = context.read<AuthProvider>();
    final success = await auth.login(
      email: _emailController.text.trim(),
      password: _passwordController.text,
      accountId: _accountIdController.text.trim(),
    );

    if (success || !mounted) return;

    // Valid credentials, but the account never finished email verification —
    // route to the OTP screen instead of surfacing it as a sign-in error.
    final pending = auth.pendingEmailVerification;
    if (pending != null) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => VerifyOtpScreen(
            email: pending.email ?? _emailController.text.trim(),
            tokenExpiry: pending.expiresIn,
          ),
        ),
      );
      return;
    }

    if (auth.errorMessage != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(auth.errorMessage!)),
      );
    }
  }

  Future<void> _openServerSettings() async {
    final preferences = context.read<AppPreferences>();
    await Navigator.of(context).push(
      MaterialPageRoute(
          builder: (_) => ServerSettingsScreen(preferences: preferences)),
    );
  }

  Future<void> _handleSocialLogin(String provider) async {
    setState(() => _socialLoading = true);
    final client = context.read<ApiClient>();

    try {
      final redirectRes =
          await client.get('/auth/$provider/redirect', query: {'mobile': 1});
      final authUrl = redirectRes['url']?.toString();

      String targetUrl = authUrl ?? '';
      if (targetUrl.isEmpty) {
        final host = await client.currentBaseUrl();
        targetUrl = '$host/auth/$provider/redirect?mobile=1';
      }

      final uri = Uri.parse(targetUrl);
      final launched =
          await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!launched) {
        throw ApiException('Could not open browser for $provider sign-in.');
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message)),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to initiate $provider sign-in: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _socialLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final l10n = AppLocalizations.of(context);
    final hasSocial = _googleEnabled || _facebookEnabled;

    return AuthScaffold(
      // Logo + headline + description come from the Superadmin global
      // branding (GET /auth/branding -> platform.*); no hardcoded copy.
      marketingHeader: true,
      heading: 'Welcome back',
      subheading: 'Sign in to your account',
      onServerSettings: _openServerSettings,
      form: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            AuthFieldLabel(l10n.emailOrLogin),
            TextFormField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              autocorrect: false,
              decoration: authInputDecoration(
                hint: 'Enter your email or username',
                icon: Icons.person_outline,
              ),
              validator: (value) => (value == null || value.trim().isEmpty)
                  ? l10n.required
                  : null,
            ),
            const SizedBox(height: 16),
            AuthFieldLabel(l10n.password),
            TextFormField(
              controller: _passwordController,
              obscureText: _obscurePassword,
              decoration: authInputDecoration(
                hint: 'Enter your password',
                icon: Icons.lock_outline,
                suffixIcon: IconButton(
                  icon: Icon(
                    _obscurePassword
                        ? Icons.visibility_outlined
                        : Icons.visibility_off_outlined,
                    size: 20,
                    color: AuthColors.faint,
                  ),
                  onPressed: () =>
                      setState(() => _obscurePassword = !_obscurePassword),
                ),
              ),
              validator: (value) =>
                  (value == null || value.isEmpty) ? l10n.required : null,
              onFieldSubmitted: (_) => _submit(),
            ),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                style: TextButton.styleFrom(
                  foregroundColor: Theme.of(context).colorScheme.primary,
                  textStyle: const TextStyle(fontWeight: FontWeight.w700),
                ),
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(
                      builder: (_) => const ForgotPasswordScreen()),
                ),
                child: Text(l10n.text('Forgot password?')),
              ),
            ),
            if (_showAccountId) ...[
              const SizedBox(height: 6),
              AuthFieldLabel(l10n.storeAccountIdOptional),
              TextFormField(
                controller: _accountIdController,
                autocorrect: false,
                decoration: authInputDecoration(
                  hint: l10n.storeAccountIdOptional,
                  icon: Icons.storefront_outlined,
                ),
              ),
              const SizedBox(height: 16),
            ] else
              const SizedBox(height: 6),
            AuthPrimaryButton(
              label: l10n.signIn,
              busy: auth.isBusy,
              onPressed: auth.isBusy ? null : _submit,
            ),
            if (hasSocial) ...[
              const SizedBox(height: 22),
              const AuthOrDivider(),
              const SizedBox(height: 16),
              if (_googleEnabled)
                AuthSocialButton(
                  label: 'Continue with Google',
                  leading: const AuthGoogleLogo(),
                  onPressed: (_socialLoading || auth.isBusy)
                      ? null
                      : () => _handleSocialLogin('google'),
                ),
              if (_googleEnabled && _facebookEnabled)
                const SizedBox(height: 12),
              if (_facebookEnabled)
                AuthSocialButton(
                  label: 'Continue with Facebook',
                  foreground: AuthColors.facebook,
                  leading: const Icon(Icons.facebook,
                      size: 22, color: AuthColors.facebook),
                  onPressed: (_socialLoading || auth.isBusy)
                      ? null
                      : () => _handleSocialLogin('facebook'),
                ),
            ],
            if (!_showAccountId) ...[
              const SizedBox(height: 18),
              AuthInfoCard(
                icon: Icons.storefront_outlined,
                title: const Text('Have a store account ID?'),
                subtitle: const Text('Connect your store to get started'),
                onTap: () => setState(() => _showAccountId = true),
              ),
            ],
          ],
        ),
      ),
      belowCard: Wrap(
        alignment: WrapAlignment.center,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          Text(
            l10n.noStoreYetPrompt,
            style: const TextStyle(color: AuthColors.muted),
          ),
          TextButton(
            style: TextButton.styleFrom(
              foregroundColor: AuthColors.link,
              textStyle: const TextStyle(fontWeight: FontWeight.w700),
            ),
            onPressed: auth.isBusy
                ? null
                : () => Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const RegisterScreen()),
                    ),
            child: Text(l10n.createOne),
          ),
        ],
      ),
    );
  }
}
