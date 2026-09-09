import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../../../core/models/analytics_model.dart';
import '../../../core/utils/currency_formatter.dart';

/// Alternate dashboard treatment — the dark, glossy "OroitOash" analytics
/// board. Bound to the same `GET /analytics` payload as [PoshDashboardHome];
/// it is always rendered dark, independent of the app's light/dark theme, to
/// match the reference design.
class OroitDashboardHome extends StatelessWidget {
  const OroitDashboardHome({
    super.key,
    required this.analytics,
    required this.formatter,
    this.onAddProduct,
    this.onOpenTransactions,
  });

  final AnalyticsModel analytics;
  final CurrencyFormatter formatter;
  final VoidCallback? onAddProduct;
  final VoidCallback? onOpenTransactions;

  static const _bg = Color(0xFF0B0B12);
  static const _card = Color(0xFF15151F);
  static const _cardAlt = Color(0xFF1B1B27);
  static const _border = Color(0xFF262636);
  static const _muted = Color(0xFF8A8AA3);
  static const _purple = Color(0xFF7C5CFF);
  static const _cyan = Color(0xFF34D3EE);
  static const _blue = Color(0xFF4C8DFF);
  static const _mint = Color(0xFF34D399);
  static const _amber = Color(0xFFF5B546);

  @override
  Widget build(BuildContext context) {
    final activity = analytics.monthlyActivity;
    final completedTotal = activity.fold<int>(0, (s, m) => s + m.completed);
    final activeOrders = activity.isNotEmpty ? activity.last.pending : 0;

    final cards = <_StatSpec>[
      _StatSpec('Total orders', analytics.monthOrders.toString(),
          analytics.ordersDelta, _purple),
      _StatSpec('Total sales', formatter.format(analytics.monthRevenue),
          analytics.revenueDelta, _blue),
      _StatSpec('Active order', activeOrders.toString(), analytics.ordersDelta,
          _cyan),
      _StatSpec(
          'Average order size',
          formatter.format(analytics.averageOrderValue),
          analytics.revenueDelta,
          _mint),
    ];

    return DefaultTextStyle(
      style: const TextStyle(color: Colors.white),
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: _bg,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: _border),
        ),
        child: LayoutBuilder(
          builder: (context, constraints) {
            final wide = constraints.maxWidth >= 960;
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _header(),
                const SizedBox(height: 18),
                _statRow(cards, wide),
                const SizedBox(height: 16),
                if (wide)
                  IntrinsicHeight(
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Expanded(flex: 3, child: _overviewCard(activity)),
                        const SizedBox(width: 16),
                        Expanded(
                          flex: 1,
                          child: _sideTiles(completedTotal),
                        ),
                      ],
                    ),
                  )
                else ...[
                  _overviewCard(activity),
                  const SizedBox(height: 16),
                  _sideTiles(completedTotal),
                ],
                const SizedBox(height: 16),
                if (wide)
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(flex: 2, child: _marketingCard(activity)),
                      const SizedBox(width: 16),
                      Expanded(flex: 3, child: _orderTrackingCard()),
                    ],
                  )
                else ...[
                  _marketingCard(activity),
                  const SizedBox(height: 16),
                  _orderTrackingCard(),
                ],
              ],
            );
          },
        ),
      ),
    );
  }

  // ------------------------------------------------------------------ header
  Widget _header() {
    return Row(
      children: [
        const Expanded(
          child: Text('Order statistic',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
        ),
        _pill('This period'),
        const SizedBox(width: 10),
        _AddButton(onTap: onAddProduct),
      ],
    );
  }

  // --------------------------------------------------------------- stat cards
  Widget _statRow(List<_StatSpec> cards, bool wide) {
    if (wide) {
      return Row(
        children: [
          for (var i = 0; i < cards.length; i++) ...[
            if (i > 0) const SizedBox(width: 14),
            Expanded(child: _statCard(cards[i])),
          ],
        ],
      );
    }
    return Wrap(
      spacing: 14,
      runSpacing: 14,
      children: [
        for (final c in cards) SizedBox(width: 220, child: _statCard(c)),
      ],
    );
  }

  Widget _statCard(_StatSpec s) {
    final up = s.delta >= 0;
    final spots = <FlSpot>[
      for (var i = 0; i < analytics.revenueTrend.length; i++)
        FlSpot(i.toDouble(), analytics.revenueTrend[i].revenue),
    ];
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _card,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(s.label, style: const TextStyle(fontSize: 12.5, color: _muted)),
          const SizedBox(height: 8),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: Text(
                  s.value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      color: s.color),
                ),
              ),
              SizedBox(
                width: 66,
                height: 30,
                child: spots.length < 2
                    ? const SizedBox.shrink()
                    : LineChart(LineChartData(
                        gridData: const FlGridData(show: false),
                        titlesData: const FlTitlesData(show: false),
                        borderData: FlBorderData(show: false),
                        lineTouchData: const LineTouchData(enabled: false),
                        lineBarsData: [
                          LineChartBarData(
                            spots: spots,
                            isCurved: true,
                            color: s.color,
                            barWidth: 2,
                            dotData: const FlDotData(show: false),
                          ),
                        ],
                      )),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(up ? Icons.arrow_upward : Icons.arrow_downward,
                  size: 12, color: up ? _mint : const Color(0xFFF87171)),
              const SizedBox(width: 2),
              Text('${(s.delta.abs() * 100).toStringAsFixed(1)}%',
                  style: TextStyle(
                      fontSize: 11.5,
                      color: up ? _mint : const Color(0xFFF87171))),
            ],
          ),
        ],
      ),
    );
  }

  // ----------------------------------------------------------- overview lines
  Widget _overviewCard(List<MonthlyActivityPoint> activity) {
    final maxVal = [
      1,
      for (final m in activity) m.completed + m.pending,
    ].reduce((a, b) => a > b ? a : b).toDouble();

    LineChartBarData series(List<int> values, Color color) => LineChartBarData(
          spots: [
            for (var i = 0; i < values.length; i++)
              FlSpot(i.toDouble(), values[i].toDouble()),
          ],
          isCurved: true,
          color: color,
          barWidth: 2.5,
          dotData: const FlDotData(show: false),
        );

    return _panel(
      title: 'Order and Sale Overview',
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _legendDot('Completed', _purple),
          const SizedBox(width: 10),
          _legendDot('Pending', _amber),
          const SizedBox(width: 10),
          _legendDot('Total', _cyan),
          const SizedBox(width: 10),
          _pill('Last months'),
        ],
      ),
      child: SizedBox(
        height: 240,
        child: activity.isEmpty
            ? const Center(
                child: Text('No orders yet', style: TextStyle(color: _muted)))
            : LineChart(LineChartData(
                minY: 0,
                maxY: maxVal * 1.25,
                gridData: FlGridData(
                  show: true,
                  drawVerticalLine: false,
                  getDrawingHorizontalLine: (v) =>
                      const FlLine(color: _border, strokeWidth: 1),
                ),
                borderData: FlBorderData(show: false),
                titlesData: FlTitlesData(
                  topTitles: const AxisTitles(
                      sideTitles: SideTitles(showTitles: false)),
                  rightTitles: const AxisTitles(
                      sideTitles: SideTitles(showTitles: false)),
                  leftTitles: AxisTitles(
                    sideTitles: SideTitles(
                      showTitles: true,
                      reservedSize: 32,
                      getTitlesWidget: (v, meta) => Text(
                        v.toInt().toString(),
                        style: const TextStyle(fontSize: 10, color: _muted),
                      ),
                    ),
                  ),
                  bottomTitles: AxisTitles(
                    sideTitles: SideTitles(
                      showTitles: true,
                      getTitlesWidget: (v, meta) {
                        final i = v.toInt();
                        if (i < 0 || i >= activity.length) {
                          return const SizedBox.shrink();
                        }
                        return Padding(
                          padding: const EdgeInsets.only(top: 6),
                          child: Text(activity[i].month,
                              style:
                                  const TextStyle(fontSize: 10, color: _muted)),
                        );
                      },
                    ),
                  ),
                ),
                lineTouchData: LineTouchData(
                  touchTooltipData: LineTouchTooltipData(
                    getTooltipItems: (spots) => spots
                        .map((s) => LineTooltipItem(s.y.toInt().toString(),
                            const TextStyle(color: Colors.white, fontSize: 11)))
                        .toList(),
                  ),
                ),
                lineBarsData: [
                  series([for (final m in activity) m.completed], _purple),
                  series([for (final m in activity) m.pending], _amber),
                  series([for (final m in activity) m.completed + m.pending],
                      _cyan),
                ],
              )),
      ),
    );
  }

  // -------------------------------------------------------------- side tiles
  Widget _sideTiles(int completedTotal) {
    Widget tile(String label, String value, IconData icon, Color color) =>
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: _cardAlt,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: _border),
          ),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, size: 20, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(label,
                        style: const TextStyle(fontSize: 12, color: _muted)),
                    const SizedBox(height: 4),
                    Text(value,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                            color: color)),
                  ],
                ),
              ),
            ],
          ),
        );

    return Column(
      children: [
        tile('Total Transactions', formatter.format(analytics.allTimeRevenue),
            Icons.trending_up, _purple),
        const SizedBox(height: 12),
        tile('Confirm Orders', completedTotal.toString(),
            Icons.check_circle_outline, _amber),
        const SizedBox(height: 12),
        tile('Order Delivered', analytics.allTimeOrders.toString(),
            Icons.local_shipping_outlined, _mint),
      ],
    );
  }

  // ---------------------------------------------------------------- marketing
  Widget _marketingCard(List<MonthlyActivityPoint> activity) {
    final points =
        activity.length > 6 ? activity.sublist(activity.length - 6) : activity;
    return _panel(
      title: 'Marketing',
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _legendDot('Completed', _purple),
          const SizedBox(width: 10),
          _legendDot('Pending', _mint),
        ],
      ),
      child: SizedBox(
        height: 230,
        child: points.length < 3
            ? const Center(
                child:
                    Text('Not enough history', style: TextStyle(color: _muted)))
            : RadarChart(RadarChartData(
                radarShape: RadarShape.polygon,
                radarBorderData: const BorderSide(color: _border),
                gridBorderData: const BorderSide(color: _border),
                tickBorderData: const BorderSide(color: Colors.transparent),
                ticksTextStyle:
                    const TextStyle(color: Colors.transparent, fontSize: 1),
                titlePositionPercentageOffset: 0.12,
                getTitle: (index, angle) =>
                    RadarChartTitle(text: points[index % points.length].month),
                titleTextStyle: const TextStyle(color: _muted, fontSize: 10),
                dataSets: [
                  RadarDataSet(
                    fillColor: _purple.withValues(alpha: 0.25),
                    borderColor: _purple,
                    entryRadius: 2,
                    dataEntries: [
                      for (final m in points)
                        RadarEntry(value: m.completed.toDouble()),
                    ],
                  ),
                  RadarDataSet(
                    fillColor: _mint.withValues(alpha: 0.22),
                    borderColor: _mint,
                    entryRadius: 2,
                    dataEntries: [
                      for (final m in points)
                        RadarEntry(value: m.pending.toDouble()),
                    ],
                  ),
                ],
              )),
      ),
    );
  }

  // ----------------------------------------------------------- order tracking
  Widget _orderTrackingCard() {
    final trend = analytics.revenueTrend;
    final maxVal = [
      1.0,
      for (final t in trend) t.revenue,
    ].reduce((a, b) => a > b ? a : b);
    final highlight = trend.isEmpty ? -1 : trend.length - 1;

    return _panel(
      title: 'Order Tracking',
      trailing: _pill('Last ${trend.length} days'),
      child: SizedBox(
        height: 230,
        child: trend.isEmpty
            ? const Center(
                child: Text('No revenue yet', style: TextStyle(color: _muted)))
            : BarChart(BarChartData(
                alignment: BarChartAlignment.spaceAround,
                maxY: maxVal * 1.2,
                gridData: FlGridData(
                  show: true,
                  drawVerticalLine: false,
                  getDrawingHorizontalLine: (v) =>
                      const FlLine(color: _border, strokeWidth: 1),
                ),
                borderData: FlBorderData(show: false),
                barTouchData: BarTouchData(
                  touchTooltipData: BarTouchTooltipData(
                    getTooltipItem: (g, gi, r, ri) => BarTooltipItem(
                      formatter.format(r.toY),
                      const TextStyle(color: Colors.white, fontSize: 11),
                    ),
                  ),
                ),
                titlesData: FlTitlesData(
                  topTitles: const AxisTitles(
                      sideTitles: SideTitles(showTitles: false)),
                  rightTitles: const AxisTitles(
                      sideTitles: SideTitles(showTitles: false)),
                  leftTitles: AxisTitles(
                    sideTitles: SideTitles(
                      showTitles: true,
                      reservedSize: 40,
                      getTitlesWidget: (v, meta) => Text(
                        v >= 1000
                            ? '${(v / 1000).toStringAsFixed(0)}k'
                            : v.toInt().toString(),
                        style: const TextStyle(fontSize: 10, color: _muted),
                      ),
                    ),
                  ),
                  bottomTitles: AxisTitles(
                    sideTitles: SideTitles(
                      showTitles: true,
                      getTitlesWidget: (v, meta) {
                        final i = v.toInt();
                        if (i < 0 || i >= trend.length) {
                          return const SizedBox.shrink();
                        }
                        return Padding(
                          padding: const EdgeInsets.only(top: 6),
                          child: Text(trend[i].day,
                              style:
                                  const TextStyle(fontSize: 10, color: _muted)),
                        );
                      },
                    ),
                  ),
                ),
                barGroups: [
                  for (var i = 0; i < trend.length; i++)
                    BarChartGroupData(x: i, barRods: [
                      BarChartRodData(
                        toY: trend[i].revenue,
                        width: 12,
                        color: i == highlight ? _purple : _mint,
                        borderRadius: BorderRadius.circular(6),
                      ),
                    ]),
                ],
              )),
      ),
    );
  }

  // -------------------------------------------------------------- primitives
  Widget _panel({
    required String title,
    Widget? trailing,
    required Widget child,
  }) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: _card,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(title,
                    style: const TextStyle(
                        fontSize: 15, fontWeight: FontWeight.w700)),
              ),
              if (trailing != null) Flexible(child: trailing),
            ],
          ),
          const SizedBox(height: 16),
          child,
        ],
      ),
    );
  }

  static Widget _pill(String label) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: _cardAlt,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: _border),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(label, style: const TextStyle(fontSize: 11.5, color: _muted)),
            const Icon(Icons.expand_more, size: 14, color: _muted),
          ],
        ),
      );

  static Widget _legendDot(String label, Color color) => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 5),
          Text(label, style: const TextStyle(fontSize: 11, color: _muted)),
        ],
      );
}

class _StatSpec {
  const _StatSpec(this.label, this.value, this.delta, this.color);
  final String label;
  final String value;
  final double delta;
  final Color color;
}

class _AddButton extends StatelessWidget {
  const _AddButton({this.onTap});
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: OroitDashboardHome._purple,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: const Padding(
          padding: EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.add, size: 16, color: Colors.white),
              SizedBox(width: 6),
              Text('Add Product',
                  style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w600,
                      fontSize: 13)),
            ],
          ),
        ),
      ),
    );
  }
}
