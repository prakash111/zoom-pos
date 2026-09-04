import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/models/settings_models.dart';
import 'package:zoom_pos_mobile/core/sdui/components/navigation_tree_builder.dart';

void main() {
  group('navigation indentation', () {
    test('snaps at the mobile 20 px and 50 px thresholds', () {
      expect(navigationIndentForOffset(-30), 0);
      expect(navigationIndentForOffset(19.99), 0);
      expect(navigationIndentForOffset(20), 1);
      expect(navigationIndentForOffset(49.99), 1);
      expect(navigationIndentForOffset(50), 2);
      expect(navigationIndentForOffset(120), 2);
    });
  });

  group('NavConfig hierarchy contract', () {
    test('reads parent_id and recursively serializes three levels', () {
      final config = NavConfig(
        sections: const [NavSectionOrder(key: 'main', order: 0)],
        items: [
          const NavItemConfig(
            key: 'root',
            section: 'main',
            level: 0,
            order: 0,
            visible: true,
          ),
          NavItemConfig.fromJson({
            'key': 'child',
            'section': 'main',
            'parent_id': 'root',
            'level': 1,
            'order': 0,
            'visible': true,
          }),
          NavItemConfig.fromJson({
            'key': 'grandchild',
            'section': 'main',
            'parent_id': 'child',
            'level': 2,
            'order': 0,
            'visible': false,
          }),
          const NavItemConfig(
            key: 'second-root',
            section: 'main',
            level: 0,
            order: 1,
            visible: true,
          ),
        ],
      );

      final json = config.toJson();
      final flat = (json['items'] as List).cast<Map<String, dynamic>>();
      final tree = (json['tree'] as List).single as Map<String, dynamic>;
      final roots = (tree['items'] as List).cast<Map<String, dynamic>>();
      final child =
          ((roots.first['children'] as List).single) as Map<String, dynamic>;
      final grandchild =
          ((child['children'] as List).single) as Map<String, dynamic>;

      expect(flat[1]['parent_id'], 'root');
      expect(flat[1]['level'], 1);
      expect(roots.map((item) => item['key']), ['root', 'second-root']);
      expect(child['key'], 'child');
      expect(child['parent_id'], 'root');
      expect(grandchild['key'], 'grandchild');
      expect(grandchild['level'], 2);
      expect(grandchild['visible'], isFalse);
    });

    test('can hydrate a flat index from a tree-only response', () {
      final config = NavConfig.fromJson({
        'sections': [
          {'key': 'main', 'order': 0},
        ],
        'tree': [
          {
            'key': 'main',
            'order': 0,
            'items': [
              {
                'key': 'root',
                'visible': true,
                'children': [
                  {
                    'key': 'child',
                    'visible': true,
                    'children': <dynamic>[],
                  },
                ],
              },
            ],
          },
        ],
      });

      expect(config.items.map((item) => item.key), ['root', 'child']);
      expect(config.items.last.parentId, 'root');
      expect(config.items.last.level, 1);
    });
  });
}
