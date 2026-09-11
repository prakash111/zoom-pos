import 'package:flutter/material.dart';

/// A labelled, single-row, horizontally-scrolling group of minute presets
/// (`5m 10m 15m …`) plus a trailing custom numeric entry — used for both
/// "Prep time" and "Alert" on the restaurant order screen so neither ever
/// wraps onto a second row on a narrow phone, and either can be set to any
/// custom minute count, not just the presets.
class MinutesChipRow extends StatefulWidget {
  const MinutesChipRow({
    super.key,
    required this.label,
    required this.presets,
    required this.value,
    required this.onChanged,
  });

  final String label;

  /// (minutes, chip label) pairs, e.g. `(5, '5m')` or `(0, 'At expiry')`.
  final List<(int, String)> presets;
  final int value;
  final ValueChanged<int> onChanged;

  @override
  State<MinutesChipRow> createState() => _MinutesChipRowState();
}

class _MinutesChipRowState extends State<MinutesChipRow> {
  late final TextEditingController _customController;

  bool get _isPreset => widget.presets.any((p) => p.$1 == widget.value);

  @override
  void initState() {
    super.initState();
    _customController =
        TextEditingController(text: _isPreset ? '' : '${widget.value}');
  }

  @override
  void didUpdateWidget(covariant MinutesChipRow oldWidget) {
    super.didUpdateWidget(oldWidget);
    // A preset tap (or an external reset) clears the custom field so it goes
    // back to showing its placeholder instead of a stale typed number.
    if (widget.value != oldWidget.value && _isPreset) {
      _customController.clear();
    }
  }

  @override
  void dispose() {
    _customController.dispose();
    super.dispose();
  }

  void _applyCustom(String text) {
    final parsed = int.tryParse(text.trim());
    if (parsed == null || parsed <= 0) return;
    widget.onChanged(parsed);
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final isCustomActive = !_isPreset;

    return Row(
      children: [
        Text(widget.label,
            style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: scheme.onSurfaceVariant)),
        const SizedBox(width: 8),
        Expanded(
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                for (final preset in widget.presets) ...[
                  ChoiceChip(
                    label:
                        Text(preset.$2, style: const TextStyle(fontSize: 11)),
                    visualDensity: VisualDensity.compact,
                    selected: !isCustomActive && widget.value == preset.$1,
                    onSelected: (_) {
                      _customController.clear();
                      widget.onChanged(preset.$1);
                    },
                  ),
                  const SizedBox(width: 6),
                ],
                SizedBox(
                  width: 75,
                  height: 34,
                  child: TextField(
                    controller: _customController,
                    keyboardType: TextInputType.number,
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 12),
                    decoration: InputDecoration(
                      isDense: true,
                      hintText: 'Custom m',
                      hintStyle: TextStyle(
                          fontSize: 10, color: scheme.onSurfaceVariant),
                      contentPadding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 6),
                      filled: true,
                      fillColor: isCustomActive
                          ? scheme.primaryContainer
                          : scheme.surfaceContainerHighest,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(20),
                        borderSide: BorderSide.none,
                      ),
                    ),
                    onChanged: _applyCustom,
                    onSubmitted: _applyCustom,
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
