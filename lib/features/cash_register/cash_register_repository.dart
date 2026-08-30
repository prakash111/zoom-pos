import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/cash_register_model.dart';

/// Talks to CashRegisterApiController: current/open/{id}/transaction/{id}/close/history/{id}.
class CashRegisterRepository {
  CashRegisterRepository(this._client);

  final ApiClient _client;

  Future<CashRegisterModel?> fetchCurrent() async {
    final response = await _client.get(ApiEndpoints.cashRegisterCurrent);
    final register = response['register'];
    return register is Map<String, dynamic> ? CashRegisterModel.fromJson(register) : null;
  }

  Future<CashRegisterModel> openRegister({
    required double openingBalance,
    Map<String, int>? openingDenominations,
    String? openingNotes,
    String? terminalId,
  }) async {
    final response = await _client.post(ApiEndpoints.cashRegisterOpen, data: {
      'opening_balance': openingBalance,
      if (openingDenominations != null && openingDenominations.isNotEmpty)
        'opening_denominations': openingDenominations,
      if (openingNotes != null && openingNotes.isNotEmpty) 'opening_notes': openingNotes,
      if (terminalId != null && terminalId.isNotEmpty) 'terminal_id': terminalId,
    });
    return CashRegisterModel.fromJson(response['register'] as Map<String, dynamic>);
  }

  Future<CashRegisterTransactionModel> recordTransaction({
    required String registerId,
    required String type,
    required String category,
    required double amount,
    String? reason,
  }) async {
    final response = await _client.post(ApiEndpoints.cashRegisterTransaction(registerId), data: {
      'type': type,
      'category': category,
      'amount': amount,
      if (reason != null && reason.isNotEmpty) 'reason': reason,
    });
    return CashRegisterTransactionModel.fromJson(response['transaction'] as Map<String, dynamic>);
  }

  Future<CashRegisterModel> closeRegister({
    required String registerId,
    required double countedClosingBalance,
    Map<String, int>? closingDenominations,
    String? notes,
  }) async {
    final response = await _client.post(ApiEndpoints.cashRegisterClose(registerId), data: {
      'counted_closing_balance': countedClosingBalance,
      if (closingDenominations != null && closingDenominations.isNotEmpty)
        'closing_denominations': closingDenominations,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
    });
    return CashRegisterModel.fromJson(response['register'] as Map<String, dynamic>);
  }

  Future<List<CashRegisterModel>> fetchHistory() async {
    final response = await _client.get(ApiEndpoints.cashRegisterHistory);
    return (response['registers'] as List? ?? [])
        .map((e) => CashRegisterModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<({CashRegisterModel register, Map<String, dynamic> metrics, List<CashRegisterTransactionModel> transactions})>
      fetchDetail(String registerId) async {
    final response = await _client.get(ApiEndpoints.cashRegister(registerId));
    return (
      register: CashRegisterModel.fromJson(response['register'] as Map<String, dynamic>),
      metrics: response['metrics'] as Map<String, dynamic>? ?? {},
      transactions: (response['transactions'] as List? ?? [])
          .map((e) => CashRegisterTransactionModel.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}
