import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/models/customer_model.dart';
import 'customers_repository.dart';

enum CustomersStatus { loading, loaded, error }

/// Drives the customers list screen: load/search, and the create/edit
/// action. Ledger viewing and payment recording are handled locally by
/// [CustomerLedgerScreen] since they're scoped to a single customer.
class CustomersProvider extends ChangeNotifier {
  CustomersProvider({required CustomersRepository repository}) : _repository = repository;

  final CustomersRepository _repository;

  CustomersStatus status = CustomersStatus.loading;
  String? error;
  List<CustomerModel> _customers = [];

  String searchQuery = '';
  bool isSaving = false;
  String? actionError;

  List<CustomerModel> get filteredCustomers {
    final query = searchQuery.trim().toLowerCase();
    if (query.isEmpty) return _customers;
    return _customers
        .where((c) => c.name.toLowerCase().contains(query) || c.phone.toLowerCase().contains(query))
        .toList();
  }

  Future<void> loadCustomers() async {
    status = CustomersStatus.loading;
    notifyListeners();

    try {
      _customers = await _repository.fetchCustomers();
      status = CustomersStatus.loaded;
    } on ApiException catch (e) {
      error = e.message;
      status = CustomersStatus.error;
    }
    notifyListeners();
  }

  void setSearchQuery(String value) {
    searchQuery = value;
    notifyListeners();
  }

  Future<bool> saveCustomer({
    String? externalId,
    required String name,
    String? phone,
    String? email,
    String? document,
    String? address,
    String? city,
    String? state,
  }) async {
    isSaving = true;
    actionError = null;
    notifyListeners();

    try {
      await _repository.saveCustomer(
        externalId: externalId,
        name: name,
        phone: phone,
        email: email,
        document: document,
        address: address,
        city: city,
        state: state,
      );
      await loadCustomers();
      isSaving = false;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      actionError = e.message;
      isSaving = false;
      notifyListeners();
      return false;
    }
  }
}
