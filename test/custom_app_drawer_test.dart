import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/navigation/navigation_provider.dart';
import 'package:zoom_pos_mobile/widgets/navigation/custom_app_drawer.dart';

void main() {
  testWidgets('CustomAppDrawer removes language items from navigation drawer',
      (tester) async {
    final sections = [
      NavSection(
        key: 'settings',
        title: 'Settings',
        items: [
          NavItem(
            key: 'settings_profile',
            title: 'Store Profile',
            route: '/settings',
            icon: Icons.store,
          ),
          NavItem(
            key: 'languages',
            title: 'Languages & Translations',
            route: '/languages',
            icon: Icons.language,
          ),
          NavItem(
            key: 'devices',
            title: 'Devices',
            route: '/devices',
            icon: Icons.devices,
          ),
        ],
      ),
    ];

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          drawer: CustomAppDrawer(
            sections: sections,
          ),
          body: const SizedBox(),
        ),
      ),
    );

    await tester.pumpAndSettle();

    // Open drawer
    final scaffoldState = tester.state<ScaffoldState>(find.byType(Scaffold));
    scaffoldState.openDrawer();
    await tester.pumpAndSettle();

    // Verify Store Profile and Devices are rendered
    expect(find.text('Store Profile'), findsOneWidget);
    expect(find.text('Devices'), findsOneWidget);

    // Verify Languages & Translations is stripped and NOT rendered in drawer
    expect(find.text('Languages & Translations'), findsNothing);
    expect(find.byKey(const ValueKey('drawer-item-languages')), findsNothing);
  });
}
