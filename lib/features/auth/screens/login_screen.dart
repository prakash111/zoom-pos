import 'package:cached_network_image/cached_network_image.dart';
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
import 'forgot_password_screen.dart';
import 'register_screen.dart';

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
  String? _brandLogoUrl;
  bool _googleEnabled = true;
  bool _facebookEnabled = true;
  bool _socialLoading = false;

  @override
  void initState() {
    super.initState();
    context.read<ApiClient>().get(ApiEndpoints.authBranding).then((response) {
      final url = response['brand_logo_url']?.toString();
      if (!mounted || url == null || url.isEmpty) return;
      setState(() => _brandLogoUrl = url);
    }).catchError((_) {
      // Pre-auth branding fetch is best-effort — fall back to the store icon.
    });

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

    if (!success && mounted && auth.errorMessage != null) {
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
      final redirectRes = await client.get('/auth/$provider/redirect', query: {'mobile': 1});
      final authUrl = redirectRes['url']?.toString();

      String targetUrl = authUrl ?? '';
      if (targetUrl.isEmpty) {
        final host = await client.currentBaseUrl();
        targetUrl = '$host/auth/$provider/redirect?mobile=1';
      }

      final uri = Uri.parse(targetUrl);
      final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
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

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.signIn),
        actions: [
          IconButton(
            tooltip: l10n.serverAddress,
            icon: const Icon(Icons.dns_outlined),
            onPressed: _openServerSettings,
          ),
        ],
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    (_brandLogoUrl?.isNotEmpty ?? false)
                        ? CachedNetworkImage(
                            imageUrl: _brandLogoUrl!,
                            height: 64,
                            placeholder: (context, url) => const Icon(
                                Icons.store,
                                size: 64,
                                color: Color(0xFF4A3B32)),
                            errorWidget: (context, url, error) => const Icon(
                                Icons.store,
                                size: 64,
                                color: Color(0xFF4A3B32)),
                          )
                        : const Icon(Icons.store,
                            size: 64, color: Color(0xFF4A3B32)),
                    const SizedBox(height: 12),
                    Text(
                      l10n.text('Sales & Inventory'),
                      textAlign: TextAlign.center,
                      style: Theme.of(context)
                          .textTheme
                          .headlineSmall
                          ?.copyWith(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 32),
                    TextFormField(
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      autocorrect: false,
                      decoration: InputDecoration(
                        labelText: l10n.emailOrLogin,
                        prefixIcon: const Icon(Icons.person_outline),
                      ),
                      validator: (value) =>
                          (value == null || value.trim().isEmpty)
                              ? l10n.required
                              : null,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _passwordController,
                      obscureText: _obscurePassword,
                      decoration: InputDecoration(
                        labelText: l10n.password,
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          icon: Icon(_obscurePassword
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined),
                          onPressed: () => setState(
                              () => _obscurePassword = !_obscurePassword),
                        ),
                      ),
                      validator: (value) => (value == null || value.isEmpty)
                          ? l10n.required
                          : null,
                      onFieldSubmitted: (_) => _submit(),
                    ),
                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton(
                        onPressed: () => Navigator.of(context).push(
                          MaterialPageRoute(
                              builder: (_) => const ForgotPasswordScreen()),
                        ),
                        child: Text(l10n.text('Forgot password?')),
                      ),
                    ),
                    if (_showAccountId) ...[
                      const SizedBox(height: 14),
                      TextFormField(
                        controller: _accountIdController,
                        autocorrect: false,
                        decoration: InputDecoration(
                          labelText: l10n.storeAccountIdOptional,
                          prefixIcon: const Icon(Icons.badge_outlined),
                        ),
                      ),
                    ] else
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton(
                          onPressed: () =>
                              setState(() => _showAccountId = true),
                          child: Text(l10n.iHaveAccountId),
                        ),
                      ),
                    const SizedBox(height: 24),
                    ElevatedButton(
                      onPressed: auth.isBusy ? null : _submit,
                      child: auth.isBusy
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(
                                  strokeWidth: 2, color: Colors.white),
                            )
                          : Text(l10n.signIn),
                    ),
                    if (_googleEnabled || _facebookEnabled) ...[
                      const SizedBox(height: 20),
                      Row(
                        children: [
                          Expanded(child: Divider(color: Colors.grey.shade300)),
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 12),
                            child: Text(
                              'Or continue with',
                              style: TextStyle(
                                fontSize: 13,
                                color: Colors.grey.shade600,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ),
                          Expanded(child: Divider(color: Colors.grey.shade300)),
                        ],
                      ),
                      const SizedBox(height: 16),
                      if (_googleEnabled)
                        OutlinedButton(
                          style: OutlinedButton.styleFrom(
                            backgroundColor: Colors.white,
                            foregroundColor: const Color(0xFF1F2937),
                            side: BorderSide(color: Colors.grey.shade300, width: 1.2),
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                          onPressed: (_socialLoading || auth.isBusy)
                              ? null
                              : () => _handleSocialLogin('google'),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Container(
                                width: 22,
                                height: 22,
                                alignment: Alignment.center,
                                child: const Text(
                                  'G',
                                  style: TextStyle(
                                    color: Color(0xFF4285F4),
                                    fontSize: 20,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 10),
                              const Text(
                                'Continue with Google',
                                style: TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w600,
                                  color: Color(0xFF1F2937),
                                ),
                              ),
                            ],
                          ),
                        ),
                      if (_googleEnabled && _facebookEnabled)
                        const SizedBox(height: 12),
                      if (_facebookEnabled)
                        ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF1877F2),
                            foregroundColor: Colors.white,
                            elevation: 0,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                          onPressed: (_socialLoading || auth.isBusy)
                              ? null
                              : () => _handleSocialLogin('facebook'),
                          child: const Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.facebook, size: 22, color: Colors.white),
                              SizedBox(width: 10),
                              Text(
                                'Continue with Facebook',
                                style: TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w600,
                                  color: Colors.white,
                                ),
                              ),
                            ],
                          ),
                        ),
                    ],
                    const SizedBox(height: 12),
                    Wrap(
                      alignment: WrapAlignment.center,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text(l10n.noStoreYetPrompt),
                        TextButton(
                          onPressed: auth.isBusy
                              ? null
                              : () => Navigator.of(context).push(
                                    MaterialPageRoute(
                                        builder: (_) => const RegisterScreen()),
                                  ),
                          child: Text(l10n.createOne),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
