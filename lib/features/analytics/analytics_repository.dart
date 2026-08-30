import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/analytics_model.dart';

/// Talks to GET /analytics (PosSyncApiController::analytics) for the
/// dashboard KPIs, payment breakdown, revenue trend, and top products.
class AnalyticsRepository {
  AnalyticsRepository(this._client);

  final ApiClient _client;

  Future<AnalyticsModel> fetchAnalytics() async {
    final response = await _client.get(ApiEndpoints.analytics);
    return AnalyticsModel.fromJson(response);
  }
}
