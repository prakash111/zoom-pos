<?php

namespace Tests\Unit\Services;

use App\Services\Navigation\NavigationSanitizerService;
use PHPUnit\Framework\TestCase;

class NavigationSanitizerServiceTest extends TestCase
{
    public function test_it_recursively_reindexes_sparse_navigation_collections(): void
    {
        $sections = [
            4 => [
                'key' => 'cashier_sales',
                'items' => [
                    3 => [
                        'key' => 'pos',
                        'children' => [
                            7 => ['key' => 'quick_sale'],
                            11 => ['key' => 'held_sales', 'children' => [9 => ['key' => 'resume_sale']]],
                        ],
                    ],
                    8 => ['key' => 'sales'],
                ],
                'sub_items' => [5 => ['key' => 'sales']],
            ],
        ];

        $sanitized = NavigationSanitizerService::sanitizeSections($sections);

        $this->assertSame([0], array_keys($sanitized));
        $this->assertSame([0, 1], array_keys($sanitized[0]['items']));
        $this->assertSame([0, 1], array_keys($sanitized[0]['items'][0]['children']));
        $this->assertSame([0], array_keys($sanitized[0]['items'][0]['children'][1]['children']));
        $this->assertSame([0], array_keys($sanitized[0]['sub_items']));

        $decoded = json_decode(json_encode($sanitized, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsList($decoded);
        $this->assertIsList($decoded[0]['items']);
        $this->assertIsList($decoded[0]['items'][0]['children']);
        $this->assertIsList($decoded[0]['items'][0]['children'][1]['children']);
    }

    public function test_it_normalizes_flat_and_tree_nav_payloads_before_serialization(): void
    {
        $payload = NavigationSanitizerService::normalizeNavPayload([
            'sections' => [6 => ['key' => 'cashier_sales', 'order' => 0]],
            'items' => [9 => ['key' => 'pos', 'children' => [4 => ['key' => 'quick_sale']]]],
            'tree' => [
                2 => [
                    'key' => 'cashier_sales',
                    'items' => [5 => ['key' => 'pos', 'children' => []]],
                ],
            ],
        ]);

        $this->assertIsList($payload['sections']);
        $this->assertIsList($payload['items']);
        $this->assertIsList($payload['items'][0]['children']);
        $this->assertIsList($payload['tree']);
        $this->assertIsList($payload['tree'][0]['items']);
    }

    public function test_it_preserves_sparse_flat_drawer_item_lists(): void
    {
        $items = NavigationSanitizerService::sanitizeSections([
            3 => ['key' => 'profile', 'children' => []],
            9 => ['key' => 'languages', 'children' => [8 => ['key' => 'staff']]],
        ]);

        $this->assertSame(['profile', 'languages'], array_column($items, 'key'));
        $this->assertIsList($items);
        $this->assertIsList($items[1]['children']);
        $this->assertSame('staff', $items[1]['children'][0]['key']);
    }

    public function test_it_resolves_restaurant_store_navigation_config_to_restaurant_terminal(): void
    {
        $store = [
            'operating_mode' => 'restaurant',
            'is_restaurant_module_enabled' => true,
        ];

        $config = NavigationSanitizerService::getStoreNavigationConfig($store);

        $this->assertTrue($config['is_restaurant']);
        $this->assertSame('restaurant_terminal', $config['primary_pos_route']);
        $this->assertSame('restaurant_terminal', $config['center_action_route']);
        $this->assertSame('restaurant_pos', $config['drawer_pos_route']);
        $this->assertSame('restaurant_pos', $config['default_pos_action']);
        $this->assertSame('RestaurantPosTerminalScreen', $config['default_pos_screen']);
        $this->assertSame('restaurant_terminal', $config['pos_layout']);
        $this->assertSame('restaurant_terminal', $config['default_terminal_view']);
        $this->assertSame('/restaurant-pos-terminal', $config['quick_actions']['add_sale']['target_route']);
        $this->assertSame('restaurant_terminal', $config['quick_actions']['add_sale']['screen_type']);
        $this->assertSame('restaurant_pos', $config['quick_actions']['add_sale']['route_key']);
    }

    public function test_it_resolves_standard_store_navigation_config_to_standard_pos(): void
    {
        $store = [
            'operating_mode' => 'retail',
            'is_restaurant_module_enabled' => false,
        ];

        $config = NavigationSanitizerService::getStoreNavigationConfig($store);

        $this->assertFalse($config['is_restaurant']);
        $this->assertSame('standard_pos', $config['primary_pos_route']);
        $this->assertSame('standard_pos', $config['center_action_route']);
        $this->assertSame('pos', $config['drawer_pos_route']);
        $this->assertSame('pos', $config['default_pos_action']);
        $this->assertSame('PosGridScreen', $config['default_pos_screen']);
        $this->assertSame('grid_catalog', $config['pos_layout']);
        $this->assertSame('/pos', $config['quick_actions']['add_sale']['target_route']);
        $this->assertSame('pos_catalog', $config['quick_actions']['add_sale']['screen_type']);
        $this->assertSame('pos', $config['quick_actions']['add_sale']['route_key']);
    }
}
