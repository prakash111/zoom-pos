import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/sdui/dynamic_schema_context.dart';
import 'package:zoom_pos_mobile/core/sdui/dynamic_schema_parser.dart';
import 'package:zoom_pos_mobile/core/sdui/screens/dynamic_schema_page.dart';
import 'package:zoom_pos_mobile/core/sdui/sdui_component_registry.dart';

void main() {
  group('Declarative SDUI Schema Parser - Layouts', () {
    testWidgets('renders container, card, column, and row', (tester) async {
      final schema = {
        'type': 'container',
        'padding': 12,
        'child': {
          'type': 'card',
          'components': [
            {
              'type': 'column',
              'components': [
                {'type': 'text', 'text': 'Card Header', 'bold': true},
                {
                  'type': 'row',
                  'components': [
                    {'type': 'text', 'text': 'Left Col'},
                    {'type': 'text', 'text': 'Right Col'},
                  ],
                },
              ],
            },
          ],
        },
      };

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (ctx) => DynamicSchemaParser.buildComponent(ctx, schema),
            ),
          ),
        ),
      );

      expect(find.text('Card Header'), findsOneWidget);
      expect(find.text('Left Col'), findsOneWidget);
      expect(find.text('Right Col'), findsOneWidget);
      expect(find.byType(Card), findsOneWidget);
    });

    testWidgets('renders accordion group with ExpansionTile', (tester) async {
      final schema = {
        'type': 'accordion_group',
        'title': 'Operating Mode Settings',
        'initially_expanded': true,
        'components': [
          {'type': 'text', 'text': 'Strict Lock Notice'},
        ],
      };

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (ctx) => DynamicSchemaParser.buildComponent(ctx, schema),
            ),
          ),
        ),
      );

      expect(find.text('Operating Mode Settings'), findsOneWidget);
      expect(find.byType(ExpansionTile), findsOneWidget);
      expect(find.text('Strict Lock Notice'), findsOneWidget);
    });

    testWidgets('renders grid view with columns', (tester) async {
      final schema = {
        'type': 'grid_view',
        'cross_axis_count': 2,
        'components': [
          {'type': 'text', 'text': 'Grid Item 1'},
          {'type': 'text', 'text': 'Grid Item 2'},
        ],
      };

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (ctx) => DynamicSchemaParser.buildComponent(ctx, schema),
            ),
          ),
        ),
      );

      expect(find.text('Grid Item 1'), findsOneWidget);
      expect(find.text('Grid Item 2'), findsOneWidget);
      expect(find.byType(Wrap), findsOneWidget);
    });

    testWidgets('row aligns an expanded label against a trailing status chip',
        (tester) async {
      // The intake diagnostic checklist row: a long label must take the free
      // width (Expanded) and the PENDING chip must sit hard against the right
      // edge instead of wrapping mid-row.
      final schema = {
        'type': 'row',
        'main_axis_alignment': 'space_between',
        'cross_axis_alignment': 'center',
        'components': [
          {
            'type': 'text',
            'text': 'Rear camera glass and housing gasket inspection',
            'expanded': true,
            'max_lines': 2,
          },
          {'type': 'badge', 'label': 'PENDING', 'color': '#64748b'},
        ],
      };

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (ctx) => DynamicSchemaParser.buildComponent(ctx, schema),
            ),
          ),
        ),
      );

      expect(find.byType(Expanded), findsOneWidget);
      expect(find.text('PENDING'), findsOneWidget);

      final rowSize = tester.getSize(find.byType(Row).first);
      final chipRight = tester.getBottomRight(find.text('PENDING')).dx;
      final rowRight = tester.getBottomRight(find.byType(Row).first).dx;
      expect(rowSize.width, greaterThan(200));
      // Chip hugs the right edge (within padding), never floats mid-row.
      expect(rowRight - chipRight, lessThan(24));
    });

    testWidgets(
        'compact timeline keeps fixed time and stacked badges in bounds',
        (tester) async {
      final schema = {
        'type': 'row',
        'spacing': 8,
        'cross_axis_alignment': 'start',
        'components': [
          {
            'type': 'container',
            'width': 64,
            'flexible': false,
            'components': [
              {'type': 'text', 'text': '9:00 AM'},
            ],
          },
          {
            'type': 'column',
            'expanded': true,
            'components': [
              {
                'type': 'text',
                'text': 'Beard Trim & Hot Towel',
                'max_lines': 2,
              },
              {'type': 'text', 'text': 'Staff: Elena Rostova'},
            ],
          },
          {
            'type': 'column',
            'flexible': false,
            'spacing': 4,
            'cross_axis_alignment': 'end',
            'components': [
              {
                'type': 'badge',
                'label': 'IN CHAIR',
                'color': '#0284c7',
                'max_width': 124,
              },
              {
                'type': 'badge',
                'label': 'Advance: \$500.00',
                'color': '#059669',
                'max_width': 124,
              },
            ],
          },
        ],
      };

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Center(
              child: SizedBox(
                width: 288,
                child: Builder(
                  builder: (ctx) =>
                      DynamicSchemaParser.buildComponent(ctx, schema),
                ),
              ),
            ),
          ),
        ),
      );

      expect(tester.takeException(), isNull);
      expect(find.text('IN CHAIR'), findsOneWidget);
      expect(find.text('Advance: \$500.00'), findsOneWidget);
      expect(find.byType(Expanded), findsOneWidget);
    });
  });

  group('Declarative SDUI Schema Parser - Display Elements', () {
    testWidgets('renders text, badge, icon, and divider', (tester) async {
      final schema = {
        'type': 'column',
        'components': [
          {'type': 'text', 'text': 'Store Overview', 'style': 'title_large'},
          {
            'type': 'badge',
            'label': 'ACTIVE',
            'color': '#16a34a',
            'badge_style': 'solid'
          },
          {'type': 'icon', 'icon': 'settings', 'size': 28},
          {'type': 'divider'},
        ],
      };

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (ctx) => DynamicSchemaParser.buildComponent(ctx, schema),
            ),
          ),
        ),
      );

      expect(find.text('Store Overview'), findsOneWidget);
      expect(find.text('ACTIVE'), findsOneWidget);
      expect(find.byType(Icon), findsWidgets);
      expect(find.byType(Divider), findsOneWidget);
    });
  });

  group('Declarative SDUI Schema Parser - Forms & Inputs', () {
    testWidgets(
        'binds text_input, dropdown_select, toggle_switch, and color_picker',
        (tester) async {
      final formValues = <String, dynamic>{};
      final schema = {
        'type': 'column',
        'components': [
          {
            'type': 'text_input',
            'name': 'business_name',
            'label': 'Business Name',
            'initial_value': 'Acme Retail',
          },
          {
            'type': 'dropdown_select',
            'name': 'currency',
            'label': 'Currency',
            'initial_value': 'USD',
            'options': [
              {'label': r'USD ($)', 'value': 'USD'},
              {'label': r'EUR (€)', 'value': 'EUR'},
            ],
          },
          {
            'type': 'toggle_switch',
            'name': 'tax_inclusive',
            'label': 'Tax Inclusive',
            'initial_value': true,
          },
          {
            'type': 'color_picker',
            'name': 'primary_color',
            'label': 'Accent Color',
            'initial_value': '#1d4ed8',
            'presets': ['#1d4ed8', '#10b981'],
          },
        ],
      };

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: DynamicSchemaContext(
              formValues: formValues,
              setFormValue: (k, v) => formValues[k] = v,
              dispatchAction: (_) async {},
              child: Builder(
                builder: (ctx) =>
                    DynamicSchemaParser.buildComponent(ctx, schema),
              ),
            ),
          ),
        ),
      );

      expect(find.text('Business Name'), findsOneWidget);
      expect(find.text('Acme Retail'), findsOneWidget);
      expect(find.text('Tax Inclusive'), findsOneWidget);
      expect(find.text('Accent Color'), findsOneWidget);

      // Mutate text input
      await tester.enterText(find.byType(TextFormField), 'Apex Supermarket');
      expect(formValues['business_name'], 'Apex Supermarket');

      // Toggle switch
      await tester.tap(find.byType(SwitchListTile));
      await tester.pump();
      expect(formValues['tax_inclusive'], isFalse);
    });

    testWidgets('color_picker opens an HSV dialog and stores #RRGGBB',
        (tester) async {
      await tester.binding.setSurfaceSize(const Size(800, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final formValues = <String, dynamic>{};

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: DynamicSchemaContext(
              formValues: formValues,
              setFormValue: (key, value) => formValues[key] = value,
              dispatchAction: (_) async {},
              child: Builder(
                builder: (context) => DynamicSchemaParser.buildComponent(
                  context,
                  {
                    'type': 'color_picker',
                    'name': 'primary_color',
                    'label': 'Primary Accent Color',
                    'initial_value': '#1d4ed8',
                    'presets': ['#1d4ed8'],
                  },
                ),
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.text('Choose custom color'));
      await tester.pumpAndSettle();

      expect(find.text('Hue'), findsOneWidget);
      expect(find.text('Saturation'), findsOneWidget);
      expect(find.text('Brightness'), findsOneWidget);
      expect(find.byType(Slider), findsNWidgets(3));

      await tester.drag(find.byType(Slider).first, const Offset(80, 0));
      await tester.pump();
      await tester.tap(find.text('Apply color'));
      await tester.pumpAndSettle();

      expect(formValues['primary_color'], matches(RegExp(r'^#[0-9A-F]{6}$')));
      expect(find.byType(TextField), findsNothing);
    });

    testWidgets(
        'cash_tendered_field computes CHANGE DUE live, on-device, no round trip',
        (tester) async {
      await tester.binding.setSurfaceSize(const Size(500, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final formValues = <String, dynamic>{};
      var dispatched = 0;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: DynamicSchemaContext(
              formValues: formValues,
              setFormValue: (key, value) => formValues[key] = value,
              dispatchAction: (_) async => dispatched++,
              child: Builder(
                builder: (context) => DynamicSchemaParser.buildComponent(
                  context,
                  {
                    'type': 'cash_tendered_field',
                    'name': 'tendered',
                    'total': 7215.95,
                    'currency': r'$',
                    'initial_value': '7215.95',
                    'quick_cash': [7215.95, 7216.00, 7220.00],
                  },
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pump();

      // Starts at exact tender -> no change due.
      expect(find.text(r'$0.00'), findsOneWidget);
      expect(formValues['tendered'], '7215.95');

      // Typing recomputes CHANGE DUE with zero network calls.
      await tester.enterText(find.byType(TextField), '7300');
      await tester.pump();
      expect(find.text(r'$84.05'), findsOneWidget);
      expect(formValues['tendered'], '7300.00');

      // A quick-cash chip sets the field and recomputes locally.
      await tester.tap(find.text(r'$7220.00'));
      await tester.pump();
      expect(find.text(r'$4.05'), findsOneWidget);
      expect(formValues['tendered'], '7220.00');

      expect(dispatched, 0, reason: 'change-due math must never hit dispatch');
    });

    testWidgets('customer_selector renders lookup UI and binds customer details',
        (tester) async {
      final formValues = <String, dynamic>{};
      final schema = {
        'type': 'customer_selector',
        'name': 'customer_id',
        'label': 'Client / Customer Lookup',
        'search_endpoint': '/api/tenant/customers/search',
        'fields': {
          'name_field': 'customer_name',
          'phone_field': 'customer_phone',
        },
        'name_label': 'Client Full Name *',
        'phone_label': 'Client Phone Number *',
        'initial_name': 'Sarah Connor',
        'initial_phone': '+1 555 123 4567',
      };

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: DynamicSchemaContext(
              formValues: formValues,
              setFormValue: (k, v) => formValues[k] = v,
              dispatchAction: (_) async {},
              child: Builder(
                builder: (ctx) =>
                    DynamicSchemaParser.buildComponent(ctx, schema),
              ),
            ),
          ),
        ),
      );
      await tester.pump();

      expect(find.text('Client / Customer Lookup'), findsOneWidget);
      expect(find.text('Client Full Name *'), findsOneWidget);
      expect(find.text('Client Phone Number *'), findsOneWidget);
      expect(find.text('Search CRM customer by name or phone...'), findsOneWidget);
      expect(find.text('Sarah Connor'), findsOneWidget);
      expect(find.text('+1 555 123 4567'), findsOneWidget);

      expect(formValues['customer_name'], 'Sarah Connor');
      expect(formValues['customer_phone'], '+1 555 123 4567');
    });
  });

  group('Declarative SDUI Schema Parser - Lists, Tables & Stepper', () {
    testWidgets('renders line_item_tile and table_grid', (tester) async {
      final schema = {
        'type': 'column',
        'components': [
          {
            'type': 'line_item_tile',
            'title': 'Dispensed Prescriptions',
            'subtitle': 'Manage patient orders and batches',
            'leading_icon': 'receipt_long',
          },
          {
            'type': 'table_grid',
            'headers': ['Tax Authority', 'Rate', 'Type'],
            'rows': [
              ['CGST', '9%', 'Central'],
              ['SGST', '9%', 'State'],
            ],
          },
          {
            'type': 'step_counter',
            'name': 'quantity',
            'label': 'Order Quantity',
            'initial_value': 2,
          },
        ],
      };

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (ctx) => DynamicSchemaParser.buildComponent(ctx, schema),
            ),
          ),
        ),
      );

      expect(find.text('Dispensed Prescriptions'), findsOneWidget);
      expect(find.text('CGST'), findsOneWidget);
      expect(find.text('Order Quantity'), findsOneWidget);
      expect(find.byType(DataTable), findsOneWidget);
    });
  });

  group('DynamicSchemaPage & Action Dispatcher', () {
    testWidgets('dispatches form_submit with server method and bound values',
        (tester) async {
      String? submittedEndpoint;
      String? submittedMethod;
      Map<String, dynamic>? submittedData;
      final schema = {
        'title': 'Financial Settings',
        'layout': 'scroll_view',
        'components': [
          {
            'type': 'text_input',
            'name': 'currency',
            'label': 'Currency',
            'initial_value': 'USD',
          },
          {
            'type': 'button_primary',
            'label': 'Save Financial Settings',
            'action': {
              'type': 'form_submit',
              'endpoint': '/api/tenant/settings/financial',
              'method': 'POST',
              'success_toast': 'Financial settings updated successfully',
            },
          },
        ],
      };

      await tester.pumpWidget(
        MaterialApp(
          home: DynamicSchemaPage(
            schema: schema,
            requestExecutor: (endpoint, {required method, data}) async {
              submittedEndpoint = endpoint;
              submittedMethod = method;
              submittedData = Map<String, dynamic>.from(data ?? const {});
              return {
                'success': true,
                'message': 'Financial settings updated successfully',
              };
            },
          ),
        ),
      );

      expect(find.text('Financial Settings'), findsOneWidget);
      expect(find.text('USD'), findsOneWidget);
      expect(find.text('Save Financial Settings'), findsOneWidget);

      // Tap button to submit form
      await tester.tap(find.byType(ElevatedButton));
      await tester.pumpAndSettle();

      expect(submittedEndpoint, '/api/tenant/settings/financial');
      expect(submittedMethod, 'POST');
      expect(submittedData, containsPair('currency', 'USD'));
      expect(
          find.text('Financial settings updated successfully'), findsOneWidget);
    });

    testWidgets('validates server-declared required fields before submission',
        (tester) async {
      final schema = {
        'title': 'Profile',
        'layout': 'column',
        'components': [
          {
            'type': 'text_input',
            'name': 'business_name',
            'label': 'Business Name',
            'required': true,
            'initial_value': '',
          },
          {
            'type': 'button_primary',
            'label': 'Save',
            'action': {
              'type': 'form_submit',
              'endpoint': '/api/tenant/settings/profile',
            },
          },
        ],
      };

      await tester.pumpWidget(
        MaterialApp(home: DynamicSchemaPage(schema: schema)),
      );
      await tester.tap(find.text('Save'));
      await tester.pump();

      expect(find.text('Business Name is required.'), findsOneWidget);
      expect(
          find.text('Please correct the highlighted fields.'), findsOneWidget);
    });

    testWidgets('routes unfamiliar paths dynamically to DynamicSchemaPage',
        (tester) async {
      final registry = SduiComponentRegistry.instance;
      // Unmapped vertical e.g. 'hotel_booking'
      final builder = registry.resolve('hotel_booking');
      expect(builder, isNotNull);

      await tester.pumpWidget(
        MaterialApp(
          home: Builder(builder: builder),
        ),
      );

      expect(find.byType(DynamicSchemaPage), findsOneWidget);
    });

    testWidgets('resolves targetEndpoint explicitly to DynamicSchemaPage',
        (tester) async {
      final registry = SduiComponentRegistry.instance;
      final builder = registry.resolve(
        'store_mode',
        targetEndpoint: '/api/tenant/views/settings-mode',
      );
      expect(builder, isNotNull);

      await tester.pumpWidget(
        MaterialApp(
          home: Builder(builder: builder),
        ),
      );

      expect(find.byType(DynamicSchemaPage), findsOneWidget);
    });

    testWidgets(
        'button_danger shows confirmation dialog and dispatches on confirm',
        (tester) async {
      Map<String, dynamic>? dispatchedAction;
      final schema = {
        'type': 'button_danger',
        'label': 'Clear Sample Demo Data',
        'icon': 'delete_forever',
        'action': {
          'type': 'form_submit',
          'endpoint': '/api/tenant/demo-data',
          'method': 'DELETE',
          'confirm_message':
              'Are you sure you want to delete all sample demo items?',
        },
      };

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: DynamicSchemaContext(
              formValues: const {},
              setFormValue: (_, __) {},
              dispatchAction: (action) async {
                dispatchedAction = action;
              },
              child: Builder(
                builder: (ctx) =>
                    DynamicSchemaParser.buildComponent(ctx, schema),
              ),
            ),
          ),
        ),
      );

      expect(find.text('Clear Sample Demo Data'), findsOneWidget);

      // Tap button -> should open AlertDialog
      await tester.tap(find.text('Clear Sample Demo Data'));
      await tester.pumpAndSettle();

      expect(
          find.text('Are you sure you want to delete all sample demo items?'),
          findsOneWidget);
      expect(find.text('Proceed'), findsOneWidget);
      expect(find.text('Cancel'), findsOneWidget);

      // Tap Proceed -> should dispatch action
      await tester.tap(find.text('Proceed'));
      await tester.pumpAndSettle();

      expect(dispatchedAction, isNotNull);
      expect(dispatchedAction!['endpoint'], '/api/tenant/demo-data');
      expect(dispatchedAction!['method'], 'DELETE');
    });

    testWidgets('button_primary renders in system forest green',
        (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: ThemeData(colorSchemeSeed: const Color(0xFF2563EB)),
          home: Scaffold(
            body: DynamicSchemaContext(
              formValues: const {},
              setFormValue: (_, __) {},
              dispatchAction: (_) async {},
              child: Builder(
                builder: (ctx) => DynamicSchemaParser.buildComponent(ctx, {
                  'type': 'button_primary',
                  'label': 'Invoice & Receipt Options',
                  'icon': 'receipt_long',
                  'action': {
                    'type': 'show_post_sale_sheet',
                    'data': {'invoice_number': 'INV-0028'},
                  },
                }),
              ),
            ),
          ),
        ),
      );

      final button = tester.widget<ElevatedButton>(find.byType(ElevatedButton));
      final bg = button.style?.backgroundColor?.resolve(<WidgetState>{});
      expect(bg, const Color(0xFF166534));
    });
  });
}
