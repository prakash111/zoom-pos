import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/config/bootstrap_cache.dart';
import '../../../core/models/customer_model.dart';
import '../../../core/models/settings_models.dart';
import '../../../core/sdui/sdui_icon_registry.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/utils/responsive.dart';
import '../../../core/widgets/adaptive_sheet.dart';
import '../../../core/widgets/sdui/sdui_controls.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/auth_provider.dart';
import '../../customers/customers_repository.dart';
import '../pos_provider.dart';
import '../../../core/services/thermal/thermal_printer_service.dart';
import 'customer_picker_sheet.dart';
import 'invoice_actions_sheet.dart';
import 'invoice_preview_screen.dart';

/// The modernized POS Cart & Checkout sheet supporting dynamic payment options,
/// action pills (Hold, Customer, Note, More), and country-wise GST tax breakdown.
class CartSheet extends StatelessWidget {
  const CartSheet({
    super.key,
    required this.customersRepository,
    this.onCheckoutCompleted,
  });

  final CustomersRepository customersRepository;
  final ValueChanged<PosCheckoutResult>? onCheckoutCompleted;

  Future<void> _pickDueDate(BuildContext context) async {
    final pos = context.read<PosProvider>();
    final value = await showDatePicker(
      context: context,
      initialDate: pos.effectiveDueDate,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
    );
    if (value != null) pos.setDueDate(value);
  }

  Future<void> _pickDueReminder(BuildContext context) async {
    final pos = context.read<PosProvider>();
    final current = pos.effectiveDueReminderAt;
    final date = await showDatePicker(
      context: context,
      initialDate: current.isBefore(DateTime.now()) ? DateTime.now() : current,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
    );
    if (date == null || !context.mounted) return;
    final time = await showTimePicker(
        context: context, initialTime: TimeOfDay.fromDateTime(current));
    if (time != null)
      pos.setDueReminderAt(
          DateTime(date.year, date.month, date.day, time.hour, time.minute));
  }

  Future<void> _pickCustomer(BuildContext context) async {
    final pos = context.read<PosProvider>();
    final customer = await showAdaptiveSheet<CustomerModel>(
      context,
      builder: (_) =>
          CustomerPickerSheet(customersRepository: customersRepository),
    );
    if (customer != null) {
      pos.setCustomer(customer);
    }
  }

  void _showNotesDialog(BuildContext context) {
    final pos = context.read<PosProvider>();
    final controller = TextEditingController(text: pos.orderNotes);

    final l10n = AppLocalizations.of(context);
    showDialog(
      context: context,
      builder: (dialogCtx) => AlertDialog(
        title: Row(
          children: [
            const Icon(Icons.edit_note, size: 22),
            const SizedBox(width: 8),
            Text(l10n.text('orderNotes', fallback: 'Order Notes & Remarks')),
          ],
        ),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        content: TextField(
          controller: controller,
          maxLines: 3,
          autofocus: true,
          decoration: InputDecoration(
            hintText: l10n.text('orderNotesHint',
                fallback:
                    'e.g. Special packaging, delivery note, invoice memo'),
            border: const OutlineInputBorder(),
          ),
        ),
        actionsPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        actions: [
          OverflowBar(
            spacing: 8,
            overflowSpacing: 8,
            children: [
              TextButton(
                onPressed: () => Navigator.of(dialogCtx).pop(),
                child: Text(l10n.cancel),
              ),
              ElevatedButton(
                onPressed: () {
                  pos.setOrderNotes(controller.text.trim());
                  Navigator.of(dialogCtx).pop();
                },
                child: Text(l10n.text('saveNote', fallback: 'Save Note')),
              ),
            ],
          ),
        ],
      ),
    );
  }

  void _showDiscountDialog(BuildContext context) {
    final pos = context.read<PosProvider>();
    final l10n = AppLocalizations.of(context);
    final controller = TextEditingController(
      text: pos.customDiscount > 0 ? pos.customDiscount.toStringAsFixed(0) : '',
    );
    bool isPercent = pos.isPercentDiscount;

    showDialog(
      context: context,
      builder: (dialogCtx) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Row(
            children: [
              const Icon(Icons.local_offer_outlined, size: 22),
              const SizedBox(width: 8),
              Text(l10n.text('applyDiscount', fallback: 'Apply Discount')),
            ],
          ),
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Expanded(
                    child: ChoiceChip(
                      label: Text(
                          l10n.text('fixedAmount', fallback: 'Fixed Amount')),
                      selected: !isPercent,
                      onSelected: (_) =>
                          setDialogState(() => isPercent = false),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: ChoiceChip(
                      label: Text(
                          l10n.text('percentage', fallback: 'Percentage (%)')),
                      selected: isPercent,
                      onSelected: (_) => setDialogState(() => isPercent = true),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              TextField(
                controller: controller,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                autofocus: true,
                decoration: InputDecoration(
                  labelText:
                      isPercent ? 'Discount Percentage' : 'Discount Amount',
                  suffixText: isPercent ? '%' : '',
                  prefixIcon: const Icon(Icons.percent),
                ),
              ),
            ],
          ),
          actionsPadding:
              const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
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
                    child: const Text('Remove Discount',
                        style: TextStyle(color: Colors.red)),
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

    showAdaptiveSheet(
      context,
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
                    style: const TextStyle(
                        fontSize: 18, fontWeight: FontWeight.bold),
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
                        title: Text(held.name,
                            style:
                                const TextStyle(fontWeight: FontWeight.w600)),
                        subtitle: Text(l10n.heldCartSubtitle(
                            held.itemCount, formatter.format(held.total))),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              icon: const Icon(Icons.delete_outline,
                                  color: Colors.red),
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
        customerPhone: pos.selectedCustomer?.phone,
        customerEmail: pos.selectedCustomer?.email,
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
      if (onCheckoutCompleted != null) {
        onCheckoutCompleted!(result);
      } else {
        final modalRoute = ModalRoute.of(context);
        final isDialogOrSheet = modalRoute is PopupRoute;
        if (isDialogOrSheet && Navigator.of(context).canPop()) {
          Navigator.of(context).pop(result);
        } else {
          if (result.isPendingSync) {
            messenger.showSnackBar(
              SnackBar(
                  content: Text(AppLocalizations.of(context).saleQueuedOffline)),
            );
            return;
          }
          messenger.showSnackBar(
              SnackBar(content: Text(AppLocalizations.of(context).saleCompleted)));
          await showInvoiceActionsSheet(
            context,
            InvoiceActionsData(
              documentType: 'invoice',
              documentId: result.saleId,
              documentNumber: result.saleNumber,
              companyName: company?.tradeName ?? company?.name ?? '',
              customerName: result.customerName,
              customerPhone: result.customerPhone,
              customerEmail: result.customerEmail,
              currencySymbol: company?.currencySymbol ?? '\$',
              subtotal: result.subtotal,
              discount: result.discount,
              tax: result.tax,
              total: result.total,
              taxId: company?.taxId,
              taxLabel: company?.taxLabel ?? 'Tax',
              isIndia: company?.isIndia ?? false,
              taxRate: (result.subtotal - result.discount) > 0
                  ? result.tax / (result.subtotal - result.discount) * 100
                  : 0,
              paidAmount: result.paidAmount,
              dueAmount: result.dueAmount,
              batchDispatchEndpoint: '/api/v1/tenant/dispatch/batch-send',
              lines: result.items
                  .map((item) => ReceiptLine(
                        name: item.product.name,
                        quantity: item.quantity,
                        unitPrice: item.product.salePrice,
                        lineTotal: item.lineTotal,
                      ))
                  .toList(),
            ),
          );
        }
      }
    } else if (pos.checkoutError != null) {
      messenger.showSnackBar(SnackBar(content: Text(pos.checkoutError!)));
    }
  }

  IconData _iconForMethod(String code) {
    if (code.toLowerCase() == 'wallet') {
      return Icons.account_balance_wallet;
    }
    final sduiMethod = BootstrapCache.instance.paymentMethodFor(code);
    if (sduiMethod != null) {
      return SduiIconRegistry.resolve(sduiMethod.icon);
    }
    return SduiIconRegistry.resolve(code,
        fallback: Icons.account_balance_wallet_outlined);
  }

  Color _colorForMethod(String code, Color defaultPrimary) {
    if (code.toLowerCase() == 'wallet') {
      return const Color(0xFF10B981);
    }
    final sduiMethod = BootstrapCache.instance.paymentMethodFor(code);
    if (sduiMethod != null) {
      return SduiIconRegistry.parseColor(sduiMethod.color,
          fallback: defaultPrimary);
    }
    return defaultPrimary;
  }

  /// Bank/UPI metadata (bank_name, account_no, ifsc_code, upi_id,
  /// holder_name) configured for the currently-selected payment method, if
  /// it looks like a bank transfer or UPI method and has any details set.
  Map<String, dynamic>? _bankMetadataFor(
      List<PaymentMethodModel> methods, String selectedCode) {
    final match = methods.where((m) =>
        (m.code.isNotEmpty ? m.code : m.id) == selectedCode ||
        m.id == selectedCode);
    if (match.isEmpty) return null;
    final method = match.first;
    final code =
        (method.code.isNotEmpty ? method.code : method.id).toLowerCase();
    if (!code.contains('bank') &&
        !code.contains('transfer') &&
        !code.contains('upi')) return null;
    final metadata = method.metadata;
    if (metadata == null ||
        metadata.values.every((v) => v == null || v.toString().isEmpty))
      return null;
    return metadata;
  }

  Future<void> _showAmountPaidDialog(BuildContext context) async {
    final pos = context.read<PosProvider>();
    final company = context.read<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');
    final l10n = AppLocalizations.of(context);
    final controller =
        TextEditingController(text: pos.amountPaid.toStringAsFixed(2));

    await showDialog(
      context: context,
      builder: (dialogCtx) => AlertDialog(
        title: Row(
          children: [
            const Icon(Icons.price_check, size: 22),
            const SizedBox(width: 8),
            Text(l10n.text('amountPaid', fallback: 'Amount Paid')),
          ],
        ),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
                '${l10n.text('grandTotal', fallback: 'Grand Total')}: ${formatter.format(pos.grandTotal)}',
                style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              keyboardType:
                  const TextInputType.numberWithOptions(decimal: true),
              autofocus: true,
              decoration: InputDecoration(
                labelText:
                    l10n.text('amountPaidNow', fallback: 'Amount Paid Now'),
                prefixText: company?.currencySymbol ?? '\$',
                border: const OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              children: [
                ActionChip(
                  label: Text(l10n.text('fullAmount', fallback: 'Full Amount')),
                  onPressed: () =>
                      controller.text = pos.grandTotal.toStringAsFixed(2),
                ),
                ActionChip(
                  label: Text(l10n.text('zeroPayment',
                      fallback: 'Zero Payment (Full Due)')),
                  onPressed: () => controller.text = '0',
                ),
              ],
            ),
          ],
        ),
        actionsPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        actions: [
          TextButton(
              onPressed: () => Navigator.of(dialogCtx).pop(),
              child: Text(l10n.cancel)),
          ElevatedButton(
            onPressed: () {
              final val =
                  double.tryParse(controller.text.trim()) ?? pos.grandTotal;
              pos.setAmountPaid(val);
              Navigator.of(dialogCtx).pop();
            },
            child: Text(l10n.text('apply', fallback: 'Apply')),
          ),
        ],
      ),
    );
  }

  Widget _buildLoyaltyRedemptionSection(
      BuildContext context, CustomerModel? customer, double cartTotal) {
    if (customer == null || customer.id.isEmpty) return const SizedBox.shrink();

    final pos = context.watch<PosProvider>();
    final settings = BootstrapCache.instance.loyaltySettings;
    if (!settings.isPointsEnabled) return const SizedBox.shrink();

    final points = customer.effectivePoints;
    final rate = settings.redemptionValuePerPoint > 0 ? settings.redemptionValuePerPoint : 1.0;
    final pointsMonetaryVal = points * rate;
    final spendPerPoint = settings.spendAmountPerPoint > 0 ? settings.spendAmountPerPoint : 100.0;
    final pointsAwarded = settings.pointsAwarded > 0 ? settings.pointsAwarded : 1.0;
    final willEarnPoints = ((pos.grandTotal / spendPerPoint) * pointsAwarded * customer.tierMultiplier).floor();
    final isLocked = points < settings.minPointsToRedeem;

    if (pos.rewardPointsRedeemed > 0) {
      return Container(
        margin: const EdgeInsets.symmetric(vertical: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFF064E3B).withValues(alpha: 0.25),
          borderRadius: BorderRadius.circular(12),
          border:
              Border.all(color: const Color(0xFF10B981).withValues(alpha: 0.6)),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                const Icon(Icons.stars, color: Color(0xFFF59E0B), size: 20),
                const SizedBox(width: 8),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '✓ ${pos.rewardPointsRedeemed.toInt()} pts applied (-₹${pos.rewardPointsDiscount.toStringAsFixed(2)})',
                      style: const TextStyle(
                        color: Color(0xFF10B981),
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    Text(
                      'Remaining balance: ${(points - pos.rewardPointsRedeemed).toInt()} pts',
                      style: const TextStyle(
                          color: Color(0xFF94A3B8), fontSize: 11),
                    ),
                  ],
                ),
              ],
            ),
            TextButton(
              onPressed: () => pos.clearRewardPoints(),
              child: const Text('✕ Remove',
                  style: TextStyle(
                      color: Color(0xFFEF4444),
                      fontWeight: FontWeight.bold,
                      fontSize: 12)),
            ),
          ],
        ),
      );
    }

    if (isLocked) {
      return Container(
        margin: const EdgeInsets.symmetric(vertical: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFF78350F).withValues(alpha: 0.2),
          borderRadius: BorderRadius.circular(12),
          border:
              Border.all(color: const Color(0xFFF59E0B).withValues(alpha: 0.4)),
        ),
        child: Row(
          children: [
            const Icon(Icons.lock_outline, color: Color(0xFFF59E0B), size: 22),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        '${customer.tierName} • ',
                        style: const TextStyle(
                            color: Color(0xFF10B981),
                            fontSize: 11,
                            fontWeight: FontWeight.bold),
                      ),
                      Flexible(
                        child: Text(
                          '$points Pts (₹${pointsMonetaryVal.toStringAsFixed(2)})',
                          style: const TextStyle(
                              color: Colors.white,
                              fontSize: 13,
                              fontWeight: FontWeight.bold),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'Needs ${(settings.minPointsToRedeem - points)} more pts to redeem (Min ${settings.minPointsToRedeem} pts required)',
                    style: const TextStyle(color: Color(0xFFFDE68A), fontSize: 11),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'Earns +$willEarnPoints pts on this sale',
                    style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    return Container(
      margin: const EdgeInsets.symmetric(vertical: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B),
        borderRadius: BorderRadius.circular(12),
        border:
            Border.all(color: const Color(0xFFF59E0B).withValues(alpha: 0.4)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                      '${customer.tierName} • ',
                      style: const TextStyle(
                          color: Color(0xFF10B981),
                          fontSize: 11,
                          fontWeight: FontWeight.bold),
                    ),
                    Flexible(
                      child: Text(
                        '$points Pts (₹${pointsMonetaryVal.toStringAsFixed(2)})',
                        style: const TextStyle(
                            color: Colors.white,
                            fontSize: 13,
                            fontWeight: FontWeight.bold),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 2),
                Text(
                  'Earns +$willEarnPoints pts on this sale',
                  style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11),
                ),
              ],
            ),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFF59E0B),
              foregroundColor: Colors.white,
              padding:
                  const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8)),
            ),
            onPressed: () =>
                _showPointsRedeemDialog(context, points, cartTotal),
            child: const Text('Redeem',
                style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  Future<void> _showPointsRedeemDialog(
      BuildContext context, int availablePoints, double cartTotal) async {
    final pos = context.read<PosProvider>();
    final settings = BootstrapCache.instance.loyaltySettings;
    final rate = settings.redemptionValuePerPoint > 0 ? settings.redemptionValuePerPoint : 1.0;
    final capPct = settings.maxRedemptionPercentage;

    final maxAllowedCartDiscount =
        (pos.subtotal - pos.baseDiscount - pos.couponDiscount).clamp(0.0, pos.subtotal);
    final capDiscount = (capPct > 0 && capPct < 100)
        ? (pos.subtotal * capPct / 100).clamp(0.0, maxAllowedCartDiscount)
        : maxAllowedCartDiscount;

    final maxCapPoints = rate > 0 ? (capDiscount / rate).floorToDouble() : 0.0;
    final maxAllowedPoints =
        availablePoints < maxCapPoints ? availablePoints.toDouble() : maxCapPoints;

    final controller =
        TextEditingController(text: maxAllowedPoints.toInt().toString());

    await showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Redeem Reward Points'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Available Balance: $availablePoints pts (₹${(availablePoints * rate).toStringAsFixed(2)})'),
            const SizedBox(height: 4),
            Text(
              'Max redeemable (Cap $capPct% of cart): ${maxAllowedPoints.toInt()} pts (-₹${(maxAllowedPoints * rate).toStringAsFixed(2)})',
              style: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              keyboardType:
                  const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(
                labelText: 'Points to Redeem',
                border: const OutlineInputBorder(),
                prefixIcon:
                    const Icon(Icons.stars, color: Color(0xFFF59E0B)),
                suffixIcon: TextButton(
                  onPressed: () {
                    controller.text = maxAllowedPoints.toInt().toString();
                  },
                  child: const Text('MAX',
                      style: TextStyle(fontWeight: FontWeight.bold)),
                ),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFF59E0B)),
            onPressed: () {
              final pts = double.tryParse(controller.text.trim()) ?? 0;
              if (pts > 0 && pts <= maxAllowedPoints) {
                pos.applyRewardPoints(pts, rate: rate);
                Navigator.of(ctx).pop();
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                      content: Text(
                          'Applied ${pts.toInt()} reward points (-₹${pos.rewardPointsDiscount.toStringAsFixed(2)})!')),
                );
              } else if (pts <= 0) {
                pos.clearRewardPoints();
                Navigator.of(ctx).pop();
              } else {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                        'Cannot redeem more than ${maxAllowedPoints.toInt()} points (cart cap).'),
                    backgroundColor: Colors.red.shade700,
                  ),
                );
              }
            },
            child: const Text('Apply Points',
                style: TextStyle(
                    color: Colors.white, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  Widget _buildCouponSection(BuildContext context, PosProvider pos) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    if (pos.appliedCouponCode != null) {
      return Container(
        margin: const EdgeInsets.symmetric(vertical: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color:
              const Color(0xFF047857).withValues(alpha: isDark ? 0.25 : 0.12),
          borderRadius: BorderRadius.circular(12),
          border:
              Border.all(color: const Color(0xFF10B981).withValues(alpha: 0.6)),
        ),
        child: Row(
          children: [
            const Icon(Icons.confirmation_number,
                color: Color(0xFF10B981), size: 20),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Coupon ${pos.appliedCouponCode} applied',
                    style: const TextStyle(
                      color: Color(0xFF10B981),
                      fontWeight: FontWeight.bold,
                      fontSize: 13,
                    ),
                  ),
                  Text(
                    'Discount: -₹${pos.couponDiscount.toStringAsFixed(2)}',
                    style: TextStyle(
                      color: isDark
                          ? const Color(0xFFCBD5E1)
                          : const Color(0xFF334155),
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
            TextButton(
              onPressed: () {
                pos.clearCoupon();
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Coupon removed.')),
                );
              },
              child: const Text('✕ Remove',
                  style: TextStyle(
                      color: Color(0xFFEF4444),
                      fontWeight: FontWeight.bold,
                      fontSize: 12)),
            ),
          ],
        ),
      );
    }

    return _CouponInputField(pos: pos);
  }

  Future<void> _openSplitPaymentEditor(
      BuildContext context, List<PaymentMethodModel> activeMethods) async {
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
      for (final p in pos.payments)
        TextEditingController(text: p.amount.toStringAsFixed(2)),
    ];

    await showDialog(
      context: context,
      builder: (dialogCtx) => StatefulBuilder(
        builder: (context, setDialogState) {
          while (controllers.length < pos.payments.length) {
            controllers.add(TextEditingController(
                text: pos.payments[controllers.length].amount
                    .toStringAsFixed(2)));
          }
          while (controllers.length > pos.payments.length) {
            controllers.removeLast().dispose();
          }

          String codeFor(int i) => activeMethods.any((m) =>
                  (m.code.isNotEmpty ? m.code : m.id) ==
                  pos.payments[i].methodCode)
              ? pos.payments[i].methodCode
              : (activeMethods.isNotEmpty
                  ? (activeMethods.first.code.isNotEmpty
                      ? activeMethods.first.code
                      : activeMethods.first.id)
                  : 'cash');

          return AlertDialog(
            title: const Text('Split Payment'),
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
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
                                initialValue: codeFor(i),
                                isExpanded: true,
                                items: [
                                  for (final m in activeMethods)
                                    DropdownMenuItem(
                                      value: m.code.isNotEmpty ? m.code : m.id,
                                      child: Text(m.name,
                                          overflow: TextOverflow.ellipsis),
                                    ),
                                ],
                                onChanged: (val) {
                                  if (val == null) return;
                                  pos.updateSplitRow(i, methodCode: val);
                                  setDialogState(() {});
                                },
                                decoration: const InputDecoration(
                                    isDense: true,
                                    contentPadding: EdgeInsets.symmetric(
                                        horizontal: 8, vertical: 8)),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              flex: 2,
                              child: TextField(
                                controller: controllers[i],
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                        decimal: true),
                                decoration: InputDecoration(
                                    isDense: true,
                                    prefixText: company?.currencySymbol ?? '\$',
                                    border: const OutlineInputBorder()),
                                onChanged: (val) {
                                  pos.updateSplitRow(i,
                                      amount: double.tryParse(val) ?? 0);
                                  setDialogState(() {});
                                },
                              ),
                            ),
                            IconButton(
                              icon: const Icon(Icons.remove_circle_outline,
                                  color: Colors.red),
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
                        const Text('Remaining Due',
                            style: TextStyle(fontWeight: FontWeight.bold)),
                        Text(formatter.format(pos.remainingSplitBalance),
                            style:
                                const TextStyle(fontWeight: FontWeight.bold)),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            actionsPadding:
                const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            actions: [
              TextButton(
                onPressed: () {
                  pos.toggleSplitPayment();
                  Navigator.of(dialogCtx).pop();
                },
                child: const Text('Cancel Split',
                    style: TextStyle(color: Colors.red)),
              ),
              ElevatedButton(
                  onPressed: () => Navigator.of(dialogCtx).pop(),
                  child: const Text('Done')),
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
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final primaryColor = theme.colorScheme.primary;
    final l10n = AppLocalizations.of(context);

    // Load active payment methods dynamically
    final isWalletEnabled = BootstrapCache.instance.isWalletEnabled;
    final List<PaymentMethodModel> activeMethods = pos.paymentMethods.isNotEmpty
        ? pos.paymentMethods.where((p) => p.isActive).toList()
        : [
            PaymentMethodModel(
                id: 'cash',
                name: 'Cash',
                code: 'cash',
                description: '',
                isActive: true,
                orderIndex: 0),
            PaymentMethodModel(
                id: 'card',
                name: 'Card',
                code: 'card',
                description: '',
                isActive: true,
                orderIndex: 1),
            PaymentMethodModel(
                id: 'upi',
                name: 'UPI',
                code: 'upi',
                description: '',
                isActive: true,
                orderIndex: 2),
            PaymentMethodModel(
                id: 'credit',
                name: 'Credit',
                code: 'credit',
                description: '',
                isActive: true,
                orderIndex: 3),
            PaymentMethodModel(
                id: 'other',
                name: 'Other',
                code: 'other',
                description: '',
                isActive: true,
                orderIndex: 4),
          ];

    if (isWalletEnabled) {
      final hasWallet = activeMethods.any(
          (p) => p.code.toLowerCase() == 'wallet' || p.id.toLowerCase() == 'wallet');
      if (!hasWallet) {
        activeMethods.add(
          PaymentMethodModel(
            id: 'wallet',
            name: 'Store Wallet',
            code: 'wallet',
            description: 'Customer store prepaid wallet',
            isActive: true,
            orderIndex: 99,
          ),
        );
      }
    } else {
      activeMethods.removeWhere((p) =>
          p.code.toLowerCase() == 'wallet' || p.id.toLowerCase() == 'wallet');
    }

    final wide = isWide(context);

    Widget buildCartBody(ScrollController? scrollController) {
      return Padding(
        padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom + 20),
        child: SafeArea(
          top: false,
          child: Column(
            children: [
              if (!wide) ...[
                const SizedBox(height: 12),
                Container(
                  width: 44,
                  height: 5,
                  decoration: BoxDecoration(
                      color: isDark
                          ? const Color(0xFF334155)
                          : Colors.grey.shade300,
                      borderRadius: BorderRadius.circular(3)),
                ),
              ],
                Padding(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          Text(l10n.orderCart,
                              style: Theme.of(context)
                                  .textTheme
                                  .titleLarge
                                  ?.copyWith(fontWeight: FontWeight.bold)),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(
                              color: primaryColor.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Text(
                              l10n.itemsCountBadge(pos.cartCount),
                              style: TextStyle(
                                  color: primaryColor,
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold),
                            ),
                          ),
                        ],
                      ),
                      if (!pos.cartIsEmpty)
                        TextButton.icon(
                          onPressed: pos.clearCart,
                          icon: const Icon(Icons.delete_outline, size: 18),
                          label: Text(l10n.clearCart),
                          style: TextButton.styleFrom(
                              foregroundColor: Colors.red.shade600),
                        ),
                    ],
                  ),
                ),
                const Divider(height: 1),

                if (pos.hasRxContext)
                  Container(
                    width: double.infinity,
                    margin: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: isDark
                          ? const Color(0xFF10B981).withValues(alpha: 0.12)
                          : const Color(0xFFECFDF5),
                      border: Border.all(
                          color: isDark
                              ? const Color(0xFF10B981).withValues(alpha: 0.3)
                              : const Color(0xFFA7F3D0)),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(
                            pos.isRepairContext
                                ? Icons.handyman_outlined
                                : Icons.medical_information_outlined,
                            size: 18,
                            color: isDark
                                ? const Color(0xFF34D399)
                                : const Color(0xFF047857)),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                pos.isRepairContext
                                    ? l10n.text('linkedRepairTicket',
                                        fallback: 'Linked Repair Ticket')
                                    : l10n.text('attachDoctorRxDetails',
                                        fallback: 'Attach Doctor & Rx Details'),
                                style: TextStyle(
                                    fontSize: 12.5,
                                    fontWeight: FontWeight.bold,
                                    color: isDark
                                        ? const Color(0xFF6EE7B7)
                                        : const Color(0xFF065F46)),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                [
                                  if ((pos.rxNumber ?? '').isNotEmpty)
                                    '${pos.isRepairContext ? 'Ticket ' : ''}#${pos.rxNumber}',
                                  if ((pos.rxDoctorName ?? '').isNotEmpty)
                                    pos.rxDoctorName!,
                                  if (!pos.isRepairContext &&
                                      (pos.rxDoctorRegistrationNo ?? '')
                                          .isNotEmpty)
                                    'Reg. ${pos.rxDoctorRegistrationNo}',
                                ].join('  ·  '),
                                style: TextStyle(
                                    fontSize: 12,
                                    color: isDark
                                        ? const Color(0xFF34D399)
                                        : const Color(0xFF047857)),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),

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
                                      Icon(Icons.shopping_cart_outlined,
                                          size: 56,
                                          color: Colors.grey.shade300),
                                      const SizedBox(height: 12),
                                      Text(l10n.cartEmptyTitle,
                                          style: TextStyle(
                                              color: Colors.grey.shade600,
                                              fontSize: 16)),
                                      const SizedBox(height: 4),
                                      Text(l10n.cartEmptySubtitle,
                                          style: TextStyle(
                                              color: Colors.grey.shade400,
                                              fontSize: 12)),
                                    ],
                                  ),
                                ),
                              )
                            : ListView.separated(
                                shrinkWrap: true,
                                physics: const NeverScrollableScrollPhysics(),
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 16, vertical: 8),
                                itemCount: pos.cartItems.length,
                                separatorBuilder: (_, __) =>
                                     Divider(height: 1, color: isDark ? const Color(0xFF334155) : null),
                                itemBuilder: (context, index) {
                                  final item = pos.cartItems[index];
                                  return Padding(
                                    padding:
                                        const EdgeInsets.symmetric(vertical: 6),
                                    child: Row(
                                      children: [
                                        Expanded(
                                          flex: 3,
                                          child: Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                item.product.name,
                                                style: TextStyle(
                                                    fontWeight: FontWeight.w600,
                                                    fontSize: 14,
                                                    color: isDark ? const Color(0xFFF8FAFC) : null),
                                                maxLines: 2,
                                                overflow: TextOverflow.ellipsis,
                                              ),
                                              const SizedBox(height: 2),
                                              Text(
                                                '${formatter.format(item.product.salePrice)} / ${item.product.unit}',
                                                style: TextStyle(
                                                    color: isDark ? const Color(0xFF94A3B8) : Colors.grey.shade600,
                                                    fontSize: 12),
                                              ),
                                              if (item.product.taxRate > 0)
                                                Text(
                                                  '${isIndia ? 'GST' : 'Tax'} (${item.product.taxRate.toStringAsFixed(0)}%): +${formatter.format(item.taxAmount)}',
                                                  style: TextStyle(
                                                      color: isDark ? const Color(0xFF94A3B8) : Colors.grey.shade500,
                                                      fontSize: 11),
                                                ),
                                            ],
                                          ),
                                        ),
                                        Container(
                                          decoration: BoxDecoration(
                                            color: isDark
                                                ? const Color(0xFF1E293B)
                                                : Colors.white,
                                            borderRadius:
                                                BorderRadius.circular(20),
                                            border: Border.all(
                                              color: isDark
                                                  ? const Color(0xFF334155)
                                                  : const Color(0xFFCBD5E1),
                                              width: 1,
                                            ),
                                          ),
                                          child: Row(
                                            mainAxisSize: MainAxisSize.min,
                                            children: [
                                              IconButton(
                                                icon: const Icon(Icons.remove,
                                                    size: 16),
                                                color: item.quantity > 1
                                                    ? (isDark
                                                        ? const Color(0xFFF8FAFC)
                                                        : const Color(0xFF0F172A))
                                                    : (isDark
                                                        ? const Color(0xFF64748B)
                                                        : const Color(0xFF94A3B8)),
                                                padding:
                                                    const EdgeInsets.all(4),
                                                constraints:
                                                    const BoxConstraints(
                                                        minWidth: 32,
                                                        minHeight: 32),
                                                onPressed: () =>
                                                    pos.decrementQuantity(
                                                        item.product.id),
                                              ),
                                              Padding(
                                                padding:
                                                    const EdgeInsets.symmetric(
                                                        horizontal: 6),
                                                child: Text(
                                                  item.quantity.toStringAsFixed(
                                                      item.quantity ==
                                                              item.quantity
                                                                  .roundToDouble()
                                                          ? 0
                                                          : 2),
                                                  style: TextStyle(
                                                    fontWeight:
                                                        FontWeight.w600,
                                                    fontSize: 14,
                                                    color: isDark
                                                        ? const Color(0xFFF8FAFC)
                                                        : const Color(0xFF0F172A),
                                                  ),
                                                ),
                                              ),
                                              IconButton(
                                                icon: const Icon(Icons.add,
                                                    size: 16),
                                                color: isDark
                                                    ? const Color(0xFFF8FAFC)
                                                    : const Color(0xFF0F172A),
                                                padding:
                                                    const EdgeInsets.all(4),
                                                constraints:
                                                    const BoxConstraints(
                                                        minWidth: 32,
                                                        minHeight: 32),
                                                onPressed: () =>
                                                    pos.incrementQuantity(
                                                        item.product.id),
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
                                            style: TextStyle(
                                                fontWeight: FontWeight.bold,
                                                fontSize: 14,
                                                color: isDark ? const Color(0xFFF8FAFC) : null),
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
                                  SduiActionPill(
                                    label: pos.selectedCustomer?.name ??
                                        l10n.addCustomer,
                                    icon: Icons.person_outline,
                                    isActive: pos.selectedCustomer != null,
                                    activeColor: primaryColor,
                                    onTap: () => _pickCustomer(context),
                                  ),

                                  // Hold Cart Pill
                                  SduiActionPill(
                                    label: pos.heldCarts.isNotEmpty
                                        ? l10n.heldChip(pos.heldCarts.length)
                                        : l10n.hold,
                                    icon: Icons.pause_circle_outline,
                                    isActive: pos.heldCarts.isNotEmpty,
                                    badgeCount: pos.heldCarts.isNotEmpty
                                        ? pos.heldCarts.length
                                        : null,
                                    activeColor: Colors.orange.shade800,
                                    onTap: () => _showHoldCartsSheet(context),
                                  ),

                                  // Note Pill
                                  SduiActionPill(
                                    label: pos.orderNotes.isNotEmpty
                                        ? l10n.noteChecked
                                        : l10n.note,
                                    icon: Icons.edit_note,
                                    isActive: pos.orderNotes.isNotEmpty,
                                    activeColor: primaryColor,
                                    onTap: () => _showNotesDialog(context),
                                  ),

                                  // Discount Pill
                                  SduiActionPill(
                                    label: pos.customDiscount > 0
                                        ? l10n.discountChecked
                                        : l10n.discount,
                                    icon: Icons.local_offer_outlined,
                                    isActive: pos.customDiscount > 0,
                                    activeColor: Colors.green.shade800,
                                    onTap: () => _showDiscountDialog(context),
                                  ),

                                  // Split Payment Pill
                                  if (BootstrapCache.instance.activeModule
                                      .cartConfiguration.allowSplitPayment)
                                    SduiActionPill(
                                      label: pos.isSplitPayment
                                          ? 'Split (${pos.payments.length})'
                                          : l10n.text('splitPayment',
                                              fallback: 'Split Payment'),
                                      icon: Icons.call_split,
                                      isActive: pos.isSplitPayment,
                                      activeColor: primaryColor,
                                      onTap: () => _openSplitPaymentEditor(
                                          context, activeMethods),
                                    ),

                                  // Amount Paid Pill (only meaningful outside split mode)
                                  if (!pos.isSplitPayment)
                                    SduiActionPill(
                                      label: pos.dueAmount > 0.001
                                          ? 'Paid: ${formatter.format(pos.amountPaid)}'
                                          : l10n.text('amountPaid',
                                              fallback: 'Amount Paid'),
                                      icon: Icons.price_check,
                                      isActive: pos.dueAmount > 0.001,
                                      activeColor: Colors.amber.shade800,
                                      onTap: () =>
                                          _showAmountPaidDialog(context),
                                    ),
                                ],
                              ),

                              if (pos.selectedCustomer != null)
                                _buildLoyaltyRedemptionSection(
                                    context, pos.selectedCustomer, pos.grandTotal),

                              _buildCouponSection(context, pos),

                              if (pos.requiresCustomerForDue)
                                Padding(
                                  padding: const EdgeInsets.only(top: 8),
                                  child: Text(
                                    'Attach a customer for due, partial, or credit sales.',
                                    style: TextStyle(
                                        color: Colors.red.shade600,
                                        fontSize: 11,
                                        fontWeight: FontWeight.w600),
                                  ),
                                ),

                              if (pos.dueAmount > 0.001)
                                Container(
                                  margin: const EdgeInsets.only(top: 10),
                                  padding: const EdgeInsets.all(10),
                                  decoration: BoxDecoration(
                                    color: isDark
                                        ? const Color(0xFFF59E0B).withValues(alpha: 0.12)
                                        : Colors.amber.shade50,
                                    border: Border.all(
                                        color: isDark
                                            ? const Color(0xFFF59E0B).withValues(alpha: 0.3)
                                            : Colors.amber.shade200),
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      const Text('Due date & push reminder',
                                          style: TextStyle(
                                              fontSize: 12,
                                              fontWeight: FontWeight.bold)),
                                      const SizedBox(height: 6),
                                      Wrap(
                                          spacing: 8,
                                          runSpacing: 6,
                                          children: [
                                            OutlinedButton.icon(
                                              onPressed: () =>
                                                  _pickDueDate(context),
                                              icon: const Icon(Icons.event,
                                                  size: 16),
                                              label: Text(
                                                  'Due ${pos.effectiveDueDate.year}-${pos.effectiveDueDate.month.toString().padLeft(2, '0')}-${pos.effectiveDueDate.day.toString().padLeft(2, '0')}'),
                                            ),
                                            OutlinedButton.icon(
                                              onPressed: () =>
                                                  _pickDueReminder(context),
                                              icon: const Icon(Icons.alarm,
                                                  size: 16),
                                              label: Text(
                                                  'Alert ${pos.effectiveDueReminderAt.month}/${pos.effectiveDueReminderAt.day} ${TimeOfDay.fromDateTime(pos.effectiveDueReminderAt).format(context)}'),
                                            ),
                                          ]),
                                    ],
                                  ),
                                ),

                              const SizedBox(height: 12),

                              // Dynamic Payment Selection Tiles
                              if (pos.isSplitPayment)
                                Container(
                                  width: double.infinity,
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(
                                    color: primaryColor.withValues(alpha: 0.06),
                                    borderRadius: BorderRadius.circular(12),
                                    border: Border.all(
                                        color: primaryColor.withValues(
                                            alpha: 0.25)),
                                  ),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                          'Split Payment (${pos.payments.length} methods)',
                                          style: TextStyle(
                                              fontWeight: FontWeight.bold,
                                              color: primaryColor,
                                              fontSize: 13)),
                                      const SizedBox(height: 4),
                                      for (final p in pos.payments)
                                        Text(
                                            '• ${p.methodCode} — ${formatter.format(p.amount)}',
                                            style:
                                                const TextStyle(fontSize: 12)),
                                      Align(
                                        alignment: Alignment.centerRight,
                                        child: TextButton(
                                          onPressed: () =>
                                              _openSplitPaymentEditor(
                                                  context, activeMethods),
                                          child: const Text('Edit Split'),
                                        ),
                                      ),
                                    ],
                                  ),
                                )
                              else ...[
                                Text(
                                  l10n.paymentMethod,
                                  style: TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w600,
                                      color: isDark
                                          ? const Color(0xFF94A3B8)
                                          : Colors.grey.shade600),
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
                                        isSelected: pos.paymentMethod ==
                                                (method.code.isNotEmpty
                                                    ? method.code
                                                    : method.id) ||
                                            pos.paymentMethod == method.id,
                                        subtitle: (method.code.toLowerCase() == 'wallet' || method.id.toLowerCase() == 'wallet')
                                            ? (pos.selectedCustomer != null
                                                ? '${company?.currencySymbol ?? "₹"}${pos.selectedCustomer!.walletBalance.toStringAsFixed(2)}'
                                                : 'No Customer')
                                            : null,
                                        color: _colorForMethod(
                                            method.code.isNotEmpty
                                                ? method.code
                                                : method.id,
                                            primaryColor),
                                        icon: _iconForMethod(
                                            method.code.isNotEmpty
                                                ? method.code
                                                : method.id),
                                        onTap: () => pos.setPaymentMethod(
                                            method.code.isNotEmpty
                                                ? method.code
                                                : method.id),
                                      ),
                                  ],
                                ),

                                if (_bankMetadataFor(
                                        activeMethods, pos.paymentMethod) !=
                                    null) ...[
                                  const SizedBox(height: 10),
                                  _BankDetailsBox(
                                      metadata: _bankMetadataFor(
                                          activeMethods, pos.paymentMethod)!),
                                ],

                                if (pos.paymentMethod == 'cash') ...[
                                  const SizedBox(height: 12),
                                  _CashTenderSection(
                                      payableAmount: pos.amountPaid,
                                      currencySymbol:
                                          company?.currencySymbol ?? '\$'),
                                ],

                                if (pos.paymentMethod == 'wallet') ...[
                                  const SizedBox(height: 12),
                                  _StoreWalletDetailsCard(
                                    customer: pos.selectedCustomer,
                                    amountToPay: pos.amountPaid,
                                    currencySymbol:
                                        company?.currencySymbol ?? '₹',
                                  ),
                                ],
                              ],

                              const SizedBox(height: 12),

                              // Financial Summary Hierarchy
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: Theme.of(context)
                                      .colorScheme
                                      .surfaceContainerHighest,
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(
                                      color: Theme.of(context)
                                          .colorScheme
                                          .outlineVariant),
                                ),
                                child: Column(
                                  children: [
                                    _TotalsRow(
                                        label: l10n.subtotal,
                                        value: formatter.format(pos.subtotal)),
                                    if (pos.baseDiscount > 0)
                                      _TotalsRow(
                                        label: l10n.discount,
                                        value:
                                            '-${formatter.format(pos.baseDiscount)}',
                                        valueColor: Colors.red.shade600,
                                      ),
                                    if (pos.rewardPointsDiscount > 0)
                                      _TotalsRow(
                                        label:
                                            'Reward Points (${pos.rewardPointsRedeemed.toInt()} pts)',
                                        value:
                                            '-${formatter.format(pos.rewardPointsDiscount)}',
                                        valueColor: const Color(0xFFF59E0B),
                                      ),
                                    if (pos.couponDiscount > 0)
                                      _TotalsRow(
                                        label:
                                            'Coupon (${pos.appliedCouponCode ?? ""})',
                                        value:
                                            '-${formatter.format(pos.couponDiscount)}',
                                        valueColor: const Color(0xFF10B981),
                                      ),
                                    if (pos.taxTotal > 0) ...[
                                      if (isIndia) ...[
                                        _TotalsRow(
                                          label: l10n.cgst,
                                          value:
                                              '+${formatter.format(pos.taxTotal / 2)}',
                                          isSub: true,
                                        ),
                                        _TotalsRow(
                                          label: l10n.sgst,
                                          value:
                                              '+${formatter.format(pos.taxTotal / 2)}',
                                          isSub: true,
                                        ),
                                      ] else
                                        _TotalsRow(
                                          label: company?.taxLabel ?? 'Tax',
                                          value:
                                              '+${formatter.format(pos.taxTotal)}',
                                        ),
                                    ],
                                    const Divider(height: 12),
                                    Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.spaceBetween,
                                      children: [
                                        Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Text(l10n.grandTotal,
                                                style: const TextStyle(
                                                    fontWeight: FontWeight.bold,
                                                    fontSize: 14)),
                                            if ((company?.taxId ?? '')
                                                .isNotEmpty)
                                              Text(
                                                '${isIndia ? l10n.gstin : l10n.taxId}: ${company!.taxId}',
                                                style: TextStyle(
                                                    color: Theme.of(context)
                                                        .colorScheme
                                                        .onSurfaceVariant,
                                                    fontSize: 10),
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
                                      _TotalsRow(
                                          label: 'Amount Paid',
                                          value:
                                              formatter.format(pos.amountPaid)),
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
                                  minimumSize: const Size(double.infinity, 50),
                                  padding: const EdgeInsets.symmetric(
                                      vertical: 14, horizontal: 20),
                                  shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(10)),
                                ),
                                onPressed: pos.cartIsEmpty ||
                                        pos.isCheckingOut ||
                                        pos.requiresCustomerForDue
                                    ? null
                                    : () => _previewThenCheckout(context),
                                child: pos.isCheckingOut
                                    ? const SizedBox(
                                        height: 22,
                                        width: 22,
                                        child: CircularProgressIndicator(
                                            strokeWidth: 2.5,
                                            color: Colors.white),
                                      )
                                    : Row(
                                        mainAxisAlignment:
                                            MainAxisAlignment.center,
                                        children: [
                                          const Icon(Icons.check_circle_outline,
                                              size: 20),
                                          const SizedBox(width: 8),
                                          Text(
                                            l10n.completeSaleButton(formatter
                                                .format(pos.grandTotal)),
                                            style: const TextStyle(
                                                fontSize: 16,
                                                fontWeight: FontWeight.bold),
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
    }

    if (wide) {
      return buildCartBody(null);
    }

    return DraggableScrollableSheet(
      initialChildSize: 0.85,
      minChildSize: 0.5,
      maxChildSize: 0.95,
      expand: false,
      builder: (context, scrollController) => buildCartBody(scrollController),
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
    this.subtitle,
  });

  final PaymentMethodModel method;
  final bool isSelected;
  final Color color;
  final IconData icon;
  final VoidCallback onTap;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected
              ? const Color(0xFF10B981).withValues(alpha: 0.15)
              : (isDark ? const Color(0xFF1E293B) : const Color(0xFFF1F5F9)),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: isSelected
                ? const Color(0xFF10B981)
                : (isDark ? const Color(0xFF334155) : const Color(0xFFCBD5E1)),
            width: isSelected ? 1.5 : 1,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              size: 18,
              color: isSelected
                  ? const Color(0xFF34D399)
                  : (isDark ? const Color(0xFF94A3B8) : const Color(0xFF475569)),
            ),
            const SizedBox(width: 8),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  method.name,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: isSelected
                        ? const Color(0xFF34D399)
                        : (isDark ? const Color(0xFFF8FAFC) : const Color(0xFF0F172A)),
                  ),
                ),
                if (subtitle != null) ...[
                  const SizedBox(height: 1),
                  Text(
                    subtitle!,
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                      fontFamily: 'monospace',
                      color: isSelected
                          ? const Color(0xFF34D399)
                          : const Color(0xFF10B981),
                    ),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _StoreWalletDetailsCard extends StatelessWidget {
  const _StoreWalletDetailsCard({
    required this.customer,
    required this.amountToPay,
    required this.currencySymbol,
  });

  final CustomerModel? customer;
  final double amountToPay;
  final String currencySymbol;

  @override
  Widget build(BuildContext context) {
    if (customer == null) {
      return Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFF881337).withValues(alpha: 0.25),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFF43F5E).withValues(alpha: 0.5)),
        ),
        child: const Row(
          children: [
            Icon(Icons.warning_amber_rounded, color: Color(0xFFF43F5E), size: 20),
            SizedBox(width: 8),
            Expanded(
              child: Text(
                'Please select a registered customer to settle via Store Wallet.',
                style: TextStyle(
                    color: Color(0xFFFDA4AF),
                    fontSize: 12,
                    fontWeight: FontWeight.bold),
              ),
            ),
          ],
        ),
      );
    }

    final balance = customer!.walletBalance;
    final balanceAfter = balance - amountToPay;
    final isInsufficient = balanceAfter < -0.001;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFF064E3B).withValues(alpha: 0.2),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isInsufficient
              ? const Color(0xFFF43F5E).withValues(alpha: 0.6)
              : const Color(0xFF10B981).withValues(alpha: 0.4),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.account_balance_wallet,
                      color: Color(0xFF10B981), size: 18),
                  SizedBox(width: 6),
                  Text(
                    'Customer Store Wallet',
                    style: TextStyle(
                        color: Color(0xFF10B981),
                        fontSize: 13,
                        fontWeight: FontWeight.bold),
                  ),
                ],
              ),
              Text(
                'Available: $currencySymbol${balance.toStringAsFixed(2)}',
                style: const TextStyle(
                  color: Color(0xFF34D399),
                  fontSize: 12,
                  fontWeight: FontWeight.bold,
                  fontFamily: 'monospace',
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Column(
                    children: [
                      const Text('Total Debit',
                          style: TextStyle(
                              color: Color(0xFF94A3B8), fontSize: 10)),
                      const SizedBox(height: 2),
                      Text(
                        '-$currencySymbol${amountToPay.toStringAsFixed(2)}',
                        style: const TextStyle(
                            color: Color(0xFFF87171),
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                            fontFamily: 'monospace'),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Column(
                    children: [
                      const Text('Balance After',
                          style: TextStyle(
                              color: Color(0xFF94A3B8), fontSize: 10)),
                      const SizedBox(height: 2),
                      Text(
                        '$currencySymbol${balanceAfter.toStringAsFixed(2)}',
                        style: TextStyle(
                          color: isInsufficient
                              ? const Color(0xFFF87171)
                              : const Color(0xFF34D399),
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          fontFamily: 'monospace',
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          if (isInsufficient) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFF881337).withValues(alpha: 0.3),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  const Icon(Icons.error_outline,
                      color: Color(0xFFF43F5E), size: 16),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      'Insufficient balance! Customer needs $currencySymbol${balanceAfter.abs().toStringAsFixed(2)} more.',
                      style: const TextStyle(
                          color: Color(0xFFFDA4AF),
                          fontSize: 11,
                          fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
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
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: EdgeInsets.symmetric(vertical: isSub ? 1 : 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            isSub ? '  └ $label' : label,
            style: TextStyle(
              color: scheme.onSurfaceVariant,
              fontSize: isSub ? 11 : 13,
            ),
          ),
          Text(
            value,
            style: TextStyle(
              color: valueColor ??
                  (isSub ? scheme.onSurfaceVariant : scheme.onSurface),
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
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    final rows = <String, String>{
      if ((metadata['bank_name'] ?? '').toString().isNotEmpty)
        'Bank': metadata['bank_name'].toString(),
      if ((metadata['holder_name'] ?? '').toString().isNotEmpty)
        'Account Holder': metadata['holder_name'].toString(),
      if ((metadata['account_no'] ?? '').toString().isNotEmpty)
        'Account No.': metadata['account_no'].toString(),
      if ((metadata['ifsc_code'] ?? '').toString().isNotEmpty)
        'IFSC': metadata['ifsc_code'].toString(),
      if ((metadata['upi_id'] ?? '').toString().isNotEmpty)
        'UPI ID': metadata['upi_id'].toString(),
    };
    if (rows.isEmpty) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: isDark
            ? const Color(0xFF0D9488).withValues(alpha: 0.15)
            : Colors.teal.shade50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isDark
              ? const Color(0xFF0D9488).withValues(alpha: 0.3)
              : Colors.teal.shade200,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Account Details',
              style: TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 12,
                  color: isDark
                      ? const Color(0xFF5EEAD4)
                      : Colors.teal.shade800)),
          const SizedBox(height: 6),
          for (final entry in rows.entries)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 1),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(entry.key,
                      style: TextStyle(
                          fontSize: 12,
                          color: isDark
                              ? const Color(0xFF94A3B8)
                              : Colors.teal.shade700)),
                  Text(entry.value,
                      style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: isDark ? const Color(0xFFF8FAFC) : null)),
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
  const _CashTenderSection(
      {required this.payableAmount, required this.currencySymbol});

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
    _controller = TextEditingController(
        text: pos.effectiveCashTendered.toStringAsFixed(2));
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
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          'Cash Tendered by Customer',
          style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: isDark ? const Color(0xFF94A3B8) : Colors.grey.shade600),
        ),
        const SizedBox(height: 6),
        TextField(
          controller: _controller,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          style: TextStyle(color: isDark ? const Color(0xFFF8FAFC) : null),
          decoration: InputDecoration(
            prefixText: widget.currencySymbol,
            prefixStyle: TextStyle(
                color: isDark ? const Color(0xFF94A3B8) : null,
                fontWeight: FontWeight.w600),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide: BorderSide(
                  color: isDark
                      ? const Color(0xFF334155)
                      : const Color(0xFFCBD5E1)),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide: BorderSide(
                  color: isDark
                      ? const Color(0xFF334155)
                      : const Color(0xFFCBD5E1)),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide:
                  const BorderSide(color: Color(0xFF10B981), width: 1.5),
            ),
            filled: isDark,
            fillColor: isDark ? const Color(0xFF0F172A) : null,
            isDense: true,
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          ),
          onChanged: (val) => pos.setCashTendered(double.tryParse(val) ?? 0),
        ),
        const SizedBox(height: 10),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            color: isDark
                ? const Color(0xFF10B981).withValues(alpha: 0.12)
                : const Color(0xFFECFDF5),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: isDark
                  ? const Color(0xFF10B981).withValues(alpha: 0.3)
                  : const Color(0xFFA7F3D0),
              width: 1,
            ),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'CHANGE DUE TO CUSTOMER',
                style: TextStyle(
                  fontSize: 12,
                  letterSpacing: 0.5,
                  fontWeight: FontWeight.w600,
                  color:
                      isDark ? const Color(0xFF6EE7B7) : const Color(0xFF065F46),
                ),
              ),
              Text(
                formatter.format(pos.changeDue),
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color:
                      isDark ? const Color(0xFF34D399) : const Color(0xFF059669),
                ),
              ),
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
                backgroundColor: isDark ? const Color(0xFF1E293B) : null,
                side: BorderSide(
                  color: isDark
                      ? const Color(0xFF334155)
                      : const Color(0xFFCBD5E1),
                ),
                labelStyle: TextStyle(
                  color: isDark ? const Color(0xFFF8FAFC) : null,
                  fontWeight: FontWeight.w600,
                ),
                label: Text(preset == presets.first
                    ? 'Exact'
                    : formatter.format(preset)),
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


class _CouponInputField extends StatefulWidget {
  const _CouponInputField({required this.pos});
  final PosProvider pos;

  @override
  State<_CouponInputField> createState() => _CouponInputFieldState();
}

class _CouponInputFieldState extends State<_CouponInputField> {
  final _controller = TextEditingController();
  bool _isLoading = false;

  Future<void> _validateAndApplyCoupon() async {
    final code = _controller.text.trim();
    if (code.isEmpty) return;

    setState(() => _isLoading = true);
    try {
      ApiClient? client;
      try {
        client = context.read<ApiClient>();
      } catch (_) {}

      if (client == null) {
        if (!mounted) return;
        setState(() => _isLoading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Unable to connect to server.')),
        );
        return;
      }

      final res = await client.postAbsolute(
        '/api/v1/storefront/coupons/validate',
        data: {
          'code': code,
          'subtotal': widget.pos.subtotal,
          if (widget.pos.selectedCustomer?.id != null)
            'customer_id': widget.pos.selectedCustomer!.id,
          if (widget.pos.selectedCustomer?.phone != null)
            'phone': widget.pos.selectedCustomer!.phone,
          if (widget.pos.selectedCustomer?.email != null)
            'email': widget.pos.selectedCustomer!.email,
        },
      );

      if (!mounted) return;
      setState(() => _isLoading = false);

      if (res['valid'] == true || res['success'] == true) {
        final discountAmt = (res['discount_amount'] as num?)?.toDouble() ?? 0.0;
        final couponId = res['id'] is int
            ? res['id'] as int
            : int.tryParse(res['id']?.toString() ?? '');
        widget.pos.applyCoupon(
          code: res['code']?.toString() ?? code.toUpperCase(),
          discountAmount: discountAmt,
          couponId: couponId,
        );
        _controller.clear();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
                'Coupon applied! -₹${discountAmt.toStringAsFixed(2)} off'),
            backgroundColor: const Color(0xFF10B981),
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res['message']?.toString() ?? 'Invalid coupon code.'),
            backgroundColor: Colors.red.shade700,
          ),
        );
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message),
          backgroundColor: Colors.red.shade700,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to validate coupon: $e'),
          backgroundColor: Colors.red.shade700,
        ),
      );
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      margin: const EdgeInsets.symmetric(vertical: 8),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: isDark
            ? const Color(0xFF1E293B).withValues(alpha: 0.6)
            : Colors.grey.shade50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isDark ? const Color(0xFF334155) : Colors.grey.shade300,
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: _controller,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(
                isDense: true,
                hintText: 'Enter Promo / Coupon Code',
                hintStyle: TextStyle(
                  fontSize: 12,
                  color:
                      isDark ? const Color(0xFF94A3B8) : Colors.grey.shade500,
                ),
                prefixIcon: const Icon(Icons.local_offer_outlined, size: 18),
                border: InputBorder.none,
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
              ),
              onSubmitted: (_) => _validateAndApplyCoupon(),
            ),
          ),
          const SizedBox(width: 8),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.primary,
              foregroundColor: Colors.white,
              padding:
                  const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8)),
            ),
            onPressed: _isLoading ? null : _validateAndApplyCoupon,
            child: _isLoading
                ? const SizedBox(
                    width: 14,
                    height: 14,
                    child: CircularProgressIndicator(
                        strokeWidth: 2, color: Colors.white),
                  )
                : const Text('Apply',
                    style:
                        TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }
}

