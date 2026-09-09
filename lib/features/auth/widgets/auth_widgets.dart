import 'package:flutter/material.dart';

/// Input styling for the redesigned auth screens: a soft, rounded field with
/// a muted leading icon and a hint instead of a floating label, matching the
/// reference "E-Inventory" mock.
InputDecoration authInputDecoration({
  required String hint,
  required IconData icon,
  Widget? suffixIcon,
}) {
  const border = Color(0xFFE2E8F0);
  const focus = Color(0xFF2563EB);
  OutlineInputBorder b(Color c, [double w = 1.2]) => OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: c, width: w),
      );
  return InputDecoration(
    hintText: hint,
    hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 14.5),
    prefixIcon: Icon(icon, size: 20, color: const Color(0xFF94A3B8)),
    filled: true,
    fillColor: const Color(0xFFF8FAFC),
    isDense: true,
    contentPadding: const EdgeInsets.symmetric(vertical: 16, horizontal: 14),
    enabledBorder: b(border),
    border: b(border),
    focusedBorder: b(focus, 1.6),
    errorBorder: b(const Color(0xFFEF4444)),
    focusedErrorBorder: b(const Color(0xFFEF4444), 1.6),
    suffixIcon: suffixIcon,
  );
}

/// Full-width gradient primary action button with a built-in busy state,
/// matching the reference's glowing "Log in" button.
class AuthPrimaryButton extends StatelessWidget {
  const AuthPrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.busy = false,
    this.color,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool busy;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    // Follow the tenant's brand colour by default; callers can still override
    // (the OTP screen uses green for "verify & activate").
    final base = color ?? Theme.of(context).colorScheme.primary;
    final onBase = ThemeData.estimateBrightnessForColor(base) == Brightness.dark
        ? Colors.white
        : Colors.black;
    final enabled = onPressed != null && !busy;
    return Opacity(
      opacity: enabled ? 1 : 0.6,
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          gradient: LinearGradient(
            colors: [base, Color.lerp(base, Colors.black, 0.12)!],
          ),
          boxShadow: enabled
              ? [
                  BoxShadow(
                    color: base.withValues(alpha: 0.35),
                    blurRadius: 22,
                    offset: const Offset(0, 10),
                  ),
                ]
              : null,
        ),
        child: Material(
          type: MaterialType.transparency,
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: enabled ? onPressed : null,
            child: Container(
              height: 52,
              alignment: Alignment.center,
              child: busy
                  ? SizedBox(
                      height: 22,
                      width: 22,
                      child: CircularProgressIndicator(
                          strokeWidth: 2.4, color: onBase),
                    )
                  : Text(
                      label,
                      style: TextStyle(
                        color: onBase,
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
            ),
          ),
        ),
      ),
    );
  }
}
