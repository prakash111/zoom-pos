import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/models/dashboard_summary_model.dart';
import '../../../core/providers/dashboard_provider.dart';
import '../../../core/stores/store_provider.dart';

/// Sales Overview interactive chart with responsive segmented date filter pills
/// and dynamic store-scoped metrics.
class SalesOverviewChart extends StatefulWidget {
  const SalesOverviewChart({
    super.key,
    this.initialData,
    this.storeId,
    this.onPeriodChanged,
  });

  final SalesOverviewData? initialData;
  final int? storeId;
  final void Function(String period)? onPeriodChanged;

  @override
  State<SalesOverviewChart> createState() => _SalesOverviewChartState();
}

class _SalesOverviewChartState extends State<SalesOverviewChart> {
  late String _selectedPeriod;

  DateTimeRange? _customDateRange;

  String get _customLabel {
    if (_customDateRange != null) {
      final start = _customDateRange!.start;
      final end = _customDateRange!.end;
      final s = '${start.day.toString().padLeft(2, '0')}/${start.month.toString().padLeft(2, '0')}';
      final e = '${end.day.toString().padLeft(2, '0')}/${end.month.toString().padLeft(2, '0')}';
      return '$s - $e';
    }
    return 'Custom';
  }

  static const List<Map<String, String>> _periods = [
    {'label': 'Last 7 Days', 'key': 'last_7_days'},
    {'label': 'This Month', 'key': 'this_month'},
    {'label': 'Quarter', 'key': 'quarter'},
  ];

  @override
  void initState() {
    super.initState();
    _selectedPeriod = widget.initialData?.currentRange ?? 'last_7_days';

    // Seed initial data to DashboardProvider if provider has no data yet
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final provider = context.read<DashboardProvider>();
      if (provider.salesOverview == null && widget.initialData != null) {
        provider.setSalesOverview(widget.initialData!);
      }
    });
  }

  Future<void> _pickCustomDateRange() async {
    final now = DateTime.now();
    final initialRange = _customDateRange ??
        DateTimeRange(
          start: now.subtract(const Duration(days: 7)),
          end: now,
        );

    final picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2020),
      lastDate: DateTime(now.year + 2),
      initialDateRange: initialRange,
      builder: (context, child) {
        return Theme(
          data: Theme.of(context),
          child: child ?? const SizedBox(),
        );
      },
    );

    if (picked != null) {
      setState(() {
        _customDateRange = picked;
        _selectedPeriod = 'custom';
      });

      final currentStoreId = widget.storeId ??
          context.read<StoreProvider?>()?.current?.id;
      final startDateStr = picked.start.toIso8601String().split('T').first;
      final endDateStr = picked.end.toIso8601String().split('T').first;

      context.read<DashboardProvider>().fetchSalesOverview(
            period: 'custom',
            storeId: currentStoreId,
            startDate: startDateStr,
            endDate: endDateStr,
          );

      widget.onPeriodChanged?.call('custom');
    }
  }

  void _handlePeriodSelected(String periodKey) {
    if (periodKey == 'custom') {
      _pickCustomDateRange();
      return;
    }

    setState(() {
      _selectedPeriod = periodKey;
    });

    final currentStoreId = widget.storeId ??
        context.read<StoreProvider?>()?.current?.id;

    context
        .read<DashboardProvider>()
        .fetchSalesOverview(period: periodKey, storeId: currentStoreId);

    widget.onPeriodChanged?.call(periodKey);
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final dashboardProvider = context.watch<DashboardProvider>();

    final overview = dashboardProvider.salesOverview ?? widget.initialData;
    final activeRange = dashboardProvider.currentPeriod.isNotEmpty
        ? dashboardProvider.currentPeriod
        : _selectedPeriod;
    final isLoading = dashboardProvider.isLoading;

    final series = overview?.series ?? [];
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
          color: isDark
              ? Colors.white.withValues(alpha: 0.08)
              : const Color(0xFFE2E8F0),
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
              final headerInfo = Column(
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
              );

              final pillsRow = Container(
                padding: const EdgeInsets.all(3),
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF0F172A) : const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    ..._periods.map((p) {
                      return _buildFilterPill(
                        label: p['label']!,
                        periodKey: p['key']!,
                        isSelected: activeRange == p['key'],
                        isDark: isDark,
                      );
                    }),
                    _buildFilterPill(
                      label: _customLabel,
                      periodKey: 'custom',
                      isSelected: activeRange == 'custom',
                      isDark: isDark,
                    ),
                  ],
                ),
              );

              if (isHeaderCompact) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    headerInfo,
                    const SizedBox(height: 10),
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: pillsRow,
                    ),
                  ],
                );
              }

              return Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(child: headerInfo),
                  const SizedBox(width: 8),
                  pillsRow,
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
                              if (series.length > 8 &&
                                  idx % 3 != 0 &&
                                  idx != series.length - 1) {
                                return const SizedBox.shrink();
                              }
                              return Text(
                                series[idx].label,
                                style: TextStyle(
                                  fontSize: 9,
                                  fontWeight: FontWeight.w600,
                                  color: isDark
                                      ? const Color(0xFF94A3B8)
                                      : const Color(0xFF64748B),
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
                          color: const Color(0xFF2563EB),
                          barWidth: 2.5,
                          isStrokeCapRound: true,
                          dotData: FlDotData(
                            show: spots.length <= 10,
                            getDotPainter: (spot, percent, barData, index) =>
                                FlDotCirclePainter(
                              radius: 3,
                              color: Colors.white,
                              strokeWidth: 2,
                              strokeColor: const Color(0xFF2563EB),
                            ),
                          ),
                          belowBarData: BarAreaData(
                            show: true,
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [
                                const Color(0xFF2563EB).withValues(alpha: 0.28),
                                const Color(0xFF2563EB).withValues(alpha: 0.0),
                              ],
                            ),
                          ),
                        ),
                      ],
                      lineTouchData: LineTouchData(
                        touchTooltipData: LineTouchTooltipData(
                          getTooltipItems: (touchedSpots) {
                            return touchedSpots.map((spot) {
                              final idx = spot.spotIndex;
                              final pt = (idx >= 0 && idx < series.length)
                                  ? series[idx]
                                  : null;
                              return LineTooltipItem(
                                '${pt?.day ?? ''}\n',
                                TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w600,
                                  color: isDark
                                      ? const Color(0xFFCBD5E1)
                                      : const Color(0xFF475569),
                                ),
                                children: [
                                  TextSpan(
                                    text: spot.y.toStringAsFixed(2),
                                    style: TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w800,
                                      color: isDark
                                          ? Colors.white
                                          : const Color(0xFF0F172A),
                                    ),
                                  ),
                                ],
                              );
                            }).toList();
                          },
                        ),
                      ),
                    ),
                  )
                : Center(
                    child: Text(
                      isLoading
                          ? 'Loading sales overview...'
                          : 'No sales recorded for this period',
                      style: TextStyle(
                        fontSize: 12,
                        color: isDark
                            ? const Color(0xFF64748B)
                            : const Color(0xFF94A3B8),
                      ),
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterPill({
    required String label,
    required String periodKey,
    required bool isSelected,
    required bool isDark,
  }) {
    return InkWell(
      onTap: () => _handlePeriodSelected(periodKey),
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
            fontSize: 11,
            fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
            color: isSelected
                ? (isDark ? Colors.white : const Color(0xFF0F172A))
                : (isDark ? const Color(0xFF94A3B8) : const Color(0xFF64748B)),
          ),
        ),
      ),
    );
  }
}
