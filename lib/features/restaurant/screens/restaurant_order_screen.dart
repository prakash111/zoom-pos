import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/customer_model.dart';
import '../../../core/models/product_model.dart';
import '../../../core/models/restaurant_models.dart';
import '../../../core/services/thermal/thermal_printer_service.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../../customers/customers_repository.dart';
import '../../inventory/inventory_repository.dart';
import '../../pos/payment_entry.dart';
import '../../pos/screens/customer_picker_sheet.dart';
import '../../pos/screens/invoice_actions_sheet.dart';
import '../../quotations/screens/product_picker_sheet.dart';
import '../restaurant_repository.dart';
import '../widgets/kot_slip.dart';

const _kServiceTypeLabels = {
  'dine_in': 'Dine-In',
  'takeaway': 'Takeaway',
  'delivery': 'Delivery'
};

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
  int _prepMinutes = 15;
  int _intimationMinutes = 0;
  final _notesController = TextEditingController();

  /// Seat numbers for splitting a dine-in table's order (mirrors Pos.php's
  /// $seats/$activeSeat) — not used for takeaway/delivery orders.
  List<int> _seats = [1];
  int _activeSeat = 1;

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
      final guestCount = result.openOrder?.guestCount ??
          (table.guestCount > 0 ? table.guestCount : 1);
      final seatCount = <int>[4, table.seatingCapacity, guestCount]
          .reduce((a, b) => a > b ? a : b);
      setState(() {
        _saleId = result.openOrder?.id;
        _committedItems = result.openOrder?.items ?? [];
        _draftItems = [];
        _guestCount = guestCount;
        _seats = List.generate(seatCount, (i) => i + 1);
        _activeSeat = 1;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  double get _committedTotal =>
      _committedItems.fold(0.0, (sum, i) => sum + i.lineTotal);
  double get _draftTotal =>
      _draftItems.fold(0.0, (sum, i) => sum + i.lineTotal);
  double get _subtotal => _committedTotal + _draftTotal;

  void _addSeat() {
    setState(() {
      final next = _seats.isEmpty ? 1 : _seats.last + 1;
      _seats = [..._seats, next];
      _activeSeat = next;
    });
  }

  void _setActiveSeat(int seat) => setState(() => _activeSeat = seat);

  Future<void> _addItem() async {
    final inventoryRepository = InventoryRepository(context.read<ApiClient>());
    final product = await showModalBottomSheet<ProductModel>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) =>
          ProductPickerSheet(inventoryRepository: inventoryRepository),
    );
    if (product == null || !mounted) return;

    if (product.hasCustomizations) {
      final company = context.read<AuthProvider>().company;
      final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');
      final result = await showModalBottomSheet<_CustomizationResult>(
        context: context,
        isScrollControlled: true,
        shape: const RoundedRectangleBorder(
            borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
        builder: (_) =>
            _ItemCustomizationSheet(product: product, formatter: formatter),
      );
      if (result == null || !mounted) return;

      setState(() {
        _draftItems = [
          ..._draftItems,
          RestaurantOrderItemModel(
            productId: product.id,
            name: product.name,
            price: result.unitPrice,
            quantity: 1,
            variant: result.variantName,
            modifiers: result.modifiers,
            spiceLevel: result.spiceLevelName,
            note: result.note,
            seat: _activeSeat,
          ),
        ];
      });
      return;
    }

    setState(() {
      final existingIndex = _draftItems.indexWhere((i) =>
          i.productId == product.id &&
          i.note.isEmpty &&
          i.seat == _activeSeat &&
          i.variant == null &&
          i.modifiers.isEmpty &&
          i.spiceLevel == null);
      if (existingIndex != -1) {
        final existing = _draftItems[existingIndex];
        _draftItems[existingIndex] =
            existing.copyWith(quantity: existing.quantity + 1);
      } else {
        _draftItems = [
          ..._draftItems,
          RestaurantOrderItemModel(
              productId: product.id,
              name: product.name,
              price: product.salePrice,
              quantity: 1,
              seat: _activeSeat),
        ];
      }
    });
  }

  void _incrementItem(int index) {
    setState(() {
      final item = _draftItems[index];
      _draftItems[index] = item.copyWith(quantity: item.quantity + 1);
    });
  }

  void _decrementItem(int index) {
    setState(() {
      final item = _draftItems[index];
      if (item.quantity <= 1) {
        _draftItems = List.of(_draftItems)..removeAt(index);
      } else {
        _draftItems[index] = item.copyWith(quantity: item.quantity - 1);
      }
    });
  }

  /// Inline price override at POS, mirroring Pos.php's applyPriceOverride():
  /// cashiers with the `pos.edit` permission can adjust a draft line's unit
  /// price before it's sent to the kitchen; base_price is preserved for
  /// audit logging (see RestaurantOrderItemModel.isOverridden).
  Future<void> _editPrice(int index) async {
    final item = _draftItems[index];
    final controller =
        TextEditingController(text: item.price.toStringAsFixed(2));
    final newPrice = await showDialog<double>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Override Price'),
        content: TextField(
          controller: controller,
          autofocus: true,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: const InputDecoration(labelText: 'Unit Price'),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext)
                .pop(double.tryParse(controller.text.trim())),
            child: const Text('Apply'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (newPrice == null || newPrice < 0 || !mounted) return;
    setState(() {
      _draftItems[index] =
          item.copyWith(price: double.parse(newPrice.toStringAsFixed(2)));
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
        prepMinutes: _prepMinutes,
        intimationMinutes: _intimationMinutes,
        items: _draftItems,
      );
      _changed = true;
      if (!mounted) return;
      setState(() {
        _saleId = result.sale.id;
        _committedItems = result.sale.items;
        _draftItems = [];
      });
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('${result.kot.kotNumber} sent to kitchen.'),
        action: SnackBarAction(
          label: 'Print KOT',
          onPressed: () => printKitchenTicket(context, result.kot),
        ),
      ));
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  Future<void> _settleBill() async {
    if (_saleId == null || _committedItems.isEmpty) return;
    final company = context.read<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    final sale = await showModalBottomSheet<RestaurantSaleModel>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => _SettleBillSheet(
          repository: widget.repository,
          saleId: _saleId!,
          total: _committedTotal,
          formatter: formatter),
    );
    if (sale == null) return;

    _changed = true;
    if (!mounted) return;
    setState(() {
      _saleId = null;
      _committedItems = [];
      _draftItems = [];
    });

    // Show the invoice actions on this (still-live) screen rather than
    // auto-navigating back to the table list: "Preview & Print" pushes a
    // full-screen PDF viewer on top of this route, and popping back to the
    // table list right after would immediately close that viewer again
    // (Navigator.pop() targets whatever is now on top of the stack, not
    // necessarily the route this method thinks it's closing). The user
    // leaves via the app bar's back button (already wired to `_changed`)
    // once they're done previewing/printing/sharing.
    final subtotal =
        sale.items.fold(0.0, (sum, i) => sum + i.price * i.quantity);
    final tax = (sale.total - subtotal + sale.discount)
        .clamp(0, double.infinity)
        .toDouble();
    try {
      await showInvoiceActionsSheet(
        context,
        InvoiceActionsData(
          documentType: 'invoice',
          documentId: sale.id,
          documentNumber: sale.saleNumber,
          companyName: company?.tradeName ?? company?.name ?? '',
          customerName: sale.customerName,
          customerPhone: sale.customerPhone,
          customerEmail: sale.customerEmail,
          currencySymbol: company?.currencySymbol ?? '\$',
          subtotal: subtotal,
          discount: sale.discount,
          tax: tax,
          total: sale.total,
          taxId: company?.taxId,
          taxLabel: company?.taxLabel ?? 'Tax',
          isIndia: company?.isIndia ?? false,
          paidAmount: sale.paidAmount,
          dueAmount: sale.dueAmount,
          lines: sale.items
              .map((i) => ReceiptLine(
                  name: i.name,
                  quantity: i.quantity,
                  unitPrice: i.price,
                  lineTotal: i.price * i.quantity))
              .toList(),
        ),
      );
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content:
                Text('Could not open the invoice actions for this bill.')));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');
    final canOverridePrice =
        context.watch<AuthProvider>().user?.can('pos.edit') ?? false;
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
                    if (widget.table != null)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                        child: SizedBox(
                          height: 36,
                          child: ListView(
                            scrollDirection: Axis.horizontal,
                            children: [
                              for (final seat in _seats)
                                Padding(
                                  padding: const EdgeInsets.only(right: 8),
                                  child: ChoiceChip(
                                    label: Text('Seat $seat'),
                                    selected: _activeSeat == seat,
                                    onSelected: (_) => _setActiveSeat(seat),
                                  ),
                                ),
                              ActionChip(
                                avatar: const Icon(Icons.add, size: 16),
                                label: const Text('Add Seat'),
                                onPressed: _addSeat,
                              ),
                            ],
                          ),
                        ),
                      ),
                    Expanded(
                      child: (_committedItems.isEmpty && _draftItems.isEmpty)
                          ? const Center(
                              child: Text(
                                  'No items yet. Tap "Add Item" to start the order.'))
                          : ListView(
                              padding: const EdgeInsets.all(16),
                              children: [
                                if (_committedItems.isNotEmpty) ...[
                                  Text('Sent to Kitchen',
                                      style: TextStyle(
                                          fontWeight: FontWeight.bold,
                                          color: Colors.grey.shade600,
                                          fontSize: 12)),
                                  const SizedBox(height: 4),
                                  for (final item in _committedItems)
                                    _OrderItemRow(
                                        item: item, formatter: formatter),
                                  const Divider(height: 20),
                                ],
                                if (_draftItems.isNotEmpty) ...[
                                  Text('New Items (not yet sent)',
                                      style: TextStyle(
                                          fontWeight: FontWeight.bold,
                                          color: Colors.grey.shade600,
                                          fontSize: 12)),
                                  const SizedBox(height: 4),
                                  for (var index = 0;
                                      index < _draftItems.length;
                                      index++)
                                    _OrderItemRow(
                                      item: _draftItems[index],
                                      formatter: formatter,
                                      onIncrement: () => _incrementItem(index),
                                      onDecrement: () => _decrementItem(index),
                                      onEditPrice: canOverridePrice
                                          ? () => _editPrice(index)
                                          : null,
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
                                const Text('Subtotal',
                                    style:
                                        TextStyle(fontWeight: FontWeight.bold)),
                                Text(formatter.format(_subtotal),
                                    style: const TextStyle(
                                        fontWeight: FontWeight.bold,
                                        fontSize: 16)),
                              ],
                            ),
                            if (_draftItems.isNotEmpty) ...[
                              const SizedBox(height: 4),
                              Text(
                                'Send new items to kitchen before settling the bill.',
                                style: TextStyle(
                                    color: Colors.orange.shade800,
                                    fontSize: 11,
                                    fontWeight: FontWeight.w600),
                              ),
                            ],
                            const SizedBox(height: 10),
                            if (_draftItems.isNotEmpty) ...[
                              Row(
                                children: [
                                  Text('Prep time',
                                      style: TextStyle(
                                          fontSize: 11,
                                          fontWeight: FontWeight.w600,
                                          color: Colors.grey.shade700)),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Wrap(
                                      spacing: 6,
                                      children: [
                                        for (final mins in const [
                                          5,
                                          10,
                                          15,
                                          20,
                                          30
                                        ])
                                          ChoiceChip(
                                            label: Text('${mins}m',
                                                style: const TextStyle(
                                                    fontSize: 11)),
                                            visualDensity:
                                                VisualDensity.compact,
                                            selected: _prepMinutes == mins,
                                            onSelected: (_) => setState(
                                                () => _prepMinutes = mins),
                                          ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 10),
                              Row(
                                children: [
                                  Text('Alert',
                                      style: TextStyle(
                                          fontSize: 11,
                                          fontWeight: FontWeight.w600,
                                          color: Colors.grey.shade700)),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Wrap(
                                      spacing: 6,
                                      children: [
                                        for (final option in const [
                                          (0, 'At expiry'),
                                          (2, '2m before'),
                                          (5, '5m before')
                                        ])
                                          ChoiceChip(
                                            label: Text(option.$2,
                                                style: const TextStyle(
                                                    fontSize: 11)),
                                            visualDensity:
                                                VisualDensity.compact,
                                            selected:
                                                _intimationMinutes == option.$1,
                                            onSelected: (_) => setState(() =>
                                                _intimationMinutes = option.$1),
                                          ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 10),
                            ],
                            Row(
                              children: [
                                Expanded(
                                  child: OutlinedButton(
                                    onPressed:
                                        (_draftItems.isEmpty || _isSending)
                                            ? null
                                            : _sendToKitchen,
                                    child: _isSending
                                        ? const SizedBox(
                                            height: 18,
                                            width: 18,
                                            child: CircularProgressIndicator(
                                                strokeWidth: 2))
                                        : const Text('Send to Kitchen'),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: FilledButton(
                                    onPressed: (_saleId == null ||
                                            _committedItems.isEmpty ||
                                            _draftItems.isNotEmpty)
                                        ? null
                                        : _settleBill,
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
  const _OrderItemRow({
    required this.item,
    required this.formatter,
    this.onIncrement,
    this.onDecrement,
    this.onEditPrice,
  });

  final RestaurantOrderItemModel item;
  final CurrencyFormatter formatter;
  final VoidCallback? onIncrement;
  final VoidCallback? onDecrement;
  final VoidCallback? onEditPrice;

  bool get _isEditable => onIncrement != null && onDecrement != null;

  @override
  Widget build(BuildContext context) {
    final modifierNames = item.modifiers
        .map((m) => m['name']?.toString() ?? '')
        .where((n) => n.isNotEmpty)
        .join(', ');

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Flexible(
                        child: Text(item.name,
                            style:
                                const TextStyle(fontWeight: FontWeight.w600))),
                    if (item.seat != 1) ...[
                      const SizedBox(width: 6),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 6, vertical: 1),
                        decoration: BoxDecoration(
                            color: Colors.grey.shade200,
                            borderRadius: BorderRadius.circular(4)),
                        child: Text('Seat ${item.seat}',
                            style: TextStyle(
                                fontSize: 10,
                                color: Colors.grey.shade700,
                                fontWeight: FontWeight.w700)),
                      ),
                    ],
                  ],
                ),
                if (item.variant != null)
                  Text('• ${item.variant}',
                      style:
                          TextStyle(color: Colors.grey.shade600, fontSize: 11)),
                if (modifierNames.isNotEmpty)
                  Text('+ $modifierNames',
                      style: TextStyle(
                          color: Colors.blue.shade700,
                          fontSize: 11,
                          fontWeight: FontWeight.w600)),
                if (item.spiceLevel != null)
                  Text('🌶 ${item.spiceLevel}',
                      style: TextStyle(
                          color: Colors.red.shade700,
                          fontSize: 11,
                          fontWeight: FontWeight.w700)),
                if (item.note.isNotEmpty)
                  Text('Note: ${item.note}',
                      style: TextStyle(
                          color: Colors.amber.shade800,
                          fontSize: 11,
                          fontWeight: FontWeight.w700)),
                Row(
                  children: [
                    Text(formatter.format(item.price),
                        style: TextStyle(
                            color: Colors.grey.shade600, fontSize: 12)),
                    if (item.isOverridden) ...[
                      const SizedBox(width: 4),
                      Text('(edited)',
                          style: TextStyle(
                              color: Colors.orange.shade700,
                              fontSize: 10,
                              fontStyle: FontStyle.italic)),
                    ],
                    if (onEditPrice != null) ...[
                      const SizedBox(width: 4),
                      InkWell(
                          onTap: onEditPrice,
                          child: Icon(Icons.edit,
                              size: 13, color: Colors.grey.shade500)),
                    ],
                  ],
                ),
              ],
            ),
          ),
          if (_isEditable) ...[
            IconButton(
                icon: const Icon(Icons.remove_circle_outline),
                onPressed: onDecrement),
            Text(item.quantity.toStringAsFixed(
                item.quantity == item.quantity.roundToDouble() ? 0 : 1)),
            IconButton(
                icon: const Icon(Icons.add_circle_outline),
                onPressed: onIncrement),
          ] else ...[
            Icon(Icons.check_circle, size: 16, color: Colors.green.shade600),
            const SizedBox(width: 6),
            Text(
                '× ${item.quantity.toStringAsFixed(item.quantity == item.quantity.roundToDouble() ? 0 : 1)}'),
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

/// What the customization sheet hands back to be turned into a cart line.
class _CustomizationResult {
  _CustomizationResult({
    this.variantName,
    required this.unitPrice,
    this.modifiers = const [],
    this.spiceLevelName,
    this.note = '',
  });

  final String? variantName;
  final double unitPrice;
  final List<Map<String, dynamic>> modifiers;
  final String? spiceLevelName;
  final String note;
}

/// Portion/style (single-select, replaces base price), add-ons (multi-select,
/// stacks on top) and spice level (single-select) picker for a product that
/// has any of those configured, mirroring Pos.php's openModifierModal() /
/// addCustomizedItemToCart().
class _ItemCustomizationSheet extends StatefulWidget {
  const _ItemCustomizationSheet(
      {required this.product, required this.formatter});

  final ProductModel product;
  final CurrencyFormatter formatter;

  @override
  State<_ItemCustomizationSheet> createState() =>
      _ItemCustomizationSheetState();
}

class _ItemCustomizationSheetState extends State<_ItemCustomizationSheet> {
  String? _variantName;
  double _variantPrice = 0;
  final List<Map<String, dynamic>> _selectedModifiers = [];
  String? _spiceLevelName;
  double _spiceLevelPrice = 0;
  final _noteController = TextEditingController();

  @override
  void initState() {
    super.initState();
    final variants = widget.product.variants;
    if (variants.isNotEmpty) {
      _variantName = variants.first['name'] as String?;
      _variantPrice = (variants.first['price'] as num?)?.toDouble() ??
          widget.product.salePrice;
    } else {
      _variantPrice = widget.product.salePrice;
    }
    final spiceLevels = widget.product.spiceLevels;
    if (spiceLevels.isNotEmpty) {
      _spiceLevelName = spiceLevels.first['name'] as String?;
      _spiceLevelPrice = (spiceLevels.first['price'] as num?)?.toDouble() ?? 0;
    }
  }

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  double get _modifiersTotal => _selectedModifiers.fold(
      0.0, (sum, m) => sum + ((m['price'] as num?)?.toDouble() ?? 0));
  double get _totalPrice => _variantPrice + _modifiersTotal + _spiceLevelPrice;

  void _toggleModifier(Map<String, dynamic> modifier) {
    setState(() {
      final idx =
          _selectedModifiers.indexWhere((m) => m['name'] == modifier['name']);
      if (idx != -1) {
        _selectedModifiers.removeAt(idx);
      } else {
        _selectedModifiers.add(modifier);
      }
    });
  }

  String _variantLabel(Map<String, dynamic> v) {
    final name = v['name'] as String? ?? '';
    final price = (v['price'] as num?)?.toDouble() ?? 0;
    return '$name (${widget.formatter.format(price)})';
  }

  String _addOnLabel(Map<String, dynamic> option) {
    final name = option['name'] as String? ?? '';
    final price = (option['price'] as num?)?.toDouble() ?? 0;
    return price != 0 ? '$name (+${widget.formatter.format(price)})' : name;
  }

  @override
  Widget build(BuildContext context) {
    final product = widget.product;
    return Padding(
      padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom + 20),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(
          physics: const ClampingScrollPhysics(),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(product.name,
                    style: Theme.of(context)
                        .textTheme
                        .titleLarge
                        ?.copyWith(fontWeight: FontWeight.bold)),
                const SizedBox(height: 16),
                if (product.variants.isNotEmpty) ...[
                  Text('PORTION / STYLE',
                      style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: Colors.grey.shade600,
                          letterSpacing: 0.5)),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final v in product.variants)
                        ChoiceChip(
                          label: Text(_variantLabel(v)),
                          selected: _variantName == v['name'],
                          onSelected: (_) => setState(() {
                            _variantName = v['name'] as String?;
                            _variantPrice = (v['price'] as num?)?.toDouble() ??
                                product.salePrice;
                          }),
                        ),
                    ],
                  ),
                  const SizedBox(height: 16),
                ],
                if (product.modifiers.isNotEmpty) ...[
                  Text('ADD-ONS & EXTRAS',
                      style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: Colors.grey.shade600,
                          letterSpacing: 0.5)),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final m in product.modifiers)
                        FilterChip(
                          label: Text(_addOnLabel(m)),
                          selected: _selectedModifiers
                              .any((sel) => sel['name'] == m['name']),
                          onSelected: (_) => _toggleModifier(m),
                        ),
                    ],
                  ),
                  const SizedBox(height: 16),
                ],
                if (product.spiceLevels.isNotEmpty) ...[
                  Text('SPICE LEVEL',
                      style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: Colors.grey.shade600,
                          letterSpacing: 0.5)),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final s in product.spiceLevels)
                        ChoiceChip(
                          label: Text('🌶 ${_addOnLabel(s)}'),
                          selected: _spiceLevelName == s['name'],
                          onSelected: (_) => setState(() {
                            _spiceLevelName = s['name'] as String?;
                            _spiceLevelPrice =
                                (s['price'] as num?)?.toDouble() ?? 0;
                          }),
                        ),
                    ],
                  ),
                  const SizedBox(height: 16),
                ],
                TextField(
                  controller: _noteController,
                  decoration: const InputDecoration(
                      labelText: 'Kitchen Note (optional)'),
                  maxLines: 2,
                ),
                const SizedBox(height: 20),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Total',
                        style: TextStyle(fontWeight: FontWeight.bold)),
                    Text(widget.formatter.format(_totalPrice),
                        style: const TextStyle(
                            fontWeight: FontWeight.bold, fontSize: 16)),
                  ],
                ),
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: () => Navigator.of(context).pop(
                    _CustomizationResult(
                      variantName: _variantName,
                      unitPrice: _totalPrice,
                      modifiers: _selectedModifiers,
                      spiceLevelName: _spiceLevelName,
                      note: _noteController.text.trim(),
                    ),
                  ),
                  child: const Text('Add to Order'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _SettleBillSheet extends StatefulWidget {
  const _SettleBillSheet(
      {required this.repository,
      required this.saleId,
      required this.total,
      required this.formatter});

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

  bool _isSplit = false;
  final List<PaymentEntry> _splitPayments = [];
  final List<TextEditingController> _splitAmountControllers = [];
  DateTime? _dueDate;
  CustomerModel? _selectedCustomer;

  double get _tenderedAmount =>
      double.tryParse(_tenderedController.text.trim()) ?? 0.0;
  double get _changeDue =>
      (_tenderedAmount - widget.total).clamp(0, double.infinity).toDouble();
  double get _remainingDue =>
      (widget.total - _tenderedAmount).clamp(0, double.infinity).toDouble();

  Future<void> _pickCustomer() async {
    final repository = CustomersRepository(context.read<ApiClient>());
    final customer = await showModalBottomSheet<CustomerModel>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => CustomerPickerSheet(customersRepository: repository),
    );
    if (customer != null) setState(() => _selectedCustomer = customer);
  }

  @override
  void initState() {
    super.initState();
    _tenderedController.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _tenderedController.dispose();
    for (final c in _splitAmountControllers) {
      c.dispose();
    }
    super.dispose();
  }

  double get _splitTotalPaid =>
      _splitPayments.fold(0.0, (sum, p) => sum + p.amount);
  double get _remainingBalance => widget.total - _splitTotalPaid;

  void _toggleSplit() {
    setState(() {
      _isSplit = !_isSplit;
      if (_isSplit && _splitPayments.isEmpty) {
        _addSplitRow(initialAmount: widget.total);
      }
    });
  }

  void _addSplitRow({double initialAmount = 0}) {
    setState(() {
      _splitAmountControllers.add(TextEditingController(
          text: initialAmount == 0 ? '' : initialAmount.toStringAsFixed(2)));
      _splitPayments
          .add(PaymentEntry(methodCode: 'cash', amount: initialAmount));
    });
  }

  void _removeSplitRow(int index) {
    setState(() {
      _splitPayments.removeAt(index);
      _splitAmountControllers.removeAt(index).dispose();
    });
  }

  Future<void> _pickDueDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime.now().add(const Duration(days: 1)),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked != null) setState(() => _dueDate = picked);
  }

  Future<void> _settle() async {
    if (_isSplit && _remainingBalance > 0.001 && _dueDate == null) {
      setState(() => _error =
          'A due date is required when the split payment leaves a remaining balance.');
      return;
    }

    setState(() {
      _isSaving = true;
      _error = null;
    });
    try {
      // customerPhone/customerEmail on the settled sale don't always echo back
      // the walk-in customer picked in this sheet, so fall back to it here —
      // the caller (which builds InvoiceActionsData) only sees what we return.
      final sale = await widget.repository.settle(
        saleId: widget.saleId,
        paymentMethod: _isSplit ? null : _method,
        cashTendered: (!_isSplit && _method == 'cash')
            ? double.tryParse(_tenderedController.text.trim())
            : null,
        isSplitPayment: _isSplit,
        splitPayments:
            _isSplit ? _splitPayments.map((p) => p.toJson()).toList() : null,
        dueDate: _dueDate != null
            ? _dueDate!.toIso8601String().split('T').first
            : null,
        customerId: _selectedCustomer?.id,
      );
      if (!mounted) return;
      Navigator.of(context).pop(
        sale.customerPhone != null && sale.customerEmail != null
            ? sale
            : sale.copyWith(
                customerPhone: sale.customerPhone ?? _selectedCustomer?.phone,
                customerEmail: sale.customerEmail ?? _selectedCustomer?.email,
              ),
      );
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom + 20),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(
          physics: const ClampingScrollPhysics(),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Settle Bill',
                        style: Theme.of(context)
                            .textTheme
                            .titleLarge
                            ?.copyWith(fontWeight: FontWeight.bold)),
                    TextButton.icon(
                      onPressed: _toggleSplit,
                      icon: Icon(Icons.call_split,
                          size: 16,
                          color: _isSplit
                              ? Theme.of(context).colorScheme.primary
                              : Colors.grey.shade700),
                      label: Text(_isSplit ? 'Cancel Split' : 'Split Payment'),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text('Total due: ${widget.formatter.format(widget.total)}',
                    style: const TextStyle(fontWeight: FontWeight.w600)),
                const SizedBox(height: 12),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(
                      color: Colors.grey.shade100,
                      borderRadius: BorderRadius.circular(12)),
                  child: Row(
                    children: [
                      Icon(Icons.person_outline,
                          size: 18, color: Colors.grey.shade700),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _selectedCustomer == null
                            ? const Text('No customer assigned',
                                style: TextStyle(fontSize: 13))
                            : Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(_selectedCustomer!.name,
                                      style: const TextStyle(
                                          fontSize: 13,
                                          fontWeight: FontWeight.w600)),
                                  if (_selectedCustomer!.phone.isNotEmpty)
                                    Text(_selectedCustomer!.phone,
                                        style: TextStyle(
                                            fontSize: 11,
                                            color: Colors.grey.shade600)),
                                ],
                              ),
                      ),
                      if (_selectedCustomer != null)
                        TextButton(
                          onPressed: () =>
                              setState(() => _selectedCustomer = null),
                          child: const Text('Remove'),
                        )
                      else
                        TextButton.icon(
                          onPressed: _pickCustomer,
                          icon: const Icon(Icons.add, size: 16),
                          label: const Text('Add Customer'),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                if (_error != null) ...[
                  Text(_error!, style: TextStyle(color: Colors.red.shade700)),
                  const SizedBox(height: 12),
                ],
                if (!_isSplit) ...[
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
                      keyboardType:
                          const TextInputType.numberWithOptions(decimal: true),
                      decoration: const InputDecoration(
                          labelText: 'Cash Tendered by Customer'),
                    ),
                    const SizedBox(height: 10),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: _tenderedAmount >= widget.total
                            ? Colors.green.shade50
                            : Colors.orange.shade50,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                            color: _tenderedAmount >= widget.total
                                ? Colors.green.shade200
                                : Colors.orange.shade200),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            _tenderedAmount >= widget.total
                                ? 'CHANGE DUE TO CUSTOMER'
                                : 'REMAINING DUE BALANCE',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              color: _tenderedAmount >= widget.total
                                  ? Colors.green.shade800
                                  : Colors.orange.shade800,
                              fontSize: 11,
                            ),
                          ),
                          Text(
                            widget.formatter.format(
                                _tenderedAmount >= widget.total
                                    ? _changeDue
                                    : _remainingDue),
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              color: _tenderedAmount >= widget.total
                                  ? Colors.green.shade800
                                  : Colors.orange.shade800,
                              fontSize: 16,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ] else ...[
                  for (var i = 0; i < _splitPayments.length; i++)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: Row(
                        children: [
                          Expanded(
                            flex: 2,
                            child: DropdownButtonFormField<String>(
                              initialValue: _splitPayments[i].methodCode,
                              decoration: const InputDecoration(
                                  labelText: 'Method', isDense: true),
                              items: const [
                                DropdownMenuItem(
                                    value: 'cash', child: Text('Cash')),
                                DropdownMenuItem(
                                    value: 'card', child: Text('Card')),
                                DropdownMenuItem(
                                    value: 'upi', child: Text('UPI')),
                                DropdownMenuItem(
                                    value: 'other', child: Text('Other')),
                              ],
                              onChanged: (value) => setState(() =>
                                  _splitPayments[i].methodCode =
                                      value ?? 'cash'),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: TextField(
                              controller: _splitAmountControllers[i],
                              keyboardType:
                                  const TextInputType.numberWithOptions(
                                      decimal: true),
                              decoration: const InputDecoration(
                                  labelText: 'Amount', isDense: true),
                              onChanged: (value) => setState(() =>
                                  _splitPayments[i].amount =
                                      double.tryParse(value.trim()) ?? 0),
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.delete_outline, size: 20),
                            onPressed: _splitPayments.length > 1
                                ? () => _removeSplitRow(i)
                                : null,
                          ),
                        ],
                      ),
                    ),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: TextButton.icon(
                      onPressed: () => _addSplitRow(),
                      icon: const Icon(Icons.add, size: 16),
                      label: const Text('Add Payment'),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Remaining Balance',
                          style: TextStyle(fontWeight: FontWeight.w600)),
                      Text(
                        widget.formatter.format(
                            _remainingBalance > 0 ? _remainingBalance : 0),
                        style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: _remainingBalance > 0.001
                                ? Colors.orange.shade800
                                : Colors.green.shade700),
                      ),
                    ],
                  ),
                  if (_remainingBalance > 0.001) ...[
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: _pickDueDate,
                      icon: const Icon(Icons.calendar_today, size: 16),
                      label: Text(_dueDate == null
                          ? 'Set Due Date (required)'
                          : 'Due: ${_dueDate!.toIso8601String().split('T').first}'),
                    ),
                  ],
                ],
                const SizedBox(height: 20),
                FilledButton(
                  style: FilledButton.styleFrom(
                    minimumSize: const Size(double.infinity, 50),
                    padding: const EdgeInsets.symmetric(
                        vertical: 14, horizontal: 20),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10)),
                  ),
                  onPressed: _isSaving ? null : _settle,
                  child: _isSaving
                      ? const SizedBox(
                          height: 18,
                          width: 18,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white))
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
