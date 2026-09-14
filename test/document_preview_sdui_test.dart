import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/sdui/dynamic_schema_context.dart';
import 'package:zoom_pos_mobile/core/sdui/dynamic_schema_parser.dart';

void main() {
  testWidgets(
      'multi-format document sheet renders semantic components and reloads selected format',
      (tester) async {
    Map<String, dynamic>? dispatched;
    final schema = <String, dynamic>{
      'type': 'column',
      'components': [
        {
          'type': 'segmented_tabs',
          'param_name': 'format',
          'active_value': 'a4',
          'options': const [
            {'label': 'Standard A4', 'value': 'a4'},
            {'label': '80mm POS', 'value': 'thermal_80mm'},
            {'label': '58mm Receipt', 'value': 'thermal_58mm'},
            {'label': 'Mobile Slip', 'value': 'slip'},
          ],
          'action': const {
            'type': 'RELOAD_COMPONENT',
            'endpoint': '/api/v1/tenant/documents/invoice/1/preview-modal',
          },
        },
        {
          'type': 'document_preview_card',
          'format': 'a4',
          'background_color': 'theme.surface',
          'border_color': 'theme.divider',
          'document': {
            'company_name': 'Zoom Preview Store',
            'tax_label': 'GSTIN',
            'tax_id': '27AAAAA0000A1Z5',
            'document_label': 'TAX INVOICE',
            'reference': 'INV-1001',
            'customer_name': 'Asha Sharma',
            'currency_symbol': '₹',
            'subtotal': 1000,
            'tax_amount': 180,
            'total_amount': 1180,
            'lines': const [
              {
                'name': 'Consulting Service',
                'quantity': 2,
                'line_total': 1000,
              },
            ],
          },
        },
        {
          'type': 'section_header',
          'title': 'Dispatch Document',
          'divider_color': 'theme.divider',
          'text_color': 'theme.textPrimary',
        },
        {
          'type': 'list_tile',
          'title': 'Share via WhatsApp',
          'background_color': 'theme.surface',
          'border_color': 'theme.divider',
          'leading': const {
            'type': 'icon',
            'icon': 'chat',
            'color': '#25D366',
          },
          'action': const {
            'type': 'OPEN_URL',
            'url': 'https://wa.me/919876543210',
          },
        },
      ],
    };

    await tester.pumpWidget(
      MaterialApp(
        themeMode: ThemeMode.dark,
        darkTheme: ThemeData.dark(),
        home: Scaffold(
          body: DynamicSchemaContext(
            formValues: const {},
            setFormValue: (_, __) {},
            dispatchAction: (action) async => dispatched = action,
            child: SingleChildScrollView(
              child: Builder(
                builder: (context) =>
                    DynamicSchemaParser.buildComponent(context, schema),
              ),
            ),
          ),
        ),
      ),
    );

    expect(find.text('Standard A4'), findsOneWidget);
    expect(find.text('TAX INVOICE'), findsOneWidget);
    expect(find.text('Consulting Service'), findsOneWidget);
    expect(find.text('Dispatch Document'), findsOneWidget);
    expect(find.text('Share via WhatsApp'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.tap(find.text('58mm Receipt'));
    await tester.pump();

    expect(dispatched?['type'], 'refresh_sheet');
    expect(
      dispatched?['endpoint'],
      '/api/v1/tenant/documents/invoice/1/preview-modal?format=thermal_58mm',
    );
    expect(dispatched?['refresh_in_place'], isTrue);
  });
}
