import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/config/app_config.dart';
import '../../../core/sdui/sdui_icon_registry.dart';
import '../auth_provider.dart';
import 'verify_otp_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _storeNameController = TextEditingController();
  final _ownerNameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _phoneController = TextEditingController();
  bool _obscurePassword = true;
  String _posMode = '';
  List<Map<String, dynamic>> _registrationModes = [];
  bool _loadingModules = true;
  String? _moduleLoadError;

  @override
  void initState() {
    super.initState();
    final client = context.read<ApiClient>();
    Future<Map<String, dynamic>> fetch() async {
      try {
        return await client.getAbsolute(ApiEndpoints.registrationMetaAbsolute);
      } catch (_) {
        return await client.get(ApiEndpoints.registrationConfig);
      }
    }

    fetch().then((response) {
      if (!mounted) return;
      final rawModes = response['registration_modes'] ?? response['active_modules'];
      if (rawModes is List && rawModes.isNotEmpty) {
        final parsed = rawModes
            .whereType<Map>()
            .map((m) => Map<String, dynamic>.from(m))
            .where((m) =>
                (m['key']?.toString() ?? m['id']?.toString() ?? '')
                    .isNotEmpty)
            .toList();
        if (parsed.isEmpty) {
          setState(() {
            _loadingModules = false;
            _moduleLoadError =
                'No valid registration modules were returned by the server.';
          });
          return;
        }
        setState(() {
          _registrationModes = parsed;
          final defaultMode = response['default_mode']?.toString() ??
              (parsed.first['key'] ?? parsed.first['id']).toString();
          _posMode = defaultMode;
          _loadingModules = false;
          _moduleLoadError = null;
        });
      } else {
        setState(() {
          _loadingModules = false;
          _moduleLoadError = 'No registration modules are currently available.';
        });
      }
    }).catchError((_) {
      if (!mounted) return;
      setState(() {
        _loadingModules = false;
        _moduleLoadError =
            'Unable to load store types. Check the server connection and retry.';
      });
    });
  }

  @override
  void dispose() {
    _storeNameController.dispose();
    _ownerNameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_posMode.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select a store type before continuing.')),
      );
      return;
    }

    final auth = context.read<AuthProvider>();
    final result = await auth.register(
      storeName: _storeNameController.text.trim(),
      ownerName: _ownerNameController.text.trim(),
      email: _emailController.text.trim(),
      password: _passwordController.text,
      phone: _phoneController.text.trim(),
      posMode: _posMode,
    );

    if (!mounted) return;

    if (result != null) {
      if (result.requiresOtp) {
        final email = result.email ?? _emailController.text.trim();
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (ctx) => VerifyOtpScreen(
              email: email,
              tokenExpiry: result.expiresIn,
            ),
          ),
        );
        return;
      }

      Navigator.of(context).pop();
      return;
    }

    if (auth.errorMessage != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(auth.errorMessage!)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      appBar: AppBar(title: const Text('Create your store')),
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
                    TextFormField(
                      controller: _storeNameController,
                      decoration: const InputDecoration(
                        labelText: 'Store name',
                        prefixIcon: Icon(Icons.storefront_outlined),
                      ),
                      validator: (value) =>
                          (value == null || value.trim().isEmpty)
                              ? 'Required'
                              : null,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _ownerNameController,
                      decoration: const InputDecoration(
                        labelText: 'Your name',
                        prefixIcon: Icon(Icons.person_outline),
                      ),
                      validator: (value) =>
                          (value == null || value.trim().isEmpty)
                              ? 'Required'
                              : null,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      autocorrect: false,
                      decoration: const InputDecoration(
                        labelText: 'Email',
                        prefixIcon: Icon(Icons.email_outlined),
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty)
                          return 'Required';
                        if (!value.contains('@')) return 'Enter a valid email';
                        return null;
                      },
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _passwordController,
                      obscureText: _obscurePassword,
                      decoration: InputDecoration(
                        labelText: 'Password',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          icon: Icon(_obscurePassword
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined),
                          onPressed: () => setState(
                              () => _obscurePassword = !_obscurePassword),
                        ),
                      ),
                      validator: (value) => (value == null || value.length < 6)
                          ? 'At least 6 characters'
                          : null,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _phoneController,
                      keyboardType: TextInputType.phone,
                      decoration: const InputDecoration(
                        labelText: 'Phone (optional)',
                        prefixIcon: Icon(Icons.phone_outlined),
                      ),
                    ),
                    const SizedBox(height: 20),
                    const Align(
                      alignment: Alignment.centerLeft,
                      child: Text('Store type',
                          style: TextStyle(
                              fontWeight: FontWeight.bold, fontSize: 13)),
                    ),
                    const SizedBox(height: 8),
                    if (_loadingModules)
                      const Center(child: CircularProgressIndicator())
                    else if (_moduleLoadError != null)
                      Text(
                        _moduleLoadError!,
                        style: TextStyle(
                            color: Theme.of(context).colorScheme.error),
                      )
                    else
                      LayoutBuilder(
                        builder: (context, constraints) {
                          final cardWidth = constraints.maxWidth > 320
                              ? (constraints.maxWidth - 10) / 2
                              : constraints.maxWidth;
                          return Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              for (final mod in _registrationModes)
                                SizedBox(
                                  width: _registrationModes.length == 1
                                      ? constraints.maxWidth
                                      : cardWidth,
                                  child: _StoreTypeCard(
                                    icon: SduiIconRegistry.resolve(
                                        mod['icon']?.toString()),
                                    label: mod['title']?.toString() ??
                                        mod['key']?.toString() ??
                                        mod['id']?.toString() ??
                                        '',
                                    description:
                                        mod['subtitle']?.toString() ??
                                        mod['description']?.toString() ??
                                        '',
                                    selected: _posMode ==
                                        (mod['key']?.toString() ??
                                            mod['id']?.toString() ??
                                            ''),
                                    onTap: () => setState(() => _posMode =
                                        (mod['key'] ?? mod['id']).toString()),
                                  ),
                                ),
                            ],
                          );
                        },
                      ),
                    const SizedBox(height: 24),
                    ElevatedButton(
                      onPressed: auth.isBusy ||
                              _loadingModules ||
                              _moduleLoadError != null
                          ? null
                          : _submit,
                      child: auth.isBusy
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(
                                  strokeWidth: 2, color: Colors.white),
                            )
                          : const Text('Create store'),
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

class _StoreTypeCard extends StatelessWidget {
  const _StoreTypeCard({
    required this.icon,
    required this.label,
    required this.description,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final String description;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final primaryColor = Theme.of(context).colorScheme.primary;

    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: selected
              ? primaryColor.withValues(alpha: 0.08)
              : Colors.grey.shade50,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
              color: selected ? primaryColor : Colors.grey.shade300,
              width: selected ? 2 : 1),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: selected ? primaryColor : Colors.grey.shade700),
            const SizedBox(height: 6),
            Text(label,
                style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: selected ? primaryColor : Colors.grey.shade900)),
            const SizedBox(height: 2),
            Text(description,
                style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
          ],
        ),
      ),
    );
  }
}
