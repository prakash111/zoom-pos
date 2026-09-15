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
      'long-press dragging a menu item onto another section re-homes it on save',
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
    expect(
      find.textContaining('drag it onto another section'),
      findsOneWidget,
    );
    await tester.enterText(
      find.byKey(const ValueKey('section-title-cashier_sales')),
      'Cashier & Sales',
    );

    final gesture = await tester.startGesture(
        tester.getCenter(find.byKey(const ValueKey('item_cashier_sales_pos'))));
    await tester.pump(const Duration(milliseconds: 700)); // long-press fires
    await gesture.moveTo(tester.getCenter(find.text('Financial Management')));
    await tester.pump();
    await gesture.moveTo(tester.getCenter(find.text('Financial Management')));
    await tester.pump();
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
}
