import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/features/restaurant/screens/restaurant_tables_screen.dart';

void main() {
  testWidgets('renders a compact more_vert icon button', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(body: TableActionsMenuButton(onPressed: () {})),
    ));

    expect(find.byIcon(Icons.more_vert), findsOneWidget);
    final icon = tester.widget<Icon>(find.byIcon(Icons.more_vert));
    expect(icon.color, const Color(0xFF94A3B8));

    final button = tester.widget<IconButton>(find.byType(IconButton));
    expect(button.iconSize, 16);
  });

  testWidgets('a standard tap fires onPressed — no long-press needed',
      (tester) async {
    var tapped = false;
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
          body: TableActionsMenuButton(onPressed: () => tapped = true)),
    ));

    await tester.tap(find.byIcon(Icons.more_vert));
    await tester.pump();

    expect(tapped, isTrue);
  });

  testWidgets(
      'sizes small enough to sit in a table card corner without crowding the title',
      (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(body: TableActionsMenuButton(onPressed: () {})),
    ));

    final size = tester.getSize(find.byType(TableActionsMenuButton));
    expect(size.width, lessThanOrEqualTo(24));
    expect(size.height, lessThanOrEqualTo(24));
  });
}
