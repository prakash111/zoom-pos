import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/features/settings/screens/global_printer_setup_screen.dart';
import 'package:zoom_pos_mobile/features/settings/screens/printer_selection_dialog.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('hardware setup uses crisp dark segmented and empty states',
      (tester) async {
    SharedPreferences.setMockInitialValues({});

    await tester.pumpWidget(
      MaterialApp(
        themeMode: ThemeMode.dark,
        darkTheme: ThemeData.dark(),
        home: const GlobalPrinterSetupScreen(),
      ),
    );
    await tester.pumpAndSettle();

    final segmented = tester.widget<SegmentedButton<PrinterConnectionType>>(
        find.byType(SegmentedButton<PrinterConnectionType>));
    expect(
      segmented.style?.backgroundColor?.resolve({WidgetState.selected}),
      const Color(0xFF10B981),
    );
    expect(
      segmented.style?.foregroundColor?.resolve({WidgetState.selected}),
      const Color(0xFF0B1120),
    );
    expect(
      segmented.style?.backgroundColor?.resolve(<WidgetState>{}),
      const Color(0xFF1E293B),
    );
    expect(
      segmented.style?.foregroundColor?.resolve(<WidgetState>{}),
      const Color(0xFF94A3B8),
    );

    final emptyText = find.textContaining('No paired Bluetooth printers');
    expect(emptyText, findsOneWidget);
    expect(
        tester.widget<Text>(emptyText).style?.color, const Color(0xFFE2E8F0));
    final emptyCard = tester.widget<Card>(
      find.ancestor(of: emptyText, matching: find.byType(Card)).first,
    );
    expect(emptyCard.color, const Color(0xFF182230));
  });

  testWidgets('opens on an explicit picker, not a forced scan', (tester) async {
    SharedPreferences.setMockInitialValues({});

    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (context) => Scaffold(
          body: Center(
            child: ElevatedButton(
              onPressed: () => showDialog<dynamic>(
                context: context,
                builder: (_) => const PrinterSelectionDialog(),
              ),
              child: const Text('open'),
            ),
          ),
        ),
      ),
    ));

    await tester.tap(find.text('open'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    // The dialog names the interface and gives the user a way out — it does
    // not immediately kick off a discovery scan with no choice.
    expect(find.text('Select Bluetooth Printer'), findsOneWidget);
    expect(find.text('Cancel'), findsOneWidget);
    expect(find.widgetWithIcon(IconButton, Icons.refresh), findsOneWidget);
  });

  testWidgets('ensureSelected returns the saved address without prompting',
      (tester) async {
    SharedPreferences.setMockInitialValues({
      'zoom_pos.thermal_printer_mac': 'AA:BB:CC:DD:EE:FF',
    });

    String? result = 'sentinel';
    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (context) => Scaffold(
          body: Center(
            child: ElevatedButton(
              onPressed: () async {
                result = await PrinterSelectionDialog.ensureSelected(context);
              },
              child: const Text('go'),
            ),
          ),
        ),
      ),
    ));

    await tester.tap(find.text('go'));
    await tester.pumpAndSettle();

    expect(result, 'AA:BB:CC:DD:EE:FF');
    // No dialog was shown.
    expect(find.text('Select Bluetooth Printer'), findsNothing);
  });
}
