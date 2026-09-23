import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/config/app_config.dart';
import '../../core/security/license_security_engine.dart';
import '../../core/storage/app_preferences.dart';

/// Lets the store owner point this terminal at wherever they self-hosted the
/// Zoom Sales CRM & Inventory platform, subject to license authority verification.
class ServerSettingsScreen extends StatefulWidget {
  const ServerSettingsScreen({super.key, required this.preferences});

  final AppPreferences preferences;

  @override
  State<ServerSettingsScreen> createState() => _ServerSettingsScreenState();
}

class _ServerSettingsScreenState extends State<ServerSettingsScreen> {
  final _formKey = GlobalKey<FormState>();
  final _urlController = TextEditingController();
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final url = await widget.preferences.readBaseUrl();
    if (!mounted) return;
    setState(() {
      _urlController.text = url;
      _loading = false;
    });
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;

    final inputUrl = _urlController.text.trim();
    final cleanUrl = inputUrl.replaceAll(RegExp(r'/+$'), '');
    if (cleanUrl.isEmpty || !Uri.parse(cleanUrl).isAbsolute) {
      _showErrorSnackBar(context, 'Please enter a valid HTTP/HTTPS URL');
      return;
    }

    setState(() => _saving = true);

    try {
      final result = await LicenseSecurityEngine.verifyServerDomain(cleanUrl);

      // 1. Success
      if (result['code'] == 200 && result['status'] == 'authorized') {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('custom_server_url', cleanUrl);
        await prefs.setString('active_license_token', result['license_token'] ?? '');
        await prefs.setString('license_tier', result['license_tier'] ?? 'regular');

        if (result['entitlements']?['white_label_branding'] == true && result['branding'] != null) {
          await prefs.setString('whitelabel_config', jsonEncode(result['branding']));
        } else {
          await prefs.remove('whitelabel_config');
        }

        await widget.preferences.saveBaseUrl(cleanUrl);
        if (!mounted) return;

        Navigator.of(context).pop(true);
        return;
      }

      // 2. Unregistered or Blocked Domain
      if (result['code'] == 403 || result['status'] == 'unregistered') {
        if (!mounted) return;
        _showLicenseAlert(
          context,
          title: 'Unauthorized Server',
          message: result['message'] ?? 'Your domain is not registered. You are not authorized to access. Buy a valid core script license to continue.',
          actionLabel: 'Buy License',
          actionUrl: result['buy_url'] ?? 'https://zoomnearby.com/pricing',
        );
        return;
      }

      // 3. Concrete Diagnostic Error (DNS, SSL, 404, or 500)
      if (!mounted) return;
      _showLicenseAlert(
        context,
        title: 'Verification Failed',
        message: result['message'] ?? 'Could not connect to the license verification server. Check your connection and try again.',
        actionLabel: 'Retry',
        actionUrl: null,
        onRetry: _save,
      );
    } catch (e) {
      if (!mounted) return;
      _showLicenseAlert(
        context,
        title: 'Verification Failed',
        message: 'Could not connect to the license verification server. Check your connection and try again.',
        actionLabel: 'Retry',
        actionUrl: null,
        onRetry: _save,
      );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _showErrorSnackBar(BuildContext context, String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: const Color(0xFFEF4444),
      ),
    );
  }

  void _showLicenseAlert(
    BuildContext context, {
    required String title,
    required String message,
    required String actionLabel,
    String? actionUrl,
    VoidCallback? onRetry,
  }) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF131D2D),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            const Icon(Icons.gpp_bad_outlined, color: Color(0xFFEF4444), size: 24),
            const SizedBox(width: 8),
            Expanded(child: Text(title, style: const TextStyle(color: Colors.white, fontSize: 16))),
          ],
        ),
        content: Text(message, style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 14, height: 1.4)),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel', style: TextStyle(color: Color(0xFF64748B))),
          ),
          if (actionUrl != null)
            ElevatedButton(
              onPressed: () => launchUrl(Uri.parse(actionUrl), mode: LaunchMode.externalApplication),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF10B981),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: Text(actionLabel, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            )
          else
            ElevatedButton(
              onPressed: () {
                Navigator.pop(ctx);
                if (onRetry != null) {
                  onRetry();
                }
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF10B981),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: Text(actionLabel, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _urlController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Server address')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : Padding(
              padding: const EdgeInsets.all(20),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Enter the web address of your Zoom Sales CRM & Inventory platform. '
                      'The server must be verified by the central license authority.',
                      style: TextStyle(color: Colors.grey.shade600),
                    ),
                    const SizedBox(height: 20),
                    TextFormField(
                      controller: _urlController,
                      keyboardType: TextInputType.url,
                      autocorrect: false,
                      decoration: const InputDecoration(
                        labelText: 'Server URL',
                        hintText: AppConfig.defaultBaseUrl,
                        prefixIcon: Icon(Icons.dns_outlined),
                      ),
                      validator: (value) {
                        final uri = Uri.tryParse((value ?? '').trim());
                        if (uri == null || !uri.hasScheme || !uri.hasAuthority) {
                          return 'Enter a full URL, e.g. https://your-store.com';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 24),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: _saving ? null : _save,
                        child: _saving
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                              )
                            : const Text('Save'),
                      ),
                    ),
                    TextButton(
                      onPressed: _saving
                          ? null
                          : () {
                              _urlController.text = AppConfig.defaultBaseUrl;
                            },
                      child: const Text('Reset to default'),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}
