import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/models/customer_model.dart';
import '../../../core/models/settings_models.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/auth_provider.dart';
import '../../customers/customers_repository.dart';
import '../pos_provider.dart';
import 'customer_picker_sheet.dart';
import 'invoice_preview_screen.dart';

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
      useSafeArea: true,
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
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        content: TextField(
          controller: controller,
          maxLines: 3,
          autofocus: true,
          decoration: const InputDecoration(
            hintText: 'e.g. Special packaging, delivery note, invoice memo',
            border: OutlineInputBorder(),
          ),
        ),
        actionsPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        actions: [
          OverflowBar(
            spacing: 8,
            overflowSpacing: 8,
            children: [
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
          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
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
          actionsPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          actions: [
            OverflowBar(
              spacing: 8,
              overflowSpacing: 8,
              children: [
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
          ],
        ),
      ),
    );
  }

  void _showHoldCartsSheet(BuildContext context) {
    final pos = context.read<PosProvider>();
    final company = context.read<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');
    final l10n = AppLocalizations.of(context);

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
                    l10n.heldOrdersTitle(pos.heldCarts.length),
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  if (!pos.cartIsEmpty)
                    TextButton.icon(
                      onPressed: () {
                        pos.holdCurrentCart();
                        Navigator.of(sheetCtx).pop();
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(l10n.cartPutOnHold)),
                        );
                      },
                      icon: const Icon(Icons.pause_circle_outline),
                      label: Text(l10n.holdCurrentCart),
                    ),
                ],
              ),
              const Divider(),
              if (pos.heldCarts.isEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 24),
                  child: Center(child: Text(l10n.noHeldOrders)),
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
                        subtitle: Text(l10n.heldCartSubtitle(held.itemCount, formatter.format(held.total))),
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
                              child: Text(l10n.resume),
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

  Future<void> _previewThenCheckout(BuildContext context) async {
    final pos = context.read<PosProvider>();
    final company = context.read<AuthProvider>().company;
    final isIndia = company?.isIndia ?? false;

    final confirmed = await showInvoicePreview(
      context,
      InvoicePreviewData(
        documentType: 'Invoice',
        companyName: company?.tradeName ?? company?.name ?? '',
        items: pos.cartItems,
        subtotal: pos.subtotal,
        discount: pos.discount,
        taxTotal: pos.taxTotal,
        grandTotal: pos.grandTotal,
        customerName: pos.selectedCustomer?.name,
        notes: pos.orderNotes,
        currencySymbol: company?.currencySymbol ?? '\$',
        taxId: company?.taxId,
        taxLabel: company?.taxLabel ?? 'Tax',
        isIndia: isIndia,
        paidAmount: pos.amountPaid,
        dueAmount: pos.dueAmount,
      ),
    );

    if (confirmed != true || !context.mounted) return;
    await _checkout(context);
  }

  Future<void> _checkout(BuildContext context) async {
    final pos = context.read<PosProvider>();
    final company = context.read<AuthProvider>().company;
    final messenger = ScaffoldMessenger.of(context);
    final result = await pos.checkout(taxLabel: company?.taxLabel);
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

  /// Bank/UPI metadata (bank_name, account_no, ifsc_code, upi_id,
  /// holder_name) configured for the currently-selected payment method, if
  /// it looks like a bank transfer or UPI method and has any details set.
  Map<String, dynamic>? _bankMetadataFor(List<PaymentMethodModel> methods, String selectedCode) {
    final match = methods.where((m) => (m.code.isNotEmpty ? m.code : m.id) == selectedCode || m.id == selectedCode);
    if (match.isEmpty) return null;
    final method = match.first;
    final code = (method.code.isNotEmpty ? method.code : method.id).toLowerCase();
    if (!code.contains('bank') && !code.contains('transfer') && !code.contains('upi')) return null;
    final metadata = method.metadata;
    if (metadata == null || metadata.values.every((v) => v == null || v.toString().isEmpty)) return null;
    return metadata;
  }

  Future<void> _showAmountPaidDialog(BuildContext context) async {
    final pos = context.read<PosProvider>();
    final company = context.read<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');
    final controller = TextEditingController(text: pos.amountPaid.toStringAsFixed(2));

    await showDialog(
      context: context,
      builder: (dialogCtx) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.price_check, size: 22),
            SizedBox(width: 8),
            Text('Amount Paid'),
          ],
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Grand Total: ${formatter.format(pos.grandTotal)}', style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              autofocus: true,
              decoration: InputDecoration(
                labelText: 'Amount Paid Now',
                prefixText: company?.currencySymbol ?? '\$',
                border: const OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              children: [
                ActionChip(
                  label: const Text('Full Amount'),
                  onPressed: () => controller.text = pos.grandTotal.toStringAsFixed(2),
                ),
                ActionChip(
                  label: const Text('Zero Payment (Full Due)'),
                  onPressed: () => controller.text = '0',
                ),
              ],
            ),
          ],
        ),
        actionsPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogCtx).pop(), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              final val = double.tryParse(controller.text.trim()) ?? pos.grandTotal;
              pos.setAmountPaid(val);
              Navigator.of(dialogCtx).pop();
            },
            child: const Text('Apply'),
          ),
        ],
      ),
    );
  }

  Future<void> _openSplitPaymentEditor(BuildContext context, List<PaymentMethodModel> activeMethods) async {
    final pos = context.read<PosProvider>();
    final company = context.read<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    if (!pos.isSplitPayment) {
      pos.toggleSplitPayment();
    }
    if (pos.payments.isEmpty) {
      pos.addSplitRow();
    }

    final controllers = <TextEditingController>[
      for (final p in pos.payments) TextEditingController(text: p.amount.toStringAsFixed(2)),
    ];

    await showDialog(
      context: context,
      builder: (dialogCtx) => StatefulBuilder(
        builder: (context, setDialogState) {
          while (controllers.length < pos.payments.length) {
            controllers.add(TextEditingController(text: pos.payments[controllers.length].amount.toStringAsFixed(2)));
          }
          while (controllers.length > pos.payments.length) {
            controllers.removeLast().dispose();
          }

          String codeFor(int i) => activeMethods.any((m) => (m.code.isNotEmpty ? m.code : m.id) == pos.payments[i].methodCode)
              ? pos.payments[i].methodCode
              : (activeMethods.isNotEmpty ? (activeMethods.first.code.isNotEmpty ? activeMethods.first.code : activeMethods.first.id) : 'cash');

          return AlertDialog(
            title: const Text('Split Payment'),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            content: SizedBox(
              width: double.maxFinite,
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    for (int i = 0; i < pos.payments.length; i++)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: Row(
                          children: [
                            Expanded(
                              flex: 3,
                              child: DropdownButtonFormField<String>(
                                value: codeFor(i),
                                isExpanded: true,
                                items: [
                                  for (final m in activeMethods)
                                    DropdownMenuItem(
                                      value: m.code.isNotEmpty ? m.code : m.id,
                                      child: Text(m.name, overflow: TextOverflow.ellipsis),
                                    ),
                                ],
                                onChanged: (val) {
                                  if (val == null) return;
                                  pos.updateSplitRow(i, methodCode: val);
                                  setDialogState(() {});
                                },
                                decoration: const InputDecoration(isDense: true, contentPadding: EdgeInsets.symmetric(horizontal: 8, vertical: 8)),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              flex: 2,
                              child: TextField(
                                controller: controllers[i],
                                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                decoration: InputDecoration(isDense: true, prefixText: company?.currencySymbol ?? '\$', border: const OutlineInputBorder()),
                                onChanged: (val) {
                                  pos.updateSplitRow(i, amount: double.tryParse(val) ?? 0);
                                  setDialogState(() {});
                                },
                              ),
                            ),
                            IconButton(
                              icon: const Icon(Icons.remove_circle_outline, color: Colors.red),
                              onPressed: pos.payments.length <= 1
                                  ? null
                                  : () {
                                      pos.removeSplitRow(i);
                                      setDialogState(() {});
                                    },
                            ),
                          ],
                        ),
                      ),
                    Align(
                      alignment: Alignment.centerLeft,
                      child: TextButton.icon(
                        onPressed: () {
                          pos.addSplitRow();
                          setDialogState(() {});
                        },
                        icon: const Icon(Icons.add),
                        label: const Text('Add Payment Row'),
                      ),
                    ),
                    const Divider(),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Remaining Due', style: TextStyle(fontWeight: FontWeight.bold)),
                        Text(formatter.format(pos.remainingSplitBalance), style: const TextStyle(fontWeight: FontWeight.bold)),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            actionsPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            actions: [
              TextButton(
                onPressed: () {
                  pos.toggleSplitPayment();
                  Navigator.of(dialogCtx).pop();
                },
                child: const Text('Cancel Split', style: TextStyle(color: Colors.red)),
              ),
              ElevatedButton(onPressed: () => Navigator.of(dialogCtx).pop(), child: const Text('Done')),
            ],
          );
        },
      ),
    );

    for (final c in controllers) {
      c.dispose();
    }
  }

  @override
  Widget build(BuildContext context) {
    final pos = context.watch<PosProvider>();
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');
    final isIndia = company?.isIndia ?? false;
    final primaryColor = Theme.of(context).colorScheme.primary;
    final l10n = AppLocalizations.of(context);

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
          padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom + 20),
          child: SafeArea(
            top: false,
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
                        Text(l10n.orderCart, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: primaryColor.withOpacity(0.12),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            l10n.itemsCountBadge(pos.cartCount),
                            style: TextStyle(color: primaryColor, fontSize: 12, fontWeight: FontWeight.bold),
                          ),
                        ),
                      ],
                    ),
                    if (!pos.cartIsEmpty)
                      TextButton.icon(
                        onPressed: pos.clearCart,
                        icon: const Icon(Icons.delete_outline, size: 18),
                        label: Text(l10n.clearCart),
                        style: TextButton.styleFrom(foregroundColor: Colors.red.shade600),
                      ),
                  ],
                ),
              ),
              const Divider(height: 1),

              // Items List + Bottom Section share one scroll view (with the
              // sheet's own scrollController) so the cash-tender field,
              // preset chips, totals, and Complete Sale button are never
              // stranded below the keyboard or the sheet's bottom edge —
              // they scroll into view instead of being clipped.
              Expanded(
                child: SingleChildScrollView(
                  controller: scrollController,
                  physics: const ClampingScrollPhysics(),
                  child: Column(
                    children: [
                      pos.cartItems.isEmpty
                          ? SizedBox(
                              height: 260,
                              child: Center(
                                child: Column(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    Icon(Icons.shopping_cart_outlined, size: 56, color: Colors.grey.shade300),
                                    const SizedBox(height: 12),
                                    Text(l10n.cartEmptyTitle, style: TextStyle(color: Colors.grey.shade600, fontSize: 16)),
                                    const SizedBox(height: 4),
                                    Text(l10n.cartEmptySubtitle, style: TextStyle(color: Colors.grey.shade400, fontSize: 12)),
                                  ],
                                ),
                              ),
                            )
                          : ListView.separated(
                              shrinkWrap: true,
                              physics: const NeverScrollableScrollPhysics(),
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

                      const Divider(height: 1),

                      // Bottom Section
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                    // Action Pills: Hold, Customer, Note, Discount / More
                    // A Wrap instead of a horizontal scroller — same reason
                    // as the payment method tiles below: with four pills
                    // (plus their variable-width selected-state labels) this
                    // reliably overflowed the 400px cart panel and left
                    // "Note"/"Discount" clipped off-screen with no visible
                    // scroll affordance. Wrapping to a second line keeps
                    // every pill visible without requiring a swipe.
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        // Customer Pill
                        ActionChip(
                          avatar: Icon(Icons.person_outline, size: 16, color: pos.selectedCustomer != null ? primaryColor : Colors.grey.shade700),
                          label: Text(pos.selectedCustomer?.name ?? l10n.addCustomer),
                          backgroundColor: pos.selectedCustomer != null ? primaryColor.withOpacity(0.12) : null,
                          onPressed: () => _pickCustomer(context),
                        ),

                        // Hold Cart Pill
                        ActionChip(
                          avatar: Icon(Icons.pause_circle_outline, size: 16, color: pos.heldCarts.isNotEmpty ? Colors.orange.shade800 : Colors.grey.shade700),
                          label: Text(pos.heldCarts.isNotEmpty ? l10n.heldChip(pos.heldCarts.length) : l10n.hold),
                          backgroundColor: pos.heldCarts.isNotEmpty ? Colors.orange.shade50 : null,
                          onPressed: () => _showHoldCartsSheet(context),
                        ),

                        // Note Pill
                        ActionChip(
                          avatar: Icon(Icons.edit_note, size: 16, color: pos.orderNotes.isNotEmpty ? primaryColor : Colors.grey.shade700),
                          label: Text(pos.orderNotes.isNotEmpty ? l10n.noteChecked : l10n.note),
                          backgroundColor: pos.orderNotes.isNotEmpty ? primaryColor.withOpacity(0.12) : null,
                          onPressed: () => _showNotesDialog(context),
                        ),

                        // Discount Pill
                        ActionChip(
                          avatar: Icon(Icons.local_offer_outlined, size: 16, color: pos.customDiscount > 0 ? Colors.green.shade800 : Colors.grey.shade700),
                          label: Text(pos.customDiscount > 0 ? l10n.discountChecked : l10n.discount),
                          backgroundColor: pos.customDiscount > 0 ? Colors.green.shade50 : null,
                          onPressed: () => _showDiscountDialog(context),
                        ),

                        // Split Payment Pill
                        ActionChip(
                          avatar: Icon(Icons.call_split, size: 16, color: pos.isSplitPayment ? primaryColor : Colors.grey.shade700),
                          label: Text(pos.isSplitPayment ? 'Split (${pos.payments.length})' : 'Split Payment'),
                          backgroundColor: pos.isSplitPayment ? primaryColor.withOpacity(0.12) : null,
                          onPressed: () => _openSplitPaymentEditor(context, activeMethods),
                        ),

                        // Amount Paid Pill (only meaningful outside split mode)
                        if (!pos.isSplitPayment)
                          ActionChip(
                            avatar: Icon(Icons.price_check, size: 16, color: pos.dueAmount > 0.001 ? Colors.amber.shade800 : Colors.grey.shade700),
                            label: Text(pos.dueAmount > 0.001 ? 'Paid: ${formatter.format(pos.amountPaid)}' : 'Amount Paid'),
                            backgroundColor: pos.dueAmount > 0.001 ? Colors.amber.shade50 : null,
                            onPressed: () => _showAmountPaidDialog(context),
                          ),
                      ],
                    ),

                    if (pos.requiresCustomerForDue)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(
                          'Attach a customer for due, partial, or credit sales.',
                          style: TextStyle(color: Colors.red.shade600, fontSize: 11, fontWeight: FontWeight.w600),
                        ),
                      ),

                    const SizedBox(height: 12),

                    // Dynamic Payment Selection Tiles
                    if (pos.isSplitPayment)
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: primaryColor.withOpacity(0.06),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: primaryColor.withOpacity(0.25)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Split Payment (${pos.payments.length} methods)',
                                style: TextStyle(fontWeight: FontWeight.bold, color: primaryColor, fontSize: 13)),
                            const SizedBox(height: 4),
                            for (final p in pos.payments)
                              Text('• ${p.methodCode} — ${formatter.format(p.amount)}', style: const TextStyle(fontSize: 12)),
                            Align(
                              alignment: Alignment.centerRight,
                              child: TextButton(
                                onPressed: () => _openSplitPaymentEditor(context, activeMethods),
                                child: const Text('Edit Split'),
                              ),
                            ),
                          ],
                        ),
                      )
                    else ...[
                      Text(
                        l10n.paymentMethod,
                        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Colors.grey.shade600),
                      ),
                      const SizedBox(height: 6),
                      // A Wrap instead of a fixed-height horizontal scroller —
                      // with 4-5 payment methods this app's default set (or a
                      // tenant's longer custom list) doesn't reliably fit one
                      // row width, and a scroller left the last method (e.g.
                      // "UPI") visually clipped to a single letter with no
                      // scroll affordance. Wrapping to a second line keeps
                      // every method visible without requiring a swipe.
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          for (final method in activeMethods)
                            _PaymentMethodChip(
                              method: method,
                              isSelected: pos.paymentMethod == (method.code.isNotEmpty ? method.code : method.id) ||
                                  pos.paymentMethod == method.id,
                              color: _colorForMethod(method.code.isNotEmpty ? method.code : method.id, primaryColor),
                              icon: _iconForMethod(method.code.isNotEmpty ? method.code : method.id),
                              onTap: () => pos.setPaymentMethod(method.code.isNotEmpty ? method.code : method.id),
                            ),
                        ],
                      ),

                      if (_bankMetadataFor(activeMethods, pos.paymentMethod) != null) ...[
                        const SizedBox(height: 10),
                        _BankDetailsBox(metadata: _bankMetadataFor(activeMethods, pos.paymentMethod)!),
                      ],

                      if (pos.paymentMethod == 'cash') ...[
                        const SizedBox(height: 12),
                        _CashTenderSection(payableAmount: pos.amountPaid, currencySymbol: company?.currencySymbol ?? '\$'),
                      ],
                    ],

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
                          _TotalsRow(label: l10n.subtotal, value: formatter.format(pos.subtotal)),
                          if (pos.discount > 0)
                            _TotalsRow(
                              label: l10n.discount,
                              value: '-${formatter.format(pos.discount)}',
                              valueColor: Colors.red.shade600,
                            ),
                          if (pos.taxTotal > 0) ...[
                            if (isIndia) ...[
                              _TotalsRow(
                                label: l10n.cgst,
                                value: '+${formatter.format(pos.taxTotal / 2)}',
                                isSub: true,
                              ),
                              _TotalsRow(
                                label: l10n.sgst,
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
                                  Text(l10n.grandTotal, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                  if ((company?.taxId ?? '').isNotEmpty)
                                    Text(
                                      '${isIndia ? l10n.gstin : l10n.taxId}: ${company!.taxId}',
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
                          if (pos.dueAmount > 0.001) ...[
                            const Divider(height: 12),
                            _TotalsRow(label: 'Amount Paid', value: formatter.format(pos.amountPaid)),
                            _TotalsRow(
                              label: 'Due Balance',
                              value: formatter.format(pos.dueAmount),
                              valueColor: Colors.red.shade600,
                            ),
                          ],
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
                      onPressed: pos.cartIsEmpty || pos.isCheckingOut || pos.requiresCustomerForDue
                          ? null
                          : () => _previewThenCheckout(context),
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
                                  l10n.completeSaleButton(formatter.format(pos.grandTotal)),
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
                ),
              ),
            ],
            ),
          ),
        );
      },
    );
  }
}

class _PaymentMethodChip extends StatelessWidget {
  const _PaymentMethodChip({
    required this.method,
    required this.isSelected,
    required this.color,
    required this.icon,
    required this.onTap,
  });

  final PaymentMethodModel method;
  final bool isSelected;
  final Color color;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(10),
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? color.withOpacity(0.15) : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: isSelected ? color : Colors.grey.shade300,
            width: isSelected ? 2 : 1,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 18, color: isSelected ? color : Colors.grey.shade700),
            const SizedBox(width: 6),
            Text(
              method.name,
              style: TextStyle(
                color: isSelected ? color : Colors.grey.shade800,
                fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                fontSize: 13,
              ),
            ),
          ],
        ),
      ),
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

/// Read-only bank/UPI details configured for the selected payment method
/// (Settings > Financial > Payment Methods), shown at checkout so the
/// cashier can share them with the customer.
class _BankDetailsBox extends StatelessWidget {
  const _BankDetailsBox({required this.metadata});

  final Map<String, dynamic> metadata;

  @override
  Widget build(BuildContext context) {
    final rows = <String, String>{
      if ((metadata['bank_name'] ?? '').toString().isNotEmpty) 'Bank': metadata['bank_name'].toString(),
      if ((metadata['holder_name'] ?? '').toString().isNotEmpty) 'Account Holder': metadata['holder_name'].toString(),
      if ((metadata['account_no'] ?? '').toString().isNotEmpty) 'Account No.': metadata['account_no'].toString(),
      if ((metadata['ifsc_code'] ?? '').toString().isNotEmpty) 'IFSC': metadata['ifsc_code'].toString(),
      if ((metadata['upi_id'] ?? '').toString().isNotEmpty) 'UPI ID': metadata['upi_id'].toString(),
    };
    if (rows.isEmpty) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.teal.shade50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.teal.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Account Details', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.teal.shade800)),
          const SizedBox(height: 6),
          for (final entry in rows.entries)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 1),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(entry.key, style: TextStyle(fontSize: 12, color: Colors.teal.shade700)),
                  Text(entry.value, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// "Cash Tendered by Customer" input + live change-due calculation + quick
/// rounding presets, shown when Cash is the active (non-split) payment
/// method. Owns its own [TextEditingController] so the field keeps its
/// cursor/focus across the ancestor [CartSheet]'s frequent rebuilds
/// (it watches [PosProvider], which notifies on every cart change).
class _CashTenderSection extends StatefulWidget {
  const _CashTenderSection({required this.payableAmount, required this.currencySymbol});

  final double payableAmount;
  final String currencySymbol;

  @override
  State<_CashTenderSection> createState() => _CashTenderSectionState();
}

class _CashTenderSectionState extends State<_CashTenderSection> {
  late final TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    final pos = context.read<PosProvider>();
    _controller = TextEditingController(text: pos.effectiveCashTendered.toStringAsFixed(2));
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  List<double> _presetAmounts(double total) {
    if (total <= 0) return const [0];
    final result = <double>{double.parse(total.toStringAsFixed(2))};
    const steps = [1.0, 5.0, 10.0, 50.0, 100.0, 500.0];
    for (final step in steps) {
      final rounded = (total / step).ceil() * step;
      if (rounded > total) {
        result.add(rounded);
      }
    }
    final sorted = result.toList()..sort();
    return sorted.take(5).toList();
  }

  @override
  Widget build(BuildContext context) {
    final pos = context.watch<PosProvider>();
    final formatter = CurrencyFormatter(widget.currencySymbol);
    final presets = _presetAmounts(widget.payableAmount);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          'Cash Tendered by Customer',
          style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Colors.grey.shade600),
        ),
        const SizedBox(height: 6),
        TextField(
          controller: _controller,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(
            prefixText: widget.currencySymbol,
            border: const OutlineInputBorder(),
            isDense: true,
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          ),
          onChanged: (val) => pos.setCashTendered(double.tryParse(val) ?? 0),
        ),
        const SizedBox(height: 10),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: Colors.green.shade50,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: Colors.green.shade200),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('CHANGE DUE TO CUSTOMER',
                  style: TextStyle(fontWeight: FontWeight.bold, color: Colors.green.shade800, fontSize: 11)),
              Text(formatter.format(pos.changeDue),
                  style: TextStyle(fontWeight: FontWeight.bold, color: Colors.green.shade800, fontSize: 16)),
            ],
          ),
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final preset in presets)
              ActionChip(
                label: Text(preset == presets.first ? 'Exact' : formatter.format(preset)),
                onPressed: () {
                  _controller.text = preset.toStringAsFixed(2);
                  pos.setCashTendered(preset);
                },
              ),
          ],
        ),
      ],
    );
  }
}
