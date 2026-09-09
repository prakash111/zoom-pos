import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/services/sync/sync_engine.dart';
import 'package:zoom_pos_mobile/core/widgets/desktop_status_bar.dart';

void main() {
  Widget host(Widget child) => MaterialApp(home: Scaffold(body: Column(children: [child])));

  testWidgets('offline state shows the Offline label and an enabled Sync now',
      (tester) async {
    var tapped = 0;
    await tester.pumpWidget(host(DesktopStatusBar(
      state: SyncState.offline,
      pendingCount: 0,
      onSyncNow: () => tapped++,
    )));

    expect(find.text('Offline'), findsOneWidget);
    expect(find.byIcon(Icons.cloud_off), findsOneWidget);

    await tester.tap(find.text('Sync now'));
    expect(tapped, 1);
  });

  testWidgets('pending count is surfaced', (tester) async {
    await tester.pumpWidget(host(const DesktopStatusBar(
      state: SyncState.online,
      pendingCount: 3,
    )));

    expect(find.text('3 pending'), findsOneWidget);
  });

  testWidgets('syncing state shows a spinner and disables the button',
      (tester) async {
    await tester.pumpWidget(host(const DesktopStatusBar(
      state: SyncState.syncing,
      pendingCount: 1,
      onSyncNow: null,
    )));

    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    expect(find.text('Syncing…'), findsWidgets);
    final button = tester.widget<TextButton>(find.byType(TextButton));
    expect(button.onPressed, isNull);
  });

  testWidgets('synced state shows a relative timestamp', (tester) async {
    await tester.pumpWidget(host(DesktopStatusBar(
      state: SyncState.synced,
      pendingCount: 0,
      lastSyncedAt: DateTime.now().subtract(const Duration(minutes: 4)),
    )));

    expect(find.text('Synced 4m ago'), findsOneWidget);
  });

  testWidgets('failed state shows the error text', (tester) async {
    await tester.pumpWidget(host(const DesktopStatusBar(
      state: SyncState.failed,
      pendingCount: 2,
      lastError: 'Offline changes: connection refused',
    )));

    expect(find.text('Sync failed'), findsOneWidget);
    expect(find.textContaining('connection refused'), findsOneWidget);
  });

  testWidgets('conflict count is shown and singularised', (tester) async {
    await tester.pumpWidget(host(const DesktopStatusBar(
      state: SyncState.synced,
      pendingCount: 0,
      conflictCount: 1,
    )));
    expect(find.text('1 conflict'), findsOneWidget);

    await tester.pumpWidget(host(const DesktopStatusBar(
      state: SyncState.synced,
      pendingCount: 0,
      conflictCount: 3,
    )));
    expect(find.text('3 conflicts'), findsOneWidget);
  });

  testWidgets('tapping the bar opens the panel', (tester) async {
    var opened = 0;
    await tester.pumpWidget(host(DesktopStatusBar(
      state: SyncState.online,
      pendingCount: 2,
      onOpenPanel: () => opened++,
    )));

    await tester.tap(find.text('2 pending'));
    expect(opened, 1);
  });
}
