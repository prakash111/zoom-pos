import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/models/analytics_model.dart';
import 'package:zoom_pos_mobile/core/utils/currency_formatter.dart';
import 'package:zoom_pos_mobile/features/dashboard/widgets/posh_dashboard.dart';

AnalyticsModel _model() => AnalyticsModel.fromJson({
      'kpis': {
        'today_revenue': 39.23,
        'today_orders': 2,
        'month_revenue': 12235.99,
        'month_orders': 318,
        'prev_month_revenue': 10000.0,
        'prev_month_orders': 330,
        'all_time_revenue': 25640.0,
        'all_time_orders': 900,
        'average_order_value': 41.97,
        'total_receivables': 0,
        'low_stock_count': 0,
        'product_count': 128,
        'customer_count': 57,
      },
      'revenue_trend': [
        for (var i = 0; i < 7; i++)
          {'date': '2026-09-0$i', 'day': 'D$i', 'revenue': 100.0 + i * 10},
      ],
      'monthly_activity': [
        for (var i = 0; i < 9; i++)
          {'month': 'M$i', 'year': 2026, 'completed': 10 + i, 'pending': 5 + i},
      ],
      'popular_tags': ['Android', 'Head Phone', 'Cables'],
      'recent_transactions': [
        {
          'id': '1',
          'reference': 'TR-001-123456',
          'customer': 'Jolly Annam',
          'date': '02-12-2026',
          'status': 'Completed',
          'amount': 550.0,
        },
        {
          'id': '2',
          'reference': 'TR-001-777',
          'customer': 'Anna Glory',
          'date': '03-12-2026',
          'status': 'Pending',
          'amount': 120.0,
        },
      ],
      'recent_customers': [
        {
          'id': '9',
          'name': 'Daniel Gallego',
          'detail': '+1 555 0100',
          'time': '02 Dec, 12:50 PM'
        },
      ],
      'top_products': const [],
    });

void main() {
  testWidgets('PoshDashboardHome binds the analytics payload', (tester) async {
    tester.view.physicalSize = const Size(1400, 2400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: PoshDashboardHome(
            analytics: _model(),
            formatter: CurrencyFormatter('\$'),
          ),
        ),
      ),
    ));
    await tester.pumpAndSettle();

    // Total balance + statistics values off the live model.
    expect(find.text('Total balance'), findsOneWidget);
    expect(find.text('\$25,640.00'), findsOneWidget);
    expect(find.text('Statistics'), findsOneWidget);
    expect(find.text('\$12,235.99'), findsOneWidget);
    expect(find.text('318'), findsOneWidget);
    expect(find.text('128 items'), findsOneWidget);
    // Month-over-month delta: (12235.99-10000)/10000 = +22.36%
    expect(find.textContaining('+22.36%'), findsOneWidget);

    // Panels + data rows.
    expect(find.text('Purchase Activity'), findsOneWidget);
    expect(find.text('Popular Tags'), findsOneWidget);
    expect(find.text('#android'), findsOneWidget);
    expect(find.text('Latest Transactions'), findsOneWidget);
    expect(find.text('TR-001-123456'), findsOneWidget);
    expect(find.text('Completed'), findsWidgets);
    expect(find.text('Pending'), findsWidgets);
    expect(find.text('Recent Customers'), findsOneWidget);
    expect(find.text('Daniel Gallego'), findsOneWidget);
  });
}
