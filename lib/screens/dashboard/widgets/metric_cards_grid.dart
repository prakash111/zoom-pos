import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import '../../../core/models/dashboard_summary_model.dart';

class MetricCardItem {
  const MetricCardItem({
    required this.title,
    required this.value,
    required this.trend,
    required this.isPositiveTrend,
    required this.accentColor,
    required this.icon,
    required this.sparklineData,
    required this.onTap,
  });

  final String title;
  final String value;
  final String trend;
  final bool isPositiveTrend;
  final Color accentColor;
  final IconData icon;
  final List<double> sparklineData;
  final VoidCallback onTap;
}

/// Interactive 4-metric grid with ripple feedback and navigation routes:
/// 1. Total Sales -> /sales
/// 2. Total Orders -> /orders
/// 3. Total Customers -> /customers
/// 4. Low Stock Items -> /inventory (with filter: low_stock)
class MetricCardsGrid extends StatelessWidget {
  const MetricCardsGrid({
    super.key,
    required this.metrics,
  });

  final MetricsSummaryData metrics;

  @override
  Widget build(BuildContext context) {
    final items = [
      MetricCardItem(
        title: 'Total Sales',
        value: metrics.totalSales.formatted,
        trend: metrics.totalSales.trend,
        isPositiveTrend: metrics.totalSales.isPositive,
        accentColor: const Color(0xFF10B981),
        icon: Icons.payments_outlined,
        sparklineData: metrics.totalSales.sparkline,
        onTap: () => Navigator.pushNamed(context, '/sales'),
      ),
      MetricCardItem(
        title: 'Total Orders',
        value: metrics.totalOrders.formatted,
        trend: metrics.totalOrders.trend,
        isPositiveTrend: metrics.totalOrders.isPositive,
        accentColor: const Color(0xFF0284C7),
        icon: Icons.shopping_bag_outlined,
        sparklineData: metrics.totalOrders.sparkline,
        onTap: () => Navigator.pushNamed(context, '/orders'),
      ),
      MetricCardItem(
        title: 'Total Customers',
        value: metrics.totalCustomers.formatted,
        trend: metrics.totalCustomers.trend,
        isPositiveTrend: metrics.totalCustomers.isPositive,
        accentColor: const Color(0xFF8B5CF6),
        icon: Icons.people_outline,
        sparklineData: metrics.totalCustomers.sparkline,
        onTap: () => Navigator.pushNamed(context, '/customers'),
      ),
      MetricCardItem(
        title: 'Low Stock Items',
        value: metrics.lowStockItems.formatted,
        trend: metrics.lowStockItems.trend,
        isPositiveTrend: metrics.lowStockItems.isPositive,
        accentColor: const Color(0xFFF59E0B),
        icon: Icons.inventory_2_outlined,
        sparklineData: metrics.lowStockItems.sparkline,
        onTap: () => Navigator.pushNamed(
          context,
          '/inventory',
          arguments: {'filter': 'low_stock'},
        ),
      ),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        final isWide = constraints.maxWidth >= 768;
        if (isWide) {
          return Row(
            children: items
                .map((item) => Expanded(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 6),
                        child: _buildMetricCard(
                          context: context,
                          title: item.title,
                          value: item.value,
                          trend: item.trend,
                          isPositiveTrend: item.isPositiveTrend,
                          accentColor: item.accentColor,
                          icon: item.icon,
                          sparklineData: item.sparklineData,
                          onTap: item.onTap,
                        ),
                      ),
                    ))
                .toList(),
          );
        }

        return GridView.count(
          crossAxisCount: constraints.maxWidth < 320 ? 1 : 2,
          crossAxisSpacing: 10,
          mainAxisSpacing: 10,
          mainAxisExtent: 170,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          children: items
              .map((item) => _buildMetricCard(
                    context: context,
                    title: item.title,
                    value: item.value,
                    trend: item.trend,
                    isPositiveTrend: item.isPositiveTrend,
                    accentColor: item.accentColor,
                    icon: item.icon,
                    sparklineData: item.sparklineData,
                    onTap: item.onTap,
                  ))
              .toList(),
        );
      },
    );
  }

  static Widget _buildMetricCard({
    required BuildContext context,
    required String title,
    required String value,
    required String trend,
    required bool isPositiveTrend,
    required Color accentColor,
    required IconData icon,
    required List<double> sparklineData,
    required VoidCallback onTap,
  }) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Material(
      color: Colors.transparent,
      child: Ink(
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF1E293B) : Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isDark
                ? Colors.white.withValues(alpha: 0.08)
                : const Color(0xFFE2E8F0),
            width: 1,
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.02),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(20),
          splashColor: accentColor.withValues(alpha: 0.12),
          highlightColor: accentColor.withValues(alpha: 0.06),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                // Top Row: Icon + Trend Badge
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(
                        color: accentColor.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Icon(icon, color: accentColor, size: 17),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: (isPositiveTrend
                                ? const Color(0xFF10B981)
                                : const Color(0xFFEF4444))
                            .withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            isPositiveTrend
                                ? Icons.arrow_upward_rounded
                                : Icons.arrow_downward_rounded,
                            color: isPositiveTrend
                                ? const Color(0xFF10B981)
                                : const Color(0xFFEF4444),
                            size: 10,
                          ),
                          const SizedBox(width: 2),
                          Text(
                            trend,
                            style: TextStyle(
                              color: isPositiveTrend
                                  ? const Color(0xFF10B981)
                                  : const Color(0xFFEF4444),
                              fontSize: 10,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),

                // Value & Metric Title
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      value,
                      style: TextStyle(
                        color: isDark ? Colors.white : const Color(0xFF0F172A),
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -0.5,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    Text(
                      title,
                      style: TextStyle(
                        color: isDark
                            ? const Color(0xFF94A3B8)
                            : const Color(0xFF64748B),
                        fontSize: 11,
                        fontWeight: FontWeight.w500,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
                const SizedBox(height: 6),

                // Mini Sparkline Curve
                if (sparklineData.isNotEmpty)
                  SizedBox(
                    height: 24,
                    width: double.infinity,
                    child: _MiniSparklineWidget(
                      data: sparklineData,
                      color: accentColor,
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _MiniSparklineWidget extends StatelessWidget {
  const _MiniSparklineWidget({required this.data, required this.color});

  final List<double> data;
  final Color color;

  @override
  Widget build(BuildContext context) {
    if (data.isEmpty) return const SizedBox.shrink();

    final spots = <FlSpot>[];
    for (var i = 0; i < data.length; i++) {
      spots.add(FlSpot(i.toDouble(), data[i]));
    }

    return LineChart(
      LineChartData(
        gridData: const FlGridData(show: false),
        titlesData: const FlTitlesData(show: false),
        borderData: FlBorderData(show: false),
        lineTouchData: const LineTouchData(enabled: false),
        lineBarsData: [
          LineChartBarData(
            spots: spots,
            isCurved: true,
            curveSmoothness: 0.35,
            color: color,
            barWidth: 2,
            isStrokeCapRound: true,
            dotData: const FlDotData(show: false),
            belowBarData: BarAreaData(
              show: true,
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  color.withValues(alpha: 0.25),
                  color.withValues(alpha: 0.0),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
