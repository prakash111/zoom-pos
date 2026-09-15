import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/features/navigation/presentation/widgets/app_drawer.dart';

void main() {
  group('buildDrawerHierarchy', () {
    test('breaks out of active parent when item has level 0 or parentId null', () {
      final flatList = [
        const RawMenuItem(
          id: 'settings_profile',
          title: 'Store Profile',
          level: 0,
        ),
        const RawMenuItem(
          id: 'languages',
          title: 'Languages & Translations',
          level: 0,
        ),
        const RawMenuItem(
          id: 'staff',
          title: 'Users & Permissions',
          parentId: 'languages',
          level: 1,
        ),
        const RawMenuItem(
          id: 'roles',
          title: 'Roles & Access Levels',
          parentId: 'languages',
          level: 1,
        ),
        const RawMenuItem(
          id: 'devices',
          title: 'Terminals & Devices',
          level: 0,
        ),
        const RawMenuItem(
          id: 'hardware_printer',
          title: 'Printer & Hardware Setup',
          level: 0,
        ),
        const RawMenuItem(
          id: 'change_password',
          title: 'Change Password',
          level: 0,
        ),
      ];

      final roots = buildDrawerHierarchy(flatList);

      // Verify roots count
      expect(roots.length, 5);
      expect(roots.map((r) => r.id), [
        'settings_profile',
        'languages',
        'devices',
        'hardware_printer',
        'change_password',
      ]);

      // Store Profile has no children
      expect(roots[0].children, isEmpty);

      // Languages has staff and roles
      expect(roots[1].children.length, 2);
      expect(roots[1].children.map((c) => c.id), ['staff', 'roles']);

      // Terminals & Devices, Printer, Password have no children
      expect(roots[2].children, isEmpty);
      expect(roots[3].children, isEmpty);
      expect(roots[4].children, isEmpty);
    });
  });
}
