import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/models/analytics_model.dart';
import 'package:zoom_pos_mobile/features/analytics/analytics_repository.dart';

class _RecordingApiClient extends Fake implements ApiClient {
  Map<String, dynamic>? lastQuery;
  String? lastPath;

  @override
  Future<Map<String, dynamic>> get(String path,
      {Map<String, dynamic>? query}) async {
    lastPath = path;
    lastQuery = query;
    return {
      'range': {'key': 'today', 'label': 'Today'},
      'kpis': {
        'range_revenue': 500.0,
        'range_orders': 6,
        'prev_range_revenue': 400.0,
        'prev_range_orders': 5,
        'month_revenue': 9000.0,
        'month_orders': 90,
      },
    };
  }
}

void main() {
  test('AnalyticsRepository forwards the range key + custom dates', () async {
    final api = _RecordingApiClient();
    final repo = AnalyticsRepository(api);

    await repo.fetchAnalytics(range: AnalyticsRange.last7);
    expect(api.lastQuery, {'range': 'last7'});

    await repo.fetchAnalytics(
      range: AnalyticsRange.custom,
      from: DateTime(2026, 1, 2),
      to: DateTime(2026, 1, 31),
    );
    expect(api.lastQuery,
        {'range': 'custom', 'from': '2026-01-02', 'to': '2026-01-31'});
  });

  test('AnalyticsModel prefers range_* and falls back to month_* + deltas', () {
    final withRange = AnalyticsModel.fromJson({
      'range': {'label': 'Today'},
      'kpis': {
        'range_revenue': 500.0,
        'range_orders': 6,
        'prev_range_revenue': 400.0,
        'prev_range_orders': 5,
        'month_revenue': 9000.0,
        'month_orders': 90,
      },
    });
    expect(withRange.rangeLabel, 'Today');
    expect(withRange.rangeRevenue, 500.0);
    expect(withRange.rangeOrders, 6);
    expect((withRange.revenueDelta * 100).round(), 25); // (500-400)/400

    final legacy = AnalyticsModel.fromJson({
      'kpis': {
        'month_revenue': 9000.0,
        'month_orders': 90,
        'prev_month_revenue': 6000.0,
        'prev_month_orders': 60,
      },
    });
    expect(legacy.rangeRevenue, 9000.0);
    expect(legacy.rangeOrders, 90);
    expect((legacy.revenueDelta * 100).round(), 50); // (9000-6000)/6000
  });
}
