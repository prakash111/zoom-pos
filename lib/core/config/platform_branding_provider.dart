import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api_client.dart';
import 'app_config.dart';

/// The SaaS owner's platform branding (name + logo + tagline) configured in
/// the superadmin panel and served, unauthenticated, from `GET /auth/branding`.
///
/// Persisted locally so the login screen paints the right identity on the
/// next cold start before the network call returns, and refreshed in the
/// background on every launch.
class PlatformBrandingProvider extends ChangeNotifier {
  static const _nameKey = 'zoom_pos.platform_name';
  static const _logoKey = 'zoom_pos.platform_logo_url';
  static const _taglineKey = 'zoom_pos.platform_tagline';
  static const _inlineKey = 'zoom_pos.platform_header_inline';
  static const _showTaglineKey = 'zoom_pos.platform_show_tagline';

  /// Fallbacks shown until (and unless) the server responds.
  static const String defaultName = 'Sales & Inventory';
  static const String defaultTagline = 'Online inventory management system';

  /// Auth-header layout, driven by `GET /auth/branding`
  /// (`header_inline` / `show_tagline`). Defaults reproduce the current
  /// server contract: logo + title on one row, no description block.
  static const bool defaultHeaderInline = true;
  static const bool defaultShowTagline = false;

  String platformName = defaultName;
  String? brandLogoUrl;
  String tagline = defaultTagline;
  bool headerInline = defaultHeaderInline;
  bool showTagline = defaultShowTagline;

  bool get hasLogo => (brandLogoUrl ?? '').isNotEmpty;

  static bool _asBool(dynamic raw, bool fallback) {
    if (raw is bool) return raw;
    if (raw is num) return raw != 0;
    final s = raw?.toString().trim().toLowerCase();
    if (s == null || s.isEmpty) return fallback;
    if (s == 'true' || s == '1' || s == 'yes') return true;
    if (s == 'false' || s == '0' || s == 'no') return false;
    return fallback;
  }

  Future<void> load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final name = prefs.getString(_nameKey);
      if (name != null && name.trim().isNotEmpty) {
        platformName = name.trim();
      }
      final logo = prefs.getString(_logoKey);
      if (logo != null && logo.trim().isNotEmpty) {
        brandLogoUrl = logo.trim();
      }
      final t = prefs.getString(_taglineKey);
      if (t != null && t.trim().isNotEmpty) {
        tagline = t.trim();
      }
      if (prefs.containsKey(_inlineKey)) {
        headerInline = prefs.getBool(_inlineKey) ?? defaultHeaderInline;
      }
      if (prefs.containsKey(_showTaglineKey)) {
        showTagline = prefs.getBool(_showTaglineKey) ?? defaultShowTagline;
      }
      notifyListeners();
    } catch (_) {
      // Keep the defaults — this is a best-effort convenience.
    }
  }

  /// Best-effort background refresh; never throws.
  Future<void> refresh(ApiClient apiClient) async {
    try {
      final response = await apiClient.get(ApiEndpoints.authBranding);
      final name = response['platform_name']?.toString().trim();
      final logo = response['brand_logo_url']?.toString().trim();
      final tag = response['platform_tagline']?.toString().trim();
      final inline = response.containsKey('header_inline')
          ? _asBool(response['header_inline'], headerInline)
          : headerInline;
      final showTag = response.containsKey('show_tagline')
          ? _asBool(response['show_tagline'], showTagline)
          : showTagline;

      var changed = false;
      if (name != null && name.isNotEmpty && name != platformName) {
        platformName = name;
        changed = true;
      }
      if (logo != null && logo != (brandLogoUrl ?? '')) {
        brandLogoUrl = logo.isEmpty ? null : logo;
        changed = true;
      }
      if (tag != null && tag.isNotEmpty && tag != tagline) {
        tagline = tag;
        changed = true;
      }
      if (inline != headerInline) {
        headerInline = inline;
        changed = true;
      }
      if (showTag != showTagline) {
        showTagline = showTag;
        changed = true;
      }
      if (changed) {
        notifyListeners();
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(_nameKey, platformName);
        await prefs.setString(_taglineKey, tagline);
        await prefs.setBool(_inlineKey, headerInline);
        await prefs.setBool(_showTaglineKey, showTagline);
        if (brandLogoUrl != null) {
          await prefs.setString(_logoKey, brandLogoUrl!);
        } else {
          await prefs.remove(_logoKey);
        }
      }
    } catch (_) {
      // Offline / server unreachable — the cached values stand.
    }
  }
}
