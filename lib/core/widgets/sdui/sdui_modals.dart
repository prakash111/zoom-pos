import 'package:flutter/material.dart';

/// Standardized action sheet bottom modal.
class SduiActionSheet extends StatelessWidget {
  const SduiActionSheet({
    super.key,
    required this.title,
    this.subtitle,
    this.icon,
    required this.child,
    this.actions = const [],
  });

  final String title;
  final String? subtitle;
  final IconData? icon;
  final Widget child;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                margin: const EdgeInsets.only(top: 10, bottom: 8),
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.grey.shade300,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
              child: Row(
                children: [
                  if (icon != null) ...[
                    Icon(icon, size: 22, color: theme.colorScheme.primary),
                    const SizedBox(width: 10),
                  ],
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                        ),
                        if (subtitle != null && subtitle!.isNotEmpty)
                          Text(
                            subtitle!,
                            style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                          ),
                      ],
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, size: 20),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Flexible(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: child,
              ),
            ),
            if (actions.isNotEmpty) ...[
              const Divider(height: 1),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: actions,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Standardized adaptive dialog with input field and quick action chips.
class SduiInputDialog extends StatelessWidget {
  const SduiInputDialog({
    super.key,
    required this.title,
    this.icon,
    required this.controller,
    this.hint,
    this.label,
    this.chips = const [],
    this.onChipSelected,
    this.onConfirm,
    this.confirmLabel = 'Save',
    this.cancelLabel = 'Cancel',
    this.keyboardType,
    this.maxLines = 1,
  });

  final String title;
  final IconData? icon;
  final TextEditingController controller;
  final String? hint;
  final String? label;
  final List<String> chips;
  final ValueChanged<String>? onChipSelected;
  final VoidCallback? onConfirm;
  final String confirmLabel;
  final String cancelLabel;
  final TextInputType? keyboardType;
  final int maxLines;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Row(
        children: [
          if (icon != null) ...[
            Icon(icon, size: 22),
            const SizedBox(width: 8),
          ],
          Expanded(child: Text(title)),
        ],
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            controller: controller,
            keyboardType: keyboardType,
            maxLines: maxLines,
            autofocus: true,
            decoration: InputDecoration(
              hintText: hint,
              labelText: label,
              border: const OutlineInputBorder(),
            ),
          ),
          if (chips.isNotEmpty) ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 6,
              children: [
                for (final chip in chips)
                  ActionChip(
                    label: Text(chip, style: const TextStyle(fontSize: 12)),
                    onPressed: () {
                      controller.text = chip;
                      onChipSelected?.call(chip);
                    },
                  ),
              ],
            ),
          ],
        ],
      ),
      actionsPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: Text(cancelLabel),
        ),
        ElevatedButton(
          onPressed: () {
            onConfirm?.call();
            Navigator.of(context).pop();
          },
          child: Text(confirmLabel),
        ),
      ],
    );
  }
}
