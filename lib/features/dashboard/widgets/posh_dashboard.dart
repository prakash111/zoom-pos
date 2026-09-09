import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../../../core/config/theme.dart';
import '../../../core/models/analytics_model.dart';
import '../../../core/utils/currency_formatter.dart';

/// The redesigned dashboard home surface ("PoshPointHub" reference layout),
/// bound entirely to the live `GET /analytics` payload:
///
///  * Total balance card + revenue sparkline
///  * Statistics (earnings / sales / catalogue) with month-over-month deltas
///  * Purchase Activity grouped bar chart (completed vs pending, 9 months)
///  * Popular Tags (top catalogue categories)
///  * Recent Customers
///  * Latest Transactions table
class PoshDashboardHome extends StatelessWidget {
  const PoshDashboardHome({
    super.key,
    required this.analytics,
    required this.formatter,
    this.onAddProduct,
    this.onOpenTransactions,
    this.onOpenCustomers,
  });

  final AnalyticsModel analytics;
  final CurrencyFormatter formatter;
  final VoidCallback? onAddProduct;
  final VoidCallback? onOpenTransactions;
  final VoidCallback? onOpenCustomers;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final wide = constraints.maxWidth >= 900;

        Widget twoUp(Widget a, Widget b, {int flexA = 1, int flexB = 1}) {
          if (!wide) {
            return Column(children: [a, const SizedBox(height: 16), b]);
          }
          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(flex: flexA, child: a),
              const SizedBox(width: 16),
              Expanded(flex: flexB, child: b),
            ],
          );
        }

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _DashboardHeader(onAddProduct: onAddProduct),
            const SizedBox(height: 16),
            twoUp(
              _TotalBalanceCard(analytics: analytics, formatter: formatter),
              _StatisticsCard(analytics: analytics, formatter: formatter),
              flexA: 2,
              flexB: 3,
            ),
            const SizedBox(height: 16),
            twoUp(
              _PurchaseActivityCard(analytics: analytics),
              _PopularTagsCard(tags: analytics.popularTags),
              flexA: 3,
              flexB: 2,
            ),
            const SizedBox(height: 16),
            twoUp(
              _RecentContactsCard(
                contacts: analytics.recentCustomers,
                onViewAll: onOpenCustomers,
              ),
              _TransactionsCard(
                rows: analytics.recentTransactions,
                formatter: formatter,
                onViewAll: onOpenTransactions,
              ),
              flexA: 2,
              flexB: 3,
            ),
          ],
        );
      },
    );
  }
}

// ---------------------------------------------------------------------------
// Shared pieces
// ---------------------------------------------------------------------------

class _Panel extends StatelessWidget {
  const _Panel({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: scheme.outlineVariant),
      ),
      child: child,
    );
  }
}

class _PanelHeader extends StatelessWidget {
  const _PanelHeader(this.title, {this.trailing});

  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
          ),
        ),
        if (trailing != null) trailing!,
      ],
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: scheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: scheme.outlineVariant),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(label,
              style: TextStyle(fontSize: 12, color: scheme.onSurfaceVariant)),
          Icon(Icons.expand_more, size: 15, color: scheme.onSurfaceVariant),
        ],
      ),
    );
  }
}

class _DeltaChip extends StatelessWidget {
  const _DeltaChip(this.fraction);

  final double fraction;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final up = fraction >= 0;
    final color = up ? scheme.successAccent : scheme.dangerAccent;
    final pct = (fraction.abs() * 100).toStringAsFixed(2);
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(up ? Icons.north_east : Icons.south_east, size: 13, color: color),
        const SizedBox(width: 2),
        Text('${up ? '+' : '-'}$pct%',
            style: TextStyle(
                fontSize: 12, fontWeight: FontWeight.w600, color: color)),
      ],
    );
  }
}

// ---------------------------------------------------------------------------
// Header
// ---------------------------------------------------------------------------

class _DashboardHeader extends StatelessWidget {
  const _DashboardHeader({this.onAddProduct});

  final VoidCallback? onAddProduct;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const Expanded(
          child: Text('Dashboard',
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
        ),
        OutlinedButton.icon(
          onPressed: null,
          icon: const Icon(Icons.filter_list, size: 18),
          label: const Text('Filter'),
        ),
        const SizedBox(width: 12),
        ElevatedButton.icon(
          onPressed: onAddProduct,
          icon: const Icon(Icons.add, size: 18),
          label: const Text('Add Product'),
        ),
      ],
    );
  }
}

// ---------------------------------------------------------------------------
// Total balance
// ---------------------------------------------------------------------------

class _TotalBalanceCard extends StatelessWidget {
  const _TotalBalanceCard({required this.analytics, required this.formatter});

  final AnalyticsModel analytics;
  final CurrencyFormatter formatter;

  @override
  Widget build(BuildContext context) {
    final now = TimeOfDay.now();
    final stamp = now.format(context);
    final spots = <FlSpot>[
      for (var i = 0; i < analytics.revenueTrend.length; i++)
        FlSpot(i.toDouble(), analytics.revenueTrend[i].revenue),
    ];
    const line = Color(0xFF22D3EE); // cyan, as in the reference

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppTheme.darkCard,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Expanded(
                child: Text('Total balance',
                    style: TextStyle(
                        color: Colors.white,
                        fontSize: 15,
                        fontWeight: FontWeight.w700)),
              ),
              Text(
                formatter.format(analytics.allTimeRevenue),
                style: const TextStyle(
                    color: Colors.white,
                    fontSize: 22,
                    fontWeight: FontWeight.w800),
              ),
            ],
          ),
          const SizedBox(height: 2),
          Text('Last updated $stamp',
              style: const TextStyle(color: Colors.white54, fontSize: 11)),
          const SizedBox(height: 16),
          SizedBox(
            height: 96,
            child: spots.length < 2
                ? const Center(
                    child: Text('No revenue yet',
                        style: TextStyle(color: Colors.white38, fontSize: 12)))
                : LineChart(
                    LineChartData(
                      gridData: const FlGridData(show: false),
                      titlesData: const FlTitlesData(show: false),
                      borderData: FlBorderData(show: false),
                      lineTouchData: const LineTouchData(enabled: false),
                      minY: 0,
                      lineBarsData: [
                        LineChartBarData(
                          spots: spots,
                          isCurved: true,
                          color: line,
                          barWidth: 2.5,
                          dotData: const FlDotData(show: false),
                          belowBarData: BarAreaData(
                            show: true,
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [
                                line.withValues(alpha: 0.25),
                                line.withValues(alpha: 0),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Statistics
// ---------------------------------------------------------------------------

class _StatisticsCard extends StatelessWidget {
  const _StatisticsCard({required this.analytics, required this.formatter});

  final AnalyticsModel analytics;
  final CurrencyFormatter formatter;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final metrics = <(String, String, Widget?)>[
      (
        'Total Earnings',
        formatter.format(analytics.monthRevenue),
        _DeltaChip(analytics.revenueDelta),
      ),
      (
        'Number of Sales',
        analytics.monthOrders.toString(),
        _DeltaChip(analytics.ordersDelta),
      ),
      (
        'Catalogue',
        '${analytics.productCount} items',
        Text('${analytics.customerCount} customers',
            style: TextStyle(fontSize: 12, color: scheme.onSurfaceVariant)),
      ),
    ];

    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _PanelHeader('Statistics', trailing: const _Pill('This Month')),
          const SizedBox(height: 18),
          LayoutBuilder(
            builder: (context, c) {
              final narrow = c.maxWidth < 380;
              final children = [
                for (var i = 0; i < metrics.length; i++) ...[
                  if (i > 0)
                    narrow
                        ? const SizedBox(height: 14)
                        : Container(
                            width: 1,
                            height: 46,
                            color: scheme.outlineVariant,
                            margin: const EdgeInsets.symmetric(horizontal: 14),
                          ),
                  Expanded(
                    flex: narrow ? 0 : 1,
                    child: _MetricBlock(
                      label: metrics[i].$1,
                      value: metrics[i].$2,
                      trailing: metrics[i].$3,
                    ),
                  ),
                ],
              ];
              return narrow
                  ? Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: children)
                  : Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: children,
                    );
            },
          ),
        ],
      ),
    );
  }
}

class _MetricBlock extends StatelessWidget {
  const _MetricBlock({required this.label, required this.value, this.trailing});

  final String label;
  final String value;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(label,
            style: TextStyle(fontSize: 12.5, color: scheme.onSurfaceVariant)),
        const SizedBox(height: 6),
        Text(value,
            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
            overflow: TextOverflow.ellipsis),
        const SizedBox(height: 6),
        if (trailing != null) trailing!,
      ],
    );
  }
}

// ---------------------------------------------------------------------------
// Purchase activity
// ---------------------------------------------------------------------------

class _PurchaseActivityCard extends StatelessWidget {
  const _PurchaseActivityCard({required this.analytics});

  final AnalyticsModel analytics;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final data = analytics.monthlyActivity;
    final completedColor = scheme.primary;
    final pendingColor = scheme.warningAccent;
    final maxVal = [
      1,
      for (final m in data) m.completed,
      for (final m in data) m.pending,
    ].reduce((a, b) => a > b ? a : b).toDouble();
    final year = data.isNotEmpty ? data.last.year.toString() : '';

    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _PanelHeader(
            'Purchase Activity',
            trailing: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                _LegendDot('Completed', completedColor),
                const SizedBox(width: 12),
                _LegendDot('Pending', pendingColor),
                const SizedBox(width: 12),
                _Pill(year),
              ],
            ),
          ),
          const SizedBox(height: 18),
          SizedBox(
            height: 220,
            child: data.isEmpty
                ? Center(
                    child: Text('No purchase activity yet',
                        style: TextStyle(color: scheme.onSurfaceVariant)))
                : BarChart(
                    BarChartData(
                      alignment: BarChartAlignment.spaceAround,
                      maxY: maxVal * 1.2,
                      barTouchData: BarTouchData(
                        touchTooltipData: BarTouchTooltipData(
                          getTooltipItem: (group, gi, rod, ri) =>
                              BarTooltipItem(
                            '${rod.toY.toInt()}',
                            const TextStyle(color: Colors.white, fontSize: 11),
                          ),
                        ),
                      ),
                      gridData: FlGridData(
                        show: true,
                        drawVerticalLine: false,
                        horizontalInterval:
                            (maxVal / 4).ceilToDouble().clamp(1, 1e9),
                        getDrawingHorizontalLine: (v) => FlLine(
                            color: scheme.outlineVariant, strokeWidth: 1),
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
                            reservedSize: 30,
                            interval: (maxVal / 4).ceilToDouble().clamp(1, 1e9),
                            getTitlesWidget: (v, meta) => Text(
                              v.toInt().toString(),
                              style: TextStyle(
                                  fontSize: 10, color: scheme.onSurfaceVariant),
                            ),
                          ),
                        ),
                        bottomTitles: AxisTitles(
                          sideTitles: SideTitles(
                            showTitles: true,
                            getTitlesWidget: (v, meta) {
                              final i = v.toInt();
                              if (i < 0 || i >= data.length) {
                                return const SizedBox.shrink();
                              }
                              return Padding(
                                padding: const EdgeInsets.only(top: 6),
                                child: Text(data[i].month,
                                    style: TextStyle(
                                        fontSize: 10,
                                        color: scheme.onSurfaceVariant)),
                              );
                            },
                          ),
                        ),
                      ),
                      barGroups: [
                        for (var i = 0; i < data.length; i++)
                          BarChartGroupData(
                            x: i,
                            barsSpace: 3,
                            barRods: [
                              BarChartRodData(
                                toY: data[i].completed.toDouble(),
                                color: completedColor,
                                width: 7,
                                borderRadius: const BorderRadius.vertical(
                                    top: Radius.circular(3)),
                              ),
                              BarChartRodData(
                                toY: data[i].pending.toDouble(),
                                color: pendingColor,
                                width: 7,
                                borderRadius: const BorderRadius.vertical(
                                    top: Radius.circular(3)),
                              ),
                            ],
                          ),
                      ],
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}

class _LegendDot extends StatelessWidget {
  const _LegendDot(this.label, this.color);

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 9,
          height: 9,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 6),
        Text(label,
            style: TextStyle(
                fontSize: 12,
                color: Theme.of(context).colorScheme.onSurfaceVariant)),
      ],
    );
  }
}

// ---------------------------------------------------------------------------
// Popular tags
// ---------------------------------------------------------------------------

class _PopularTagsCard extends StatelessWidget {
  const _PopularTagsCard({required this.tags});

  final List<String> tags;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _PanelHeader('Popular Tags'),
          const SizedBox(height: 16),
          if (tags.isEmpty)
            Text('Add categories to your products to see tags here.',
                style: TextStyle(color: scheme.onSurfaceVariant))
          else
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final t in tags)
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
                    decoration: BoxDecoration(
                      color: scheme.surfaceContainerHighest,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: scheme.outlineVariant),
                    ),
                    child: Text(
                      '#${t.replaceAll(RegExp(r'\s+'), '').toLowerCase()}',
                      style: TextStyle(fontSize: 12.5, color: scheme.onSurface),
                    ),
                  ),
              ],
            ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Recent contacts
// ---------------------------------------------------------------------------

class _RecentContactsCard extends StatelessWidget {
  const _RecentContactsCard({required this.contacts, this.onViewAll});

  final List<RecentContactEntry> contacts;
  final VoidCallback? onViewAll;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _PanelHeader(
            'Recent Customers',
            trailing: onViewAll == null
                ? null
                : TextButton(
                    onPressed: onViewAll, child: const Text('View All')),
          ),
          const SizedBox(height: 6),
          if (contacts.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Text('No customers yet.',
                  style: TextStyle(color: scheme.onSurfaceVariant)),
            )
          else
            for (final c in contacts)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 18,
                      backgroundColor: scheme.primary.withValues(alpha: 0.14),
                      child: Text(c.initials,
                          style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: scheme.primary)),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(c.name,
                              style:
                                  const TextStyle(fontWeight: FontWeight.w600)),
                          Text(c.detail,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                  fontSize: 12.5,
                                  color: scheme.onSurfaceVariant)),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(c.time,
                        style: TextStyle(
                            fontSize: 11, color: scheme.onSurfaceVariant)),
                  ],
                ),
              ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Latest transactions
// ---------------------------------------------------------------------------

class _TransactionsCard extends StatelessWidget {
  const _TransactionsCard({
    required this.rows,
    required this.formatter,
    this.onViewAll,
  });

  final List<TransactionEntry> rows;
  final CurrencyFormatter formatter;
  final VoidCallback? onViewAll;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    Widget headCell(String t,
            {TextAlign align = TextAlign.left, int flex = 1}) =>
        Expanded(
          flex: flex,
          child: Text(t,
              textAlign: align,
              style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: scheme.onSurfaceVariant)),
        );

    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _PanelHeader(
            'Latest Transactions',
            trailing: onViewAll == null
                ? null
                : TextButton(
                    onPressed: onViewAll, child: const Text('View All')),
          ),
          const SizedBox(height: 12),
          if (rows.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 20),
              child: Text('No transactions yet.',
                  style: TextStyle(color: scheme.onSurfaceVariant)),
            )
          else ...[
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                children: [
                  headCell('Transaction', flex: 3),
                  headCell('Date', flex: 2),
                  headCell('Status', flex: 2),
                  headCell('Amount', align: TextAlign.right, flex: 2),
                ],
              ),
            ),
            for (final r in rows)
              Container(
                padding: const EdgeInsets.symmetric(vertical: 10),
                decoration: BoxDecoration(
                  border: Border(top: BorderSide(color: scheme.outlineVariant)),
                ),
                child: Row(
                  children: [
                    Expanded(
                      flex: 3,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(r.reference,
                              style: const TextStyle(
                                  fontWeight: FontWeight.w600, fontSize: 13),
                              overflow: TextOverflow.ellipsis),
                          if (r.customer.isNotEmpty)
                            Text(r.customer,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                    fontSize: 11.5,
                                    color: scheme.onSurfaceVariant)),
                        ],
                      ),
                    ),
                    Expanded(
                      flex: 2,
                      child: Text(r.date,
                          style: TextStyle(
                              fontSize: 12.5, color: scheme.onSurfaceVariant)),
                    ),
                    Expanded(
                      flex: 2,
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: _StatusBadge(r.status, completed: r.isCompleted),
                      ),
                    ),
                    Expanded(
                      flex: 2,
                      child: Text(
                        formatter.format(r.amount),
                        textAlign: TextAlign.right,
                        style: const TextStyle(
                            fontWeight: FontWeight.w700, fontSize: 13),
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ],
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge(this.label, {required this.completed});

  final String label;
  final bool completed;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final bg = completed ? scheme.successContainer : scheme.warningContainer;
    final fg =
        completed ? scheme.onSuccessContainer : scheme.onWarningContainer;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration:
          BoxDecoration(color: bg, borderRadius: BorderRadius.circular(20)),
      child: Text(label,
          style: TextStyle(
              fontSize: 11.5, fontWeight: FontWeight.w600, color: fg)),
    );
  }
}
