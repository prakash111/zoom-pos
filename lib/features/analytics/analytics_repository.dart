import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/analytics_model.dart';

/// The dashboard "Filter" date-range presets. `custom` carries an explicit
/// [DateTimeRange].
enum AnalyticsRange {
  today('today', 'Today'),
  yesterday('yesterday', 'Yesterday'),
  last7('last7', 'Last 7 Days'),
  last30('last30', 'Last 30 Days'),
  thisMonth('month', 'This Month'),
  lastMonth('last_month', 'Last Month'),
  thisYear('year', 'This Year'),
  allTime('all', 'All Time'),
  custom('custom', 'Custom Range');

  const AnalyticsRange(this.key, this.label);

  final String key;
  final String label;
}

/// Talks to GET /analytics (PosSyncApiController::analytics) for the
/// dashboard KPIs, payment breakdown, revenue trend, top products, and the
/// redesigned-dashboard extras.
class AnalyticsRepository {
  AnalyticsRepository(this._client);

  final ApiClient _client;

  Future<AnalyticsModel> fetchAnalytics({
    AnalyticsRange range = AnalyticsRange.thisMonth,
    DateTime? from,
    DateTime? to,
  }) async {
    final query = <String, dynamic>{'range': range.key};
    if (range == AnalyticsRange.custom && from != null && to != null) {
      query['from'] = _ymd(from);
      query['to'] = _ymd(to);
    }
    final response = await _client.get(ApiEndpoints.analytics, query: query);
    return AnalyticsModel.fromJson(response);
  }

  static String _ymd(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
}
