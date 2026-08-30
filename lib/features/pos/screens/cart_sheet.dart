import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/models/customer_model.dart';
import '../../../core/models/settings_models.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../auth/auth_provider.dart';
import '../../customers/customers_repository.dart';
import '../pos_provider.dart';
import 'customer_picker_sheet.dart';

/// The modernized POS Cart & Checkout sheet supporting dynamic payment options,
/// action pills (Hold, Customer, Note, More), and country-wise GST tax breakdown.
class CartSheet extends StatelessWidget {
  const CartSheet({super.key, required this.customersRepository});

  final CustomersRepository customersRepository;

  Future<void> _pickCustomer(BuildContext context) async {
    final pos = context.read<PosProvider>();
    final customer = await showModalBottomSheet<CustomerModel>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => CustomerPickerSheet(customersRepository: customersRepository),
    );
    if (customer != null) {
      pos.setCustomer(customer);
    }
  }

  void _showNotesDialog(BuildContext context) {
    final pos = context.read<PosProvider>();
    final controller = TextEditingController(text: pos.orderNotes);

    showDialog(
      context: context,
      builder: (dialogCtx) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.edit_note, size: 22),
            SizedBox(width: 8),
            Text('Order Notes & Remarks'),
          ],
        ),
        content: TextField(
          controller: controller,
          maxLines: 3,
          autofocus: true,
          decoration: const InputDecoration(
            hintText: 'e.g. Special packaging, delivery note, invoice memo',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogCtx).pop(),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              pos.setOrderNotes(controller.text.trim());
              Navigator.of(dialogCtx).pop();
            },
            child: const Text('Save Note'),
          ),
        ],
      ),
    );
  }

  void _showDiscountDialog(BuildContext context) {
    final pos = context.read<PosProvider>();
    final controller = TextEditingController(
      text: pos.customDiscount > 0 ? pos.customDiscount.toStringAsFixed(0) : '',
    );
    bool isPercent = pos.isPercentDiscount;

    showDialog(
      context: context,
      builder: (dialogCtx) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Row(
            children: [
              Icon(Icons.local_offer_outlined, size: 22),
              SizedBox(width: 8),
              Text('Apply Discount'),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Expanded(
                    child: ChoiceChip(
                      label: const Text('Fixed Amount'),
                      selected: !isPercent,
                      onSelected: (_) => setDialogState(() => isPercent = false),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: ChoiceChip(
                      label: const Text('Percentage (%)'),
                      selected: isPercent,
                      onSelected: (_) => setDialogState(() => isPercent = true),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              TextField(
                controller: controller,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                autofocus: true,
                decoration: InputDecoration(
                  labelText: isPercent ? 'Discount Percentage' : 'Discount Amount',
                  suffixText: isPercent ? '%' : '',
                  prefixIcon: const Icon(Icons.percent),
                ),
              ),
            ],
          ),
          actions: [
            if (pos.customDiscount > 0)
              TextButton(
                onPressed: () {
                  pos.setDiscount(0);
                  Navigator.of(dialogCtx).pop();
                },
                child: const Text('Remove Discount', style: TextStyle(color: Colors.red)),
              ),
            TextButton(
              onPressed: () => Navigator.of(dialogCtx).pop(),
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () {
                final val = double.tryParse(controller.text.trim()) ?? 0.0;
                pos.setDiscount(val, isPercent: isPercent);
                Navigator.of(dialogCtx).pop();
              },
              child: const Text('Apply'),
            ),
          ],
        ),
      ),
    );
  }

  void _showHoldCartsSheet(BuildContext context) {
    final pos = context.read<PosProvider>();
    final company = context.read<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (sheetCtx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Held Orders (${pos.heldCarts.length})',
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  if (!pos.cartIsEmpty)
                    TextButton.icon(
                      onPressed: () {
                        pos.holdCurrentCart();
                        Navigator.of(sheetCtx).pop();
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Current cart put on hold.')),
                        );
                      },
                      icon: const Icon(Icons.pause_circle_outline),
                      label: const Text('Hold Current Cart'),
                    ),
                ],
              ),
              const Divider(),
              if (pos.heldCarts.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 24),
                  child: Center(child: Text('No held orders at this time.')),
                )
              else
                Flexible(
                  child: ListView.separated(
                    shrinkWrap: true,
                    itemCount: pos.heldCarts.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (context, index) {
                      final held = pos.heldCarts[index];
                      return ListTile(
                        leading: const CircleAvatar(
                          child: Icon(Icons.shopping_cart_outlined),
                        ),
                        title: Text(held.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                        subtitle: Text(
                          '${held.itemCount} items · Total: ${formatter.format(held.total)}',
                        ),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              icon: const Icon(Icons.delete_outline, color: Colors.red),
                              onPressed: () => pos.deleteHeldCart(held.id),
                            ),
                            ElevatedButton(
                              onPressed: () {
                                pos.resumeHeldCart(held);
                                Navigator.of(sheetCtx).pop();
                              },
                              child: const Text('Resume'),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _checkout(BuildContext context) async {
    final pos = context.read<PosProvider>();
    final messenger = ScaffoldMessenger.of(context);
    final result = await pos.checkout();
    if (!context.mounted) return;

    if (result != null) {
      Navigator.of(context).pop(result);
    } else if (pos.checkoutError != null) {
      messenger.showSnackBar(SnackBar(content: Text(pos.checkoutError!)));
    }
  }

  IconData _iconForMethod(String code) {
    final c = code.toLowerCase();
    if (c.contains('cash')) return Icons.payments_outlined;
    if (c.contains('card') || c.contains('debit') || c.contains('credit_card')) return Icons.credit_card_outlined;
    if (c.contains('upi') || c.contains('qr') || c.contains('gpay') || c.contains('phonepe')) return Icons.qr_code_2_outlined;
    if (c.contains('credit') || c.contains('due') || c.contains('khata')) return Icons.schedule_outlined;
    if (c.contains('bank') || c.contains('transfer')) return Icons.account_balance_outlined;
    return Icons.account_balance_wallet_outlined;
  }

  Color _colorForMethod(String code, Color defaultPrimary) {
    final c = code.toLowerCase();
    if (c.contains('cash')) return Colors.green.shade700;
    if (c.contains('card')) return Colors.blue.shade700;
    if (c.contains('upi') || c.contains('qr')) return Colors.purple.shade700;
    if (c.contains('credit') || c.contains('due')) return Colors.amber.shade800;
    if (c.contains('bank')) return Colors.teal.shade700;
    return defaultPrimary;
  }

  @override
  Widget build(BuildContext context) {
    final pos = context.watch<PosProvider>();
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');
    final isIndia = company?.isIndia ?? false;
    final primaryColor = Theme.of(context).colorScheme.primary;

    // Load active payment methods dynamically
    final activeMethods = pos.paymentMethods.isNotEmpty
        ? pos.paymentMethods.where((p) => p.isActive).toList()
        : [
            PaymentMethodModel(id: 'cash', name: 'Cash', code: 'cash', description: '', isActive: true, orderIndex: 0),
            PaymentMethodModel(id: 'card', name: 'Card', code: 'card', description: '', isActive: true, orderIndex: 1),
            PaymentMethodModel(id: 'upi', name: 'UPI', code: 'upi', description: '', isActive: true, orderIndex: 2),
            PaymentMethodModel(id: 'credit', name: 'Credit', code: 'credit', description: '', isActive: true, orderIndex: 3),
            PaymentMethodModel(id: 'other', name: 'Other', code: 'other', description: '', isActive: true, orderIndex: 4),
          ];

    return DraggableScrollableSheet(
      initialChildSize: 0.85,
      minChildSize: 0.5,
      maxChildSize: 0.95,
      expand: false,
      builder: (context, scrollController) {
        return Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
          child: Column(
            children: [
              const SizedBox(height: 12),
              Container(
                width: 44,
                height: 5,
                decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(3)),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        Text('Order Cart', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: primaryColor.withOpacity(0.12),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            '${pos.cartCount} items',
                            style: TextStyle(color: primaryColor, fontSize: 12, fontWeight: FontWeight.bold),
                          ),
                        ),
                      ],
                    ),
                    if (!pos.cartIsEmpty)
                      TextButton.icon(
                        onPressed: pos.clearCart,
                        icon: const Icon(Icons.delete_outline, size: 18),
                        label: const Text('Clear Cart'),
                        style: TextButton.styleFrom(foregroundColor: Colors.red.shade600),
                      ),
                  ],
                ),
              ),
              const Divider(height: 1),

              // Items List
              Expanded(
                child: pos.cartItems.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.shopping_cart_outlined, size: 56, color: Colors.grey.shade300),
                            const SizedBox(height: 12),
                            Text('Your cart is empty', style: TextStyle(color: Colors.grey.shade600, fontSize: 16)),
                            const SizedBox(height: 4),
                            Text('Tap on products to add them to this sale.', style: TextStyle(color: Colors.grey.shade400, fontSize: 12)),
                          ],
                        ),
                      )
                    : ListView.separated(
                        controller: scrollController,
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        itemCount: pos.cartItems.length,
                        separatorBuilder: (_, __) => const Divider(height: 1),
                        itemBuilder: (context, index) {
                          final item = pos.cartItems[index];
                          return Padding(
                            padding: const EdgeInsets.symmetric(vertical: 6),
                            child: Row(
                              children: [
                                Expanded(
                                  flex: 3,
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        item.product.name,
                                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        '${formatter.format(item.product.salePrice)} / ${item.product.unit}',
                                        style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                                      ),
                                      if (item.product.taxRate > 0)
                                        Text(
                                          '${isIndia ? 'GST' : 'Tax'} (${item.product.taxRate.toStringAsFixed(0)}%): +${formatter.format(item.taxAmount)}',
                                          style: TextStyle(color: Colors.grey.shade500, fontSize: 11),
                                        ),
                                    ],
                                  ),
                                ),
                                Container(
                                  decoration: BoxDecoration(
                                    color: Colors.grey.shade100,
                                    borderRadius: BorderRadius.circular(20),
                                  ),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      IconButton(
                                        icon: const Icon(Icons.remove, size: 16),
                                        padding: const EdgeInsets.all(4),
                                        constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
                                        onPressed: () => pos.decrementQuantity(item.product.id),
                                      ),
                                      Padding(
                                        padding: const EdgeInsets.symmetric(horizontal: 6),
                                        child: Text(
                                          item.quantity.toStringAsFixed(item.quantity == item.quantity.roundToDouble() ? 0 : 2),
                                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                        ),
                                      ),
                                      IconButton(
                                        icon: const Icon(Icons.add, size: 16),
                                        padding: const EdgeInsets.all(4),
                                        constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
                                        onPressed: () => pos.incrementQuantity(item.product.id),
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(width: 12),
                                SizedBox(
                                  width: 75,
                                  child: Text(
                                    formatter.format(item.lineTotal),
                                    textAlign: TextAlign.right,
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
              ),

              const Divider(height: 1),

              // Bottom Section
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Action Pills: Hold, Customer, Note, Discount / More
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children: [
                          // Customer Pill
                          ActionChip(
                            avatar: Icon(Icons.person_outline, size: 16, color: pos.selectedCustomer != null ? primaryColor : Colors.grey.shade700),
                            label: Text(pos.selectedCustomer?.name ?? 'Add Customer'),
                            backgroundColor: pos.selectedCustomer != null ? primaryColor.withOpacity(0.12) : null,
                            onPressed: () => _pickCustomer(context),
                          ),
                          const SizedBox(width: 8),

                          // Hold Cart Pill
                          ActionChip(
                            avatar: Icon(Icons.pause_circle_outline, size: 16, color: pos.heldCarts.isNotEmpty ? Colors.orange.shade800 : Colors.grey.shade700),
                            label: Text(pos.heldCarts.isNotEmpty ? 'Held (${pos.heldCarts.length})' : 'Hold'),
                            backgroundColor: pos.heldCarts.isNotEmpty ? Colors.orange.shade50 : null,
                            onPressed: () => _showHoldCartsSheet(context),
                          ),
                          const SizedBox(width: 8),

                          // Note Pill
                          ActionChip(
                            avatar: Icon(Icons.edit_note, size: 16, color: pos.orderNotes.isNotEmpty ? primaryColor : Colors.grey.shade700),
                            label: Text(pos.orderNotes.isNotEmpty ? 'Note ✓' : 'Note'),
                            backgroundColor: pos.orderNotes.isNotEmpty ? primaryColor.withOpacity(0.12) : null,
                            onPressed: () => _showNotesDialog(context),
                          ),
                          const SizedBox(width: 8),

                          // Discount Pill
                          ActionChip(
                            avatar: Icon(Icons.local_offer_outlined, size: 16, color: pos.customDiscount > 0 ? Colors.green.shade800 : Colors.grey.shade700),
                            label: Text(pos.customDiscount > 0 ? 'Discount ✓' : 'Discount'),
                            backgroundColor: pos.customDiscount > 0 ? Colors.green.shade50 : null,
                            onPressed: () => _showDiscountDialog(context),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 12),

                    // Dynamic Payment Selection Tiles
                    Text(
                      'Payment Method',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Colors.grey.shade600),
                    ),
                    const SizedBox(height: 6),
                    SizedBox(
                      height: 42,
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        itemCount: activeMethods.length,
                        separatorBuilder: (_, __) => const SizedBox(width: 8),
                        itemBuilder: (context, idx) {
                          final method = activeMethods[idx];
                          final code = method.code.isNotEmpty ? method.code : method.id;
                          final isSelected = pos.paymentMethod == code || pos.paymentMethod == method.id;
                          final methodColor = _colorForMethod(code, primaryColor);
                          final methodIcon = _iconForMethod(code);

                          return InkWell(
                            borderRadius: BorderRadius.circular(10),
                            onTap: () => pos.setPaymentMethod(code),
                            child: AnimatedContainer(
                              duration: const Duration(milliseconds: 150),
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                              decoration: BoxDecoration(
                                color: isSelected ? methodColor.withOpacity(0.15) : Colors.grey.shade100,
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(
                                  color: isSelected ? methodColor : Colors.grey.shade300,
                                  width: isSelected ? 2 : 1,
                                ),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(methodIcon, size: 18, color: isSelected ? methodColor : Colors.grey.shade700),
                                  const SizedBox(width: 6),
                                  Text(
                                    method.name,
                                    style: TextStyle(
                                      color: isSelected ? methodColor : Colors.grey.shade800,
                                      fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                                      fontSize: 13,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
                    ),

                    const SizedBox(height: 12),

                    // Financial Summary Hierarchy
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.grey.shade50,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.grey.shade200),
                      ),
                      child: Column(
                        children: [
                          _TotalsRow(label: 'Subtotal', value: formatter.format(pos.subtotal)),
                          if (pos.discount > 0)
                            _TotalsRow(
                              label: 'Discount',
                              value: '-${formatter.format(pos.discount)}',
                              valueColor: Colors.red.shade600,
                            ),
                          if (pos.taxTotal > 0) ...[
                            if (isIndia) ...[
                              _TotalsRow(
                                label: 'CGST',
                                value: '+${formatter.format(pos.taxTotal / 2)}',
                                isSub: true,
                              ),
                              _TotalsRow(
                                label: 'SGST',
                                value: '+${formatter.format(pos.taxTotal / 2)}',
                                isSub: true,
                              ),
                            ] else
                              _TotalsRow(
                                label: company?.taxLabel ?? 'Tax',
                                value: '+${formatter.format(pos.taxTotal)}',
                              ),
                          ],
                          const Divider(height: 12),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text('Grand Total', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                  if ((company?.taxId ?? '').isNotEmpty)
                                    Text(
                                      '${isIndia ? 'GSTIN' : 'Tax ID'}: ${company!.taxId}',
                                      style: TextStyle(color: Colors.grey.shade500, fontSize: 10),
                                    ),
                                ],
                              ),
                              Text(
                                formatter.format(pos.grandTotal),
                                style: TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 20,
                                  color: primaryColor,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 12),

                    // Checkout Button
                    ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      onPressed: pos.cartIsEmpty || pos.isCheckingOut ? null : () => _checkout(context),
                      child: pos.isCheckingOut
                          ? const SizedBox(
                              height: 22,
                              width: 22,
                              child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                            )
                          : Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                const Icon(Icons.check_circle_outline, size: 20),
                                const SizedBox(width: 8),
                                Text(
                                  'Complete Sale · ${formatter.format(pos.grandTotal)}',
                                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                                ),
                              ],
                            ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _TotalsRow extends StatelessWidget {
  const _TotalsRow({
    required this.label,
    required this.value,
    this.valueColor,
    this.isSub = false,
  });

  final String label;
  final String value;
  final Color? valueColor;
  final bool isSub;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.symmetric(vertical: isSub ? 1 : 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            isSub ? '  └ $label' : label,
            style: TextStyle(
              color: isSub ? Colors.grey.shade500 : Colors.grey.shade700,
              fontSize: isSub ? 11 : 13,
            ),
          ),
          Text(
            value,
            style: TextStyle(
              color: valueColor ?? (isSub ? Colors.grey.shade600 : Colors.grey.shade900),
              fontWeight: isSub ? FontWeight.normal : FontWeight.w600,
              fontSize: isSub ? 11 : 13,
            ),
          ),
        ],
      ),
    );
  }
}
