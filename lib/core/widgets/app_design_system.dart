import 'package:flutter/material.dart';

import '../config/theme.dart';
import 'barcode_scanner_screen.dart';

/// Reusable generic card widget conforming to enterprise design tokens.
/// Consistent rounded corners (12px), elevation/border token, and padding.
class AppCard extends StatelessWidget {
  const AppCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.margin,
    this.onTap,
    this.color,
    this.borderColor,
    this.borderRadius,
    this.elevation = 0,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry? margin;
  final VoidCallback? onTap;
  final Color? color;
  final Color? borderColor;
  final BorderRadiusGeometry? borderRadius;
  final double elevation;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = color ?? (isDark ? AppTheme.darkCard : AppTheme.lightCard);
    final border = borderColor ??
        (isDark
            ? AppTheme.darkBorder
            : AppTheme.lightBorder);
    final radius = borderRadius ?? BorderRadius.circular(12);

    Widget content = Container(
      margin: margin,
      decoration: BoxDecoration(
        color: bg,
        borderRadius: radius,
        border: Border.all(color: border, width: 1),
        boxShadow: elevation > 0
            ? [
                BoxShadow(
                  color: Colors.black.withValues(alpha: isDark ? 0.3 : 0.05),
                  blurRadius: elevation * 3,
                  offset: Offset(0, elevation),
                ),
              ]
            : null,
      ),
      child: ClipRRect(
        borderRadius: radius,
        child: Padding(
          padding: padding,
          child: child,
        ),
      ),
    );

    if (onTap != null) {
      content = Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: radius is BorderRadius ? radius : BorderRadius.circular(12),
          onTap: onTap,
          child: content,
        ),
      );
    }

    return content;
  }
}

/// Left-aligned 11px uppercase tracking header with `#38BDF8` tint.
class AppSectionHeader extends StatelessWidget {
  const AppSectionHeader({
    super.key,
    required this.title,
    this.trailing,
    this.padding = const EdgeInsets.symmetric(vertical: 8),
    this.color = AppTheme.activeLink,
  });

  final String title;
  final Widget? trailing;
  final EdgeInsetsGeometry padding;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: padding,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            title.toUpperCase(),
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.2,
              color: color,
            ),
          ),
          if (trailing != null) trailing!,
        ],
      ),
    );
  }
}

/// Unified status pill for Paid, Pending, Preparing, Dispatched, In-Repair,
/// and other vertical statuses.
class StatusBadge extends StatelessWidget {
  const StatusBadge({
    super.key,
    required this.status,
    this.label,
    this.fontSize = 11,
  });

  final String status;
  final String? label;
  final double fontSize;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final normalized = status.toLowerCase().trim().replaceAll('-', '_').replaceAll(' ', '_');

    Color bg;
    Color fg;

    switch (normalized) {
      case 'paid':
      case 'completed':
      case 'ready':
      case 'delivered':
      case 'success':
        bg = isDark ? const Color(0xFF064E3B) : const Color(0xFFECFDF5);
        fg = isDark ? const Color(0xFF34D399) : const Color(0xFF065F46);
        break;

      case 'unpaid':
      case 'void':
      case 'cancelled':
      case 'failed':
      case 'danger':
        bg = isDark ? const Color(0xFF450A0A) : const Color(0xFFFEF2F2);
        fg = isDark ? const Color(0xFFF87171) : const Color(0xFF991B1B);
        break;

      case 'pending':
      case 'due':
      case 'waiting':
      case 'waiting_parts':
      case 'intake':
      case 'received':
      case 'warning':
        bg = isDark ? const Color(0xFF451A03) : const Color(0xFFFFFBEB);
        fg = isDark ? const Color(0xFFFBBF24) : const Color(0xFFB45309);
        break;

      case 'preparing':
      case 'diagnosing':
      case 'in_progress':
      case 'in_progress_service':
      case 'info':
        bg = isDark ? const Color(0xFF082F49) : const Color(0xFFF0F9FF);
        fg = isDark ? const Color(0xFF38BDF8) : const Color(0xFF0369A1);
        break;

      case 'dispatched':
      case 'sent':
      case 'served':
        bg = isDark ? const Color(0xFF1E1B4B) : const Color(0xFFEEF2FF);
        fg = isDark ? const Color(0xFF818CF8) : const Color(0xFF4338CA);
        break;

      case 'in_repair':
      case 'repair':
        bg = isDark ? const Color(0xFF2E1065) : const Color(0xFFF5F3FF);
        fg = isDark ? const Color(0xFFA78BFA) : const Color(0xFF6D28D9);
        break;

      default:
        bg = isDark ? const Color(0xFF1E293B) : const Color(0xFFF1F5F9);
        fg = isDark ? const Color(0xFF94A3B8) : const Color(0xFF475569);
        break;
    }

    final displayText = label ?? _defaultLabel(status);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        displayText,
        style: TextStyle(
          fontSize: fontSize,
          fontWeight: FontWeight.w600,
          color: fg,
        ),
      ),
    );
  }

  String _defaultLabel(String raw) {
    if (raw.isEmpty) return '';
    final words = raw.replaceAll('_', ' ').replaceAll('-', ' ').split(' ');
    return words.map((w) => w.isNotEmpty ? '${w[0].toUpperCase()}${w.substring(1)}' : '').join(' ');
  }
}

/// Uniform search bar with prefix icon, clear button, and barcode/QR scanner action.
class AppSearchInput extends StatefulWidget {
  const AppSearchInput({
    super.key,
    this.controller,
    this.hintText = 'Search...',
    this.onChanged,
    this.onSubmitted,
    this.onScanTap,
    this.showScanner = true,
    this.autofocus = false,
  });

  final TextEditingController? controller;
  final String hintText;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;
  final VoidCallback? onScanTap;
  final bool showScanner;
  final bool autofocus;

  @override
  State<AppSearchInput> createState() => _AppSearchInputState();
}

class _AppSearchInputState extends State<AppSearchInput> {
  late TextEditingController _controller;
  bool _internalController = false;

  @override
  void initState() {
    super.initState();
    if (widget.controller == null) {
      _controller = TextEditingController();
      _internalController = true;
    } else {
      _controller = widget.controller!;
    }
    _controller.addListener(_onTextChanged);
  }

  @override
  void didUpdateWidget(AppSearchInput oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      if (_internalController) {
        _controller.dispose();
      }
      if (widget.controller == null) {
        _controller = TextEditingController();
        _internalController = true;
      } else {
        _controller = widget.controller!;
        _internalController = false;
      }
      _controller.addListener(_onTextChanged);
    }
  }

  @override
  void dispose() {
    _controller.removeListener(_onTextChanged);
    if (_internalController) {
      _controller.dispose();
    }
    super.dispose();
  }

  void _onTextChanged() {
    setState(() {});
  }

  Future<void> _handleScan() async {
    if (widget.onScanTap != null) {
      widget.onScanTap!();
      return;
    }
    final code = await Navigator.of(context).push<String>(
      MaterialPageRoute(builder: (_) => const BarcodeScannerScreen()),
    );
    if (code != null && code.isNotEmpty) {
      _controller.text = code;
      widget.onChanged?.call(code);
      widget.onSubmitted?.call(code);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final fill = isDark ? AppTheme.darkInput : Colors.white;
    final border = isDark ? AppTheme.darkBorder : AppTheme.lightBorder;
    final textColor = isDark ? AppTheme.darkHeading : AppTheme.lightHeading;
    final hintColor = isDark ? AppTheme.darkMuted : AppTheme.lightMuted;

    return Container(
      height: 44,
      decoration: BoxDecoration(
        color: fill,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: border),
      ),
      child: Row(
        children: [
          const SizedBox(width: 12),
          Icon(Icons.search_rounded, size: 20, color: hintColor),
          const SizedBox(width: 8),
          Expanded(
            child: TextField(
              controller: _controller,
              autofocus: widget.autofocus,
              style: TextStyle(fontSize: 13, color: textColor),
              decoration: InputDecoration(
                hintText: widget.hintText,
                hintStyle: TextStyle(fontSize: 13, color: hintColor),
                border: InputBorder.none,
                contentPadding: const EdgeInsets.symmetric(vertical: 10),
                isDense: true,
                filled: false,
              ),
              onChanged: widget.onChanged,
              onSubmitted: widget.onSubmitted,
            ),
          ),
          if (_controller.text.isNotEmpty)
            IconButton(
              icon: Icon(Icons.close_rounded, size: 16, color: hintColor),
              onPressed: () {
                _controller.clear();
                widget.onChanged?.call('');
              },
              padding: EdgeInsets.zero,
              constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
            ),
          if (widget.showScanner) ...[
            Container(
              height: 20,
              width: 1,
              color: border,
              margin: const EdgeInsets.symmetric(horizontal: 4),
            ),
            IconButton(
              icon: const Icon(Icons.qr_code_scanner_rounded, size: 18, color: AppTheme.activeLink),
              tooltip: 'Scan Barcode / QR',
              onPressed: _handleScan,
              padding: EdgeInsets.zero,
              constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
            ),
            const SizedBox(width: 4),
          ],
        ],
      ),
    );
  }
}
