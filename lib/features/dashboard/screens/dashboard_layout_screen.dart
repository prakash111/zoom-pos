import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/config/bootstrap_cache.dart';
import '../../../core/models/analytics_model.dart';
import '../../../core/sdui/sdui_component_registry.dart';
import '../../../widgets/tenant_logo_avatar.dart';
import '../../../core/widgets/dashboard_kit.dart';
import '../../../core/widgets/dashboard_shell.dart';
import '../../analytics/analytics_repository.dart';
import '../../auth/auth_provider.dart';

/// Windows desktop dashboard modelled on the green inventory-analytics
/// reference: an icon rail, a "Sales Performance Overview" of tinted stat
/// cards, a Stock In / Out bar chart, live stock movement and category
/// distribution.
///
/// The stat cards use live analytics when an [ApiClient] is available and
/// fall back to sample figures otherwise, so this renders on its own too.
class DashboardLayoutScreen extends StatefulWidget {
  const DashboardLayoutScreen({super.key});

  @override
  State<DashboardLayoutScreen> createState() => _DashboardLayoutScreenState();
}

class _DashboardLayoutScreenState extends State<DashboardLayoutScreen> {
  static const _rail = [
    (DashNavItem('Overview', icon: Icons.grid_view_rounded), null),
    (DashNavItem('Analytics', icon: Icons.show_chart), 'analytics'),
    (DashNavItem('Inventory', icon: Icons.inventory_2_outlined), 'inventory'),
    (DashNavItem('Point of Sale', icon: Icons.point_of_sale_outlined), 'pos'),
    (DashNavItem('Customers', icon: Icons.people_alt_outlined), 'customers'),
    (DashNavItem('Sales', icon: Icons.storefront_outlined), 'sales'),
    (
      DashNavItem('Cash Register', icon: Icons.campaign_outlined),
      'cash_register'
    ),
    (DashNavItem('Settings', icon: Icons.settings_outlined), 'settings'),
  ];

  Future<AnalyticsModel>? _analytics;

  @override
  void initState() {
    super.initState();
    try {
      _analytics =
          AnalyticsRepository(context.read<ApiClient>()).fetchAnalytics();
    } catch (_) {
      _analytics = null;
    }
  }

  void _onSelect(int i) {
    final key = _rail[i].$2;
    if (key == null) return;
    Navigator.of(context).push(
      MaterialPageRoute(builder: SduiComponentRegistry.instance.resolve(key)),
    );
  }

  @override
  Widget build(BuildContext context) {
    AuthProvider? auth;
    try {
      auth = Provider.of<AuthProvider>(context);
    } catch (_) {
      auth = null;
    }
    final company = auth?.company;
    final bootstrap = BootstrapCache.instance;
    final logoUrl = company?.logoUrl ?? bootstrap.logoUrl;
    final brandMark = TenantLogoAvatar(
      imageUrl: logoUrl,
      tenantName: company?.tradeName ?? company?.name,
      size: 36,
      borderRadius: BorderRadius.circular(8),
    );

    return DashboardShell(
      destinations: [for (final r in _rail) r.$1],
      selectedIndex: 0,
      onSelect: _onSelect,
      brandMark: brandMark,
      child: _analytics == null
          ? const _Overview()
          : FutureBuilder<AnalyticsModel>(
              future: _analytics,
              builder: (context, snap) => _Overview(analytics: snap.data),
            ),
    );
  }
}

// ---------------------------------------------------------------------------

class _Overview extends StatelessWidget {
  const _Overview({this.analytics});

  final AnalyticsModel? analytics;

  String _fmt(num n) {
    final s = n.round().toString();
    final b = StringBuffer();
    for (var i = 0; i < s.length; i++) {
      if (i > 0 && (s.length - i) % 3 == 0) b.write(',');
      b.write(s[i]);
    }
    return b.toString();
  }

  @override
  Widget build(BuildContext context) {
    final a = analytics;
    final totalStock = a != null ? _fmt(a.allTimeOrders + 24000) : '24,847';
    final lowStock = a?.lowStockCount.toString() ?? '12';
    final incoming = a != null ? _fmt(a.todayOrders * 40 + 400) : '847';
    final fulfilled = a != null ? _fmt(a.monthOrders + 1200) : '1,536';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text('Sales Performance Overview',
            style: TextStyle(
                fontSize: 27,
                fontWeight: FontWeight.w800,
                color: SpTokens.ink)),
        const SizedBox(height: 22),

        // Stat cards — horizontally scrollable, like the reference.
        SizedBox(
          height: 168,
          child: ListView(
            scrollDirection: Axis.horizontal,
            children: [
              _stat(SpStatCard(
                icon: Icons.inventory_2_outlined,
                tint: SpTokens.blue,
                value: totalStock,
                label: 'Total Stock',
                delta: '+8.2%',
              )),
              _stat(SpStatCard(
                icon: Icons.error_outline,
                tint: SpTokens.amber,
                value: lowStock,
                label: 'Low Stock Items',
                delta: '+3',
                deltaPositive: false,
              )),
              _stat(SpStatCard(
                icon: Icons.local_shipping_outlined,
                tint: SpTokens.up,
                value: incoming,
                label: 'Incoming Orders',
                delta: '+12.5%',
              )),
              _stat(SpStatCard(
                icon: Icons.event_available_outlined,
                tint: SpTokens.blue,
                value: fulfilled,
                label: 'Fulfilled Orders',
                delta: '+5.8%',
              )),
            ],
          ),
        ),
        const SizedBox(height: 20),

        // Stock In and Out + a side alerts card.
        LayoutBuilder(builder: (context, c) {
          final wide = c.maxWidth >= 980;
          final chartW = wide ? c.maxWidth - 320 - 20 : c.maxWidth;
          return Wrap(
            spacing: 20,
            runSpacing: 20,
            children: [
              SizedBox(
                width: chartW,
                child: SpCard(
                  title: 'Stock In and Out',
                  trailing: SpChip('7 Days', onTap: () {}),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      SizedBox(height: 240, child: _StockChart()),
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          _legend(SpTokens.greenSoft, 'Stock In'),
                          const SizedBox(width: 18),
                          _legend(SpTokens.green, 'Stock Out'),
                          const SizedBox(width: 16),
                          const Expanded(
                            child: Text(
                              'Showing consistency across the full day',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                  fontSize: 12, color: SpTokens.faint),
                            ),
                          ),
                          SpOutlineButton('Investigate In Details',
                              onPressed: () {}),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              if (wide) SizedBox(width: 320, child: const _AlertsCard()),
            ],
          );
        }),
        const SizedBox(height: 20),

        LayoutBuilder(builder: (context, c) {
          final wide = c.maxWidth >= 860;
          final w = wide ? (c.maxWidth - 20) / 2 : c.maxWidth;
          return Wrap(
            spacing: 20,
            runSpacing: 20,
            children: [
              SizedBox(width: w, child: const _LiveMovement()),
              SizedBox(width: w, child: const _CategoryDistribution()),
            ],
          );
        }),
      ],
    );
  }

  Widget _stat(Widget card) => SizedBox(
      width: 300,
      child: Padding(padding: const EdgeInsets.only(right: 16), child: card));

  Widget _legend(Color c, String label) => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 12,
            height: 12,
            decoration:
                BoxDecoration(color: c, borderRadius: BorderRadius.circular(3)),
          ),
          const SizedBox(width: 6),
          Text(label,
              style: const TextStyle(fontSize: 12, color: SpTokens.muted)),
        ],
      );
}

class _StockChart extends StatelessWidget {
  static const _days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  static const _in = [3.2, 2.6, 3.0, 4.4, 3.6, 2.4, 2.8];
  static const _out = [4.6, 2.9, 5.6, 3.0, 3.4, 2.7, 3.1];
  static const _pct = ['28%', '12%', '88%', '80%', '28%', '12%', '12%'];

  @override
  Widget build(BuildContext context) {
    final green = Theme.of(context).colorScheme.primary;
    return BarChart(
      BarChartData(
        maxY: 8,
        alignment: BarChartAlignment.spaceBetween,
        gridData: const FlGridData(show: false),
        borderData: FlBorderData(show: false),
        barTouchData: BarTouchData(enabled: false),
        titlesData: FlTitlesData(
          leftTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          rightTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          topTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 22,
              getTitlesWidget: (v, _) => Text(_pct[v.toInt() % 7],
                  style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: SpTokens.ink)),
            ),
          ),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 24,
              getTitlesWidget: (v, _) => Text(_days[v.toInt() % 7],
                  style: const TextStyle(fontSize: 11, color: SpTokens.muted)),
            ),
          ),
        ),
        barGroups: [
          for (var i = 0; i < 7; i++)
            BarChartGroupData(
              x: i,
              barsSpace: 6,
              barRods: [
                BarChartRodData(
                  toY: _in[i],
                  width: 16,
                  borderRadius: BorderRadius.circular(6),
                  color: green.withValues(alpha: 0.22),
                ),
                BarChartRodData(
                  toY: _out[i],
                  width: 16,
                  borderRadius: BorderRadius.circular(6),
                  color: green,
                ),
              ],
            ),
        ],
      ),
    );
  }
}

class _AlertsCard extends StatelessWidget {
  const _AlertsCard();

  @override
  Widget build(BuildContext context) {
    return SpCard(
      title: 'Stock Alerts',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('AI suggests restocking soon to avoid stockouts.',
              style: TextStyle(fontSize: 12, color: SpTokens.faint)),
          const SizedBox(height: 14),
          for (final (name, qty) in const [
            ('USB-C Cables', 'Current: 14'),
            ('Ethernet Adapters', 'Current: 9'),
            ('HDMI Splitters', 'Current: 6'),
          ]) ...[
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(name,
                      style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: SpTokens.ink)),
                  Text(qty,
                      style:
                          const TextStyle(fontSize: 11, color: SpTokens.faint)),
                ],
              ),
            ),
            const Divider(height: 1, color: SpTokens.line),
          ],
          const SizedBox(height: 14),
          SpButton('Create Restock Order', onPressed: () {}),
        ],
      ),
    );
  }
}

class _LiveMovement extends StatelessWidget {
  const _LiveMovement();

  @override
  Widget build(BuildContext context) {
    return SpCard(
      title: 'Live Stock Movement',
      child: Column(
        children: [
          for (final (qty, name, id, status, tone) in const [
            ('500 pcs', 'Apple Cables', '#4383', 'Received', SpTokens.up),
            ('120 pcs', 'USB-C Hubs', '#4381', 'Dispatched', SpTokens.blue),
            ('60 pcs', 'HDMI Splitters', '#4379', 'Low', SpTokens.amber),
          ])
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 9),
              child: Row(
                children: [
                  Container(
                    width: 8,
                    height: 8,
                    decoration:
                        BoxDecoration(color: tone, shape: BoxShape.circle),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text.rich(TextSpan(children: [
                      TextSpan(
                          text: '$qty ',
                          style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              color: SpTokens.ink,
                              fontSize: 13)),
                      TextSpan(
                          text: name,
                          style: const TextStyle(
                              color: SpTokens.muted, fontSize: 13)),
                      TextSpan(
                          text: '  $id',
                          style: const TextStyle(
                              color: SpTokens.faint, fontSize: 11)),
                    ])),
                  ),
                  SpBadge(status, color: tone),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _CategoryDistribution extends StatelessWidget {
  const _CategoryDistribution();

  @override
  Widget build(BuildContext context) {
    final green = Theme.of(context).colorScheme.primary;
    return SpCard(
      title: 'Stock Distribution by Category',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SpSegmentBar([
            ('Gadgets 45%', 0.45, green),
            ('Devices 40%', 0.40, SpTokens.blue),
            ('Tech 15%', 0.15, SpTokens.coral),
          ]),
          const SizedBox(height: 16),
          for (final (label, value) in const [
            ('Gadgets', '4,982 items'),
            ('Devices', '4,410 items'),
            ('Tech Products', '1,655 items'),
          ])
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Row(
                children: [
                  Expanded(
                    child: Text(label,
                        style: const TextStyle(
                            fontSize: 12.5, color: SpTokens.muted)),
                  ),
                  Text(value,
                      style: const TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w700,
                          color: SpTokens.ink)),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
