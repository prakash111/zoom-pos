import 'package:flutter/material.dart';

import 'auth_illustration.dart';

/// Split-screen shell shared by every pre-login screen (login, register,
/// forgot-password, OTP), matching the reference "E-Inventory" design:
///
///  * a large brand wordmark + tagline in the top-left,
///  * an elevated white card holding the screen's heading and form,
///  * on desktop widths, a branded illustration panel on the right.
///
/// On narrow (phone) widths it collapses to a single scrolling column and
/// the illustration is dropped. Screens only supply their [heading],
/// optional [subheading] and the [form] body — all auth logic stays in the
/// screen.
class AuthScaffold extends StatelessWidget {
  const AuthScaffold({
    super.key,
    required this.heading,
    required this.form,
    this.subheading,
    this.headerIcon,
    this.belowCard,
    this.onServerSettings,
    this.maxCardWidth = 460,
  });

  final String heading;
  final String? subheading;
  final IconData? headerIcon;
  final Widget form;

  /// Extra content rendered under the card (e.g. "Don't have a store yet?").
  final Widget? belowCard;

  /// Opens the server-address screen from a discreet top-right button.
  final VoidCallback? onServerSettings;

  final double maxCardWidth;

  static const _pageBg = Color(0xFFEDEFF3);
  static const _ink = Color(0xFF0F172A);
  static const _muted = Color(0xFF64748B);

  Widget _brand(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'Sales & Inventory',
          style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                fontWeight: FontWeight.w800,
                color: _ink,
                letterSpacing: -0.5,
              ),
        ),
        const SizedBox(height: 4),
        const Text(
          'Online inventory management system',
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w500,
            color: _muted,
          ),
        ),
      ],
    );
  }

  Widget _card(BuildContext context) {
    final canPop = Navigator.of(context).canPop();
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 40,
            offset: const Offset(0, 18),
          ),
        ],
      ),
      padding: const EdgeInsets.fromLTRB(30, 28, 30, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              if (canPop)
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
                  child: Icon(headerIcon,
                      size: 26, color: Theme.of(context).colorScheme.primary),
                ),
              Expanded(
                child: Text(
                  heading,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: _ink,
                      ),
                ),
              ),
            ],
          ),
          if (subheading != null) ...[
            const SizedBox(height: 8),
            Text(
              subheading!,
              style:
                  const TextStyle(fontSize: 13.5, color: _muted, height: 1.45),
            ),
          ],
          const SizedBox(height: 22),
          form,
        ],
      ),
    );
  }

  Widget _formColumn(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(28, 36, 28, 40),
      child: Center(
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: maxCardWidth),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              Align(alignment: Alignment.centerLeft, child: _brand(context)),
              const SizedBox(height: 34),
              _card(context),
              if (belowCard != null) ...[
                const SizedBox(height: 22),
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
    return Scaffold(
      backgroundColor: _pageBg,
      body: SafeArea(
        child: Stack(
          children: [
            LayoutBuilder(
              builder: (context, constraints) {
                final wide = constraints.maxWidth >= 900;
                if (!wide) {
                  return _formColumn(context);
                }
                return Center(
                  child: Container(
                    margin: const EdgeInsets.all(24),
                    constraints:
                        const BoxConstraints(maxWidth: 1180, maxHeight: 820),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(28),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.08),
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
            if (onServerSettings != null)
              Positioned(
                top: 8,
                right: 12,
                child: IconButton(
                  tooltip: 'Server address',
                  icon: const Icon(Icons.dns_outlined, color: _muted),
                  onPressed: onServerSettings,
                ),
              ),
          ],
        ),
      ),
    );
  }
}
