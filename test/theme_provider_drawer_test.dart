import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/core/config/bootstrap_cache.dart';
import 'package:zoom_pos_mobile/core/config/theme_provider.dart';

BootstrapTheme _serverTheme({String? drawerBg}) => BootstrapTheme.fromJson({
      if (drawerBg != null) 'drawer_bg': drawerBg,
    });

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  group('Dashboard/bootstrap refresh must not clobber a local drawer override',
      () {
    test(
        'syncFromBootstrap applies the tenant colour when there is no override',
        () async {
      final tp = ThemeProvider();
      await tp.load();

      await tp.syncFromBootstrap(_serverTheme(drawerBg: '#0F172A'));

      expect(tp.drawerBg, const Color(0xFF0F172A));
      expect(tp.isDrawerBgUserOverride, isFalse);
    });

    test('an explicit per-device pick survives a later bootstrap sync',
        () async {
      final tp = ThemeProvider();
      await tp.load();

      await tp.setDrawerBgOrNull(const Color(0xFF7C3AED)); // user picks purple
      expect(tp.isDrawerBgUserOverride, isTrue);

      // A subsequent dashboard/pull-to-refresh re-syncs the tenant's own
      // (different) server colour — must not touch the local override.
      await tp.syncFromBootstrap(_serverTheme(drawerBg: '#FFF7ED'));

      expect(tp.drawerBg, const Color(0xFF7C3AED));
      expect(tp.isDrawerBgUserOverride, isTrue);
    });

    test('the override survives reloading the provider (persisted separately)',
        () async {
      final first = ThemeProvider();
      await first.load();
      await first.setDrawerBgOrNull(const Color(0xFF7C3AED));
      // Simulate a dashboard refresh writing the tenant's synced colour.
      await first.syncFromBootstrap(_serverTheme(drawerBg: '#FFF7ED'));

      final reloaded = ThemeProvider();
      await reloaded.load();

      expect(reloaded.drawerBg, const Color(0xFF7C3AED));
      expect(reloaded.isDrawerBgUserOverride, isTrue);
    });

    test(
        'clearing the override falls back to the synced tenant colour, not null',
        () async {
      final tp = ThemeProvider();
      await tp.load();
      await tp.syncFromBootstrap(_serverTheme(drawerBg: '#0F172A'));
      await tp.setDrawerBgOrNull(const Color(0xFF7C3AED));
      expect(tp.isDrawerBgUserOverride, isTrue);

      await tp.setDrawerBgOrNull(null); // "Reset to default"

      expect(tp.isDrawerBgUserOverride, isFalse);
      expect(tp.drawerBg, const Color(0xFF0F172A)); // the synced value, kept
    });

    test(
        'a null/blank server drawer_bg never overwrites an already-synced value',
        () async {
      final tp = ThemeProvider();
      await tp.load();
      await tp.syncFromBootstrap(_serverTheme(drawerBg: '#0F172A'));

      await tp.syncFromBootstrap(_serverTheme(drawerBg: null));

      expect(tp.drawerBg, const Color(0xFF0F172A));
    });
  });
}
