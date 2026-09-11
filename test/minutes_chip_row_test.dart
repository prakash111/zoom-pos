import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/features/restaurant/widgets/minutes_chip_row.dart';

const _prepPresets = [
  (5, '5m'),
  (10, '10m'),
  (15, '15m'),
  (20, '20m'),
  (30, '30m'),
];

void main() {
  Widget host({required int value, required ValueChanged<int> onChanged}) {
    return MaterialApp(
      home: Scaffold(
        body: SizedBox(
          // Narrow enough that 5 chips + the custom field would wrap under
          // the old Wrap-based layout — proves the row stays on one line.
          width: 320,
          child: MinutesChipRow(
            label: 'Prep time',
            presets: _prepPresets,
            value: value,
            onChanged: onChanged,
          ),
        ),
      ),
    );
  }

  testWidgets(
      'all 5 presets + the custom field render inside one horizontally-scrolling row, never wrapping to a second line',
      (tester) async {
    await tester.pumpWidget(host(value: 15, onChanged: (_) {}));

    // Everything is a SingleChildScrollView/Row descendant (one line), and
    // there is no Wrap anywhere that could push the last preset onto a
    // second row.
    expect(find.byType(SingleChildScrollView), findsOneWidget);
    final scrollView = tester
        .widget<SingleChildScrollView>(find.byType(SingleChildScrollView));
    expect(scrollView.scrollDirection, Axis.horizontal);
    expect(find.byType(Wrap), findsNothing);

    for (final preset in _prepPresets) {
      expect(
        find.descendant(
            of: find.byType(SingleChildScrollView),
            matching: find.text(preset.$2)),
        findsOneWidget,
        reason: '${preset.$2} must be reachable on the single scrollable row',
      );
    }
    expect(
      find.descendant(
          of: find.byType(SingleChildScrollView),
          matching: find.byType(TextField)),
      findsOneWidget,
    );

    // Every chip + the custom field sit at the same row cross-axis centre —
    // proof they're laid out as one Row, not stacked across lines.
    final centers = <double>[
      for (final preset in _prepPresets)
        tester.getCenter(find.text(preset.$2)).dy,
      tester.getCenter(find.byType(TextField)).dy,
    ];
    for (final y in centers) {
      expect(y, closeTo(centers.first, 6));
    }
  });

  testWidgets('tapping a preset chip (scrolled into view) reports its value',
      (tester) async {
    int? reported;
    await tester.pumpWidget(host(value: 15, onChanged: (v) => reported = v));

    // The 5th preset is off the initial 320px viewport under the narrow
    // layout — exactly the case that used to force a wrap onto a 2nd row;
    // here it's simply scrolled to instead.
    await tester.ensureVisible(find.text('30m'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('30m'));
    await tester.pump();

    expect(reported, 30);
  });

  testWidgets('typing a custom value outside the presets reports it live',
      (tester) async {
    final values = <int>[];
    await tester.pumpWidget(host(value: 15, onChanged: values.add));

    await tester.enterText(find.byType(TextField), '45');
    await tester.pump();

    expect(values, contains(45));
  });

  testWidgets(
      'a non-preset current value shows in the custom field, not as a selected chip',
      (tester) async {
    await tester.pumpWidget(host(value: 12, onChanged: (_) {}));

    final field = tester.widget<TextField>(find.byType(TextField));
    expect(field.controller?.text, '12');

    for (final preset in _prepPresets) {
      final chip = tester.widget<ChoiceChip>(find.ancestor(
        of: find.text(preset.$2),
        matching: find.byType(ChoiceChip),
      ));
      expect(chip.selected, isFalse,
          reason: '${preset.$2} must not be selected');
    }
  });

  testWidgets('picking a preset after typing custom clears the custom field',
      (tester) async {
    int current = 12;
    await tester.pumpWidget(StatefulBuilder(builder: (context, setState) {
      return host(
        value: current,
        onChanged: (v) => setState(() => current = v),
      );
    }));

    expect(tester.widget<TextField>(find.byType(TextField)).controller?.text,
        '12');

    await tester.tap(find.text('10m'));
    await tester.pump();

    expect(current, 10);
    expect(
        tester.widget<TextField>(find.byType(TextField)).controller?.text, '');
  });
}
