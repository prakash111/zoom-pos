import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/core/config/theme_provider.dart';
import 'package:zoom_pos_mobile/core/models/analytics_model.dart';
import 'package:zoom_pos_mobile/core/models/dashboard_summary_model.dart';
import 'package:zoom_pos_mobile/core/utils/currency_formatter.dart';
import 'package:zoom_pos_mobile/features/dashboard/widgets/redesigned_metric_dashboard.dart';
import 'package:zoom_pos_mobile/screens/dashboard/widgets/recent_transactions_card.dart';
import 'package:zoom_pos_mobile/screens/main_shell/main_bottom_nav_bar.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  group('ThemeProvider Brand Color', () {
    test('exposes brandColor, updates via updateBrandColor and loadFromHex', () {
      final themeProvider = ThemeProvider();
      expect(themeProvider.brandColor, equals(themeProvider.seedColor));

      // Update to custom Orange (#EA580C)
      const orange = Color(0xFFEA580C);
      themeProvider.updateBrandColor(orange);
      expect(themeProvider.brandColor, equals(orange));

      // Load via hex code with #
      themeProvider.loadFromHex('#2563EB');
      expect(themeProvider.brandColor, equals(const Color(0xFF2563EB)));

      // Load via hex code without #
      themeProvider.loadFromHex('EA580C');
      expect(themeProvider.brandColor, equals(orange));
    });
  });

  group('RecentTransactionsCard & Avatar Dynamic Color Binding', () {
    testWidgets('renders transaction avatar with custom brand color', (tester) async {
      final themeProvider = ThemeProvider();
      const orange = Color(0xFFEA580C);
      themeProvider.updateBrandColor(orange);

      await tester.pumpWidget(
        ChangeNotifierProvider<ThemeProvider>.value(
          value: themeProvider,
          child: const MaterialApp(
            home: Scaffold(
              body: RecentTransactionsCard(
                transactions: [
                  {
                    'customer_name': 'Peter Parker',
                    'created_at': 'Today, 01:20 PM',
                    'total_formatted': '\$112.00',
                    'status': 'Completed',
                  },
                ],
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Verify customer initials 'PP' are rendered
      expect(find.text('PP'), findsOneWidget);

      // Verify the initials text widget uses the orange brand color
      final textWidget = tester.widget<Text>(find.text('PP'));
      expect(textWidget.style?.color, equals(orange));

      // Dynamic update without restart: change to Purple (#7C3AED)
      const purple = Color(0xFF7C3AED);
      themeProvider.updateBrandColor(purple);
      await tester.pumpAndSettle();

      final updatedTextWidget = tester.widget<Text>(find.text('PP'));
      expect(updatedTextWidget.style?.color, equals(purple));
    });
  });

  group('MainBottomNavBar Dynamic Color Binding', () {
    testWidgets('renders active tab and center button with brand color', (tester) async {
      final themeProvider = ThemeProvider();
      const orange = Color(0xFFEA580C);
      themeProvider.updateBrandColor(orange);

      int selectedIndex = 0;

      await tester.pumpWidget(
        ChangeNotifierProvider<ThemeProvider>.value(
          value: themeProvider,
          child: MaterialApp(
            home: Scaffold(
              bottomNavigationBar: MainBottomNavBar(
                currentIndex: selectedIndex,
                onTap: (index) => selectedIndex = index,
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Active tab label 'Home' should use the orange brand color
      final homeText = tester.widget<Text>(find.text('Home'));
      expect(homeText.style?.color, equals(orange));

      // Inactive tab label 'Sales' should use inactive color (Color(0xFF64748B))
      final salesText = tester.widget<Text>(find.text('Sales'));
      expect(salesText.style?.color, equals(const Color(0xFF64748B)));

      // Center '+' button container has brandColor
      final addIcon = find.byIcon(Icons.add_rounded);
      expect(addIcon, findsOneWidget);

      // Find the parent Container with brandColor
      final containerFinder = find.ancestor(
        of: addIcon,
        matching: find.byType(Container),
      );
      final container = tester.widget<Container>(containerFinder.first);
      final boxDecoration = container.decoration as BoxDecoration;
      expect(boxDecoration.color, equals(orange));
    });
  });

  group('RedesignedMetricDashboard Customer Avatar Dynamic Brand Color', () {
    testWidgets('renders customer initials avatar with active brand color', (tester) async {
      tester.view.physicalSize = const Size(1200, 1600);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);

      final themeProvider = ThemeProvider();
      const orange = Color(0xFFEA580C);
      themeProvider.updateBrandColor(orange);

      final summaryModel = DashboardSummaryModel.fromAnalytics(
        AnalyticsModel.fromJson({
          'kpis': {
            'month_revenue': 1000.0,
            'month_orders': 10,
            'prev_month_revenue': 900.0,
            'prev_month_orders': 9,
            'all_time_revenue': 5000.0,
            'all_time_orders': 50,
            'average_order_value': 100.0,
            'product_count': 10,
            'customer_count': 5,
            'low_stock_count': 0,
            'total_receivables': 0.0,
          },
          'revenue_trend': const [],
          'monthly_activity': const [],
          'popular_tags': const [],
          'recent_transactions': [
            {
              'id': '1',
              'reference': '#ORD-001',
              'customer': 'Wayne Rooney',
              'amount': 99.0,
              'status': 'Completed',
              'date': 'Today, 10:00 AM',
            },
          ],
          'recent_customers': const [],
          'top_products': const [],
        }),
        CurrencyFormatter('\$'),
        storeName: 'Test Store',
        userName: 'Admin',
        userRole: 'Manager',
      );

      await tester.pumpWidget(
        ChangeNotifierProvider<ThemeProvider>.value(
          value: themeProvider,
          child: MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: RedesignedMetricDashboard(
                  summary: summaryModel,
                ),
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Find initials 'WR'
      expect(find.text('WR'), findsOneWidget);
      final textWidget = tester.widget<Text>(find.text('WR'));
      expect(textWidget.style?.color, equals(orange));
    });
  });
}
