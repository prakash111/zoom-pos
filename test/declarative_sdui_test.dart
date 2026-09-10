import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/core/sdui/dynamic_schema_context.dart';
import 'package:zoom_pos_mobile/core/sdui/dynamic_schema_parser.dart';
import 'package:zoom_pos_mobile/core/sdui/screens/dynamic_schema_page.dart';
import 'package:zoom_pos_mobile/core/sdui/sdui_component_registry.dart';
import 'package:zoom_pos_mobile/features/settings/screens/global_printer_setup_screen.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  // A DynamicSchemaPage that can't reach the server falls back to the on-disk
  // SchemaCache (SharedPreferences); an empty mock store lets that resolve
  // instantly instead of stalling on an unregistered plugin channel.
  setUp(() => SharedPreferences.setMockInitialValues({}));

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

    testWidgets(
        'a scrollable row lays chips out horizontally without clipping labels',
        (tester) async {
      final schema = {
        'type': 'row',
        'scrollable': true,
        'spacing': 8,
        'components': [
          {
            'type': 'button_primary',
            'label': 'All (3)',
            'dense': true,
            'full_width': false,
            'action': {'type': 'pop'},
          },
          {
            'type': 'button_outlined',
            'label': 'Pending (1)',
            'dense': true,
            'full_width': false,
            'action': {'type': 'pop'},
          },
          {
            'type': 'button_outlined',
            'label': 'Dispensed (2)',
            'dense': true,
            'full_width': false,
            'action': {'type': 'pop'},
          },
        ],
      };

      // A deliberately narrow viewport — the un-scrollable version ellipsized
      // here ("Pendi...", "Dispe...").
      tester.view.physicalSize = const Size(320, 800);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.reset);

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (ctx) => DynamicSchemaParser.buildComponent(ctx, schema),
            ),
          ),
        ),
      );

      expect(find.byType(SingleChildScrollView), findsOneWidget);
      // Every label is present and rendered in full (no RenderFlex overflow,
      // no ellipsis truncation).
      for (final label in ['All (3)', 'Pending (1)', 'Dispensed (2)']) {
        final textWidget = tester.widget<Text>(find.text(label));
        expect(textWidget.overflow, TextOverflow.ellipsis);
        final painter = TextPainter(
          text: TextSpan(text: label, style: textWidget.style),
          textDirection: TextDirection.ltr,
          maxLines: 1,
        )..layout();
        expect(painter.didExceedMaxLines, isFalse,
            reason: '"$label" should fit on one line at its natural width');
      }
      expect(tester.takeException(), isNull);
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

    testWidgets(
        'tabs honor initial_index and is_scrollable and accept label or title',
        (tester) async {
      final schema = {
        'type': 'tabs',
        'initial_index': 2,
        'is_scrollable': true,
        'tabs': [
          {
            'id': 'active',
            'label': 'Active Batches',
            'components': [
              {'type': 'text', 'text': 'ACTIVE BODY'}
            ],
          },
          {
            'id': 'register',
            'label': 'Register Batch',
            'components': [
              {'type': 'text', 'text': 'REGISTER BODY'}
            ],
          },
          {
            'id': 'adjust',
            'label': 'Stock Adjust & Returns',
            'components': [
              {'type': 'text', 'text': 'ADJUST BODY'}
            ],
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
      await tester.pumpAndSettle();

      // All three tab labels are present in full.
      expect(find.text('Active Batches'), findsOneWidget);
      expect(find.text('Register Batch'), findsOneWidget);
      expect(find.text('Stock Adjust & Returns'), findsOneWidget);
      expect(tester.widget<TabBar>(find.byType(TabBar)).isScrollable, isTrue);

      // initial_index: 2 → the third tab's body is the visible one.
      expect(
          DefaultTabController.of(tester.element(find.text('Active Batches')))
              .index,
          2);
      expect(find.text('ADJUST BODY'), findsOneWidget);

      // Each tab body is a real scroll view (never a fixed, non-scrollable
      // container) so long forms + the submit button stay reachable.
      final bodyScroll = tester.widget<SingleChildScrollView>(
        find.ancestor(
          of: find.text('ADJUST BODY'),
          matching: find.byType(SingleChildScrollView),
        ),
      );
      expect(bodyScroll.physics, isA<AlwaysScrollableScrollPhysics>());
      expect(bodyScroll.padding, isNotNull);
    });

    testWidgets('text_input submit_action fires on keyboard submit',
        (tester) async {
      final formValues = <String, dynamic>{};
      Map<String, dynamic>? dispatched;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: DynamicSchemaContext(
              formValues: formValues,
              setFormValue: (k, v) => formValues[k] = v,
              dispatchAction: (a) async => dispatched = a,
              child: Builder(
                builder: (ctx) => DynamicSchemaParser.buildComponent(ctx, {
                  'type': 'text_input',
                  'name': 'search',
                  'label': 'Search',
                  'submit_action': {
                    'type': 'filter_view',
                    'endpoint': '/api/tenant/views/pharmacy-batches?tab=active',
                    'fields': ['search'],
                  },
                }),
              ),
            ),
          ),
        ),
      );

      await tester.enterText(find.byType(TextFormField), 'metformin');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pump();

      expect(formValues['search'], 'metformin');
      expect(dispatched?['type'], 'filter_view');
      expect(dispatched?['fields'], ['search']);
    });

    testWidgets('read_only + copyable text_input copies to clipboard',
        (tester) async {
      const url = 'https://saas.zoomnearby.com/api/v1/pos/webhooks/in/abc123';
      final clipboard = <MethodCall>[];
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(SystemChannels.platform, (call) async {
        if (call.method == 'Clipboard.setData') clipboard.add(call);
        return null;
      });
      addTearDown(() => TestDefaultBinaryMessengerBinding
          .instance.defaultBinaryMessenger
          .setMockMethodCallHandler(SystemChannels.platform, null));

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: DynamicSchemaContext(
              formValues: const {},
              setFormValue: (_, __) {},
              dispatchAction: (_) async {},
              child: Builder(
                builder: (ctx) => DynamicSchemaParser.buildComponent(ctx, {
                  'type': 'text_input',
                  'name': 'inbound_webhook_url',
                  'label': 'Your Unique Webhook Endpoint URL',
                  'initial_value': url,
                  'read_only': true,
                  'copyable': true,
                  'copy_tooltip': 'Copy webhook endpoint',
                  'copy_toast': 'Webhook URL copied to clipboard!',
                }),
              ),
            ),
          ),
        ),
      );

      final field = tester.widget<TextFormField>(find.byType(TextFormField));
      expect(field.enabled, isTrue); // sharp + selectable, not greyed out

      expect(find.byTooltip('Copy webhook endpoint'), findsOneWidget);
      await tester.tap(find.byIcon(Icons.copy_rounded));
      await tester.pump();

      expect(clipboard, hasLength(1));
      expect(clipboard.single.arguments['text'], url);
      expect(find.text('Webhook URL copied to clipboard!'), findsOneWidget);
    });

    testWidgets('creatable_select binds a preset, then a typed custom reason',
        (tester) async {
      final formValues = <String, dynamic>{};
      final schema = {
        'type': 'creatable_select',
        'name': 'reason',
        'label': 'Adjustment Reason',
        'allow_custom': true,
        'custom_value': '__custom__',
        'custom_label': '+ Other / Custom Reason',
        'initial_value': 'Damaged stock',
        'options': [
          {
            'label': 'Physical audit / discrepancy',
            'value': 'Physical audit / discrepancy'
          },
          {'label': 'Damaged stock', 'value': 'Damaged stock'},
          {'label': 'Vendor return', 'value': 'Vendor return'},
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
      await tester.pumpAndSettle();

      // Starts on the preset from initial_value.
      expect(formValues['reason'], 'Damaged stock');
      expect(find.byType(TextFormField), findsNothing);

      // Pick "+ Other / Custom Reason" → a free-text field appears.
      await tester.tap(find.byType(DropdownButtonFormField<String>));
      await tester.pumpAndSettle();
      await tester.tap(find.text('+ Other / Custom Reason').last);
      await tester.pumpAndSettle();

      expect(find.byType(TextFormField), findsOneWidget);
      await tester.enterText(
          find.byType(TextFormField), 'Cold-chain temperature excursion');
      await tester.pump();

      expect(formValues['reason'], 'Cold-chain temperature excursion');
    });

    testWidgets('creatable_select opens in custom mode for an off-list value',
        (tester) async {
      final formValues = <String, dynamic>{'reason': 'Supplier recall lot 88B'};
      final schema = {
        'type': 'creatable_select',
        'name': 'reason',
        'label': 'Adjustment Reason',
        'options': [
          {'label': 'Damaged stock', 'value': 'Damaged stock'},
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
      await tester.pumpAndSettle();

      expect(find.byType(TextFormField), findsOneWidget);
      expect(
          tester
              .widget<TextFormField>(find.byType(TextFormField))
              .controller
              ?.text,
          'Supplier recall lot 88B');
      expect(formValues['reason'], 'Supplier recall lot 88B');
    });

    testWidgets(
        'search_bar is full-width, shows the full hint, and filters on submit',
        (tester) async {
      final formValues = <String, dynamic>{};
      Map<String, dynamic>? dispatched;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SizedBox(
              width: 360,
              child: DynamicSchemaContext(
                formValues: formValues,
                setFormValue: (k, v) => formValues[k] = v,
                dispatchAction: (a) async => dispatched = a,
                child: Builder(
                  builder: (ctx) => DynamicSchemaParser.buildComponent(ctx, {
                    'type': 'search_bar',
                    'name': 'search',
                    'placeholder': 'Search medicine name, batch #, or rack...',
                    'prefix_icon': 'search',
                    'clearable': true,
                    'action': {
                      'type': 'filter_view',
                      'endpoint':
                          '/api/tenant/views/pharmacy-batches?tab=active',
                      'fields': ['search'],
                    },
                  }),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Full hint present (no "Search medici..." truncation) and full width
      // (the field spans the 360px parent, minus the 1px pill border).
      expect(find.text('Search medicine name, batch #, or rack...'),
          findsOneWidget);
      expect(tester.getSize(find.byType(TextField)).width, greaterThan(355));
      expect(find.byIcon(Icons.clear), findsNothing);

      await tester.enterText(find.byType(TextField), 'Amoxicillin');
      await tester.pump();
      // Clear button appears once there's text.
      expect(find.byIcon(Icons.clear), findsOneWidget);

      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pump();
      expect(formValues['search'], 'Amoxicillin');
      expect(dispatched?['type'], 'filter_view');
      expect(dispatched?['fields'], ['search']);

      // Clear wipes the field and re-fires the filter (now unscoped).
      dispatched = null;
      await tester.tap(find.byIcon(Icons.clear));
      await tester.pump();
      expect(formValues['search'], '');
      expect(dispatched?['type'], 'filter_view');
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

    testWidgets(
        'customer_selector renders lookup UI and binds customer details',
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
      expect(
          find.text('Search CRM customer by name or phone...'), findsOneWidget);
      expect(find.text('Sarah Connor'), findsOneWidget);
      expect(find.text('+1 555 123 4567'), findsOneWidget);

      expect(formValues['customer_name'], 'Sarah Connor');
      expect(formValues['customer_phone'], '+1 555 123 4567');
    });

    testWidgets('file_picker renders the upload card, not a raw URL text field',
        (tester) async {
      final formValues = <String, dynamic>{};
      final schema = {
        'type': 'file_picker',
        'name': 'rx_attachment_url',
        'label': 'Prescription Document / Photo',
        'hint': 'Upload prescription photo or PDF (non-executable only)',
        'upload_endpoint': '/api/tenant/uploads/prescription-doc',
        'allowed_extensions': ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'heic'],
        'max_size_mb': 10,
        'allow_camera': true,
        'allow_gallery': true,
        'allow_document': true,
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

      expect(find.text('Prescription Document / Photo'), findsOneWidget);
      expect(find.text('Tap to upload'), findsOneWidget);
      expect(
        find.text('Upload prescription photo or PDF (non-executable only)'),
        findsOneWidget,
      );
      // It's an upload affordance, never a plain text input for a URL.
      expect(find.byType(TextField), findsNothing);
      expect(find.byIcon(Icons.cloud_upload_outlined), findsOneWidget);
    });

    testWidgets('file_picker shows a removable preview when a URL is bound',
        (tester) async {
      final formValues = <String, dynamic>{
        'rx_attachment_url': 'https://cdn.example.test/uploads/abc123.pdf',
      };
      final schema = {
        'type': 'file_picker',
        'name': 'rx_attachment_url',
        'label': 'Prescription Document / Photo',
        'upload_endpoint': '/api/tenant/uploads/prescription-doc',
        'allowed_extensions': ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'heic'],
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
      await tester.pumpAndSettle();

      // PDF preview badge + a remove (X) button.
      expect(find.byIcon(Icons.picture_as_pdf_outlined), findsOneWidget);
      expect(find.byIcon(Icons.close), findsOneWidget);
      expect(find.text('Tap to upload'), findsNothing);

      await tester.tap(find.byIcon(Icons.close));
      await tester.pumpAndSettle();

      expect(formValues['rx_attachment_url'], isNull);
      expect(find.text('Tap to upload'), findsOneWidget);
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

    test(
        'navigate to the native "pos" key does not fall through to a schema page',
        () {
      // The pharmacy "Pharmacy POS" quick action and the drawer entry both
      // navigate to the bare route key 'pos' — it must resolve to a native
      // screen, never a DynamicSchemaPage of the retired counter screen.
      expect(
        SduiComponentRegistry.resolveRoute('pos'),
        isNot(isA<DynamicSchemaPage>()),
      );
      // 'pharmacy_pos' is aliased to the same native POS screen.
      expect(
        SduiComponentRegistry.resolveRoute('pharmacy_pos'),
        isNot(isA<DynamicSchemaPage>()),
      );
      // A genuine /api/ view still routes to the dynamic renderer.
      expect(
        SduiComponentRegistry.resolveRoute('/api/tenant/views/pharmacy-pos'),
        isA<DynamicSchemaPage>(),
      );
    });

    test('the "printer_setup" route resolves to the native hardware screen',
        () {
      // Drawer row ("Printer & Hardware Setup") and the Receipt Settings
      // button both navigate to the bare key — it must open the on-device
      // pairing screen, never a schema page.
      expect(
        SduiComponentRegistry.resolveRoute('printer_setup'),
        isA<GlobalPrinterSetupScreen>(),
      );
      expect(
        SduiComponentRegistry.resolveRoute('hardware_settings'),
        isA<GlobalPrinterSetupScreen>(),
      );
    });

    testWidgets(
        'drawer resolve() prefers the native printer screen over its fallback endpoint',
        (tester) async {
      // The drawer passes component="printer_setup" AND a target_endpoint;
      // the native registration must win.
      final builder = SduiComponentRegistry.instance.resolve(
        'printer_setup',
        targetEndpoint: '/api/tenant/views/printer-setup',
      );
      await tester.pumpWidget(MaterialApp(home: Builder(builder: builder)));
      expect(find.byType(GlobalPrinterSetupScreen), findsOneWidget);
      expect(find.byType(DynamicSchemaPage), findsNothing);
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
