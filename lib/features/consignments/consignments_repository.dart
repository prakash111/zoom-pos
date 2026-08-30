import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/consignment_model.dart';

class ConsignmentStats {
  ConsignmentStats({required this.total, required this.dispatched, required this.reconciled, required this.finalized});

  factory ConsignmentStats.fromJson(Map<String, dynamic> json) {
    return ConsignmentStats(
      total: (json['total'] as num?)?.toInt() ?? 0,
      dispatched: (json['dispatched'] as num?)?.toInt() ?? 0,
      reconciled: (json['reconciled'] as num?)?.toInt() ?? 0,
      finalized: (json['finalized'] as num?)?.toInt() ?? 0,
    );
  }

  final int total;
  final int dispatched;
  final int reconciled;
  final int finalized;
}

/// Talks to ConsignmentApiController: GET/POST /consignments,
/// GET/DELETE /consignments/{id}, and the dispatch/reconcile/finalize
/// lifecycle actions.
class ConsignmentsRepository {
  ConsignmentsRepository(this._client);

  final ApiClient _client;

  Future<({List<ConsignmentModel> consignments, ConsignmentStats stats})> fetchConsignments({String? status}) async {
    final response = await _client.get(ApiEndpoints.consignments, query: {
      if (status != null && status.isNotEmpty) 'status': status,
    });
    return (
      consignments: (response['consignments'] as List? ?? [])
          .map((e) => ConsignmentModel.fromJson(e as Map<String, dynamic>))
          .toList(),
      stats: ConsignmentStats.fromJson(response['stats'] as Map<String, dynamic>? ?? {}),
    );
  }

  Future<ConsignmentModel> fetchConsignment(String id) async {
    final response = await _client.get(ApiEndpoints.consignment(id));
    return ConsignmentModel.fromJson(response['consignment'] as Map<String, dynamic>);
  }

  Future<ConsignmentModel> createConsignment({
    required String customerId,
    required List<Map<String, dynamic>> items,
    DateTime? dueDate,
    String? notes,
    String status = 'draft',
  }) async {
    final response = await _client.post(ApiEndpoints.consignments, data: {
      'customer_id': customerId,
      'items': items,
      if (dueDate != null) 'due_date': dueDate.toIso8601String().split('T').first,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
      'status': status,
    });
    return ConsignmentModel.fromJson(response['consignment'] as Map<String, dynamic>);
  }

  Future<ConsignmentModel> dispatchConsignment(String id) async {
    final response = await _client.post(ApiEndpoints.consignmentDispatch(id));
    return ConsignmentModel.fromJson(response['consignment'] as Map<String, dynamic>);
  }

  Future<ConsignmentModel> reconcile(String id, List<Map<String, dynamic>> items) async {
    final response = await _client.post(ApiEndpoints.consignmentReconcile(id), data: {'items': items});
    return ConsignmentModel.fromJson(response['consignment'] as Map<String, dynamic>);
  }

  Future<Map<String, dynamic>> finalize(String id, String paymentMethod) async {
    final response = await _client.post(ApiEndpoints.consignmentFinalize(id), data: {'payment_method': paymentMethod});
    return response['sale'] as Map<String, dynamic>;
  }

  Future<void> deleteConsignment(String id) => _client.delete(ApiEndpoints.consignment(id));
}
