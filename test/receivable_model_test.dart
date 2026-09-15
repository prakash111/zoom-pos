import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/models/receivable_model.dart';

void main() {
  test('receivable keeps the native POS sheet action and document identity',
      () {
    final receivable = ReceivableModel.fromJson({
      'sale_id': 'external-sale-42',
      'document_id': '42',
      'document_type': 'sale',
      'sale_number': 'POS-63776202',
      'customer_name': 'Ava Thompson',
      'total': 150,
      'paid_amount': 50,
      'due_amount': 100,
      'status': 'partial',
      'action': {
        'type': 'show_post_sale_sheet',
        'data': {
          'document_id': 42,
          'document_type': 'sale',
          'invoice_number': 'POS-63776202',
          'actions_endpoint':
              '/api/v1/tenant/receivables/42/reminder-sheet?document_type=sale',
        },
      },
    });

    expect(receivable.saleId, 'external-sale-42');
    expect(receivable.documentId, '42');
    expect(receivable.documentType, 'sale');
    expect(receivable.nativeAction?['type'], 'show_post_sale_sheet');
    expect(receivable.nativeAction?['data']['document_type'], 'sale');
    expect(receivable.nativeAction?['data']['actions_endpoint'],
        contains('/receivables/42/reminder-sheet'));
  });

  test('legacy receivable infers invoice versus POS without an action', () {
    final invoice = ReceivableModel.fromJson({
      'sale_id': 7,
      'sale_number': 'INV-0007',
      'customer_name': 'John Doe',
    });
    final pos = ReceivableModel.fromJson({
      'sale_id': 8,
      'sale_number': 'POS-0008',
      'customer_name': 'Walk-in',
    });

    expect(invoice.documentId, '7');
    expect(invoice.documentType, 'invoice');
    expect(invoice.nativeAction, isNull);
    expect(pos.documentType, 'sale');
  });
}
