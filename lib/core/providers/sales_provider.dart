import 'package:flutter/foundation.dart';
import '../models/sale_model.dart';
import '../../features/pos/sales_repository.dart';

/// Provider for managing sales list, search queries, due date filters,
/// custom date ranges, and store scoping.
class SalesProvider extends ChangeNotifier {
  SalesProvider(this._repository);

  final SalesRepository _repository;

  List<SaleModel> _sales = [];
  List<SaleModel> get sales {
    if (_filter == 'overdue') {
      final now = DateTime.now();
      final today = DateTime(now.year, now.month, now.day);
      return _sales.where((s) {
        if (s.dueAmount <= 0) return false;
        if (s.paymentStatus.toLowerCase() == 'paid') return false;
        if (s.dueDate == null) return false;
        final d = DateTime(s.dueDate!.year, s.dueDate!.month, s.dueDate!.day);
        return d.isBefore(today);
      }).toList();
    }
    return _sales;
  }

  bool _isLoading = false;
  bool get isLoading => _isLoading;

  String? _error;
  String? get error => _error;

  String _filter = 'all';
  String get filter => _filter;

  String _query = '';
  String get query => _query;

  DateTime? _startDate;
  DateTime? get startDate => _startDate;

  DateTime? _endDate;
  DateTime? get endDate => _endDate;

  int? _storeId;
  int? get storeId => _storeId;

  Future<void> fetchSales({
    String? filter,
    String? query,
    DateTime? startDate,
    DateTime? endDate,
    int? storeId,
    bool notify = true,
  }) async {
    if (filter != null) _filter = filter;
    if (query != null) _query = query;
    if (startDate != null || filter == 'custom_date') _startDate = startDate;
    if (endDate != null || filter == 'custom_date') _endDate = endDate;
    if (storeId != null) _storeId = storeId;

    _isLoading = true;
    _error = null;
    if (notify) notifyListeners();

    try {
      final fetched = await _repository.fetchSales(
        query: _query,
        filter: _filter,
        startDate: _startDate,
        endDate: _endDate,
        storeId: _storeId,
      );

      if (_filter == 'overdue') {
        final now = DateTime.now();
        final today = DateTime(now.year, now.month, now.day);
        _sales = fetched.where((s) {
          if (s.dueAmount <= 0) return false;
          if (s.paymentStatus.toLowerCase() == 'paid') return false;
          if (s.dueDate == null) return false;
          final d = DateTime(s.dueDate!.year, s.dueDate!.month, s.dueDate!.day);
          return d.isBefore(today);
        }).toList();
      } else {
        _sales = fetched;
      }
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  void setFilter(String filter) {
    if (_filter == filter) return;
    _filter = filter;
    fetchSales(filter: filter);
  }

  void setQuery(String query) {
    _query = query;
    fetchSales(query: query);
  }

  void setCustomDateRange(DateTime? start, DateTime? end) {
    _startDate = start;
    _endDate = end;
    _filter = 'custom_date';
    fetchSales(filter: 'custom_date', startDate: start, endDate: end);
  }
}
