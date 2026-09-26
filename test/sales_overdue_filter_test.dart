import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/models/sale_model.dart';
import 'package:zoom_pos_mobile/core/providers/sales_provider.dart';
import 'package:zoom_pos_mobile/features/pos/sales_repository.dart';

class MockSalesRepository extends Fake implements SalesRepository {
  List<SaleModel> salesToReturn = [];

  @override
  Future<List<SaleModel>> fetchSales({
    String? query,
    String? filter,
    DateTime? startDate,
    DateTime? endDate,
    int? storeId,
  }) async {
    return salesToReturn;
  }
}

void main() {
  group('SalesProvider Overdue Filter Tests', () {
    late MockSalesRepository repo;
    late SalesProvider provider;

    setUp(() {
      repo = MockSalesRepository();
      provider = SalesProvider(repo);
    });

    test('overdue filter strictly excludes zero-due, paid, and future-due sales',
        () async {
      final pastDate = DateTime.now().subtract(const Duration(days: 5));
      final futureDate = DateTime.now().add(const Duration(days: 5));

      final overdueUnpaid = SaleModel(
        id: '1',
        saleNumber: 'OVERDUE-01',
        customerName: 'Customer A',
        items: [],
        total: 100.0,
        discount: 0,
        tax: 0,
        paymentMethod: 'cash',
        paymentStatus: 'pending',
        paidAmount: 0.0,
        dueAmount: 100.0,
        status: 'completed',
        createdAt: pastDate,
        dueDate: pastDate,
      );

      final overduePartiallyPaid = SaleModel(
        id: '2',
        saleNumber: 'OVERDUE-02',
        customerName: 'Customer B',
        items: [],
        total: 100.0,
        discount: 0,
        tax: 0,
        paymentMethod: 'cash',
        paymentStatus: 'partial',
        paidAmount: 40.0,
        dueAmount: 60.0,
        status: 'completed',
        createdAt: pastDate,
        dueDate: pastDate,
      );

      final overduePaid = SaleModel(
        id: '3',
        saleNumber: 'DEMO-RETAIL-QUO-01',
        customerName: 'Customer C',
        items: [],
        total: 100.0,
        discount: 0,
        tax: 0,
        paymentMethod: 'cash',
        paymentStatus: 'paid',
        paidAmount: 100.0,
        dueAmount: 0.0,
        status: 'completed',
        createdAt: pastDate,
        dueDate: pastDate,
      );

      final zeroDueUnpaid = SaleModel(
        id: '4',
        saleNumber: 'ZERO-DUE',
        customerName: 'Customer D',
        items: [],
        total: 0.0,
        discount: 0,
        tax: 0,
        paymentMethod: 'cash',
        paymentStatus: 'pending',
        paidAmount: 0.0,
        dueAmount: 0.0,
        status: 'completed',
        createdAt: pastDate,
        dueDate: pastDate,
      );

      final futureDue = SaleModel(
        id: '5',
        saleNumber: 'FUTURE-01',
        customerName: 'Customer E',
        items: [],
        total: 200.0,
        discount: 0,
        tax: 0,
        paymentMethod: 'cash',
        paymentStatus: 'pending',
        paidAmount: 0.0,
        dueAmount: 200.0,
        status: 'completed',
        createdAt: DateTime.now(),
        dueDate: futureDate,
      );

      repo.salesToReturn = [
        overdueUnpaid,
        overduePartiallyPaid,
        overduePaid,
        zeroDueUnpaid,
        futureDue,
      ];

      await provider.fetchSales(filter: 'overdue');

      expect(provider.sales.length, 2);
      expect(provider.sales.map((s) => s.saleNumber).toList(), [
        'OVERDUE-01',
        'OVERDUE-02',
      ]);
      expect(provider.sales.any((s) => s.saleNumber == 'DEMO-RETAIL-QUO-01'),
          isFalse);
      expect(provider.sales.any((s) => s.dueAmount <= 0), isFalse);
      expect(provider.sales.any((s) => s.paymentStatus.toLowerCase() == 'paid'),
          isFalse);
    });
  });
}
