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
      heading: 'Login',
      onServerSettings: _openServerSettings,
      form: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            TextFormField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              autocorrect: false,
              decoration: authInputDecoration(
                hint: l10n.emailOrLogin,
                icon: Icons.person_outline,
              ),
              validator: (value) => (value == null || value.trim().isEmpty)
                  ? l10n.required
                  : null,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _passwordController,
              obscureText: _obscurePassword,
              decoration: authInputDecoration(
                hint: l10n.password,
                icon: Icons.lock_outline,
                suffixIcon: IconButton(
                  icon: Icon(
                    _obscurePassword
                        ? Icons.visibility_outlined
                        : Icons.visibility_off_outlined,
                    size: 20,
                    color: const Color(0xFF94A3B8),
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
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(
                      builder: (_) => const ForgotPasswordScreen()),
                ),
                child: Text(l10n.text('Forgot password?')),
              ),
            ),
            if (_showAccountId) ...[
              const SizedBox(height: 6),
              TextFormField(
                controller: _accountIdController,
                autocorrect: false,
                decoration: authInputDecoration(
                  hint: l10n.storeAccountIdOptional,
                  icon: Icons.badge_outlined,
                ),
              ),
            ] else
              Align(
                alignment: Alignment.centerRight,
                child: TextButton(
                  onPressed: () => setState(() => _showAccountId = true),
                  child: Text(l10n.iHaveAccountId),
                ),
              ),
            const SizedBox(height: 14),
            AuthPrimaryButton(
              label: l10n.signIn,
              busy: auth.isBusy,
              onPressed: auth.isBusy ? null : _submit,
            ),
            if (hasSocial) ...[
              const SizedBox(height: 22),
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
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  onPressed: (_socialLoading || auth.isBusy)
                      ? null
                      : () => _handleSocialLogin('google'),
                  child: const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      SizedBox(
                        width: 20,
                        height: 20,
                        child: CustomPaint(painter: _GoogleGPainter()),
                      ),
                      SizedBox(width: 10),
                      Text(
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
                      borderRadius: BorderRadius.circular(12),
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
          ],
        ),
      ),
      belowCard: Wrap(
        alignment: WrapAlignment.center,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          Text(l10n.noStoreYetPrompt),
          TextButton(
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

/// The multi-colour Google "G", drawn small so it never clips inside the
/// social-sign-in button the way a scaled text glyph did.
class _GoogleGPainter extends CustomPainter {
  const _GoogleGPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final r = size.width / 2;
    final c = Offset(r, r);
    final stroke = size.width * 0.28;
    final rect = Rect.fromCircle(center: c, radius: r - stroke / 2);
    final p = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.butt;

    // Four coloured arcs around the ring.
    p.color = const Color(0xFF4285F4); // blue  (right)
    canvas.drawArc(rect, -0.55, 1.6, false, p);
    p.color = const Color(0xFF34A853); // green (bottom)
    canvas.drawArc(rect, 1.15, 1.5, false, p);
    p.color = const Color(0xFFFBBC05); // yellow (bottom-left)
    canvas.drawArc(rect, 2.55, 1.1, false, p);
    p.color = const Color(0xFFEA4335); // red   (top-left)
    canvas.drawArc(rect, 3.6, 1.6, false, p);

    // The horizontal bar of the G.
    p
      ..style = PaintingStyle.fill
      ..color = const Color(0xFF4285F4);
    canvas.drawRect(
      Rect.fromLTWH(c.dx, c.dy - stroke / 2, r - stroke / 2, stroke),
      p,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
