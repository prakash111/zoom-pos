import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../../../core/widgets/dashboard_kit.dart';
import '../../../core/widgets/dashboard_shell.dart';

/// Windows desktop dashboard layout modelled on the "POWRSALE" reference:
/// a white left sidebar, a violet greeting hero, a grid of soft white stat
/// cards with charts, and a profile / messages right rail.
///
/// Self-contained and provider-free — preview it or drop it on a route as
/// is, then wire real data into the cards.
class DashboardLayoutScreen extends StatefulWidget {
  const DashboardLayoutScreen({super.key});

  @override
  State<DashboardLayoutScreen> createState() => _DashboardLayoutScreenState();
}

class _DashboardLayoutScreenState extends State<DashboardLayoutScreen> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    return DashboardShell(
      brand: 'Powrsale',
      shopName: 'Techshop',
      selectedIndex: _tab,
      onSelect: (i) => setState(() => _tab = i),
      onCreate: () {},
      onLogout: () {},
      destinations: const [
        DashNavItem('Overview', icon: Icons.grid_view_rounded),
        DashNavItem('Shop', icon: Icons.lock_outline),
        DashNavItem('Transaction', icon: Icons.bar_chart_rounded),
        DashNavItem('Analytics', icon: Icons.pie_chart_outline),
        DashNavItem('Wallet', icon: Icons.account_balance_wallet_outlined),
        DashNavItem('Delivery', icon: Icons.card_giftcard),
      ],
      rightRail: const _ProfileRail(),
      child: switch (_tab) {
        2 => const _TransactionsPage(),
        4 => const _WalletPage(),
        _ => const _OverviewPage(),
      },
    );
  }
}

// ---------------------------------------------------------------------------
// Overview
// ---------------------------------------------------------------------------

class _OverviewPage extends StatelessWidget {
  const _OverviewPage();

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        PowrPageHeader('Overview',
            trailing: PowrDateRangePill('Jan – Feb, 2020', onTap: () {})),
        const SizedBox(height: 20),
        const PowrHeroBanner(
          greeting: 'Hi, Ebenezer Ghanney',
          subtitle: 'Welcome to your Powrsale dashboard',
        ),
        const SizedBox(height: 18),
        LayoutBuilder(builder: (context, c) {
          final wide = c.maxWidth >= 720;
          final leftW = wide ? c.maxWidth * 0.58 : c.maxWidth;
          final rightW = wide ? c.maxWidth * 0.42 - 16 : c.maxWidth;
          return Wrap(
            spacing: 16,
            runSpacing: 16,
            children: [
              SizedBox(
                width: leftW,
                child: PowrCard(
                  title: 'Activity',
                  child: SizedBox(height: 168, child: _ActivityChart()),
                ),
              ),
              SizedBox(
                width: rightW,
                child: Wrap(
                  spacing: 16,
                  runSpacing: 16,
                  children: [
                    PowrCard(
                      padding: const EdgeInsets.all(18),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                        children: const [
                          PowrRadial(
                              percent: 0.62,
                              centerTop: '259K',
                              centerBottom: 'link visits'),
                          PowrRadial(
                              percent: 0.78,
                              centerTop: '78%',
                              centerBottom: 'this week',
                              color: PowrTokens.primary),
                        ],
                      ),
                    ),
                    PowrCard(
                      padding: const EdgeInsets.all(20),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: const [
                          Flexible(child: PowrStat('58', 'New Orders')),
                          SizedBox(width: 8),
                          Icon(Icons.shopping_bag_outlined,
                              color: PowrTokens.faint, size: 28),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              SizedBox(
                width: leftW,
                child: PowrCard(
                  title: 'Transaction Volume',
                  trailing: PowrDateRangePill('Last 7 days'),
                  child: SizedBox(height: 150, child: _VolumeChart()),
                ),
              ),
              SizedBox(
                width: rightW,
                child: PowrCard(
                  title: 'Transaction Status',
                  child: Row(
                    children: const [
                      Expanded(
                          child: PowrStat('73', 'Shipped',
                              color: PowrTokens.coral)),
                      Expanded(
                          child: PowrStat('46', 'Delivered',
                              color: PowrTokens.primary)),
                      Expanded(
                          child: PowrStat('21', 'Completed',
                              color: PowrTokens.accentSoft)),
                    ],
                  ),
                ),
              ),
            ],
          );
        }),
      ],
    );
  }
}

class _ActivityChart extends StatelessWidget {
  static const _spots = [3.0, 3.4, 3.1, 4.2, 4.6, 4.1, 4.9];

  @override
  Widget build(BuildContext context) {
    return LineChart(
      LineChartData(
        minY: 2,
        maxY: 6,
        gridData: const FlGridData(show: false),
        borderData: FlBorderData(show: false),
        titlesData: FlTitlesData(
          leftTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          topTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          rightTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              getTitlesWidget: (v, _) => Text(
                const [
                  'Sat',
                  'Sun',
                  'Mon',
                  'Tue',
                  'Wed',
                  'Thu',
                  'Fri'
                ][v.toInt() % 7],
                style: const TextStyle(fontSize: 10, color: PowrTokens.faint),
              ),
            ),
          ),
        ),
        lineBarsData: [
          LineChartBarData(
            spots: [
              for (var i = 0; i < _spots.length; i++)
                FlSpot(i.toDouble(), _spots[i]),
            ],
            isCurved: true,
            barWidth: 3,
            color: PowrTokens.primary,
            dotData: const FlDotData(show: false),
            belowBarData: BarAreaData(
              show: true,
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  PowrTokens.primary.withValues(alpha: 0.22),
                  PowrTokens.primary.withValues(alpha: 0.0),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _VolumeChart extends StatelessWidget {
  static const _bars = [4.0, 6.5, 5.2, 3.6, 5.9, 4.4, 6.8];

  @override
  Widget build(BuildContext context) {
    return BarChart(
      BarChartData(
        maxY: 8,
        gridData: const FlGridData(show: false),
        borderData: FlBorderData(show: false),
        titlesData: FlTitlesData(
          leftTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          topTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          rightTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              getTitlesWidget: (v, _) => Text(
                const [
                  'Sat',
                  'Sun',
                  'Mon',
                  'Tue',
                  'Wed',
                  'Thu',
                  'Fri'
                ][v.toInt() % 7],
                style: const TextStyle(fontSize: 10, color: PowrTokens.faint),
              ),
            ),
          ),
        ),
        barGroups: [
          for (var i = 0; i < _bars.length; i++)
            BarChartGroupData(x: i, barRods: [
              BarChartRodData(
                toY: _bars[i],
                width: 14,
                borderRadius: BorderRadius.circular(6),
                color: PowrTokens.accent,
                backDrawRodData: BackgroundBarChartRodData(
                  show: true,
                  toY: 8,
                  color: PowrTokens.line,
                ),
              ),
            ]),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Transactions
// ---------------------------------------------------------------------------

class _TransactionsPage extends StatefulWidget {
  const _TransactionsPage();

  @override
  State<_TransactionsPage> createState() => _TransactionsPageState();
}

class _TransactionsPageState extends State<_TransactionsPage> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const PowrPageHeader('Transactions'),
        const SizedBox(height: 16),
        PowrTabs(
          tabs: const ['History', 'Upcoming'],
          index: _tab,
          onChanged: (i) => setState(() => _tab = i),
        ),
        const SizedBox(height: 16),
        PowrCard(
          child: Column(
            children: [
              _txn('Crazyshop USA', '#25698535', 'Electronics', '12 Jan, 2020',
                  disputed: true),
              const Divider(color: PowrTokens.line, height: 26),
              _txn('Aliexpress', '#25698120', 'Electronics', '11 Jan, 2020'),
              const Divider(color: PowrTokens.line, height: 26),
              _txn('Alibaba', '#25690012', '10pc Mobile', '11 Jan, 2020'),
              const Divider(color: PowrTokens.line, height: 26),
              _txn('Aliexpress', '#25698999', 'City Bank', '10 Jan, 2020'),
            ],
          ),
        ),
      ],
    );
  }

  Widget _txn(String shop, String id, String cat, String date,
      {bool disputed = false}) {
    return Row(
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: PowrTokens.pageBg,
            borderRadius: PowrTokens.radiusSm,
          ),
          child: const Icon(Icons.receipt_long_outlined,
              size: 18, color: PowrTokens.muted),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(shop,
                  style: const TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w800,
                      color: PowrTokens.ink)),
              const SizedBox(height: 3),
              Text('$date  ·  $id',
                  style:
                      const TextStyle(fontSize: 11, color: PowrTokens.faint)),
            ],
          ),
        ),
        Text(cat,
            style: const TextStyle(fontSize: 12, color: PowrTokens.muted)),
        const SizedBox(width: 16),
        if (disputed) ...[
          PowrButton(
              label: 'Open dispute',
              dense: true,
              filledColor: PowrTokens.primary,
              onPressed: () {}),
          const SizedBox(width: 8),
          PowrButton(
              label: 'Chat',
              dense: true,
              filledColor: PowrTokens.accent,
              onPressed: () {}),
        ] else
          const PowrPill('Completed', color: PowrTokens.accentSoft),
      ],
    );
  }
}

// ---------------------------------------------------------------------------
// Wallet
// ---------------------------------------------------------------------------

class _WalletPage extends StatelessWidget {
  const _WalletPage();

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        PowrPageHeader('Wallet',
            trailing: PowrButton(label: 'Edit', dense: true, onPressed: () {})),
        const SizedBox(height: 20),
        Container(
          decoration: BoxDecoration(
            gradient: PowrTokens.heroGradient,
            borderRadius: PowrTokens.radius,
            boxShadow: [
              BoxShadow(
                color: PowrTokens.accent.withValues(alpha: 0.28),
                blurRadius: 26,
                offset: const Offset(0, 14),
              ),
            ],
          ),
          padding: const EdgeInsets.all(26),
          child: Row(
            children: [
              _balance('Available', '\$2,595.00'),
              const SizedBox(width: 40),
              _balance('Funds in escrow', '\$159.00'),
              const Spacer(),
              const PowrRadial(
                percent: 0.7,
                centerTop: '',
                color: Colors.white,
                size: 74,
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        LayoutBuilder(builder: (context, c) {
          final w = c.maxWidth >= 700 ? (c.maxWidth - 32) / 3 : c.maxWidth;
          return Wrap(
            spacing: 16,
            runSpacing: 16,
            children: [
              SizedBox(
                  width: w,
                  child: _payoutCard(
                      'Mahmudul Hasan', 'MTN MoMo', PowrTokens.coral)),
              SizedBox(
                  width: w,
                  child:
                      _payoutCard('John Smith', 'Access Bank', PowrTokens.ink)),
              SizedBox(
                width: w,
                child: PowrCard(
                  child: Center(
                    child:
                        Column(mainAxisSize: MainAxisSize.min, children: const [
                      Icon(Icons.add_circle_outline,
                          color: PowrTokens.muted, size: 26),
                      SizedBox(height: 8),
                      Text('Add new payout wallet',
                          style: TextStyle(
                              fontSize: 12.5, color: PowrTokens.muted)),
                    ]),
                  ),
                ),
              ),
            ],
          );
        }),
      ],
    );
  }

  Widget _balance(String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(label,
            style: TextStyle(
                color: Colors.white.withValues(alpha: 0.8), fontSize: 12)),
        const SizedBox(height: 6),
        Text(value,
            style: const TextStyle(
                color: Colors.white,
                fontSize: 22,
                fontWeight: FontWeight.w800)),
      ],
    );
  }

  Widget _payoutCard(String name, String bank, Color color) {
    return Container(
      height: 128,
      decoration: BoxDecoration(
        color: color,
        borderRadius: PowrTokens.radius,
      ),
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(name,
              style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 13.5)),
          const Spacer(),
          Text(bank,
              style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.85), fontSize: 11)),
          const SizedBox(height: 4),
          const Text('•••• 9018',
              style: TextStyle(
                  color: Colors.white,
                  letterSpacing: 2,
                  fontSize: 12,
                  fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Right rail
// ---------------------------------------------------------------------------

class _ProfileRail extends StatelessWidget {
  const _ProfileRail();

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text('My profile',
            style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w800,
                color: PowrTokens.ink)),
        const Text('80% completed',
            style: TextStyle(fontSize: 11, color: PowrTokens.faint)),
        const SizedBox(height: 16),
        const Center(
          child: PowrRadial(
            percent: 0.8,
            centerTop: 'EG',
            color: PowrTokens.primary,
            size: 92,
          ),
        ),
        const SizedBox(height: 12),
        const Center(
          child: Text('Ebenezer Ghanney',
              style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: PowrTokens.ink)),
        ),
        const Center(
          child: Text('Edit Profile',
              style: TextStyle(fontSize: 11.5, color: PowrTokens.primary)),
        ),
        const SizedBox(height: 16),
        Material(
          color: PowrTokens.accent.withValues(alpha: 0.10),
          borderRadius: PowrTokens.radiusSm,
          child: InkWell(
            borderRadius: PowrTokens.radiusSm,
            onTap: () {},
            child: const Padding(
              padding: EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              child: Row(children: [
                Icon(Icons.chat_bubble_outline,
                    size: 16, color: PowrTokens.accent),
                SizedBox(width: 10),
                Text('Chat Now',
                    style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w800,
                        color: PowrTokens.accent)),
                Spacer(),
                Text('11 New',
                    style: TextStyle(fontSize: 11, color: PowrTokens.muted)),
              ]),
            ),
          ),
        ),
        const SizedBox(height: 20),
        Row(children: const [
          Expanded(
            child: Text('Message',
                style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w800,
                    color: PowrTokens.ink)),
          ),
          Text('View all',
              style: TextStyle(fontSize: 11, color: PowrTokens.primary)),
        ]),
        const SizedBox(height: 4),
        for (final name in const [
          'Albert Flores',
          'Annette Black',
          'Arlene McCoy',
          'Bessie Cooper',
        ])
          PowrPersonRow(name: name, subtitle: 'Item will be replaced with…'),
      ],
    );
  }
}
