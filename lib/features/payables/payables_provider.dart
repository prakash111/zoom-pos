import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/models/vendor_bill_model.dart';
import 'payables_repository.dart';

enum PayablesStatus { loading, loaded, error }

/// Drives the payables (Accounts Payable) list screen: load/status-filter,
/// and the create/edit/delete/pay actions.
class PayablesProvider extends ChangeNotifier {
  PayablesProvider({required PayablesRepository repository}) : _repository = repository;

  final PayablesRepository _repository;

  PayablesStatus status = PayablesStatus.loading;
  String? error;
  double totalPayable = 0;
  double overduePayable = 0;
  List<VendorBillModel> _bills = [];

  String? statusFilter;
  bool isSaving = false;
  String? actionError;

  List<VendorBillModel> get filteredBills {
    if (statusFilter == null || statusFilter!.isEmpty) return _bills;
    return _bills.where((b) => b.status == statusFilter).toList();
  }

  Future<void> loadBills() async {
    status = PayablesStatus.loading;
    notifyListeners();

    try {
      final summary = await _repository.fetchPayables();
      _bills = summary.bills;
      totalPayable = summary.totalPayable;
      overduePayable = summary.overduePayable;
      status = PayablesStatus.loaded;
    } on ApiException catch (e) {
      error = e.message;
      status = PayablesStatus.error;
    }
    notifyListeners();
  }

  void setStatusFilter(String? value) {
    statusFilter = value;
    notifyListeners();
  }

  Future<bool> saveBill({
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
    isSaving = true;
    actionError = null;
    notifyListeners();

    try {
      await _repository.saveBill(
        id: id,
        supplierId: supplierId,
        vendorName: vendorName,
        billNumber: billNumber,
        category: category,
        title: title,
        amount: amount,
        taxAmount: taxAmount,
        billDate: billDate,
        dueDate: dueDate,
        notes: notes,
      );
      await loadBills();
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

  Future<bool> deleteBill(String id) async {
    isSaving = true;
    actionError = null;
    notifyListeners();

    try {
      await _repository.deleteBill(id);
      await loadBills();
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

  Future<bool> recordPayment({
    required String billId,
    required double amount,
    required String paymentMethod,
    required DateTime paymentDate,
    String? referenceNumber,
    String? notes,
  }) async {
    isSaving = true;
    actionError = null;
    notifyListeners();

    try {
      await _repository.recordPayment(
        billId: billId,
        amount: amount,
        paymentMethod: paymentMethod,
        paymentDate: paymentDate,
        referenceNumber: referenceNumber,
        notes: notes,
      );
      await loadBills();
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
