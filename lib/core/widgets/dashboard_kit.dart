import 'package:flutter/material.dart';

/// Visual primitives for the Windows desktop dashboard, styled after the
/// "POWRSALE" reference: a soft lavender page, floating white cards with a
/// large radius and a whisper-soft shadow, a violet hero banner, cyan pill
/// CTAs and circular-progress stats.
class PowrTokens {
  PowrTokens._();

  static const pageBg = Color(0xFFF4F5FB);
  static const card = Colors.white;
  static const ink = Color(0xFF1B1D28);
  static const muted = Color(0xFF8A8FA3);
  static const faint = Color(0xFFB6BAC9);
  static const line = Color(0xFFEDEEF5);

  static const primary = Color(0xFF23B7F0); // cyan CTA
  static const accent = Color(0xFF6D28D9); // violet
  static const accentSoft = Color(0xFF8B5CF6);
  static const coral = Color(0xFFFF7A6B);

  static const heroGradient = LinearGradient(
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
    colors: [Color(0xFF6D28D9), Color(0xFF4C1D95)],
  );

  static BorderRadius get radius => BorderRadius.circular(22);
  static BorderRadius get radiusSm => BorderRadius.circular(14);

  static List<BoxShadow> get shadow => [
        BoxShadow(
          color: const Color(0xFF6B7280).withValues(alpha: 0.10),
          blurRadius: 30,
          offset: const Offset(0, 14),
        ),
      ];
}

/// A floating white card.
class PowrCard extends StatelessWidget {
  const PowrCard({
    super.key,
    required this.child,
    this.title,
    this.trailing,
    this.padding = const EdgeInsets.all(20),
  });

  final Widget child;
  final String? title;
  final Widget? trailing;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: PowrTokens.card,
        borderRadius: PowrTokens.radius,
        boxShadow: PowrTokens.shadow,
      ),
      padding: padding,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          if (title != null || trailing != null) ...[
            Row(
              children: [
                if (title != null)
                  Expanded(
                    child: Text(title!,
                        style: const TextStyle(
                            fontSize: 14.5,
                            fontWeight: FontWeight.w800,
                            color: PowrTokens.ink)),
                  )
                else
                  const Spacer(),
                if (trailing != null) trailing!,
              ],
            ),
            const SizedBox(height: 16),
          ],
          child,
        ],
      ),
    );
  }
}

/// Bold page heading + optional trailing (the date-range pill).
class PowrPageHeader extends StatelessWidget {
  const PowrPageHeader(this.title, {super.key, this.trailing});

  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(title,
              style: const TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.w800,
                  color: PowrTokens.ink)),
        ),
        if (trailing != null) trailing!,
      ],
    );
  }
}

/// The "Jan – Feb, 2020 ▾" pill.
class PowrDateRangePill extends StatelessWidget {
  const PowrDateRangePill(this.label, {super.key, this.onTap});

  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: PowrTokens.card,
      borderRadius: PowrTokens.radiusSm,
      child: InkWell(
        borderRadius: PowrTokens.radiusSm,
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.calendar_today_outlined,
                size: 15, color: PowrTokens.accent),
            const SizedBox(width: 8),
            Text(label,
                style: const TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: PowrTokens.ink)),
            const SizedBox(width: 6),
            const Icon(Icons.keyboard_arrow_down,
                size: 16, color: PowrTokens.muted),
          ]),
        ),
      ),
    );
  }
}

/// Cyan pill primary button (SAVE / SEND REQUEST).
class PowrButton extends StatelessWidget {
  const PowrButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.filledColor,
    this.busy = false,
    this.dense = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final Color? filledColor;
  final bool busy;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    final bg = filledColor ?? PowrTokens.primary;
    final enabled = onPressed != null && !busy;
    return Opacity(
      opacity: enabled ? 1 : 0.55,
      child: Material(
        color: bg,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: enabled ? onPressed : null,
          child: Padding(
            padding: EdgeInsets.symmetric(
                horizontal: dense ? 16 : 24, vertical: dense ? 10 : 13),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                if (busy)
                  const SizedBox(
                    width: 15,
                    height: 15,
                    child: CircularProgressIndicator(
                        strokeWidth: 2, color: Colors.white),
                  )
                else ...[
                  if (icon != null) ...[
                    Icon(icon, size: 16, color: Colors.white),
                    const SizedBox(width: 8),
                  ],
                  Text(label.toUpperCase(),
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                        fontSize: 12,
                        letterSpacing: 0.6,
                      )),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// The violet greeting banner with a rounded avatar blob on the left.
class PowrHeroBanner extends StatelessWidget {
  const PowrHeroBanner({
    super.key,
    required this.greeting,
    required this.subtitle,
  });

  final String greeting;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        gradient: PowrTokens.heroGradient,
        borderRadius: PowrTokens.radius,
        boxShadow: [
          BoxShadow(
            color: PowrTokens.accent.withValues(alpha: 0.28),
            blurRadius: 26,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      padding: const EdgeInsets.fromLTRB(24, 22, 24, 22),
      child: Row(
        children: [
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.18),
              shape: BoxShape.circle,
            ),
            alignment: Alignment.center,
            child: const Icon(Icons.emoji_emotions_outlined,
                color: Colors.white, size: 30),
          ),
          const SizedBox(width: 18),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(greeting,
                    style: const TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w800)),
                const SizedBox(height: 4),
                Text(subtitle,
                    style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.85),
                        fontSize: 13)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Big number + caption stat (58 / New Orders).
class PowrStat extends StatelessWidget {
  const PowrStat(this.value, this.caption, {super.key, this.color});

  final String value;
  final String caption;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisAlignment: MainAxisAlignment.center,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
                fontSize: 28,
                fontWeight: FontWeight.w800,
                color: color ?? PowrTokens.primary)),
        const SizedBox(height: 4),
        Text(caption,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 12, color: PowrTokens.muted)),
      ],
    );
  }
}

/// A ring-gauge with a centred label (259K / 78%).
class PowrRadial extends StatelessWidget {
  const PowrRadial({
    super.key,
    required this.percent,
    required this.centerTop,
    this.centerBottom,
    this.color = PowrTokens.accentSoft,
    this.size = 96,
  });

  final double percent; // 0..1
  final String centerTop;
  final String? centerBottom;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        alignment: Alignment.center,
        children: [
          SizedBox(
            width: size,
            height: size,
            child: CircularProgressIndicator(
              value: percent.clamp(0, 1),
              strokeWidth: 8,
              backgroundColor: PowrTokens.line,
              valueColor: AlwaysStoppedAnimation(color),
            ),
          ),
          Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(centerTop,
                  style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: PowrTokens.ink)),
              if (centerBottom != null)
                Text(centerBottom!,
                    style: const TextStyle(
                        fontSize: 9.5, color: PowrTokens.muted)),
            ],
          ),
        ],
      ),
    );
  }
}

/// Underline tab bar ("History | Upcoming").
class PowrTabs extends StatelessWidget {
  const PowrTabs({
    super.key,
    required this.tabs,
    required this.index,
    required this.onChanged,
  });

  final List<String> tabs;
  final int index;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (var i = 0; i < tabs.length; i++)
          Padding(
            padding: const EdgeInsets.only(right: 24),
            child: InkWell(
              onTap: () => onChanged(i),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(tabs[i],
                        style: TextStyle(
                          fontSize: 13.5,
                          fontWeight:
                              i == index ? FontWeight.w800 : FontWeight.w600,
                          color: i == index
                              ? PowrTokens.primary
                              : PowrTokens.muted,
                        )),
                    const SizedBox(height: 6),
                    Container(
                      height: 2.5,
                      width: 26,
                      decoration: BoxDecoration(
                        color: i == index
                            ? PowrTokens.primary
                            : Colors.transparent,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
      ],
    );
  }
}

/// Small rounded label.
class PowrPill extends StatelessWidget {
  const PowrPill(this.label, {super.key, this.color = PowrTokens.primary});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(label,
          style: TextStyle(
              fontSize: 11, fontWeight: FontWeight.w800, color: color)),
    );
  }
}

/// A person row for the "Message" list on the right rail.
class PowrPersonRow extends StatelessWidget {
  const PowrPersonRow({
    super.key,
    required this.name,
    required this.subtitle,
    this.trailing,
  });

  final String name;
  final String subtitle;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          CircleAvatar(
            radius: 17,
            backgroundColor: PowrTokens.accent.withValues(alpha: 0.12),
            child: Text(
              name.isNotEmpty ? name.characters.first.toUpperCase() : '?',
              style: const TextStyle(
                  color: PowrTokens.accent,
                  fontWeight: FontWeight.w800,
                  fontSize: 13),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(name,
                    style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: PowrTokens.ink)),
                Text(subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style:
                        const TextStyle(fontSize: 11, color: PowrTokens.faint)),
              ],
            ),
          ),
          if (trailing != null) trailing!,
        ],
      ),
    );
  }
}
