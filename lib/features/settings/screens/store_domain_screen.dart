import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/api/api_client.dart';

/// Screen for managing Storefront Subdomain and Custom Domain configuration,
/// including live store preview and DNS CNAME setup guidance.
class StoreDomainScreen extends StatefulWidget {
  const StoreDomainScreen({super.key});

  @override
  State<StoreDomainScreen> createState() => _StoreDomainScreenState();
}

class _StoreDomainScreenState extends State<StoreDomainScreen> {
  bool _isLoading = true;
  bool _isSavingSubdomain = false;
  bool _isSavingCustomDomain = false;
  String? _errorMessage;

  String _subdomain = '';
  String _subdomainUrl = '';
  String _customDomain = '';
  String _liveStoreUrl = '';
  String _cnameTarget = 'cname.saas.zoomnearby.com';
  String _sslStatus = 'Auto-provisioned via SSL certificate provider';
  String _propagationNote =
      'DNS changes can take anywhere from 15 minutes up to 24-48 hours to propagate worldwide.';
  List<Map<String, dynamic>> _dnsRecords = [];

  final _subdomainController = TextEditingController();
  final _customDomainController = TextEditingController();
  final _subdomainFormKey = GlobalKey<FormState>();
  final _customDomainFormKey = GlobalKey<FormState>();

  @override
  void initState() {
    super.initState();
    _fetchDomainConfig();
  }

  @override
  void dispose() {
    _subdomainController.dispose();
    _customDomainController.dispose();
    super.dispose();
  }

  Future<void> _fetchDomainConfig() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final client = context.read<ApiClient>();
      Map<String, dynamic>? res;

      try {
        res = await client.get('/tenant/storefront/domain-config');
      } catch (_) {
        res = await client.getAbsolute('/api/v1/tenant/storefront/domain-config');
      }

      if (!mounted) return;

      if (res['success'] == true && res['data'] is Map) {
        final data = res['data'] as Map<String, dynamic>;
        setState(() {
          _subdomain = data['subdomain']?.toString() ?? '';
          _subdomainUrl = data['subdomain_url']?.toString() ?? '';
          _customDomain = data['custom_domain']?.toString() ?? '';
          _liveStoreUrl = data['live_store_url']?.toString() ??
              (_customDomain.isNotEmpty
                  ? 'https://$_customDomain'
                  : _subdomainUrl);
          _cnameTarget =
              data['cname_target']?.toString() ?? 'cname.saas.zoomnearby.com';
          _sslStatus = data['ssl_status']?.toString() ??
              'Auto-provisioned via SSL certificate provider';
          _propagationNote = data['propagation_note']?.toString() ??
              'DNS changes can take anywhere from 15 minutes up to 24-48 hours to propagate worldwide.';

          if (data['dns_records'] is List) {
            _dnsRecords = (data['dns_records'] as List)
                .whereType<Map>()
                .map((e) => Map<String, dynamic>.from(e))
                .toList();
          }

          _subdomainController.text = _subdomain;
          _customDomainController.text = _customDomain;
          _isLoading = false;
        });
      } else {
        setState(() {
          _isLoading = false;
          _errorMessage = res?['error']?.toString() ?? 'Failed to load domain configuration.';
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = e.toString();
      });
    }
  }

  Future<void> _saveSubdomain() async {
    if (!_subdomainFormKey.currentState!.validate()) return;

    final newSub = _subdomainController.text.trim().toLowerCase();
    setState(() => _isSavingSubdomain = true);

    try {
      final client = context.read<ApiClient>();
      Map<String, dynamic>? res;
      final payload = {'subdomain': newSub};

      try {
        res = await client.put('/tenant/storefront/domain-config', data: payload);
      } catch (_) {
        res = await client.putAbsolute('/api/v1/tenant/storefront/domain-config', data: payload);
      }

      if (!mounted) return;

      if (res['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Store subdomain updated successfully!'),
            backgroundColor: Colors.green,
          ),
        );
        _fetchDomainConfig();
      } else {
        final msg = res['message'] ?? res['error'] ?? 'Could not update subdomain.';
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(msg.toString()),
            backgroundColor: Colors.red,
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error updating subdomain: $e'),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) setState(() => _isSavingSubdomain = false);
    }
  }

  Future<void> _saveCustomDomain({bool clear = false}) async {
    if (!clear && !_customDomainFormKey.currentState!.validate()) return;

    final newDomain = clear ? '' : _customDomainController.text.trim().toLowerCase();
    setState(() => _isSavingCustomDomain = true);

    try {
      final client = context.read<ApiClient>();
      Map<String, dynamic>? res;
      final payload = {'custom_domain': newDomain};

      try {
        res = await client.put('/tenant/storefront/domain-config', data: payload);
      } catch (_) {
        res = await client.putAbsolute('/api/v1/tenant/storefront/domain-config', data: payload);
      }

      if (!mounted) return;

      if (res['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(clear
                ? 'Custom domain removed.'
                : 'Custom domain updated! DNS changes may take some time to propagate.'),
            backgroundColor: Colors.green,
          ),
        );
        if (clear) _customDomainController.clear();
        _fetchDomainConfig();
      } else {
        final msg = res['message'] ?? res['error'] ?? 'Could not update custom domain.';
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(msg.toString()),
            backgroundColor: Colors.red,
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error saving custom domain: $e'),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) setState(() => _isSavingCustomDomain = false);
    }
  }

  void _copyToClipboard(String text, String label) {
    Clipboard.setData(ClipboardData(text: text));
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('$label copied to clipboard!'),
        duration: const Duration(seconds: 2),
      ),
    );
  }

  Future<void> _launchLiveStore() async {
    if (_liveStoreUrl.isEmpty) return;
    final uri = Uri.parse(_liveStoreUrl);
    try {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not launch store: $e')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Storefront Domain & URLs'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
            onPressed: _fetchDomainConfig,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _errorMessage != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24.0),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.error_outline, size: 48, color: colorScheme.error),
                        const SizedBox(height: 16),
                        Text(
                          _errorMessage!,
                          textAlign: TextAlign.center,
                          style: TextStyle(color: colorScheme.error),
                        ),
                        const SizedBox(height: 16),
                        FilledButton.icon(
                          onPressed: _fetchDomainConfig,
                          icon: const Icon(Icons.refresh),
                          label: const Text('Retry'),
                        ),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _fetchDomainConfig,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      _buildLiveStoreCard(theme, colorScheme),
                      const SizedBox(height: 16),
                      _buildSubdomainCard(theme, colorScheme),
                      const SizedBox(height: 16),
                      _buildCustomDomainCard(theme, colorScheme),
                      const SizedBox(height: 16),
                      _buildDnsGuidanceCard(theme, colorScheme),
                      const SizedBox(height: 32),
                    ],
                  ),
                ),
    );
  }

  Widget _buildLiveStoreCard(ThemeData theme, ColorScheme colorScheme) {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: colorScheme.primary.withValues(alpha: 0.2)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: colorScheme.primaryContainer,
                    shape: BoxShape.circle,
                  ),
                  child: Icon(Icons.public, color: colorScheme.primary, size: 24),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Live Storefront Address',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Row(
                        children: [
                          Container(
                            width: 8,
                            height: 8,
                            decoration: const BoxDecoration(
                              color: Colors.green,
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: 6),
                          Text(
                            'Active & Online',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: Colors.green,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: colorScheme.outlineVariant.withValues(alpha: 0.5)),
              ),
              child: SelectableText(
                _liveStoreUrl.isNotEmpty ? _liveStoreUrl : 'https://...',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  fontFamily: 'monospace',
                  color: colorScheme.primary,
                ),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _liveStoreUrl.isNotEmpty
                        ? () => _copyToClipboard(_liveStoreUrl, 'Store link')
                        : null,
                    icon: const Icon(Icons.copy, size: 18),
                    label: const Text('Copy Link'),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: _liveStoreUrl.isNotEmpty ? _launchLiveStore : null,
                    icon: const Icon(Icons.open_in_new, size: 18),
                    label: const Text('Visit Store'),
                    style: FilledButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSubdomainCard(ThemeData theme, ColorScheme colorScheme) {
    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _subdomainFormKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(Icons.link, color: colorScheme.primary),
                  const SizedBox(width: 8),
                  Text(
                    'Default Cloud Subdomain',
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                'Your permanent cloud subdomain is always available and requires zero DNS configuration.',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.7),
                ),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _subdomainController,
                decoration: InputDecoration(
                  labelText: 'Subdomain Prefix',
                  hintText: 'my-store',
                  prefixIcon: const Icon(Icons.storefront_outlined),
                  suffixText: '.saas.zoomnearby.com',
                  suffixStyle: TextStyle(
                    fontWeight: FontWeight.w600,
                    color: colorScheme.onSurface.withValues(alpha: 0.6),
                  ),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                ),
                validator: (value) {
                  if (value == null || value.trim().isEmpty) {
                    return 'Subdomain cannot be empty';
                  }
                  final clean = value.trim();
                  if (clean.length < 3) return 'Must be at least 3 characters';
                  if (!RegExp(r'^[a-z0-9-]+$').hasMatch(clean)) {
                    return 'Only lowercase letters, numbers, and hyphens allowed';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 14),
              Align(
                alignment: Alignment.centerRight,
                child: FilledButton.icon(
                  onPressed: _isSavingSubdomain ? null : _saveSubdomain,
                  icon: _isSavingSubdomain
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.save_outlined),
                  label: const Text('Update Subdomain'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCustomDomainCard(ThemeData theme, ColorScheme colorScheme) {
    final hasCustomDomain = _customDomain.isNotEmpty;

    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _customDomainFormKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(Icons.domain, color: colorScheme.primary),
                  const SizedBox(width: 8),
                  Text(
                    'Custom Domain (Your Brand)',
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                'Connect your own custom domain (e.g. store.yourbrand.com or yourbrand.com) to provide a white-labeled shopping experience.',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.7),
                ),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _customDomainController,
                decoration: InputDecoration(
                  labelText: 'Custom Domain',
                  hintText: 'e.g. store.mybrand.com',
                  prefixIcon: const Icon(Icons.language),
                  helperText: 'Enter without http:// or https://',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                ),
                validator: (value) {
                  if (value == null || value.trim().isEmpty) {
                    return 'Domain cannot be empty';
                  }
                  final clean = value.trim().replaceAll(RegExp(r'^https?://'), '');
                  if (!clean.contains('.')) return 'Enter a valid domain name';
                  return null;
                },
              ),
              const SizedBox(height: 14),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  if (hasCustomDomain) ...[
                    TextButton.icon(
                      onPressed: _isSavingCustomDomain
                          ? null
                          : () => _saveCustomDomain(clear: true),
                      icon: const Icon(Icons.delete_outline, color: Colors.red),
                      label: const Text(
                        'Remove Domain',
                        style: TextStyle(color: Colors.red),
                      ),
                    ),
                    const SizedBox(width: 8),
                  ],
                  FilledButton.icon(
                    onPressed: _isSavingCustomDomain ? null : () => _saveCustomDomain(),
                    icon: _isSavingCustomDomain
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.check),
                    label: Text(hasCustomDomain ? 'Update Domain' : 'Connect Domain'),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDnsGuidanceCard(ThemeData theme, ColorScheme colorScheme) {
    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.dns_outlined, color: colorScheme.primary),
                const SizedBox(width: 8),
                Text(
                  'DNS Setup Instructions',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'To point your custom domain here, log in to your DNS registrar (Cloudflare, GoDaddy, Namecheap, Route53, etc.) and create this CNAME record:',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.8),
              ),
            ),
            const SizedBox(height: 16),
            Container(
              decoration: BoxDecoration(
                border: Border.all(color: colorScheme.outlineVariant),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Column(
                children: [
                  _buildDnsRow('Record Type', 'CNAME', theme, colorScheme, isFirst: true),
                  const Divider(height: 1),
                  _buildDnsRow('Host / Name', '@ or www or store', theme, colorScheme),
                  const Divider(height: 1),
                  _buildDnsRowWithCopy(
                    'Target / Points To',
                    _cnameTarget,
                    theme,
                    colorScheme,
                  ),
                  const Divider(height: 1),
                  _buildDnsRow('TTL', '3600 (Automatic)', theme, colorScheme),
                  const Divider(height: 1),
                  _buildDnsRow(
                    'SSL / TLS',
                    _sslStatus,
                    theme,
                    colorScheme,
                    isLast: true,
                    statusColor: Colors.green,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: colorScheme.primaryContainer.withValues(alpha: 0.3),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.info_outline, size: 20, color: colorScheme.primary),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      _propagationNote,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDnsRow(
    String label,
    String value,
    ThemeData theme,
    ColorScheme colorScheme, {
    bool isFirst = false,
    bool isLast = false,
    Color? statusColor,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: colorScheme.onSurfaceVariant,
              fontWeight: FontWeight.w500,
            ),
          ),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: TextStyle(
                fontWeight: FontWeight.bold,
                fontFamily: 'monospace',
                color: statusColor ?? colorScheme.onSurface,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDnsRowWithCopy(
    String label,
    String value,
    ThemeData theme,
    ColorScheme colorScheme,
  ) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: colorScheme.onSurfaceVariant,
              fontWeight: FontWeight.w500,
            ),
          ),
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              SelectableText(
                value,
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  fontFamily: 'monospace',
                  color: colorScheme.primary,
                ),
              ),
              const SizedBox(width: 8),
              IconButton(
                icon: const Icon(Icons.copy, size: 16),
                tooltip: 'Copy target',
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(),
                onPressed: () => _copyToClipboard(value, 'CNAME target'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
