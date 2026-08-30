import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/models/cash_register_model.dart';
import 'cash_register_repository.dart';

enum CashRegisterStatus { loading, loaded, error }

/// Drives the cash register screen: current shift status, open/close,
/// movements, and shift history.
class CashRegisterProvider extends ChangeNotifier {
  CashRegisterProvider({required CashRegisterRepository repository}) : _repository = repository;

  final CashRegisterRepository _repository;

  CashRegisterStatus status = CashRegisterStatus.loading;
  String? error;
  CashRegisterModel? current;
  List<CashRegisterModel> history = [];

  bool isSaving = false;
  String? actionError;

  Future<void> loadCurrent() async {
    status = CashRegisterStatus.loading;
    notifyListeners();

    try {
      current = await _repository.fetchCurrent();
      status = CashRegisterStatus.loaded;
    } on ApiException catch (e) {
      error = e.message;
      status = CashRegisterStatus.error;
    }
    notifyListeners();
  }

  Future<void> loadHistory() async {
    try {
      history = await _repository.fetchHistory();
      notifyListeners();
    } on ApiException catch (e) {
      actionError = e.message;
      notifyListeners();
    }
  }

  Future<bool> openRegister({
    required double openingBalance,
    Map<String, int>? openingDenominations,
    String? openingNotes,
    String? terminalId,
  }) async {
    isSaving = true;
    actionError = null;
    notifyListeners();

    try {
      current = await _repository.openRegister(
        openingBalance: openingBalance,
        openingDenominations: openingDenominations,
        openingNotes: openingNotes,
        terminalId: terminalId,
      );
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

  Future<bool> recordTransaction({
    required String type,
    required String category,
    required double amount,
    String? reason,
  }) async {
    final register = current;
    if (register == null) return false;

    isSaving = true;
    actionError = null;
    notifyListeners();

    try {
      await _repository.recordTransaction(
        registerId: register.id,
        type: type,
        category: category,
        amount: amount,
        reason: reason,
      );
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

  Future<CashRegisterModel?> closeRegister({
    required double countedClosingBalance,
    Map<String, int>? closingDenominations,
    String? notes,
  }) async {
    final register = current;
    if (register == null) return null;

    isSaving = true;
    actionError = null;
    notifyListeners();

    try {
      final closed = await _repository.closeRegister(
        registerId: register.id,
        countedClosingBalance: countedClosingBalance,
        closingDenominations: closingDenominations,
        notes: notes,
      );
      current = null;
      isSaving = false;
      notifyListeners();
      return closed;
    } on ApiException catch (e) {
      actionError = e.message;
      isSaving = false;
      notifyListeners();
      return null;
    }
  }
}
