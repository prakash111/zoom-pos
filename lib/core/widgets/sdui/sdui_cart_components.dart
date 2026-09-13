import 'package:flutter/material.dart';

import '../../sdui/models/sdui_models.dart';
import '../../utils/currency_formatter.dart';
import 'sdui_controls.dart';

/// Reusable server-driven cart summary component.
class SduiCartSummary extends StatelessWidget {
  const SduiCartSummary({
    super.key,
    required this.subtotal,
    required this.tax,
    required this.discount,
    required this.grandTotal,
    required this.formatter,
    this.taxConfig = const SduiTaxConfigSchema(),
    this.customDiscountLabel,
  });

  final double subtotal;
  final double tax;
  final double discount;
  final double grandTotal;
  final CurrencyFormatter formatter;
  final SduiTaxConfigSchema taxConfig;
  final String? customDiscountLabel;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      elevation: 0,
      color: theme.colorScheme.surfaceContainerLow,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5)),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Column(
          children: [
            _SummaryRow(
              label: 'Subtotal',
              value: formatter.format(subtotal),
              textStyle: theme.textTheme.bodyMedium,
            ),
            if (discount > 0) ...[
              const SizedBox(height: 6),
              _SummaryRow(
                label: customDiscountLabel ?? 'Discount',
                value: '-${formatter.format(discount)}',
                textColor: theme.brightness == Brightness.dark
                    ? const Color(0xFF34D399)
                    : Colors.green.shade700,
              ),
            ],
            if (tax > 0) ...[
              const SizedBox(height: 6),
              if (taxConfig.isIndia && taxConfig.subComponents.isNotEmpty) ...[
                for (final sub in taxConfig.subComponents)
                  _SummaryRow(
                    label: sub.label,
                    value: formatter.format(tax * sub.split),
                    textColor: theme.colorScheme.onSurfaceVariant,
                  ),
              ] else
                _SummaryRow(
                  label: taxConfig.taxLabel.isNotEmpty ? taxConfig.taxLabel : 'Tax',
                  value: formatter.format(tax),
                  textColor: theme.colorScheme.onSurfaceVariant,
                ),
            ],
            const Divider(height: 18),
            _SummaryRow(
              label: 'Grand Total',
              value: formatter.format(grandTotal),
              isBold: true,
              textStyle: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.bold,
                color: theme.colorScheme.primary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({
    required this.label,
    required this.value,
    this.textStyle,
    this.textColor,
    this.isBold = false,
  });

  final String label;
  final String value;
  final TextStyle? textStyle;
  final Color? textColor;
  final bool isBold;

  @override
  Widget build(BuildContext context) {
    final defaultStyle = textStyle ??
        TextStyle(
          fontSize: 13,
          fontWeight: isBold ? FontWeight.bold : FontWeight.normal,
          color: textColor,
        );

    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: defaultStyle),
        Text(value, style: defaultStyle),
      ],
    );
  }
}

/// Reusable cart line item component with step counter and remove action.
class SduiCartLineItem extends StatelessWidget {
  const SduiCartLineItem({
    super.key,
    required this.name,
    this.subtitle,
    required this.unitPrice,
    required this.quantity,
    required this.lineTotal,
    required this.formatter,
    required this.onQuantityChanged,
    required this.onRemove,
  });

  final String name;
  final String? subtitle;
  final double unitPrice;
  final int quantity;
  final double lineTotal;
  final CurrencyFormatter formatter;
  final ValueChanged<int> onQuantityChanged;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                if (subtitle != null && subtitle!.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(
                      subtitle!,
                      style: TextStyle(color: theme.colorScheme.onSurfaceVariant, fontSize: 12),
                    ),
                  ),
                const SizedBox(height: 4),
                Text(
                  formatter.format(unitPrice),
                  style: TextStyle(color: theme.colorScheme.primary, fontSize: 13),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          SduiStepCounter(
            value: quantity,
            onChanged: onQuantityChanged,
          ),
          const SizedBox(width: 12),
          SizedBox(
            width: 70,
            child: Text(
              formatter.format(lineTotal),
              textAlign: TextAlign.end,
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.close, size: 18, color: Colors.grey),
            onPressed: onRemove,
            visualDensity: VisualDensity.compact,
          ),
        ],
      ),
    );
  }
}

/// Reusable sticky cart bottom action container.
class SduiCartFooter extends StatelessWidget {
  const SduiCartFooter({
    super.key,
    required this.itemCount,
    required this.grandTotal,
    required this.formatter,
    required this.onCheckout,
    this.buttonLabel,
  });

  final int itemCount;
  final double grandTotal;
  final CurrencyFormatter formatter;
  final VoidCallback onCheckout;
  final String? buttonLabel;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return SafeArea(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          color: theme.colorScheme.surface,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.05),
              blurRadius: 10,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        child: ElevatedButton(
          style: ElevatedButton.styleFrom(
            padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
          onPressed: onCheckout,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  const Icon(Icons.shopping_cart_checkout),
                  const SizedBox(width: 8),
                  Text(
                    buttonLabel ?? 'View Cart',
                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
              Text(
                '${itemCount} ${itemCount == 1 ? 'item' : 'items'} · ${formatter.format(grandTotal)}',
                style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
