import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/config/dashboard_layout.dart';
import 'package:zoom_pos_mobile/core/models/analytics_model.dart';
import 'package:zoom_pos_mobile/core/utils/currency_formatter.dart';
import 'package:zoom_pos_mobile/features/dashboard/widgets/oroit_dashboard.dart';

AnalyticsModel _model() => AnalyticsModel.fromJson({
      'kpis': {
        'month_revenue': 65428.0,
        'month_orders': 2765,
        'prev_month_revenue': 63000.0,
        'prev_month_orders': 2680,
        'all_time_revenue': 8482.10,
        'all_time_orders': 1050,
        'average_order_value': 35.90,
        'product_count': 120,
        'customer_count': 40,
      },
      'revenue_trend': [
        for (var i = 0; i < 7; i++)
          {
            'date': '2026-09-0$i',
            'day': 'Jun 0$i',
            'revenue': 1000.0 + i * 500
          },
      ],
      'monthly_activity': [
        for (var i = 0; i < 9; i++)
          {
            'month': 'M$i',
            'year': 2026,
            'completed': 40 + i,
            'pending': 15 + i
          },
      ],
      'popular_tags': const [],
      'recent_transactions': const [],
      'recent_customers': const [],
      'top_products': const [],
    });

void main() {
  test('DashboardLayout round-trips by name', () {
    expect(DashboardLayout.fromName('oroit'), DashboardLayout.oroit);
    expect(DashboardLayout.fromName('posh'), DashboardLayout.posh);
    expect(DashboardLayout.fromName(null), DashboardLayout.redesigned);
    expect(DashboardLayout.fromName('junk'), DashboardLayout.redesigned);
  });

  testWidgets('OroitDashboardHome renders the dark analytics board from data',
      (tester) async {
    tester.view.physicalSize = const Size(1500, 2600);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: OroitDashboardHome(
            analytics: _model(),
            formatter: CurrencyFormatter('\$'),
          ),
        ),
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Order statistic'), findsOneWidget);
    expect(find.text('Total orders'), findsOneWidget);
    expect(find.text('2765'), findsOneWidget);
    expect(find.text('\$65,428.00'), findsOneWidget);
    expect(find.text('Order and Sale Overview'), findsOneWidget);
    expect(find.text('Total Transactions'), findsOneWidget);
    expect(find.text('Order Delivered'), findsOneWidget);
    expect(find.text('1050'), findsOneWidget);
    expect(find.text('Order Tracking'), findsOneWidget);
    expect(find.text('Marketing'), findsOneWidget);
  });
}
