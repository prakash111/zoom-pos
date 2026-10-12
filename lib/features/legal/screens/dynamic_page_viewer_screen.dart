import 'package:flutter/material.dart';
import 'package:flutter_widget_from_html/flutter_widget_from_html.dart';
import 'package:url_launcher/url_launcher.dart';

import '../models/app_page_model.dart';

/// Native in-app viewer for dynamic legal & custom pages managed in Super Admin.
class DynamicPageViewerScreen extends StatelessWidget {
  const DynamicPageViewerScreen({
    super.key,
    required this.page,
  });

  final AppPageModel page;

  static Route<void> route({required AppPageModel page}) {
    return MaterialPageRoute<void>(
      builder: (_) => DynamicPageViewerScreen(page: page),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final primaryColor = theme.colorScheme.primary;

    return Scaffold(
      appBar: AppBar(
        title: Text(
          page.title,
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
        ),
        centerTitle: false,
        actions: [
          if (page.url != null && page.url!.isNotEmpty)
            IconButton(
              icon: const Icon(Icons.open_in_new_rounded, size: 20),
              tooltip: 'Open in browser',
              onPressed: () async {
                final uri = Uri.tryParse(page.url!);
                if (uri != null) {
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                }
              },
            ),
          const SizedBox(width: 8),
        ],
      ),
      body: SafeArea(
        child: SelectionArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Header badge / title banner
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: isDark
                        ? theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.4)
                        : primaryColor.withValues(alpha: 0.06),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: isDark
                          ? theme.dividerColor.withValues(alpha: 0.2)
                          : primaryColor.withValues(alpha: 0.15),
                      width: 1,
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(
                            Icons.verified_outlined,
                            size: 18,
                            color: primaryColor,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              page.title,
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.bold,
                                color: isDark ? Colors.white : Colors.black87,
                              ),
                            ),
                          ),
                        ],
                      ),
                      if (page.metaDescription != null &&
                          page.metaDescription!.trim().isNotEmpty) ...[
                        const SizedBox(height: 6),
                        Text(
                          page.metaDescription!,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.textTheme.bodySmall?.color
                                ?.withValues(alpha: 0.75),
                          ),
                        ),
                      ],
                      if (page.updatedAt != null &&
                          page.updatedAt!.trim().isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Text(
                          'Last updated: ${_formatDate(page.updatedAt!)}',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w500,
                            color: theme.textTheme.bodySmall?.color
                                ?.withValues(alpha: 0.6),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                // Render dynamic HTML content
                if (page.content.trim().isNotEmpty)
                  HtmlWidget(
                    page.content,
                    textStyle: TextStyle(
                      fontSize: 14,
                      height: 1.6,
                      color: isDark ? Colors.white70 : Colors.black87,
                    ),
                    customStylesBuilder: (element) {
                      if (element.localName == 'h1') {
                        return {
                          'font-size': '1.3rem',
                          'font-weight': 'bold',
                          'margin-top': '1.2rem',
                          'margin-bottom': '0.5rem',
                        };
                      }
                      if (element.localName == 'h2') {
                        return {
                          'font-size': '1.15rem',
                          'font-weight': 'bold',
                          'margin-top': '1rem',
                          'margin-bottom': '0.4rem',
                        };
                      }
                      if (element.localName == 'h3') {
                        return {
                          'font-size': '1.05rem',
                          'font-weight': '600',
                          'margin-top': '0.8rem',
                          'margin-bottom': '0.3rem',
                        };
                      }
                      if (element.localName == 'p') {
                        return {
                          'margin-bottom': '0.75rem',
                        };
                      }
                      return null;
                    },
                    onTapUrl: (url) async {
                      final uri = Uri.tryParse(url);
                      if (uri != null) {
                        return await launchUrl(uri,
                            mode: LaunchMode.externalApplication);
                      }
                      return false;
                    },
                  )
                else
                  Center(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 40),
                      child: Text(
                        'No content available for this page.',
                        style: TextStyle(
                          color: theme.disabledColor,
                          fontStyle: FontStyle.italic,
                        ),
                      ),
                    ),
                  ),

                const SizedBox(height: 32),
                Center(
                  child: Text(
                    'ZooM-POS • Policy & Legal Center',
                    style: TextStyle(
                      fontSize: 11,
                      color: theme.disabledColor,
                    ),
                  ),
                ),
                const SizedBox(height: 16),
              ],
            ),
          ),
        ),
      ),
    );
  }

  String _formatDate(String isoString) {
    try {
      final dt = DateTime.parse(isoString);
      return '${dt.day.toString().padLeft(2, '0')}-${dt.month.toString().padLeft(2, '0')}-${dt.year}';
    } catch (_) {
      return isoString;
    }
  }
}
