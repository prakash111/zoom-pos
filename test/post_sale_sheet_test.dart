import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/sdui/sdui_action_dispatcher.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('receivable action opens the four-row native POS sale sheet',
      (tester) async {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(400, 800);
    addTearDown(tester.view.resetDevicePixelRatio);
    addTearDown(tester.view.resetPhysicalSize);
    final api = _ActionsApiClient();
    final dispatcher = SduiActionDispatcher(
      resolveApiClient: () => api,
      formKey: GlobalKey<FormState>(),
      formValues: <String, dynamic>{},
      setFormValue: (_, __) {},
      onReload: () {},
      showToast: (_, {isError = false}) {},
    );

    await tester.pumpWidget(
      Provider<ApiClient>.value(
        value: api,
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData.dark(),
          home: const Scaffold(body: SizedBox()),
        ),
      ),
    );
    final context = tester.element(find.byType(Scaffold));

    unawaited(dispatcher.dispatch(context, {
      'type': 'show_post_sale_sheet',
      'data': {
        'document_id': 42,
        'document_type': 'sale',
        'sale_id': 42,
        'invoice_number': 'POS-63776202',
        'company_name': 'Zoom Store',
        'tax_id': '29ABCDE1234F1Z5',
        'is_india': true,
        'customer_phone': '+919876543210',
        'customer_email': 'ava@example.com',
        'total': 100,
        'paid_amount': 20,
        'due_amount': 80,
        'pdf_endpoint': '/api/tenant/invoices/42/pdf-stream',
        'actions_endpoint':
            '/api/v1/tenant/receivables/42/reminder-sheet?document_type=sale',
        'lines': [
          {
            'name': 'Widget',
            'quantity': 1,
            'unit_price': 100,
            'line_total': 100,
          },
        ],
      },
    }));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pump();
    debugDefaultTargetPlatformOverride = null;

    expect(find.text('POS-63776202'), findsOneWidget);
    expect(find.text('GSTIN: 29ABCDE1234F1Z5'), findsOneWidget);
    expect(find.text('Preview & Print'), findsOneWidget);
    expect(find.text('Print on receipt printer'), findsOneWidget);
    expect(find.text('Send via WhatsApp'), findsOneWidget);
    expect(find.text('Send via Email'), findsOneWidget);
    expect(find.textContaining('SMS'), findsNothing);
    expect(api.requestedPaths, [
      '/api/v1/tenant/receivables/42/reminder-sheet?document_type=sale',
    ]);

    final bottomSheet = tester.widget<BottomSheet>(find.byType(BottomSheet));
    expect(bottomSheet.backgroundColor, const Color(0xFF131E29));

    Navigator.of(context).pop();
    await tester.pumpAndSettle();
  });
}

class _ActionsApiClient implements ApiClient {
  final List<String> requestedPaths = [];

  @override
  Future<Map<String, dynamic>> requestAbsolute(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? data,
    Map<String, dynamic>? query,
  }) async {
    requestedPaths.add(path);
    return {
      'schema': {
        'type': 'bottom_sheet',
        'components': [
          {
            'type': 'list_tile',
            'title': 'Preview & Print',
            'action_type': 'OPEN_RECEIPT_PREVIEW',
          },
          {
            'type': 'list_tile',
            'title': 'Print on receipt printer',
            'action_type': 'TRIGGER_THERMAL_PRINT',
          },
          {
            'type': 'list_tile',
            'id': 'channel_whatsapp',
            'channel': 'whatsapp',
            'title': 'Send via WhatsApp',
            'subtitle': '+919876543210',
            'leading': {'icon': 'chat', 'color': '#22C55E'},
            'action': {
              'type': 'SUBMIT_FORM',
              'endpoint': '/api/v1/tenant/dispatch/send',
              'data': {'channel': 'whatsapp', 'id': 42},
            },
          },
          {
            'type': 'list_tile',
            'id': 'channel_email',
            'channel': 'email',
            'title': 'Send via Email',
            'subtitle': 'ava@example.com',
            'leading': {'icon': 'email', 'color': '#818CF8'},
            'action': {
              'type': 'SUBMIT_FORM',
              'endpoint': '/api/v1/tenant/dispatch/send',
              'data': {'channel': 'email', 'id': 42},
            },
          },
        ],
      },
    };
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
