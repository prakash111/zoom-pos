import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:fl_chart/fl_chart.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/analytics_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../analytics_repository.dart';

/// Business analytics dashboard — GET /analytics: today/month/all-time
/// KPIs, a 7-day revenue trend, payment-method breakdown, and top products.
class AnalyticsScreen extends StatefulWidget {
  const AnalyticsScreen({super.key});

  @override
  State<AnalyticsScreen> createState() => _AnalyticsScreenState();
}

class _AnalyticsScreenState extends State<AnalyticsScreen> {
  late final AnalyticsRepository _repository;
  late Future<AnalyticsModel> _future;

  @override
  void initState() {
    super.initState();
    _repository = AnalyticsRepository(context.read<ApiClient>());
    _future = _repository.fetchAnalytics();
  }

  void _reload() {
    setState(() => _future = _repository.fetchAnalytics());
  }

  @override
  Widget build(BuildContext context) {
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    return Scaffold(
      appBar: AppBar(title: const Text('Analytics')),
      body: FutureBuilder<AnalyticsModel>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const LoadingIndicator();
          }
          if (snapshot.hasError) {
            final message =
                snapshot.error is ApiException ? (snapshot.error as ApiException).message : 'Could not load analytics.';
            return ErrorView(message: message, onRetry: _reload);
          }

          final analytics = snapshot.data!;
          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  childAspectRatio: 1.6,
                  children: [
                    _KpiCard(label: "Today's revenue", value: formatter.format(analytics.todayRevenue), sub: '${analytics.todayOrders} orders'),
                    _KpiCard(label: "This month", value: formatter.format(analytics.monthRevenue), sub: '${analytics.monthOrders} orders'),
                    _KpiCard(label: 'Average order', value: formatter.format(analytics.averageOrderValue)),
                    _KpiCard(
                      label: 'Receivables',
                      value: formatter.format(analytics.totalReceivables),
                      sub: analytics.lowStockCount > 0 ? '${analytics.lowStockCount} low stock' : null,
                      valueColor: analytics.totalReceivables > 0 ? Colors.red.shade400 : null,
                    ),
                  ],
                ),
                const SizedBox(height: 24),
                Text('7-day revenue trend', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 12),
                if (analytics.revenueTrend.isEmpty)
                  const SizedBox.shrink()
                else
                  SizedBox(height: 180, child: _RevenueTrendChart(points: analytics.revenueTrend)),
                const SizedBox(height: 24),
                Text('Payment methods', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                if (analytics.paymentBreakdown.isEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: Text('No sales yet.', style: TextStyle(color: Colors.grey.shade600)),
                  )
                else
                  Card(
                    child: Column(
                      children: [
                        for (final entry in analytics.paymentBreakdown)
                          ListTile(
                            title: Text(entry.method),
                            subtitle: Text('${entry.count} sale${entry.count == 1 ? '' : 's'}'),
                            trailing: Text(formatter.format(entry.total), style: const TextStyle(fontWeight: FontWeight.w600)),
                          ),
                      ],
                    ),
                  ),
                const SizedBox(height: 24),
                Text('Top products', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                if (analytics.topProducts.isEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: Text('No sales yet.', style: TextStyle(color: Colors.grey.shade600)),
                  )
                else
                  Card(
                    child: Column(
                      children: [
                        for (final product in analytics.topProducts)
                          ListTile(
                            title: Text(product.name),
                            subtitle: Text('${product.unitsSold.toStringAsFixed(0)} sold'),
                            trailing: Text(formatter.format(product.revenue), style: const TextStyle(fontWeight: FontWeight.w600)),
                          ),
                      ],
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _KpiCard extends StatelessWidget {
  const _KpiCard({required this.label, required this.value, this.sub, this.valueColor});

  final String label;
  final String value;
  final String? sub;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(label, style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
            const SizedBox(height: 4),
            Text(
              value,
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: valueColor),
              overflow: TextOverflow.ellipsis,
            ),
            if (sub != null) ...[
              const SizedBox(height: 2),
              Text(sub!, style: TextStyle(color: Colors.grey.shade500, fontSize: 11)),
            ],
          ],
        ),
      ),
    );
  }
}

class _RevenueTrendChart extends StatelessWidget {
  const _RevenueTrendChart({required this.points});

  final List<RevenueTrendPoint> points;

  @override
  Widget build(BuildContext context) {
    final maxRevenue = points.map((p) => p.revenue).fold<double>(0, (a, b) => a > b ? a : b);
    final primary = Theme.of(context).colorScheme.primary;

    return LineChart(
      LineChartData(
        minY: 0,
        maxY: maxRevenue <= 0 ? 1 : maxRevenue * 1.2,
        gridData: const FlGridData(show: false),
        borderData: FlBorderData(show: false),
        titlesData: FlTitlesData(
          topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 24,
              getTitlesWidget: (value, meta) {
                final index = value.round();
                if (index < 0 || index >= points.length) return const SizedBox.shrink();
                return Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(points[index].day, style: TextStyle(color: Colors.grey.shade600, fontSize: 11)),
                );
              },
            ),
          ),
        ),
        lineTouchData: LineTouchData(
          touchTooltipData: LineTouchTooltipData(
            getTooltipItems: (spots) => spots
                .map((spot) => LineTooltipItem(points[spot.x.round()].revenue.toStringAsFixed(2), const TextStyle(color: Colors.white)))
                .toList(),
          ),
        ),
        lineBarsData: [
          LineChartBarData(
            spots: [for (var i = 0; i < points.length; i++) FlSpot(i.toDouble(), points[i].revenue)],
            isCurved: true,
            color: primary,
            barWidth: 3,
            dotData: const FlDotData(show: false),
            belowBarData: BarAreaData(show: true, color: primary.withOpacity(0.15)),
          ),
        ],
      ),
    );
  }
}
