import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/config/platform_branding_provider.dart';
import '../../../core/widgets/adaptive_sheet.dart';
import '../models/app_page_model.dart';
import '../services/app_pages_service.dart';
import 'dynamic_page_viewer_screen.dart';

/// Unified bottom popup modal displaying Legal & App Information pages
/// dynamically managed from the Super Admin panel.
class LegalInfoModal extends StatefulWidget {
  const LegalInfoModal({super.key});

  /// Opens the Legal & App Information modal.
  static Future<void> show(BuildContext context) {
    return showAdaptiveSheet<void>(
      context,
      isScrollControlled: true,
      maxWidth: 540,
      maxHeight: 650,
      builder: (_) => const LegalInfoModal(),
    );
  }

  @override
  State<LegalInfoModal> createState() => _LegalInfoModalState();
}

class _LegalInfoModalState extends State<LegalInfoModal> {
  late final AppPagesService _service;
  bool _isLoading = true;
  String? _errorMessage;
  List<AppPageModel> _pages = const [];

  @override
  void initState() {
    super.initState();
    ApiClient? client;
    try {
      client = context.read<ApiClient>();
    } catch (_) {}
    _service = AppPagesService(apiClient: client);
    _loadPages();
  }

  Future<void> _loadPages({bool forceRefresh = false}) async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final items = await _service.getAppPages(forceRefresh: forceRefresh);
      if (!mounted) return;
      setState(() {
        _pages = items;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = 'Failed to load legal pages. Please check your connection.';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final primaryColor = theme.colorScheme.primary;

    String appVersion = 'ZooM-POS v1.0.6';
    try {
      final branding = context.watch<PlatformBrandingProvider>();
      if (branding.appVersion.isNotEmpty) {
        appVersion = branding.appVersion;
      }
    } catch (_) {}

    return Container(
      decoration: BoxDecoration(
        color: theme.scaffoldBackgroundColor,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Drag handle
          const SizedBox(height: 12),
          Center(
            child: Container(
              width: 38,
              height: 4,
              decoration: BoxDecoration(
                color: theme.dividerColor.withValues(alpha: 0.35),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          const SizedBox(height: 12),

          // Header
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: primaryColor.withValues(alpha: 0.12),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    Icons.policy_outlined,
                    color: primaryColor,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Legal & App Information',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Terms, policies & regulatory disclosures',
                        style: TextStyle(
                          fontSize: 11,
                          color: theme.textTheme.bodySmall?.color
                              ?.withValues(alpha: 0.65),
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close_rounded, size: 20),
                  onPressed: () => Navigator.of(context).pop(),
                  tooltip: 'Close',
                ),
              ],
            ),
          ),
          const Divider(height: 20),

          // Content body
          Flexible(
            child: _buildBody(context, theme, isDark, primaryColor),
          ),

          // Footer
          const Divider(height: 1),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
            color: isDark
                ? theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.2)
                : Colors.grey.withValues(alpha: 0.04),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  Icons.shield_outlined,
                  size: 13,
                  color: theme.disabledColor,
                ),
                const SizedBox(width: 5),
                Text(
                  appVersion,
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    color: theme.disabledColor,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBody(
    BuildContext context,
    ThemeData theme,
    bool isDark,
    Color primaryColor,
  ) {
    if (_isLoading) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 48),
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                width: 28,
                height: 28,
                child: CircularProgressIndicator(strokeWidth: 2.5),
              ),
              SizedBox(height: 12),
              Text(
                'Loading policies...',
                style: TextStyle(fontSize: 12, color: Colors.grey),
              ),
            ],
          ),
        ),
      );
    }

    if (_errorMessage != null) {
      return Padding(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.cloud_off_rounded,
                size: 36,
                color: theme.colorScheme.error,
              ),
              const SizedBox(height: 10),
              Text(
                _errorMessage!,
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 12),
              ),
              const SizedBox(height: 14),
              OutlinedButton.icon(
                onPressed: () => _loadPages(forceRefresh: true),
                icon: const Icon(Icons.refresh_rounded, size: 16),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    if (_pages.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 36),
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.article_outlined,
                size: 36,
                color: theme.disabledColor,
              ),
              const SizedBox(height: 10),
              Text(
                'No information pages are currently published for the mobile app.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 12, color: theme.disabledColor),
              ),
            ],
          ),
        ),
      );
    }

    return ListView.separated(
      shrinkWrap: true,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      itemCount: _pages.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (ctx, index) {
        final page = _pages[index];
        final iconData = _iconForPage(page.slug);
        final iconColor = _colorForPage(page.slug, primaryColor);

        return Material(
          color: isDark
              ? theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.3)
              : Colors.grey.withValues(alpha: 0.05),
          borderRadius: BorderRadius.circular(14),
          child: InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: () {
              Navigator.of(context, rootNavigator: true).push(
                DynamicPageViewerScreen.route(page: page),
              );
            },
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              child: Row(
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: iconColor.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(iconData, size: 18, color: iconColor),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          page.title,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        if (page.metaDescription != null &&
                            page.metaDescription!.trim().isNotEmpty) ...[
                          const SizedBox(height: 2),
                          Text(
                            page.metaDescription!,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 11,
                              color: theme.textTheme.bodySmall?.color
                                  ?.withValues(alpha: 0.65),
                            ),
                          ),
                        ] else ...[
                          const SizedBox(height: 2),
                          Text(
                            'View full details and policy',
                            style: TextStyle(
                              fontSize: 10,
                              color: theme.disabledColor,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Icon(
                    Icons.chevron_right_rounded,
                    size: 18,
                    color: theme.disabledColor,
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  IconData _iconForPage(String slug) {
    final s = slug.toLowerCase();
    if (s.contains('privacy')) return Icons.privacy_tip_outlined;
    if (s.contains('term')) return Icons.gavel_rounded;
    if (s.contains('refund') || s.contains('cancellation')) {
      return Icons.currency_exchange_rounded;
    }
    if (s.contains('about')) return Icons.info_outline;
    if (s.contains('contact')) return Icons.contact_support_outlined;
    return Icons.article_outlined;
  }

  Color _colorForPage(String slug, Color primary) {
    final s = slug.toLowerCase();
    if (s.contains('privacy')) return const Color(0xFF3B82F6); // Blue
    if (s.contains('term')) return const Color(0xFF10B981); // Emerald
    if (s.contains('refund') || s.contains('cancellation')) {
      return const Color(0xFFF59E0B); // Amber
    }
    if (s.contains('about')) return const Color(0xFF8B5CF6); // Purple
    return primary;
  }
}
