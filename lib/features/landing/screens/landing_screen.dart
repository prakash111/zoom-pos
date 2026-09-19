import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/api/api_client.dart';
import '../../../core/widgets/app_network_image.dart';
import '../../auth/auth_provider.dart';
import '../../auth/screens/login_screen.dart';
import '../../dashboard/dashboard_screen.dart';
import '../models/landing_data.dart';
import '../services/landing_provider.dart';

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

  Future<void> _launchWhatsApp(String phone) async {
    final digits = phone.replaceAll(RegExp(r'[^0-9]'), '');
    final url = Uri.parse(
        'https://wa.me/$digits?text=${Uri.encodeComponent('Hello, I would like to learn more about ZoomPOS.')}');
    if (await canLaunchUrl(url)) {
      await launchUrl(url, mode: LaunchMode.externalApplication);
    }
  }

  Future<void> _launchCall(String phone) async {
    final clean = phone.replaceAll(RegExp(r'[^0-9+]'), '');
    final url = Uri.parse('tel:$clean');
    if (await canLaunchUrl(url)) {
      await launchUrl(url);
    }
  }

  Future<void> _launchEmail(String email) async {
    final url = Uri.parse(
        'mailto:$email?subject=${Uri.encodeComponent('ZoomPOS Enterprise Inquiry')}');
    if (await canLaunchUrl(url)) {
      await launchUrl(url);
    }
  }

  Future<void> _launchExternalUrl(String? urlString) async {
    if (urlString == null || urlString.isEmpty) return;
    final uri = Uri.tryParse(urlString);
    if (uri != null && await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider<LandingProvider>.value(
      value: _landingProvider,
      child: Consumer<LandingProvider>(
        builder: (context, provider, _) {
          final data = provider.data;
          final branding = data.branding;
          final hero = data.hero;

          final primaryColor = _parseColor(
              branding.primaryColorHex, const Color(0xFF10B981));
          final accentColor = _parseColor(
              branding.accentColorHex, const Color(0xFF38BDF8));
          final bgDark = _parseColor(
              branding.darkBgHex, const Color(0xFF0B1120));

          return Scaffold(
            backgroundColor: bgDark,
            appBar: _buildTopNav(context, data, primaryColor),
            endDrawer: _buildMobileDrawer(context, data, primaryColor),
            body: RefreshIndicator(
              color: primaryColor,
              backgroundColor: const Color(0xFF1E293B),
              onRefresh: () =>
                  _landingProvider.refresh(context.read<ApiClient>()),
              child: SingleChildScrollView(
                controller: _scrollController,
                child: Column(
                  children: [
                    _buildHeroSection(
                        context, hero, branding, primaryColor, accentColor),
                    _buildHardwareBar(context, data.hardware, primaryColor),
                    _buildStatsSection(context, data.stats, primaryColor),
                    _buildSolutionsSection(
                        context, data.solutions, primaryColor),
                    _buildFeaturesSection(context, data.features, primaryColor),
                    _buildPricingSection(
                        context, data.plans, primaryColor, accentColor),
                    if (data.testimonials.isNotEmpty)
                      _buildTestimonialsSection(
                          context, data.testimonials, primaryColor),
                    _buildFaqSection(context, data.faqs, primaryColor),
                    if (data.downloads.playstoreEnabled ||
                        data.downloads.windowsEnabled)
                      _buildDownloadsSection(
                          context, data.downloads, primaryColor),
                    _buildContactSection(context, data.contact, primaryColor),
                    _buildFooter(context, branding, primaryColor),
                  ],
                ),
              ),
            ),
            floatingActionButton: FloatingActionButton.extended(
              backgroundColor: const Color(0xFF25D366),
              foregroundColor: Colors.white,
              icon: const Icon(Icons.chat),
              label: const Text(
                'WhatsApp Support',
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
              onPressed: () => _launchWhatsApp(branding.supportWhatsapp),
            ),
          );
        },
      ),
    );
  }

  PreferredSizeWidget _buildTopNav(
      BuildContext context, LandingData data, Color primaryColor) {
    final branding = data.branding;
    final isDesktop = MediaQuery.sizeOf(context).width >= 960;

    return AppBar(
      backgroundColor: const Color(0xFF0B1120).withValues(alpha: 0.96),
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 2,
      shadowColor: Colors.black.withValues(alpha: 0.4),
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
                color: primaryColor.withValues(alpha: 0.2),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(Icons.storefront, color: primaryColor, size: 22),
            ),
          const SizedBox(width: 8),
          Flexible(
            child: Text(
              branding.platformName,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Colors.white,
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
          _navTextButton('Features', () => _scrollToSection(_featuresKey)),
          _navTextButton('Solutions', () => _scrollToSection(_solutionsKey)),
          _navTextButton('Hardware', () => _scrollToSection(_hardwareKey)),
          _navTextButton('Pricing', () => _scrollToSection(_pricingKey)),
          _navTextButton('FAQs', () => _scrollToSection(_faqsKey)),
          _navTextButton('Contact', () => _scrollToSection(_contactKey)),
          const SizedBox(width: 12),
          Padding(
            padding: const EdgeInsets.only(right: 20),
            child: FilledButton.icon(
              style: FilledButton.styleFrom(
                backgroundColor: primaryColor,
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
          IconButton(
            tooltip: 'Sign In',
            icon: const Icon(Icons.login, color: Colors.white),
            onPressed: () => _navigateToAuth(context),
          ),
          Builder(
            builder: (ctx) => IconButton(
              icon: const Icon(Icons.menu, color: Colors.white),
              onPressed: () => Scaffold.of(ctx).openEndDrawer(),
            ),
          ),
        ],
      ],
    );
  }

  Widget _navTextButton(String title, VoidCallback onTap) {
    return TextButton(
      onPressed: onTap,
      child: Text(
        title,
        style: TextStyle(
          color: Colors.white.withValues(alpha: 0.82),
          fontWeight: FontWeight.w600,
          fontSize: 14,
        ),
      ),
    );
  }

  Widget _buildMobileDrawer(
      BuildContext context, LandingData data, Color primaryColor) {
    final branding = data.branding;
    return Drawer(
      backgroundColor: const Color(0xFF0F172A),
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
                      color: primaryColor.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child:
                        Icon(Icons.storefront, color: primaryColor, size: 24),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      branding.platformName,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const Divider(color: Color(0xFF1E293B)),
            ListTile(
              leading: const Icon(Icons.star_outline, color: Colors.white70),
              title: const Text('Features',
                  style: TextStyle(color: Colors.white)),
              onTap: () {
                Navigator.pop(context);
                _scrollToSection(_featuresKey);
              },
            ),
            ListTile(
              leading: const Icon(Icons.business_outlined, color: Colors.white70),
              title: const Text('Solutions',
                  style: TextStyle(color: Colors.white)),
              onTap: () {
                Navigator.pop(context);
                _scrollToSection(_solutionsKey);
              },
            ),
            ListTile(
              leading:
                  const Icon(Icons.devices_outlined, color: Colors.white70),
              title: const Text('Hardware',
                  style: TextStyle(color: Colors.white)),
              onTap: () {
                Navigator.pop(context);
                _scrollToSection(_hardwareKey);
              },
            ),
            ListTile(
              leading: const Icon(Icons.price_check, color: Colors.white70),
              title:
                  const Text('Pricing', style: TextStyle(color: Colors.white)),
              onTap: () {
                Navigator.pop(context);
                _scrollToSection(_pricingKey);
              },
            ),
            ListTile(
              leading: const Icon(Icons.help_outline, color: Colors.white70),
              title: const Text('FAQs', style: TextStyle(color: Colors.white)),
              onTap: () {
                Navigator.pop(context);
                _scrollToSection(_faqsKey);
              },
            ),
            ListTile(
              leading:
                  const Icon(Icons.contact_support_outlined, color: Colors.white70),
              title:
                  const Text('Contact', style: TextStyle(color: Colors.white)),
              onTap: () {
                Navigator.pop(context);
                _scrollToSection(_contactKey);
              },
            ),
            const Spacer(),
            Padding(
              padding: const EdgeInsets.all(20),
              child: SizedBox(
                width: double.infinity,
                child: FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: primaryColor,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10)),
                  ),
                  onPressed: () {
                    Navigator.pop(context);
                    _navigateToAuth(context);
                  },
                  child: const Text(
                    'Sign In / Launch POS',
                    style: TextStyle(
                        fontWeight: FontWeight.bold, color: Colors.white),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHeroSection(
    BuildContext context,
    LandingHero hero,
    LandingBranding branding,
    Color primaryColor,
    Color accentColor,
  ) {
    final width = MediaQuery.sizeOf(context).width;
    final isDesktop = width >= 960;

    final bannerUrl = hero.bannerImageUrl?.isNotEmpty == true
        ? hero.bannerImageUrl
        : (branding.authBannerImageUrl?.isNotEmpty == true
            ? branding.authBannerImageUrl
            : null);

    return Container(
      width: double.infinity,
      padding: EdgeInsets.symmetric(
        horizontal: isDesktop ? 48 : 20,
        vertical: isDesktop ? 64 : 36,
      ),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            Color(0xFF0B1120),
            Color(0xFF0F172A),
          ],
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
                      child: _buildHeroCopy(
                          context, hero, primaryColor, accentColor),
                    ),
                    const SizedBox(width: 48),
                    Expanded(
                      flex: 5,
                      child: _buildHeroBanner(context, bannerUrl, primaryColor),
                    ),
                  ],
                )
              : Column(
                  children: [
                    _buildHeroCopy(context, hero, primaryColor, accentColor),
                    const SizedBox(height: 36),
                    _buildHeroBanner(context, bannerUrl, primaryColor),
                  ],
                ),
        ),
      ),
    );
  }

  Widget _buildHeroCopy(
    BuildContext context,
    LandingHero hero,
    Color primaryColor,
    Color accentColor,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
          decoration: BoxDecoration(
            color: primaryColor.withValues(alpha: 0.15),
            border: Border.all(color: primaryColor.withValues(alpha: 0.35)),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                hero.badge,
                style: TextStyle(
                  color: primaryColor,
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
          style: const TextStyle(
            color: Colors.white,
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
            color: Colors.white.withValues(alpha: 0.78),
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
                backgroundColor: primaryColor,
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
                foregroundColor: Colors.white,
                side: BorderSide(color: Colors.white.withValues(alpha: 0.3)),
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
            const Icon(Icons.check_circle, color: Color(0xFF10B981), size: 16),
            const SizedBox(width: 6),
            Text(
              'No credit card required',
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.65),
                fontSize: 12,
              ),
            ),
            const SizedBox(width: 16),
            const Icon(Icons.check_circle, color: Color(0xFF10B981), size: 16),
            const SizedBox(width: 6),
            Text(
              'Instant setup',
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.65),
                fontSize: 12,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildHeroBanner(
      BuildContext context, String? bannerUrl, Color primaryColor) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: primaryColor.withValues(alpha: 0.2),
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
            : _buildFallbackHeroGraphic(primaryColor),
      ),
    );
  }

  Widget _buildFallbackHeroGraphic(Color primaryColor) {
    return Container(
      height: 360,
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: const Color(0xFF131D2D),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFF1E293B)),
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
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: primaryColor.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  'Live Counter',
                  style: TextStyle(
                    color: primaryColor,
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
                    color: const Color(0xFF1E293B),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Today\'s Revenue',
                          style: TextStyle(color: Colors.white60, fontSize: 11)),
                      SizedBox(height: 4),
                      Text('\$3,842.50',
                          style: TextStyle(
                              color: Colors.white,
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
                    color: const Color(0xFF1E293B),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Completed Orders',
                          style: TextStyle(color: Colors.white60, fontSize: 11)),
                      SizedBox(height: 4),
                      Text('184 sales',
                          style: TextStyle(
                              color: Colors.white,
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
                color: const Color(0xFF1E293B).withValues(alpha: 0.6),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFF334155)),
              ),
              child: const Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.point_of_sale, size: 48, color: Color(0xFF10B981)),
                  SizedBox(height: 10),
                  Text(
                    'High-Speed Omnichannel POS Engine',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 14),
                  ),
                  SizedBox(height: 4),
                  Text(
                    'Barcode Scanning · Cash Register · WhatsApp Digital Receipts',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.white60, fontSize: 11),
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
    Color primaryColor,
  ) {
    if (hardware.isEmpty) return const SizedBox.shrink();

    return Container(
      key: _hardwareKey,
      width: double.infinity,
      color: const Color(0xFF0F172A),
      padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 20),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 1200),
          child: Column(
            children: [
              Text(
                'COMPATIBLE WITH STANDARD RETAIL & RESTAURANT HARDWARE',
                style: TextStyle(
                  color: primaryColor,
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
                    padding:
                        const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                    decoration: BoxDecoration(
                      color: const Color(0xFF1E293B),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFF334155)),
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
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                                fontSize: 12,
                              ),
                            ),
                            Text(
                              item.tag,
                              style: TextStyle(
                                color: primaryColor,
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
    Color primaryColor,
  ) {
    if (stats.isEmpty) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 48, horizontal: 20),
      color: const Color(0xFF0B1120),
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
                      color: const Color(0xFF131D2D),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFF1E293B)),
                    ),
                    child: Column(
                      children: [
                        Text(
                          s.value,
                          style: TextStyle(
                            color: primaryColor,
                            fontSize: 32,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          s.label,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: Colors.white70,
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
    Color primaryColor,
  ) {
    return Container(
      key: _solutionsKey,
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 64, horizontal: 20),
      color: const Color(0xFF0F172A),
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
                primaryColor: primaryColor,
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
                          color: const Color(0xFF131D2D),
                          borderRadius: BorderRadius.circular(18),
                          border: Border.all(color: const Color(0xFF1E293B)),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: primaryColor.withValues(alpha: 0.15),
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
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontWeight: FontWeight.bold,
                                      fontSize: 17,
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    sol.desc,
                                    style: TextStyle(
                                      color:
                                          Colors.white.withValues(alpha: 0.75),
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
    Color primaryColor,
  ) {
    return Container(
      key: _featuresKey,
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 64, horizontal: 20),
      color: const Color(0xFF0B1120),
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
                primaryColor: primaryColor,
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
                          color: const Color(0xFF131D2D),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: const Color(0xFF1E293B)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color: primaryColor.withValues(alpha: 0.15),
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
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              feat.body,
                              style: TextStyle(
                                color: Colors.white.withValues(alpha: 0.72),
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
    Color primaryColor,
    Color accentColor,
  ) {
    if (plans.isEmpty) return const SizedBox.shrink();

    return Container(
      key: _pricingKey,
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 64, horizontal: 20),
      color: const Color(0xFF0F172A),
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
                primaryColor: primaryColor,
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
                          color: plan.isFeatured
                              ? const Color(0xFF1E293B)
                              : const Color(0xFF131D2D),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(
                            color: plan.isFeatured
                                ? primaryColor
                                : const Color(0xFF1E293B),
                            width: plan.isFeatured ? 2 : 1,
                          ),
                          boxShadow: plan.isFeatured
                              ? [
                                  BoxShadow(
                                    color: primaryColor.withValues(alpha: 0.2),
                                    blurRadius: 20,
                                    offset: const Offset(0, 8),
                                  )
                                ]
                              : null,
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (plan.isFeatured) ...[
                              Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 10, vertical: 4),
                                decoration: BoxDecoration(
                                  color: primaryColor,
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
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                                fontSize: 18,
                              ),
                            ),
                            if (plan.description != null &&
                                plan.description!.isNotEmpty) ...[
                              const SizedBox(height: 4),
                              Text(
                                plan.description!,
                                style: const TextStyle(
                                  color: Colors.white60,
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
                                  '\$${plan.price.toStringAsFixed(plan.price.truncateToDouble() == plan.price ? 0 : 2)}',
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 36,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                const SizedBox(width: 4),
                                Text(
                                  '/${plan.billingPeriod}',
                                  style: const TextStyle(
                                    color: Colors.white60,
                                    fontSize: 14,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 24),
                            const Divider(color: Color(0xFF334155)),
                            const SizedBox(height: 16),
                            ...plan.features.map((f) => Padding(
                                  padding: const EdgeInsets.only(bottom: 10),
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Icon(Icons.check_circle,
                                          color: primaryColor, size: 16),
                                      const SizedBox(width: 10),
                                      Expanded(
                                        child: Text(
                                          f,
                                          style: const TextStyle(
                                            color: Colors.white70,
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
                                      ? primaryColor
                                      : const Color(0xFF334155),
                                  foregroundColor: Colors.white,
                                  padding:
                                      const EdgeInsets.symmetric(vertical: 14),
                                  shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(10)),
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
    Color primaryColor,
  ) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 64, horizontal: 20),
      color: const Color(0xFF0B1120),
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
                primaryColor: primaryColor,
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
                          color: const Color(0xFF131D2D),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: const Color(0xFF1E293B)),
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
                                color: Colors.white.withValues(alpha: 0.85),
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
                                  backgroundColor:
                                      primaryColor.withValues(alpha: 0.2),
                                  child: Text(
                                    t.name.isNotEmpty ? t.name[0] : 'U',
                                    style: TextStyle(
                                      color: primaryColor,
                                      fontWeight: FontWeight.bold,
                                      fontSize: 12,
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        t.name,
                                        style: const TextStyle(
                                          color: Colors.white,
                                          fontWeight: FontWeight.bold,
                                          fontSize: 13,
                                        ),
                                      ),
                                      Text(
                                        t.role,
                                        style: const TextStyle(
                                          color: Colors.white60,
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
    Color primaryColor,
  ) {
    if (faqs.isEmpty) return const SizedBox.shrink();

    return Container(
      key: _faqsKey,
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 64, horizontal: 20),
      color: const Color(0xFF0F172A),
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
                primaryColor: primaryColor,
              ),
              const SizedBox(height: 40),
              ...faqs.map((faq) {
                return Container(
                  margin: const EdgeInsets.only(bottom: 14),
                  decoration: BoxDecoration(
                    color: const Color(0xFF131D2D),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFF1E293B)),
                  ),
                  child: Theme(
                    data: Theme.of(context).copyWith(
                      dividerColor: Colors.transparent,
                    ),
                    child: ExpansionTile(
                      iconColor: primaryColor,
                      collapsedIconColor: Colors.white60,
                      title: Text(
                        faq.question,
                        style: const TextStyle(
                          color: Colors.white,
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
                              color: Colors.white.withValues(alpha: 0.78),
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
    Color primaryColor,
  ) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 54, horizontal: 20),
      color: const Color(0xFF0B1120),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 900),
          child: Container(
            padding: const EdgeInsets.all(36),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  const Color(0xFF131D2D),
                  primaryColor.withValues(alpha: 0.15),
                ],
              ),
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: primaryColor.withValues(alpha: 0.3)),
            ),
            child: Column(
              children: [
                const Icon(Icons.install_mobile,
                    size: 40, color: Colors.white),
                const SizedBox(height: 14),
                const Text(
                  'Download Native Counter Apps',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                    fontSize: 22,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Experience blazing-fast offline performance with direct thermal printer and scanner hardware drivers.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.white70, fontSize: 13),
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
                          backgroundColor: const Color(0xFF3B82F6),
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
    Color primaryColor,
  ) {
    return Container(
      key: _contactKey,
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 64, horizontal: 20),
      color: const Color(0xFF0F172A),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 900),
          child: Column(
            children: [
              _buildSectionHeader(
                badge: 'SUPPORT & SALES',
                title: contact.pageTitle,
                subtitle: contact.pageSubtitle,
                primaryColor: primaryColor,
              ),
              const SizedBox(height: 40),
              Container(
                padding: const EdgeInsets.all(28),
                decoration: BoxDecoration(
                  color: const Color(0xFF131D2D),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFF1E293B)),
                ),
                child: Column(
                  children: [
                    ListTile(
                      leading: Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color:
                              const Color(0xFF25D366).withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(Icons.chat, color: Color(0xFF25D366)),
                      ),
                      title: const Text('Direct WhatsApp Chat',
                          style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold)),
                      subtitle: Text(contact.supportWhatsapp,
                          style: const TextStyle(color: Colors.white70)),
                      trailing: FilledButton(
                        style: FilledButton.styleFrom(
                          backgroundColor: const Color(0xFF25D366),
                        ),
                        onPressed: () =>
                            _launchWhatsApp(contact.supportWhatsapp),
                        child: const Text('Chat Now'),
                      ),
                    ),
                    const Divider(color: Color(0xFF1E293B)),
                    ListTile(
                      leading: Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color:
                              const Color(0xFF3B82F6).withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child:
                            const Icon(Icons.phone, color: Color(0xFF3B82F6)),
                      ),
                      title: const Text('Phone Hotline',
                          style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold)),
                      subtitle: Text(contact.supportPhone,
                          style: const TextStyle(color: Colors.white70)),
                      trailing: OutlinedButton(
                        style: OutlinedButton.styleFrom(
                          foregroundColor: Colors.white,
                          side: const BorderSide(color: Color(0xFF334155)),
                        ),
                        onPressed: () => _launchCall(contact.supportPhone),
                        child: const Text('Call Us'),
                      ),
                    ),
                    const Divider(color: Color(0xFF1E293B)),
                    ListTile(
                      leading: Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color:
                              const Color(0xFF6366F1).withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(Icons.email_outlined,
                            color: Color(0xFF6366F1)),
                      ),
                      title: const Text('Email Help Desk',
                          style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold)),
                      subtitle: Text(contact.supportEmail,
                          style: const TextStyle(color: Colors.white70)),
                      trailing: OutlinedButton(
                        style: OutlinedButton.styleFrom(
                          foregroundColor: Colors.white,
                          side: const BorderSide(color: Color(0xFF334155)),
                        ),
                        onPressed: () => _launchEmail(contact.supportEmail),
                        child: const Text('Send Email'),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildFooter(
    BuildContext context,
    LandingBranding branding,
    Color primaryColor,
  ) {
    return Container(
      width: double.infinity,
      color: const Color(0xFF080C16),
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
                    style: const TextStyle(
                      color: Colors.white,
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
                style: const TextStyle(color: Colors.white38, fontSize: 11),
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
    required Color primaryColor,
  }) {
    return Column(
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
          decoration: BoxDecoration(
            color: primaryColor.withValues(alpha: 0.15),
            borderRadius: BorderRadius.circular(16),
          ),
          child: Text(
            badge,
            style: TextStyle(
              color: primaryColor,
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
          style: const TextStyle(
            color: Colors.white,
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
              color: Colors.white.withValues(alpha: 0.7),
              fontSize: 14,
              height: 1.4,
            ),
          ),
        ),
      ],
    );
  }

  Color _parseColor(String? hex, Color fallback) {
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
