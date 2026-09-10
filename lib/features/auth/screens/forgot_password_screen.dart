import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/config/app_config.dart';
import '../widgets/auth_scaffold.dart';
import '../widgets/auth_widgets.dart';

/// Requests a password reset email via POST /api/tenant/password/email —
/// the same endpoint the web "Forgot password?" form posts to. The actual
/// password change happens by following the emailed link in a browser
/// (resources/views/auth/reset-password.blade.php), same as most apps.
class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  bool _isSending = false;
  String? _resultMessage;
  bool _resultIsError = false;

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _isSending = true;
      _resultMessage = null;
    });
    try {
      final response = await context.read<ApiClient>().postAbsolute(
        ApiEndpoints.passwordEmailAbsolute,
        data: {'email': _emailController.text.trim()},
      );
      setState(() {
        _resultIsError = false;
        _resultMessage = response['message']?.toString() ??
            'If an account exists for that email, a reset link has been sent.';
      });
    } on ApiException catch (e) {
      setState(() {
        _resultIsError = true;
        _resultMessage = e.message;
      });
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AuthScaffold(
      marketingHeader: true,
      brandHeadline: 'Reset your password',
      brandSubline: "We'll email you a secure link to set a new one.",
      headerTrailing: TextButton.icon(
        onPressed: () => Navigator.of(context).maybePop(),
        icon: const Icon(Icons.arrow_back, size: 16),
        label: const Text('Back to login'),
        style: TextButton.styleFrom(
          foregroundColor: AuthColors.link,
          textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
        ),
      ),
      heading: 'Forgot password?',
      subheading:
          "Enter your account email and we'll send you a link to reset your password.",
      form: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            const AuthFieldLabel('Email address'),
            TextFormField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              autocorrect: false,
              decoration: authInputDecoration(
                hint: 'Enter your email address',
                icon: Icons.mail_outline,
              ),
              validator: (value) =>
                  (value == null || value.trim().isEmpty) ? 'Required' : null,
              onFieldSubmitted: (_) => _submit(),
            ),
            if (_resultMessage != null) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: _resultIsError
                      ? Colors.red.shade50
                      : Colors.green.shade50,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  _resultMessage!,
                  style: TextStyle(
                      color: _resultIsError
                          ? Colors.red.shade800
                          : Colors.green.shade800),
                ),
              ),
            ],
            const SizedBox(height: 22),
            AuthPrimaryButton(
              label: 'Send reset link',
              busy: _isSending,
              onPressed: _isSending ? null : _submit,
            ),
          ],
        ),
      ),
      belowCard: const Text(
        'You can request a new link every few minutes.',
        textAlign: TextAlign.center,
        style: TextStyle(fontSize: 12.5, color: AuthColors.muted),
      ),
    );
  }
}
