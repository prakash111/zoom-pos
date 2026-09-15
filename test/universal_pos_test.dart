import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/core/sdui/screens/dynamic_schema_page.dart';
import 'package:zoom_pos_mobile/core/sdui/sdui_action_dispatcher.dart';
import 'package:zoom_pos_mobile/features/pos_universal/local_cart.dart';
import 'package:zoom_pos_mobile/features/pos_universal/pos_screen_model.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  // A pushed DynamicSchemaPage that can't reach the server now consults the
  // on-disk SchemaCache (SharedPreferences); give it an empty mock store so
  // that lookup resolves instantly instead of on a missing-plugin channel.
  setUp(() => SharedPreferences.setMockInitialValues({}));

  group('LocalCart', () {
    test('adds a new line and computes count/total', () {
      final cart = LocalCart();
      cart.add(id: 1, title: 'Amoxicillin', unitPrice: 10, quantity: 2);

      expect(cart.count, 2);
      expect(cart.total, 20);
      expect(cart.isEmpty, isFalse);
    });

    test('merges repeated adds of the same product+batch into one line', () {
      final cart = LocalCart();
      cart.add(
          id: 1, batchId: 9, title: 'Amoxicillin', unitPrice: 10, quantity: 1);
      cart.add(
          id: 1, batchId: 9, title: 'Amoxicillin', unitPrice: 10, quantity: 2);

      expect(cart.lines, hasLength(1));
      expect(cart.lines.first.quantity, 3);
      expect(cart.count, 3);
    });

    test('keeps different batches of the same product as separate lines', () {
      final cart = LocalCart();
      cart.add(
          id: 1, batchId: 9, title: 'Amoxicillin', unitPrice: 10, quantity: 1);
      cart.add(
          id: 1, batchId: 10, title: 'Amoxicillin', unitPrice: 10, quantity: 1);

      expect(cart.lines, hasLength(2));
    });

    test('clamps quantity to maxQuantity on add and update', () {
      final cart = LocalCart();
      cart.add(
          id: 1,
          title: 'Limited Stock',
          unitPrice: 5,
          quantity: 3,
          maxQuantity: 2);
      expect(cart.lines.first.quantity, 2);

      final key = cart.lines.first.lineKey;
      cart.updateQuantity(key, 10);
      expect(cart.lines.first.quantity, 2);
    });

    test('updateQuantity to zero or below removes the line', () {
      final cart = LocalCart();
      cart.add(id: 1, title: 'Item', unitPrice: 5, quantity: 1);
      final key = cart.lines.first.lineKey;

      cart.updateQuantity(key, 0);
      expect(cart.isEmpty, isTrue);
    });

    test('toApiItems maps to product_id/batch_id/quantity/unit_price', () {
      final cart = LocalCart();
      cart.add(
          id: 501,
          batchId: 9001,
          title: 'Amoxicillin',
          unitPrice: 12.5,
          quantity: 2);

      expect(cart.toApiItems(), [
        {
          'product_id': 501,
          'batch_id': 9001,
          'quantity': 2,
          'unit_price': 12.5
        },
      ]);
    });

    test('toApiItems omits batch_id entirely when null', () {
      final cart = LocalCart();
      cart.add(id: 777, title: 'OTC Item', unitPrice: 6, quantity: 1);

      expect(cart.toApiItems().first.containsKey('batch_id'), isFalse);
    });

    test('clear empties the cart and notifies listeners', () {
      final cart = LocalCart();
      var notified = false;
      cart.add(id: 1, title: 'Item', unitPrice: 5, quantity: 1);
      cart.addListener(() => notified = true);

      cart.clear();

      expect(cart.isEmpty, isTrue);
      expect(notified, isTrue);
    });
  });

  group('PosScreenModel', () {
    test('parses the full pos_screen contract shape', () {
      final schema = {
        'title': 'Pharmacy Counter POS',
        'banner': {
          'message': 'No cash register is open.',
          'icon': 'info_outline',
          'background_color': '#2A1E17',
          'border_color': '#D97706',
          'text_color': '#FCD34D',
          'action_text_color': '#10B981',
          'action_label': 'Open',
          'action': {
            'type': 'navigate',
            'endpoint': '/api/tenant/views/cash-register'
          },
        },
        'search': {
          'placeholder': 'Search medicines...',
          'scanner_enabled': true
        },
        'categories': [
          {'id': null, 'label': 'All'},
          {'id': 12, 'label': 'Antibiotics'},
        ],
        'catalog': {
          'layout_type': 'standard_grid',
          'items': [
            {
              'id': 501,
              'category_id': 12,
              'title': 'Amoxicillin 500mg',
              'subtitle': 'Batch #A1',
              'price': 12.5,
              'stock': 30,
              'badge': {'text': 'Rx', 'color': '#f59e0b'},
              'on_tap': {
                'type': 'open_remote_sheet',
                'sheet_endpoint':
                    '/api/tenant/pharmacy/batch-sheet?product_id=501'
              },
            },
          ],
        },
        'cart_bar': {
          'label_template': 'View Cart · {count} items · {total}',
          'checkout_sheet_endpoint': '/api/tenant/pharmacy/checkout-sheet',
        },
      };

      final model = PosScreenModel.fromJson(schema);

      expect(model.title, 'Pharmacy Counter POS');
      expect(model.banner?.message, 'No cash register is open.');
      expect(model.banner?.backgroundColor, const Color(0xFF2A1E17));
      expect(model.banner?.borderColor, const Color(0xFFD97706));
      expect(model.banner?.textColor, const Color(0xFFFCD34D));
      expect(model.banner?.actionTextColor, const Color(0xFF10B981));
      expect(model.banner?.actionLabel, 'Open');
      expect(model.searchPlaceholder, 'Search medicines...');
      expect(model.scannerEnabled, isTrue);
      expect(model.categories, hasLength(2));
      expect(model.items, hasLength(1));
      expect(model.items.first.badge?.text, 'Rx');
      expect(model.items.first.onTap['type'], 'open_remote_sheet');
      expect(
          model.checkoutSheetEndpoint, '/api/tenant/pharmacy/checkout-sheet');
    });

    test('banner is null when the message is missing or empty', () {
      final model = PosScreenModel.fromJson({
        'title': 'Repair Counter POS',
        'banner': null,
        'catalog': {'items': []},
        'cart_bar': {},
      });

      expect(model.banner, isNull);
    });

    test('item.isOutOfStock reflects a zero or missing stock value', () {
      final item = PosCatalogItem.fromJson(
          {'id': 1, 'title': 'X', 'price': 1, 'stock': 0});
      expect(item.isOutOfStock, isTrue);

      final unlimited =
          PosCatalogItem.fromJson({'id': 2, 'title': 'Labor Fee', 'price': 20});
      expect(unlimited.isOutOfStock, isFalse);
    });
  });

  group('SduiActionDispatcher', () {
    late GlobalKey<FormState> formKey;
    late Map<String, dynamic> formValues;

    setUp(() {
      formKey = GlobalKey<FormState>();
      formValues = {};
    });

    SduiActionDispatcher buildDispatcher({
      SduiRequestExecutor? requestExecutor,
      Future<bool> Function(BuildContext, Map<String, dynamic>)?
          onBeforeDispatch,
    }) {
      return SduiActionDispatcher(
        resolveApiClient: () => null,
        requestExecutor: requestExecutor,
        formKey: formKey,
        formValues: formValues,
        setFormValue: (k, v) => formValues[k] = v,
        onReload: () {},
        showToast: (_, {isError = false}) {},
        onBeforeDispatch: onBeforeDispatch,
      );
    }

    testWidgets(
        'onBeforeDispatch intercepts add_to_cart without any network call',
        (tester) async {
      var networkCalled = false;
      Map<String, dynamic>? interceptedAction;

      final dispatcher = buildDispatcher(
        requestExecutor: (endpoint, {required method, data}) async {
          networkCalled = true;
          return {'success': true};
        },
        onBeforeDispatch: (context, action) async {
          if (action['type'] == 'add_to_cart') {
            interceptedAction = action;
            return true;
          }
          return false;
        },
      );

      await tester
          .pumpWidget(const MaterialApp(home: Scaffold(body: SizedBox())));
      final context = tester.element(find.byType(Scaffold));

      await dispatcher.dispatch(context, {
        'type': 'add_to_cart',
        'item': {'id': 1, 'title': 'Item', 'price': 5},
      });

      expect(interceptedAction, isNotNull);
      expect(networkCalled, isFalse);
    });

    testWidgets('form_submit includes server-provided checkout items',
        (tester) async {
      Map<String, dynamic>? submittedData;
      final dispatcher = buildDispatcher(
        requestExecutor: (endpoint, {required method, data}) async {
          submittedData = Map<String, dynamic>.from(data ?? const {});
          return {'success': true};
        },
      );
      formValues['payment_method'] = 'cash';

      await tester
          .pumpWidget(const MaterialApp(home: Scaffold(body: SizedBox())));
      final context = tester.element(find.byType(Scaffold));

      await dispatcher.dispatch(context, {
        'type': 'form_submit',
        'endpoint': '/api/tenant/salon/pos-checkout',
        'method': 'POST',
        'payload': {
          'appointment_id': 42,
          'items': [
            {
              'id': 1,
              'type': 'service',
              'name': 'Beard Trim & Hot Towel',
              'price': 15.0,
              'quantity': 1,
              'staff_id': 999,
            },
          ],
        },
      });

      expect(submittedData, containsPair('appointment_id', 42));
      expect(submittedData, containsPair('payment_method', 'cash'));
      expect(submittedData?['items'], isA<List<dynamic>>());
      expect(submittedData?['items'][0]['name'], 'Beard Trim & Hot Towel');
    });

    testWidgets(
        'open_remote_sheet fetches the endpoint and renders its components',
        (tester) async {
      String? requestedEndpoint;

      final dispatcher = buildDispatcher(
        requestExecutor: (endpoint, {required method, data}) async {
          requestedEndpoint = endpoint;
          return {
            'schema': {
              'title': 'Select FEFO Batch',
              'components': [
                {'type': 'text', 'text': 'Batch #A1'},
              ],
            },
          };
        },
      );

      await tester
          .pumpWidget(const MaterialApp(home: Scaffold(body: SizedBox())));
      final context = tester.element(find.byType(Scaffold));

      await dispatcher.dispatch(context, {
        'type': 'open_remote_sheet',
        'sheet_endpoint': '/api/tenant/pharmacy/batch-sheet?product_id=501',
      });
      await tester.pumpAndSettle();

      expect(
          requestedEndpoint, '/api/tenant/pharmacy/batch-sheet?product_id=501');
      expect(find.text('Select FEFO Batch'), findsOneWidget);
      expect(find.text('Batch #A1'), findsOneWidget);
    });

    testWidgets(
        'add_to_cart nested inside an open_remote_sheet button is intercepted too',
        (tester) async {
      Map<String, dynamic>? interceptedAction;

      final dispatcher = buildDispatcher(
        requestExecutor: (endpoint, {required method, data}) async => {
          'schema': {
            'title': 'Select FEFO Batch',
            'components': [
              {
                'type': 'button_primary',
                'label': 'Add to Dispensing Cart',
                'action': {
                  'type': 'add_to_cart',
                  'item': {
                    'id': 501,
                    'batch_id': 9001,
                    'title': 'Amoxicillin',
                    'price': 12.5
                  },
                },
              },
            ],
          },
        },
        onBeforeDispatch: (context, action) async {
          if (action['type'] == 'add_to_cart') {
            interceptedAction = action;
            return true;
          }
          return false;
        },
      );

      await tester
          .pumpWidget(const MaterialApp(home: Scaffold(body: SizedBox())));
      final context = tester.element(find.byType(Scaffold));

      await dispatcher.dispatch(context, {
        'type': 'open_remote_sheet',
        'sheet_endpoint': '/api/tenant/pharmacy/batch-sheet?product_id=501',
      });
      await tester.pumpAndSettle();

      await tester.tap(find.text('Add to Dispensing Cart'));
      await tester.pumpAndSettle();

      expect(interceptedAction?['item']?['id'], 501);
    });

    testWidgets(
        'filter_view re-opens the view with only the allow-listed fields as query',
        (tester) async {
      formValues['q'] = 'metformin';
      formValues['stock_qty'] = '100'; // a foreign form field, must NOT leak

      final dispatcher = buildDispatcher();

      await tester
          .pumpWidget(const MaterialApp(home: Scaffold(body: SizedBox())));
      final context = tester.element(find.byType(Scaffold));

      await dispatcher.dispatch(context, {
        'type': 'filter_view',
        'endpoint': '/api/tenant/views/pharmacy-batches?tab=active',
        'fields': ['q'],
      });
      await tester.pumpAndSettle();

      final page =
          tester.widget<DynamicSchemaPage>(find.byType(DynamicSchemaPage));
      expect(page.endpoint,
          '/api/tenant/views/pharmacy-batches?tab=active&q=metformin');
      expect(page.endpoint, isNot(contains('stock_qty')));
    });

    testWidgets(
        'a show_ticket_share_sheet response pops a native sheet, never a wa.me redirect',
        (tester) async {
      final dispatcher = buildDispatcher(
        requestExecutor: (endpoint, {required method, data}) async => {
          'success': true,
          'message': 'Repair ticket created successfully!',
          'action': 'show_ticket_share_sheet',
          'redirect_route': '/api/tenant/views/repair-tickets',
          'whatsapp_url': 'https://wa.me/15551239876?text=hi',
          'share': {
            'id': 'REP-2026-0006',
            'device': 'Lenovo ThinkPad X1',
            'status': 'received',
            'customer_name': 'Sarah Connor',
            'customer_phone': '15551239876',
            'share_text': 'Track: https://x.test/track/REP-2026-0006',
            'whatsapp_url': 'https://wa.me/15551239876?text=hi',
          },
        },
      );

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: Builder(builder: (ctx) => const SizedBox())),
        ),
      );
      final context = tester.element(find.byType(Scaffold));

      // Fire and forget — the dispatch future only resolves once the modal is
      // dismissed, so drive the frames manually instead of awaiting it.
      unawaited(dispatcher.dispatch(context, {
        'type': 'form_submit',
        'endpoint': '/api/tenant/repair/tickets',
        'method': 'POST',
      }));
      await tester.pump(); // request completes
      await tester.pump(const Duration(milliseconds: 400)); // sheet animates in

      // The native bottom sheet, not an app switch.
      expect(find.text('Ticket #REP-2026-0006 Created'), findsOneWidget);
      expect(find.text('Share via WhatsApp'), findsOneWidget);
      expect(find.text('System Share (SMS / Other Apps)'), findsOneWidget);
      expect(find.text('Done'), findsOneWidget);
      expect(find.textContaining('Lenovo ThinkPad X1'), findsWidgets);

      // Dismiss so the widget tree tears down cleanly.
      await tester.tap(find.text('Done'));
      await tester.pumpAndSettle();
      expect(find.text('Share via WhatsApp'), findsNothing);
    });
  });
}
