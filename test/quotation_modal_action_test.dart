import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/sdui/dynamic_schema_context.dart';
import 'package:zoom_pos_mobile/core/sdui/dynamic_schema_parser.dart';
import 'package:zoom_pos_mobile/core/sdui/sdui_action_dispatcher.dart';
import 'package:zoom_pos_mobile/features/auth/auth_provider.dart';
import 'package:zoom_pos_mobile/features/quotations/screens/quotation_form_sheet.dart';

void main() {
  testWidgets(
      'OPEN_QUOTATION_MODAL fetches server schema and opens native quotation sheet',
      (tester) async {
    const endpoint =
        '/api/v1/tenant/quotations/create-modal?lead_id=17&customer_id=29';
    final apiClient = _FakeApiClient();
    late SduiActionDispatcher dispatcher;
    String? requestedEndpoint;

    dispatcher = SduiActionDispatcher(
      resolveApiClient: () => apiClient,
      requestExecutor: (requestEndpoint, {required method, data}) async {
        requestedEndpoint = requestEndpoint;
        return {
          'success': true,
          'type': 'bottom_sheet',
          'sheet_type': 'native_quotation',
          'modal': 'quotation',
          'title': 'New quotation',
          'lead_id': 17,
          'customer_id': 29,
          'customer_name': 'Prakash Kumar Singh (#29)',
          'notes': 'Store default note',
          'terms': 'Valid for 15 days.',
        };
      },
      formKey: GlobalKey<FormState>(),
      formValues: <String, dynamic>{},
      setFormValue: (_, __) {},
      onReload: () {},
      showToast: (_, {isError = false}) {},
    );

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          Provider<ApiClient>.value(value: apiClient),
          Provider<AuthProvider?>.value(value: null),
        ],
        child: MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: DynamicSchemaContext(
                formValues: const {},
                setFormValue: (_, __) {},
                dispatchAction: (action) =>
                    dispatcher.dispatch(context, action),
                apiClient: apiClient,
                child: Builder(
                  builder: (cardContext) =>
                      DynamicSchemaParser.buildComponent(cardContext, {
                    'type': 'entity_record_card',
                    'title': 'Prakash Kumar Singh',
                    'actions': [
                      {
                        'label': 'Create quote',
                        'variant': 'primary',
                        'action_type': 'OPEN_QUOTATION_MODAL',
                        'endpoint': endpoint,
                        'action': {
                          'type': 'OPEN_QUOTATION_MODAL',
                          'action_type': 'OPEN_QUOTATION_MODAL',
                          'endpoint': endpoint,
                          'data': {
                            'lead_id': 17,
                            'customer_id': 29,
                            'notes': 'Lead requested barcode scanner support.',
                          },
                        },
                      },
                    ],
                  }),
                ),
              ),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Create quote'));
    await tester.pumpAndSettle();

    expect(requestedEndpoint, endpoint);
    expect(find.byType(QuotationFormSheet), findsOneWidget);
    expect(find.text('New quotation'), findsOneWidget);
    expect(find.text('Add product'), findsOneWidget);
    expect(find.text('No items yet.'), findsOneWidget);

    final sheet = tester.widget<QuotationFormSheet>(
      find.byType(QuotationFormSheet),
    );
    expect(sheet.initialLeadId, '17');
    expect(sheet.initialCustomerId, '29');
    expect(sheet.initialCustomerName, 'Prakash Kumar Singh (#29)');
    expect(sheet.initialNotes, 'Lead requested barcode scanner support.');
    expect(sheet.initialTerms, 'Valid for 15 days.');
  });
}

class _FakeApiClient implements ApiClient {
  @override
  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? query,
  }) async {
    if (path == '/quotations/defaults') {
      return {
        'defaults': {'notes': '', 'terms': '', 'prefix': 'QUO-'}
      };
    }
    if (path == '/taxes') {
      return {'taxes': <Map<String, dynamic>>[]};
    }
    return {'success': true};
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
