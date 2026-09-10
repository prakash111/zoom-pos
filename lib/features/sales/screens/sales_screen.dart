import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/sale_model.dart';
import '../../../core/services/tenant_time_service.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../../pos/sales_repository.dart';
import 'sale_detail_screen.dart';

final _dateFormat = DateFormat('MMM d, y · h:mm a');

/// Sales history: the most recent completed sales pulled back from the
/// server (see [SalesRepository.fetchRecentSales]), searchable by sale
/// number or customer name. Read-only — new sales are recorded from the
/// Point of Sale module.
class SalesScreen extends StatefulWidget {
  const SalesScreen({super.key, this.initialFilter});

  /// A pre-applied text filter (e.g. a "Popular Tag" tapped on the dashboard).
  /// Shown as a dismissible chip and matched against the sale #, customer and
  /// line-item names.
  final String? initialFilter;

  @override
  State<SalesScreen> createState() => _SalesScreenState();
}

class _SalesScreenState extends State<SalesScreen> {
  late final SalesRepository _repository;
  late Future<List<SaleModel>> _future;
  final _searchController = TextEditingController();
  String _query = '';
  String? _tagFilter;

  @override
  void initState() {
    super.initState();
    _repository = SalesRepository(context.read<ApiClient>());
    _future = _repository.fetchRecentSales();
    final tag = widget.initialFilter?.trim();
    if (tag != null && tag.isNotEmpty) {
      _tagFilter = tag;
      _query = tag.toLowerCase();
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _reload() {
    setState(() => _future = _repository.fetchRecentSales());
  }

  @override
  Widget build(BuildContext context) {
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    return Scaffold(
      appBar: AppBar(title: const Text('Sales')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              onChanged: (value) => setState(() {
                _query = value.trim().toLowerCase();
                _tagFilter = null;
              }),
              decoration: InputDecoration(
                hintText: 'Search sale #, customer or item',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _query.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchController.clear();
                          setState(() {
                            _query = '';
                            _tagFilter = null;
                          });
                        },
                      ),
              ),
            ),
          ),
          if (_tagFilter != null)
            Align(
              alignment: Alignment.centerLeft,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 4),
                child: InputChip(
                  avatar: const Icon(Icons.local_offer_outlined, size: 16),
                  label: Text('Filtered by #$_tagFilter'),
                  onDeleted: () => setState(() {
                    _tagFilter = null;
                    _query = '';
                  }),
                ),
              ),
            ),
          Expanded(
            child: FutureBuilder<List<SaleModel>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const LoadingIndicator();
                }
                if (snapshot.hasError) {
                  final message = snapshot.error is ApiException
                      ? (snapshot.error as ApiException).message
                      : 'Could not load sales.';
                  return ErrorView(message: message, onRetry: _reload);
                }

                final sales = (snapshot.data ?? []).where((s) {
                  if (_query.isEmpty) return true;
                  if (s.saleNumber.toLowerCase().contains(_query) ||
                      (s.customerName ?? '').toLowerCase().contains(_query)) {
                    return true;
                  }
                  return s.items.any((it) {
                    final name = (it['name'] ?? it['product_name'] ?? '')
                        .toString()
                        .toLowerCase();
                    final cat = (it['category_name'] ?? it['category'] ?? '')
                        .toString()
                        .toLowerCase()
                        .replaceAll(RegExp(r'\s+'), '');
                    return name.contains(_query) ||
                        cat.contains(_query.replaceAll(RegExp(r'\s+'), ''));
                  });
                }).toList();

                if (sales.isEmpty) {
                  return const Center(child: Text('No sales found.'));
                }

                return RefreshIndicator(
                  onRefresh: () async => _reload(),
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                    itemCount: sales.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 8),
                    itemBuilder: (context, index) {
                      final sale = sales[index];
                      return _SaleTile(
                        sale: sale,
                        formatter: formatter,
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                              builder: (_) => SaleDetailScreen(
                                  sale: sale, formatter: formatter)),
                        ),
                      );
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _SaleTile extends StatelessWidget {
  const _SaleTile(
      {required this.sale, required this.formatter, required this.onTap});

  final SaleModel sale;
  final CurrencyFormatter formatter;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final due = sale.dueAmount > 0;

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Sale #${sale.saleNumber}',
                        style: const TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 2),
                    Text(
                      [
                        if (sale.createdAt != null)
                          _dateFormat.format(TenantTimeService.instance
                              .toTenantTime(sale.createdAt!)),
                        if ((sale.customerName ?? '').isNotEmpty)
                          sale.customerName!,
                      ].join(' · '),
                      style:
                          TextStyle(color: Colors.grey.shade600, fontSize: 12),
                    ),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(formatter.format(sale.total),
                      style: const TextStyle(fontWeight: FontWeight.w600)),
                  Text(
                    sale.isCancelled
                        ? 'Cancelled'
                        : (due
                            ? '${formatter.format(sale.dueAmount)} due'
                            : 'Paid'),
                    style: TextStyle(
                      fontSize: 12,
                      color: sale.isCancelled
                          ? Colors.grey.shade500
                          : (due ? Colors.red.shade400 : Colors.green.shade600),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
