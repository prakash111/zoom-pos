import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../../../core/models/analytics_model.dart';

/// A single KPI tile — shared between [AnalyticsScreen] and the condensed
/// row on [DashboardScreen]. Lifts on hover and shows an ink ripple when it
/// carries an [onTap].
class KpiCard extends StatefulWidget {
  const KpiCard({
    super.key,
    required this.label,
    required this.value,
    this.sub,
    this.valueColor,
    this.icon,
    this.onTap,
  });

  final String label;
  final String value;
  final String? sub;
  final Color? valueColor;
  final IconData? icon;
  final VoidCallback? onTap;

  @override
  State<KpiCard> createState() => _KpiCardState();
}

class _KpiCardState extends State<KpiCard> {
  bool _hovered = false;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final interactive = widget.onTap != null;
    return MouseRegion(
      cursor: interactive ? SystemMouseCursors.click : MouseCursor.defer,
      onEnter: (_) => setState(() => _hovered = true),
      onExit: (_) => setState(() => _hovered = false),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 140),
        curve: Curves.easeOut,
        transform: Matrix4.translationValues(0, _hovered ? -2 : 0, 0),
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: _hovered && interactive
                ? scheme.primary.withValues(alpha: 0.55)
                : scheme.outlineVariant,
          ),
          boxShadow: _hovered
              ? [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.10),
                    blurRadius: 16,
                    offset: const Offset(0, 6),
                  ),
                ]
              : null,
        ),
        child: Material(
          type: MaterialType.transparency,
          child: InkWell(
            onTap: widget.onTap,
            borderRadius: BorderRadius.circular(12),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Row(
                    children: [
                      if (widget.icon != null) ...[
                        Icon(widget.icon,
                            size: 15, color: scheme.onSurfaceVariant),
                        const SizedBox(width: 6),
                      ],
                      Expanded(
                        child: Text(
                          widget.label,
                          style: TextStyle(
                              color: scheme.onSurfaceVariant, fontSize: 12),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    widget.value,
                    style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: widget.valueColor ?? scheme.onSurface),
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (widget.sub != null) ...[
                    const SizedBox(height: 2),
                    Text(widget.sub!,
                        style: TextStyle(
                            color: scheme.onSurfaceVariant, fontSize: 11)),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// 7-day revenue trend line chart — shared between [AnalyticsScreen] and
/// [DashboardScreen].
class RevenueTrendChart extends StatelessWidget {
  const RevenueTrendChart({super.key, required this.points});

  final List<RevenueTrendPoint> points;

  @override
  Widget build(BuildContext context) {
    final maxRevenue =
        points.map((p) => p.revenue).fold<double>(0, (a, b) => a > b ? a : b);
    final scheme = Theme.of(context).colorScheme;
    final primary = scheme.primary;
    final axisStyle = TextStyle(color: scheme.onSurfaceVariant, fontSize: 11);

    return LineChart(
      LineChartData(
        minY: 0,
        maxY: maxRevenue <= 0 ? 1 : maxRevenue * 1.2,
        gridData: const FlGridData(show: false),
        borderData: FlBorderData(show: false),
        titlesData: FlTitlesData(
          topTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          rightTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          leftTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 24,
              getTitlesWidget: (value, meta) {
                final index = value.round();
                if (index < 0 || index >= points.length)
                  return const SizedBox.shrink();
                return Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(points[index].day, style: axisStyle),
                );
              },
            ),
          ),
        ),
        lineTouchData: LineTouchData(
          touchTooltipData: LineTouchTooltipData(
            getTooltipItems: (spots) => spots
                .map((spot) => LineTooltipItem(
                    points[spot.x.round()].revenue.toStringAsFixed(2),
                    const TextStyle(color: Colors.white)))
                .toList(),
          ),
        ),
        lineBarsData: [
          LineChartBarData(
            spots: [
              for (var i = 0; i < points.length; i++)
                FlSpot(i.toDouble(), points[i].revenue)
            ],
            isCurved: true,
            color: primary,
            barWidth: 3,
            dotData: const FlDotData(show: false),
            belowBarData:
                BarAreaData(show: true, color: primary.withValues(alpha: 0.15)),
          ),
        ],
      ),
    );
  }
}
