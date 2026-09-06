import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/service_order_model.dart';

/// Talks to ServiceOrderApiController: GET/POST /service-orders,
/// GET/PUT/DELETE /service-orders/{id}, POST /service-orders/{id}/status.
class ServiceOrdersRepository {
  ServiceOrdersRepository(this._client);

  final ApiClient _client;

  Future<({List<ServiceOrderModel> orders, Map<String, int> counts})> fetchOrders({
    String? status,
    String? priority,
    String? search,
  }) async {
    final response = await _client.get(ApiEndpoints.serviceOrders, query: {
      if (status != null && status.isNotEmpty) 'status': status,
      if (priority != null && priority.isNotEmpty) 'priority': priority,
      if (search != null && search.isNotEmpty) 'search': search,
    });
    final counts = (response['counts'] as Map<String, dynamic>? ?? {}).map((k, v) => MapEntry(k, (v as num).toInt()));
    return (
      orders: (response['service_orders'] as List? ?? [])
          .map((e) => ServiceOrderModel.fromJson(e as Map<String, dynamic>))
          .toList(),
      counts: counts,
    );
  }

  Future<ServiceOrderModel> saveOrder({
    String? id,
    String? customerId,
    required String customerName,
    String? customerPhone,
    String? customerEmail,
    required String equipmentName,
    String? brandModel,
    String? serialNumber,
    required String reportedDefect,
    String? technicalDiagnosis,
    required List<Map<String, dynamic>> partsUsed,
    required double laborCost,
    required double discount,
    required String status,
    required String priority,
    String? warrantyPeriod,
    String? warrantyTerms,
    String? technicianId,
    String? notes,
    Map<String, dynamic>? extraAttributes,
  }) async {
    final data = {
      if (customerId != null) 'customer_id': customerId,
      'customer_name': customerName,
      if (customerPhone != null && customerPhone.isNotEmpty) 'customer_phone': customerPhone,
      if (customerEmail != null && customerEmail.isNotEmpty) 'customer_email': customerEmail,
      'equipment_name': equipmentName,
      if (brandModel != null && brandModel.isNotEmpty) 'brand_model': brandModel,
      if (serialNumber != null && serialNumber.isNotEmpty) 'serial_number': serialNumber,
      'reported_defect': reportedDefect,
      if (technicalDiagnosis != null && technicalDiagnosis.isNotEmpty) 'technical_diagnosis': technicalDiagnosis,
      'parts_used': partsUsed,
      'labor_cost': laborCost,
      'discount': discount,
      'status': status,
      'priority': priority,
      if (warrantyPeriod != null && warrantyPeriod.isNotEmpty) 'warranty_period': warrantyPeriod,
      if (warrantyTerms != null && warrantyTerms.isNotEmpty) 'warranty_terms': warrantyTerms,
      if (technicianId != null) 'technician_id': technicianId,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
      if (extraAttributes != null && extraAttributes.isNotEmpty) 'extra_attributes': extraAttributes,
    };

    final response = id == null
        ? await _client.post(ApiEndpoints.serviceOrders, data: data)
        : await _client.put(ApiEndpoints.serviceOrder(id), data: data);
    return ServiceOrderModel.fromJson(response['service_order'] as Map<String, dynamic>);
  }

  Future<ServiceOrderModel> fetchOrder(String id) async {
    final response = await _client.get(ApiEndpoints.serviceOrder(id));
    return ServiceOrderModel.fromJson(response['service_order'] as Map<String, dynamic>);
  }

  Future<ServiceOrderModel> updateStatus(String id, String status) async {
    final response = await _client.post(ApiEndpoints.serviceOrderStatus(id), data: {'status': status});
    return ServiceOrderModel.fromJson(response['service_order'] as Map<String, dynamic>);
  }

  Future<void> deleteOrder(String id) => _client.delete(ApiEndpoints.serviceOrder(id));
}
