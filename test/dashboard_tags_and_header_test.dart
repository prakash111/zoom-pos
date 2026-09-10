import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/models/analytics_model.dart';
import 'package:zoom_pos_mobile/core/utils/currency_formatter.dart';
import 'package:zoom_pos_mobile/features/dashboard/widgets/posh_dashboard.dart';

AnalyticsModel _model() => AnalyticsModel.fromJson({
      'range': {'label': 'This Month'},
      'kpis': {'month_revenue': 100.0, 'month_orders': 4, 'product_count': 9},
      'popular_tags': ['Household Goods', 'Beverages'],
      'revenue_trend': const [],
      'monthly_activity': const [],
      'recent_transactions': const [],
      'recent_customers': const [],
      'top_products': const [],
    });

void main() {
  testWidgets('Popular Tags are tappable and pass the tag name back',
      (tester) async {
    tester.view.physicalSize = const Size(1400, 2600);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    String? tapped;
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: PoshDashboardHome(
            analytics: _model(),
            formatter: CurrencyFormatter('\$'),
            onTagTap: (t) => tapped = t,
          ),
        ),
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('#householdgoods'), findsOneWidget);
    await tester.tap(find.text('#householdgoods'));
    await tester.pump();
    expect(tapped, 'Household Goods');
  });

  testWidgets('narrow dashboard header keeps "Dashboard" on one line',
      (tester) async {
    tester.view.physicalSize = const Size(420, 900); // narrow
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

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

    final title = tester.widget<Text>(find.text('Dashboard'));
    expect(title.maxLines, 1);
    expect(title.softWrap, isFalse);
    expect(tester.takeException(), isNull); // no RenderFlex overflow
  });
}
