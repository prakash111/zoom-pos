import 'package:flutter/material.dart';

/// Design tokens for the auth flow ("POS SYSTEMS" reference mock): a soft
/// blue-tinted page, a white rounded card, labelled icon inputs on a light
/// grey fill, and an orange gradient primary action.
class AuthColors {
  AuthColors._();

  static const ink = Color(0xFF0F172A);
  static const muted = Color(0xFF64748B);
  static const faint = Color(0xFF94A3B8);
  static const fieldFill = Color(0xFFF1F5F9);
  static const border = Color(0xFFE2E8F0);
  static const orange = Color(0xFFF97316);
  static const orangeDark = Color(0xFFEA580C);
  static const link = Color(0xFF2563EB);
  static const facebook = Color(0xFF1877F2);
  static const infoBg = Color(0xFFEFF3FF);
  static const infoBorder = Color(0xFFDCE6FF);
  static const pageTop = Color(0xFFF0F5FC);
  static const pageBottom = Color(0xFFE2ECF8);
}

/// Input styling for the auth screens: a soft, rounded field with a muted
/// leading icon and a hint (the bold field name is rendered above via
/// [AuthFieldLabel]).
InputDecoration authInputDecoration({
  required String hint,
  required IconData icon,
  Widget? suffixIcon,
}) {
  OutlineInputBorder b(Color c, [double w = 1.2]) => OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: c, width: w),
      );
  return InputDecoration(
    hintText: hint,
    hintStyle: const TextStyle(color: AuthColors.faint, fontSize: 14.5),
    prefixIcon: Icon(icon, size: 20, color: AuthColors.faint),
    filled: true,
    fillColor: AuthColors.fieldFill,
    isDense: true,
    contentPadding: const EdgeInsets.symmetric(vertical: 16, horizontal: 14),
    enabledBorder: b(AuthColors.border),
    border: b(AuthColors.border),
    focusedBorder: b(AuthColors.orange, 1.6),
    errorBorder: b(const Color(0xFFEF4444)),
    focusedErrorBorder: b(const Color(0xFFEF4444), 1.6),
    suffixIcon: suffixIcon,
  );
}

/// Bold field name shown directly above its input.
class AuthFieldLabel extends StatelessWidget {
  const AuthFieldLabel(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 7, top: 2),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Text(
          text,
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: AuthColors.ink,
          ),
        ),
      ),
    );
  }
}

/// Full-width orange gradient primary action with a trailing arrow and a
/// built-in busy state. Pass [color] to override (the OTP screen uses green).
class AuthPrimaryButton extends StatelessWidget {
  const AuthPrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.busy = false,
    this.color,
    this.trailingIcon = Icons.arrow_forward_rounded,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool busy;
  final Color? color;
  final IconData? trailingIcon;

  @override
  Widget build(BuildContext context) {
    final base = color ?? AuthColors.orange;
    final dark = color == null
        ? AuthColors.orangeDark
        : Color.lerp(base, Colors.black, 0.14)!;
    final onBase = ThemeData.estimateBrightnessForColor(base) == Brightness.dark
        ? Colors.white
        : Colors.black;
    final enabled = onPressed != null && !busy;

    return Opacity(
      opacity: enabled ? 1 : 0.55,
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          gradient: LinearGradient(colors: [base, dark]),
          boxShadow: enabled
              ? [
                  BoxShadow(
                    color: base.withValues(alpha: 0.35),
                    blurRadius: 24,
                    offset: const Offset(0, 12),
                  ),
                ]
              : null,
        ),
        child: Material(
          type: MaterialType.transparency,
          child: InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: enabled ? onPressed : null,
            child: Container(
              height: 54,
              alignment: Alignment.center,
              child: busy
                  ? SizedBox(
                      height: 22,
                      width: 22,
                      child: CircularProgressIndicator(
                          strokeWidth: 2.4, color: onBase),
                    )
                  : Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Flexible(
                          child: Text(
                            label,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: onBase,
                              fontSize: 16,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        if (trailingIcon != null) ...[
                          const SizedBox(width: 10),
                          Icon(trailingIcon, size: 20, color: onBase),
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

/// "———  Or continue with  ———" separator above the social buttons.
class AuthOrDivider extends StatelessWidget {
  const AuthOrDivider({super.key, this.text = 'Or continue with'});

  final String text;

  @override
  Widget build(BuildContext context) {
    const line = Divider(color: AuthColors.border, thickness: 1);
    return Row(
      children: [
        const Expanded(child: line),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Text(
            text,
            style: const TextStyle(
              fontSize: 13,
              color: AuthColors.faint,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
        const Expanded(child: line),
      ],
    );
  }
}

/// White, bordered, full-width social sign-in button.
class AuthSocialButton extends StatelessWidget {
  const AuthSocialButton({
    super.key,
    required this.label,
    required this.leading,
    required this.onPressed,
    this.foreground = AuthColors.ink,
  });

  final String label;
  final Widget leading;
  final VoidCallback? onPressed;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 52,
      child: OutlinedButton(
        style: OutlinedButton.styleFrom(
          backgroundColor: Colors.white,
          foregroundColor: foreground,
          side: const BorderSide(color: AuthColors.border, width: 1.2),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
        onPressed: onPressed,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          mainAxisSize: MainAxisSize.min,
          children: [
            leading,
            const SizedBox(width: 10),
            Flexible(
              child: Text(
                label,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: foreground,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// The multi-colour Google "G", drawn small so it never clips inside a button.
class AuthGoogleLogo extends StatelessWidget {
  const AuthGoogleLogo({super.key, this.size = 20});

  final double size;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: const CustomPaint(painter: _GoogleGPainter()),
    );
  }
}

/// Soft blue call-to-action tile ("Have a store account ID?", "Already have
/// an account?").
class AuthInfoCard extends StatelessWidget {
  const AuthInfoCard({
    super.key,
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.trailing = const Icon(Icons.chevron_right, color: AuthColors.muted),
  });

  final IconData icon;
  final Widget title;
  final Widget subtitle;
  final VoidCallback? onTap;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AuthColors.infoBg,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AuthColors.infoBorder),
          ),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, size: 20, color: AuthColors.link),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    DefaultTextStyle.merge(
                      style: const TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w700,
                        color: AuthColors.ink,
                      ),
                      child: title,
                    ),
                    const SizedBox(height: 2),
                    DefaultTextStyle.merge(
                      style: const TextStyle(
                        fontSize: 12,
                        color: AuthColors.muted,
                      ),
                      child: subtitle,
                    ),
                  ],
                ),
              ),
              if (trailing != null) trailing!,
            ],
          ),
        ),
      ),
    );
  }
}

class _GoogleGPainter extends CustomPainter {
  const _GoogleGPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final r = size.width / 2;
    final c = Offset(r, r);
    final stroke = size.width * 0.28;
    final rect = Rect.fromCircle(center: c, radius: r - stroke / 2);
    final p = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.butt;

    p.color = const Color(0xFF4285F4);
    canvas.drawArc(rect, -0.55, 1.6, false, p);
    p.color = const Color(0xFF34A853);
    canvas.drawArc(rect, 1.15, 1.5, false, p);
    p.color = const Color(0xFFFBBC05);
    canvas.drawArc(rect, 2.55, 1.1, false, p);
    p.color = const Color(0xFFEA4335);
    canvas.drawArc(rect, 3.6, 1.6, false, p);

    p
      ..style = PaintingStyle.fill
      ..color = const Color(0xFF4285F4);
    canvas.drawRect(
      Rect.fromLTWH(c.dx, c.dy - stroke / 2, r - stroke / 2, stroke),
      p,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
