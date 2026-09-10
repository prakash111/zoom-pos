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

  /// Fallbacks shown until (and unless) the server responds.
  static const String defaultName = 'Sales & Inventory';
  static const String defaultTagline = 'Online inventory management system';

  String platformName = defaultName;
  String? brandLogoUrl;
  String tagline = defaultTagline;

  bool get hasLogo => (brandLogoUrl ?? '').isNotEmpty;

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
      if (changed) {
        notifyListeners();
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(_nameKey, platformName);
        await prefs.setString(_taglineKey, tagline);
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
