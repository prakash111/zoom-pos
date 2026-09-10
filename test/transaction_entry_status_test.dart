import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/config/theme.dart';
import 'package:zoom_pos_mobile/core/models/analytics_model.dart';
import 'package:zoom_pos_mobile/core/utils/currency_formatter.dart';
import 'package:zoom_pos_mobile/features/dashboard/widgets/posh_dashboard.dart';

void main() {
  group('TransactionEntry status resolution', () {
    test(
        'short "Paid" label with a success colour token is treated as completed',
        () {
      final e = TransactionEntry.fromJson({
        'id': '1',
        'reference': 'TR-1',
        'date': '9 Sep',
        'status': 'Paid',
        'status_label': 'Paid',
        'status_key': 'completed',
        'status_color': 'success',
        'badge': {'variant': 'success', 'color': '#10B981'},
        'amount': 100.0,
      });

      expect(e.status, 'Paid');
      expect(e.isCompleted, isTrue);
    });

    test('badge.variant alone still resolves the success state', () {
      final e = TransactionEntry.fromJson({
        'id': '2',
        'reference': 'TR-2',
        'status': 'Paid',
        'badge': {'variant': 'success'},
        'amount': 50.0,
      });
      expect(e.isCompleted, isTrue);
    });

    test('pending / warning stays not-completed', () {
      final e = TransactionEntry.fromJson({
        'id': '3',
        'reference': 'TR-3',
        'status': 'Pending',
        'status_key': 'pending',
        'status_color': 'warning',
        'amount': 40.0,
      });
      expect(e.status, 'Pending');
      expect(e.isCompleted, isFalse);
    });

    test('legacy payload with only "Completed" still works', () {
      final e = TransactionEntry.fromJson({
        'id': '4',
        'reference': 'TR-4',
        'status': 'Completed',
        'amount': 10.0,
      });
      expect(e.isCompleted, isTrue);
    });
  });

  testWidgets(
      'Latest Transactions renders "Paid" on one line with the success chip',
      (tester) async {
    tester.view.physicalSize = const Size(1200, 2000);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final model = AnalyticsModel.fromJson({
      'kpis': const {
        'all_time_revenue': 100.0,
        'all_time_orders': 1,
        'month_revenue': 100.0,
        'month_orders': 1,
        'prev_month_revenue': 100.0,
        'prev_month_orders': 1,
        'product_count': 1,
      },
      'revenue_trend': [
        for (var i = 0; i < 3; i++)
          {'date': '2026-09-0$i', 'day': '0$i:00', 'revenue': 10.0 * i},
      ],
      'monthly_activity': const [],
      'recent_transactions': const [
        {
          'id': '1',
          'reference': 'TR-PAID',
          'customer': 'Ada',
          'date': '9 Sep',
          'status': 'Paid',
          'status_label': 'Paid',
          'status_key': 'completed',
          'status_color': 'success',
          'badge': {'variant': 'success'},
          'amount': 550.0,
        },
      ],
      'recent_customers': const [],
      'top_products': const [],
    });

    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: PoshDashboardHome(
            analytics: model,
            formatter: CurrencyFormatter('\$'),
          ),
        ),
      ),
    ));
    await tester.pumpAndSettle();

    final paid = find.text('Paid');
    expect(paid, findsOneWidget);

    // Single visual line.
    final tp = (tester.widget<Text>(paid));
    expect(tp.softWrap, isFalse);
    expect(tp.maxLines, 1);
    expect(tester.getSize(paid).height, lessThan(20));

    // Chip fill is the success container, not the warning one.
    final scheme = Theme.of(tester.element(paid)).colorScheme;
    final chip = tester.widget<Container>(
      find.ancestor(of: paid, matching: find.byType(Container)).first,
    );
    final decoration = chip.decoration as BoxDecoration;
    expect(decoration.color, scheme.successContainer);
    expect(decoration.color, isNot(scheme.warningContainer));
  });
}
