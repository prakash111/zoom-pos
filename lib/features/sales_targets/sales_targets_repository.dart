import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/sales_target_model.dart';

/// Talks to SalesTargetApiController: GET/POST /sales-targets.
class SalesTargetsRepository {
  SalesTargetsRepository(this._client);

  final ApiClient _client;

  Future<SalesTargetsBundle> fetchTargets({required int year, required int month}) async {
    final response = await _client.get(ApiEndpoints.salesTargets, query: {'year': year, 'month': month});
    return SalesTargetsBundle.fromJson(response);
  }

  Future<void> saveTargets({
    required int year,
    required int month,
    required double companyTargetAmount,
    String? companyNotes,
    required List<Map<String, dynamic>> userTargets,
  }) {
    return _client.post(ApiEndpoints.salesTargets, data: {
      'year': year,
      'month': month,
      'company_target_amount': companyTargetAmount,
      if (companyNotes != null && companyNotes.isNotEmpty) 'company_notes': companyNotes,
      'user_targets': userTargets,
    });
  }
}
