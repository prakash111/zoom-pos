import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/models/customer_model.dart';
import '../../../core/models/quotation_model.dart';
import '../../../core/models/tax_rule_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../customers/customers_repository.dart';
import '../../inventory/inventory_repository.dart';
import '../../pos/screens/customer_picker_sheet.dart';
import '../../taxes/taxes_repository.dart';
import '../quotations_provider.dart';
import 'product_picker_sheet.dart';

/// Bottom sheet for POST/PUT /quotations, used for both creating a new
/// quote ([quotation] is null) and editing an existing one.
///
/// Customer is picked from the saved customer directory (search, or quick-add
/// a new one) via [CustomerPickerSheet] rather than typed freehand, and tax
/// is computed automatically from each added product's own tax rate — with
/// an optional tax-rule override applied uniformly to the whole quotation —
/// instead of a manual flat amount.
class QuotationFormSheet extends StatefulWidget {
  const QuotationFormSheet({super.key, this.quotation, required this.formatter});

  final QuotationModel? quotation;
  final CurrencyFormatter formatter;

  @override
  State<QuotationFormSheet> createState() => _QuotationFormSheetState();
}

class _QuotationFormSheetState extends State<QuotationFormSheet> {
  late final TextEditingController _discountController;
  late final TextEditingController _notesController;
  late final TextEditingController _termsController;
  late List<Map<String, dynamic>> _items;
  late final CustomersRepository _customersRepository;
  late final TaxesRepository _taxesRepository;

  CustomerModel? _selectedCustomer;
  String _fallbackCustomerName = '';
  List<TaxRuleModel> _taxRules = [];
  TaxRuleModel? _taxRuleOverride;

  bool get _isEditing => widget.quotation != null;

  @override
  void initState() {
    super.initState();
    final quote = widget.quotation;
    _discountController = TextEditingController(text: (quote?.discount ?? 0).toStringAsFixed(2));
    _notesController = TextEditingController(text: quote?.notes ?? '');
    _termsController = TextEditingController(text: quote?.terms ?? '');
    _items = quote?.items.map((e) => Map<String, dynamic>.from(e)).toList() ?? [];
    _fallbackCustomerName = quote?.customerName ?? '';
    if (quote?.customerId != null) {
      _selectedCustomer = CustomerModel(
        id: quote!.customerId!,
        name: quote.customerName,
        phone: '',
        email: '',
        document: '',
        address: '',
        city: '',
        state: '',
        balanceDue: 0,
        loyaltyPoints: 0,
      );
    }

    _customersRepository = CustomersRepository(context.read<ApiClient>());
    _taxesRepository = TaxesRepository(context.read<ApiClient>());
    _loadTaxRules();
  }

  Future<void> _loadTaxRules() async {
    try {
      final rules = await _taxesRepository.fetchTaxes();
      if (!mounted) return;
      setState(() => _taxRules = rules);
    } catch (_) {
      // Tax-rule override is a convenience on top of each product's own
      // tax_rate — silently skip it if the list can't be loaded.
    }
  }

  @override
  void dispose() {
    _discountController.dispose();
    _notesController.dispose();
    _termsController.dispose();
    super.dispose();
  }

  double get _subtotal => _items.fold(0.0, (sum, item) {
        final qty = (item['quantity'] as num?)?.toDouble() ?? 0;
        final price = (item['price'] as num?)?.toDouble() ?? 0;
        return sum + (qty * price);
      });

  /// The effective tax rate (%) for a line item — the whole-quotation
  /// override if one is set, otherwise the rate captured from the product
  /// when it was added.
  double _itemTaxRate(Map<String, dynamic> item) {
    if (_taxRuleOverride != null) return _taxRuleOverride!.rate;
    return (item['tax_rate'] as num?)?.toDouble() ?? 0;
  }

  double _itemTaxAmount(Map<String, dynamic> item) {
    final qty = (item['quantity'] as num?)?.toDouble() ?? 0;
    final price = (item['price'] as num?)?.toDouble() ?? 0;
    return qty * price * _itemTaxRate(item) / 100;
  }

  double get _taxTotal => _items.fold(0.0, (sum, item) => sum + _itemTaxAmount(item));

  Future<void> _pickCustomer() async {
    final customer = await showModalBottomSheet<CustomerModel>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => CustomerPickerSheet(customersRepository: _customersRepository),
    );
    if (customer != null) {
      setState(() => _selectedCustomer = customer);
    }
  }

  Future<void> _addProduct() async {
    final product = await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ProductPickerSheet(inventoryRepository: InventoryRepository(context.read<ApiClient>())),
    );

    if (product == null) return;
    setState(() {
      _items.add({
        'id': product.id,
        'product_id': product.id,
        'name': product.name,
        'price': product.salePrice,
        'quantity': 1.0,
        'tax_rate': product.taxRate,
      });
    });
  }

  Future<void> _overrideItemTaxRate(int index) async {
    final item = _items[index];
    final rate = await showModalBottomSheet<double>(
      context: context,
      builder: (sheetCtx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text('Tax rate for this item', style: TextStyle(fontWeight: FontWeight.bold)),
            ),
            ListTile(
              title: const Text('No tax (0%)'),
              onTap: () => Navigator.of(sheetCtx).pop(0.0),
            ),
            for (final rule in _taxRules)
              ListTile(
                title: Text(rule.name),
                trailing: Text('${rule.rate.toStringAsFixed(rule.rate.truncateToDouble() == rule.rate ? 0 : 2)}%'),
                onTap: () => Navigator.of(sheetCtx).pop(rule.rate),
              ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
    if (rate == null) return;
    setState(() => item['tax_rate'] = rate);
  }

  Future<void> _submit() async {
    final customerName = _selectedCustomer?.name ?? _fallbackCustomerName;
    if (customerName.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Select a customer.')));
      return;
    }
    if (_items.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Add at least one item.')));
      return;
    }

    final quotations = context.read<QuotationsProvider>();
    final itemsToSend = _items.map((item) => {...item, 'tax_rate': _itemTaxRate(item)}).toList();
    final success = await quotations.saveQuotation(
      id: widget.quotation?.id,
      customerId: _selectedCustomer?.id,
      customerName: customerName.trim(),
      items: itemsToSend,
      discount: double.tryParse(_discountController.text) ?? 0,
      tax: _taxTotal,
      notes: _notesController.text.trim(),
      terms: _termsController.text.trim(),
    );

    if (!mounted) return;
    if (success) {
      Navigator.of(context).pop();
    } else if (quotations.actionError != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(quotations.actionError!)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final quotations = context.watch<QuotationsProvider>();
    final discount = double.tryParse(_discountController.text) ?? 0;
    final tax = _taxTotal;
    final total = (_subtotal - discount + tax).clamp(0, double.infinity);
    final customerName = _selectedCustomer?.name ?? _fallbackCustomerName;

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: DraggableScrollableSheet(
        initialChildSize: 0.9,
        minChildSize: 0.5,
        maxChildSize: 0.95,
        expand: false,
        builder: (context, scrollController) {
          return SingleChildScrollView(
            controller: scrollController,
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(_isEditing ? 'Edit quotation' : 'New quotation', style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 16),
                InkWell(
                  borderRadius: BorderRadius.circular(8),
                  onTap: _pickCustomer,
                  child: InputDecorator(
                    decoration: const InputDecoration(labelText: 'Customer', suffixIcon: Icon(Icons.search)),
                    child: Text(
                      customerName.isEmpty ? 'Select customer' : customerName,
                      style: TextStyle(color: customerName.isEmpty ? Colors.grey.shade600 : null),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Items', style: Theme.of(context).textTheme.titleMedium),
                    TextButton.icon(
                      onPressed: _addProduct,
                      icon: const Icon(Icons.add),
                      label: const Text('Add product'),
                    ),
                  ],
                ),
                if (_items.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 12),
                    child: Text('No items yet.', style: TextStyle(color: Colors.grey)),
                  )
                else
                  ..._items.asMap().entries.map((entry) {
                    final index = entry.key;
                    final item = entry.value;
                    final qty = (item['quantity'] as num?)?.toDouble() ?? 0;
                    final price = (item['price'] as num?)?.toDouble() ?? 0;
                    final rate = _itemTaxRate(item);

                    return Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: Padding(
                        padding: const EdgeInsets.all(8),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  flex: 3,
                                  child: Text(item['name'] as String? ?? 'Item', overflow: TextOverflow.ellipsis),
                                ),
                                IconButton(
                                  icon: const Icon(Icons.remove_circle_outline, size: 20),
                                  onPressed: () => setState(() {
                                    if (qty > 1) {
                                      item['quantity'] = qty - 1;
                                    } else {
                                      _items.removeAt(index);
                                    }
                                  }),
                                ),
                                Text(qty.toStringAsFixed(qty.truncateToDouble() == qty ? 0 : 2)),
                                IconButton(
                                  icon: const Icon(Icons.add_circle_outline, size: 20),
                                  onPressed: () => setState(() => item['quantity'] = qty + 1),
                                ),
                                SizedBox(
                                  width: 70,
                                  child: Text(
                                    widget.formatter.format(qty * price),
                                    textAlign: TextAlign.right,
                                    style: const TextStyle(fontWeight: FontWeight.w600),
                                  ),
                                ),
                                IconButton(
                                  icon: const Icon(Icons.delete_outline, size: 20, color: Colors.redAccent),
                                  onPressed: () => setState(() => _items.removeAt(index)),
                                ),
                              ],
                            ),
                            Padding(
                              padding: const EdgeInsets.only(left: 4),
                              child: InkWell(
                                onTap: _taxRuleOverride != null ? null : () => _overrideItemTaxRate(index),
                                child: Text(
                                  rate > 0 ? 'Tax: ${rate.toStringAsFixed(rate.truncateToDouble() == rate ? 0 : 2)}%' : 'No tax · tap to set',
                                  style: TextStyle(fontSize: 11, color: Colors.grey.shade600, decoration: TextDecoration.underline),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _discountController,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: const InputDecoration(labelText: 'Discount'),
                  onChanged: (_) => setState(() {}),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<TaxRuleModel?>(
                  initialValue: _taxRuleOverride,
                  decoration: const InputDecoration(
                    labelText: 'Tax rule',
                    helperText: 'Auto uses each item\'s own product tax rate',
                  ),
                  items: [
                    const DropdownMenuItem<TaxRuleModel?>(value: null, child: Text('Auto (per item)')),
                    for (final rule in _taxRules)
                      DropdownMenuItem<TaxRuleModel?>(
                        value: rule,
                        child: Text('${rule.name} (${rule.rate.toStringAsFixed(rule.rate.truncateToDouble() == rule.rate ? 0 : 2)}%)'),
                      ),
                  ],
                  onChanged: (rule) => setState(() => _taxRuleOverride = rule),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _notesController,
                  decoration: const InputDecoration(labelText: 'Notes (optional)'),
                  maxLines: 2,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _termsController,
                  decoration: const InputDecoration(labelText: 'Terms (optional)'),
                  maxLines: 2,
                ),
                const SizedBox(height: 16),
                _SummaryRow(label: 'Subtotal', value: widget.formatter.format(_subtotal)),
                if (discount > 0) _SummaryRow(label: 'Discount', value: '-${widget.formatter.format(discount)}'),
                if (tax > 0) _SummaryRow(label: 'Tax', value: '+${widget.formatter.format(tax)}'),
                const Divider(),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Total', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                    Text(widget.formatter.format(total), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  ],
                ),
                const SizedBox(height: 20),
                ElevatedButton(
                  onPressed: quotations.isSaving ? null : _submit,
                  child: quotations.isSaving
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : Text(_isEditing ? 'Save changes' : 'Create quotation'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(color: Colors.grey.shade700)),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}
