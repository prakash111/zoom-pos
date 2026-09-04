import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/sdui/screens/dynamic_schema_page.dart';
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
    expect(find.text('Point of Sale'), findsOneWidget);
    expect(find.text('Sales History'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
