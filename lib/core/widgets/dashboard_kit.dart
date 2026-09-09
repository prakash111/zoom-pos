import 'package:flutter/material.dart';

/// Visual primitives for the Windows desktop dashboard, styled after the
/// green inventory-analytics reference: a light page, floating white cards
/// with a large radius and a soft shadow, tinted rounded-square stat icons,
/// green delta chips and lime bar charts.
class SpTokens {
  SpTokens._();

  static const pageBg = Color(0xFFFAFAFB);
  static const card = Colors.white;
  static const ink = Color(0xFF111827);
  static const muted = Color(0xFF6B7280);
  static const faint = Color(0xFF9CA3AF);
  static const line = Color(0xFFEDEEF1);

  static const green = Color(0xFF7CC518); // brand lime
  static const greenSoft = Color(0xFFE9F8D6);
  static const greenInk = Color(0xFF3F8E00);
  static const up = Color(0xFF16A34A);
  static const down = Color(0xFFDC2626);
  static const blue = Color(0xFF3B82F6);
  static const amber = Color(0xFFF59E0B);
  static const coral = Color(0xFFFB7185);

  static BorderRadius get radius => BorderRadius.circular(20);
  static BorderRadius get radiusSm => BorderRadius.circular(12);

  static List<BoxShadow> get shadow => [
        BoxShadow(
          color: const Color(0xFF9AA1B2).withValues(alpha: 0.14),
          blurRadius: 24,
          offset: const Offset(0, 10),
        ),
      ];
}

/// Floating white card with an optional title + trailing header.
class SpCard extends StatelessWidget {
  const SpCard({
    super.key,
    required this.child,
    this.title,
    this.trailing,
    this.padding = const EdgeInsets.all(22),
  });

  final Widget child;
  final String? title;
  final Widget? trailing;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: SpTokens.card,
        borderRadius: SpTokens.radius,
        boxShadow: SpTokens.shadow,
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
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: SpTokens.ink)),
                  )
                else
                  const Spacer(),
                if (trailing != null) trailing!,
              ],
            ),
            const SizedBox(height: 18),
          ],
          child,
        ],
      ),
    );
  }
}

/// Up/down delta chip: "▲ +8.2%" green, "▼ +3" red.
class SpDeltaChip extends StatelessWidget {
  const SpDeltaChip(this.label, {super.key, this.positive = true});

  final String label;
  final bool positive;

  @override
  Widget build(BuildContext context) {
    final c = positive ? SpTokens.up : SpTokens.down;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: c.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(positive ? Icons.arrow_drop_up : Icons.arrow_drop_down,
            size: 16, color: c),
        Text(label,
            style: TextStyle(
                fontSize: 11.5, fontWeight: FontWeight.w800, color: c)),
      ]),
    );
  }
}

/// Stat card: tinted rounded-square icon + big number, then label + delta.
class SpStatCard extends StatelessWidget {
  const SpStatCard({
    super.key,
    required this.icon,
    required this.tint,
    required this.value,
    required this.label,
    required this.delta,
    this.deltaPositive = true,
  });

  final IconData icon;
  final Color tint;
  final String value;
  final String label;
  final String delta;
  final bool deltaPositive;

  @override
  Widget build(BuildContext context) {
    return SpCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: tint.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(icon, color: tint, size: 24),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Text(
                  value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                      fontSize: 30,
                      fontWeight: FontWeight.w800,
                      color: SpTokens.ink,
                      letterSpacing: -0.5),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(label,
              style: const TextStyle(fontSize: 13, color: SpTokens.muted)),
          const SizedBox(height: 8),
          SpDeltaChip(delta, positive: deltaPositive),
        ],
      ),
    );
  }
}

/// "7 Days ▾" dropdown pill.
class SpChip extends StatelessWidget {
  const SpChip(this.label, {super.key, this.onTap});

  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: SpTokens.card,
      shape: RoundedRectangleBorder(
        borderRadius: SpTokens.radiusSm,
        side: const BorderSide(color: SpTokens.line),
      ),
      child: InkWell(
        borderRadius: SpTokens.radiusSm,
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Text(label,
                style: const TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: SpTokens.ink)),
            const SizedBox(width: 8),
            const Icon(Icons.keyboard_arrow_down,
                size: 18, color: SpTokens.muted),
          ]),
        ),
      ),
    );
  }
}

/// Green outlined button ("Investigate In Details").
class SpOutlineButton extends StatelessWidget {
  const SpOutlineButton(this.label, {super.key, required this.onPressed});

  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    final c = Theme.of(context).colorScheme.primary;
    return OutlinedButton(
      onPressed: onPressed,
      style: OutlinedButton.styleFrom(
        foregroundColor: c,
        side: BorderSide(color: c.withValues(alpha: 0.5)),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        textStyle: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700),
      ),
      child: Text(label),
    );
  }
}

/// Solid brand button.
class SpButton extends StatelessWidget {
  const SpButton(this.label,
      {super.key, required this.onPressed, this.busy = false});

  final String label;
  final VoidCallback? onPressed;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final c = Theme.of(context).colorScheme.primary;
    final on = ThemeData.estimateBrightnessForColor(c) == Brightness.dark
        ? Colors.white
        : Colors.black;
    return Opacity(
      opacity: (onPressed == null || busy) ? 0.55 : 1,
      child: Material(
        color: c,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: busy ? null : onPressed,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
            child: busy
                ? SizedBox(
                    height: 16,
                    width: 16,
                    child: CircularProgressIndicator(strokeWidth: 2, color: on))
                : Text(label,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                        color: on,
                        fontWeight: FontWeight.w800,
                        fontSize: 12.5)),
          ),
        ),
      ),
    );
  }
}

/// Small status badge ("RECEIVED").
class SpBadge extends StatelessWidget {
  const SpBadge(this.label, {super.key, this.color = SpTokens.up});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(label.toUpperCase(),
          style: TextStyle(
              fontSize: 10.5,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.4,
              color: color)),
    );
  }
}

/// Horizontal stacked segment bar ("Gadgets 45% · Devices 45% · …").
class SpSegmentBar extends StatelessWidget {
  const SpSegmentBar(this.segments, {super.key});

  /// (label, fraction 0..1, colour)
  final List<(String, double, Color)> segments;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: SizedBox(
            height: 30,
            child: Row(
              children: [
                for (final (label, frac, color) in segments)
                  Expanded(
                    flex: (frac * 1000).round().clamp(1, 100000),
                    child: Container(
                      color: color,
                      alignment: Alignment.center,
                      child: Text(label,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                              fontSize: 10.5,
                              fontWeight: FontWeight.w700,
                              color: Colors.white)),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
