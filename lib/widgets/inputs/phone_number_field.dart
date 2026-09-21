import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/config/bootstrap_cache.dart';
import '../../core/config/countries.dart';

/// Reusable phone number input widget that seamlessly auto-selects the
/// platform's configured country dial code, auto-strips leading trunk zeros,
/// handles full E.164 pasted numbers, and emits clean normalized phone strings.
class PhoneNumberField extends StatefulWidget {
  const PhoneNumberField({
    super.key,
    this.controller,
    this.initialValue,
    this.initialDialCode,
    this.onChanged,
    this.validator,
    this.label,
    this.hint,
    this.enabled = true,
    this.readOnly = false,
    this.textStyle,
    this.fillColor,
    this.borderColor,
    this.focusedBorderColor,
    this.borderRadius,
    this.autofocus = false,
  });

  final TextEditingController? controller;
  final String? initialValue;
  final String? initialDialCode;
  final void Function(String fullE164, String localNumber, String dialCode)?
      onChanged;
  final String? Function(String? localNumber, String fullE164)? validator;
  final String? label;
  final String? hint;
  final bool enabled;
  final bool readOnly;
  final TextStyle? textStyle;
  final Color? fillColor;
  final Color? borderColor;
  final Color? focusedBorderColor;
  final BorderRadius? borderRadius;
  final bool autofocus;

  @override
  State<PhoneNumberField> createState() => _PhoneNumberFieldState();
}

class _PhoneNumberFieldState extends State<PhoneNumberField> {
  late TextEditingController _controller;
  bool _internalController = false;
  late String _dialCode;
  final FocusNode _focusNode = FocusNode();

  static const List<String> _popularDialCodes = [
    '+91',
    '+1',
    '+44',
    '+971',
    '+966',
    '+61',
    '+65',
    '+60',
    '+49',
    '+33',
    '+81',
    '+55',
    '+27',
    '+234',
    '+254',
    '+880',
    '+92',
  ];

  @override
  void initState() {
    super.initState();
    _focusNode.addListener(() => setState(() {}));
    _resolveInitialState();
  }

  void _resolveInitialState() {
    final systemDefault = BootstrapCache.instance.defaultDialCode;
    _dialCode = widget.initialDialCode?.trim().isNotEmpty == true
        ? (widget.initialDialCode!.startsWith('+')
            ? widget.initialDialCode!
            : '+${widget.initialDialCode}')
        : systemDefault;

    if (!_dialCode.startsWith('+')) {
      _dialCode = '+$_dialCode';
    }

    String local = '';
    if (widget.initialValue != null && widget.initialValue!.isNotEmpty) {
      final raw = widget.initialValue!.trim();
      if (raw.startsWith('+')) {
        // Find longest matching dial code
        String? matchedCode;
        for (final entry in kCountryDialCodes.entries) {
          final code = entry.value;
          if (raw.startsWith(code)) {
            if (matchedCode == null || code.length > matchedCode.length) {
              matchedCode = code;
            }
          }
        }
        if (matchedCode != null) {
          _dialCode = matchedCode;
          local = raw.substring(matchedCode.length).replaceAll(RegExp(r'\D+'), '');
        } else {
          local = raw.substring(1).replaceAll(RegExp(r'\D+'), '');
        }
      } else {
        local = raw.replaceAll(RegExp(r'\D+'), '');
      }
    }

    // Strip leading trunk zeros
    while (local.startsWith('0') && local.length > 1) {
      local = local.substring(1);
    }

    if (widget.controller != null) {
      _controller = widget.controller!;
      if (local.isNotEmpty && _controller.text.isEmpty) {
        _controller.text = local;
      }
    } else {
      _internalController = true;
      _controller = TextEditingController(text: local);
    }

    _controller.addListener(_handleTextChange);
  }

  void _handleTextChange() {
    final text = _controller.text;

    // Handle pasted full number starting with '+'
    if (text.startsWith('+')) {
      String? matchedCode;
      for (final code in _popularDialCodes) {
        if (text.startsWith(code)) {
          if (matchedCode == null || code.length > matchedCode.length) {
            matchedCode = code;
          }
        }
      }
      if (matchedCode != null) {
        final remaining = text.substring(matchedCode.length).replaceAll(RegExp(r'\D+'), '');
        setState(() {
          _dialCode = matchedCode!;
        });
        _controller.value = TextEditingValue(
          text: remaining,
          selection: TextSelection.collapsed(offset: remaining.length),
        );
        _notifyChange();
        return;
      }
    }

    // Auto-strip leading trunk zeros
    if (text.startsWith('0') && text.length > 1) {
      final stripped = text.replaceFirst(RegExp(r'^0+'), '');
      _controller.value = TextEditingValue(
        text: stripped,
        selection: TextSelection.collapsed(offset: stripped.length),
      );
      _notifyChange();
      return;
    }

    _notifyChange();
  }

  void _notifyChange() {
    final local = _controller.text.trim().replaceAll(RegExp(r'\D+'), '');
    final fullE164 = local.isNotEmpty ? '$_dialCode$local' : '';
    widget.onChanged?.call(fullE164, local, _dialCode);
  }

  @override
  void dispose() {
    _controller.removeListener(_handleTextChange);
    _focusNode.dispose();
    if (_internalController) {
      _controller.dispose();
    }
    super.dispose();
  }

  String get _fullE164 {
    final local = _controller.text.trim().replaceAll(RegExp(r'\D+'), '');
    return local.isNotEmpty ? '$_dialCode$local' : '';
  }

  List<String> get _dialCodeChoices {
    final set = <String>{_dialCode, ..._popularDialCodes};
    return set.toList(growable: false);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final isFocused = _focusNode.hasFocus;

    final bg = widget.fillColor ??
        (isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC));
    final borderCol = widget.borderColor ??
        (isDark ? const Color(0xFF334155) : const Color(0xFFCBD5E1));
    final focusedCol = widget.focusedBorderColor ??
        (isDark ? const Color(0xFF38BDF8) : const Color(0xFF2563EB));
    final radius = widget.borderRadius ?? BorderRadius.circular(12);

    final defaultHint = _dialCode == '+91'
        ? '98765 43210'
        : (_dialCode == '+1' ? '(555) 234-5678' : 'Phone number');

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (widget.label != null) ...[
          Text(
            widget.label!,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: isDark ? const Color(0xFFE2E8F0) : const Color(0xFF1E293B),
            ),
          ),
          const SizedBox(height: 6),
        ],
        FormField<String>(
          initialValue: _controller.text,
          validator: (val) => widget.validator?.call(_controller.text, _fullE164),
          builder: (formState) {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  decoration: BoxDecoration(
                    color: bg,
                    borderRadius: radius,
                    border: Border.all(
                      color: formState.hasError
                          ? Colors.redAccent
                          : (isFocused ? focusedCol : borderCol),
                      width: (isFocused || formState.hasError) ? 1.5 : 1.0,
                    ),
                  ),
                  child: Row(
                    children: [
                      // Country Dial Code Selector
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10),
                        decoration: BoxDecoration(
                          border: Border(
                            right: BorderSide(color: borderCol, width: 1.0),
                          ),
                        ),
                        child: DropdownButtonHideUnderline(
                          child: DropdownButton<String>(
                            value: _dialCodeChoices.contains(_dialCode)
                                ? _dialCode
                                : _dialCodeChoices.first,
                            dropdownColor: isDark
                                ? const Color(0xFF1E293B)
                                : Colors.white,
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: isDark
                                  ? const Color(0xFFF1F5F9)
                                  : const Color(0xFF0F172A),
                            ),
                            icon: Icon(
                              Icons.arrow_drop_down,
                              size: 18,
                              color: isDark
                                  ? const Color(0xFF94A3B8)
                                  : const Color(0xFF64748B),
                            ),
                            items: _dialCodeChoices.map((code) {
                              final countryIso = countryForDialCode(code);
                              return DropdownMenuItem<String>(
                                value: code,
                                child: Text('$code ($countryIso)'),
                              );
                            }).toList(),
                            onChanged: widget.enabled && !widget.readOnly
                                ? (newCode) {
                                    if (newCode != null && newCode != _dialCode) {
                                      setState(() {
                                        _dialCode = newCode;
                                      });
                                      _notifyChange();
                                    }
                                  }
                                : null,
                          ),
                        ),
                      ),
                      // Local number text field
                      Expanded(
                        child: TextFormField(
                          controller: _controller,
                          focusNode: _focusNode,
                          enabled: widget.enabled,
                          readOnly: widget.readOnly,
                          autofocus: widget.autofocus,
                          keyboardType: TextInputType.phone,
                          inputFormatters: [
                            FilteringTextInputFormatter.allow(
                              RegExp(r'[\d\s\-\(\)\+]'),
                            ),
                          ],
                          style: widget.textStyle ??
                              TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w500,
                                color: isDark
                                    ? const Color(0xFFF8FAFC)
                                    : const Color(0xFF0F172A),
                              ),
                          decoration: InputDecoration(
                            hintText: widget.hint ?? defaultHint,
                            hintStyle: TextStyle(
                              fontSize: 13,
                              color: isDark
                                  ? const Color(0xFF64748B)
                                  : const Color(0xFF94A3B8),
                            ),
                            contentPadding: const EdgeInsets.symmetric(
                              horizontal: 14,
                              vertical: 12,
                            ),
                            border: InputBorder.none,
                            enabledBorder: InputBorder.none,
                            focusedBorder: InputBorder.none,
                            errorBorder: InputBorder.none,
                            focusedErrorBorder: InputBorder.none,
                          ),
                          onChanged: (val) {
                            formState.didChange(val);
                          },
                        ),
                      ),
                    ],
                  ),
                ),
                if (formState.hasError) ...[
                  const SizedBox(height: 4),
                  Padding(
                    padding: const EdgeInsets.only(left: 4),
                    child: Text(
                      formState.errorText!,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: Colors.redAccent,
                      ),
                    ),
                  ),
                ],
              ],
            );
          },
        ),
      ],
    );
  }
}
