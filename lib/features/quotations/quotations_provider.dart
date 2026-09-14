import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/models/quotation_model.dart';
import 'quotations_repository.dart';

enum QuotationsStatus { loading, loaded, error }

/// Drives the quotations list screen: load/search/status-filter, and the
/// create/edit/delete/convert actions.
class QuotationsProvider extends ChangeNotifier {
  QuotationsProvider({required QuotationsRepository repository}) : _repository = repository;

  final QuotationsRepository _repository;

  QuotationsStatus status = QuotationsStatus.loading;
  String? error;
  List<QuotationModel> _quotations = [];

  String searchQuery = '';
  String? statusFilter;
  bool isSaving = false;
  String? actionError;

  List<QuotationModel> get filteredQuotations {
    final query = searchQuery.trim().toLowerCase();
    return _quotations.where((q) {
      final matchesQuery = query.isEmpty ||
          q.quoteNumber.toLowerCase().contains(query) ||
          q.customerName.toLowerCase().contains(query);
      final matchesStatus = statusFilter == null || statusFilter!.isEmpty || q.status == statusFilter;
      return matchesQuery && matchesStatus;
    }).toList();
  }

  Future<void> loadQuotations() async {
    status = QuotationsStatus.loading;
    notifyListeners();

    try {
      _quotations = await _repository.fetchQuotations();
      status = QuotationsStatus.loaded;
    } on ApiException catch (e) {
      error = e.message;
      status = QuotationsStatus.error;
    }
    notifyListeners();
  }

  void setSearchQuery(String value) {
    searchQuery = value;
    notifyListeners();
  }

  void setStatusFilter(String? value) {
    statusFilter = value;
    notifyListeners();
  }

  Future<bool> saveQuotation({
    String? id,
    String? customerId,
    required String customerName,
    String? leadId,
    required List<Map<String, dynamic>> items,
    required double discount,
    required double tax,
    String? notes,
    String? terms,
    DateTime? validUntil,
  }) async {
    isSaving = true;
    actionError = null;
    notifyListeners();

    try {
      await _repository.saveQuotation(
        id: id,
        customerId: customerId,
        customerName: customerName,
        leadId: leadId,
        items: items,
        discount: discount,
        tax: tax,
        notes: notes,
        terms: terms,
        validUntil: validUntil,
      );
      await loadQuotations();
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

  Future<bool> deleteQuotation(String id) async {
    isSaving = true;
    actionError = null;
    notifyListeners();

    try {
      await _repository.deleteQuotation(id);
      await loadQuotations();
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

  Future<bool> convertToSale(String id) async {
    isSaving = true;
    actionError = null;
    notifyListeners();

    try {
      await _repository.convertToSale(id);
      await loadQuotations();
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
