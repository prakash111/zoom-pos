import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/config/locale_provider.dart';
import 'package:zoom_pos_mobile/core/models/settings_models.dart';
import 'package:zoom_pos_mobile/core/storage/app_preferences.dart';
import 'package:zoom_pos_mobile/features/settings/screens/nav_menu_settings_tab.dart';
import 'package:zoom_pos_mobile/features/settings/settings_repository.dart';
import 'package:zoom_pos_mobile/l10n/app_localizations.dart';

class _FakeApiClient extends Fake implements ApiClient {}

/// Captures the [NavConfig] handed to `updateNavConfig` without touching the
/// network or the shared [BootstrapCache].
class _CapturingRepository extends SettingsRepository {
  _CapturingRepository() : super(_FakeApiClient());

  NavConfig? captured;

  @override
  Future<NavConfig> updateNavConfig(NavConfig nav) async {
    captured = nav;
    return nav;
  }
}

class _NoopLocaleProvider extends LocaleProvider {
  _NoopLocaleProvider()
      : super(preferences: AppPreferences(), apiClient: _FakeApiClient());

  @override
  Future<void> refreshFromServer() async {}
}

void main() {
  testWidgets(
      'one reorder list lets an item cross a section header and persists it',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(900, 1500));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final repository = _CapturingRepository();

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider<LocaleProvider>.value(
              value: _NoopLocaleProvider()),
        ],
        child: MaterialApp(
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          supportedLocales: const [Locale('en')],
          home: NavMenuSettingsTab(repository: repository),
        ),
      ),
    );
    await tester.pumpAndSettle();

    // Falls back to the built-in catalog: "Point of Sale" starts under
    // "Cashier & Sales"; we drag it into "Financial Management".
    expect(find.text('Point of Sale'), findsWidgets);
    expect(find.text('Financial Management'), findsOneWidget);
    expect(find.byType(ReorderableListView), findsOneWidget);
    expect(
      find.textContaining('drag any item across a section header'),
      findsOneWidget,
    );
    await tester.enterText(
      find.byKey(const ValueKey('section-title-cashier_sales')),
      'Cashier & Sales',
    );

    final gesture = await tester.startGesture(
      tester.getCenter(find.byKey(const ValueKey('nav-drag-pos'))),
    );
    await tester.pump();
    await gesture.moveBy(const Offset(0, 20));
    await tester.pump(const Duration(milliseconds: 100));
    final targetHeader =
        find.byKey(const ValueKey('nav-section-financial_mgmt'));
    await gesture
        .moveTo(tester.getBottomLeft(targetHeader) + const Offset(220, 40));
    await tester.pump(const Duration(milliseconds: 500));
    await gesture.up();
    await tester.pumpAndSettle();

    await tester.tap(find.byType(ElevatedButton));
    await tester.pumpAndSettle();

    final captured = repository.captured;
    expect(captured, isNotNull);
    final pos = captured!.items.firstWhere((item) => item.key == 'pos');
    expect(pos.section, 'financial_mgmt');
    expect(
      captured.sections
          .firstWhere((section) => section.key == 'cashier_sales')
          .customTitle,
      'Cashier & Sales',
    );
    // The item left its old section entirely — no stale duplicate.
    expect(captured.items.where((item) => item.key == 'pos').length, 1);
  });

  testWidgets(
      'move action transfers Store Settings subtree as an independent root',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(900, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final repository = _CapturingRepository();
    const initial = NavConfig(
      sections: [
        NavSectionOrder(key: 'billing', order: 0),
        NavSectionOrder(key: 'administration', order: 1),
      ],
      items: [
        NavItemConfig(
          key: 'subscription',
          section: 'billing',
          order: 0,
          visible: true,
        ),
        NavItemConfig(
          key: 'settings',
          section: 'billing',
          parent: 'subscription',
          level: 1,
          order: 0,
          visible: true,
        ),
        NavItemConfig(
          key: 'settings_profile',
          section: 'billing',
          parent: 'settings',
          level: 2,
          order: 0,
          visible: true,
        ),
      ],
    );

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider<LocaleProvider>.value(
              value: _NoopLocaleProvider()),
        ],
        child: MaterialApp(
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          supportedLocales: const [Locale('en')],
          home: NavMenuSettingsTab(
            repository: repository,
            initial: initial,
            schema: const {
              'tree_data': [
                {
                  'key': 'billing',
                  'title': 'Subscription & Billing',
                  'items': [
                    {
                      'key': 'subscription',
                      'title': 'Subscription & Billing',
                      'children': [
                        {
                          'key': 'settings',
                          'title': 'Store Settings',
                          'children': [
                            {
                              'key': 'settings_profile',
                              'title': 'Store Profile',
                              'children': [],
                            },
                          ],
                        },
                      ],
                    },
                  ],
                },
                {
                  'key': 'administration',
                  'title': 'Administration & Settings',
                  'items': [],
                },
              ],
            },
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    // Explicit outdent clears the stale Subscription parent immediately.
    await tester.tap(find.byKey(const ValueKey('nav-level-settings')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('nav-level-option-settings-0')));
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const ValueKey('nav-move-settings')));
    await tester.pumpAndSettle();
    expect(find.text('Move "Store Settings" to section'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('move-target-administration')));
    await tester.pumpAndSettle();

    await tester.tap(find.byType(ElevatedButton));
    await tester.pumpAndSettle();

    final captured = repository.captured!;
    final settings =
        captured.items.firstWhere((item) => item.key == 'settings');
    final profile =
        captured.items.firstWhere((item) => item.key == 'settings_profile');
    expect(settings.section, 'administration');
    expect(settings.parentId, isNull);
    expect(settings.level, 0);
    expect(profile.section, 'administration');
    expect(profile.parentId, 'settings');
    expect(profile.level, 1);
  });
}
