import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/models/analytics_model.dart';
import 'package:zoom_pos_mobile/core/utils/currency_formatter.dart';
import 'package:zoom_pos_mobile/screens/dashboard/layouts/cards_dashboard_layout.dart';
import 'package:zoom_pos_mobile/screens/dashboard/widgets/dynamic_dashboard_engine.dart';

AnalyticsModel _testModel() => AnalyticsModel.fromJson({
      'kpis': {
        'today_revenue': 50.0,
        'today_orders': 2,
        'month_revenue': 12000.0,
        'month_orders': 150,
        'prev_month_revenue': 10000.0,
        'prev_month_orders': 140,
        'all_time_revenue': 30000.0,
        'all_time_orders': 400,
        'average_order_value': 80.0,
        'total_receivables': 1500.0,
        'low_stock_count': 3,
        'product_count': 75,
        'customer_count': 42,
      },
      'revenue_trend': [],
      'monthly_activity': [
        {'month': 'Jan', 'year': 2026, 'completed': 20, 'pending': 5},
        {'month': 'Feb', 'year': 2026, 'completed': 25, 'pending': 4},
      ],
      'popular_tags': ['Retail', 'Pharma'],
      'recent_transactions': [],
      'recent_customers': [],
      'top_products': [],
    });

void main() {
  testWidgets('buildDashboardFromSchema strictly excludes total_balance and balance_card', (tester) async {
    final widgets = [
      'total_balance',
      'balance_card',
      'total_balance_card',
      'quick_actions',
      'receivables_banner',
      'statistics_card',
    ];

    final data = {
      'receivables': {'total_amount': 1250.0},
      'statistics': {
        'range_revenue': 8500.0,
        'range_orders': 95,
        'catalogue_count': 60,
      },
    };

    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: Builder(
            builder: (context) => buildDashboardFromSchema(
              context,
              widgets,
              data,
              formatter: CurrencyFormatter('₹'),
            ),
          ),
        ),
      ),
    ));
    await tester.pumpAndSettle();

    // Verify total balance is completely absent
    expect(find.text('Total balance'), findsNothing);
    expect(find.text('balance_card'), findsNothing);

    // Verify active widgets rendered properly
    expect(find.text('Quick Sale'), findsOneWidget);
    expect(find.text('New Customer'), findsOneWidget);
    expect(find.text('Due Payments / Receivables'), findsOneWidget);
    expect(find.text('Statistics'), findsOneWidget);
    expect(find.text('Total Earnings'), findsOneWidget);
    expect(find.text('Catalogue'), findsOneWidget);
  });

  testWidgets('CardsDashboardLayout renders without TotalBalanceCard', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: CardsDashboardLayout(
            analytics: _testModel(),
            formatter: CurrencyFormatter('₹'),
          ),
        ),
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Total balance'), findsNothing);
    expect(find.text('Statistics'), findsOneWidget);
    expect(find.text('Purchase Activity'), findsOneWidget);
  });

  testWidgets('TotalBalanceCard widget returns SizedBox.shrink', (tester) async {
    await tester.pumpWidget(const MaterialApp(
      home: Scaffold(
        body: TotalBalanceCard(),
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Total balance'), findsNothing);
    expect(find.byType(TotalBalanceCard), findsOneWidget);
  });
}
