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
    this.onFilter,
    this.onTagTap,
  });

  final AnalyticsModel analytics;
  final CurrencyFormatter formatter;
  final VoidCallback? onAddProduct;
  final VoidCallback? onOpenTransactions;
  final VoidCallback? onOpenCustomers;
  final VoidCallback? onFilter;
  final void Function(String tag)? onTagTap;

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
            _DashboardHeader(
              onAddProduct: onAddProduct,
              onFilter: onFilter,
              rangeLabel: analytics.rangeLabel,
            ),
            const SizedBox(height: 16),
            _StatisticsCard(
              analytics: analytics,
              formatter: formatter,
              onFilter: onFilter,
            ),
            const SizedBox(height: 16),
            twoUp(
              _PurchaseActivityCard(analytics: analytics),
              _PopularTagsCard(tags: analytics.popularTags, onTagTap: onTagTap),
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
  const _DashboardHeader({this.onAddProduct, this.onFilter, this.rangeLabel});

  final VoidCallback? onAddProduct;
  final VoidCallback? onFilter;
  final String? rangeLabel;

  @override
  Widget build(BuildContext context) {
    final title = Text(
      'Dashboard',
      maxLines: 1,
      softWrap: false,
      overflow: TextOverflow.ellipsis,
      style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
    );
    final filterBtn = OutlinedButton.icon(
      onPressed: onFilter,
      icon: const Icon(Icons.filter_list, size: 18),
      label: Text(rangeLabel == null || rangeLabel!.isEmpty
          ? 'Filter'
          : 'Filter · ${rangeLabel!}'),
    );
    final addBtn = ElevatedButton.icon(
      onPressed: onAddProduct,
      icon: const Icon(Icons.add, size: 18),
      label: const Text('Add Product'),
    );

    return LayoutBuilder(
      builder: (context, c) {
        // Narrow: stack the title above a wrapping button row so "Dashboard"
        // is never squeezed into a one-glyph-wide column.
        if (c.maxWidth < 520) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              title,
              const SizedBox(height: 10),
              Wrap(spacing: 12, runSpacing: 8, children: [filterBtn, addBtn]),
            ],
          );
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Expanded(child: title),
            filterBtn,
            const SizedBox(width: 12),
            addBtn,
          ],
        );
      },
    );
  }
}

// ---------------------------------------------------------------------------
// Statistics
// ---------------------------------------------------------------------------

class _StatisticsCard extends StatelessWidget {
  const _StatisticsCard(
      {required this.analytics, required this.formatter, this.onFilter});

  final AnalyticsModel analytics;
  final CurrencyFormatter formatter;
  final VoidCallback? onFilter;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _PanelHeader(
            'Statistics',
            trailing: InkWell(
              onTap: onFilter,
              borderRadius: BorderRadius.circular(8),
              child: _Pill(analytics.rangeLabel),
            ),
          ),
          const SizedBox(height: 18),
          LayoutBuilder(
            builder: (context, c) {
              final wide = c.maxWidth >= 640;

              final card1 = _PoshMetricTile(
                icon: Icons.account_balance_wallet_outlined,
                iconColor: scheme.primary,
                iconBg: scheme.primary.withValues(alpha: 0.12),
                label: 'Total Earnings',
                value: formatter.format(analytics.rangeRevenue),
                badge: _DeltaChip(analytics.revenueDelta),
              );

              final card2 = _PoshMetricTile(
                icon: Icons.shopping_bag_outlined,
                iconColor: const Color(0xFF3B82F6),
                iconBg: const Color(0xFF3B82F6).withValues(alpha: 0.12),
                label: 'Number of Sales',
                value: analytics.rangeOrders.toString(),
                badge: _DeltaChip(analytics.ordersDelta),
              );

              final card3 = _PoshMetricTile(
                icon: Icons.inventory_2_outlined,
                iconColor: const Color(0xFF8B5CF6),
                iconBg: const Color(0xFF8B5CF6).withValues(alpha: 0.12),
                label: 'Catalogue',
                value: '${analytics.productCount} items',
                subtitle: '${analytics.customerCount} customers',
              );

              final card4 = _PoshMetricTile(
                icon: Icons.people_outline,
                iconColor: const Color(0xFF0EA5E9),
                iconBg: const Color(0xFF0EA5E9).withValues(alpha: 0.12),
                label: 'Customers',
                value: '${analytics.customerCount}',
                subtitle: 'Registered',
              );

              if (wide) {
                return Row(
                  children: [
                    Expanded(child: card1),
                    const SizedBox(width: 12),
                    Expanded(child: card2),
                    const SizedBox(width: 12),
                    Expanded(child: card3),
                    const SizedBox(width: 12),
                    Expanded(child: card4),
                  ],
                );
              }

              // Responsive 2x2 grid on mobile
              return Column(
                children: [
                  Row(
                    children: [
                      Expanded(child: card1),
                      const SizedBox(width: 10),
                      Expanded(child: card2),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(child: card3),
                      const SizedBox(width: 10),
                      Expanded(child: card4),
                    ],
                  ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}

class _PoshMetricTile extends StatelessWidget {
  const _PoshMetricTile({
    required this.icon,
    required this.iconColor,
    required this.iconBg,
    required this.label,
    required this.value,
    this.badge,
    this.subtitle,
  });

  final IconData icon;
  final Color iconColor;
  final Color iconBg;
  final String label;
  final String value;
  final Widget? badge;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final tileBg = isDark
        ? scheme.surfaceContainerHighest.withValues(alpha: 0.3)
        : const Color(0xFFF8FAFC);
    final borderColor = isDark
        ? scheme.outlineVariant.withValues(alpha: 0.4)
        : const Color(0xFFE2E8F0);

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: tileBg,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: borderColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Container(
                width: 32,
                height: 32,
                decoration: BoxDecoration(
                  color: iconBg,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(icon, size: 17, color: iconColor),
              ),
              const Spacer(),
              if (badge != null) badge!,
            ],
          ),
          const SizedBox(height: 10),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.3,
            ),
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              Expanded(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w500,
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ),
              if (subtitle != null) ...[
                const SizedBox(width: 4),
                Text(
                  subtitle!,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 10.5,
                    color: scheme.onSurfaceVariant.withValues(alpha: 0.8),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
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
      10.0,
      for (final m in data) m.completed.toDouble(),
      for (final m in data) m.pending.toDouble(),
    ].reduce((a, b) => a > b ? a : b);
    final year = data.isNotEmpty ? data.last.year.toString() : '';

    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          LayoutBuilder(
            builder: (context, c) {
              final isNarrow = c.maxWidth < 460;
              const title = Text(
                'Purchase Activity',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
              );
              final legend = Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  _LegendDot('Completed', completedColor),
                  const SizedBox(width: 10),
                  _LegendDot('Pending', pendingColor),
                ],
              );
              final pill = _Pill(year);

              if (isNarrow) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Expanded(child: title),
                        pill,
                      ],
                    ),
                    const SizedBox(height: 8),
                    legend,
                  ],
                );
              }

              return Row(
                children: [
                  const Expanded(child: title),
                  legend,
                  const SizedBox(width: 12),
                  pill,
                ],
              );
            },
          ),
          const SizedBox(height: 18),
          SizedBox(
            height: 200,
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
  const _PopularTagsCard({required this.tags, this.onTagTap});

  final List<String> tags;
  final void Function(String tag)? onTagTap;

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
                  Builder(builder: (context) {
                    final clean =
                        t.replaceAll(RegExp(r'\s+'), '').toLowerCase();
                    return Material(
                      color: scheme.surfaceContainerHighest,
                      borderRadius: BorderRadius.circular(8),
                      child: InkWell(
                        onTap: onTagTap == null ? null : () => onTagTap!(t),
                        borderRadius: BorderRadius.circular(8),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 12, vertical: 7),
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: scheme.outlineVariant),
                          ),
                          child: Text('#$clean',
                              style: TextStyle(
                                  fontSize: 12.5, color: scheme.onSurface)),
                        ),
                      ),
                    );
                  }),
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
          else
            LayoutBuilder(
              builder: (context, c) {
                final isNarrow = c.maxWidth < 480;

                if (isNarrow) {
                  return Column(
                    children: [
                      for (final r in rows)
                        Container(
                          padding: const EdgeInsets.symmetric(vertical: 10),
                          decoration: BoxDecoration(
                            border: Border(top: BorderSide(color: scheme.outlineVariant)),
                          ),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.center,
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      r.reference,
                                      style: const TextStyle(
                                          fontWeight: FontWeight.w600, fontSize: 13),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      r.customer.isNotEmpty
                                          ? '${r.customer} · ${r.date}'
                                          : r.date,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                          fontSize: 11.5,
                                          color: scheme.onSurfaceVariant),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(width: 8),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                    formatter.format(r.amount),
                                    style: const TextStyle(
                                        fontWeight: FontWeight.w700, fontSize: 13),
                                  ),
                                  const SizedBox(height: 3),
                                  _StatusBadge(r.status, completed: r.isCompleted),
                                ],
                              ),
                            ],
                          ),
                        ),
                    ],
                  );
                }

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

                return Column(
                  children: [
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
                );
              },
            ),
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
          maxLines: 1,
          softWrap: false,
          overflow: TextOverflow.visible,
          style: TextStyle(
              fontSize: 11.5, fontWeight: FontWeight.w600, color: fg)),
    );
  }
}
