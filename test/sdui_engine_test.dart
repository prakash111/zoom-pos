import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/core/config/bootstrap_cache.dart';
import 'package:zoom_pos_mobile/core/sdui/models/sdui_models.dart';
import 'package:zoom_pos_mobile/core/sdui/sdui_component_registry.dart';
import 'package:zoom_pos_mobile/core/sdui/sdui_icon_registry.dart';
import 'package:zoom_pos_mobile/core/widgets/sdui/sdui_containers.dart';
import 'package:zoom_pos_mobile/core/widgets/sdui/sdui_controls.dart';

void main() {
  group('SDUI Models & Serialization', () {
    test('TenantSchema and ModuleSchema decode correctly from JSON payload',
        () {
      final json = {
        'tenant': {
          'id': 'tenant_123',
          'business_name': 'Zoom Fresh',
          'active_mode': 'pharmacy',
          'available_modes': ['retail', 'pharmacy', 'service_booking']
        },
        'modules': {
          'pharmacy': {
            'id': 'pharmacy',
            'title': 'Pharmacy & Chemist',
            'layout_type': 'standard_grid',
            'features': {
              'has_batch_tracking': true,
              'has_expiry_alerts': true,
              'has_prescription_upload': true,
              'has_barcode_scanner': true,
            },
            'cart_configuration': {
              'show_customer_selector': true,
              'allow_split_payment': true,
              'tax_display': 'itemized',
            }
          }
        },
        'menu_structure': [
          {
            'key': 'pharmacy_dispensary',
            'title': 'Pharmacy & Dispensary',
            'color': '#0d9488',
            'items': [
              {
                'key': 'dispensary_pos',
                'title': 'Dispensary POS',
                'icon': 'medication',
                'component': 'pos',
                'permission': 'pos',
              },
              {
                'key': 'prescriptions',
                'title': 'Doctor Prescriptions',
                'icon': 'receipt_long',
                'component': 'prescriptions',
                'permission': 'sales',
              }
            ]
          }
        ],
        'ui_schema': {
          'payment_methods': [
            {
              'id': 'cash',
              'code': 'cash',
              'title': 'Cash Payment',
              'icon': 'payments',
              'color': '#15803d',
              'requires_reference': false,
            },
            {
              'id': 'upi_qr',
              'code': 'upi_qr',
              'title': 'UPI & QR Code',
              'icon': 'qr_code_2',
              'color': '#7e22ce',
              'requires_reference': true,
            }
          ],
          'status_labels': {
            'sale': {
              'paid': {
                'label': 'Fully Paid',
                'color': '#16a34a',
                'badge_style': 'solid'
              },
              'partial': {
                'label': 'Partially Settled',
                'color': '#ca8a04',
                'badge_style': 'subtle'
              },
            }
          },
          'tax': {
            'country': 'IN',
            'tax_label': 'GST',
            'has_sub_components': true,
            'sub_components': [
              {'code': 'CGST', 'label': 'Central GST', 'rate': 9.0},
              {'code': 'SGST', 'label': 'State GST', 'rate': 9.0},
            ]
          }
        }
      };

      final tenant =
          TenantSchema.fromJson(json['tenant'] as Map<String, dynamic>);
      expect(tenant.id, 'tenant_123');
      expect(tenant.businessName, 'Zoom Fresh');
      expect(tenant.activeMode, 'pharmacy');
      expect(tenant.availableModes, contains('pharmacy'));

      final modules = (json['modules'] as Map<String, dynamic>).map(
        (k, v) => MapEntry(k, ModuleSchema.fromJson(v as Map<String, dynamic>)),
      );
      expect(modules['pharmacy']?.title, 'Pharmacy & Chemist');
      expect(modules['pharmacy']?.features['has_batch_tracking'], isTrue);
      expect(modules['pharmacy']?.cartConfiguration.allowSplitPayment, isTrue);

      final menu = (json['menu_structure'] as List)
          .map((m) => SduiNavSectionSchema.fromJson(m as Map<String, dynamic>))
          .toList();
      expect(menu.length, 1);
      expect(menu.first.items.length, 2);
      expect(menu.first.items.first.title, 'Dispensary POS');

      final uiSchema =
          SduiUiSchema.fromJson(json['ui_schema'] as Map<String, dynamic>);
      expect(uiSchema.paymentMethods.length, 2);
      expect(uiSchema.paymentMethods.first.code, 'cash');
      expect(uiSchema.statusFor('sale', 'paid')?.label, 'Fully Paid');
      expect(uiSchema.tax.subComponents.length, 2);
      expect(uiSchema.tax.subComponents.first.code, 'CGST');
    });

    test(
        'SduiNavItemSchema decodes parent_id, effectiveParentId, and recursive children',
        () {
      final json = {
        'key': 'settings',
        'title': 'Store Settings',
        'icon': 'settings',
        'children': [
          {
            'key': 'settings_mode',
            'title': 'Store Operating Mode',
            'icon': 'tune',
            'parent_id': 'settings',
          },
          {
            'key': 'settings_profile',
            'title': 'Store Profile & Branding',
            'icon': 'storefront',
            'parent': 'settings',
          }
        ]
      };

      final item = SduiNavItemSchema.fromJson(json);
      expect(item.key, 'settings');
      expect(item.children.length, 2);
      expect(item.children[0].key, 'settings_mode');
      expect(item.children[0].effectiveParentId, 'settings');
      expect(item.children[1].key, 'settings_profile');
      expect(item.children[1].effectiveParentId, 'settings');
    });

    test('navigation parser keeps valid siblings when one item is malformed',
        () {
      final section = SduiNavSectionSchema.fromJson({
        'key': 'operations',
        'label': 'Operations',
        'items': [
          {
            'key': 'pos',
            'label': 'Point of Sale',
            'icon': 'point_of_sale',
            'children': null,
          },
          'not-an-object',
          {
            'key': 'settings',
            'label': 'Settings',
            'icon': <String, dynamic>{'unexpected': true},
            'children': 'not-a-list',
          },
        ],
      });

      expect(section.items.map((item) => item.key), ['pos', 'settings']);
      expect(section.items.first.children, isEmpty);
      expect(section.items.last.children, isEmpty);
      expect(SduiIconRegistry.resolve(section.items.last.icon),
          Icons.widgets_outlined);
    });
  });

  group('SDUI Icon and Color Registry', () {
    test('resolves server icon names to Material Icons', () {
      expect(SduiIconRegistry.resolve('point_of_sale'),
          Icons.point_of_sale_outlined);
      expect(SduiIconRegistry.resolve('restaurant'), Icons.restaurant_outlined);
      expect(SduiIconRegistry.resolve('medication'), Icons.medication_outlined);
      expect(SduiIconRegistry.resolve('soup_kitchen'),
          Icons.soup_kitchen_outlined);
      expect(SduiIconRegistry.resolve('qr_code'), Icons.qr_code_outlined);
      expect(
          SduiIconRegistry.resolve('unrecognized_xyz'), Icons.widgets_outlined);
    });

    test('parses hex colors correctly with or without hash', () {
      final c1 = SduiIconRegistry.parseColor('#16a34a');
      expect(c1.toARGB32(), const Color(0xFF16A34A).toARGB32());

      final c2 = SduiIconRegistry.parseColor('0284c7');
      expect(c2.toARGB32(), const Color(0xFF0284C7).toARGB32());

      final c3 = SduiIconRegistry.parseColor(null, fallback: Colors.red);
      expect(c3, Colors.red);
    });
  });

  group('SDUI Component Registry', () {
    test('resolves registered components and custom registered handlers', () {
      final registry = SduiComponentRegistry.instance;
      expect(registry.has('pos'), isTrue);
      expect(registry.has('restaurant_pos'), isTrue);
      expect(registry.has('cash_register'), isTrue);

      // Register new custom component for future hotel module
      registry.register(
          'hotel_rooms', (_) => const Scaffold(body: Text('Hotel Rooms')));
      expect(registry.has('hotel_rooms'), isTrue);

      final builder = registry.resolve('hotel_rooms');
      expect(builder, isNotNull);
    });

    test('does not require dedicated settings screen components', () {
      final registry = SduiComponentRegistry.instance;
      expect(registry.has('settings'), isTrue);
      expect(registry.has('settings_mode'), isFalse);
      expect(registry.has('settings_profile'), isFalse);
      expect(registry.has('settings_receipts'), isFalse);
      expect(registry.has('settings_financial'), isFalse);
      expect(registry.has('settings_billing'), isFalse);
      expect(registry.has('settings_navigation'), isTrue);
      expect(registry.has('navigation'), isTrue);
    });

    test('resolves core navigation views to working screen builders', () {
      final registry = SduiComponentRegistry.instance;
      final coreViews = [
        'pos',
        'restaurant_pos',
        'floor_plan',
        'kitchen_display',
        'sales',
        'cash_register',
        'quotations',
        'consignments',
        'service_orders',
        'customers',
        'due_receivables',
        'payables',
        'sales_targets',
        'reports',
        'analytics',
        'inventory',
        'categories',
        'brands',
        'units',
        'suppliers',
        'taxes',
        'catalog',
        'subscription',
        'settings',
        'navigation',
        'languages',
        'staff',
        'devices',
      ];

      for (final viewKey in coreViews) {
        expect(registry.has(viewKey), isTrue,
            reason: 'View key $viewKey should be registered');
        final builder = registry.resolve(viewKey,
            targetEndpoint: '/api/tenant/views/$viewKey');
        expect(builder, isNotNull,
            reason: 'Builder for $viewKey should not be null');
      }
    });
  });

  group('SDUI Dynamic Widgets', () {
    testWidgets('SduiActionPill renders badge and responds to taps',
        (tester) async {
      var tapped = false;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SduiActionPill(
              label: 'Hold Carts',
              icon: Icons.pause_circle_outline,
              badgeCount: 3,
              isActive: true,
              onTap: () => tapped = true,
            ),
          ),
        ),
      );

      expect(find.text('Hold Carts'), findsOneWidget);
      expect(find.text('3'), findsOneWidget);

      await tester.tap(find.byType(SduiActionPill));
      expect(tapped, isTrue);
    });

    testWidgets('SduiStatusBadge renders solid and subtle styles',
        (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: Column(
              children: [
                SduiStatusBadge(
                    label: 'COMPLETED', color: Colors.green, isSolid: true),
                SduiStatusBadge(
                    label: 'PENDING', color: Colors.amber, isSolid: false),
              ],
            ),
          ),
        ),
      );

      expect(find.text('COMPLETED'), findsOneWidget);
      expect(find.text('PENDING'), findsOneWidget);
    });

    testWidgets('SduiStepCounter increments and decrements quantity',
        (tester) async {
      var currentVal = 1;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: StatefulBuilder(
              builder: (context, setState) {
                return SduiStepCounter(
                  value: currentVal,
                  onChanged: (newVal) => setState(() => currentVal = newVal),
                );
              },
            ),
          ),
        ),
      );

      expect(find.text('1'), findsOneWidget);

      // Tap + button
      await tester.tap(find.byIcon(Icons.add));
      await tester.pump();
      expect(currentVal, 2);
      expect(find.text('2'), findsOneWidget);

      // Tap - button
      await tester.tap(find.byIcon(Icons.remove));
      await tester.pump();
      expect(currentVal, 1);
      expect(find.text('1'), findsOneWidget);
    });

    testWidgets(
        'SduiSideDrawerContainer renders hierarchical sub-items with branch indicator',
        (tester) async {
      final sections = [
        const SduiNavSectionSchema(
          key: 'admin',
          title: 'Administration',
          items: [
            SduiNavItemSchema(
              key: 'settings',
              title: 'Store Settings',
              icon: 'settings',
              children: [
                SduiNavItemSchema(
                  key: 'settings_mode',
                  title: 'Store Operating Mode',
                  icon: 'tune',
                  parentId: 'settings',
                ),
              ],
            ),
            SduiNavItemSchema(
              key: 'settings_mode',
              title: 'Store Operating Mode',
              icon: 'tune',
              parentId: 'settings',
            ),
          ],
        ),
      ];

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            drawer: SduiSideDrawerContainer(
              sections: sections,
              selectedKey: 'settings_mode',
              onItemTap: (_) {},
            ),
            body: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () => Scaffold.of(context).openDrawer(),
                child: const Text('Open'),
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.text('Open'));
      await tester.pumpAndSettle();

      expect(find.text('Store Settings'), findsOneWidget);
      expect(find.byType(ExpansionTile), findsOneWidget);
      expect(find.text('Store Operating Mode'), findsOneWidget);
      expect(find.text('↳'), findsOneWidget);
    });
  });

  group('BootstrapCache SDUI Integration', () {
    test(
        'empty cache reports navigation loading before disk hydration',
        () {
      final cache = BootstrapCache.instance;
      cache.menuStructure = [];
      expect(cache.effectiveSections, isEmpty);
      expect(cache.isNavigationLoading, isTrue);
    });

    test('disk hydration salvages valid sections from a damaged menu cache',
        () async {
      SharedPreferences.setMockInitialValues({
        'zoom_pos.bootstrap.menu': jsonEncode([
          'bad-section',
          {
            'key': 'operations',
            'label': 'Operations',
            'items': [
              'bad-item',
              {
                'key': 'pos',
                'label': 'Point of Sale',
                'icon': 'point_of_sale',
                'children': null,
              },
            ],
          },
          {
            'key': 'broken',
            'label': 'Broken',
            'items': 'not-a-list',
          },
        ]),
      });

      final cache = BootstrapCache.instance;
      cache.menuStructure = [];
      await cache.loadFromDisk();

      expect(cache.isNavigationLoading, isFalse);
      expect(cache.effectiveSections, hasLength(1));
      expect(cache.effectiveSections.single.key, 'operations');
      expect(cache.effectiveSections.single.items.single.key, 'pos');
    });

    test('accepts id and title interoperably for key and label', () {
      final section = SduiNavSectionSchema.fromJson({
        'id': 'cashier_pos',
        'title': 'Cashier & POS',
        'color': '#1d4ed8',
        'items': [
          {
            'id': 'pos_terminal',
            'title': 'Point of Sale',
            'icon': 'point_of_sale',
          },
        ],
      });

      expect(section.key, 'cashier_pos');
      expect(section.title, 'Cashier & POS');
      expect(section.items.first.key, 'pos_terminal');
      expect(section.items.first.title, 'Point of Sale');
    });

    test('effectiveSections falls back to baseline sections when hydrated cache is empty', () async {
      SharedPreferences.setMockInitialValues({
        'zoom_pos.bootstrap.menu': jsonEncode([]),
      });

      final cache = BootstrapCache.instance;
      cache.menuStructure = [];
      await cache.loadFromDisk();

      expect(cache.isNavigationLoading, isFalse);
      expect(cache.effectiveSections, isNotEmpty);
      expect(cache.effectiveSections.first.key, 'cashier_sales');
      expect(cache.effectiveSections.first.items.first.key, 'pos');
    });
  });
}
