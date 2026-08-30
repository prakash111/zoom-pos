import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/models/customer_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../auth/auth_provider.dart';
import '../../customers/customers_repository.dart';
import '../pos_provider.dart';
import 'customer_picker_sheet.dart';

const _paymentMethods = [
  ('cash', 'Cash', Icons.payments_outlined),
  ('card', 'Card', Icons.credit_card_outlined),
  ('credit', 'Credit', Icons.schedule_outlined),
];

/// The cart/checkout drawer, opened from [PosScreen] over the current
/// [PosProvider] (passed in via ChangeNotifierProvider.value by the caller).
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

  Future<void> _checkout(BuildContext context) async {
    final pos = context.read<PosProvider>();
    final success = await pos.checkout();
    if (!context.mounted) return;

    if (success) {
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Sale completed.')),
      );
    } else if (pos.checkoutError != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(pos.checkoutError!)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final pos = context.watch<PosProvider>();
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    return DraggableScrollableSheet(
      initialChildSize: 0.75,
      minChildSize: 0.4,
      maxChildSize: 0.95,
      expand: false,
      builder: (context, scrollController) {
        return Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
          child: Column(
            children: [
              const SizedBox(height: 12),
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2)),
              ),
              Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Cart', style: Theme.of(context).textTheme.titleLarge),
                    if (!pos.cartIsEmpty)
                      TextButton(onPressed: pos.clearCart, child: const Text('Clear')),
                  ],
                ),
              ),
              Expanded(
                child: pos.cartItems.isEmpty
                    ? const Center(child: Text('Your cart is empty.'))
                    : ListView.separated(
                        controller: scrollController,
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        itemCount: pos.cartItems.length,
                        separatorBuilder: (_, __) => const Divider(height: 1),
                        itemBuilder: (context, index) {
                          final item = pos.cartItems[index];
                          return ListTile(
                            contentPadding: EdgeInsets.zero,
                            title: Text(item.product.name),
                            subtitle: Text(formatter.format(item.product.salePrice)),
                            trailing: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                IconButton(
                                  icon: const Icon(Icons.remove_circle_outline),
                                  onPressed: () => pos.decrementQuantity(item.product.id),
                                ),
                                Text(item.quantity.toStringAsFixed(0)),
                                IconButton(
                                  icon: const Icon(Icons.add_circle_outline),
                                  onPressed: () => pos.incrementQuantity(item.product.id),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
              ),
              const Divider(height: 1),
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    InkWell(
                      onTap: () => _pickCustomer(context),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        child: Row(
                          children: [
                            const Icon(Icons.person_outline, size: 20),
                            const SizedBox(width: 8),
                            Expanded(child: Text(pos.selectedCustomer?.name ?? 'Walk-in customer')),
                            if (pos.selectedCustomer != null)
                              IconButton(
                                icon: const Icon(Icons.close, size: 18),
                                onPressed: () => pos.setCustomer(null),
                              )
                            else
                              const Icon(Icons.chevron_right, size: 18),
                          ],
                        ),
                      ),
                    ),
                    Row(
                      children: [
                        for (final method in _paymentMethods)
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 4),
                              child: ChoiceChip(
                                label: Text(method.$2),
                                avatar: Icon(method.$3, size: 16),
                                selected: pos.paymentMethod == method.$1,
                                onSelected: (_) => pos.setPaymentMethod(method.$1),
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Total', style: TextStyle(fontWeight: FontWeight.bold)),
                        Text(
                          formatter.format(pos.subtotal),
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    ElevatedButton(
                      onPressed: pos.cartIsEmpty || pos.isCheckingOut ? null : () => _checkout(context),
                      child: pos.isCheckingOut
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : const Text('Checkout'),
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
