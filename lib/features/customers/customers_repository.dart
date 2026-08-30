import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/customer_model.dart';
import '../../core/models/ledger_entry_model.dart';

class CustomerLedger {
  CustomerLedger({required this.customer, required this.entries});

  final CustomerModel customer;
  final List<LedgerEntryModel> entries;
}

/// Talks to the customer-ledger endpoints on PosSyncApiController:
/// GET/POST /customers, GET /customers/{id}/ledger, POST /customers/{id}/payment.
class CustomersRepository {
  CustomersRepository(this._client);

  final ApiClient _client;

  Future<List<CustomerModel>> fetchCustomers() async {
    final response = await _client.get(ApiEndpoints.customers);
    return (response['customers'] as List? ?? [])
        .map((e) => CustomerModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> saveCustomer({
    String? externalId,
    required String name,
    String? phone,
    String? email,
    String? document,
    String? address,
    String? city,
    String? state,
  }) {
    return _client.post(ApiEndpoints.customers, data: {
      if (externalId != null) 'external_id': externalId,
      'name': name,
      if (phone != null && phone.isNotEmpty) 'phone': phone,
      if (email != null && email.isNotEmpty) 'email': email,
      if (document != null && document.isNotEmpty) 'document': document,
      if (address != null && address.isNotEmpty) 'address': address,
      if (city != null && city.isNotEmpty) 'city': city,
      if (state != null && state.isNotEmpty) 'state': state,
    });
  }

  Future<CustomerLedger> fetchLedger(String customerId) async {
    final response = await _client.get(ApiEndpoints.customerLedger(customerId));
    return CustomerLedger(
      customer: CustomerModel.fromJson(response['customer'] as Map<String, dynamic>),
      entries: (response['ledger'] as List? ?? [])
          .map((e) => LedgerEntryModel.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  Future<void> recordPayment({
    required String customerId,
    required double amount,
    required String paymentMethod,
    String? reference,
    String? notes,
    String? saleId,
  }) {
    return _client.post(ApiEndpoints.customerPayment(customerId), data: {
      'amount': amount,
      'payment_method': paymentMethod,
      if (reference != null && reference.isNotEmpty) 'reference': reference,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
      if (saleId != null) 'sale_id': saleId,
    });
  }
}
