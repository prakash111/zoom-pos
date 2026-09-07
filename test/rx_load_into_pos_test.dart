import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/models/product_model.dart';
import 'package:zoom_pos_mobile/core/sdui/sdui_action_dispatcher.dart';
import 'package:zoom_pos_mobile/features/pos/pos_provider.dart';
import 'package:zoom_pos_mobile/features/pos/rx_cart_handoff.dart';

void main() {
  setUp(() => RxCartHandoff.instance.clear());
  tearDown(() => RxCartHandoff.instance.clear());

  group('RxCartHandoff', () {
    test('take() returns the staged payload once, then clears it', () {
      expect(RxCartHandoff.instance.hasPending, isFalse);

      RxCartHandoff.instance.stage({'rx_id': 'RX-1'});
      expect(RxCartHandoff.instance.hasPending, isTrue);

      expect(RxCartHandoff.instance.take(), {'rx_id': 'RX-1'});
      expect(RxCartHandoff.instance.take(), isNull);
      expect(RxCartHandoff.instance.hasPending, isFalse);
    });
  });

  group('SduiActionDispatcher · load_rx_to_pos', () {
    testWidgets('stages the prescription payload for the native POS screen',
        (tester) async {
      final dispatcher = SduiActionDispatcher(
        resolveApiClient: () => null,
        formKey: GlobalKey<FormState>(),
        formValues: {},
        setFormValue: (_, __) {},
        onReload: () {},
        showToast: (_, {isError = false}) {},
      );

      await tester.pumpWidget(
        const MaterialApp(home: Scaffold(body: SizedBox())),
      );
      final context = tester.element(find.byType(Scaffold));

      await dispatcher.dispatch(context, {
        'type': 'load_rx_to_pos',
        'payload': {
          'rx_id': '7',
          'rx_number': 'RX-20260907-0001',
          'customer': {'id': 14, 'name': 'prakash', 'phone': '918535075196'},
          'doctor': {'name': 'Ramesh agarwal', 'registration_no': 'MED-88912'},
          'items': [
            {
              'product_id': 102,
              'product_name': 'Amoxicillin 500mg Capsules',
              'quantity': 2,
              'unit_price': 9.5,
            },
          ],
        },
      });

      expect(RxCartHandoff.instance.hasPending, isTrue);
      final staged = RxCartHandoff.instance.take()!;
      expect(staged['rx_number'], 'RX-20260907-0001');
      expect((staged['items'] as List).single['product_name'],
          'Amoxicillin 500mg Capsules');

      // Discard the pushed POS route before it tries to build without providers.
      await tester.pumpWidget(const SizedBox());
    });

    testWidgets('load_repair_to_pos stages the ticket payload tagged as repair',
        (tester) async {
      final dispatcher = SduiActionDispatcher(
        resolveApiClient: () => null,
        formKey: GlobalKey<FormState>(),
        formValues: {},
        setFormValue: (_, __) {},
        onReload: () {},
        showToast: (_, {isError = false}) {},
      );

      await tester.pumpWidget(
        const MaterialApp(home: Scaffold(body: SizedBox())),
      );
      final context = tester.element(find.byType(Scaffold));

      await dispatcher.dispatch(context, {
        'type': 'load_repair_to_pos',
        'payload': {
          'ticket_id': '6',
          'ticket_number': 'REP-2026-0006',
          'customer': {'id': 14, 'name': 'prakash', 'phone': '918535075196'},
          'device': 'Dell Inspiron 15 (SN: DL-9921)',
          'labor_cost': 45.0,
          'parts': [
            {'product_id': 88, 'name': '512GB NVMe SSD', 'unit_price': 65.0, 'quantity': 1},
          ],
        },
      });

      final staged = RxCartHandoff.instance.take()!;
      expect(staged['_handoff_kind'], 'repair');
      expect(staged['ticket_number'], 'REP-2026-0006');
      expect(staged['labor_cost'], 45.0);

      await tester.pumpWidget(const SizedBox());
    });
  });

  group('PosProvider Rx line resolution', () {
    ProductModel catalogProduct() => ProductModel.fromJson({
          'id': '102',
          'name': 'Amoxicillin 500mg Capsules',
          'sale_price': 9.5,
          'tax_rate': 5,
          'active': true,
        });

    test('resolveRxProduct matches a prescription line to the catalog by id',
        () {
      final match = PosProvider.resolveRxProduct(
        {'product_id': 102, 'product_name': 'Amoxicillin 500mg Capsules'},
        [catalogProduct()],
      );

      expect(match, isNotNull);
      expect(match!.id, '102');
      expect(match.taxRate, 5); // real catalog tax, not the synthesized 0
    });

    test('resolveRxProduct returns null when the medicine is off-catalogue',
        () {
      expect(
        PosProvider.resolveRxProduct(
          {'product_id': 999, 'product_name': 'Unknown syrup'},
          [catalogProduct()],
        ),
        isNull,
      );
    });

    test('synthesizeRxProduct carries name, price and stock from the payload',
        () {
      final synthetic = PosProvider.synthesizeRxProduct({
        'product_id': 555,
        'product_name': 'Cough Linctus',
        'unit_price': 4.25,
        'quantity': 3,
      });

      expect(synthetic.id, '555');
      expect(synthetic.name, 'Cough Linctus');
      expect(synthetic.salePrice, 4.25);
      expect(synthetic.currentStock, 3);
      expect(synthetic.unit, 'pcs');
    });

    test('synthesizeRxProduct marks an is_service line as a service unit', () {
      final labor = PosProvider.synthesizeRxProduct({
        'product_id': 'repair-labor-REP-1',
        'product_name': 'Labor: Dell Inspiron 15',
        'unit_price': 45.0,
        'quantity': 1,
        'is_service': true,
      });

      expect(labor.name, 'Labor: Dell Inspiron 15');
      expect(labor.salePrice, 45.0);
      expect(labor.unit, 'service');
    });
  });
}
