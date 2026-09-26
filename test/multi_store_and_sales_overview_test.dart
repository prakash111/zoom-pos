import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/models/dashboard_summary_model.dart';
import 'package:zoom_pos_mobile/core/providers/dashboard_provider.dart';
import 'package:zoom_pos_mobile/core/models/product_model.dart';
import 'package:zoom_pos_mobile/features/pos/cart_item.dart';
import 'package:zoom_pos_mobile/features/pos/screens/invoice_preview_screen.dart';
import 'package:zoom_pos_mobile/screens/dashboard/widgets/sales_overview_chart.dart';
import 'package:zoom_pos_mobile/screens/pos/invoice_view_screen.dart';
import 'package:zoom_pos_mobile/screens/pos/receipt_preview_screen.dart';
import 'package:zoom_pos_mobile/widgets/modals/create_store_modal.dart';

class _MockApiClient extends Fake implements ApiClient {
  String? lastPeriod;
  String? lastStoreId;

  @override
  int? get activeStoreId => 42;

  @override
  Future<Map<String, dynamic>> getAbsolute(String path,
      {Map<String, dynamic>? query}) async {
    if (path.contains('sales-chart')) {
      lastPeriod = query?['period']?.toString();
      lastStoreId = query?['store_id']?.toString();

      return {
        'success': true,
        'period': lastPeriod ?? 'last_7_days',
        'total_sales': 12500.50,
        'formatted_total_sales': '\$12,500.50',
        'total_orders': 45,
        'formatted_total_orders': '45',
        'series': [
          {'date': '2026-09-16', 'label': 'Wed', 'day': 'Wed', 'amount': 1500.0},
          {'date': '2026-09-17', 'label': 'Thu', 'day': 'Thu', 'amount': 2200.0},
          {'date': '2026-09-18', 'label': 'Fri', 'day': 'Fri', 'amount': 1800.0},
          {'date': '2026-09-19', 'label': 'Sat', 'day': 'Sat', 'amount': 3100.0},
          {'date': '2026-09-20', 'label': 'Sun', 'day': 'Sun', 'amount': 2400.0},
          {'date': '2026-09-21', 'label': 'Mon', 'day': 'Mon', 'amount': 1500.5},
        ],
      };
    }
    return {'success': true};
  }

  @override
  Future<Map<String, dynamic>> post(String path,
      {dynamic data, Map<String, dynamic>? query}) async {
    return {
      'success': true,
      'data': {
        'id': 99,
        'name': data['name'] ?? 'New Test Store',
        'code': data['code'] ?? 'TEST-01',
        'is_primary': false,
        'is_current': true,
        'is_active': true,
      }
    };
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('Multi-Store Isolation & Minimal Creation Modal Tests', () {
    testWidgets('CreateStoreModal renders only Store Name and Branch Code',
        (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: CreateStoreModal(),
          ),
        ),
      );

      // Verify required and optional fields
      expect(find.text('Store name'), findsOneWidget);
      expect(find.text('Branch code (optional)'), findsOneWidget);
      expect(find.text('Cancel'), findsOneWidget);
      expect(find.text('Create and switch'), findsOneWidget);

      // Verify removed extraneous fields are NOT present
      expect(find.text('Contact phone'), findsNothing);
      expect(find.text('Address'), findsNothing);
      expect(find.text('Tax number'), findsNothing);
      expect(find.text('Tax ID'), findsNothing);
    });
  });

  group('Sales Overview Interactive Date Filter Tests', () {
    testWidgets(
        'Tapping filter pills highlights active pill and triggers fetchSalesOverview',
        (tester) async {
      final mockApi = _MockApiClient();
      final dashboardProvider = DashboardProvider(mockApi);

      const initialData = SalesOverviewData(
        ranges: ['last_7_days', 'this_month', 'quarter'],
        currentRange: 'last_7_days',
        series: [
          SalesOverviewPoint(
              date: '2026-09-20', label: 'Sun', day: 'Sun', amount: 100),
          SalesOverviewPoint(
              date: '2026-09-21', label: 'Mon', day: 'Mon', amount: 200),
        ],
      );

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<DashboardProvider>.value(
              value: dashboardProvider,
            ),
          ],
          child: const MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: SalesOverviewChart(
                  initialData: initialData,
                  storeId: 42,
                ),
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Sales Overview'), findsOneWidget);
      expect(find.text('Last 7 Days'), findsOneWidget);
      expect(find.text('This Month'), findsOneWidget);
      expect(find.text('Quarter'), findsOneWidget);
      expect(find.text('Custom'), findsOneWidget);

      // Tap "This Month"
      await tester.tap(find.text('This Month'));
      await tester.pumpAndSettle();

      expect(mockApi.lastPeriod, 'this_month');
      expect(mockApi.lastStoreId, '42');
      expect(dashboardProvider.currentPeriod, 'this_month');

      // Tap "Quarter"
      await tester.tap(find.text('Quarter'));
      await tester.pumpAndSettle();

      expect(mockApi.lastPeriod, 'quarter');
      expect(mockApi.lastStoreId, '42');
      expect(dashboardProvider.currentPeriod, 'quarter');
    });

    testWidgets(
        'Tapping "Custom" opens the POS date range picker dialog and fetches custom range',
        (tester) async {
      final mockApi = _MockApiClient();
      final dashboardProvider = DashboardProvider(mockApi);

      const initialData = SalesOverviewData(
        ranges: ['last_7_days', 'this_month', 'quarter'],
        currentRange: 'last_7_days',
        series: [
          SalesOverviewPoint(
              date: '2026-09-20', label: 'Sun', day: 'Sun', amount: 100),
          SalesOverviewPoint(
              date: '2026-09-21', label: 'Mon', day: 'Mon', amount: 200),
        ],
      );

      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<DashboardProvider>.value(
              value: dashboardProvider,
            ),
          ],
          child: const MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: SalesOverviewChart(
                  initialData: initialData,
                  storeId: 42,
                ),
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      final customFinder = find.text('Custom');
      await tester.ensureVisible(customFinder);
      await tester.pumpAndSettle();

      // Tap "Custom" pill
      await tester.tap(customFinder);
      await tester.pumpAndSettle();

      // Verify DateRangePickerDialog is launched
      expect(find.byType(DateRangePickerDialog), findsOneWidget);
      final saveFinder = find.textContaining(RegExp(r'Save', caseSensitive: false));
      expect(saveFinder, findsOneWidget);

      // Tap Save to confirm date selection
      await tester.tap(saveFinder);
      await tester.pumpAndSettle();

      expect(mockApi.lastPeriod, 'custom');
      expect(mockApi.lastStoreId, '42');
      expect(dashboardProvider.currentPeriod, 'custom');
    });
  });

  group('POS Preview Direct Unified Dispatch Routing Tests', () {
    late InvoicePreviewData sampleInvoiceData;

    setUp(() {
      sampleInvoiceData = InvoicePreviewData(
        companyName: 'Metro Retail',
        taxId: '29ABCDE1234F1Z5',
        currencySymbol: '\$',
        documentType: 'Receipt',
        customerName: 'Aarav Patel',
        customerPhone: '+919876543210',
        discount: 0.0,
        taxTotal: 0.0,
        subtotal: 130.0,
        grandTotal: 130.0,
        items: [
          CartItem(
            product: ProductModel.fromJson({
              'id': 1,
              'name': 'Organic Milk 1L',
              'sale_price': 65.0,
              'cost_price': 50.0,
              'current_stock': 10,
              'minimum_stock': 2,
              'unit': 'bottle',
              'category_id': 1,
              'category_name': 'Dairy',
              'brand_name': 'FreshFarm',
              'tax_rate': 0,
              'active': true,
              'is_low_stock': false,
            }),
            quantity: 2,
          ),
        ],
      );
    });

    testWidgets('ReceiptPreviewScreen Share button opens Unified Dispatch Sheet',
        (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: ReceiptPreviewScreen(data: sampleInvoiceData),
        ),
      );

      await tester.pumpAndSettle();

      // Find share buttons (top app bar and bottom action bar)
      final shareButtons = find.byIcon(Icons.share_outlined);
      expect(shareButtons, findsNWidgets(2));

      await tester.tap(shareButtons.first);
      await tester.pumpAndSettle();

      // Verify Unified Dispatch Sheet opened
      expect(find.text('RCP-PREVIEW'), findsOneWidget);
      expect(find.text('PDF Preview'), findsOneWidget);
      expect(find.text('Thermal Print'), findsOneWidget);
      expect(find.text('Send to Selected Channels'), findsOneWidget);
    });

    testWidgets('InvoiceViewScreen Share button opens Unified Dispatch Sheet',
        (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: InvoiceViewScreen(data: sampleInvoiceData),
        ),
      );

      await tester.pumpAndSettle();

      // Find share buttons (top app bar and bottom action bar)
      final shareButtons = find.byIcon(Icons.share_outlined);
      expect(shareButtons, findsNWidgets(2));

      await tester.tap(shareButtons.last);
      await tester.pumpAndSettle();

      // Verify Unified Dispatch Sheet opened
      expect(find.text('RECEIPT-INV'), findsOneWidget);
      expect(find.text('PDF Preview'), findsOneWidget);
      expect(find.text('Thermal Print'), findsOneWidget);
      expect(find.text('Send to Selected Channels'), findsOneWidget);
    });
  });
}
