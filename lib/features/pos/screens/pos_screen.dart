import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/models/product_model.dart';
import '../../../core/services/sync/sync_engine.dart';
import '../../../core/services/thermal/thermal_printer_service.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/utils/image_url.dart';
import '../../../core/utils/responsive.dart';
import '../../../core/widgets/barcode_scanner_screen.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/auth_provider.dart';
import '../../cash_register/cash_register_provider.dart';
import '../../cash_register/cash_register_repository.dart';
import '../../cash_register/screens/open_register_sheet.dart';
import '../../customers/customers_repository.dart';
import '../../inventory/inventory_repository.dart';
import '../held_carts_store.dart';
import '../pos_provider.dart';
import '../sales_repository.dart';
import 'cart_sheet.dart';
import 'invoice_actions_sheet.dart';

/// Entry point for the POS module. Owns a [PosProvider] scoped to this route
/// so the cart resets whenever a fresh sale is started from the dashboard.
class PosScreen extends StatelessWidget {
  const PosScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final apiClient = context.read<ApiClient>();

    final heldCartsStore = context.read<HeldCartsStore>();
    final syncEngine = context.read<SyncEngine>();

    return ChangeNotifierProvider(
      create: (_) => PosProvider(
        inventoryRepository: InventoryRepository(apiClient),
        salesRepository: SalesRepository(apiClient),
        cashRegisterRepository: CashRegisterRepository(apiClient),
        heldCartsStore: heldCartsStore,
        syncEngine: syncEngine,
      )
        ..loadCatalog()
        ..checkRegisterStatus(),
      child: const _PosScreenBody(),
    );
  }
}

class _PosScreenBody extends StatefulWidget {
  const _PosScreenBody();

  @override
  State<_PosScreenBody> createState() => _PosScreenBodyState();
}

class _PosScreenBodyState extends State<_PosScreenBody> {
  final _searchController = TextEditingController();
  String? _baseUrl;

  @override
  void initState() {
    super.initState();
    context.read<ApiClient>().currentBaseUrl().then((url) {
      if (mounted) setState(() => _baseUrl = url);
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _scanBarcode(BuildContext context) async {
    final code = await Navigator.of(context).push<String>(
      MaterialPageRoute(builder: (_) => const BarcodeScannerScreen()),
    );
    if (code == null || code.isEmpty || !context.mounted) return;

    _searchController.text = code;
    context.read<PosProvider>().setSearchQuery(code);
  }

  Future<void> _openRegisterPrompt(BuildContext context) async {
    final apiClient = context.read<ApiClient>();
    final pos = context.read<PosProvider>();

    await showDialog(
      context: context,
      builder: (_) => ChangeNotifierProvider(
        create: (_) => CashRegisterProvider(repository: CashRegisterRepository(apiClient)),
        child: const OpenRegisterSheet(),
      ),
    );

    if (context.mounted) {
      await pos.checkRegisterStatus();
    }
  }

  Future<void> _openCart(BuildContext context) async {
    final posProvider = context.read<PosProvider>();
    final customersRepository = CustomersRepository(context.read<ApiClient>());
    final company = context.read<AuthProvider>().company;

    final result = await showModalBottomSheet<PosCheckoutResult>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ChangeNotifierProvider.value(
        value: posProvider,
        child: CartSheet(customersRepository: customersRepository),
      ),
    );

    if (result == null || !context.mounted) return;

    if (result.isPendingSync) {
      // No server-side sale exists yet to preview/print/share — it's only
      // created once the outbox pushes successfully — so skip straight past
      // the invoice actions sheet instead of pointing it at a document id
      // the server doesn't have.
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppLocalizations.of(context).saleQueuedOffline)),
      );
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AppLocalizations.of(context).saleCompleted)));
    await showInvoiceActionsSheet(
      context,
      InvoiceActionsData(
        documentType: 'invoice',
        documentId: result.saleId,
        documentNumber: result.saleNumber,
        companyName: company?.tradeName ?? company?.name ?? '',
        customerName: result.customerName,
        currencySymbol: company?.currencySymbol ?? '\$',
        subtotal: result.subtotal,
        discount: result.discount,
        tax: result.tax,
        total: result.total,
        taxId: company?.taxId,
        taxLabel: company?.taxLabel ?? 'Tax',
        isIndia: company?.isIndia ?? false,
        taxRate: (result.subtotal - result.discount) > 0 ? result.tax / (result.subtotal - result.discount) * 100 : 0,
        paidAmount: result.paidAmount,
        dueAmount: result.dueAmount,
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

  @override
  Widget build(BuildContext context) {
    final pos = context.watch<PosProvider>();
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');
    final isDesktopOrTabletWide = isWide(context);
    final l10n = AppLocalizations.of(context);

    final catalogWidget = Column(
      children: [
        if (pos.registerOpen == false)
          Container(
            width: double.infinity,
            color: Colors.orange.shade50,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            child: Row(
              children: [
                Icon(Icons.lock_clock_outlined, size: 18, color: Colors.orange.shade800),
                const SizedBox(width: 8),
                Expanded(child: Text(l10n.registerClosedBanner)),
                TextButton(
                  onPressed: () => _openRegisterPrompt(context),
                  child: Text(l10n.open),
                ),
              ],
            ),
          ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: TextField(
            controller: _searchController,
            onChanged: pos.setSearchQuery,
            decoration: InputDecoration(
              hintText: l10n.searchProductsHint,
              prefixIcon: const Icon(Icons.search),
              suffixIcon: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  IconButton(
                    icon: const Icon(Icons.qr_code_scanner),
                    tooltip: l10n.scanBarcode,
                    onPressed: () => _scanBarcode(context),
                  ),
                  if (pos.searchQuery.isNotEmpty)
                    IconButton(
                      icon: const Icon(Icons.clear),
                      onPressed: () {
                        _searchController.clear();
                        pos.setSearchQuery('');
                      },
                    ),
                ],
              ),
            ),
          ),
        ),
        if (pos.categories.isNotEmpty)
          SizedBox(
            height: 40,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: [
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: const Text('All'),
                    selected: pos.selectedCategoryId == null,
                    onSelected: (_) => pos.setCategory(null),
                  ),
                ),
                for (final category in pos.categories)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(category.name),
                      selected: pos.selectedCategoryId == category.id,
                      onSelected: (_) => pos.setCategory(category.id),
                    ),
                  ),
              ],
            ),
          ),
        const SizedBox(height: 8),
        Expanded(child: _buildBody(pos, formatter, _baseUrl)),
      ],
    );

    return Scaffold(
      appBar: AppBar(
        title: const Text('Point of Sale'),
        actions: [
          if (pos.heldCarts.isNotEmpty)
            IconButton(
              icon: Badge(
                label: Text('${pos.heldCarts.length}'),
                child: const Icon(Icons.pause_circle_outline),
              ),
              tooltip: 'Held orders',
              onPressed: () => _openCart(context),
            ),
        ],
      ),
      body: isDesktopOrTabletWide
          ? Row(
              children: [
                Expanded(flex: 6, child: catalogWidget),
                const VerticalDivider(width: 1),
                SizedBox(
                  width: 400,
                  child: CartSheet(customersRepository: CustomersRepository(context.read<ApiClient>())),
                ),
              ],
            )
          : catalogWidget,
      bottomNavigationBar: isDesktopOrTabletWide || pos.cartIsEmpty
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: () => _openCart(context),
                  icon: const Icon(Icons.shopping_cart_checkout),
                  label: Text(
                    'View Cart · ${pos.cartCount} item${pos.cartCount == 1 ? '' : 's'} · ${formatter.format(pos.grandTotal)}',
                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                  ),
                ),
              ),
            ),
    );
  }

  Widget _buildBody(PosProvider pos, CurrencyFormatter formatter, String? baseUrl) {
    switch (pos.catalogStatus) {
      case CatalogStatus.loading:
        return const LoadingIndicator();
      case CatalogStatus.error:
        return ErrorView(message: pos.catalogError ?? 'Could not load products.', onRetry: pos.loadCatalog);
      case CatalogStatus.loaded:
        final products = pos.filteredProducts;
        if (products.isEmpty) {
          return const Center(child: Text('No products found.'));
        }
        return LayoutBuilder(
          builder: (context, constraints) {
            return GridView.builder(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: gridColumnsFor(constraints.maxWidth),
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: 0.85,
              ),
              itemCount: products.length,
              itemBuilder: (context, index) {
                final product = products[index];
                return _ProductCard(
                  product: product,
                  formatter: formatter,
                  baseUrl: baseUrl,
                  onTap: product.isOutOfStock ? null : () => pos.addToCart(product),
                );
              },
            );
          },
        );
    }
  }
}

class _ProductCard extends StatelessWidget {
  const _ProductCard({required this.product, required this.formatter, required this.onTap, this.baseUrl});

  final ProductModel product;
  final CurrencyFormatter formatter;
  final VoidCallback? onTap;
  final String? baseUrl;

  @override
  Widget build(BuildContext context) {
    final resolvedImageUrl = resolveImageUrl(product.imageUrl, baseUrl: baseUrl);

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: SizedBox.expand(
                    child: resolvedImageUrl == null
                        ? _ProductImagePlaceholder()
                        : CachedNetworkImage(
                            imageUrl: resolvedImageUrl,
                            fit: BoxFit.cover,
                            placeholder: (context, url) => const Center(
                              child: SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(strokeWidth: 2),
                              ),
                            ),
                            errorWidget: (context, url, error) => _ProductImagePlaceholder(),
                          ),
                  ),
                ),
              ),
              Text(
                product.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 4),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    formatter.format(product.salePrice),
                    style: TextStyle(color: Theme.of(context).colorScheme.primary, fontWeight: FontWeight.bold),
                  ),
                  if (product.isOutOfStock)
                    Text('Out of stock', style: TextStyle(color: Colors.red.shade400, fontSize: 11))
                  else if (product.isLowStock)
                    Text('Low stock', style: TextStyle(color: Colors.orange.shade700, fontSize: 11)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ProductImagePlaceholder extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.grey.shade100,
      child: Center(
        child: Icon(Icons.inventory_2_outlined, size: 36, color: Colors.grey.shade400),
      ),
    );
  }
}
