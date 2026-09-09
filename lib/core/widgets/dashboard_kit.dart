import 'package:flutter/material.dart';

/// Shared visual primitives for the Windows desktop dashboard, matching the
/// reference admin screenshots: light slate page, elevated white cards with
/// an emoji-led header, indigo primary actions, soft status pills and a
/// plain data table.
class DashTokens {
  DashTokens._();

  static const pageBg = Color(0xFFF1F5F9);
  static const topBar = Color(0xFF0B1220);
  static const card = Colors.white;
  static const cardBorder = Color(0xFFE9EDF3);
  static const ink = Color(0xFF0F172A);
  static const muted = Color(0xFF64748B);
  static const faint = Color(0xFF94A3B8);
  static const primary = Color(0xFF4F46E5);
  static const primaryDark = Color(0xFF4338CA);
  static const danger = Color(0xFFDC2626);

  static const contentMaxWidth = 1160.0;

  static BorderRadius get cardRadius => BorderRadius.circular(18);

  static List<BoxShadow> get cardShadow => [
        BoxShadow(
          color: Colors.black.withValues(alpha: 0.04),
          blurRadius: 24,
          offset: const Offset(0, 10),
        ),
      ];
}

/// A white rounded card with an optional `emoji + title (+ subtitle)` header
/// and an optional trailing widget (a pill, a button…).
class DashCard extends StatelessWidget {
  const DashCard({
    super.key,
    required this.child,
    this.emoji,
    this.title,
    this.subtitle,
    this.trailing,
    this.padding = const EdgeInsets.all(24),
  });

  final Widget child;
  final String? emoji;
  final String? title;
  final String? subtitle;
  final Widget? trailing;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    final hasHeader = title != null || trailing != null;
    return Container(
      decoration: BoxDecoration(
        color: DashTokens.card,
        borderRadius: DashTokens.cardRadius,
        border: Border.all(color: DashTokens.cardBorder),
        boxShadow: DashTokens.cardShadow,
      ),
      padding: padding,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          if (hasHeader) ...[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (title != null)
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            if (emoji != null) ...[
                              Text(emoji!,
                                  style: const TextStyle(fontSize: 16)),
                              const SizedBox(width: 10),
                            ],
                            Flexible(
                              child: Text(
                                title!,
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w800,
                                  color: DashTokens.ink,
                                ),
                              ),
                            ),
                          ],
                        ),
                      if (subtitle != null) ...[
                        const SizedBox(height: 4),
                        Text(
                          subtitle!,
                          style: const TextStyle(
                              fontSize: 12.5,
                              color: DashTokens.muted,
                              height: 1.4),
                        ),
                      ],
                    ],
                  ),
                ),
                if (trailing != null) ...[
                  const SizedBox(width: 12),
                  trailing!,
                ],
              ],
            ),
            const SizedBox(height: 20),
          ],
          child,
        ],
      ),
    );
  }
}

/// Small rounded label — e.g. the indigo "5 Active" count on a card header,
/// or a category tag inside a table row.
class DashPill extends StatelessWidget {
  const DashPill(
    this.label, {
    super.key,
    this.color = DashTokens.primary,
  });

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
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11.5,
          fontWeight: FontWeight.w800,
          color: color,
        ),
      ),
    );
  }
}

/// Green / amber / red status pill for a table cell.
class DashStatusPill extends StatelessWidget {
  const DashStatusPill(this.label, {super.key, this.tone = DashStatusTone.ok});

  final String label;
  final DashStatusTone tone;

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = switch (tone) {
      DashStatusTone.ok => (const Color(0xFFDCFCE7), const Color(0xFF15803D)),
      DashStatusTone.warn => (const Color(0xFFFEF3C7), const Color(0xFFB45309)),
      DashStatusTone.bad => (const Color(0xFFFEE2E2), const Color(0xFFB91C1C)),
      DashStatusTone.neutral => (
          const Color(0xFFE2E8F0),
          const Color(0xFF475569)
        ),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration:
          BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Text(label,
          style: TextStyle(
              fontSize: 11.5, fontWeight: FontWeight.w800, color: fg)),
    );
  }
}

enum DashStatusTone { ok, warn, bad, neutral }

/// Full-height indigo primary button, optionally with a leading emoji, in a
/// solid or "floating" (drop-shadowed, pill) style.
class DashPrimaryButton extends StatelessWidget {
  const DashPrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.emoji,
    this.busy = false,
    this.floating = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final String? emoji;
  final bool busy;
  final bool floating;

  @override
  Widget build(BuildContext context) {
    final enabled = onPressed != null && !busy;
    return Opacity(
      opacity: enabled ? 1 : 0.6,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: DashTokens.primary,
          borderRadius: BorderRadius.circular(floating ? 999 : 12),
          boxShadow: floating && enabled
              ? [
                  BoxShadow(
                    color: DashTokens.primary.withValues(alpha: 0.35),
                    blurRadius: 20,
                    offset: const Offset(0, 8),
                  ),
                ]
              : null,
        ),
        child: Material(
          type: MaterialType.transparency,
          child: InkWell(
            borderRadius: BorderRadius.circular(floating ? 999 : 12),
            onTap: enabled ? onPressed : null,
            child: Padding(
              padding: EdgeInsets.symmetric(
                  horizontal: floating ? 22 : 20, vertical: 13),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (busy)
                    const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white),
                    )
                  else ...[
                    if (emoji != null) ...[
                      Text(emoji!, style: const TextStyle(fontSize: 14)),
                      const SizedBox(width: 8),
                    ],
                    Text(
                      label,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                        fontSize: 13.5,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Compact dark pill button used in a table's "Actions" column; pass
/// `danger: true` for the red variant.
class DashRowAction extends StatelessWidget {
  const DashRowAction(this.label,
      {super.key, required this.onPressed, this.danger = false});

  final String label;
  final VoidCallback? onPressed;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final bg = danger ? DashTokens.danger : const Color(0xFF475569);
    return Material(
      color: bg,
      borderRadius: BorderRadius.circular(8),
      child: InkWell(
        borderRadius: BorderRadius.circular(8),
        onTap: onPressed,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
          child: Text(label,
              style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11.5,
                  fontWeight: FontWeight.w700)),
        ),
      ),
    );
  }
}

/// A settings row: title + description on the left, a switch on the right —
/// the "Powered By Receipt Branding" pattern from the reference.
class DashToggleRow extends StatelessWidget {
  const DashToggleRow({
    super.key,
    required this.title,
    required this.value,
    required this.onChanged,
    this.emoji,
    this.description,
  });

  final String title;
  final String? emoji;
  final String? description;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                emoji != null ? '$emoji  $title' : title,
                style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: DashTokens.ink),
              ),
              if (description != null) ...[
                const SizedBox(height: 4),
                Text(description!,
                    style: const TextStyle(
                        fontSize: 12.5, color: DashTokens.muted, height: 1.4)),
              ],
            ],
          ),
        ),
        const SizedBox(width: 16),
        Switch(
          value: value,
          activeThumbColor: DashTokens.primary,
          onChanged: onChanged,
        ),
      ],
    );
  }
}

/// A pickable option card — indigo outline + filled check when [selected],
/// as used by the reference's "Module Governance" grid.
class DashSelectableCard extends StatelessWidget {
  const DashSelectableCard({
    super.key,
    required this.title,
    required this.subtitle,
    required this.selected,
    required this.onTap,
    this.tag,
    this.leading,
  });

  final String title;
  final String subtitle;
  final String? tag;
  final Widget? leading;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 140),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: selected
              ? DashTokens.primary.withValues(alpha: 0.05)
              : DashTokens.card,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? DashTokens.primary : DashTokens.cardBorder,
            width: selected ? 1.6 : 1,
          ),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (leading != null) ...[
              leading!,
              const SizedBox(width: 12),
            ],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      Flexible(
                        child: Text(title,
                            style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                                color: DashTokens.ink)),
                      ),
                      if (tag != null) ...[
                        const SizedBox(width: 8),
                        DashPill(tag!),
                      ],
                    ],
                  ),
                  const SizedBox(height: 3),
                  Text(subtitle,
                      style: const TextStyle(
                          fontSize: 12, color: DashTokens.muted)),
                ],
              ),
            ),
            const SizedBox(width: 10),
            Icon(
              selected ? Icons.check_circle : Icons.circle_outlined,
              size: 22,
              color: selected ? DashTokens.primary : DashTokens.cardBorder,
            ),
          ],
        ),
      ),
    );
  }
}

/// A plain data table with bold grey headers and horizontally scrollable
/// rows, matching the reference "Licenses" list.
class DashTable extends StatelessWidget {
  const DashTable({
    super.key,
    required this.columns,
    required this.rows,
    this.columnWidths,
  });

  final List<String> columns;
  final List<List<Widget>> rows;
  final Map<int, TableColumnWidth>? columnWidths;

  @override
  Widget build(BuildContext context) {
    TableRow header = TableRow(
      children: [
        for (final c in columns)
          Padding(
            padding: const EdgeInsets.only(bottom: 14, right: 12),
            child: Text(c,
                style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                    color: DashTokens.ink)),
          ),
      ],
    );

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: ConstrainedBox(
        constraints: const BoxConstraints(minWidth: 720),
        child: Table(
          columnWidths: columnWidths ??
              {
                for (var i = 0; i < columns.length; i++)
                  i: const IntrinsicColumnWidth()
              },
          defaultVerticalAlignment: TableCellVerticalAlignment.middle,
          children: [
            header,
            for (final r in rows)
              TableRow(
                decoration: const BoxDecoration(
                  border: Border(top: BorderSide(color: DashTokens.cardBorder)),
                ),
                children: [
                  for (final cell in r)
                    Padding(
                      padding: const EdgeInsets.symmetric(
                          vertical: 12, horizontal: 0),
                      child: Padding(
                        padding: const EdgeInsets.only(right: 12),
                        child: cell,
                      ),
                    ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}
