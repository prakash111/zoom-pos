import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/sdui/screens/dynamic_schema_page.dart';
import 'package:zoom_pos_mobile/features/settings/screens/nav_menu_settings_tab.dart';
import 'package:zoom_pos_mobile/l10n/app_localizations.dart';

void main() {
  testWidgets(
      'SDUI tree_builder renders recursive rows with null child collections',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(800, 1000));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      MaterialApp(
        localizationsDelegates: const [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        supportedLocales: const [Locale('en')],
        home: DynamicSchemaPage(
          schema: {
            'type': 'screen',
            'schema_version': 1,
            'title': 'Navigation Menu',
            'layout': 'scroll_view',
            'components': [
              {
                'type': 'tree_builder',
                'tree_data': [
                  {
                    'key': 'sales',
                    'title': 'Sales',
                    'order': 0,
                    'items': [
                      {
                        'key': 'pos',
                        'title': 'Point of Sale',
                        'visible': true,
                        'children': [
                          {
                            'key': 'history',
                            'title': 'Sales History',
                            'visible': true,
                            'children': null,
                          },
                        ],
                      },
                    ],
                  },
                ],
              },
            ],
          },
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Sales'), findsOneWidget);
    expect(find.text('Point of Sale'), findsWidgets);
    expect(find.text('Sales History'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
      'NavMenuSettingsTab renders standalone with self-scaffold, header, and sections',
      (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        localizationsDelegates: [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        supportedLocales: [Locale('en')],
        home: NavMenuSettingsTab(),
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.byType(AppBar), findsOneWidget);
    expect(find.text('Navigation Menu'), findsOneWidget);
    expect(find.text('Main Menu · 0 px'), findsOneWidget);
    expect(find.text('Sub-Menu · 30 px'), findsOneWidget);
    expect(find.text('Sub-Sub-Menu · 60 px'), findsOneWidget);
    expect(find.text('Cashier & Sales'), findsOneWidget);
    expect(find.text('Point of Sale'), findsWidgets);
    expect(find.byType(ElevatedButton), findsOneWidget);
  });

  testWidgets(
      'DynamicSchemaPage renders full navigation schema with sections and save button',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(800, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      MaterialApp(
        localizationsDelegates: const [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        supportedLocales: const [Locale('en')],
        home: DynamicSchemaPage(
          schema: {
            'type': 'screen',
            'schema_version': 1,
            'title': 'Navigation Menu Customization',
            'layout': 'scroll_view',
            'components': [
              {
                'type': 'tree_builder',
                'active_mode': 'retail',
                'menu_structure': [
                  {
                    'key': 'cashier_sales',
                    'label': 'Cashier & Sales',
                    'items': [
                      {
                        'key': 'pos',
                        'title': 'Point of Sale',
                        'label': 'Point of Sale',
                        'visible': true,
                        'children': [],
                      },
                      {
                        'key': 'sales',
                        'title': 'Sales History',
                        'label': 'Sales History',
                        'visible': true,
                        'children': [],
                      },
                    ],
                  },
                ],
                'sections': [
                  {
                    'key': 'cashier_sales',
                    'label': 'Cashier & Sales',
                    'items': [
                      {
                        'key': 'pos',
                        'title': 'Point of Sale',
                        'label': 'Point of Sale',
                        'visible': true,
                        'children': [],
                      },
                      {
                        'key': 'sales',
                        'title': 'Sales History',
                        'label': 'Sales History',
                        'visible': true,
                        'children': [],
                      },
                    ],
                  },
                ],
                'tree_data': [
                  {
                    'key': 'cashier_sales',
                    'label': 'Cashier & Sales',
                    'items': [
                      {
                        'key': 'pos',
                        'title': 'Point of Sale',
                        'label': 'Point of Sale',
                        'visible': true,
                        'children': [],
                      },
                      {
                        'key': 'sales',
                        'title': 'Sales History',
                        'label': 'Sales History',
                        'visible': true,
                        'children': [],
                      },
                    ],
                  },
                ],
              },
            ],
          },
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('Navigation Menu Customization'), findsOneWidget);
    expect(find.text('Cashier & Sales'), findsOneWidget);
    expect(find.text('Point of Sale'), findsWidgets);
    expect(find.text('Sales History'), findsOneWidget);
  });
}
