import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/core/models/settings_models.dart';
import 'package:zoom_pos_mobile/core/navigation/navigation_provider.dart';
import 'package:zoom_pos_mobile/core/sdui/models/sdui_models.dart';
import 'package:zoom_pos_mobile/core/utils/image_url.dart';
import 'package:zoom_pos_mobile/widgets/app_drawer.dart';
import 'package:zoom_pos_mobile/widgets/tenant_logo_avatar.dart';

void main() {
  group('TenantLogoAvatar and Image URL Tests', () {
    test('resolveImageUrl normalizes http to https and prepends base url', () {
      expect(resolveImageUrl('http://example.com/logo.png'),
          'https://example.com/logo.png');
      expect(resolveImageUrl('https://example.com/logo.png'),
          'https://example.com/logo.png');
      expect(
          resolveImageUrl('/storage/logos/store.png'), startsWith('https://'));
      expect(resolveImageUrl(null), isNull);
      expect(resolveImageUrl(''), isNull);
    });

    testWidgets(
        'TenantLogoAvatar renders emerald green fallback pill with initial when imageUrl is null',
        (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: TenantLogoAvatar(
              imageUrl: null,
              tenantName: 'Pharmacy Demo',
              size: 48,
            ),
          ),
        ),
      );

      // Verify container decoration has emerald color (0xFF10B981)
      final containerFinder = find.byType(Container);
      expect(containerFinder, findsOneWidget);
      final container = tester.widget<Container>(containerFinder);
      final decoration = container.decoration as BoxDecoration;
      expect(decoration.color, const Color(0xFF10B981));

      // Verify text shows first initial 'P'
      expect(find.text('P'), findsOneWidget);
    });

    testWidgets(
        'TenantLogoAvatar renders emerald green fallback pill when imageUrl is empty',
        (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: TenantLogoAvatar(
              imageUrl: '',
              tenantName: 'zoom Pos Store',
              size: 40,
            ),
          ),
        ),
      );

      expect(find.text('Z'), findsOneWidget);
      final container = tester.widget<Container>(find.byType(Container));
      final decoration = container.decoration as BoxDecoration;
      expect(decoration.color, const Color(0xFF10B981));
    });

    testWidgets(
        'TenantLogoAvatar renders image widget when valid HTTPS URL provided',
        (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: TenantLogoAvatar(
              imageUrl: 'https://cdn.example.com/logo.png',
              tenantName: 'Acme Corp',
              size: 40,
            ),
          ),
        ),
      );

      expect(find.byType(Image), findsOneWidget);
    });
  });

  group('Defensive Map & Web Navigation Parsing Tests', () {
    test(
        'NavigationProvider.safeMap handles non-string keys and arbitrary structures gracefully',
        () {
      expect(NavigationProvider.safeMap(null), isEmpty);
      expect(NavigationProvider.safeMap('not a map'), isEmpty);
      expect(NavigationProvider.safeMap([1, 2, 3]), isEmpty);

      // Map with non-string keys (simulating minified JS map structures)
      final dynamic rawMap = {
        1: 'numeric_key',
        'key': 'pos',
        'title': 'Point of Sale',
      };
      final safe = NavigationProvider.safeMap(rawMap);
      expect(safe['1'], 'numeric_key');
      expect(safe['key'], 'pos');
      expect(safe['title'], 'Point of Sale');
    });

    test('safeList accepts sparse PHP arrays encoded as JSON objects', () {
      final values = NavigationProvider.safeList({
        '3': {'key': 'pos'},
        '8': {'key': 'sales'},
      });

      expect(values, hasLength(2));
      expect(NavigationProvider.safeMap(values.first)['key'], 'pos');
    });

    test(
        'NavigationProvider.parseNavigation successfully parses nav_v2 schema without cast exceptions',
        () {
      final provider = NavigationProvider();

      // Payload mimicking minified web payload with mixed keys and nested lists
      final dynamic rawPayload = {
        'navigation_schema_version': 2,
        'sections': [
          {
            'key': 'cashier_sales',
            'title': 'Cashier & Sales',
            'color': '#1d4ed8',
            'items': [
              {
                'key': 'pos',
                'title': 'Point of Sale',
                'icon': 'point_of_sale',
                'route': '/pos',
                'children': [],
              },
              {
                'key': 'sales',
                'title': 'Sales & Invoices',
                'icon': 'receipt_long',
                'route': '/sales',
                'children': [
                  {
                    'key': 'invoices',
                    'title': 'Tax Invoices',
                    'route': '/sales/invoices',
                  }
                ],
              }
            ]
          }
        ]
      };

      provider.parseNavigation(rawPayload);

      expect(provider.error, isNull);
      expect(provider.sections.length, 1);
      final section = provider.sections.first;
      expect(section.key, 'cashier_sales');
      expect(section.title, 'Cashier & Sales');
      expect(section.items.length, 2);

      final posItem = section.items[0];
      expect(posItem.key, 'pos');
      expect(posItem.title, 'Point of Sale');
      expect(posItem.children, isEmpty);

      final salesItem = section.items[1];
      expect(salesItem.key, 'sales');
      expect(salesItem.children.length, 1);
      expect(salesItem.children[0].key, 'invoices');
    });

    test(
        'navigation parsers recover object-shaped sections, items, and children',
        () {
      final objectShapedSections = {
        '4': {
          'key': 'cashier_sales',
          'title': 'Cashier & Sales',
          'items': {
            '3': {
              'key': 'reports',
              'title': 'Reports',
              'children': {
                '7': {'key': 'sales_report', 'title': 'Sales Report'},
              },
            },
          },
        },
      };

      final provider = NavigationProvider()
        ..parseNavigation({'sections': objectShapedSections});
      expect(provider.error, isNull);
      expect(provider.sections.single.items.single.children.single.key,
          'sales_report');

      final sduiSection = SduiNavSectionSchema.fromJson(
        NavigationProvider.safeMap(objectShapedSections['4']),
      );
      expect(sduiSection.items.single.children.single.key, 'sales_report');

      final navConfig = NavConfig.fromJson({
        'tree': objectShapedSections,
      });
      expect(navConfig.sections.single.key, 'cashier_sales');
      expect(navConfig.items.map((item) => item.key),
          containsAll(['reports', 'sales_report']));
    });
  });

  group('DrawerItemParser Point of Sale and Dropdown Arrow Rules', () {
    test(
        'DrawerItemParser.hasChildren strictly requires non-empty children list',
        () {
      expect(DrawerItemParser.hasChildren({'key': 'pos', 'children': null}),
          isFalse);
      expect(DrawerItemParser.hasChildren({'key': 'pos', 'children': []}),
          isFalse);
      expect(
          DrawerItemParser.hasChildren({'key': 'pos', 'children': 'invalid'}),
          isFalse);
      expect(
          DrawerItemParser.hasChildren({
            'key': 'pos',
            'children': [
              {'key': 'quick_pos', 'title': 'Quick POS'}
            ]
          }),
          isTrue);
    });

    testWidgets(
        'Point of Sale renders flat ListTile without dropdown arrow when children is empty',
        (tester) async {
      final item = <String, dynamic>{
        'key': 'pos',
        'title': 'Point of Sale',
        'level': 0,
        'indent': 0,
        'children': [],
      };

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => DrawerItemParser.buildDrawerMenuItem(
                context,
                item,
                activeColor: const Color(0xFF1D4ED8),
              ),
            ),
          ),
        ),
      );

      // Must be a ListTile
      expect(find.byType(ListTile), findsOneWidget);
      // Must NOT be an ExpansionTile
      expect(find.byType(ExpansionTile), findsNothing);
      // No '↳' indicator
      expect(find.text('↳'), findsNothing);
      expect(find.text('Point of Sale'), findsOneWidget);
    });

    testWidgets(
        'Item with non-empty children renders ExpansionTile with sub-items',
        (tester) async {
      final item = <String, dynamic>{
        'key': 'sales',
        'title': 'Sales & Invoices',
        'level': 0,
        'indent': 0,
        'children': [
          {
            'key': 'invoices',
            'title': 'Invoices',
            'level': 1,
            'indent': 1,
          }
        ],
      };

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => DrawerItemParser.buildDrawerMenuItem(
                context,
                item,
                activeColor: const Color(0xFF1D4ED8),
              ),
            ),
          ),
        ),
      );

      // Must render an ExpansionTile
      expect(find.byType(ExpansionTile), findsOneWidget);
      expect(find.text('Sales & Invoices'), findsOneWidget);
    });

    test('SduiUiSchema parses minified dynamic map without TypeError', () {
      // Simulate raw dynamic JS maps where types are not Map<String, dynamic>
      final dynamic rawUiSchema = {
        'payment_methods': [
          {
            'id': 'cash',
            'code': 'cash',
            'name': 'Cash',
            'metadata': {'quick_cash': true},
          }
        ],
        'status_labels': {
          'orders': {
            'pending': {'label': 'Pending', 'color': '#f59e0b'},
          }
        },
        'tax': {
          'tax_id': 'vat_standard',
          'tax_label': 'VAT',
          'display_mode': 'exclusive',
          'is_india': false,
          'sub_components': [
            {'key': 'cgst', 'label': 'CGST', 'split': 0.5}
          ],
        },
        'action_pills': [
          {'key': 'refund', 'label': 'Refund', 'icon': 'receipt'},
        ],
      };

      final uiSchema = SduiUiSchema.fromJson(rawUiSchema);
      expect(uiSchema.paymentMethods, hasLength(1));
      expect(uiSchema.paymentMethods.first.code, 'cash');
      expect(uiSchema.tax.taxLabel, 'VAT');
      expect(uiSchema.tax.subComponents, hasLength(1));
      expect(uiSchema.actionPills, hasLength(1));
      expect(uiSchema.statusFor('orders', 'pending')?.label, 'Pending');
    });
  });

  group('Tenant Model, Local Cache and Drawer Header Resilience Tests', () {
    setUp(() {
      SharedPreferences.setMockInitialValues({});
    });

    test('Tenant.fromJson parses diverse schemas and drawer_header payloads', () {
      final json = {
        'id': 'tenant_123',
        'store_name': 'ZoomWeb Retail',
        'trade_name': 'ZoomWeb SuperStore',
        'business_type': 'PHARMACY',
        'logo_url': 'https://example.com/logo.png',
        'drawer_header': {
          'store_type': 'PHARMACY',
          'badge': 'PHARMACY',
        }
      };

      final tenant = Tenant.fromJson(json);
      expect(tenant.id, 'tenant_123');
      expect(tenant.displayName, 'ZoomWeb SuperStore');
      expect(tenant.displayType, 'PHARMACY');
      expect(tenant.logoUrl, 'https://example.com/logo.png');
    });

    testWidgets('TenantLogoAvatar supports logoUrl named parameter with fallback', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: TenantLogoAvatar(
              logoUrl: null,
              tenantName: 'Retail Hub',
              size: 44,
            ),
          ),
        ),
      );

      expect(find.text('R'), findsOneWidget);
    });

    test('NavigationProvider loads cached tenant profile from SharedPreferences', () async {
      SharedPreferences.setMockInitialValues({
        'cached_tenant_profile': jsonEncode({
          'id': 'cached_t1',
          'name': 'Cached Store',
          'trade_name': 'ZoomWeb Cached',
          'business_type': 'RETAIL',
          'logo_url': 'https://example.com/cached_logo.png',
        }),
      });

      final provider = NavigationProvider();
      expect(provider.currentTenant, isNull);

      final loaded = await provider.loadCachedTenant();
      expect(loaded, isNotNull);
      expect(provider.currentTenant?.displayName, 'ZoomWeb Cached');
      expect(provider.currentTenant?.displayType, 'RETAIL');
      expect(provider.currentTenant?.logoUrl, 'https://example.com/cached_logo.png');
    });

    test('NavigationProvider.syncBootstrap never wipes currentTenant on failure', () async {
      final provider = NavigationProvider();
      final tenant = Tenant.fromJson({
        'id': 'active_1',
        'name': 'ZoomWeb Active',
        'trade_name': 'ZoomWeb Active',
        'business_type': 'RETAIL',
      });

      await provider.updateTenant(tenant);
      expect(provider.currentTenant?.displayName, 'ZoomWeb Active');

      // Trigger a failure in syncBootstrap by passing an invalid payload/throwing error
      await provider.syncBootstrap('invalid_token', Object());

      // Current tenant must still be preserved!
      expect(provider.currentTenant, isNotNull);
      expect(provider.currentTenant?.displayName, 'ZoomWeb Active');
      expect(provider.error, isNotNull);
    });

    testWidgets('buildDrawerHeader renders store logo, brand name, and badge when tenant is provided', (tester) async {
      final tenant = Tenant.fromJson({
        'id': 't_test',
        'name': 'ZoomWeb Store',
        'trade_name': 'ZoomWeb Store',
        'business_type': 'RETAIL',
      });

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => buildDrawerHeader(
                context,
                tenant: tenant,
              ),
            ),
          ),
        ),
      );

      // Verify header renders without collapsing
      expect(find.byKey(const ValueKey('app-drawer-header')), findsOneWidget);
      expect(find.text('ZoomWeb Store'), findsOneWidget);
      expect(find.text('RETAIL'), findsOneWidget);
      expect(find.byType(TenantLogoAvatar), findsOneWidget);
    });

    testWidgets('buildDrawerHeader never collapses into SizedBox.shrink when tenant is null', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => buildDrawerHeader(
                context,
                tenant: null,
              ),
            ),
          ),
        ),
      );

      // Verify fallback name and badge render
      expect(find.byKey(const ValueKey('app-drawer-header')), findsOneWidget);
      expect(find.text('ZoomNearby Enterprise'), findsOneWidget);
      expect(find.text('RETAIL'), findsOneWidget);
      expect(find.byType(TenantLogoAvatar), findsOneWidget);
    });

    testWidgets('AppDrawer renders successfully with header and navigation items', (tester) async {
      final tenant = Tenant.fromJson({
        'id': 't_app_drawer',
        'name': 'ZoomWeb POS',
        'business_type': 'RETAIL',
      });

      final sections = [
        const NavSection(
          key: 'main',
          title: 'Main Navigation',
          items: [
            NavItem(key: 'pos', title: 'Point of Sale'),
            NavItem(key: 'sales', title: 'Sales'),
          ],
        ),
      ];

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            drawer: AppDrawer(
              tenant: tenant,
              sections: sections,
            ),
            body: const Center(child: Text('Content')),
          ),
        ),
      );

      // Open drawer
      final scaffoldState = tester.state<ScaffoldState>(find.byType(Scaffold));
      scaffoldState.openDrawer();
      await tester.pumpAndSettle();

      expect(find.byType(AppDrawer), findsOneWidget);
      expect(find.text('ZoomWeb POS'), findsOneWidget);
      expect(find.text('RETAIL'), findsOneWidget);
      expect(find.text('MAIN NAVIGATION'), findsOneWidget);
      expect(find.text('Point of Sale'), findsOneWidget);
      expect(find.text('Sales'), findsOneWidget);
    });
  });
}
