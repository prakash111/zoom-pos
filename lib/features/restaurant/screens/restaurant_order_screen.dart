import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/product_model.dart';
import '../../../core/models/restaurant_models.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../../inventory/inventory_repository.dart';
import '../../quotations/screens/product_picker_sheet.dart';
import '../restaurant_repository.dart';

const _kServiceTypeLabels = {'dine_in': 'Dine-In', 'takeaway': 'Takeaway', 'delivery': 'Delivery'};

/// Order-taking screen for a single dining table (or a table-less takeaway/
/// delivery order): build a cart, send it to the kitchen (creating/updating
/// a KOT), and settle the bill when the guest is ready to pay.
///
/// Pops `true` if anything changed server-side (order sent/settled) so the
/// tables screen knows to reload.
class RestaurantOrderScreen extends StatefulWidget {
  const RestaurantOrderScreen({
    super.key,
    required this.repository,
    this.table,
    this.serviceType = 'dine_in',
  });

  final RestaurantRepository repository;
  final DiningTableModel? table;
  final String serviceType;

  @override
  State<RestaurantOrderScreen> createState() => _RestaurantOrderScreenState();
}

class _RestaurantOrderScreenState extends State<RestaurantOrderScreen> {
  bool _isLoading = true;
  String? _error;
  bool _changed = false;
  bool _isSending = false;

  String? _saleId;

  /// Items already persisted server-side (from a prior Send to Kitchen in
  /// this order, or an existing open order loaded for this table) — shown
  /// read-only. Only [_draftItems] are ever sent to `sendToKitchen`: the
  /// backend appends whatever it receives onto the sale's existing items
  /// (RestaurantApiController::sendToKitchen), so resending [_committedItems]
  /// alongside new ones would duplicate every previously-sent line on the
  /// bill and the kitchen ticket.
  List<RestaurantOrderItemModel> _committedItems = [];
  List<RestaurantOrderItemModel> _draftItems = [];
  int _guestCount = 1;
  final _notesController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final table = widget.table;
    if (table == null) {
      setState(() => _isLoading = false);
      return;
    }

    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final result = await widget.repository.fetchTable(table.id);
      if (!mounted) return;
      setState(() {
        _saleId = result.openOrder?.id;
        _committedItems = result.openOrder?.items ?? [];
        _draftItems = [];
        _guestCount = result.openOrder?.guestCount ?? (table.guestCount > 0 ? table.guestCount : 1);
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  double get _committedTotal => _committedItems.fold(0.0, (sum, i) => sum + i.lineTotal);
  double get _draftTotal => _draftItems.fold(0.0, (sum, i) => sum + i.lineTotal);
  double get _subtotal => _committedTotal + _draftTotal;

  Future<void> _addItem() async {
    final inventoryRepository = InventoryRepository(context.read<ApiClient>());
    final product = await showModalBottomSheet<ProductModel>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ProductPickerSheet(inventoryRepository: inventoryRepository),
    );
    if (product == null) return;

    setState(() {
      final existingIndex = _draftItems.indexWhere((i) => i.productId == product.id && (i.note.isEmpty));
      if (existingIndex != -1) {
        final existing = _draftItems[existingIndex];
        _draftItems[existingIndex] = RestaurantOrderItemModel(
          id: existing.id,
          productId: existing.productId,
          name: existing.name,
          price: existing.price,
          basePrice: existing.basePrice,
          quantity: existing.quantity + 1,
          note: existing.note,
          seat: existing.seat,
        );
      } else {
        _draftItems = [
          ..._draftItems,
          RestaurantOrderItemModel(productId: product.id, name: product.name, price: product.salePrice, quantity: 1),
        ];
      }
    });
  }

  void _incrementItem(int index) {
    setState(() {
      final item = _draftItems[index];
      _draftItems[index] = RestaurantOrderItemModel(
        id: item.id,
        productId: item.productId,
        name: item.name,
        price: item.price,
        basePrice: item.basePrice,
        quantity: item.quantity + 1,
        note: item.note,
        seat: item.seat,
      );
    });
  }

  void _decrementItem(int index) {
    setState(() {
      final item = _draftItems[index];
      if (item.quantity <= 1) {
        _draftItems = List.of(_draftItems)..removeAt(index);
      } else {
        _draftItems[index] = RestaurantOrderItemModel(
          id: item.id,
          productId: item.productId,
          name: item.name,
          price: item.price,
          basePrice: item.basePrice,
          quantity: item.quantity - 1,
          note: item.note,
          seat: item.seat,
        );
      }
    });
  }

  Future<void> _sendToKitchen() async {
    if (_draftItems.isEmpty) return;
    setState(() => _isSending = true);
    try {
      final result = await widget.repository.sendToKitchen(
        serviceType: widget.table != null ? 'dine_in' : widget.serviceType,
        tableId: widget.table?.id,
        saleId: _saleId,
        guestCount: _guestCount,
        notes: _notesController.text.trim(),
        items: _draftItems,
      );
      _changed = true;
      if (!mounted) return;
      setState(() {
        _saleId = result.sale.id;
        _committedItems = result.sale.items;
        _draftItems = [];
      });
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('${result.kot.kotNumber} sent to kitchen.')));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  Future<void> _settleBill() async {
    if (_saleId == null || _committedItems.isEmpty) return;
    final company = context.read<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    final settled = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) =>
          _SettleBillSheet(repository: widget.repository, saleId: _saleId!, total: _committedTotal, formatter: formatter),
    );

    if (settled == true) {
      _changed = true;
      if (mounted) Navigator.of(context).pop(true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');
    final title = widget.table != null
        ? 'Table ${widget.table!.tableNumber}'
        : (_kServiceTypeLabels[widget.serviceType] ?? 'Order');

    return Scaffold(
        appBar: AppBar(
          title: Text(title),
          leading: IconButton(
            icon: const Icon(Icons.arrow_back),
            onPressed: () => Navigator.of(context).pop(_changed),
          ),
        ),
        body: _isLoading
            ? const LoadingIndicator()
            : _error != null
                ? ErrorView(message: _error!, onRetry: _load)
                : Column(
                    children: [
                      Expanded(
                        child: (_committedItems.isEmpty && _draftItems.isEmpty)
                            ? const Center(child: Text('No items yet. Tap "Add Item" to start the order.'))
                            : ListView(
                                padding: const EdgeInsets.all(16),
                                children: [
                                  if (_committedItems.isNotEmpty) ...[
                                    Text('Sent to Kitchen', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.grey.shade600, fontSize: 12)),
                                    const SizedBox(height: 4),
                                    for (final item in _committedItems) _OrderItemRow(item: item, formatter: formatter),
                                    const Divider(height: 20),
                                  ],
                                  if (_draftItems.isNotEmpty) ...[
                                    Text('New Items (not yet sent)', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.grey.shade600, fontSize: 12)),
                                    const SizedBox(height: 4),
                                    for (var index = 0; index < _draftItems.length; index++)
                                      _OrderItemRow(
                                        item: _draftItems[index],
                                        formatter: formatter,
                                        onIncrement: () => _incrementItem(index),
                                        onDecrement: () => _decrementItem(index),
                                      ),
                                  ],
                                ],
                              ),
                      ),
                      SafeArea(
                        top: false,
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              OutlinedButton.icon(
                                onPressed: _addItem,
                                icon: const Icon(Icons.add),
                                label: const Text('Add Item'),
                              ),
                              const SizedBox(height: 10),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text('Subtotal', style: TextStyle(fontWeight: FontWeight.bold)),
                                  Text(formatter.format(_subtotal), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                                ],
                              ),
                              if (_draftItems.isNotEmpty) ...[
                                const SizedBox(height: 4),
                                Text(
                                  'Send new items to kitchen before settling the bill.',
                                  style: TextStyle(color: Colors.orange.shade800, fontSize: 11, fontWeight: FontWeight.w600),
                                ),
                              ],
                              const SizedBox(height: 10),
                              Row(
                                children: [
                                  Expanded(
                                    child: OutlinedButton(
                                      onPressed: (_draftItems.isEmpty || _isSending) ? null : _sendToKitchen,
                                      child: _isSending
                                          ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                          : const Text('Send to Kitchen'),
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: FilledButton(
                                      onPressed: (_saleId == null || _committedItems.isEmpty || _draftItems.isNotEmpty) ? null : _settleBill,
                                      child: const Text('Settle Bill'),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
    );
  }
}

/// One cart line. Committed (already sent-to-kitchen) items omit
/// [onIncrement]/[onDecrement] and render read-only with a "sent" mark,
/// since editing them wouldn't be reflected server-side without resending —
/// see the [_RestaurantOrderScreenState] docblock on `_committedItems`.
class _OrderItemRow extends StatelessWidget {
  const _OrderItemRow({required this.item, required this.formatter, this.onIncrement, this.onDecrement});

  final RestaurantOrderItemModel item;
  final CurrencyFormatter formatter;
  final VoidCallback? onIncrement;
  final VoidCallback? onDecrement;

  bool get _isEditable => onIncrement != null && onDecrement != null;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                Text(formatter.format(item.price), style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
              ],
            ),
          ),
          if (_isEditable) ...[
            IconButton(icon: const Icon(Icons.remove_circle_outline), onPressed: onDecrement),
            Text(item.quantity.toStringAsFixed(item.quantity == item.quantity.roundToDouble() ? 0 : 1)),
            IconButton(icon: const Icon(Icons.add_circle_outline), onPressed: onIncrement),
          ] else ...[
            Icon(Icons.check_circle, size: 16, color: Colors.green.shade600),
            const SizedBox(width: 6),
            Text('× ${item.quantity.toStringAsFixed(item.quantity == item.quantity.roundToDouble() ? 0 : 1)}'),
            const SizedBox(width: 12),
          ],
          SizedBox(
            width: 70,
            child: Text(
              formatter.format(item.lineTotal),
              textAlign: TextAlign.right,
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
          ),
        ],
      ),
    );
  }
}

class _SettleBillSheet extends StatefulWidget {
  const _SettleBillSheet({required this.repository, required this.saleId, required this.total, required this.formatter});

  final RestaurantRepository repository;
  final String saleId;
  final double total;
  final CurrencyFormatter formatter;

  @override
  State<_SettleBillSheet> createState() => _SettleBillSheetState();
}

class _SettleBillSheetState extends State<_SettleBillSheet> {
  String _method = 'cash';
  final _tenderedController = TextEditingController();
  bool _isSaving = false;
  String? _error;

  @override
  void dispose() {
    _tenderedController.dispose();
    super.dispose();
  }

  Future<void> _settle() async {
    setState(() {
      _isSaving = true;
      _error = null;
    });
    try {
      await widget.repository.settle(
        saleId: widget.saleId,
        paymentMethod: _method,
        cashTendered: _method == 'cash' ? double.tryParse(_tenderedController.text.trim()) : null,
      );
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom + 20),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(
          physics: const ClampingScrollPhysics(),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text('Settle Bill', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                const SizedBox(height: 4),
                Text('Total due: ${widget.formatter.format(widget.total)}', style: const TextStyle(fontWeight: FontWeight.w600)),
                const SizedBox(height: 16),
                if (_error != null) ...[
                  Text(_error!, style: TextStyle(color: Colors.red.shade700)),
                  const SizedBox(height: 12),
                ],
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final m in const ['cash', 'card', 'upi', 'other'])
                      ChoiceChip(
                        label: Text(m[0].toUpperCase() + m.substring(1)),
                        selected: _method == m,
                        onSelected: (_) => setState(() => _method = m),
                      ),
                  ],
                ),
                if (_method == 'cash') ...[
                  const SizedBox(height: 12),
                  TextField(
                    controller: _tenderedController,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: const InputDecoration(labelText: 'Cash Tendered by Customer'),
                  ),
                ],
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: _isSaving ? null : _settle,
                  child: _isSaving
                      ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('Settle Bill'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
