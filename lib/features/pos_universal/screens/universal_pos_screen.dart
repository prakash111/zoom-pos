import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/sdui/sdui_action_dispatcher.dart';
import '../../../core/sdui/sdui_icon_registry.dart';
import '../../../core/services/dynamic_string_service.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/utils/responsive.dart';
import '../../../core/widgets/barcode_scanner_screen.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../local_cart.dart';
import '../pos_screen_model.dart';
import '../widgets/universal_catalog_card.dart';

/// Generic native POS screen driven entirely by the backend's `pos_screen`
/// JSON contract (see `App\Services\Sdui\PosScreenBuilder`). Gives any
/// module that emits the contract — today Pharmacy and Repair counter
/// sales — the same look and feel as Retail's hand-coded `PosScreen`
/// (search + scanner, category pills, 2-column grid, floating cart bar)
/// without a bespoke Flutter screen per vertical.
///
/// Deliberately independent of `PosProvider`/`ProductModel`/
/// `SduiCatalogLayout`, all of which are shaped around Retail's fixed
/// fields (no subtitle/badge/on_tap slot) and Retail's own cart/checkout
/// math. Retail keeps using its existing native screen untouched.
///
/// Architecture: this screen holds only a lightweight local cart
/// (id/batch → qty, used purely to drive the floating bar total). Batch
/// pickers and the checkout/settlement drawer are small JSON component
/// trees fetched from the backend (the `open_remote_sheet` action) and
/// rendered through the same dispatcher/parser `DynamicSchemaPage` uses —
/// so payment method, discount, and tendered-cash fields are entirely
/// backend-controlled and can change without another Flutter release.
class UniversalPosScreen extends StatefulWidget {
  const UniversalPosScreen({super.key, required this.endpoint});

  final String endpoint;

  @override
  State<UniversalPosScreen> createState() => _UniversalPosScreenState();
}

enum _ScreenStatus { loading, loaded, error }

class _UniversalPosScreenState extends State<UniversalPosScreen> {
  final _searchController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  final Map<String, dynamic> _formValues = {};
  final LocalCart _cart = LocalCart();

  _ScreenStatus _status = _ScreenStatus.loading;
  String? _errorMessage;
  PosScreenModel? _model;
  dynamic _selectedCategoryId;
  String? _baseUrl;

  @override
  void initState() {
    super.initState();
    _cart.addListener(_onCartChanged);
    context.read<ApiClient>().currentBaseUrl().then((url) {
      if (mounted) setState(() => _baseUrl = url);
    });
    _fetchScreen();
  }

  @override
  void dispose() {
    _cart.removeListener(_onCartChanged);
    _cart.dispose();
    _searchController.dispose();
    super.dispose();
  }

  void _onCartChanged() {
    if (mounted) setState(() {});
  }

  ApiClient? _resolveApiClient() {
    try {
      return context.read<ApiClient>();
    } catch (_) {
      return null;
    }
  }

  void _setFormValue(String key, dynamic value) {
    _formValues[key] = value;
  }

  void _showToast(String message, {bool isError = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.red.shade700 : Colors.green.shade700,
        duration: const Duration(seconds: 3),
      ),
    );
  }

  SduiActionDispatcher get _dispatcher => SduiActionDispatcher(
        resolveApiClient: _resolveApiClient,
        formKey: _formKey,
        formValues: _formValues,
        setFormValue: _setFormValue,
        onReload: () {
          if (!mounted) return;
          _cart.clear();
          _formValues.clear();
          _fetchScreen();
        },
        showToast: _showToast,
        onBeforeDispatch: _handleBeforeDispatch,
      );

  /// Intercepts `add_to_cart` client-side (mutates the local cart, never
  /// hits the network); everything else falls through to the shared
  /// dispatcher's switch unmodified.
  Future<bool> _handleBeforeDispatch(
    BuildContext context,
    Map<String, dynamic> action,
  ) async {
    if (action['type']?.toString() != 'add_to_cart') return false;

    final rawItem = action['item'];
    if (rawItem is! Map) return true;

    final item = Map<String, dynamic>.from(rawItem);
    final quantityField = item['quantity_field']?.toString();
    final quantitySource =
        quantityField != null ? (_formValues[quantityField] ?? item['quantity'] ?? 1) : (item['quantity'] ?? 1);
    final quantity = int.tryParse('$quantitySource') ?? 1;

    _cart.add(
      id: item['id'],
      batchId: item['batch_id'],
      title: item['title']?.toString() ?? '',
      subtitle: item['subtitle']?.toString(),
      unitPrice: (item['price'] as num?)?.toDouble() ?? 0,
      quantity: quantity,
      maxQuantity: (item['max_quantity'] as num?)?.toInt(),
    );

    _showToast('${item['title'] ?? 'Item'} added to cart.');

    return true;
  }

  Future<void> _fetchScreen() async {
    setState(() {
      _status = _ScreenStatus.loading;
      _errorMessage = null;
    });

    try {
      final client = _resolveApiClient();
      if (client == null) {
        throw ApiException('API client is unavailable.');
      }
      final res = await client.requestAbsolute(widget.endpoint, method: 'GET');
      final rawSchema = res['schema'] ?? res;
      final schema = rawSchema is Map<String, dynamic>
          ? rawSchema
          : Map<String, dynamic>.from(rawSchema as Map);

      final model = PosScreenModel.fromJson(schema);
      if (!mounted) return;
      setState(() {
        _model = model;
        _status = _ScreenStatus.loaded;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _status = _ScreenStatus.error;
        _errorMessage = e is ApiException ? e.message : e.toString();
      });
    }
  }

  Future<void> _scanBarcode() async {
    final code = await Navigator.of(context).push<String>(
      MaterialPageRoute(builder: (_) => const BarcodeScannerScreen()),
    );
    if (code == null || code.isEmpty || !mounted) return;

    setState(() {
      _searchController.text = code;
    });
  }

  Future<void> _handleItemTap(PosCatalogItem item) async {
    await _dispatcher.dispatch(context, item.onTap);
  }

  Future<void> _openCheckout() async {
    final model = _model;
    if (model == null) return;

    if (_cart.isEmpty) {
      _showToast('Your cart is empty. Add items before checking out.', isError: true);
      return;
    }

    final base = model.checkoutSheetEndpoint;
    if (base.isEmpty) {
      _showToast('Checkout is not available for this screen.', isError: true);
      return;
    }

    final cartParam = Uri.encodeComponent(jsonEncode(_cart.toPreview()));
    final endpoint = '$base${base.contains('?') ? '&' : '?'}cart=$cartParam';

    _setFormValue('items', _cart.toApiItems());

    await _dispatcher.dispatch(context, {
      'type': 'open_remote_sheet',
      'sheet_endpoint': endpoint,
      'title': 'Checkout & Settlement',
    });
  }

  List<PosCatalogItem> get _filteredItems {
    final model = _model;
    if (model == null) return const [];

    final query = _searchController.text.trim().toLowerCase();

    return model.items.where((item) {
      final matchesCategory =
          _selectedCategoryId == null || item.categoryId == _selectedCategoryId;
      final matchesQuery = query.isEmpty ||
          item.title.toLowerCase().contains(query) ||
          (item.subtitle?.toLowerCase().contains(query) ?? false);

      return matchesCategory && matchesQuery;
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final model = _model;
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    return Scaffold(
      appBar: AppBar(
        title: Text(context.tr(model?.title ?? 'Point of Sale')),
      ),
      body: _buildBody(model, formatter),
      bottomNavigationBar: model == null || _cart.isEmpty
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: _openCheckout,
                  icon: const Icon(Icons.shopping_cart_checkout),
                  label: Text(
                    model.cartLabelTemplate
                        .replaceAll('{count}', '${_cart.count}')
                        .replaceAll('{total}', formatter.format(_cart.total)),
                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                  ),
                ),
              ),
            ),
    );
  }

  Widget _buildBody(PosScreenModel? model, CurrencyFormatter formatter) {
    if (_status == _ScreenStatus.loading) {
      return const LoadingIndicator();
    }

    if (_status == _ScreenStatus.error || model == null) {
      return ErrorView(
        message: _errorMessage ?? context.tr('Could not load this screen.'),
        onRetry: _fetchScreen,
      );
    }

    final banner = model.banner;
    final items = _filteredItems;

    return Column(
      children: [
        if (banner != null)
          Container(
            width: double.infinity,
            color: Colors.orange.shade50,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            child: Row(
              children: [
                Icon(SduiIconRegistry.resolve(banner.icon, fallback: Icons.info_outline),
                    size: 18, color: Colors.orange.shade800),
                const SizedBox(width: 8),
                Expanded(child: Text(context.tr(banner.message))),
                if (banner.action != null)
                  TextButton(
                    onPressed: () => _dispatcher.dispatch(context, banner.action!),
                    child: Text(context.tr(banner.action!['title']?.toString() ?? 'Open')),
                  ),
              ],
            ),
          ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: TextField(
            controller: _searchController,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(
              hintText: context.tr(model.searchPlaceholder),
              prefixIcon: const Icon(Icons.search),
              suffixIcon: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (model.scannerEnabled)
                    IconButton(
                      icon: const Icon(Icons.qr_code_scanner),
                      tooltip: context.tr('Scan Barcode'),
                      onPressed: _scanBarcode,
                    ),
                  if (_searchController.text.isNotEmpty)
                    IconButton(
                      icon: const Icon(Icons.clear),
                      onPressed: () => setState(() => _searchController.clear()),
                    ),
                ],
              ),
            ),
          ),
        ),
        if (model.categories.isNotEmpty)
          SizedBox(
            height: 40,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: [
                for (final category in model.categories)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(context.tr(category.label)),
                      selected: _selectedCategoryId == category.id,
                      onSelected: (_) => setState(() => _selectedCategoryId = category.id),
                    ),
                  ),
              ],
            ),
          ),
        const SizedBox(height: 8),
        Expanded(
          child: items.isEmpty
              ? Center(
                  child: Text(
                    context.tr('No products found.'),
                    style: const TextStyle(color: Colors.grey),
                  ),
                )
              : LayoutBuilder(
                  builder: (context, constraints) {
                    return GridView.builder(
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: gridColumnsFor(constraints.maxWidth),
                        mainAxisSpacing: 12,
                        crossAxisSpacing: 12,
                        childAspectRatio: 0.85,
                      ),
                      itemCount: items.length,
                      itemBuilder: (context, index) {
                        final item = items[index];
                        return UniversalCatalogCard(
                          item: item,
                          formatter: formatter,
                          baseUrl: _baseUrl,
                          onTap: () => _handleItemTap(item),
                        );
                      },
                    );
                  },
                ),
        ),
      ],
    );
  }
}
