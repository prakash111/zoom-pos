import 'package:flutter/foundation.dart';

import '../../core/api/api_exception.dart';
import '../../core/models/report_models.dart';
import 'reports_repository.dart';

enum ReportsStatus { loading, loaded, error }

/// Drives the tabbed Reports screen. Mirrors the web Reports page's default
/// date range (this calendar month) and re-fetches the active tab whenever
/// the date range changes; each tab's data is cached until the range changes.
class ReportsProvider extends ChangeNotifier {
  ReportsProvider({required ReportsRepository repository}) : _repository = repository {
    final now = DateTime.now();
    startDate = DateTime(now.year, now.month, 1);
    endDate = DateTime(now.year, now.month + 1, 0);
  }

  final ReportsRepository _repository;

  ReportsStatus status = ReportsStatus.loading;
  String? error;

  late DateTime? startDate;
  late DateTime? endDate;

  ReportKpis? kpis;
  SalesSummary? summary;
  List<TopProductStat> topProducts = [];
  DreStatement? dre;
  List<PaymentMethodStat> paymentMethods = [];
  List<TillClosingStat> tillClosings = [];
  List<CommissionStat> commissions = [];
  AgingReport? aging;

  void setDateRange(DateTime? start, DateTime? end) {
    startDate = start;
    endDate = end;
    loadSummary();
  }

  Future<void> loadSummary() async {
    status = ReportsStatus.loading;
    notifyListeners();

    try {
      final result = await _repository.fetchSummary(startDate, endDate);
      kpis = result.kpis;
      summary = result.summary;
      topProducts = result.topProducts;
      status = ReportsStatus.loaded;
    } on ApiException catch (e) {
      error = e.message;
      status = ReportsStatus.error;
    }
    notifyListeners();
  }

  Future<void> loadProfitLoss() async {
    try {
      dre = await _repository.fetchProfitLoss(startDate, endDate);
      notifyListeners();
    } on ApiException catch (e) {
      error = e.message;
      notifyListeners();
    }
  }

  Future<void> loadPaymentMethods() async {
    try {
      paymentMethods = await _repository.fetchPaymentMethods(startDate, endDate);
      notifyListeners();
    } on ApiException catch (e) {
      error = e.message;
      notifyListeners();
    }
  }

  Future<void> loadTillClosings() async {
    try {
      tillClosings = await _repository.fetchTillClosings(startDate, endDate);
      notifyListeners();
    } on ApiException catch (e) {
      error = e.message;
      notifyListeners();
    }
  }

  Future<void> loadCommissions() async {
    try {
      commissions = await _repository.fetchCommissions(startDate, endDate);
      notifyListeners();
    } on ApiException catch (e) {
      error = e.message;
      notifyListeners();
    }
  }

  Future<void> loadAging() async {
    try {
      aging = await _repository.fetchAging();
      notifyListeners();
    } on ApiException catch (e) {
      error = e.message;
      notifyListeners();
    }
  }

  Future<String> exportCsv(String report) => _repository.exportCsv(report, startDate, endDate);
}
