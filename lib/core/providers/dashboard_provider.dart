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

  double totalSales = 0.0;
  String formattedTotalSales = '';
  int totalOrders = 0;
  String formattedTotalOrders = '';

  bool isLoading = false;
  String? error;

  /// Fetches sales overview series and metrics filtered by [period] and [storeId].
  ///
  /// Valid periods: 'last_7_days', 'this_month', 'quarter'.
  Future<void> fetchSalesOverview({
    required String period,
    int? storeId,
  }) async {
    currentPeriod = period;
    currentStoreId = storeId;
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
          ranges: const ['last_7_days', 'this_month', 'quarter'],
          currentRange: period,
          series: rawSeries,
        );

        totalSales = (response['total_sales'] as num?)?.toDouble() ?? 0.0;
        formattedTotalSales = response['formatted_total_sales']?.toString() ?? '';
        totalOrders = (response['total_orders'] as num?)?.toInt() ?? 0;
        formattedTotalOrders = response['formatted_total_orders']?.toString() ?? '';
      }
    } catch (e) {
      error = e.toString();
    } finally {
      isLoading = false;
      notifyListeners();
    }
  }

  /// Sets sales overview data from external sources (e.g. initial dashboard summary)
  void setSalesOverview(SalesOverviewData data) {
    salesOverview = data;
    currentPeriod = data.currentRange;
    notifyListeners();
  }
}
