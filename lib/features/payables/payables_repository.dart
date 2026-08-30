import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/vendor_bill_model.dart';

class PayablesSummary {
  PayablesSummary({required this.totalPayable, required this.overduePayable, required this.bills});

  final double totalPayable;
  final double overduePayable;
  final List<VendorBillModel> bills;
}

/// Talks to PayablesApiController: GET/POST /payables, PUT/DELETE
/// /payables/{id}, POST /payables/{id}/pay.
class PayablesRepository {
  PayablesRepository(this._client);

  final ApiClient _client;

  Future<PayablesSummary> fetchPayables({String? status, String? category}) async {
    final response = await _client.get(ApiEndpoints.payables, query: {
      if (status != null && status.isNotEmpty) 'status': status,
      if (category != null && category.isNotEmpty) 'category': category,
    });
    return PayablesSummary(
      totalPayable: (response['total_payable'] as num?)?.toDouble() ?? 0,
      overduePayable: (response['overdue_payable'] as num?)?.toDouble() ?? 0,
      bills: (response['bills'] as List? ?? [])
          .map((e) => VendorBillModel.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  Future<VendorBillModel> saveBill({
    String? id,
    String? supplierId,
    String? vendorName,
    String? billNumber,
    required String category,
    String? title,
    required double amount,
    required double taxAmount,
    required DateTime billDate,
    DateTime? dueDate,
    String? notes,
  }) async {
    final data = {
      if (supplierId != null) 'supplier_id': supplierId,
      if (vendorName != null && vendorName.isNotEmpty) 'vendor_name': vendorName,
      if (billNumber != null && billNumber.isNotEmpty) 'bill_number': billNumber,
      'category': category,
      if (title != null && title.isNotEmpty) 'title': title,
      'amount': amount,
      'tax_amount': taxAmount,
      'bill_date': billDate.toIso8601String().split('T').first,
      if (dueDate != null) 'due_date': dueDate.toIso8601String().split('T').first,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
    };

    final response = id == null
        ? await _client.post(ApiEndpoints.payables, data: data)
        : await _client.put(ApiEndpoints.payable(id), data: data);

    return VendorBillModel.fromJson(response['bill'] as Map<String, dynamic>);
  }

  Future<void> deleteBill(String id) {
    return _client.delete(ApiEndpoints.payable(id));
  }

  Future<VendorBillModel> recordPayment({
    required String billId,
    required double amount,
    required String paymentMethod,
    required DateTime paymentDate,
    String? referenceNumber,
    String? notes,
  }) async {
    final response = await _client.post(ApiEndpoints.payablePay(billId), data: {
      'amount': amount,
      'payment_method': paymentMethod,
      'payment_date': paymentDate.toIso8601String().split('T').first,
      if (referenceNumber != null && referenceNumber.isNotEmpty) 'reference_number': referenceNumber,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
    });
    return VendorBillModel.fromJson(response['bill'] as Map<String, dynamic>);
  }
}
