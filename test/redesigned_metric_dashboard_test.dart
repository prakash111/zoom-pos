import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/config/dashboard_layout.dart';
import 'package:zoom_pos_mobile/core/models/analytics_model.dart';
import 'package:zoom_pos_mobile/core/models/dashboard_summary_model.dart';
import 'package:zoom_pos_mobile/core/utils/currency_formatter.dart';
import 'package:zoom_pos_mobile/features/dashboard/widgets/redesigned_metric_dashboard.dart';

AnalyticsModel _sampleAnalytics() => AnalyticsModel.fromJson({
      'kpis': {
        'month_revenue': 12480.0,
        'month_orders': 348,
        'prev_month_revenue': 11000.0,
        'prev_month_orders': 310,
        'all_time_revenue': 85000.0,
        'all_time_orders': 2500,
        'average_order_value': 35.86,
        'product_count': 420,
        'customer_count': 1204,
        'low_stock_count': 3,
        'total_receivables': 4520.0,
      },
      'revenue_trend': [
        {'date': '2026-09-14', 'day': 'Mon', 'revenue': 1200.0},
        {'date': '2026-09-15', 'day': 'Tue', 'revenue': 1850.0},
      ],
      'monthly_activity': const [],
      'popular_tags': const [],
      'recent_transactions': [
        {
          'id': '1',
          'reference': '#ORD-9081',
          'customer': 'Sarah Connor',
          'amount': 142.50,
          'status': 'Completed',
          'date': 'Today, 09:42 AM',
        },
      ],
      'recent_customers': const [],
      'top_products': const [],
    });

void main() {
  group('DashboardLayout enum', () {
    test('contains redesigned layout', () {
      expect(DashboardLayout.values, contains(DashboardLayout.redesigned));
      expect(DashboardLayout.redesigned.name, 'redesigned');
      expect(DashboardLayout.redesigned.label, 'Modern Metric (split)');
    });

    test('DashboardLayout round-trips by name including redesigned', () {
      expect(DashboardLayout.fromName('redesigned'), DashboardLayout.redesigned);
      expect(DashboardLayout.fromName('oroit'), DashboardLayout.oroit);
      expect(DashboardLayout.fromName('posh'), DashboardLayout.posh);
    });
  });

  group('DashboardSummaryModel', () {
    test('parses from standard API summary JSON payload', () {
      final json = <String, dynamic>{
        'greeting': {
          'title': 'Good Morning, Alex Johnson!',
          'subtitle': "Here's what's happening at your store today.",
        },
        'status_badges': {
          'datetime': 'Tue, 20 Sep 2026 · 10:24 AM',
          'weather': '28°C Sunny',
        },
        'top_app_bar': {
          'store_name': 'MetroRetail',
          'tagline': 'Smarter Retail. Faster Growth.',
          'unread_notifications_count': 3,
          'user_name': 'Alex Johnson',
          'user_role': 'Store Manager',
          'user_initials': 'AJ',
        },
        'metrics': {
          'total_sales': {
            'value': 12480.00,
            'formatted': '\$12,480.00',
            'trend': '+14.2%',
            'is_positive': true,
            'sparkline': [1200, 1500, 1800, 2100, 2400, 2900, 3100],
          },
          'total_orders': {
            'value': 348,
            'formatted': '348',
            'trend': '+8.5%',
            'is_positive': true,
            'sparkline': [10, 14, 12, 18, 15, 22, 25],
          },
          'total_customers': {
            'value': 1204,
            'formatted': '1,204',
            'trend': '+12.0%',
            'is_positive': true,
            'sparkline': [20, 25, 30, 35, 40, 45, 52],
          },
          'low_stock_items': {
            'value': 3,
            'formatted': '3',
            'trend': '3',
            'is_positive': false,
            'sparkline': [5, 4, 6, 8, 5, 4, 3],
          },
        },
        'sales_overview': {
          'ranges': ['last_7_days', 'this_month', 'quarter'],
          'current_range': 'last_7_days',
          'series': [
            {'date': '2026-09-14', 'label': 'Mon', 'day': 'Mon', 'amount': 1200.0},
            {'date': '2026-09-15', 'label': 'Tue', 'day': 'Tue', 'amount': 1850.0},
          ],
        },
        'receivables': {
          'total_outstanding': 4520.00,
          'formatted': '\$4,520.00',
          'breakdown': {
            'overdue_amount': 1800.00,
            'formatted_overdue': '\$1,800.00',
            'due_today_amount': 650.00,
            'formatted_due_today': '\$650.00',
            'outstanding_invoices_count': 7,
          },
        },
        'quick_actions': [
          {'key': 'add_product', 'label': 'Add Product', 'icon': 'add_box', 'target': 'inventory'},
          {'key': 'create_order', 'label': 'Create Order', 'icon': 'point_of_sale', 'target': 'pos'},
          {'key': 'add_customer', 'label': 'Add Customer', 'icon': 'person_add', 'target': 'customers'},
          {'key': 'view_reports', 'label': 'View Reports', 'icon': 'bar_chart', 'target': 'reports'},
        ],
        'recent_transactions': [
          {
            'id': 'ORD-9081',
            'order_number': '#ORD-9081',
            'customer_name': 'Sarah Connor',
            'customer_initials': 'SC',
            'datetime': 'Today, 09:42 AM',
            'amount': 142.50,
            'formatted_amount': '\$142.50',
            'status': 'Completed',
            'status_color': 'success',
          },
        ],
      };

      final model = DashboardSummaryModel.fromJson(json);

      expect(model.greeting.title, 'Good Morning, Alex Johnson!');
      expect(model.topAppBar.storeName, 'MetroRetail');
      expect(model.topAppBar.tagline, 'Smarter Retail. Faster Growth.');
      expect(model.topAppBar.unreadNotificationsCount, 3);
      expect(model.metrics.totalSales.formatted, '\$12,480.00');
      expect(model.metrics.totalOrders.value, 348);
      expect(model.metrics.lowStockItems.isPositive, false);
      expect(model.receivables.totalOutstanding, 4520.00);
      expect(model.receivables.overdueAmount, 1800.00);
      expect(model.receivables.outstandingInvoicesCount, 7);
      expect(model.quickActions.length, 4);
      expect(model.recentTransactions.first.orderNumber, '#ORD-9081');
    });

    test('transforms from AnalyticsModel fallback seamlessly', () {
      final analytics = _sampleAnalytics();
      final formatter = CurrencyFormatter('\$');
      final model = DashboardSummaryModel.fromAnalytics(
        analytics,
        formatter,
        storeName: 'MetroRetail',
        userName: 'Alex Johnson',
        userRole: 'Store Manager',
      );

      expect(model.topAppBar.storeName, 'MetroRetail');
      expect(model.topAppBar.userName, 'Alex Johnson');
      expect(model.metrics.totalSales.value, 12480.0);
      expect(model.metrics.totalOrders.value, 348.0);
      expect(model.metrics.totalCustomers.value, 1204.0);
      expect(model.metrics.lowStockItems.value, 3.0);
      expect(model.recentTransactions.first.customerName, 'Sarah Connor');
      expect(model.recentTransactions.first.customerInitials, 'SC');
    });
  });

  group('RedesignedMetricDashboard Widget', () {
    testWidgets('renders all redesigned sections and tiles', (tester) async {
      tester.view.physicalSize = const Size(1200, 1600);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);

      bool addProductTapped = false;
      bool createOrderTapped = false;

      final sampleModel = DashboardSummaryModel.fromAnalytics(
        _sampleAnalytics(),
        CurrencyFormatter('\$'),
        storeName: 'MetroRetail',
        userName: 'Alex Johnson',
        userRole: 'Store Manager',
      );

      await tester.pumpWidget(
        MaterialApp(
          theme: ThemeData.light(),
          home: Scaffold(
            body: SingleChildScrollView(
              child: RedesignedMetricDashboard(
                summary: sampleModel,
                isDemo: true,
                onAddProduct: () => addProductTapped = true,
                onCreateOrder: () => createOrderTapped = true,
              ),
            ),
          ),
        ),
      );

      await tester.pump();

      // Demo Mode Banner
      expect(find.text('DEMO MODE'), findsOneWidget);
      expect(find.text('🚀 Try Flutter Web'), findsOneWidget);

      // Store Brand & Tagline
      expect(find.text('MetroRetail'), findsOneWidget);
      expect(find.text('Smarter Retail. Faster Growth.'), findsOneWidget);
      expect(find.text('Alex Johnson'), findsOneWidget);
      expect(find.text('Store Manager'), findsOneWidget);

      // Status Badges
      expect(find.text('28°C Sunny'), findsOneWidget);

      // 4 Metric Cards
      expect(find.text('Total Sales'), findsOneWidget);
      expect(find.text('Total Orders'), findsOneWidget);
      expect(find.text('Total Customers'), findsOneWidget);
      expect(find.text('Low Stock Items'), findsOneWidget);

      // Sales Overview & Amount Receivable
      expect(find.text('Sales Overview'), findsOneWidget);
      expect(find.text('Amount Receivable'), findsOneWidget);

      // 4 Quick Actions
      expect(find.text('Add Product'), findsOneWidget);
      expect(find.text('Create Order'), findsOneWidget);
      expect(find.text('Add Customer'), findsOneWidget);
      expect(find.text('View Reports'), findsOneWidget);

      // Recent Transactions
      expect(find.text('Recent Transactions'), findsOneWidget);
      expect(find.textContaining('#ORD-9081'), findsOneWidget);
      expect(find.text('Sarah Connor'), findsOneWidget);

      // Test Quick Action taps
      await tester.tap(find.text('Add Product'));
      await tester.pump();
      expect(addProductTapped, isTrue);

      await tester.tap(find.text('Create Order'));
      await tester.pump();
      expect(createOrderTapped, isTrue);
    });
  });
}
