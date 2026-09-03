import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/receivable_model.dart';

/// Talks to GET /receivables/due and POST /receivables/{sale}/remind
/// (PosSyncApiController::dueReceivables / remindReceivable).
class ReceivablesRepository {
  ReceivablesRepository(this._client);

  final ApiClient _client;

  Future<List<ReceivableModel>> fetchDueReceivables() async {
    final response = await _client.get(ApiEndpoints.receivablesDue);
    return (response['receivables'] as List? ?? [])
        .map((e) => ReceivableModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  /// Returns a map with either `{'sent': true}`, `{'fallback_url': ...}`
  /// (WhatsApp, no Cloud API configured), or `{'fallback_mailto': ...}`
  /// (email, no SMTP configured) — the caller decides what to do with it.
  Future<Map<String, dynamic>> sendReminder(String saleId,
      {required String channel}) async {
    final response = await _client.post(
      ApiEndpoints.receivableRemind(saleId),
      data: {'channel': channel},
    );
    return {
      'sent': response['message'] != null,
      if (response['fallback_url'] != null)
        'fallback_url': response['fallback_url'],
      if (response['fallback_mailto'] != null)
        'fallback_mailto': response['fallback_mailto'],
    };
  }

  Future<void> scheduleReminder(String saleId,
      {required DateTime dueDate, required DateTime reminderAt}) {
    return _client.put(ApiEndpoints.receivableReminder(saleId), data: {
      'due_date': dueDate.toIso8601String().split('T').first,
      'reminder_at': reminderAt.toUtc().toIso8601String(),
    });
  }
}
