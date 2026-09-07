import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/features/settings/screens/printer_selection_dialog.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

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
