import 'dart:convert';
import 'package:http/http.dart' as http;
import '../../../core/api/api_client.dart';
import '../../../core/config/app_config.dart';
import '../models/app_page_model.dart';

/// Service for fetching dynamic Super Admin CMS pages marked for the Flutter app.
class AppPagesService {
  AppPagesService({ApiClient? apiClient}) : _apiClient = apiClient;

  final ApiClient? _apiClient;
  static List<AppPageModel>? _memoryCache;

  /// Fetch all active app pages.
  Future<List<AppPageModel>> getAppPages({bool forceRefresh = false}) async {
    if (!forceRefresh && _memoryCache != null && _memoryCache!.isNotEmpty) {
      return _memoryCache!;
    }

    // 1. Try via ApiClient
    if (_apiClient != null) {
      try {
        final res = await _apiClient.get('/app-pages');
        final items = _parseList(res['data']);
        if (items.isNotEmpty) {
          _memoryCache = items;
          return items;
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
          final items = _parseList(decoded['data']);
          _memoryCache = items;
          return items;
        }
      }
    } catch (_) {
      if (_memoryCache != null && _memoryCache!.isNotEmpty) {
        return _memoryCache!;
      }
      rethrow;
    }

    return _memoryCache ?? const [];
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
