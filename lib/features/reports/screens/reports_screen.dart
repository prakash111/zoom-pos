import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/models/report_models.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/utils/responsive.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../reports_provider.dart';
import '../reports_repository.dart';

final _dateFormat = DateFormat('MMM d, y');

const _tabs = ['Summary', 'P&L', 'Payments', 'Till', 'Commissions', 'Aging'];
const _exportKeys = ['sales_summary', null, 'payment_methods', null, 'commissions', 'aging'];

/// Reports & Analytics: Sales Summary / DRE (P&L) / Payment Methods / Till
/// Closings / Commissions / Aging, mirroring the web Reports page's tabs.
/// Owns a [ReportsProvider] scoped to this route.
class ReportsScreen extends StatelessWidget {
  const ReportsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final apiClient = context.read<ApiClient>();

    return ChangeNotifierProvider(
      create: (_) => ReportsProvider(repository: ReportsRepository(apiClient))..loadSummary(),
      child: const _ReportsScreenBody(),
    );
  }
}

class _ReportsScreenBody extends StatefulWidget {
  const _ReportsScreenBody();

  @override
  State<_ReportsScreenBody> createState() => _ReportsScreenBodyState();
}

class _ReportsScreenBodyState extends State<_ReportsScreenBody> with SingleTickerProviderStateMixin {
  late final TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _tabs.length, vsync: this);
    _tabController.addListener(_onTabChanged);
  }

  @override
  void dispose() {
    _tabController.removeListener(_onTabChanged);
    _tabController.dispose();
    super.dispose();
  }

  void _onTabChanged() {
    if (_tabController.indexIsChanging) return;
    setState(() {});
    final reports = context.read<ReportsProvider>();
    switch (_tabController.index) {
      case 1:
        reports.loadProfitLoss();
        break;
      case 2:
        reports.loadPaymentMethods();
        break;
      case 3:
        reports.loadTillClosings();
        break;
      case 4:
        reports.loadCommissions();
        break;
      case 5:
        reports.loadAging();
        break;
    }
  }

  Future<void> _pickDateRange(BuildContext context) async {
    final reports = context.read<ReportsProvider>();
    final picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2020),
      lastDate: DateTime(2100),
      initialDateRange: reports.startDate != null && reports.endDate != null
          ? DateTimeRange(start: reports.startDate!, end: reports.endDate!)
          : null,
    );
    if (picked == null) return;
    reports.setDateRange(picked.start, picked.end);
  }

  Future<void> _exportCsv(BuildContext context) async {
    final reports = context.read<ReportsProvider>();
    final key = _exportKeys[_tabController.index];
    if (key == null) return;

    try {
      final csv = await reports.exportCsv(key);
      await Clipboard.setData(ClipboardData(text: csv));
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Report CSV copied to clipboard.')));
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Export failed: $e')));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final reports = context.watch<ReportsProvider>();
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');
    final canExport = _exportKeys[_tabController.index] != null;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Reports'),
        bottom: TabBar(controller: _tabController, isScrollable: true, tabs: [for (final t in _tabs) Tab(text: t)]),
        actions: [
          IconButton(
            tooltip: 'Date range',
            icon: const Icon(Icons.date_range_outlined),
            onPressed: () => _pickDateRange(context),
          ),
          IconButton(
            tooltip: 'Copy CSV',
            icon: const Icon(Icons.ios_share),
            onPressed: canExport ? () => _exportCsv(context) : null,
          ),
        ],
      ),
      body: Column(
        children: [
          if (reports.startDate != null && reports.endDate != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Text(
                '${_dateFormat.format(reports.startDate!)} – ${_dateFormat.format(reports.endDate!)}',
                style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
              ),
            ),
          Expanded(
            child: TabBarView(
              controller: _tabController,
              children: [
                _SummaryTab(reports: reports, formatter: formatter),
                _ProfitLossTab(reports: reports, formatter: formatter),
                _PaymentMethodsTab(reports: reports, formatter: formatter),
                _TillClosingsTab(reports: reports, formatter: formatter),
                _CommissionsTab(reports: reports, formatter: formatter),
                _AgingTab(reports: reports, formatter: formatter),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SummaryTab extends StatelessWidget {
  const _SummaryTab({required this.reports, required this.formatter});

  final ReportsProvider reports;
  final CurrencyFormatter formatter;

  @override
  Widget build(BuildContext context) {
    if (reports.status == ReportsStatus.loading) return const LoadingIndicator();
    if (reports.status == ReportsStatus.error) {
      return ErrorView(message: reports.error ?? 'Could not load report.', onRetry: reports.loadSummary);
    }

    final kpis = reports.kpis;
    final summary = reports.summary;
    if (kpis == null || summary == null) return const SizedBox.shrink();

    return RefreshIndicator(
      onRefresh: reports.loadSummary,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          LayoutBuilder(
            builder: (context, constraints) => GridView.count(
              crossAxisCount: gridColumnsFor(constraints.maxWidth, mobile: 2, tablet: 3, desktop: 4),
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: 1.6,
              children: [
                _KpiTile(label: 'Revenue', value: formatter.format(kpis.totalRevenue), growth: kpis.revenueGrowth),
                _KpiTile(label: 'Transactions', value: '${kpis.transactionsCount}', growth: kpis.transactionsGrowth),
                _KpiTile(label: 'Avg. order value', value: formatter.format(kpis.aov), growth: kpis.aovGrowth),
                _KpiTile(label: 'Items sold', value: summary.itemsSoldCount.toStringAsFixed(0), growth: null),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Sales summary', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  _Row('Gross sales', formatter.format(summary.grossSales)),
                  _Row('Discounts', formatter.format(summary.totalDiscount)),
                  _Row('Tax collected', formatter.format(summary.totalTax)),
                  _Row('Paid', formatter.format(summary.totalPaid)),
                  _Row('Outstanding', formatter.format(summary.totalDue)),
                  _Row('Orders', '${summary.ordersCount}'),
                  _Row('Cancelled', '${summary.cancelledCount} (${formatter.format(summary.cancelledValue)})'),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Top products', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  if (reports.topProducts.isEmpty) const Text('No sales in this period.'),
                  for (final p in reports.topProducts) _Row('${p.name} × ${p.qty.toStringAsFixed(0)}', formatter.format(p.revenue)),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ProfitLossTab extends StatelessWidget {
  const _ProfitLossTab({required this.reports, required this.formatter});

  final ReportsProvider reports;
  final CurrencyFormatter formatter;

  @override
  Widget build(BuildContext context) {
    final dre = reports.dre;
    if (dre == null) return const LoadingIndicator();

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              children: [
                _Row('Gross revenue', formatter.format(dre.grossRevenue)),
                _Row('Discounts', '-${formatter.format(dre.discounts)}'),
                _Row('Taxes', '-${formatter.format(dre.taxes)}'),
                const Divider(),
                _Row('Net revenue', formatter.format(dre.netRevenue), bold: true),
                _Row('COGS', '-${formatter.format(dre.cogs)}'),
                _Row('Gross profit (${dre.grossMargin.toStringAsFixed(1)}%)', formatter.format(dre.grossProfit), bold: true),
                const Divider(),
                _Row('Card fees', '-${formatter.format(dre.cardFees)}'),
                _Row('Commissions', '-${formatter.format(dre.commissions)}'),
                _Row('Cash expenses', '-${formatter.format(dre.cashExpenses)}'),
                _Row('Operating expenses', '-${formatter.format(dre.operatingExpenses)}'),
                const Divider(),
                _Row('EBITDA (${dre.netMargin.toStringAsFixed(1)}%)', formatter.format(dre.ebitda), bold: true),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _PaymentMethodsTab extends StatelessWidget {
  const _PaymentMethodsTab({required this.reports, required this.formatter});

  final ReportsProvider reports;
  final CurrencyFormatter formatter;

  @override
  Widget build(BuildContext context) {
    if (reports.paymentMethods.isEmpty) {
      return const Center(child: Text('No payments in this period.'));
    }

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: reports.paymentMethods.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        final pm = reports.paymentMethods[index];
        return Card(
          child: ListTile(
            title: Text(pm.method[0].toUpperCase() + pm.method.substring(1)),
            subtitle: Text('${pm.count} transactions · ${pm.share.toStringAsFixed(1)}%'),
            trailing: Text(formatter.format(pm.amount), style: const TextStyle(fontWeight: FontWeight.w600)),
          ),
        );
      },
    );
  }
}

class _TillClosingsTab extends StatelessWidget {
  const _TillClosingsTab({required this.reports, required this.formatter});

  final ReportsProvider reports;
  final CurrencyFormatter formatter;

  @override
  Widget build(BuildContext context) {
    if (reports.tillClosings.isEmpty) {
      return const Center(child: Text('No till closings in this period.'));
    }

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: reports.tillClosings.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        final t = reports.tillClosings[index];
        final diff = t.cashDifference;
        return Card(
          child: ListTile(
            title: Text(t.terminalId),
            subtitle: Text(t.closedAt != null ? _dateFormat.format(t.closedAt!) : ''),
            trailing: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(formatter.format(t.countedClosingBalance)),
                Text(
                  diff == 0 ? 'Matched' : formatter.format(diff),
                  style: TextStyle(fontSize: 12, color: diff == 0 ? Colors.green : Colors.orange.shade800),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _CommissionsTab extends StatelessWidget {
  const _CommissionsTab({required this.reports, required this.formatter});

  final ReportsProvider reports;
  final CurrencyFormatter formatter;

  @override
  Widget build(BuildContext context) {
    if (reports.commissions.isEmpty) {
      return const Center(child: Text('No sales in this period.'));
    }

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: reports.commissions.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        final c = reports.commissions[index];
        return Card(
          child: ListTile(
            title: Text(c.name),
            subtitle: Text('${c.role} · ${c.salesCount} sales · ${c.formattedRate}'),
            trailing: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(formatter.format(c.totalRevenue)),
                Text('Comm. ${formatter.format(c.totalCommission)}', style: const TextStyle(fontSize: 12)),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _AgingTab extends StatelessWidget {
  const _AgingTab({required this.reports, required this.formatter});

  final ReportsProvider reports;
  final CurrencyFormatter formatter;

  @override
  Widget build(BuildContext context) {
    final aging = reports.aging;
    if (aging == null) return const LoadingIndicator();

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text('Total receivables: ${formatter.format(aging.totalReceivables)}',
            style: const TextStyle(fontWeight: FontWeight.bold)),
        const SizedBox(height: 12),
        for (final b in aging.brackets)
          Card(
            child: ListTile(
              title: Text(b.label),
              trailing: Text('${b.count} · ${formatter.format(b.amount)}'),
            ),
          ),
        const SizedBox(height: 16),
        Text('By customer', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        if (aging.customers.isEmpty) const Text('No outstanding balances.'),
        for (final c in aging.customers)
          Card(
            child: ListTile(
              title: Text(c.name),
              subtitle: Text('${c.invoicesCount} invoices · ${c.daysOverdue} days overdue'),
              trailing: Text(formatter.format(c.totalDue), style: const TextStyle(fontWeight: FontWeight.w600)),
            ),
          ),
      ],
    );
  }
}

class _KpiTile extends StatelessWidget {
  const _KpiTile({required this.label, required this.value, required this.growth});

  final String label;
  final String value;
  final GrowthStat? growth;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(label, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
            const SizedBox(height: 4),
            Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            if (growth != null)
              Text(
                growth!.label,
                style: TextStyle(fontSize: 11, color: growth!.isPositive ? Colors.green : Colors.red),
              ),
          ],
        ),
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row(this.label, this.value, {this.bold = false});

  final String label;
  final String value;
  final bool bold;

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(fontWeight: bold ? FontWeight.bold : FontWeight.normal);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [Text(label, style: style), Text(value, style: style)],
      ),
    );
  }
}
