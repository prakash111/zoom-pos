import 'dart:convert';
import 'package:http/http.dart' as http;
import '../../../core/api/api_client.dart';
import '../../../core/config/app_config.dart';
import '../models/app_page_model.dart';

/// Server-driven metadata & pages bundle for Legal & App Information.
class AppPagesPayload {
  final List<AppPageModel> pages;
  final bool showAppVersion;
  final String? appVersion;
  final String modalTitle;
  final String modalSubtitle;
  final bool footerVisible;
  final String? footerText;

  const AppPagesPayload({
    required this.pages,
    this.showAppVersion = true,
    this.appVersion,
    this.modalTitle = 'Legal & App Information',
    this.modalSubtitle = 'Terms, policies & regulatory disclosures',
    this.footerVisible = true,
    this.footerText,
  });
}

/// Service for fetching dynamic Super Admin CMS pages marked for the Flutter app.
class AppPagesService {
  AppPagesService({ApiClient? apiClient}) : _apiClient = apiClient;

  final ApiClient? _apiClient;
  static List<AppPageModel>? _memoryCache;
  static AppPagesPayload? _cachedPayload;

  /// Fetch full server payload for legal & app pages modal.
  Future<AppPagesPayload> getAppPagesPayload({bool forceRefresh = false}) async {
    if (!forceRefresh && _cachedPayload != null && _cachedPayload!.pages.isNotEmpty) {
      return _cachedPayload!;
    }

    AppPagesPayload parsePayload(Map<String, dynamic> map) {
      final items = _parseList(map['data']);
      final bool showVersion = map['show_app_version'] == true ||
          (map['show_app_version'] == null && map['footer_visible'] == true);
      final String? version = map['app_version']?.toString();
      final String modalTitle = map['modal_title']?.toString() ??
          (map['modal'] is Map ? map['modal']['title']?.toString() : null) ??
          'Legal & App Information';
      final String modalSubtitle = map['modal_subtitle']?.toString() ??
          (map['modal'] is Map ? map['modal']['subtitle']?.toString() : null) ??
          'Terms, policies & regulatory disclosures';
      final bool footerVisible = map['footer_visible'] != null
          ? (map['footer_visible'] == true)
          : (map['footer'] is Map
              ? (map['footer']['visible'] == true)
              : (showVersion && (version != null && version.isNotEmpty)));
      final String? footerText = map['footer_text']?.toString() ??
          (map['footer'] is Map ? map['footer']['text']?.toString() : null) ??
          version;

      return AppPagesPayload(
        pages: items,
        showAppVersion: showVersion,
        appVersion: version,
        modalTitle: modalTitle,
        modalSubtitle: modalSubtitle,
        footerVisible: footerVisible,
        footerText: footerText,
      );
    }

    // 1. Try via ApiClient
    if (_apiClient != null) {
      try {
        final res = await _apiClient.get('/app-pages');
        if (res is Map<String, dynamic> && res['data'] != null) {
          final payload = parsePayload(res);
          _cachedPayload = payload;
          _memoryCache = payload.pages;
          return payload;
        }
      } catch (_) {
        // Fall back to direct HTTP call below
      }
    }

    // 2. Direct HTTP fallback (works unauthenticated and standalone)
    try {
      final baseUrl = _apiClient != null
          ? await _apiClient.currentBaseUrl()
          : AppConfig.defaultBaseUrl;
      final uri = Uri.parse('$baseUrl/api/v1/pos/app-pages');
      final res = await http.get(uri, headers: {
        'Accept': 'application/json',
      }).timeout(const Duration(seconds: 10));

      if (res.statusCode == 200) {
        final decoded = jsonDecode(res.body);
        if (decoded is Map<String, dynamic> && decoded['data'] != null) {
          final payload = parsePayload(decoded);
          _cachedPayload = payload;
          _memoryCache = payload.pages;
          return payload;
        }
      }
    } catch (_) {
      if (_cachedPayload != null) {
        return _cachedPayload!;
      }
      rethrow;
    }

    return _cachedPayload ??
        const AppPagesPayload(
          pages: [],
          showAppVersion: false,
          footerVisible: false,
        );
  }

  /// Fetch all active app pages.
  Future<List<AppPageModel>> getAppPages({bool forceRefresh = false}) async {
    final payload = await getAppPagesPayload(forceRefresh: forceRefresh);
    return payload.pages;
  }

  /// Fetch a single page by slug.
  Future<AppPageModel?> getPageBySlug(String slug, {bool forceRefresh = false}) async {
    final pages = await getAppPages(forceRefresh: forceRefresh);
    for (final p in pages) {
      if (p.slug.toLowerCase() == slug.toLowerCase()) {
        return p;
      }
    }

    // Fallback: single endpoint fetch
    if (_apiClient != null) {
      try {
        final res = await _apiClient.get('/app-pages/$slug');
        if (res['data'] is Map<String, dynamic>) {
          return AppPageModel.fromJson(res['data'] as Map<String, dynamic>);
        }
      } catch (_) {}
    }

    return null;
  }

  static List<AppPageModel> _parseList(dynamic raw) {
    if (raw is List) {
      return raw
          .whereType<Map<String, dynamic>>()
          .map((m) => AppPageModel.fromJson(m))
          .toList();
    }
    return const [];
  }
}
