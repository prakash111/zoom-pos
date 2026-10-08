import 'package:flutter/foundation.dart';

import '../api/api_client.dart';
import '../models/dashboard_summary_model.dart';

/// Provider managing dashboard metrics, sales overview chart data,
/// and responsive date filter state with store scoping.
class DashboardProvider extends ChangeNotifier {
  DashboardProvider(this._api);

  final ApiClient _api;

  SalesOverviewData? salesOverview;
  String currentPeriod = 'last_7_days';
  int? currentStoreId;
  String? currentStartDate;
  String? currentEndDate;

  double totalSales = 0.0;
  String formattedTotalSales = '';
  int totalOrders = 0;
  String formattedTotalOrders = '';

  String salesTrend = '';
  bool isSalesPositive = true;
  String ordersTrend = '';
  bool isOrdersPositive = true;
  List<double> salesSparkline = [];
  List<double> ordersSparkline = [];
  int totalCustomers = 0;
  String formattedCustomerCount = '';
  String customerTrend = '+12.0%';
  int lowStockCount = 0;
  String formattedLowStockCount = '';

  bool isLoading = false;
  String? error;

  /// Fetches sales overview series and metrics filtered by [period], [storeId],
  /// and optional [startDate] and [endDate] for custom date ranges.
  ///
  /// Valid periods: 'last_7_days', 'this_month', 'quarter', 'all_time', 'custom'.
  Future<void> fetchSalesOverview({
    required String period,
    int? storeId,
    String? startDate,
    String? endDate,
  }) async {
    currentPeriod = period;
    currentStoreId = storeId;
    if (startDate != null && startDate.isNotEmpty) {
      currentStartDate = startDate;
    }
    if (endDate != null && endDate.isNotEmpty) {
      currentEndDate = endDate;
    }

    final effectiveStart = (period == 'custom') ? (startDate ?? currentStartDate) : null;
    final effectiveEnd = (period == 'custom') ? (endDate ?? currentEndDate) : null;

    isLoading = true;
    error = null;
    notifyListeners();

    try {
      final queryParams = <String, dynamic>{
        'period': period,
      };
      if (storeId != null) {
        queryParams['store_id'] = storeId.toString();
      }
      if (effectiveStart != null && effectiveStart.isNotEmpty) {
        queryParams['startDate'] = effectiveStart;
        queryParams['start_date'] = effectiveStart;
      }
      if (effectiveEnd != null && effectiveEnd.isNotEmpty) {
        queryParams['endDate'] = effectiveEnd;
        queryParams['end_date'] = effectiveEnd;
      }

      final response = await _api.getAbsolute(
        '/api/v1/dashboard/sales-chart',
        query: queryParams,
      );

      if (response['success'] == true || response['series'] != null) {
        final rawSeries = (response['series'] as List? ?? [])
            .whereType<Map>()
            .map((e) => SalesOverviewPoint.fromJson(Map<String, dynamic>.from(e)))
            .toList();

        salesOverview = SalesOverviewData(
          ranges: const ['last_7_days', 'this_month', 'quarter', 'all_time', 'custom'],
          currentRange: period,
          series: rawSeries,
        );

        totalSales = (response['total_sales'] as num?)?.toDouble() ?? 0.0;
        formattedTotalSales = response['formatted_total_sales']?.toString() ?? '';
        totalOrders = (response['total_orders'] as num?)?.toInt() ?? 0;
        formattedTotalOrders = response['formatted_total_orders']?.toString() ?? '';

        salesTrend = response['sales_trend']?.toString() ?? '';
        isSalesPositive = response['is_sales_positive'] == true;
        ordersTrend = response['orders_trend']?.toString() ?? '';
        isOrdersPositive = response['is_orders_positive'] == true;

        if (response['total_customers'] != null) {
          totalCustomers = (response['total_customers'] as num).toInt();
          formattedCustomerCount = response['formatted_total_customers']?.toString() ?? '$totalCustomers';
        }
        if (response['customer_trend'] != null) {
          customerTrend = response['customer_trend']?.toString() ?? '+12.0%';
        }
        if (response['low_stock_items'] != null) {
          lowStockCount = (response['low_stock_items'] as num).toInt();
          formattedLowStockCount = response['formatted_low_stock_items']?.toString() ?? '$lowStockCount';
        }

        if (response['sales_sparkline'] is List) {
          salesSparkline = (response['sales_sparkline'] as List)
              .map((e) => (e as num).toDouble())
              .toList();
        } else if (rawSeries.isNotEmpty) {
          salesSparkline = rawSeries.map((e) => e.amount).toList();
        }

        if (response['orders_sparkline'] is List) {
          ordersSparkline = (response['orders_sparkline'] as List)
              .map((e) => (e as num).toDouble())
              .toList();
        } else if (rawSeries.isNotEmpty) {
          ordersSparkline = rawSeries.map((e) => e.orders.toDouble()).toList();
        }
      }
    } catch (e) {
      error = e.toString();
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  /// Alias for [fetchSalesOverview] allowing callers to fetch chart data by period and dates
  Future<void> fetchSalesChart({
    required String period,
    int? storeId,
    String? startDate,
    String? endDate,
  }) =>
      fetchSalesOverview(
        period: period,
        storeId: storeId,
        startDate: startDate,
        endDate: endDate,
      );

  /// Sets sales overview data from external sources (e.g. initial dashboard summary)
  void setSalesOverview(
    SalesOverviewData data, {
    double? initialTotalSales,
    String? initialFormattedTotalSales,
    int? initialTotalOrders,
    String? initialFormattedTotalOrders,
    String? initialSalesTrend,
    bool? initialIsSalesPositive,
    String? initialOrdersTrend,
    bool? initialIsOrdersPositive,
    List<double>? initialSalesSparkline,
    List<double>? initialOrdersSparkline,
    int? initialTotalCustomers,
    String? initialFormattedCustomerCount,
    int? initialLowStockCount,
    String? initialFormattedLowStockCount,
  }) {
    salesOverview = data;
    currentPeriod = data.currentRange;
    if (initialTotalSales != null) totalSales = initialTotalSales;
    if (initialFormattedTotalSales != null) formattedTotalSales = initialFormattedTotalSales;
    if (initialTotalOrders != null) totalOrders = initialTotalOrders;
    if (initialFormattedTotalOrders != null) formattedTotalOrders = initialFormattedTotalOrders;
    if (initialSalesTrend != null) salesTrend = initialSalesTrend;
    if (initialIsSalesPositive != null) isSalesPositive = initialIsSalesPositive;
    if (initialOrdersTrend != null) ordersTrend = initialOrdersTrend;
    if (initialIsOrdersPositive != null) isOrdersPositive = initialIsOrdersPositive;
    if (initialSalesSparkline != null) salesSparkline = initialSalesSparkline;
    if (initialOrdersSparkline != null) ordersSparkline = initialOrdersSparkline;
    if (initialTotalCustomers != null) totalCustomers = initialTotalCustomers;
    if (initialFormattedCustomerCount != null) formattedCustomerCount = initialFormattedCustomerCount;
    if (initialLowStockCount != null) lowStockCount = initialLowStockCount;
    if (initialFormattedLowStockCount != null) formattedLowStockCount = initialFormattedLowStockCount;
    notifyListeners();
  }
}
