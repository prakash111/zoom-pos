import 'package:flutter/material.dart';

import '../../sdui/models/sdui_models.dart';
import '../../sdui/sdui_icon_registry.dart';

/// Reusable action pill (e.g. Hold, Customer, Note, Discount, Due Date)
class SduiActionPill extends StatelessWidget {
  const SduiActionPill({
    super.key,
    required this.label,
    required this.icon,
    this.isActive = false,
    this.badgeCount,
    this.activeColor,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool isActive;
  final int? badgeCount;
  final Color? activeColor;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color = activeColor ?? theme.colorScheme.primary;

    return Material(
      color: isActive ? color.withValues(alpha: 0.12) : theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(
          color: isActive ? color : theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
        ),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 16, color: isActive ? color : theme.colorScheme.onSurfaceVariant),
              const SizedBox(width: 6),
              Text(
                label,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: isActive ? FontWeight.w600 : FontWeight.normal,
                  color: isActive ? color : theme.colorScheme.onSurface,
                ),
              ),
              if (badgeCount != null && badgeCount! > 0) ...[
                const SizedBox(width: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: color,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    '$badgeCount',
                    style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Quantity stepper `[-] count [+]` component.
class SduiStepCounter extends StatelessWidget {
  const SduiStepCounter({
    super.key,
    required this.value,
    required this.onChanged,
    this.min = 1,
    this.max = 9999,
  });

  final int value;
  final ValueChanged<int> onChanged;
  final int min;
  final int max;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: isDark ? const Color(0xFF334155) : const Color(0xFFCBD5E1),
          width: 1,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            icon: const Icon(Icons.remove, size: 16),
            color: value > min
                ? (isDark ? const Color(0xFFF8FAFC) : const Color(0xFF0F172A))
                : (isDark ? const Color(0xFF64748B) : const Color(0xFF94A3B8)),
            onPressed: value > min ? () => onChanged(value - 1) : null,
            visualDensity: VisualDensity.compact,
            padding: const EdgeInsets.all(4),
            constraints: const BoxConstraints(minWidth: 28, minHeight: 28),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 6),
            child: Text(
              '$value',
              style: TextStyle(
                fontWeight: FontWeight.w600,
                fontSize: 13,
                color: isDark ? const Color(0xFFF8FAFC) : const Color(0xFF0F172A),
              ),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.add, size: 16),
            color: isDark ? const Color(0xFFF8FAFC) : const Color(0xFF0F172A),
            onPressed: value < max ? () => onChanged(value + 1) : null,
            visualDensity: VisualDensity.compact,
            padding: const EdgeInsets.all(4),
            constraints: const BoxConstraints(minWidth: 28, minHeight: 28),
          ),
        ],
      ),
    );
  }
}

/// Dynamic status badge rendering label, background/text color, and icon from server schema.
class SduiStatusBadge extends StatelessWidget {
  const SduiStatusBadge({
    super.key,
    this.statusSchema,
    this.label,
    this.fallbackLabel,
    this.color,
    this.icon,
    this.isSolid = false,
  });

  final SduiStatusSchema? statusSchema;
  final String? label;
  final String? fallbackLabel;
  final Color? color;
  final IconData? icon;
  final bool isSolid;

  @override
  Widget build(BuildContext context) {
    final displayLabel = label ?? statusSchema?.label ?? fallbackLabel ?? 'Unknown';
    final badgeColor = color ??
        (statusSchema != null
            ? SduiIconRegistry.parseColor(statusSchema!.color)
            : Colors.grey.shade600);
    final badgeIcon = icon ??
        (statusSchema != null
            ? SduiIconRegistry.resolve(statusSchema!.icon, fallback: Icons.info_outline)
            : null);
    final isSolidStyle = isSolid || statusSchema?.badgeStyle == 'solid';

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: isSolidStyle ? badgeColor : badgeColor.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(8),
        border: isSolidStyle ? null : Border.all(color: badgeColor.withValues(alpha: 0.3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (badgeIcon != null) ...[
            Icon(badgeIcon, size: 14, color: isSolidStyle ? Colors.white : badgeColor),
            const SizedBox(width: 4),
          ],
          Text(
            displayLabel,
            style: TextStyle(
              color: isSolidStyle ? Colors.white : badgeColor,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

/// Reusable input field.
class SduiInputField extends StatelessWidget {
  const SduiInputField({
    super.key,
    required this.controller,
    this.label,
    this.hint,
    this.prefixIcon,
    this.suffixIcon,
    this.keyboardType,
    this.onChanged,
    this.maxLines = 1,
  });

  final TextEditingController controller;
  final String? label;
  final String? hint;
  final Widget? prefixIcon;
  final Widget? suffixIcon;
  final TextInputType? keyboardType;
  final ValueChanged<String>? onChanged;
  final int maxLines;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      keyboardType: keyboardType,
      maxLines: maxLines,
      onChanged: onChanged,
      decoration: InputDecoration(
        labelText: label,
        hintText: hint,
        prefixIcon: prefixIcon,
        suffixIcon: suffixIcon,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      ),
    );
  }
}
