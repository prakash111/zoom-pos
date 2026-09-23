import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/models/dashboard_summary_model.dart';
import 'package:zoom_pos_mobile/screens/dashboard/widgets/metric_cards_grid.dart';

void main() {
  testWidgets('tapping each metric card routes to the expected endpoint with feedback', (tester) async {
    final routesObserved = <String>[];
    final argumentsObserved = <Object?>[];

    final metrics = MetricsSummaryData.fromJson({
      'total_sales': {
        'value': 1200.21,
        'formatted': '₹1,200.21',
        'trend': '+12.4%',
        'is_positive': true,
        'sparkline': [10.0, 20.0, 15.0, 30.0],
      },
      'total_orders': {
        'value': 54,
        'formatted': '54',
        'trend': '+8.2%',
        'is_positive': true,
        'sparkline': [5.0, 10.0, 8.0, 15.0],
      },
      'total_customers': {
        'value': 12,
        'formatted': '12',
        'trend': '+5.0%',
        'is_positive': true,
        'sparkline': [1.0, 2.0, 3.0, 4.0],
      },
      'low_stock_items': {
        'value': 1,
        'formatted': '1',
        'trend': '-2.1%',
        'is_positive': false,
        'sparkline': [4.0, 3.0, 2.0, 1.0],
      },
    });

    Widget buildTestWidget() {
      return MaterialApp(
        onGenerateRoute: (settings) {
          routesObserved.add(settings.name ?? '');
          argumentsObserved.add(settings.arguments);
          return MaterialPageRoute(builder: (_) => const Scaffold(body: Text('Target Screen')));
        },
        home: Scaffold(
          body: SingleChildScrollView(
            child: MetricCardsGrid(metrics: metrics),
          ),
        ),
      );
    }

    await tester.pumpWidget(buildTestWidget());
    await tester.pumpAndSettle();

    // Verify all 4 titles and values are rendered
    expect(find.text('Total Sales'), findsOneWidget);
    expect(find.text('₹1,200.21'), findsOneWidget);
    expect(find.text('Total Orders'), findsOneWidget);
    expect(find.text('54'), findsOneWidget);
    expect(find.text('Total Customers'), findsOneWidget);
    expect(find.text('12'), findsOneWidget);
    expect(find.text('Low Stock Items'), findsOneWidget);
    expect(find.text('1'), findsOneWidget);

    // 1. Tap Total Sales -> /sales
    await tester.tap(find.text('Total Sales'));
    await tester.pumpAndSettle();
    expect(routesObserved.last, '/sales');

    // Pop back to home
    final navigator = tester.state<NavigatorState>(find.byType(Navigator));
    navigator.pop();
    await tester.pumpAndSettle();

    // 2. Tap Total Orders -> /orders
    await tester.tap(find.text('Total Orders'));
    await tester.pumpAndSettle();
    expect(routesObserved.last, '/orders');

    navigator.pop();
    await tester.pumpAndSettle();

    // 3. Tap Total Customers -> /customers
    await tester.tap(find.text('Total Customers'));
    await tester.pumpAndSettle();
    expect(routesObserved.last, '/customers');

    navigator.pop();
    await tester.pumpAndSettle();

    // 4. Tap Low Stock Items -> /inventory with filter low_stock
    await tester.tap(find.text('Low Stock Items'));
    await tester.pumpAndSettle();
    expect(routesObserved.last, '/inventory');
    expect(argumentsObserved.last, {'filter': 'low_stock'});
  });
}
