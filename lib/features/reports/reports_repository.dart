import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/report_models.dart';

/// Talks to ReportsApiController — one method per web Reports tab, plus the
/// CSV export endpoint.
class ReportsRepository {
  ReportsRepository(this._client);

  final ApiClient _client;

  Map<String, dynamic> _range(DateTime? start, DateTime? end) => {
        if (start != null) 'start_date': start.toIso8601String().split('T').first,
        if (end != null) 'end_date': end.toIso8601String().split('T').first,
      };

  Future<({ReportKpis kpis, SalesSummary summary, List<TopProductStat> topProducts})> fetchSummary(
      DateTime? start, DateTime? end) async {
    final response = await _client.get(ApiEndpoints.reportsSummary, query: _range(start, end));
    return (
      kpis: ReportKpis.fromJson(response['kpis'] as Map<String, dynamic>),
      summary: SalesSummary.fromJson(response['summary'] as Map<String, dynamic>),
      topProducts: (response['top_products'] as List? ?? [])
          .map((e) => TopProductStat.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  Future<DreStatement> fetchProfitLoss(DateTime? start, DateTime? end) async {
    final response = await _client.get(ApiEndpoints.reportsProfitLoss, query: _range(start, end));
    return DreStatement.fromJson(response['dre'] as Map<String, dynamic>);
  }

  Future<List<PaymentMethodStat>> fetchPaymentMethods(DateTime? start, DateTime? end) async {
    final response = await _client.get(ApiEndpoints.reportsPaymentMethods, query: _range(start, end));
    return (response['payment_methods'] as List? ?? [])
        .map((e) => PaymentMethodStat.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<TillClosingStat>> fetchTillClosings(DateTime? start, DateTime? end) async {
    final response = await _client.get(ApiEndpoints.reportsTillClosings, query: _range(start, end));
    return (response['till_closings'] as List? ?? [])
        .map((e) => TillClosingStat.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<CommissionStat>> fetchCommissions(DateTime? start, DateTime? end) async {
    final response = await _client.get(ApiEndpoints.reportsCommissions, query: _range(start, end));
    return (response['commissions'] as List? ?? [])
        .map((e) => CommissionStat.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<AgingReport> fetchAging() async {
    final response = await _client.get(ApiEndpoints.reportsAging);
    return AgingReport.fromJson(response);
  }

  Future<String> exportCsv(String report, DateTime? start, DateTime? end) {
    return _client.getRaw(ApiEndpoints.reportsExport, query: {'report': report, ..._range(start, end)});
  }
}
