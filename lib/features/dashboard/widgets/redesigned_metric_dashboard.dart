import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/models/dashboard_summary_model.dart';
import '../../../core/providers/dashboard_provider.dart';
import '../../../core/stores/store_provider.dart';

/// Redesigned Metric & Transaction Dashboard layout (reference: 1000592601.png).
///
/// Features:
///  * Dynamic time-based greeting banner ("Good Morning, {Name}!")
///  * Real-time date/time and live weather status badge pills
///  * Store brand bar with tagline, unread notifications badge, and avatar with role
///  * 4-Column Stat Metric Cards with bezier sparklines (Sales, Orders, Customers, Low Stock)
///  * Analytics & Receivables split: Smooth bezier area chart with range toggles, and
///    Amount Receivable summary card with overdue/due-today breakdown
///  * 4 Quick Action tiles (Add Product, Create Order, Add Customer, View Reports)
///  * Recent Transactions table with order #, customer avatar, amount, and status pill
///  * Floating Persistent Bottom Navigation Bar (Home, Sales, FAB '+' Quick Sale, Orders, More)
class RedesignedMetricDashboard extends StatefulWidget {
  const RedesignedMetricDashboard({
    super.key,
    required this.summary,
    this.onAddProduct,
    this.onCreateOrder,
    this.onAddCustomer,
    this.onViewReports,
    this.onOpenTransactions,
    this.onOpenNotifications,
    this.onRangeChanged,
    this.isDemo = false,
    this.onTryFlutterDemo,
    this.bottomNavIndex = 0,
    this.onBottomNavTap,
  });

  final DashboardSummaryModel summary;
  final VoidCallback? onAddProduct;
  final VoidCallback? onCreateOrder;
  final VoidCallback? onAddCustomer;
  final VoidCallback? onViewReports;
  final VoidCallback? onOpenTransactions;
  final VoidCallback? onOpenNotifications;
  final void Function(String range)? onRangeChanged;
  final bool isDemo;
  final VoidCallback? onTryFlutterDemo;
  final int bottomNavIndex;
  final void Function(int index)? onBottomNavTap;

  @override
  State<RedesignedMetricDashboard> createState() => _RedesignedMetricDashboardState();
}

class _RedesignedMetricDashboardState extends State<RedesignedMetricDashboard> {
  late String _selectedRange;

  @override
  void initState() {
    super.initState();
    _selectedRange = widget.summary.salesOverview.currentRange;
  }

  @override
  void didUpdateWidget(covariant RedesignedMetricDashboard oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.summary.salesOverview.currentRange != widget.summary.salesOverview.currentRange) {
      _selectedRange = widget.summary.salesOverview.currentRange;
    }
  }

  void _handleRangeSelect(String range) {
    setState(() => _selectedRange = range);
    widget.onRangeChanged?.call(range);
    try {
      final storeId = context.read<StoreProvider?>()?.current?.id;
      context.read<DashboardProvider?>()?.fetchSalesOverview(period: range, storeId: storeId);
    } catch (_) {}
  }

  Future<void> _launchDemoUrl() async {
    if (widget.onTryFlutterDemo != null) {
      widget.onTryFlutterDemo!();
      return;
    }
    final uri = Uri.parse('https://web.zoomnearby.com/demo');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return LayoutBuilder(
      builder: (context, constraints) {
        final isWide = constraints.maxWidth >= 850;

        return Padding(
          padding: const EdgeInsets.only(bottom: 32),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Demo Workspace Banner (if demo active)
              if (widget.isDemo) ...[
                _buildDemoBanner(context),
                const SizedBox(height: 14),
              ],

              // Dynamic Greeting & Status Badges
              _buildGreetingAndStatusBadges(context, isDark, isWide),
              const SizedBox(height: 18),

              // 4-Column Stat Metric Cards with Bezier Sparklines
              _buildStatMetricCards(context, isDark, isWide),
              const SizedBox(height: 20),

              // Analytics & Receivables Split Section
              if (isWide)
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(flex: 7, child: _buildSalesOverviewCard(context, isDark)),
                    const SizedBox(width: 16),
                    Expanded(flex: 5, child: _buildReceivablesSummaryCard(context, isDark)),
                  ],
                )
              else ...[
                _buildSalesOverviewCard(context, isDark),
                const SizedBox(height: 16),
                _buildReceivablesSummaryCard(context, isDark),
              ],
              const SizedBox(height: 20),

              // 4 Quick Action Tiles
              _buildQuickActionsGrid(context, isDark),
              const SizedBox(height: 20),

              // Recent Transactions Table
              _buildRecentTransactionsCard(context, isDark),
            ],
          ),
        );
      },
    );
  }

  Widget _buildDemoBanner(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFF59E0B), Color(0xFFEA580C)],
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFF59E0B).withValues(alpha: 0.25),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.2),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Text(
              'DEMO MODE',
              style: TextStyle(
                color: Colors.white,
                fontSize: 10,
                fontWeight: FontWeight.w900,
                letterSpacing: 0.5,
              ),
            ),
          ),
          const SizedBox(width: 10),
          const Expanded(
            child: Text(
              'Sample data active. Explore live web POS interface.',
              style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
              overflow: TextOverflow.ellipsis,
            ),
          ),
          const SizedBox(width: 8),
          ElevatedButton(
            onPressed: _launchDemoUrl,
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.white,
              foregroundColor: const Color(0xFF9A3412),
              elevation: 0,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            child: const Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('🚀 Try Flutter Web', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800)),
                SizedBox(width: 4),
                Icon(Icons.open_in_new, size: 12),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildGreetingAndStatusBadges(BuildContext context, bool isDark, bool isWide) {
    final greeting = widget.summary.greeting;
    final badges = widget.summary.statusBadges;

    final greetingCol = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          greeting.title,
          style: TextStyle(
            fontSize: isWide ? 22 : 18,
            fontWeight: FontWeight.w900,
            letterSpacing: -0.5,
            color: isDark ? Colors.white : const Color(0xFF0F172A),
          ),
        ),
        const SizedBox(height: 2),
        Text(
          greeting.subtitle,
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w500,
            color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
          ),
        ),
      ],
    );

    final badgesRow = Wrap(
      spacing: 8,
      runSpacing: 6,
      children: [
        // Date/Time pill
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF1E293B) : const Color(0xFFF1F5F9),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: isDark ? Colors.white.withValues(alpha: 0.08) : const Color(0xFFE2E8F0),
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.calendar_today_outlined, size: 12, color: Color(0xFF2563EB)),
              const SizedBox(width: 6),
              Text(
                badges.datetime,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: isDark ? const Color(0xFFCBD5E1) : const Color(0xFF334155),
                ),
              ),
            ],
          ),
        ),
        // Weather pill
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF1E293B) : const Color(0xFFF1F5F9),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: isDark ? Colors.white.withValues(alpha: 0.08) : const Color(0xFFE2E8F0),
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.wb_sunny_outlined, size: 13, color: Color(0xFFF59E0B)),
              const SizedBox(width: 6),
              Text(
                badges.weather,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: isDark ? const Color(0xFFCBD5E1) : const Color(0xFF334155),
                ),
              ),
            ],
          ),
        ),
      ],
    );

    if (isWide) {
      return Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Expanded(child: greetingCol),
          badgesRow,
        ],
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        greetingCol,
        const SizedBox(height: 10),
        badgesRow,
      ],
    );
  }

  Widget _buildStatMetricCards(BuildContext context, bool isDark, bool isWide) {
    final m = widget.summary.metrics;

    final cards = [
      _MetricCardSpec(
        title: 'Total Sales',
        formattedValue: m.totalSales.formatted,
        trend: m.totalSales.trend,
        isPositive: m.totalSales.isPositive,
        color: const Color(0xFF10B981), // Emerald
        icon: Icons.payments_outlined,
        sparkline: m.totalSales.sparkline,
      ),
      _MetricCardSpec(
        title: 'Total Orders',
        formattedValue: m.totalOrders.formatted,
        trend: m.totalOrders.trend,
        isPositive: m.totalOrders.isPositive,
        color: const Color(0xFF0284C7), // Sky blue
        icon: Icons.shopping_bag_outlined,
        sparkline: m.totalOrders.sparkline,
      ),
      _MetricCardSpec(
        title: 'Total Customers',
        formattedValue: m.totalCustomers.formatted,
        trend: m.totalCustomers.trend,
        isPositive: m.totalCustomers.isPositive,
        color: const Color(0xFF8B5CF6), // Purple
        icon: Icons.people_outline,
        sparkline: m.totalCustomers.sparkline,
      ),
      _MetricCardSpec(
        title: 'Low Stock Items',
        formattedValue: m.lowStockItems.formatted,
        trend: m.lowStockItems.trend,
        isPositive: m.lowStockItems.isPositive,
        color: const Color(0xFFF59E0B), // Amber
        icon: Icons.inventory_2_outlined,
        sparkline: m.lowStockItems.sparkline,
      ),
    ];

    if (isWide) {
      return Row(
        children: cards
            .map((c) => Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 6),
                    child: _buildSingleMetricCard(c, isDark),
                  ),
                ))
            .toList(),
      );
    }

    return LayoutBuilder(
      builder: (context, constraints) => GridView.count(
        crossAxisCount: constraints.maxWidth < 320 ? 1 : 2,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
        mainAxisExtent: 170,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        children: cards.map((c) => _buildSingleMetricCard(c, isDark)).toList(),
      ),
    );
  }

  Widget _buildSingleMetricCard(_MetricCardSpec spec, bool isDark) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: isDark ? Colors.white.withValues(alpha: 0.08) : const Color(0xFFE2E8F0),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              // Icon container
              Container(
                width: 32,
                height: 32,
                decoration: BoxDecoration(
                  color: spec.color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(spec.icon, color: spec.color, size: 17),
              ),
              // Trend pill
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: spec.isPositive
                      ? const Color(0xFF10B981).withValues(alpha: 0.12)
                      : const Color(0xFFF59E0B).withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      spec.isPositive ? Icons.arrow_upward_rounded : Icons.arrow_downward_rounded,
                      size: 10,
                      color: spec.isPositive ? const Color(0xFF10B981) : const Color(0xFFF59E0B),
                    ),
                    const SizedBox(width: 2),
                    Text(
                      spec.trend,
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        color: spec.isPositive ? const Color(0xFF10B981) : const Color(0xFFF59E0B),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                spec.formattedValue,
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.5,
                  color: isDark ? Colors.white : const Color(0xFF0F172A),
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              Text(
                spec.title,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w500,
                  color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
          const SizedBox(height: 6),
          // Smooth sparkline
          SizedBox(
            height: 24,
            child: _buildMiniSparkline(spec.sparkline, spec.color),
          ),
        ],
      ),
    );
  }

  Widget _buildMiniSparkline(List<double> points, Color color) {
    if (points.isEmpty) return const SizedBox.shrink();

    final spots = <FlSpot>[];
    for (var i = 0; i < points.length; i++) {
      spots.add(FlSpot(i.toDouble(), points[i]));
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

  Widget _buildSalesOverviewCard(BuildContext context, bool isDark) {
    final dashboardProvider = context.watch<DashboardProvider?>();
    final overview = (dashboardProvider?.salesOverview != null &&
            dashboardProvider?.currentPeriod == _selectedRange)
        ? dashboardProvider!.salesOverview!
        : widget.summary.salesOverview;
    final series = overview.series;
    final isLoading = dashboardProvider?.isLoading ?? false;

    final spots = <FlSpot>[];
    for (var i = 0; i < series.length; i++) {
      spots.add(FlSpot(i.toDouble(), series[i].amount));
    }

    final hasPoints = spots.isNotEmpty;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: isDark ? Colors.white.withValues(alpha: 0.08) : const Color(0xFFE2E8F0),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.03),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header + Range Selector Pills
          LayoutBuilder(
            builder: (context, headerConstraints) {
              final isHeaderCompact = headerConstraints.maxWidth < 460;
              if (isHeaderCompact) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(
                          'Sales Overview',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            color: isDark ? Colors.white : const Color(0xFF0F172A),
                          ),
                        ),
                        if (isLoading) ...[
                          const SizedBox(width: 8),
                          SizedBox(
                            width: 12,
                            height: 12,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: isDark ? const Color(0xFF38BDF8) : const Color(0xFF2563EB),
                            ),
                          ),
                        ],
                      ],
                    ),
                    Text(
                      'Revenue trend with area gradient',
                      style: TextStyle(
                        fontSize: 11,
                        color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
                      ),
                    ),
                    const SizedBox(height: 10),
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Container(
                        padding: const EdgeInsets.all(3),
                        decoration: BoxDecoration(
                          color: isDark ? const Color(0xFF0F172A) : const Color(0xFFF1F5F9),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            _buildRangePill('Last 7 Days', 'last_7_days', isDark),
                            _buildRangePill('This Month', 'this_month', isDark),
                            _buildRangePill('Quarter', 'quarter', isDark),
                          ],
                        ),
                      ),
                    ),
                  ],
                );
              }
              return Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Text(
                              'Sales Overview',
                              style: TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w800,
                                color: isDark ? Colors.white : const Color(0xFF0F172A),
                              ),
                            ),
                            if (isLoading) ...[
                              const SizedBox(width: 8),
                              SizedBox(
                                width: 12,
                                height: 12,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: isDark ? const Color(0xFF38BDF8) : const Color(0xFF2563EB),
                                ),
                              ),
                            ],
                          ],
                        ),
                        Text(
                          'Revenue trend with area gradient',
                          style: TextStyle(
                            fontSize: 11,
                            color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.all(3),
                    decoration: BoxDecoration(
                      color: isDark ? const Color(0xFF0F172A) : const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        _buildRangePill('Last 7 Days', 'last_7_days', isDark),
                        _buildRangePill('This Month', 'this_month', isDark),
                        _buildRangePill('Quarter', 'quarter', isDark),
                      ],
                    ),
                  ),
                ],
              );
            },
          ),
          const SizedBox(height: 20),

          // Bezier Area Chart with Gradient
          SizedBox(
            height: 180,
            child: hasPoints
                ? LineChart(
                    LineChartData(
                      gridData: FlGridData(
                        show: true,
                        drawVerticalLine: false,
                        getDrawingHorizontalLine: (value) => FlLine(
                          color: isDark
                              ? Colors.white.withValues(alpha: 0.05)
                              : const Color(0xFFE2E8F0),
                          strokeWidth: 1,
                        ),
                      ),
                      titlesData: FlTitlesData(
                        topTitles: const FlTitlesData(show: false).topTitles,
                        rightTitles: const FlTitlesData(show: false).rightTitles,
                        bottomTitles: AxisTitles(
                          sideTitles: SideTitles(
                            showTitles: true,
                            reservedSize: 22,
                            interval: 1,
                            getTitlesWidget: (value, meta) {
                              final idx = value.toInt();
                              if (idx < 0 || idx >= series.length) {
                                return const SizedBox.shrink();
                              }
                              // Sample intervals if series is large
                              if (series.length > 8 && idx % 3 != 0 && idx != series.length - 1) {
                                return const SizedBox.shrink();
                              }
                              return Text(
                                series[idx].label,
                                style: TextStyle(
                                  fontSize: 9,
                                  fontWeight: FontWeight.w600,
                                  color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
                                ),
                              );
                            },
                          ),
                        ),
                        leftTitles: const FlTitlesData(show: false).leftTitles,
                      ),
                      borderData: FlBorderData(show: false),
                      lineBarsData: [
                        LineChartBarData(
                          spots: spots,
                          isCurved: true,
                          curveSmoothness: 0.35,
                          color: const Color(0xFF27E498),
                          barWidth: 3,
                          isStrokeCapRound: true,
                          dotData: FlDotData(
                            show: spots.length <= 10,
                            getDotPainter: (spot, percent, barData, index) => FlDotCirclePainter(
                              radius: 3.5,
                              color: Colors.white,
                              strokeWidth: 2,
                              strokeColor: const Color(0xFF27E498),
                            ),
                          ),
                          belowBarData: BarAreaData(
                            show: true,
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [
                                const Color(0xFF27E498).withValues(alpha: 0.3),
                                const Color(0xFF27E498).withValues(alpha: 0.0),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  )
                : const Center(
                    child: Text('No chart data available for selected range',
                        style: TextStyle(fontSize: 12, color: Colors.grey)),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildRangePill(String label, String key, bool isDark) {
    final isSelected = _selectedRange == key;

    return InkWell(
      onTap: () => _handleRangeSelect(key),
      borderRadius: BorderRadius.circular(16),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(
          color: isSelected
              ? (isDark ? const Color(0xFF2563EB) : Colors.white)
              : Colors.transparent,
          borderRadius: BorderRadius.circular(16),
          boxShadow: isSelected && !isDark
              ? [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.06),
                    blurRadius: 4,
                    offset: const Offset(0, 1),
                  ),
                ]
              : null,
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 10,
            fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
            color: isSelected
                ? (isDark ? Colors.white : const Color(0xFF0F172A))
                : (isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B)),
          ),
        ),
      ),
    );
  }

  Widget _buildReceivablesSummaryCard(BuildContext context, bool isDark) {
    final r = widget.summary.receivables;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: isDark ? Colors.white.withValues(alpha: 0.08) : const Color(0xFFE2E8F0),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.03),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header
          Wrap(
            alignment: WrapAlignment.spaceBetween,
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 8,
            runSpacing: 6,
            children: [
              Text(
                'Amount Receivable',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                  color: isDark ? Colors.white : const Color(0xFF0F172A),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFFEF4444).withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  '${r.outstandingInvoicesCount} Invoices',
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFFEF4444),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Big Total Outstanding Figure
          Text(
            r.formatted,
            style: TextStyle(
              fontSize: 26,
              fontWeight: FontWeight.w900,
              letterSpacing: -0.5,
              color: isDark ? Colors.white : const Color(0xFF0F172A),
            ),
          ),
          Text(
            'Total outstanding pending customer payment',
            style: TextStyle(
              fontSize: 11,
              color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
            ),
          ),
          const SizedBox(height: 16),
          const Divider(height: 1),
          const SizedBox(height: 14),

          // Breakdown List
          _buildReceivableBreakdownRow(
            icon: Icons.error_outline,
            iconColor: const Color(0xFFEF4444),
            label: 'Overdue Amount',
            value: r.formattedOverdue,
            isDark: isDark,
            onTap: () {
              Navigator.pushNamed(
                context,
                '/sales',
                arguments: {'initial_due_filter': 'overdue'},
              );
            },
          ),
          const SizedBox(height: 10),
          _buildReceivableBreakdownRow(
            icon: Icons.schedule,
            iconColor: const Color(0xFFF59E0B),
            label: 'Due Today',
            value: r.formattedDueToday,
            isDark: isDark,
            onTap: () {
              Navigator.pushNamed(
                context,
                '/sales',
                arguments: {'initial_due_filter': 'due_today'},
              );
            },
          ),
          const SizedBox(height: 16),

          // Action Button
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: widget.onOpenTransactions,
              icon: const Icon(Icons.send_outlined, size: 14),
              label: const Text('Send Payment Reminders', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFF2563EB),
                side: const BorderSide(color: Color(0xFF2563EB)),
                padding: const EdgeInsets.symmetric(vertical: 10),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildReceivableBreakdownRow({
    required IconData icon,
    required Color iconColor,
    required String label,
    required String value,
    required bool isDark,
    VoidCallback? onTap,
  }) {
    final rowContent = Row(
      children: [
        Container(
          width: 26,
          height: 26,
          decoration: BoxDecoration(
            color: iconColor.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(icon, color: iconColor, size: 14),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: isDark ? const Color(0xFFCBD5E1) : const Color(0xFF334155),
            ),
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w800,
            color: isDark ? Colors.white : const Color(0xFF0F172A),
          ),
        ),
        if (onTap != null) ...[
          const SizedBox(width: 4),
          Icon(
            Icons.chevron_right,
            size: 16,
            color: isDark ? const Color(0xFF64748B) : const Color(0xFF94A3B8),
          ),
        ],
      ],
    );

    if (onTap != null) {
      return InkWell(
        borderRadius: BorderRadius.circular(8),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 2),
          child: rowContent,
        ),
      );
    }

    return rowContent;
  }

  Widget _buildQuickActionsGrid(BuildContext context, bool isDark) {
    final actions = [
      _QuickActionSpec('Add Product', Icons.add_box_outlined, const Color(0xFF06B6D4), widget.onAddProduct),
      _QuickActionSpec('Create Order', Icons.point_of_sale_outlined, const Color(0xFF2563EB), widget.onCreateOrder),
      _QuickActionSpec('Add Customer', Icons.person_add_outlined, const Color(0xFF8B5CF6), widget.onAddCustomer),
      _QuickActionSpec('View Reports', Icons.insert_chart_outlined_rounded, const Color(0xFFF59E0B), widget.onViewReports),
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Quick Actions',
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w800,
            color: isDark ? Colors.white : const Color(0xFF0F172A),
          ),
        ),
        const SizedBox(height: 10),
        Row(
          children: actions
              .map((a) => Expanded(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                      child: InkWell(
                        onTap: a.onTap,
                        borderRadius: BorderRadius.circular(16),
                        child: Container(
                          padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
                          decoration: BoxDecoration(
                            color: isDark ? const Color(0xFF1E293B) : Colors.white,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(
                              color: isDark ? Colors.white.withValues(alpha: 0.08) : const Color(0xFFE2E8F0),
                            ),
                          ),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Container(
                                width: 36,
                                height: 36,
                                decoration: BoxDecoration(
                                  color: a.color.withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Icon(a.icon, color: a.color, size: 20),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                a.title,
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                  color: isDark ? Colors.white : const Color(0xFF0F172A),
                                ),
                                textAlign: TextAlign.center,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ))
              .toList(),
        ),
      ],
    );
  }

  Widget _buildRecentTransactionsCard(BuildContext context, bool isDark) {
    final txList = widget.summary.recentTransactions;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: isDark ? Colors.white.withValues(alpha: 0.08) : const Color(0xFFE2E8F0),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.03),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            alignment: WrapAlignment.spaceBetween,
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 8,
            runSpacing: 6,
            children: [
              Text(
                'Recent Transactions',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                  color: isDark ? Colors.white : const Color(0xFF0F172A),
                ),
              ),
              if (widget.onOpenTransactions != null)
                TextButton(
                  onPressed: widget.onOpenTransactions,
                  child: const Text('View All', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                ),
            ],
          ),
          const SizedBox(height: 10),
          if (txList.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 20),
              child: Center(
                child: Text('No recent transactions recorded',
                    style: TextStyle(fontSize: 12, color: Colors.grey)),
              ),
            )
          else
            ListView.separated(
              itemCount: txList.length,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              separatorBuilder: (_, __) => Divider(
                height: 1,
                color: isDark ? Colors.white.withValues(alpha: 0.05) : const Color(0xFFF1F5F9),
              ),
              itemBuilder: (context, index) {
                final tx = txList[index];
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  child: Row(
                    children: [
                      // Initials avatar
                      CircleAvatar(
                        radius: 18,
                        backgroundColor: tx.isCompleted
                            ? const Color(0xFF10B981).withValues(alpha: 0.15)
                            : const Color(0xFFF59E0B).withValues(alpha: 0.15),
                        child: Text(
                          tx.customerInitials,
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            color: tx.isCompleted ? const Color(0xFF10B981) : const Color(0xFFF59E0B),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      // Customer name & date
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              tx.customerName,
                              style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: isDark ? Colors.white : const Color(0xFF0F172A),
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                            Text(
                              '${tx.orderNumber} · ${tx.datetime}',
                              style: TextStyle(
                                fontSize: 10,
                                color: isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B),
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      // Amount & Status pill
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            tx.formattedAmount,
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                              color: isDark ? Colors.white : const Color(0xFF0F172A),
                            ),
                          ),
                          const SizedBox(height: 2),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: tx.isCompleted
                                  ? const Color(0xFF10B981).withValues(alpha: 0.12)
                                  : const Color(0xFFF59E0B).withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              tx.status,
                              style: TextStyle(
                                fontSize: 9,
                                fontWeight: FontWeight.w700,
                                color: tx.isCompleted
                                    ? const Color(0xFF10B981)
                                    : const Color(0xFFF59E0B),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              },
            ),
        ],
      ),
    );
  }
}

class _MetricCardSpec {
  const _MetricCardSpec({
    required this.title,
    required this.formattedValue,
    required this.trend,
    required this.isPositive,
    required this.color,
    required this.icon,
    required this.sparkline,
  });

  final String title;
  final String formattedValue;
  final String trend;
  final bool isPositive;
  final Color color;
  final IconData icon;
  final List<double> sparkline;
}

class _QuickActionSpec {
  const _QuickActionSpec(this.title, this.icon, this.color, this.onTap);

  final String title;
  final IconData icon;
  final Color color;
  final VoidCallback? onTap;
}
