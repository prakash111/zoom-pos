import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/api/api_client.dart';
import '../../../core/config/locale_provider.dart';
import '../../../core/config/theme_provider.dart';
import '../../../core/widgets/app_network_image.dart';
import '../../auth/auth_provider.dart';
import '../../auth/screens/login_screen.dart';
import '../../dashboard/dashboard_screen.dart';
import '../models/landing_data.dart';
import '../services/landing_provider.dart';
import '../widgets/interactive_contact_form.dart';

class _LandingThemeTokens {
  final bool isDark;
  final Color scaffoldBg;
  final Color sectionAltBg;
  final Color surfaceCard;
  final Color borderColor;
  final Color textPrimary;
  final Color textSecondary;
  final Color primaryColor;
  final Color accentColor;

  const _LandingThemeTokens({
    required this.isDark,
    required this.scaffoldBg,
    required this.sectionAltBg,
    required this.surfaceCard,
    required this.borderColor,
    required this.textPrimary,
    required this.textSecondary,
    required this.primaryColor,
    required this.accentColor,
  });

  factory _LandingThemeTokens.resolve({
    required BuildContext context,
    required LandingBranding branding,
    required ThemeProvider themeProvider,
  }) {
    final mode = themeProvider.themeMode;
    final isDark = mode == ThemeMode.dark ||
        (mode == ThemeMode.system &&
            MediaQuery.platformBrightnessOf(context) == Brightness.dark);

    final primary =
        _parseColorStatic(branding.primaryColorHex, const Color(0xFF10B981));
    final accent =
        _parseColorStatic(branding.accentColorHex, const Color(0xFF38BDF8));

    return _LandingThemeTokens(
      isDark: isDark,
      scaffoldBg: isDark ? const Color(0xFF0B1120) : const Color(0xFFF8FAFC),
      sectionAltBg: isDark ? const Color(0xFF0F172A) : const Color(0xFFF1F5F9),
      surfaceCard: isDark ? const Color(0xFF131D2D) : const Color(0xFFFFFFFF),
      borderColor: isDark ? const Color(0xFF1E293B) : const Color(0xFFE2E8F0),
      textPrimary: isDark ? const Color(0xFFF8FAFC) : const Color(0xFF0F172A),
      textSecondary:
          isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
      primaryColor: primary,
      accentColor: accent,
    );
  }

  static Color _parseColorStatic(String? hex, Color fallback) {
    if (hex == null || hex.isEmpty) return fallback;
    final buffer = StringBuffer();
    if (hex.length == 6 || hex.length == 7) buffer.write('ff');
    buffer.write(hex.replaceFirst('#', ''));
    try {
      return Color(int.parse(buffer.toString(), radix: 16));
    } catch (_) {
      return fallback;
    }
  }
}

class LandingScreen extends StatefulWidget {
  const LandingScreen({super.key});

  @override
  State<LandingScreen> createState() => _LandingScreenState();
}

class _LandingScreenState extends State<LandingScreen> {
  final ScrollController _scrollController = ScrollController();
  final GlobalKey _featuresKey = GlobalKey();
  final GlobalKey _solutionsKey = GlobalKey();
  final GlobalKey _hardwareKey = GlobalKey();
  final GlobalKey _pricingKey = GlobalKey();
  final GlobalKey _faqsKey = GlobalKey();
  final GlobalKey _contactKey = GlobalKey();

  late final LandingProvider _landingProvider;

  static const List<Map<String, String>> _languages = [
    {'code': 'en', 'label': 'English', 'flag': '🇺🇸'},
    {'code': 'ar', 'label': 'العربية', 'flag': '🇸🇦'},
    {'code': 'es', 'label': 'Español', 'flag': '🇪🇸'},
    {'code': 'hi', 'label': 'हिन्दी', 'flag': '🇮🇳'},
    {'code': 'fr', 'label': 'Français', 'flag': '🇫🇷'},
    {'code': 'pt', 'label': 'Português', 'flag': '🇧🇷'},
    {'code': 'de', 'label': 'Deutsch', 'flag': '🇩🇪'},
    {'code': 'zh', 'label': '中文', 'flag': '🇨🇳'},
    {'code': 'ja', 'label': '日本語', 'flag': '🇯🇵'},
    {'code': 'ru', 'label': 'Русский', 'flag': '🇷🇺'},
    {'code': 'it', 'label': 'Italiano', 'flag': '🇮🇹'},
    {'code': 'id', 'label': 'Bahasa Indonesia', 'flag': '🇮🇩'},
    {'code': 'tr', 'label': 'Türkçe', 'flag': '🇹🇷'},
  ];

  @override
  void initState() {
    super.initState();
    final apiClient = context.read<ApiClient>();
    _landingProvider = LandingProvider(apiClient: apiClient);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _landingProvider.refresh(apiClient);
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    _landingProvider.dispose();
    super.dispose();
  }

  void _scrollToSection(GlobalKey key) {
    final ctx = key.currentContext;
    if (ctx != null) {
      Scrollable.ensureVisible(
        ctx,
        duration: const Duration(milliseconds: 500),
        curve: Curves.easeInOutCubic,
      );
    }
  }

  void _navigateToAuth(BuildContext context) {
    final auth = context.read<AuthProvider>();
    if (auth.status == AuthStatus.authenticated) {
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const DashboardScreen()),
      );
    } else {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
    }
  }

  Future<void> _launchExternalUrl(String? urlString) async {
    if (urlString == null || urlString.isEmpty) return;
    final uri = Uri.tryParse(urlString);
    if (uri != null && await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  String _formatExtensionName(String ext) {
    switch (ext.toLowerCase()) {
      case 'leadmanagement':
        return 'CRM & Leads';
      case 'whatsapp_api':
        return 'WhatsApp API';
      case 'custom_domain':
        return 'Custom Domain';
      default:
        return ext
            .replaceAll('_', ' ')
            .split(' ')
            .map((w) => w.isNotEmpty
                ? '${w[0].toUpperCase()}${w.substring(1)}'
                : '')
            .join(' ');
    }
  }

  Widget _buildLimitChip({
    required IconData icon,
    required String label,
    required _LandingThemeTokens tokens,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: tokens.isDark
            ? tokens.primaryColor.withValues(alpha: 0.12)
            : tokens.primaryColor.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: tokens.primaryColor.withValues(alpha: 0.25),
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: tokens.primaryColor),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              color:
                  tokens.isDark ? tokens.textPrimary : const Color(0xFF065F46),
              fontWeight: FontWeight.w600,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildLanguageDropdown(
    BuildContext context,
    LocaleProvider localeProvider,
    _LandingThemeTokens tokens,
  ) {
    final currentCode = localeProvider.locale.languageCode;
    final activeCode =
        _languages.any((l) => l['code'] == currentCode) ? currentCode : 'en';

    return DropdownButtonHideUnderline(
      child: DropdownButton<String>(
        value: activeCode,
        dropdownColor: tokens.surfaceCard,
        icon: Icon(Icons.keyboard_arrow_down,
            color: tokens.textSecondary, size: 18),
        style: TextStyle(
          color: tokens.textPrimary,
          fontSize: 13,
          fontWeight: FontWeight.w600,
        ),
        items: _languages.map((lang) {
          return DropdownMenuItem<String>(
            value: lang['code'],
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(lang['flag']!, style: const TextStyle(fontSize: 14)),
                const SizedBox(width: 6),
                Text(
                  lang['label']!,
                  style: TextStyle(
                    color: tokens.textPrimary,
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          );
        }).toList(),
        onChanged: (code) {
          if (code != null) {
            localeProvider.setLocale(Locale(code));
          }
        },
      ),
    );
  }

  Widget _buildThemeToggle(
    BuildContext context,
    ThemeProvider themeProvider,
    _LandingThemeTokens tokens,
  ) {
    return IconButton(
      tooltip: tokens.isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode',
      icon: Icon(
        tokens.isDark ? Icons.light_mode_outlined : Icons.dark_mode_outlined,
        color: tokens.textPrimary,
        size: 20,
      ),
      onPressed: () {
        themeProvider.setThemeMode(
          tokens.isDark ? ThemeMode.light : ThemeMode.dark,
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final themeProvider = context.watch<ThemeProvider>();
    final localeProvider = context.watch<LocaleProvider>();

    return ChangeNotifierProvider<LandingProvider>.value(
      value: _landingProvider,
      child: Consumer<LandingProvider>(
        builder: (context, provider, _) {
          final data = provider.data;
          final branding = data.branding;
          final hero = data.hero;

          final tokens = _LandingThemeTokens.resolve(
            context: context,
            branding: branding,
            themeProvider: themeProvider,
          );

          if (!data.landingPageEnabled) {
            WidgetsBinding.instance.addPostFrameCallback((_) {
              if (mounted) {
                Navigator.of(context).pushReplacement(
                  MaterialPageRoute(builder: (_) => const LoginScreen()),
                );
              }
            });
            return const Scaffold(
              body: Center(child: CircularProgressIndicator()),
            );
          }

          final isRtl = localeProvider.locale.languageCode == 'ar' ||
              localeProvider.locale.languageCode == 'ur';

          return Directionality(
            textDirection: isRtl ? TextDirection.rtl : TextDirection.ltr,
            child: Scaffold(
              backgroundColor: tokens.scaffoldBg,
              appBar: _buildTopNav(
                  context, data, tokens, themeProvider, localeProvider),
              endDrawer: _buildMobileDrawer(
                  context, data, tokens, themeProvider, localeProvider),
              body: RefreshIndicator(
                color: tokens.primaryColor,
                backgroundColor: tokens.surfaceCard,
                onRefresh: () =>
                    _landingProvider.refresh(context.read<ApiClient>()),
                child: SingleChildScrollView(
                  controller: _scrollController,
                  child: Column(
                    children: [
                      _buildHeroSection(context, hero, branding, tokens),
                      _buildHardwareBar(context, data.hardware, tokens),
                      _buildStatsSection(context, data.stats, tokens),
                      _buildSolutionsSection(context, data.solutions, tokens),
                      _buildFeaturesSection(context, data.features, tokens),
                      _buildPricingSection(context, data.plans, tokens),
                      if (data.testimonials.isNotEmpty)
                        _buildTestimonialsSection(
                            context, data.testimonials, tokens),
                      _buildFaqSection(context, data.faqs, tokens),
                      if (data.downloads.playstoreEnabled ||
                          data.downloads.windowsEnabled)
                        _buildDownloadsSection(context, data.downloads, tokens),
                      _buildContactSection(context, data.contact, tokens),
                      _buildFooter(context, branding, tokens),
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  PreferredSizeWidget _buildTopNav(
    BuildContext context,
    LandingData data,
    _LandingThemeTokens tokens,
    ThemeProvider themeProvider,
    LocaleProvider localeProvider,
  ) {
    final branding = data.branding;
    final isDesktop = MediaQuery.sizeOf(context).width >= 960;

    return AppBar(
      backgroundColor: tokens.scaffoldBg.withValues(alpha: 0.96),
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 2,
      shadowColor: Colors.black.withValues(alpha: tokens.isDark ? 0.4 : 0.08),
      titleSpacing: 20,
      title: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (branding.logoUrl != null && branding.logoUrl!.isNotEmpty) ...[
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: AppNetworkImage(
                imageUrl: branding.logoUrl,
                width: 36,
                height: 36,
                fit: BoxFit.contain,
                fallbackIcon: Icons.storefront,
              ),
            ),
            const SizedBox(width: 10),
          ] else
            Container(
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: tokens.primaryColor.withValues(alpha: 0.2),
                borderRadius: BorderRadius.circular(8),
              ),
              child:
                  Icon(Icons.storefront, color: tokens.primaryColor, size: 22),
            ),
          const SizedBox(width: 8),
          Flexible(
            child: Text(
              branding.platformName,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: tokens.textPrimary,
                fontWeight: FontWeight.w800,
                fontSize: 18,
                letterSpacing: -0.3,
              ),
            ),
          ),
        ],
      ),
      actions: [
        if (isDesktop) ...[
          _navTextButton(
              'Features', () => _scrollToSection(_featuresKey), tokens),
          _navTextButton(
              'Solutions', () => _scrollToSection(_solutionsKey), tokens),
          _navTextButton(
              'Hardware', () => _scrollToSection(_hardwareKey), tokens),
          _navTextButton(
              'Pricing', () => _scrollToSection(_pricingKey), tokens),
          _navTextButton('FAQs', () => _scrollToSection(_faqsKey), tokens),
          _navTextButton(
              'Contact', () => _scrollToSection(_contactKey), tokens),
          const SizedBox(width: 12),
          _buildLanguageDropdown(context, localeProvider, tokens),
          const SizedBox(width: 6),
          _buildThemeToggle(context, themeProvider, tokens),
          const SizedBox(width: 10),
          Padding(
            padding: const EdgeInsets.only(right: 20),
            child: FilledButton.icon(
              style: FilledButton.styleFrom(
                backgroundColor: tokens.primaryColor,
                foregroundColor: Colors.white,
                padding:
                    const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10)),
              ),
              icon: const Icon(Icons.login, size: 16),
              label: const Text(
                'Sign In / POS',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
              ),
              onPressed: () => _navigateToAuth(context),
            ),
          ),
        ] else ...[
          _buildLanguageDropdown(context, localeProvider, tokens),
          _buildThemeToggle(context, themeProvider, tokens),
          IconButton(
            tooltip: 'Sign In',
            icon: Icon(Icons.login, color: tokens.textPrimary),
            onPressed: () => _navigateToAuth(context),
          ),
          Builder(
            builder: (ctx) => IconButton(
              icon: Icon(Icons.menu, color: tokens.textPrimary),
              onPressed: () => Scaffold.of(ctx).openEndDrawer(),
            ),
          ),
        ],
      ],
    );
  }

  Widget _navTextButton(
      String title, VoidCallback onTap, _LandingThemeTokens tokens) {
    return TextButton(
      onPressed: onTap,
      child: Text(
        title,
        style: TextStyle(
          color: tokens.textSecondary,
          fontWeight: FontWeight.w600,
          fontSize: 14,
        ),
      ),
    );
  }

  Widget _buildMobileDrawer(
    BuildContext context,
    LandingData data,
    _LandingThemeTokens tokens,
    ThemeProvider themeProvider,
    LocaleProvider localeProvider,
  ) {
    final branding = data.branding;
    return Drawer(
      backgroundColor: tokens.surfaceCard,
      child: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(20),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: tokens.primaryColor.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(Icons.storefront,
                        color: tokens.primaryColor, size: 24),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      branding.platformName,
                      style: TextStyle(
                        color: tokens.textPrimary,
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Divider(color: tokens.borderColor),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(vertical: 8),
                children: [
                  _drawerNavItem('Features', Icons.stars_outlined, () {
                    Navigator.of(context).pop();
                    _scrollToSection(_featuresKey);
                  }, tokens),
                  _drawerNavItem('Solutions', Icons.grid_view_outlined, () {
                    Navigator.of(context).pop();
                    _scrollToSection(_solutionsKey);
                  }, tokens),
                  _drawerNavItem('Hardware', Icons.devices_other_outlined, () {
                    Navigator.of(context).pop();
                    _scrollToSection(_hardwareKey);
                  }, tokens),
                  _drawerNavItem('Pricing', Icons.payments_outlined, () {
                    Navigator.of(context).pop();
                    _scrollToSection(_pricingKey);
                  }, tokens),
                  _drawerNavItem('FAQs', Icons.help_outline, () {
                    Navigator.of(context).pop();
                    _scrollToSection(_faqsKey);
                  }, tokens),
                  _drawerNavItem('Contact', Icons.contact_support_outlined, () {
                    Navigator.of(context).pop();
                    _scrollToSection(_contactKey);
                  }, tokens),
                ],
              ),
            ),
            Divider(color: tokens.borderColor),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Language',
                        style: TextStyle(
                          color: tokens.textSecondary,
                          fontWeight: FontWeight.w600,
                          fontSize: 14,
                        ),
                      ),
                      _buildLanguageDropdown(context, localeProvider, tokens),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        tokens.isDark ? 'Dark Mode' : 'Light Mode',
                        style: TextStyle(
                          color: tokens.textSecondary,
                          fontWeight: FontWeight.w600,
                          fontSize: 14,
                        ),
                      ),
                      Switch(
                        value: tokens.isDark,
                        activeThumbColor: tokens.primaryColor,
                        onChanged: (val) {
                          themeProvider.setThemeMode(
                            val ? ThemeMode.dark : ThemeMode.light,
                          );
                        },
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      style: FilledButton.styleFrom(
                        backgroundColor: tokens.primaryColor,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10)),
                      ),
                      icon: const Icon(Icons.login, size: 18),
                      label: const Text(
                        'Sign In to POS',
                        style: TextStyle(fontWeight: FontWeight.bold),
                      ),
                      onPressed: () {
                        Navigator.of(context).pop();
                        _navigateToAuth(context);
                      },
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

  Widget _drawerNavItem(
    String title,
    IconData icon,
    VoidCallback onTap,
    _LandingThemeTokens tokens,
  ) {
    return ListTile(
      leading: Icon(icon, color: tokens.textSecondary, size: 20),
      title: Text(
        title,
        style: TextStyle(
          color: tokens.textPrimary,
          fontWeight: FontWeight.w600,
          fontSize: 14,
        ),
      ),
      onTap: onTap,
    );
  }

  Widget _buildHeroSection(
    BuildContext context,
    LandingHero hero,
    LandingBranding branding,
    _LandingThemeTokens tokens,
  ) {
    final isDesktop = MediaQuery.sizeOf(context).width >= 960;
    final bannerUrl = hero.bannerImageUrl ??
        (branding.showAuthBanner ? branding.authBannerImageUrl : null);

    return Container(
      width: double.infinity,
      padding: EdgeInsets.symmetric(
        horizontal: isDesktop ? 48 : 20,
        vertical: isDesktop ? 64 : 36,
      ),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: tokens.isDark
              ? const [Color(0xFF0B1120), Color(0xFF0F172A)]
              : const [Color(0xFFF8FAFC), Color(0xFFEEF2F6)],
        ),
      ),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 1200),
          child: isDesktop
              ? Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    Expanded(
                      flex: 6,
                      child: _buildHeroCopy(context, hero, tokens),
                    ),
                    const SizedBox(width: 48),
                    Expanded(
                      flex: 5,
                      child: _buildHeroBanner(context, bannerUrl, tokens),
                    ),
                  ],
                )
              : Column(
                  children: [
                    _buildHeroCopy(context, hero, tokens),
                    const SizedBox(height: 36),
                    _buildHeroBanner(context, bannerUrl, tokens),
                  ],
                ),
        ),
      ),
    );
  }

  Widget _buildHeroCopy(
    BuildContext context,
    LandingHero hero,
    _LandingThemeTokens tokens,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
          decoration: BoxDecoration(
            color: tokens.primaryColor.withValues(alpha: 0.15),
            border:
                Border.all(color: tokens.primaryColor.withValues(alpha: 0.35)),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                hero.badge,
                style: TextStyle(
                  color: tokens.primaryColor,
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        Text(
          hero.title,
          style: TextStyle(
            color: tokens.textPrimary,
            fontWeight: FontWeight.w900,
            fontSize: 38,
            height: 1.15,
            letterSpacing: -1.0,
          ),
        ),
        const SizedBox(height: 18),
        Text(
          hero.subtitle,
          style: TextStyle(
            color: tokens.textSecondary,
            fontSize: 16,
            height: 1.5,
          ),
        ),
        const SizedBox(height: 28),
        Wrap(
          spacing: 14,
          runSpacing: 12,
          children: [
            FilledButton.icon(
              style: FilledButton.styleFrom(
                backgroundColor: tokens.primaryColor,
                foregroundColor: Colors.white,
                padding:
                    const EdgeInsets.symmetric(horizontal: 26, vertical: 16),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12)),
                elevation: 4,
              ),
              icon: const Icon(Icons.rocket_launch, size: 18),
              label: Text(
                hero.ctaPrimaryText.isNotEmpty
                    ? hero.ctaPrimaryText
                    : 'Start Free Trial',
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 15,
                ),
              ),
              onPressed: () => _navigateToAuth(context),
            ),
            OutlinedButton.icon(
              style: OutlinedButton.styleFrom(
                foregroundColor: tokens.textPrimary,
                side: BorderSide(color: tokens.borderColor),
                padding:
                    const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12)),
              ),
              icon: const Icon(Icons.visibility_outlined, size: 18),
              label: Text(
                hero.ctaSecondaryText.isNotEmpty
                    ? hero.ctaSecondaryText
                    : 'Explore Pricing',
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 15,
                ),
              ),
              onPressed: () => _scrollToSection(_pricingKey),
            ),
          ],
        ),
        const SizedBox(height: 20),
        Row(
          children: [
            Icon(Icons.check_circle, color: tokens.primaryColor, size: 16),
            const SizedBox(width: 6),
            Text(
              'No credit card required',
              style: TextStyle(
                color: tokens.textSecondary,
                fontSize: 12,
              ),
            ),
            const SizedBox(width: 16),
            Icon(Icons.check_circle, color: tokens.primaryColor, size: 16),
            const SizedBox(width: 6),
            Text(
              'Instant setup',
              style: TextStyle(
                color: tokens.textSecondary,
                fontSize: 12,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildHeroBanner(
      BuildContext context, String? bannerUrl, _LandingThemeTokens tokens) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: tokens.primaryColor.withValues(alpha: 0.2),
            blurRadius: 32,
            spreadRadius: 2,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: bannerUrl != null && bannerUrl.isNotEmpty
            ? AppNetworkImage(
                imageUrl: bannerUrl,
                height: 380,
                fit: BoxFit.cover,
                fallbackIcon: Icons.point_of_sale,
              )
            : _buildFallbackHeroGraphic(tokens),
      ),
    );
  }

  Widget _buildFallbackHeroGraphic(_LandingThemeTokens tokens) {
    final cardInnerBg = tokens.isDark
        ? const Color(0xFF1E293B)
        : tokens.scaffoldBg;

    return Container(
      height: 360,
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: tokens.surfaceCard,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: tokens.borderColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 12,
                height: 12,
                decoration: const BoxDecoration(
                    color: Color(0xFFEF4444), shape: BoxShape.circle),
              ),
              const SizedBox(width: 6),
              Container(
                width: 12,
                height: 12,
                decoration: const BoxDecoration(
                    color: Color(0xFFF59E0B), shape: BoxShape.circle),
              ),
              const SizedBox(width: 6),
              Container(
                width: 12,
                height: 12,
                decoration: const BoxDecoration(
                    color: Color(0xFF10B981), shape: BoxShape.circle),
              ),
              const Spacer(),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: tokens.primaryColor.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  'Live Counter',
                  style: TextStyle(
                    color: tokens.primaryColor,
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: cardInnerBg,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Today\'s Revenue',
                          style: TextStyle(
                              color: tokens.textSecondary, fontSize: 11)),
                      const SizedBox(height: 4),
                      Text('\$3,842.50',
                          style: TextStyle(
                              color: tokens.textPrimary,
                              fontSize: 18,
                              fontWeight: FontWeight.bold)),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: cardInnerBg,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Completed Orders',
                          style: TextStyle(
                              color: tokens.textSecondary, fontSize: 11)),
                      const SizedBox(height: 4),
                      Text('184 sales',
                          style: TextStyle(
                              color: tokens.textPrimary,
                              fontSize: 18,
                              fontWeight: FontWeight.bold)),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          Expanded(
            child: Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: cardInnerBg.withValues(alpha: 0.7),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: tokens.borderColor),
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.point_of_sale,
                      size: 48, color: tokens.primaryColor),
                  const SizedBox(height: 10),
                  Text(
                    'High-Speed Omnichannel POS Engine',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                        color: tokens.textPrimary,
                        fontWeight: FontWeight.bold,
                        fontSize: 14),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Barcode Scanning · Cash Register · WhatsApp Digital Receipts',
                    textAlign: TextAlign.center,
                    style:
                        TextStyle(color: tokens.textSecondary, fontSize: 11),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHardwareBar(
    BuildContext context,
    List<LandingHardwareItem> hardware,
    _LandingThemeTokens tokens,
  ) {
    if (hardware.isEmpty) return const SizedBox.shrink();

    return Container(
      key: _hardwareKey,
      width: double.infinity,
      color: tokens.sectionAltBg,
      padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 20),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 1200),
          child: Column(
            children: [
              Text(
                'COMPATIBLE WITH STANDARD RETAIL & RESTAURANT HARDWARE',
                style: TextStyle(
                  color: tokens.primaryColor,
                  fontWeight: FontWeight.w700,
                  fontSize: 11,
                  letterSpacing: 1.2,
                ),
              ),
              const SizedBox(height: 18),
              Wrap(
                spacing: 16,
                runSpacing: 12,
                alignment: WrapAlignment.center,
                children: hardware.map((item) {
                  return Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 16, vertical: 10),
                    decoration: BoxDecoration(
                      color: tokens.surfaceCard,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: tokens.borderColor),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(item.icon, style: const TextStyle(fontSize: 18)),
                        const SizedBox(width: 8),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.title,
                              style: TextStyle(
                                color: tokens.textPrimary,
                                fontWeight: FontWeight.bold,
                                fontSize: 12,
                              ),
                            ),
                            Text(
                              item.tag,
                              style: TextStyle(
                                color: tokens.primaryColor,
                                fontSize: 10,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  );
                }).toList(),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStatsSection(
    BuildContext context,
    List<LandingStatItem> stats,
    _LandingThemeTokens tokens,
  ) {
    if (stats.isEmpty) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 48, horizontal: 20),
      color: tokens.scaffoldBg,
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 1100),
          child: LayoutBuilder(
            builder: (ctx, constraints) {
              final isWide = constraints.maxWidth >= 720;
              return Wrap(
                spacing: 24,
                runSpacing: 20,
                alignment: WrapAlignment.center,
                children: stats.map((s) {
                  return Container(
                    width: isWide
                        ? (constraints.maxWidth - 72) / 4
                        : (constraints.maxWidth - 24) / 2,
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: tokens.surfaceCard,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: tokens.borderColor),
                    ),
                    child: Column(
                      children: [
                        Text(
                          s.value,
                          style: TextStyle(
                            color: tokens.primaryColor,
                            fontSize: 32,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          s.label,
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: tokens.textSecondary,
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  );
                }).toList(),
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _buildSolutionsSection(
    BuildContext context,
    List<LandingSolutionItem> solutions,
    _LandingThemeTokens tokens,
  ) {
    return Container(
      key: _solutionsKey,
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 64, horizontal: 20),
      color: tokens.sectionAltBg,
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 1200),
          child: Column(
            children: [
              _buildSectionHeader(
                badge: 'TAILORED SOLUTIONS',
                title: 'Engineered for Every Business Vertical',
                subtitle:
                    'Specialized workflows that fit the exact operational model of your store.',
                tokens: tokens,
              ),
              const SizedBox(height: 48),
              LayoutBuilder(
                builder: (ctx, constraints) {
                  final isWide = constraints.maxWidth >= 900;
                  final itemWidth = isWide
                      ? (constraints.maxWidth - 48) / 2
                      : constraints.maxWidth;
                  return Wrap(
                    spacing: 24,
                    runSpacing: 24,
                    children: solutions.map((sol) {
                      return Container(
                        width: itemWidth,
                        padding: const EdgeInsets.all(24),
                        decoration: BoxDecoration(
                          color: tokens.surfaceCard,
                          borderRadius: BorderRadius.circular(18),
                          border: Border.all(color: tokens.borderColor),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color:
                                    tokens.primaryColor.withValues(alpha: 0.15),
                                borderRadius: BorderRadius.circular(14),
                              ),
                              child: Text(
                                sol.icon,
                                style: const TextStyle(fontSize: 26),
                              ),
                            ),
                            const SizedBox(width: 16),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    sol.title,
                                    style: TextStyle(
                                      color: tokens.textPrimary,
                                      fontWeight: FontWeight.bold,
                                      fontSize: 17,
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    sol.desc,
                                    style: TextStyle(
                                      color: tokens.textSecondary,
                                      fontSize: 13,
                                      height: 1.5,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      );
                    }).toList(),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildFeaturesSection(
    BuildContext context,
    List<LandingFeatureItem> features,
    _LandingThemeTokens tokens,
  ) {
    return Container(
      key: _featuresKey,
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 64, horizontal: 20),
      color: tokens.scaffoldBg,
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 1200),
          child: Column(
            children: [
              _buildSectionHeader(
                badge: 'POWERFUL CAPABILITIES',
                title: 'Everything You Need to Run & Scale',
                subtitle:
                    'Cutting-edge omnichannel sales, real-time inventory, and offline-first peace of mind.',
                tokens: tokens,
              ),
              const SizedBox(height: 48),
              LayoutBuilder(
                builder: (ctx, constraints) {
                  final isDesktop = constraints.maxWidth >= 960;
                  final isTablet = constraints.maxWidth >= 640;
                  final cardWidth = isDesktop
                      ? (constraints.maxWidth - 48) / 3
                      : (isTablet
                          ? (constraints.maxWidth - 20) / 2
                          : constraints.maxWidth);

                  return Wrap(
                    spacing: 20,
                    runSpacing: 20,
                    children: features.map((feat) {
                      return Container(
                        width: cardWidth,
                        padding: const EdgeInsets.all(24),
                        decoration: BoxDecoration(
                          color: tokens.surfaceCard,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: tokens.borderColor),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color:
                                    tokens.primaryColor.withValues(alpha: 0.15),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Text(
                                feat.icon,
                                style: const TextStyle(fontSize: 22),
                              ),
                            ),
                            const SizedBox(height: 16),
                            Text(
                              feat.title,
                              style: TextStyle(
                                color: tokens.textPrimary,
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              feat.body,
                              style: TextStyle(
                                color: tokens.textSecondary,
                                fontSize: 13,
                                height: 1.5,
                              ),
                            ),
                          ],
                        ),
                      );
                    }).toList(),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildPricingSection(
    BuildContext context,
    List<LandingPlanItem> plans,
    _LandingThemeTokens tokens,
  ) {
    if (plans.isEmpty) return const SizedBox.shrink();

    return Container(
      key: _pricingKey,
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 64, horizontal: 20),
      color: tokens.sectionAltBg,
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 1200),
          child: Column(
            children: [
              _buildSectionHeader(
                badge: 'TRANSPARENT PRICING',
                title: 'Simple Plans for Businesses of Any Size',
                subtitle:
                    'Zero hidden setup fees. Upgrade, downgrade, or cancel anytime.',
                tokens: tokens,
              ),
              const SizedBox(height: 48),
              LayoutBuilder(
                builder: (ctx, constraints) {
                  final isWide = constraints.maxWidth >= 900;
                  final cardWidth = isWide
                      ? (constraints.maxWidth - 48) /
                          math.max(1, math.min(plans.length, 3))
                      : constraints.maxWidth;

                  return Wrap(
                    spacing: 24,
                    runSpacing: 24,
                    alignment: WrapAlignment.center,
                    children: plans.map((plan) {
                      return Container(
                        width: cardWidth,
                        padding: const EdgeInsets.all(28),
                        decoration: BoxDecoration(
                          color: tokens.surfaceCard,
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(
                            color: plan.isFeatured
                                ? tokens.primaryColor
                                : tokens.borderColor,
                            width: plan.isFeatured ? 2 : 1,
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: plan.isFeatured
                                  ? tokens.primaryColor.withValues(alpha: 0.2)
                                  : Colors.black.withValues(
                                      alpha: tokens.isDark ? 0.3 : 0.05),
                              blurRadius: 20,
                              offset: const Offset(0, 8),
                            ),
                          ],
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (plan.isFeatured) ...[
                              Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 10, vertical: 4),
                                decoration: BoxDecoration(
                                  color: tokens.primaryColor,
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: const Text(
                                  'MOST POPULAR',
                                  style: TextStyle(
                                    color: Colors.white,
                                    fontSize: 10,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                              const SizedBox(height: 12),
                            ],
                            Text(
                              plan.name.toUpperCase(),
                              style: TextStyle(
                                color: tokens.textPrimary,
                                fontWeight: FontWeight.bold,
                                fontSize: 18,
                              ),
                            ),
                            if (plan.description != null &&
                                plan.description!.isNotEmpty) ...[
                              const SizedBox(height: 4),
                              Text(
                                plan.description!,
                                style: TextStyle(
                                  color: tokens.textSecondary,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                            const SizedBox(height: 20),
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.baseline,
                              textBaseline: TextBaseline.alphabetic,
                              children: [
                                Text(
                                  '${plan.currency == 'INR' ? '₹' : (plan.currency == 'EUR' ? '€' : (plan.currency == 'GBP' ? '£' : '\$'))}${plan.price.toStringAsFixed(plan.price.truncateToDouble() == plan.price ? 0 : 2)}',
                                  style: TextStyle(
                                    color: tokens.textPrimary,
                                    fontSize: 36,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                const SizedBox(width: 4),
                                Text(
                                  '/${plan.billingPeriod}',
                                  style: TextStyle(
                                    color: tokens.textSecondary,
                                    fontSize: 14,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 16),
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: [
                                _buildLimitChip(
                                  icon: Icons.receipt_long_outlined,
                                  label: plan.invoiceLimit == -1
                                      ? 'Unlimited Invoices'
                                      : '${plan.invoiceLimit} Invoices/mo',
                                  tokens: tokens,
                                ),
                                _buildLimitChip(
                                  icon: Icons.inventory_2_outlined,
                                  label: plan.productsLimit == -1
                                      ? 'Unlimited Products'
                                      : '${plan.productsLimit} Products',
                                  tokens: tokens,
                                ),
                                _buildLimitChip(
                                  icon: Icons.devices_outlined,
                                  label: plan.deviceLimit == -1
                                      ? 'Unlimited POS Devices'
                                      : '${plan.deviceLimit} Devices',
                                  tokens: tokens,
                                ),
                                _buildLimitChip(
                                  icon: Icons.people_outline,
                                  label: plan.staffLimit == -1
                                      ? 'Unlimited Staff'
                                      : '${plan.staffLimit} Staff',
                                  tokens: tokens,
                                ),
                              ],
                            ),
                            if (plan.extensions.isNotEmpty) ...[
                              const SizedBox(height: 14),
                              Wrap(
                                spacing: 6,
                                runSpacing: 6,
                                children: plan.extensions.map((ext) {
                                  final label = _formatExtensionName(ext);
                                  return Container(
                                    padding: const EdgeInsets.symmetric(
                                        horizontal: 8, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: tokens.accentColor
                                          .withValues(alpha: 0.12),
                                      borderRadius: BorderRadius.circular(6),
                                      border: Border.all(
                                        color: tokens.accentColor
                                            .withValues(alpha: 0.28),
                                      ),
                                    ),
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Icon(
                                          Icons.extension_outlined,
                                          size: 11,
                                          color: tokens.accentColor,
                                        ),
                                        const SizedBox(width: 4),
                                        Text(
                                          label,
                                          style: TextStyle(
                                            color: tokens.accentColor,
                                            fontSize: 11,
                                            fontWeight: FontWeight.w700,
                                          ),
                                        ),
                                      ],
                                    ),
                                  );
                                }).toList(),
                              ),
                            ],
                            const SizedBox(height: 20),
                            Divider(color: tokens.borderColor),
                            const SizedBox(height: 16),
                            ...plan.features.map((f) => Padding(
                                  padding: const EdgeInsets.only(bottom: 10),
                                  child: Row(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Icon(Icons.check_circle,
                                          color: tokens.primaryColor,
                                          size: 16),
                                      const SizedBox(width: 10),
                                      Expanded(
                                        child: Text(
                                          f,
                                          style: TextStyle(
                                            color: tokens.textSecondary,
                                            fontSize: 13,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                )),
                            const SizedBox(height: 24),
                            SizedBox(
                              width: double.infinity,
                              child: FilledButton(
                                style: FilledButton.styleFrom(
                                  backgroundColor: plan.isFeatured
                                      ? tokens.primaryColor
                                      : (tokens.isDark
                                          ? const Color(0xFF334155)
                                          : const Color(0xFFE2E8F0)),
                                  foregroundColor: plan.isFeatured
                                      ? Colors.white
                                      : (tokens.isDark
                                          ? Colors.white
                                          : const Color(0xFF0F172A)),
                                  padding:
                                      const EdgeInsets.symmetric(vertical: 14),
                                  shape: RoundedRectangleBorder(
                                      borderRadius:
                                          BorderRadius.circular(10)),
                                ),
                                onPressed: () => _navigateToAuth(context),
                                child: Text(
                                  plan.isFeatured
                                      ? 'Get Started Now'
                                      : 'Select Plan',
                                  style: const TextStyle(
                                      fontWeight: FontWeight.bold),
                                ),
                              ),
                            ),
                          ],
                        ),
                      );
                    }).toList(),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildTestimonialsSection(
    BuildContext context,
    List<LandingTestimonialItem> testimonials,
    _LandingThemeTokens tokens,
  ) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 64, horizontal: 20),
      color: tokens.scaffoldBg,
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 1200),
          child: Column(
            children: [
              _buildSectionHeader(
                badge: 'SOCIAL PROOF',
                title: 'Trusted by Leading Merchants Globally',
                subtitle:
                    'Hear how store owners streamline their business daily.',
                tokens: tokens,
              ),
              const SizedBox(height: 48),
              LayoutBuilder(
                builder: (ctx, constraints) {
                  final isWide = constraints.maxWidth >= 900;
                  final cardWidth = isWide
                      ? (constraints.maxWidth - 48) / 3
                      : constraints.maxWidth;

                  return Wrap(
                    spacing: 24,
                    runSpacing: 24,
                    children: testimonials.map((t) {
                      return Container(
                        width: cardWidth,
                        padding: const EdgeInsets.all(24),
                        decoration: BoxDecoration(
                          color: tokens.surfaceCard,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: tokens.borderColor),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: List.generate(
                                5,
                                (i) => const Icon(Icons.star,
                                    color: Color(0xFFF59E0B), size: 16),
                              ),
                            ),
                            const SizedBox(height: 14),
                            Text(
                              '"${t.quote}"',
                              style: TextStyle(
                                color: tokens.textSecondary,
                                fontSize: 13,
                                height: 1.5,
                                fontStyle: FontStyle.italic,
                              ),
                            ),
                            const SizedBox(height: 20),
                            Row(
                              children: [
                                CircleAvatar(
                                  radius: 16,
                                  backgroundColor: tokens.primaryColor
                                      .withValues(alpha: 0.2),
                                  child: Text(
                                    t.name.isNotEmpty ? t.name[0] : 'U',
                                    style: TextStyle(
                                      color: tokens.primaryColor,
                                      fontWeight: FontWeight.bold,
                                      fontSize: 12,
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        t.name,
                                        style: TextStyle(
                                          color: tokens.textPrimary,
                                          fontWeight: FontWeight.bold,
                                          fontSize: 13,
                                        ),
                                      ),
                                      Text(
                                        t.role,
                                        style: TextStyle(
                                          color: tokens.textSecondary,
                                          fontSize: 11,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      );
                    }).toList(),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildFaqSection(
    BuildContext context,
    List<LandingFaqItem> faqs,
    _LandingThemeTokens tokens,
  ) {
    if (faqs.isEmpty) return const SizedBox.shrink();

    return Container(
      key: _faqsKey,
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 64, horizontal: 20),
      color: tokens.sectionAltBg,
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 800),
          child: Column(
            children: [
              _buildSectionHeader(
                badge: 'QUESTIONS & ANSWERS',
                title: 'Frequently Asked Questions',
                subtitle:
                    'Have inquiries before starting? Find quick answers right here.',
                tokens: tokens,
              ),
              const SizedBox(height: 40),
              ...faqs.map((faq) {
                return Container(
                  margin: const EdgeInsets.only(bottom: 14),
                  decoration: BoxDecoration(
                    color: tokens.surfaceCard,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: tokens.borderColor),
                  ),
                  child: Theme(
                    data: Theme.of(context).copyWith(
                      dividerColor: Colors.transparent,
                    ),
                    child: ExpansionTile(
                      iconColor: tokens.primaryColor,
                      collapsedIconColor: tokens.textSecondary,
                      title: Text(
                        faq.question,
                        style: TextStyle(
                          color: tokens.textPrimary,
                          fontWeight: FontWeight.w700,
                          fontSize: 14,
                        ),
                      ),
                      children: [
                        Padding(
                          padding: const EdgeInsets.only(
                              left: 16, right: 16, bottom: 18),
                          child: Text(
                            faq.answer,
                            style: TextStyle(
                              color: tokens.textSecondary,
                              fontSize: 13,
                              height: 1.5,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              }),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDownloadsSection(
    BuildContext context,
    LandingDownloads downloads,
    _LandingThemeTokens tokens,
  ) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 54, horizontal: 20),
      color: tokens.scaffoldBg,
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 900),
          child: Container(
            padding: const EdgeInsets.all(36),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  tokens.surfaceCard,
                  tokens.primaryColor.withValues(alpha: 0.15),
                ],
              ),
              borderRadius: BorderRadius.circular(24),
              border: Border.all(
                  color: tokens.primaryColor.withValues(alpha: 0.3)),
            ),
            child: Column(
              children: [
                Icon(Icons.install_mobile,
                    size: 40, color: tokens.primaryColor),
                const SizedBox(height: 14),
                Text(
                  'Download Native Counter Apps',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: tokens.textPrimary,
                    fontWeight: FontWeight.bold,
                    fontSize: 22,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Experience blazing-fast offline performance with direct thermal printer and scanner hardware drivers.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: tokens.textSecondary, fontSize: 13),
                ),
                const SizedBox(height: 24),
                Wrap(
                  spacing: 16,
                  runSpacing: 12,
                  alignment: WrapAlignment.center,
                  children: [
                    if (downloads.playstoreEnabled &&
                        downloads.playstoreUrl != null)
                      FilledButton.icon(
                        style: FilledButton.styleFrom(
                          backgroundColor: const Color(0xFF10B981),
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(
                              horizontal: 20, vertical: 14),
                          shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10)),
                        ),
                        icon: const Icon(Icons.android),
                        label: const Text('Download Android APK',
                            style: TextStyle(fontWeight: FontWeight.bold)),
                        onPressed: () =>
                            _launchExternalUrl(downloads.playstoreUrl),
                      ),
                    if (downloads.windowsEnabled &&
                        downloads.windowsUrl != null)
                      FilledButton.icon(
                        style: FilledButton.styleFrom(
                          backgroundColor: const Color(0xFF38BDF8),
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(
                              horizontal: 20, vertical: 14),
                          shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10)),
                        ),
                        icon: const Icon(Icons.desktop_windows),
                        label: const Text('Download Windows App',
                            style: TextStyle(fontWeight: FontWeight.bold)),
                        onPressed: () =>
                            _launchExternalUrl(downloads.windowsUrl),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildContactSection(
    BuildContext context,
    LandingContact contact,
    _LandingThemeTokens tokens,
  ) {
    return Container(
      key: _contactKey,
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 64, horizontal: 20),
      color: tokens.sectionAltBg,
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 1000),
          child: Column(
            children: [
              _buildSectionHeader(
                badge: 'CONTACT & INQUIRIES',
                title: contact.pageTitle.isNotEmpty
                    ? contact.pageTitle
                    : 'Connect with Our Sales & Support Team',
                subtitle: contact.pageSubtitle.isNotEmpty
                    ? contact.pageSubtitle
                    : 'Have questions before signing up? Send us a message and our team will get in touch.',
                tokens: tokens,
              ),
              const SizedBox(height: 36),
              _buildContactCardsGrid(contact, tokens),
              const SizedBox(height: 36),
              InteractiveContactForm(
                contact: contact,
                isDark: tokens.isDark,
                surfaceCard: tokens.surfaceCard,
                borderColor: tokens.borderColor,
                textPrimary: tokens.textPrimary,
                textSecondary: tokens.textSecondary,
                primaryColor: tokens.primaryColor,
                accentColor: tokens.accentColor,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildContactCardsGrid(
    LandingContact contact,
    _LandingThemeTokens tokens,
  ) {
    final items = [
      (
        icon: Icons.location_on_outlined,
        title: 'Head Office',
        content: contact.headOfficeAddress.isNotEmpty
            ? contact.headOfficeAddress
            : 'Metrotech Center, NY 11201',
      ),
      (
        icon: Icons.phone_in_talk_outlined,
        title: 'Call Center',
        content: contact.supportPhone.isNotEmpty
            ? contact.supportPhone
            : '+1 (555) 019-2834',
      ),
      (
        icon: Icons.email_outlined,
        title: 'Email',
        content: contact.supportEmail.isNotEmpty
            ? contact.supportEmail
            : 'support@zoomnearby.com',
      ),
      (
        icon: Icons.access_time_rounded,
        title: 'Working Hours',
        content: contact.workingHours.isNotEmpty
            ? contact.workingHours
            : 'Monday - Friday (07 am - 05 pm)',
      ),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 600;
        final cardWidth = isMobile
            ? constraints.maxWidth
            : (constraints.maxWidth - 16) / 2;

        return Wrap(
          spacing: 16,
          runSpacing: 16,
          children: items.map((item) {
            return SizedBox(
              width: cardWidth,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
                decoration: BoxDecoration(
                  color: tokens.surfaceCard,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: tokens.borderColor),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: tokens.isDark ? 0.25 : 0.04),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: tokens.primaryColor.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Icon(
                        item.icon,
                        size: 22,
                        color: tokens.primaryColor,
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            item.title,
                            style: TextStyle(
                              color: tokens.textPrimary,
                              fontWeight: FontWeight.w700,
                              fontSize: 14,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            item.content,
                            style: TextStyle(
                              color: tokens.textSecondary,
                              fontSize: 13,
                              height: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            );
          }).toList(),
        );
      },
    );
  }

  Widget _buildFooter(
    BuildContext context,
    LandingBranding branding,
    _LandingThemeTokens tokens,
  ) {
    return Container(
      width: double.infinity,
      color: tokens.isDark ? const Color(0xFF080C16) : const Color(0xFFE2E8F0),
      padding: const EdgeInsets.symmetric(vertical: 36, horizontal: 20),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 1200),
          child: Column(
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (branding.logoUrl != null &&
                      branding.logoUrl!.isNotEmpty) ...[
                    ClipRRect(
                      borderRadius: BorderRadius.circular(6),
                      child: AppNetworkImage(
                        imageUrl: branding.logoUrl,
                        width: 24,
                        height: 24,
                        fit: BoxFit.contain,
                        fallbackIcon: Icons.storefront,
                      ),
                    ),
                    const SizedBox(width: 8),
                  ],
                  Text(
                    branding.platformName,
                    style: TextStyle(
                      color: tokens.textPrimary,
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                '© ${DateTime.now().year} ${branding.platformName}. All rights reserved. Cloud & Offline Enterprise POS Architecture.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: tokens.textSecondary.withValues(alpha: 0.8),
                  fontSize: 11,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSectionHeader({
    required String badge,
    required String title,
    required String subtitle,
    required _LandingThemeTokens tokens,
  }) {
    return Column(
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
          decoration: BoxDecoration(
            color: tokens.primaryColor.withValues(alpha: 0.15),
            borderRadius: BorderRadius.circular(16),
          ),
          child: Text(
            badge,
            style: TextStyle(
              color: tokens.primaryColor,
              fontWeight: FontWeight.bold,
              fontSize: 11,
              letterSpacing: 1.1,
            ),
          ),
        ),
        const SizedBox(height: 12),
        Text(
          title,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: tokens.textPrimary,
            fontWeight: FontWeight.w900,
            fontSize: 28,
            letterSpacing: -0.5,
          ),
        ),
        const SizedBox(height: 8),
        ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 600),
          child: Text(
            subtitle,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: tokens.textSecondary,
              fontSize: 14,
              height: 1.4,
            ),
          ),
        ),
      ],
    );
  }
}
