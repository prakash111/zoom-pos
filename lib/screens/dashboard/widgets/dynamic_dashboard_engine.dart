import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../../../core/config/theme.dart';
import '../../../core/models/analytics_model.dart';
import '../../../core/sdui/sdui_component_registry.dart';
import '../../../core/utils/currency_formatter.dart';
import 'sales_overview_chart.dart';

/// Dynamic Server-Driven Dashboard Widget Engine.
///
/// Iterates over a server-dictated list of section keys [enabledWidgets]
/// rather than hardcoding static card positions. Any future widget additions,
/// repositioning, or removals can be orchestrated purely from Laravel.
///
/// The legacy 'total_balance' / 'balance_card' key is permanently intercepted
/// and excluded to ensure it never renders under any circumstance.
Widget buildDashboardFromSchema(
  BuildContext context,
  List<String> enabledWidgets,
  Map<String, dynamic> dashboardData, {
  CurrencyFormatter? formatter,
  AnalyticsModel? analytics,
  VoidCallback? onAddProduct,
  VoidCallback? onOpenTransactions,
  VoidCallback? onOpenCustomers,
  VoidCallback? onFilter,
}) {
  final fmt = formatter ?? CurrencyFormatter('\$');

  return ListView.builder(
    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
    shrinkWrap: true,
    physics: const ClampingScrollPhysics(),
    itemCount: enabledWidgets.length,
    itemBuilder: (context, index) {
      final widgetKey = enabledWidgets[index];

      switch (widgetKey) {
        // PERMANENTLY EXCLUDED: 'total_balance' / 'balance_card'
        // If received or present, return SizedBox.shrink() to ensure it never renders
        case 'total_balance':
        case 'balance_card':
        case 'total_balance_card':
          return const SizedBox.shrink();

        case 'quick_actions':
          return Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: QuickActionsRow(
              onAddProduct: onAddProduct,
              onNewCustomer: onOpenCustomers,
            ),
          );

        case 'receivables_banner':
          final recData = dashboardData['receivables'] ?? dashboardData['amount_receivable_card'];
          return Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: DueReceivablesBanner(data: recData, formatter: fmt),
          );

        case 'statistics_card':
          return Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: StatisticsMetricCard(
              data: dashboardData['statistics'] ?? dashboardData['kpis'],
              analytics: analytics,
              formatter: fmt,
              onFilter: onFilter,
            ),
          );

        case 'order_statistics':
          return Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: OrderStatisticsGrid(
              data: dashboardData['order_statistics'] ?? dashboardData['kpis'],
              formatter: fmt,
            ),
          );

        case 'sales_overview_chart':
          return Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: SalesOverviewAreaChart(data: dashboardData['sales_chart']),
          );

        case 'purchase_activity':
          return Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: PurchaseActivityBarChart(
              data: dashboardData['purchase_activity'] ?? dashboardData['monthly_activity'],
            ),
          );

        case 'marketing_radar':
          return Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: MarketingRadarChart(data: dashboardData['marketing']),
          );

        default:
          return const SizedBox.shrink();
      }
    },
  );
}

/// DynamicDashboardEngine Widget wrapper for building server-orchestrated dashboards.
class DynamicDashboardEngine extends StatelessWidget {
  const DynamicDashboardEngine({
    super.key,
    required this.enabledWidgets,
    required this.dashboardData,
    this.formatter,
    this.analytics,
    this.onAddProduct,
    this.onOpenTransactions,
    this.onOpenCustomers,
    this.onFilter,
  });

  final List<String> enabledWidgets;
  final Map<String, dynamic> dashboardData;
  final CurrencyFormatter? formatter;
  final AnalyticsModel? analytics;
  final VoidCallback? onAddProduct;
  final VoidCallback? onOpenTransactions;
  final VoidCallback? onOpenCustomers;
  final VoidCallback? onFilter;

  @override
  Widget build(BuildContext context) {
    return buildDashboardFromSchema(
      context,
      enabledWidgets,
      dashboardData,
      formatter: formatter,
      analytics: analytics,
      onAddProduct: onAddProduct,
      onOpenTransactions: onOpenTransactions,
      onOpenCustomers: onOpenCustomers,
      onFilter: onFilter,
    );
  }
}

// -----------------------------------------------------------------------------
// Component Implementations
// -----------------------------------------------------------------------------

class QuickActionsRow extends StatelessWidget {
  const QuickActionsRow({
    super.key,
    this.onQuickSale,
    this.onNewCustomer,
    this.onOpenRegister,
    this.onAddProduct,
  });

  final VoidCallback? onQuickSale;
  final VoidCallback? onNewCustomer;
  final VoidCallback? onOpenRegister;
  final VoidCallback? onAddProduct;

  void _nav(BuildContext context, String key, VoidCallback? customAction) {
    if (customAction != null) {
      customAction();
      return;
    }
    try {
      Navigator.of(context).push(
        MaterialPageRoute(builder: SduiComponentRegistry.instance.resolve(key)),
      );
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final actions = [
      (Icons.point_of_sale, 'Quick Sale', () => _nav(context, 'pos', onQuickSale)),
      (Icons.person_add_alt_1_outlined, 'New Customer', () => _nav(context, 'customers', onNewCustomer)),
      (Icons.campaign_outlined, 'Open Register', () => _nav(context, 'cash_register', onOpenRegister)),
      (Icons.add_box_outlined, 'Add Product', () => _nav(context, 'inventory', onAddProduct)),
    ];

    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children: actions.map((act) {
        return InkWell(
          onTap: act.$3,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: scheme.surfaceContainerHighest,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: scheme.outlineVariant),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(act.$1, size: 16, color: scheme.primary),
                const SizedBox(width: 8),
                Text(
                  act.$2,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: scheme.onSurface,
                  ),
                ),
              ],
            ),
          ),
        );
      }).toList(),
    );
  }
}

class DueReceivablesBanner extends StatelessWidget {
  const DueReceivablesBanner({super.key, this.data, this.formatter});

  final dynamic data;
  final CurrencyFormatter? formatter;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final fmt = formatter ?? CurrencyFormatter('\$');

    double amount = 0.0;
    if (data is Map) {
      amount = (data['total_amount'] as num?)?.toDouble()
          ?? (data['amount'] as num?)?.toDouble()
          ?? 0.0;
    } else if (data is num) {
      amount = data.toDouble();
    }

    if (amount <= 0) {
      return const SizedBox.shrink();
    }

    return InkWell(
      onTap: () {
        try {
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: SduiComponentRegistry.instance.resolve('due_receivables'),
            ),
          );
        } catch (_) {}
      },
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          color: scheme.infoContainer,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: scheme.infoAccent.withValues(alpha: 0.3)),
        ),
        child: Row(
          children: [
            Icon(Icons.request_page_outlined, color: scheme.infoAccent, size: 20),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Due Payments / Receivables',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: scheme.onInfoContainer,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${fmt.format(amount)} outstanding',
                    style: TextStyle(
                      fontSize: 12,
                      color: scheme.onInfoContainer.withValues(alpha: 0.8),
                    ),
                  ),
                ],
              ),
            ),
            Icon(Icons.chevron_right, color: scheme.onInfoContainer, size: 18),
          ],
        ),
      ),
    );
  }
}

class StatisticsMetricCard extends StatelessWidget {
  const StatisticsMetricCard({
    super.key,
    this.data,
    this.analytics,
    this.formatter,
    this.onFilter,
  });

  final dynamic data;
  final AnalyticsModel? analytics;
  final CurrencyFormatter? formatter;
  final VoidCallback? onFilter;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final fmt = formatter ?? CurrencyFormatter('\$');

    final revenue = analytics?.rangeRevenue
        ?? (data is Map ? (data['range_revenue'] as num?)?.toDouble() : 0.0)
        ?? 0.0;
    final orders = analytics?.rangeOrders
        ?? (data is Map ? (data['range_orders'] as num?)?.toInt() : 0)
        ?? 0;
    final catalogue = analytics?.productCount
        ?? (data is Map ? (data['catalogue_count'] as num?)?.toInt() : 0)
        ?? 0;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: scheme.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Statistics',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                ),
              ),
              if (onFilter != null)
                InkWell(
                  onTap: onFilter,
                  borderRadius: BorderRadius.circular(8),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    decoration: BoxDecoration(
                      color: scheme.surfaceContainerHighest,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: scheme.outlineVariant),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          analytics?.rangeLabel ?? 'Filter',
                          style: TextStyle(fontSize: 12, color: scheme.onSurfaceVariant),
                        ),
                        Icon(Icons.expand_more, size: 15, color: scheme.onSurfaceVariant),
                      ],
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 18),
          LayoutBuilder(
            builder: (context, c) {
              final narrow = c.maxWidth < 450;
              final items = [
                ('Total Earnings', fmt.format(revenue)),
                ('Number of Sales', orders.toString()),
                ('Catalogue', '$catalogue items'),
              ];

              return narrow
                  ? Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: items.map((it) {
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(it.$1, style: TextStyle(color: scheme.onSurfaceVariant, fontSize: 12)),
                              const SizedBox(height: 2),
                              Text(it.$2, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                            ],
                          ),
                        );
                      }).toList(),
                    )
                  : Row(
                      children: items.map((it) {
                        return Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(it.$1, style: TextStyle(color: scheme.onSurfaceVariant, fontSize: 12)),
                              const SizedBox(height: 4),
                              Text(it.$2, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                            ],
                          ),
                        );
                      }).toList(),
                    );
            },
          ),
        ],
      ),
    );
  }
}

class OrderStatisticsGrid extends StatelessWidget {
  const OrderStatisticsGrid({super.key, this.data, this.formatter});

  final dynamic data;
  final CurrencyFormatter? formatter;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final fmt = formatter ?? CurrencyFormatter('\$');

    final totalOrders = (data is Map ? (data['total_orders'] as num?)?.toInt() : 0) ?? 0;
    final totalSales = (data is Map ? (data['total_sales'] as num?)?.toDouble() : 0.0) ?? 0.0;
    final activeOrders = (data is Map ? (data['active_orders'] as num?)?.toInt() : 0) ?? 0;
    final avgSize = (data is Map ? (data['average_order_value'] as num?)?.toDouble() : 0.0) ?? 0.0;

    final tiles = [
      ('Total orders', totalOrders.toString(), const Color(0xFF7C5CFF)),
      ('Total sales', fmt.format(totalSales), const Color(0xFF4C8DFF)),
      ('Active order', activeOrders.toString(), const Color(0xFF34D3EE)),
      ('Average order size', fmt.format(avgSize), const Color(0xFF34D399)),
    ];

    return LayoutBuilder(
      builder: (context, c) {
        final wide = c.maxWidth >= 600;
        return GridView.count(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisCount: wide ? 4 : 2,
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
          childAspectRatio: wide ? 1.6 : 1.4,
          children: tiles.map((t) {
            return Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Theme.of(context).cardColor,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: scheme.outlineVariant),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(t.$1, style: TextStyle(color: scheme.onSurfaceVariant, fontSize: 12)),
                  const SizedBox(height: 6),
                  Text(
                    t.$2,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: t.$3,
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            );
          }).toList(),
        );
      },
    );
  }
}

class SalesOverviewAreaChart extends StatelessWidget {
  const SalesOverviewAreaChart({super.key, this.data});

  final dynamic data;

  @override
  Widget build(BuildContext context) {
    return const SalesOverviewChart();
  }
}

class PurchaseActivityBarChart extends StatelessWidget {
  const PurchaseActivityBarChart({super.key, this.data});

  final dynamic data;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final rawList = (data is List ? data : []) as List;
    final points = rawList.take(9).toList();

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: scheme.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Purchase Activity',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                ),
              ),
              Row(
                children: [
                  _LegendIndicator(color: const Color(0xFF6366F1), label: 'Completed'),
                  const SizedBox(width: 10),
                  _LegendIndicator(color: const Color(0xFF38BDF8), label: 'Pending'),
                ],
              ),
            ],
          ),
          const SizedBox(height: 18),
          SizedBox(
            height: 160,
            child: points.isEmpty
                ? Center(
                    child: Text('No activity data recorded',
                        style: TextStyle(color: scheme.onSurfaceVariant, fontSize: 12)),
                  )
                : BarChart(
                    BarChartData(
                      gridData: const FlGridData(show: false),
                      borderData: FlBorderData(show: false),
                      titlesData: FlTitlesData(
                        topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                        rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                        leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                        bottomTitles: AxisTitles(
                          sideTitles: SideTitles(
                            showTitles: true,
                            getTitlesWidget: (val, meta) {
                              final idx = val.toInt();
                              if (idx < 0 || idx >= points.length) return const SizedBox.shrink();
                              final item = points[idx];
                              final label = (item is Map ? item['month'] : null)?.toString() ?? '';
                              return Padding(
                                padding: const EdgeInsets.only(top: 6),
                                child: Text(
                                  label,
                                  style: TextStyle(
                                    fontSize: 10,
                                    color: isDark ? Colors.white60 : Colors.black54,
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                      ),
                      barGroups: List.generate(points.length, (idx) {
                        final item = points[idx];
                        final c = (item is Map ? (item['completed'] as num?)?.toDouble() : 0.0) ?? 0.0;
                        final p = (item is Map ? (item['pending'] as num?)?.toDouble() : 0.0) ?? 0.0;

                        return BarChartGroupData(
                          x: idx,
                          barRods: [
                            BarChartRodData(
                              toY: c,
                              color: const Color(0xFF6366F1),
                              width: 8,
                              borderRadius: BorderRadius.circular(4),
                            ),
                            BarChartRodData(
                              toY: p,
                              color: const Color(0xFF38BDF8),
                              width: 8,
                              borderRadius: BorderRadius.circular(4),
                            ),
                          ],
                        );
                      }),
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}

class _LegendIndicator extends StatelessWidget {
  const _LegendIndicator({required this.color, required this.label});

  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 4),
        Text(label, style: const TextStyle(fontSize: 11)),
      ],
    );
  }
}

class MarketingRadarChart extends StatelessWidget {
  const MarketingRadarChart({super.key, this.data});

  final dynamic data;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: scheme.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Marketing & Reach',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 14),
          SizedBox(
            height: 140,
            child: Center(
              child: Icon(Icons.radar_rounded, size: 56, color: scheme.primary.withValues(alpha: 0.6)),
            ),
          ),
        ],
      ),
    );
  }
}
