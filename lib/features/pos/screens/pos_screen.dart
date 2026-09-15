import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/config/bootstrap_cache.dart';
import '../../../core/services/sync/sync_engine.dart';
import '../../../core/services/thermal/thermal_printer_service.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/barcode_scanner_screen.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../../core/widgets/sdui/sdui_catalog_layouts.dart';
import '../../../l10n/app_localizations.dart';
import '../../auth/auth_provider.dart';
import '../../cash_register/cash_register_provider.dart';
import '../../cash_register/cash_register_repository.dart';
import '../../cash_register/screens/open_register_sheet.dart';
import '../../customers/customers_repository.dart';
import '../../inventory/inventory_repository.dart';
import '../held_carts_store.dart';
import '../pos_provider.dart';
import '../rx_cart_handoff.dart';
import '../sales_repository.dart';
import 'cart_sheet.dart';
import 'invoice_actions_sheet.dart';

/// Entry point for the POS module. Agnostic layout driven by [BootstrapCache.instance.activeModule].
class PosScreen extends StatelessWidget {
  const PosScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final apiClient = context.read<ApiClient>();
    final heldCartsStore = context.read<HeldCartsStore>();
    final syncEngine = context.read<SyncEngine>();

    return ChangeNotifierProvider(
      create: (_) {
        final provider = PosProvider(
          inventoryRepository: InventoryRepository(apiClient),
          salesRepository: SalesRepository(apiClient),
          cashRegisterRepository: CashRegisterRepository(apiClient),
          heldCartsStore: heldCartsStore,
          syncEngine: syncEngine,
        );
        // Drain a document staged by a "load into POS" action (pharmacy Rx or
        // repair ticket) BEFORE the catalog load kicks off, so the resolved
        // line items are matched and injected the moment products arrive.
        final pending = RxCartHandoff.instance.take();
        if (pending != null) {
          if (pending['_handoff_kind'] == 'repair') {
            provider.loadRepairTicket(pending);
          } else {
            provider.loadPrescription(pending);
          }
        }
        return provider
          ..loadCatalog()
          ..checkRegisterStatus();
      },
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
        create: (_) =>
            CashRegisterProvider(repository: CashRegisterRepository(apiClient)),
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
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ChangeNotifierProvider.value(
        value: posProvider,
        child: CartSheet(customersRepository: customersRepository),
      ),
    );

    if (result == null || !context.mounted) return;

    if (result.isPendingSync) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppLocalizations.of(context).saleQueuedOffline)),
      );
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
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

  @override
  Widget build(BuildContext context) {
    final pos = context.watch<PosProvider>();
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');
    final isDesktopOrTabletWide = MediaQuery.of(context).size.width >= 768;
    final l10n = AppLocalizations.of(context);
    final activeModule = BootstrapCache.instance.activeModule;

    final catalogWidget = Column(
      children: [
        if (pos.registerOpen == false)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
              color: Theme.of(context).brightness == Brightness.dark
                  ? const Color(0xFF2A1E17)
                  : const Color(0xFFFEF3C7),
              border: Border.all(
                color: const Color(0xFFD97706),
                width: 1,
              ),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.info_outline,
                  size: 18,
                  color: Theme.of(context).brightness == Brightness.dark
                      ? const Color(0xFFFCD34D)
                      : const Color(0xFFD97706),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    l10n.text(
                      'registerOptionalBanner',
                      fallback:
                          'No cash register is open. Sales can continue outside a register session.',
                    ),
                    style: TextStyle(
                      color: Theme.of(context).brightness == Brightness.dark
                          ? const Color(0xFFFCD34D)
                          : const Color(0xFF854D0E),
                      fontSize: 12,
                    ),
                  ),
                ),
                TextButton(
                  onPressed: () => _openRegisterPrompt(context),
                  child: Text(
                    l10n.open,
                    style: const TextStyle(
                      color: Color(0xFF10B981),
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
          ),
        if (pos.hasRxContext)
          Container(
            width: double.infinity,
            color: const Color(0xFFECFDF5),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            child: Row(
              children: [
                Icon(
                    pos.isRepairContext
                        ? Icons.handyman_outlined
                        : Icons.medical_information_outlined,
                    size: 18,
                    color: const Color(0xFF047857)),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    [
                      if ((pos.rxNumber ?? '').isNotEmpty)
                        '${pos.isRepairContext ? 'Ticket' : 'Rx'} #${pos.rxNumber}',
                      if ((pos.selectedCustomer?.name ?? '').isNotEmpty)
                        '${pos.selectedCustomer!.name} (CRM LINKED)',
                      if ((pos.rxDoctorName ?? '').isNotEmpty)
                        pos.isRepairContext
                            ? pos.rxDoctorName!
                            : 'Dr. ${pos.rxDoctorName}',
                    ].join('  ·  '),
                    style: const TextStyle(
                        fontSize: 12.5,
                        color: Color(0xFF065F46),
                        fontWeight: FontWeight.w600),
                  ),
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
                  if (activeModule.features['has_barcode_scanner'] != false)
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
                    label: Text(l10n.text('all', fallback: 'All')),
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
        Expanded(child: _buildBody(pos, formatter, _baseUrl, l10n)),
      ],
    );

    return Scaffold(
      appBar: AppBar(
        title: Text(activeModule.title),
        actions: [
          if (pos.heldCarts.isNotEmpty)
            IconButton(
              icon: Badge(
                label: Text('${pos.heldCarts.length}'),
                child: const Icon(Icons.pause_circle_outline),
              ),
              tooltip: l10n.text('heldOrders', fallback: 'Held orders'),
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
                  child: CartSheet(
                      customersRepository:
                          CustomersRepository(context.read<ApiClient>())),
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
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: () => _openCart(context),
                  icon: const Icon(Icons.shopping_cart_checkout),
                  label: Text(
                    '${l10n.text('viewCart', fallback: 'View Cart')} · ${pos.cartCount} ${pos.cartCount == 1 ? l10n.text('item', fallback: 'item') : l10n.text('items', fallback: 'items')} · ${formatter.format(pos.grandTotal)}',
                    style: const TextStyle(
                        fontSize: 15, fontWeight: FontWeight.bold),
                  ),
                ),
              ),
            ),
    );
  }

  Widget _buildBody(PosProvider pos, CurrencyFormatter formatter,
      String? baseUrl, AppLocalizations l10n) {
    switch (pos.catalogStatus) {
      case CatalogStatus.loading:
        return const LoadingIndicator();
      case CatalogStatus.error:
        return ErrorView(
          message: pos.catalogError ??
              l10n.text('couldNotLoadProducts',
                  fallback: 'Could not load products.'),
          onRetry: pos.loadCatalog,
        );
      case CatalogStatus.loaded:
        return SduiCatalogLayout(
          layoutType: BootstrapCache.instance.activeModule.layoutType,
          products: pos.filteredProducts,
          formatter: formatter,
          baseUrl: baseUrl,
          onProductSelected: pos.addToCart,
          emptyMessage:
              l10n.text('noProductsFound', fallback: 'No products found.'),
        );
    }
  }
}
