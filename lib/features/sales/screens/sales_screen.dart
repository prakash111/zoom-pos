import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/sale_model.dart';
import '../../../core/services/tenant_time_service.dart';
import '../../../core/stores/store_provider.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/utils/responsive.dart';
import '../../../core/widgets/date_range_picker.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../../core/widgets/responsive/desktop_content_area.dart';
import '../../auth/auth_provider.dart';
import '../../pos/sales_repository.dart';
import 'sale_detail_screen.dart';

final _dateFormat = DateFormat('MMM d, y · h:mm a');

/// Sales history: the most recent completed sales pulled back from the
/// server (see [SalesRepository.fetchRecentSales]), searchable by sale
/// number or customer name. Read-only — new sales are recorded from the
/// Point of Sale module.
class SalesScreen extends StatefulWidget {
  const SalesScreen({
    super.key,
    this.initialFilter,
    this.initialDueFilter,
  });

  /// A pre-applied text filter (e.g. a "Popular Tag" tapped on the dashboard).
  /// Shown as a dismissible chip and matched against the sale #, customer and
  /// line-item names.
  final String? initialFilter;

  /// An initial due date filter (e.g. 'overdue', 'due_today') passed from navigation.
  final String? initialDueFilter;

  @override
  State<SalesScreen> createState() => _SalesScreenState();
}

class _SalesScreenState extends State<SalesScreen> {
  late final SalesRepository _repository;
  Future<List<SaleModel>>? _future;
  final _searchController = TextEditingController();
  String _query = '';
  String? _tagFilter;
  String _selectedFilter = 'all';
  DateTimeRange? _customDateRange;
  bool _initializedArgs = false;

  @override
  void initState() {
    super.initState();
    _repository = SalesRepository(context.read<ApiClient>());
    final tag = widget.initialFilter?.trim();
    if (tag != null && tag.isNotEmpty) {
      _tagFilter = tag;
      _query = tag.toLowerCase();
      _searchController.text = tag;
    }
    if (widget.initialDueFilter != null && widget.initialDueFilter!.isNotEmpty) {
      _selectedFilter = widget.initialDueFilter!;
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_initializedArgs) {
      _initializedArgs = true;
      final args = ModalRoute.of(context)?.settings.arguments;
      if (args is Map) {
        final argFilter = (args['initial_due_filter'] ??
                args['due_filter'] ??
                args['filter'])
            ?.toString();
        if (argFilter != null && argFilter.isNotEmpty) {
          _selectedFilter = argFilter;
        }
      }
      _reload();
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _reload() {
    final storeProvider = context.read<StoreProvider>();
    setState(() {
      _future = _repository.fetchSales(
        query: _query,
        filter: _selectedFilter,
        startDate: _customDateRange?.start,
        endDate: _customDateRange?.end,
        storeId: storeProvider.currentStoreId,
      );
    });
  }

  Future<void> _pickCustomDateRange() async {
    final picked = await showPosDateRangePicker(
      context: context,
      initialDateRange: _customDateRange,
    );

    if (picked != null) {
      setState(() {
        _customDateRange = picked;
        _selectedFilter = 'custom_date';
      });
      _reload();
    }
  }

  Widget _buildFilterBar() {
    final theme = Theme.of(context);
    final chips = [
      {'key': 'all', 'label': 'All', 'icon': null},
      {'key': 'overdue', 'label': 'Overdue', 'icon': Icons.error_outline},
      {'key': 'due_today', 'label': 'Due Today', 'icon': Icons.access_time},
      {'key': 'due_7_days', 'label': 'Due in 7 Days', 'icon': Icons.date_range_outlined},
      {'key': 'due_15_days', 'label': 'Due in 15 Days', 'icon': Icons.calendar_today_outlined},
    ];

    String dateLabel = 'Date Filter';
    if (_customDateRange != null) {
      final s = DateFormat('MMM d').format(_customDateRange!.start);
      final e = DateFormat('MMM d').format(_customDateRange!.end);
      dateLabel = '$s - $e';
    }

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: Row(
        children: [
          ...chips.map((chip) {
            final key = chip['key'] as String;
            final label = chip['label'] as String;
            final icon = chip['icon'] as IconData?;
            final isSelected = _selectedFilter == key;

            return Padding(
              padding: const EdgeInsets.only(right: 8),
              child: FilterChip(
                showCheckmark: false,
                avatar: icon != null
                    ? Icon(
                        icon,
                        size: 16,
                        color: isSelected
                            ? Colors.white
                            : (key == 'overdue'
                                ? const Color(0xFFEF4444)
                                : key == 'due_today'
                                    ? const Color(0xFFF59E0B)
                                    : theme.colorScheme.primary),
                      )
                    : null,
                label: Text(label),
                labelStyle: TextStyle(
                  fontSize: 12.5,
                  fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                  color: isSelected ? Colors.white : theme.colorScheme.onSurface,
                ),
                selected: isSelected,
                selectedColor: key == 'overdue'
                    ? const Color(0xFFEF4444)
                    : key == 'due_today'
                        ? const Color(0xFFF59E0B)
                        : theme.colorScheme.primary,
                backgroundColor: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(20),
                  side: BorderSide(
                    color: isSelected
                        ? Colors.transparent
                        : theme.colorScheme.outlineVariant.withValues(alpha: 0.6),
                  ),
                ),
                onSelected: (selected) {
                  if (selected) {
                    setState(() {
                      _selectedFilter = key;
                      if (key != 'custom_date') {
                        _customDateRange = null;
                      }
                    });
                    _reload();
                  }
                },
              ),
            );
          }),
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: FilterChip(
              showCheckmark: false,
              avatar: Icon(
                Icons.calendar_month_outlined,
                size: 16,
                color: _selectedFilter == 'custom_date'
                    ? Colors.white
                    : theme.colorScheme.primary,
              ),
              label: Text(dateLabel),
              labelStyle: TextStyle(
                fontSize: 12.5,
                fontWeight: _selectedFilter == 'custom_date'
                    ? FontWeight.w700
                    : FontWeight.w500,
                color: _selectedFilter == 'custom_date'
                    ? Colors.white
                    : theme.colorScheme.onSurface,
              ),
              selected: _selectedFilter == 'custom_date',
              selectedColor: theme.colorScheme.primary,
              backgroundColor: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(20),
                side: BorderSide(
                  color: _selectedFilter == 'custom_date'
                      ? Colors.transparent
                      : theme.colorScheme.outlineVariant.withValues(alpha: 0.6),
                ),
              ),
              onSelected: (_) => _pickCustomDateRange(),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');
    final desktop = MediaQuery.sizeOf(context).width >= Breakpoints.desktop;

    return Scaffold(
      appBar: AppBar(title: const Text('Sales')),
      body: DesktopContentArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
              child: DesktopBoundedField(
                child: TextField(
                  controller: _searchController,
                  onChanged: (value) => setState(() {
                    _query = value.trim().toLowerCase();
                    _tagFilter = null;
                  }),
                  onSubmitted: (_) => _reload(),
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
                              _reload();
                            },
                          ),
                  ),
                ),
              ),
            ),
            _buildFilterBar(),
            if (_tagFilter != null)
              Align(
                alignment: Alignment.centerLeft,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 4),
                  child: InputChip(
                    avatar: const Icon(Icons.local_offer_outlined, size: 16),
                    label: Text('Filtered by #$_tagFilter'),
                    onDeleted: () => setState(() {
                      _tagFilter = null;
                      _query = '';
                      _reload();
                    }),
                  ),
                ),
              ),
            Expanded(
              child: _future == null
                  ? const LoadingIndicator()
                  : FutureBuilder<List<SaleModel>>(
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
                    if (_selectedFilter == 'overdue') {
                      final now = DateTime.now();
                      final today = DateTime(now.year, now.month, now.day);
                      if (s.dueAmount <= 0) return false;
                      if (s.paymentStatus.toLowerCase() == 'paid') return false;
                      if (s.dueDate == null) return false;
                      final d = DateTime(s.dueDate!.year, s.dueDate!.month, s.dueDate!.day);
                      if (!d.isBefore(today)) return false;
                    }
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

                  void openSale(SaleModel sale) => Navigator.of(context).push(
                        MaterialPageRoute(
                            builder: (_) => SaleDetailScreen(
                                sale: sale, formatter: formatter)),
                      );

                  return RefreshIndicator(
                    onRefresh: () async => _reload(),
                    child: desktop
                        ? _DesktopSalesTable(
                            sales: sales, formatter: formatter, onTap: openSale)
                        : ListView.separated(
                            padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                            itemCount: sales.length,
                            separatorBuilder: (_, __) =>
                                const SizedBox(height: 8),
                            itemBuilder: (context, index) {
                              final sale = sales[index];
                              return _SaleTile(
                                sale: sale,
                                formatter: formatter,
                                onTap: () => openSale(sale),
                              );
                            },
                          ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Desktop composition for the sales list: a compact header + data rows
/// (Sale # | Customer | Date | Status | Amount) instead of stretching the
/// mobile card list edge-to-edge. Falls back to [_SaleTile] cards below the
/// desktop breakpoint.
class _DesktopSalesTable extends StatelessWidget {
  const _DesktopSalesTable(
      {required this.sales, required this.formatter, required this.onTap});

  final List<SaleModel> sales;
  final CurrencyFormatter formatter;
  final void Function(SaleModel) onTap;

  static const _headerStyle =
      TextStyle(fontWeight: FontWeight.w600, fontSize: 12.5);

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(0, 4, 0, 24),
      itemCount: sales.length + 1,
      itemBuilder: (context, index) {
        if (index == 0) {
          return Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
                border:
                    Border(bottom: BorderSide(color: scheme.outlineVariant))),
            child: Row(
              children: [
                Expanded(
                    flex: 3,
                    child: Text('SALE #',
                        style: _headerStyle.copyWith(
                            color: scheme.onSurfaceVariant))),
                Expanded(
                    flex: 4,
                    child: Text('CUSTOMER',
                        style: _headerStyle.copyWith(
                            color: scheme.onSurfaceVariant))),
                Expanded(
                    flex: 3,
                    child: Text('DATE',
                        style: _headerStyle.copyWith(
                            color: scheme.onSurfaceVariant))),
                Expanded(
                    flex: 2,
                    child: Text('STATUS',
                        style: _headerStyle.copyWith(
                            color: scheme.onSurfaceVariant))),
                Expanded(
                    flex: 2,
                    child: Text('AMOUNT',
                        textAlign: TextAlign.end,
                        style: _headerStyle.copyWith(
                            color: scheme.onSurfaceVariant))),
                const SizedBox(width: 36),
              ],
            ),
          );
        }

        final sale = sales[index - 1];
        final due = sale.dueAmount > 0;

        return InkWell(
          onTap: () => onTap(sale),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
              border: Border(
                  bottom: BorderSide(
                      color: scheme.outlineVariant.withValues(alpha: 0.5))),
            ),
            child: Row(
              children: [
                Expanded(
                  flex: 3,
                  child: Text('#${sale.saleNumber}',
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                          fontWeight: FontWeight.w600, fontSize: 13.5)),
                ),
                Expanded(
                  flex: 4,
                  child: Text(
                    (sale.customerName ?? '').isEmpty
                        ? '—'
                        : sale.customerName!,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 13.5),
                  ),
                ),
                Expanded(
                  flex: 3,
                  child: Text(
                    sale.createdAt != null
                        ? _dateFormat.format(TenantTimeService.instance
                            .toTenantTime(sale.createdAt!))
                        : '—',
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                        fontSize: 12.5, color: scheme.onSurfaceVariant),
                  ),
                ),
                Expanded(
                  flex: 2,
                  child:
                      _SaleStatusBadge(cancelled: sale.isCancelled, due: due),
                ),
                Expanded(
                  flex: 2,
                  child: Text(
                    formatter.format(sale.total),
                    textAlign: TextAlign.end,
                    style: const TextStyle(
                        fontWeight: FontWeight.w600, fontSize: 13.5),
                  ),
                ),
                SizedBox(
                    width: 36,
                    child: Icon(Icons.chevron_right,
                        size: 18, color: scheme.onSurfaceVariant)),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _SaleStatusBadge extends StatelessWidget {
  const _SaleStatusBadge({required this.cancelled, required this.due});

  final bool cancelled;
  final bool due;

  @override
  Widget build(BuildContext context) {
    final (label, color) = cancelled
        ? ('Cancelled', Colors.grey)
        : due
            ? ('Due', Colors.red)
            : ('Paid', Colors.green);

    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(
          label,
          style: TextStyle(
              fontSize: 11.5,
              fontWeight: FontWeight.w600,
              color: color.shade700),
        ),
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
