import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api_client.dart';
import 'app_config.dart';

/// The SaaS owner's global platform branding + pre-auth theme, configured in
/// the Superadmin panel and served, unauthenticated, from
/// `GET /auth/branding` (aka `/auth/public-settings`):
///
/// ```json
/// { "platform": { "name", "headline", "description", "logo_url", "favicon_url" },
///   "theme":    { "primary_color", "secondary_color", "accent_color",
///                 "splash_bg_color", "auth_bg_color" } }
/// ```
///
/// Persisted locally so the Splash / Login / Register / Forgot-Password
/// screens paint the right identity on the next cold start before the network
/// call returns. Tenants override this after sign-in via [ThemeProvider] /
/// `BootstrapCache`.
class PlatformBrandingProvider extends ChangeNotifier {
  static const _nameKey = 'zoom_pos.platform_name';
  static const _logoKey = 'zoom_pos.platform_logo_url';
  static const _faviconKey = 'zoom_pos.platform_favicon_url';
  static const _taglineKey = 'zoom_pos.platform_tagline';
  static const _headlineKey = 'zoom_pos.platform_headline';
  static const _descKey = 'zoom_pos.platform_description';
  static const _inlineKey = 'zoom_pos.platform_header_inline';
  static const _showTaglineKey = 'zoom_pos.platform_show_tagline';
  static const _primaryKey = 'zoom_pos.platform_primary_color';
  static const _secondaryKey = 'zoom_pos.platform_secondary_color';
  static const _accentKey = 'zoom_pos.platform_accent_color';
  static const _splashBgKey = 'zoom_pos.platform_splash_bg';
  static const _authBgKey = 'zoom_pos.platform_auth_bg';
  static const _supportPhoneKey = 'zoom_pos.platform_support_phone';
  static const _supportEmailKey = 'zoom_pos.platform_support_email';
  static const _bannerKey = 'zoom_pos.platform_auth_banner_url';
  static const _showBannerKey = 'zoom_pos.platform_show_auth_banner';
  static const _landingEnabledKey = 'zoom_pos.platform_landing_enabled';

  /// Fallbacks shown until (and unless) the server responds — these mirror the
  /// Superadmin contract defaults.
  static const String defaultName = 'Sales & Inventory';
  static const String defaultTagline = 'Online inventory management system';
  static const String defaultSupportPhone = '+918535075196';
  static const String defaultSupportEmail = 'support@zoomnearby.com';

  /// Auth marketing copy is purely Superadmin-authored — there is no app-side
  /// default. Empty means "render nothing".
  static const String defaultHeadline = '';
  static const String defaultDescription = '';

  static const Color defaultPrimary = Color(0xFFF95700);
  static const Color defaultSecondary = Color(0xFF0F172A);
  static const Color defaultAccent = Color(0xFFFF7A00);
  static const Color defaultSplashBg = Color(0xFF0F172A);
  static const Color defaultAuthBg = Color(0xFFF8FAFC);

  /// Auth-header layout, driven by `header_inline` / `show_tagline`.
  static const bool defaultHeaderInline = true;
  static const bool defaultShowTagline = false;

  String platformName = defaultName;
  String? brandLogoUrl;
  String? faviconUrl;
  String tagline = defaultTagline;
  String headline = defaultHeadline;
  String description = defaultDescription;
  String supportPhone = defaultSupportPhone;
  String supportEmail = defaultSupportEmail;
  String? authBannerImageUrl;
  bool showAuthBanner = false;
  bool landingPageEnabled = true;
  bool headerInline = defaultHeaderInline;
  bool showTagline = defaultShowTagline;

  Color primaryColor = defaultPrimary;
  Color secondaryColor = defaultSecondary;
  Color accentColor = defaultAccent;
  Color splashBgColor = defaultSplashBg;
  Color authBgColor = defaultAuthBg;

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

  static Color? _parseHex(dynamic raw) {
    var s = raw?.toString().trim() ?? '';
    if (s.isEmpty) return null;
    if (s.startsWith('#')) s = s.substring(1);
    if (s.length == 6) s = 'FF$s';
    if (s.length != 8) return null;
    final v = int.tryParse(s, radix: 16);
    return v == null ? null : Color(v);
  }

  static String _hex(Color c) =>
      '#${(c.toARGB32() & 0xFFFFFF).toRadixString(16).padLeft(6, '0').toUpperCase()}';

  Future<void> load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      String? str(String k) {
        final v = prefs.getString(k);
        return (v != null && v.trim().isNotEmpty) ? v.trim() : null;
      }

      platformName = str(_nameKey) ?? platformName;
      brandLogoUrl = str(_logoKey) ?? brandLogoUrl;
      faviconUrl = str(_faviconKey) ?? faviconUrl;
      tagline = str(_taglineKey) ?? tagline;
      headline = str(_headlineKey) ?? headline;
      description = str(_descKey) ?? description;
      primaryColor = _parseHex(str(_primaryKey)) ?? primaryColor;
      secondaryColor = _parseHex(str(_secondaryKey)) ?? secondaryColor;
      accentColor = _parseHex(str(_accentKey)) ?? accentColor;
      splashBgColor = _parseHex(str(_splashBgKey)) ?? splashBgColor;
      authBgColor = _parseHex(str(_authBgKey)) ?? authBgColor;
      supportPhone = str(_supportPhoneKey) ?? supportPhone;
      supportEmail = str(_supportEmailKey) ?? supportEmail;
      authBannerImageUrl = str(_bannerKey) ?? authBannerImageUrl;
      if (prefs.containsKey(_showBannerKey)) {
        showAuthBanner = prefs.getBool(_showBannerKey) ?? showAuthBanner;
      }
      if (prefs.containsKey(_landingEnabledKey)) {
        landingPageEnabled = prefs.getBool(_landingEnabledKey) ?? true;
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
      final platform = response['platform'] is Map
          ? Map<String, dynamic>.from(response['platform'] as Map)
          : const <String, dynamic>{};
      final theme = response['theme'] is Map
          ? Map<String, dynamic>.from(response['theme'] as Map)
          : const <String, dynamic>{};

      String? pick(String nested, List<String> flats) {
        dynamic raw = platform[nested];
        for (final f in flats) {
          if (raw != null && raw.toString().trim().isNotEmpty) break;
          raw = response[f];
        }
        final v = raw?.toString().trim();
        return (v == null || v.isEmpty) ? null : v;
      }

      // The Superadmin "Platform Title / App Name" under any of its aliases.
      final name = pick('name', const [
        'platform_name',
        'platform_title',
        'app_name',
      ]);
      final logo = pick('logo_url', const ['brand_logo_url']);
      final favicon = pick('favicon_url', const ['favicon_url']);
      final head = pick('headline', const ['platform_headline']);
      final desc = pick('description', const ['platform_description']);
      final tag = response['platform_tagline']?.toString().trim();

      final inline = response.containsKey('header_inline')
          ? _asBool(response['header_inline'], headerInline)
          : headerInline;
      final showTag = response.containsKey('show_tagline')
          ? _asBool(response['show_tagline'], showTagline)
          : showTagline;

      final phone = pick('support_phone', const ['support_whatsapp', 'phone']);
      final email = pick('support_email', const ['email']);
      final banner = pick('auth_banner_image_url', const ['banner_url', 'auth_banner_url']);
      final showBanner = response.containsKey('show_auth_banner')
          ? _asBool(response['show_auth_banner'], showAuthBanner)
          : (platform.containsKey('show_auth_banner')
              ? _asBool(platform['show_auth_banner'], showAuthBanner)
              : showAuthBanner);
      final landingEnabled = response.containsKey('landing_page_enabled')
          ? _asBool(response['landing_page_enabled'], landingPageEnabled)
          : (platform.containsKey('landing_page_enabled')
              ? _asBool(platform['landing_page_enabled'], landingPageEnabled)
              : landingPageEnabled);

      // Primary colour under any of its aliases (theme.primary /
      // theme.primary_color / brand_color / primary_color).
      final primary = _parseHex(theme['primary'] ??
              theme['primary_color'] ??
              response['brand_color'] ??
              response['primary_color']) ??
          primaryColor;
      final secondary =
          _parseHex(theme['secondary_color'] ?? theme['secondary']) ??
              secondaryColor;
      final accent =
          _parseHex(theme['accent_color'] ?? theme['accent']) ?? accentColor;
      final splashBg = _parseHex(theme['splash_bg_color']) ?? splashBgColor;
      final authBg = _parseHex(theme['auth_bg_color']) ?? authBgColor;

      var changed = false;
      void set<T>(T next, T current, void Function() apply) {
        if (next != current) {
          apply();
          changed = true;
        }
      }

      if (name != null) set(name, platformName, () => platformName = name);
      set(logo, brandLogoUrl, () => brandLogoUrl = logo);
      set(favicon, faviconUrl, () => faviconUrl = favicon);
      if (tag != null && tag.isNotEmpty) {
        set(tag, tagline, () => tagline = tag);
      }
      if (head != null) set(head, headline, () => headline = head);
      if (desc != null) set(desc, description, () => description = desc);
      if (phone != null) set(phone, supportPhone, () => supportPhone = phone);
      if (email != null) set(email, supportEmail, () => supportEmail = email);
      set(banner, authBannerImageUrl, () => authBannerImageUrl = banner);
      set(showBanner, showAuthBanner, () => showAuthBanner = showBanner);
      set(landingEnabled, landingPageEnabled, () => landingPageEnabled = landingEnabled);
      set(inline, headerInline, () => headerInline = inline);
      set(showTag, showTagline, () => showTagline = showTag);
      set(primary, primaryColor, () => primaryColor = primary);
      set(secondary, secondaryColor, () => secondaryColor = secondary);
      set(accent, accentColor, () => accentColor = accent);
      set(splashBg, splashBgColor, () => splashBgColor = splashBg);
      set(authBg, authBgColor, () => authBgColor = authBg);

      if (changed) {
        notifyListeners();
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(_nameKey, platformName);
        await prefs.setString(_taglineKey, tagline);
        await prefs.setString(_headlineKey, headline);
        await prefs.setString(_descKey, description);
        await prefs.setBool(_inlineKey, headerInline);
        await prefs.setBool(_showTaglineKey, showTagline);
        await prefs.setString(_primaryKey, _hex(primaryColor));
        await prefs.setString(_secondaryKey, _hex(secondaryColor));
        await prefs.setString(_accentKey, _hex(accentColor));
        await prefs.setString(_splashBgKey, _hex(splashBgColor));
        await prefs.setString(_authBgKey, _hex(authBgColor));
        await prefs.setString(_supportPhoneKey, supportPhone);
        await prefs.setString(_supportEmailKey, supportEmail);
        await prefs.setBool(_showBannerKey, showAuthBanner);
        await prefs.setBool(_landingEnabledKey, landingPageEnabled);
        Future<void> put(String k, String? v) => (v != null && v.isNotEmpty)
            ? prefs.setString(k, v)
            : prefs.remove(k);
        await put(_logoKey, brandLogoUrl);
        await put(_faviconKey, faviconUrl);
        await put(_bannerKey, authBannerImageUrl);
      }
    } catch (_) {
      // Offline / server unreachable — the cached values stand.
    }
  }
}
