import 'package:flutter/material.dart';

import '../../core/config/app_config.dart';
import '../../core/storage/app_preferences.dart';

/// Lets the store owner point this terminal at wherever they self-hosted the
/// Zoom POS platform, since [AppConfig.defaultBaseUrl] is only a fallback.
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

    setState(() => _saving = true);
    await widget.preferences.saveBaseUrl(_urlController.text);
    if (!mounted) return;

    setState(() => _saving = false);
    Navigator.of(context).pop(true);
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
                      'Enter the web address of your Zoom POS platform. Leave the '
                      'default if you were not given a custom one.',
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
