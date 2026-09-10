import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/config/platform_branding_provider.dart';
import 'auth_illustration.dart';
import 'auth_widgets.dart';

/// Shell shared by every pre-login screen (login, register, forgot-password,
/// OTP), matching the "POS SYSTEMS" reference mock:
///
///  * a blue-tinted page with soft decorative shapes,
///  * a brand header — logo, a marketing headline ([brandHeadline]) and
///    subline ([brandSubline]) — sitting above
///  * an elevated white card holding the screen's [heading]/[subheading] and
///    [form] body.
///
/// On desktop widths a branded illustration panel is shown on the right.
class AuthScaffold extends StatelessWidget {
  const AuthScaffold({
    super.key,
    required this.form,
    this.heading,
    this.subheading,
    this.headerIcon,
    this.brandHeadline,
    this.brandSubline,
    this.headerTrailing,
    this.belowCard,
    this.onServerSettings,
    this.maxCardWidth = 460,
  });

  /// Card title ("Welcome back", "Create your account", "Forgot password?").
  final String? heading;
  final String? subheading;
  final IconData? headerIcon;

  /// Big marketing line above the card. Falls back to the platform name.
  final String? brandHeadline;

  /// Muted line under [brandHeadline]. Falls back to the platform tagline
  /// when the server enables it.
  final String? brandSubline;

  /// Optional action on the top-right of the header (e.g. "Back to login").
  final Widget? headerTrailing;

  final Widget form;

  /// Extra content rendered under the card.
  final Widget? belowCard;

  /// Opens the server-address screen from a discreet top-right button.
  final VoidCallback? onServerSettings;

  final double maxCardWidth;

  Widget _logo(PlatformBrandingProvider branding) => branding.hasLogo
      ? CachedNetworkImage(
          imageUrl: branding.brandLogoUrl!,
          height: 40,
          fit: BoxFit.contain,
          alignment: Alignment.centerLeft,
          errorWidget: (_, __, ___) => const SizedBox.shrink(),
        )
      : const SizedBox.shrink();

  Widget _brand(BuildContext context) {
    final branding = context.watch<PlatformBrandingProvider>();

    // Marketing header layout used by the login / register / reset screens.
    if (brandHeadline != null) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Expanded(child: _logo(branding)),
              if (headerTrailing != null) headerTrailing!,
            ],
          ),
          const SizedBox(height: 18),
          Text(
            brandHeadline!,
            style: const TextStyle(
              fontSize: 27,
              height: 1.15,
              fontWeight: FontWeight.w800,
              color: AuthColors.ink,
              letterSpacing: -0.5,
            ),
          ),
          if ((brandSubline ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              brandSubline!,
              style: const TextStyle(
                fontSize: 14.5,
                height: 1.4,
                fontWeight: FontWeight.w500,
                color: AuthColors.muted,
              ),
            ),
          ],
        ],
      );
    }

    // Legacy inline/stacked brand (logo + platform name + optional tagline),
    // driven by GET /auth/branding's `header_inline` / `show_tagline`.
    final title = Text(
      branding.platformName,
      style: Theme.of(context).textTheme.headlineMedium?.copyWith(
            fontWeight: FontWeight.w800,
            color: AuthColors.ink,
            letterSpacing: -0.5,
          ),
    );
    final logo = branding.hasLogo ? _logo(branding) : null;
    final header = branding.headerInline
        ? Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (logo != null) ...[logo, const SizedBox(width: 12)],
              Flexible(child: title),
            ],
          )
        : Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (logo != null)
                Padding(
                    padding: const EdgeInsets.only(bottom: 14), child: logo),
              title,
            ],
          );
    final showTagline =
        branding.showTagline && branding.tagline.trim().isNotEmpty;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        header,
        if (showTagline) ...[
          const SizedBox(height: 4),
          Text(
            branding.tagline,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w500,
              color: AuthColors.muted,
            ),
          ),
        ],
      ],
    );
  }

  Widget _card(BuildContext context) {
    final canPop = Navigator.of(context).canPop();
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0F172A).withValues(alpha: 0.08),
            blurRadius: 44,
            offset: const Offset(0, 20),
          ),
        ],
      ),
      padding: const EdgeInsets.fromLTRB(26, 26, 26, 30),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          if (heading != null) ...[
            Row(
              children: [
                if (canPop && brandHeadline == null)
                  Padding(
                    padding: const EdgeInsets.only(right: 6),
                    child: IconButton(
                      visualDensity: VisualDensity.compact,
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(),
                      icon: const Icon(Icons.arrow_back, size: 22),
                      onPressed: () => Navigator.of(context).maybePop(),
                    ),
                  ),
                if (headerIcon != null)
                  Padding(
                    padding: const EdgeInsets.only(right: 10),
                    child: Icon(headerIcon, size: 24, color: AuthColors.orange),
                  ),
                Expanded(
                  child: Text(
                    heading!,
                    style: const TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.w800,
                      color: AuthColors.ink,
                    ),
                  ),
                ),
              ],
            ),
            if (subheading != null) ...[
              const SizedBox(height: 6),
              Text(
                subheading!,
                style: const TextStyle(
                    fontSize: 13.5, color: AuthColors.muted, height: 1.45),
              ),
            ],
            const SizedBox(height: 22),
          ],
          form,
        ],
      ),
    );
  }

  Widget _formColumn(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(22, 30, 22, 40),
      child: Center(
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: maxCardWidth),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: _brand(context),
                ),
              ),
              const SizedBox(height: 26),
              _card(context),
              if (belowCard != null) ...[
                const SizedBox(height: 20),
                belowCard!,
              ],
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final brand = Theme.of(context).colorScheme.primary;
    final scheme = ColorScheme.fromSeed(
      seedColor: brand,
      brightness: Brightness.light,
    ).copyWith(
      primary: AuthColors.orange,
      surface: Colors.white,
      onSurface: AuthColors.ink,
      onSurfaceVariant: AuthColors.muted,
    );
    final theme = ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      colorScheme: scheme,
      scaffoldBackgroundColor: AuthColors.pageTop,
      textTheme: ThemeData(brightness: Brightness.light)
          .textTheme
          .apply(bodyColor: AuthColors.ink, displayColor: AuthColors.ink),
      inputDecorationTheme: const InputDecorationTheme(
        filled: true,
        fillColor: AuthColors.fieldFill,
        hintStyle: TextStyle(color: AuthColors.faint),
        labelStyle: TextStyle(color: AuthColors.muted),
      ),
      iconTheme: const IconThemeData(color: AuthColors.muted),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: AuthColors.link),
      ),
    );

    return Theme(
      data: theme,
      child: Scaffold(
        backgroundColor: AuthColors.pageTop,
        body: DecoratedBox(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [AuthColors.pageTop, AuthColors.pageBottom],
            ),
          ),
          child: Stack(
            children: [
              // Soft decorative shapes behind the content.
              Positioned(
                top: -90,
                right: -70,
                child: _blob(210, Colors.white.withValues(alpha: 0.55)),
              ),
              Positioned(
                bottom: -110,
                left: -80,
                child: _blob(240, Colors.white.withValues(alpha: 0.35)),
              ),
              SafeArea(
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    final wide = constraints.maxWidth >= 900;
                    if (!wide) return _formColumn(context);
                    return Center(
                      child: Container(
                        margin: const EdgeInsets.all(24),
                        constraints: const BoxConstraints(
                            maxWidth: 1180, maxHeight: 840),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(28),
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFF0F172A)
                                  .withValues(alpha: 0.10),
                              blurRadius: 60,
                              offset: const Offset(0, 24),
                            ),
                          ],
                        ),
                        clipBehavior: Clip.antiAlias,
                        child: Row(
                          children: [
                            Expanded(flex: 5, child: _formColumn(context)),
                            const Expanded(flex: 5, child: AuthIllustration()),
                          ],
                        ),
                      ),
                    );
                  },
                ),
              ),
              if (onServerSettings != null)
                Positioned(
                  top: 8,
                  right: 12,
                  child: SafeArea(
                    child: IconButton(
                      tooltip: 'Server address',
                      icon: const Icon(Icons.dns_outlined,
                          color: AuthColors.muted),
                      onPressed: onServerSettings,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  static Widget _blob(double size, Color color) => Container(
        width: size,
        height: size,
        decoration: BoxDecoration(color: color, shape: BoxShape.circle),
      );
}
