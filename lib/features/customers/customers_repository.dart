import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/config/app_config.dart';
import '../../core/models/customer_model.dart';
import '../../core/models/ledger_entry_model.dart';
import '../../core/services/sync/offline_writeable.dart';
import '../../core/storage/app_database.dart';

class CustomerLedger {
  CustomerLedger({required this.customer, required this.entries});

  final CustomerModel customer;
  final List<LedgerEntryModel> entries;
}

/// Talks to the customer-ledger endpoints on PosSyncApiController:
/// GET/POST /customers, GET /customers/{id}/ledger, POST /customers/{id}/payment.
class CustomersRepository with OfflineWriteable {
  CustomersRepository(this._client, {AppDatabase? database}) : _database = database ?? AppDatabase.instance;

  final ApiClient _client;
  final AppDatabase _database;

  @override
  AppDatabase get offlineDb => _database;

  /// Fetches customers and refreshes the offline cache, or falls back to it
  /// if the request fails and a cache exists from a previous fetch — so the
  /// customer picker in the POS cart still works offline.
  Future<List<CustomerModel>> fetchCustomers() async {
    try {
      final response = await _client.get(ApiEndpoints.customers);
      final customers = (response['customers'] as List? ?? [])
          .map((e) => CustomerModel.fromJson(e as Map<String, dynamic>))
          .toList();
      await _database.replaceCacheBucket('customers', customers.map((c) => c.toJson()).toList());
      return customers;
    } on ApiException {
      final cached = await _database.readCacheBucket('customers');
      if (cached.isEmpty) rethrow;
      return cached.map(CustomerModel.fromJson).toList();
    }
  }

  /// Offline search over the cached customer bucket — name / phone / email /
  /// document substring, case-insensitive. Used when the live list can't be
  /// fetched so the POS customer picker still filters.
  Future<List<CustomerModel>> searchCached(String query) async {
    final q = query.trim().toLowerCase();
    final cached = await _database.readCacheBucket('customers');
    final all = cached.map(CustomerModel.fromJson);
    if (q.isEmpty) return all.toList();
    return all
        .where((c) =>
            c.name.toLowerCase().contains(q) ||
            c.phone.toLowerCase().contains(q) ||
            c.email.toLowerCase().contains(q) ||
            c.document.toLowerCase().contains(q))
        .toList();
  }

  /// Creates a new customer, or updates an existing one when [externalId] is
  /// passed. Returns the saved customer — from the server on success, or an
  /// optimistic local copy when the write was queued offline.
  Future<CustomerModel> saveCustomer({
    String? externalId,
    required String name,
    String? phone,
    String? email,
    String? document,
    String? address,
    String? city,
    String? state,
    Map<String, dynamic>? customFields,
  }) async {
    final isCreate = externalId == null;
    final ext = externalId ?? newExternalId();

    final body = <String, dynamic>{
      // On an edit let the server match its own row by id first (a web-created
      // customer has only a numeric id, no external_id); still send external_id
      // so an offline replay agrees on identity.
      if (!isCreate) 'id': ext,
      'external_id': ext,
      'name': name,
      if (phone != null && phone.isNotEmpty) 'phone': phone,
      if (email != null && email.isNotEmpty) 'email': email,
      if (document != null && document.isNotEmpty) 'document': document,
      if (address != null && address.isNotEmpty) 'address': address,
      if (city != null && city.isNotEmpty) 'city': city,
      if (state != null && state.isNotEmpty) 'state': state,
      if (customFields != null) 'custom_fields': customFields,
    };

    final cachedRow =
        isCreate ? null : await _database.readCacheItem('customers', ext);
    final baseUpdatedAt =
        DateTime.tryParse(cachedRow?['updated_at']?.toString() ?? '');

    // Shape matches CustomerModel.fromJson so the picker can render an
    // offline-created/edited customer straight from cache.
    final optimisticRow = <String, dynamic>{
      'id': ext,
      'name': name,
      'phone': phone ?? '',
      'email': email ?? '',
      'document': document ?? '',
      'address': address ?? '',
      'city': city ?? '',
      'state': state ?? '',
      'balance_due': (cachedRow?['balance_due'] as num?)?.toDouble() ?? 0,
      'loyalty_points': (cachedRow?['loyalty_points'] as num?)?.toInt() ?? 0,
      'custom_fields': customFields ?? cachedRow?['custom_fields'] ?? const {},
      'updated_at': DateTime.now().toUtc().toIso8601String(),
    };

    return writeThrough<CustomerModel>(
      op: isCreate ? 'create' : 'update',
      entity: 'customer',
      externalId: ext,
      endpoint: ApiEndpoints.customers,
      payload: body,
      cacheBucket: 'customers',
      optimisticRow: optimisticRow,
      baseUpdatedAt: baseUpdatedAt,
      online: () async {
        final response = await _client.post(ApiEndpoints.customers, data: body);
        return CustomerModel.fromJson(
            response['customer'] as Map<String, dynamic>);
      },
      offlineResult: () => CustomerModel.fromJson(optimisticRow),
    );
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
    final paymentId = newExternalId();
    final body = <String, dynamic>{
      // customer_id rides in the body so the queued /sync-batch replay can
      // resolve the customer; the live endpoint takes it from the route and
      // ignores the extra field.
      'customer_id': customerId,
      'amount': amount,
      'payment_method': paymentMethod,
      if (reference != null && reference.isNotEmpty) 'reference': reference,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
      if (saleId != null) 'sale_id': saleId,
    };

    return writeThrough<void>(
      op: 'create',
      entity: 'payment',
      externalId: paymentId,
      endpoint: ApiEndpoints.customerPayment(customerId),
      payload: body,
      online: () =>
          _client.post(ApiEndpoints.customerPayment(customerId), data: body),
      offlineResult: () async {
        // Optimistically knock the payment off the cached balance so the
        // ledger / dues list reflects it before the sync lands. Targets the
        // customer row (keyed by customerId), not this payment's externalId.
        final cached = await _database.readCacheItem('customers', customerId);
        if (cached == null) return;
        final due = (cached['balance_due'] as num?)?.toDouble() ?? 0;
        final next = due - amount < 0 ? 0.0 : due - amount;
        await _database.upsertCacheItems(
            'customers', [{...cached, 'balance_due': next}]);
      },
    );
  }
}
