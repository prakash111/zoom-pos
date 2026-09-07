<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\Navigation\TenantNavRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AppBootstrapApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'trial',
            'display_name' => 'Free Trial',
            'price' => 0.00,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'duration_days' => 14,
            'features' => ['pos' => true, 'offline' => true],
            'limits' => ['products' => 500, 'users' => 5],
            'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Metro Supermarket',
            'trade_name' => 'Metro Mart',
            'slug' => 'metro-mart-bootstrap',
            'email' => 'pos@metromart.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'plan_name' => 'trial',
            'expires_at' => now()->addDays(14),
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'admin@metromart.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);
    }

    protected function token(): string
    {
        return $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@metromart.com',
            'password' => 'secret123',
        ])->assertOk()->json('token');
    }

    public function test_bootstrap_returns_translations_nav_and_config(): void
    {
        $response = $this->withToken($this->token())->getJson('/api/v1/pos/app/bootstrap?locale=en');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('nav.sections', [])
            ->assertJsonPath('nav.items', [])
            ->assertJsonPath('config.pos_mode', 'general')
            ->assertJsonPath('config.currency_symbol', '$')
            ->assertJsonStructure(['translations', 'messages']);
    }

    public function test_nav_config_round_trips_through_update_and_bootstrap(): void
    {
        $token = $this->token();

        $payload = [
            'sections' => [
                ['key' => 'financial_management', 'order' => 0],
                ['key' => 'cashier_sales', 'order' => 1],
            ],
            'items' => [
                ['key' => 'quotations', 'section' => 'cashier_sales', 'order' => 0, 'visible' => false],
                ['key' => 'consignments', 'section' => 'cashier_sales', 'order' => 1, 'visible' => false],
                // Moved out of its default section, into financial_management.
                ['key' => 'pos', 'section' => 'financial_management', 'order' => 0, 'visible' => true],
            ],
        ];
        $expectedItems = [
            ['key' => 'pos', 'section' => 'financial_management', 'parent' => null, 'parent_id' => null, 'level' => 0, 'order' => 0, 'visible' => true],
            ['key' => 'quotations', 'section' => 'cashier_sales', 'parent' => null, 'parent_id' => null, 'level' => 0, 'order' => 0, 'visible' => false],
            ['key' => 'consignments', 'section' => 'cashier_sales', 'parent' => null, 'parent_id' => null, 'level' => 0, 'order' => 1, 'visible' => false],
        ];

        $this->withToken($token)->postJson('/api/v1/pos/settings/nav-config', $payload)
            ->assertOk()
            ->assertJsonPath('nav.sections', $payload['sections'])
            ->assertJsonPath('nav.items', $expectedItems)
            ->assertJsonPath('nav.tree.0.items.0.key', 'pos');

        $this->withToken($token)->getJson('/api/v1/pos/app/bootstrap?locale=en')
            ->assertOk()
            ->assertJsonPath('nav.sections', $payload['sections'])
            ->assertJsonPath('nav.items', $expectedItems);

        $this->withToken($token)->getJson('/api/v1/pos/settings')
            ->assertOk()
            ->assertJsonPath('nav.sections', $payload['sections'])
            ->assertJsonPath('nav.items', $expectedItems);
    }

    public function test_nested_item_parent_round_trips_through_update_and_bootstrap(): void
    {
        $token = $this->token();

        $payload = [
            'sections' => [
                ['key' => 'administration', 'order' => 0],
            ],
            'items' => [
                ['key' => 'settings', 'section' => 'administration', 'parent' => null, 'order' => 0, 'visible' => true],
                // Un-nested from "settings" back to the section root.
                ['key' => 'settings_navigation', 'section' => 'administration', 'parent' => null, 'order' => 1, 'visible' => true],
                // Nested under "settings" (only one of the eight tabs kept).
                ['key' => 'settings_profile', 'section' => 'administration', 'parent' => 'settings', 'order' => 0, 'visible' => true],
            ],
        ];
        $expectedItems = [
            ['key' => 'settings', 'section' => 'administration', 'parent' => null, 'parent_id' => null, 'level' => 0, 'order' => 0, 'visible' => true],
            ['key' => 'settings_profile', 'section' => 'administration', 'parent' => 'settings', 'parent_id' => 'settings', 'level' => 1, 'order' => 0, 'visible' => true],
            ['key' => 'settings_navigation', 'section' => 'administration', 'parent' => null, 'parent_id' => null, 'level' => 0, 'order' => 1, 'visible' => true],
        ];

        $this->withToken($token)->postJson('/api/v1/pos/settings/nav-config', $payload)
            ->assertOk()
            ->assertJsonPath('nav.items', $expectedItems)
            ->assertJsonPath('nav.tree.0.items.0.children.0.key', 'settings_profile');

        $this->withToken($token)->getJson('/api/v1/pos/app/bootstrap?locale=en')
            ->assertOk()
            ->assertJsonPath('nav.items', $expectedItems);
    }

    public function test_legacy_hidden_tiles_and_section_order_shape_upgrades_on_read(): void
    {
        $this->company->update([
            'nav_config' => [
                'hidden_tiles' => ['quotations'],
                'section_order' => ['financial_management', 'cashier_sales'],
            ],
        ]);

        $nav = $this->company->fresh()->normalizedNavConfig();

        $this->assertSame(
            [
                ['key' => 'financial_management', 'order' => 0],
                ['key' => 'cashier_sales', 'order' => 1],
            ],
            $nav['sections']
        );
        $this->assertSame(
            [[
                'key' => 'quotations',
                'section' => null,
                'parent' => null,
                'parent_id' => null,
                'level' => 0,
                'order' => null,
                'visible' => false,
            ]],
            $nav['items']
        );
    }

    public function test_nav_config_update_is_permission_gated(): void
    {
        $cashier = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'cashier@metromart.com',
            'password' => Hash::make('secret123'),
            'role' => 'cashier',
        ]);

        $token = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'cashier@metromart.com',
            'password' => 'secret123',
        ])->assertOk()->json('token');

        $this->withToken($token)->postJson('/api/v1/pos/settings/nav-config', [
            'items' => [['key' => 'pos', 'visible' => false]],
        ])->assertForbidden();
    }

    public function test_bootstrap_returns_sdui_modular_schema(): void
    {
        $response = $this->withToken($this->token())->getJson('/api/v1/pos/app/bootstrap?locale=en');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tenant.id', (string) $this->company->id)
            ->assertJsonPath('tenant.business_name', 'Metro Mart')
            ->assertJsonPath('tenant.active_mode', 'retail')
            ->assertJsonPath('tenant.available_modes', ['retail'])
            ->assertJsonPath('modules.retail.id', 'retail')
            ->assertJsonPath('modules.retail.layout_type', 'standard_grid')
            ->assertJsonPath('modules.retail.features.has_tables', false)
            ->assertJsonPath('modules.retail.features.has_barcode_scanner', true)
            ->assertJsonPath('modules.restaurant.id', 'restaurant')
            ->assertJsonPath('modules.restaurant.layout_type', 'table_floor_plan')
            ->assertJsonPath('modules.restaurant.features.has_tables', true)
            ->assertJsonPath('modules.restaurant.features.has_kot', true)
            ->assertJsonPath('modules.pharmacy.id', 'pharmacy')
            ->assertJsonPath('modules.service_booking.id', 'service_booking')
            ->assertJsonPath('menu_structure.0.key', 'cashier_sales')
            ->assertJsonPath('menu_structure.0.items.0.key', 'pos')
            ->assertJsonStructure([
                'tenant' => ['id', 'business_name', 'active_mode', 'available_modes'],
                'modules' => [
                    'retail' => ['id', 'title', 'layout_type', 'features', 'cart_configuration'],
                    'restaurant' => ['id', 'title', 'layout_type', 'features', 'cart_configuration'],
                ],
                'menu_structure',
                'ui_schema' => ['payment_methods', 'status_labels', 'tax_configuration', 'action_pills'],
            ]);
    }

    public function test_empty_or_corrupted_database_navigation_falls_back_to_core_sections(): void
    {
        DB::table('sdui_modules')->insert([
            'name' => 'Retail Override',
            'slug' => 'retail',
            'layout_type' => 'standard_grid',
            'navigation' => json_encode([]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assertCoreMenu = function (array $menu): void {
            $sections = collect($menu)->keyBy('key');
            $this->assertTrue($sections->has('cashier_sales'));
            $this->assertTrue($sections->has('products_inventory'));
            $this->assertTrue($sections->has('administration'));
            $this->assertContains('pos', collect($sections['cashier_sales']['items'])->pluck('key')->all());
            $this->assertContains('settings', collect($sections['administration']['items'])->pluck('key')->all());
        };

        $assertCoreMenu(TenantNavRegistry::menuStructureForMode(' RETAIL '));

        DB::table('sdui_modules')->where('slug', 'retail')->update([
            'navigation' => json_encode([
                ['key' => 'broken', 'label' => 'Broken', 'items' => 'not-an-array'],
            ]),
        ]);

        $assertCoreMenu(TenantNavRegistry::menuStructureForMode('retail'));
    }

    public function test_corrupted_tenant_navigation_order_cannot_break_bootstrap_menu(): void
    {
        $token = $this->token();
        DB::table('companies')->where('id', $this->company->id)->update([
            'nav_config' => json_encode('corrupted-order-data'),
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/pos/app/bootstrap?locale=en');

        $response->assertOk()
            ->assertJsonPath('nav.sections', [])
            ->assertJsonPath('nav.items', []);

        $menu = collect($response->json('menu_structure'))->keyBy('key');
        $this->assertTrue($menu->has('cashier_sales'));
        $this->assertTrue($menu->has('products_inventory'));
        $this->assertTrue($menu->has('administration'));
    }

    public function test_regular_tenant_cannot_switch_mode_dynamically(): void
    {
        $token = $this->token();

        // Attempting to switch mode via POST /api/v1/pos/app/mode is forbidden for regular tenants
        $res = $this->withToken($token)->postJson('/api/v1/pos/app/mode', [
            'mode' => 'restaurant',
        ]);

        $res->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_get_effective_nav_for_tenant_guarantees_non_empty_menu_and_key_id_parity(): void
    {
        foreach (['retail', 'restaurant', 'pharmacy', 'service_booking'] as $mode) {
            $sections = TenantNavRegistry::getEffectiveNavForTenant($mode);
            $this->assertNotEmpty($sections, "Menu sections for mode {$mode} must not be empty.");

            foreach ($sections as $section) {
                $this->assertArrayHasKey('key', $section);
                $this->assertArrayHasKey('id', $section);
                $this->assertEquals($section['key'], $section['id']);
                $this->assertArrayHasKey('label', $section);
                $this->assertArrayHasKey('title', $section);
                $this->assertEquals($section['label'], $section['title']);
                $this->assertNotEmpty($section['items']);

                foreach ($section['items'] as $item) {
                    $this->assertArrayHasKey('key', $item);
                    $this->assertArrayHasKey('id', $item);
                    $this->assertEquals($item['key'], $item['id']);
                    $this->assertArrayHasKey('label', $item);
                    $this->assertArrayHasKey('title', $item);
                    $this->assertEquals($item['label'], $item['title']);
                }
            }
        }
    }

    public function test_bootstrap_seeds_sample_data_on_first_launch_without_error(): void
    {
        $unseededCompany = \App\Models\Company::create([
            'id' => 'test_unseeded_' . uniqid(),
            'name' => 'Unseeded Test Store',
            'pos_mode' => 'retail',
            'is_seeding_complete' => false,
            'status' => 'active',
        ]);

        $user = \App\Models\User::factory()->create([
            'company_id' => $unseededCompany->id,
            'email' => 'unseeded_' . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $token = $this->postJson('/api/v1/pos/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertOk()->json('token');

        $response = $this->withToken($token)
            ->withHeaders(['X-Company-Id' => $unseededCompany->id])
            ->getJson('/api/v1/pos/app/bootstrap?locale=en');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tenant.is_seeding_complete', true);

        $this->assertNotEmpty($response->json('menu_structure'));
        $this->assertNotEmpty($response->json('navigation'));
        $this->assertDatabaseHas('products', [
            'company_id' => $unseededCompany->id,
            'is_demo' => true,
        ]);
    }

    public function test_bootstrap_returns_all_licensed_module_sections_in_drawer_navigation(): void
    {
        $this->company->update([
            'operating_mode' => 'retail',
            'licensed_modules' => ['retail', 'restaurant', 'pharmacy', 'service_booking'],
        ]);

        $response = $this->withToken($this->token())
            ->getJson('/api/v1/pos/app/bootstrap?locale=en');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $menu = collect($response->json('menu_structure'))->keyBy('key');

        // Verify all licensed module sections exist in menu_structure
        $this->assertTrue($menu->has('cashier_sales'));
        $this->assertTrue($menu->has('financial_management'));
        $this->assertTrue($menu->has('products_inventory'));
        $this->assertTrue($menu->has('restaurant_operations'));
        $this->assertTrue($menu->has('pharmacy_management'));
        $this->assertTrue($menu->has('salon_bookings'));
        $this->assertTrue($menu->has('administration'));

        // Verify strict modular section sequence on first load:
        $keys = collect($response->json('menu_structure'))->pluck('key')->all();
        $this->assertSame([
            'cashier_sales',
            'products_inventory',
            'financial_management',
            'restaurant_operations',
            'pharmacy_management',
            'salon_bookings',
            'administration',
        ], $keys);

        // Verify Administration anchored at the bottom with Change Password
        $adminItems = collect($menu['administration']['items'])->pluck('key')->all();
        $this->assertContains('change_password', $adminItems);
        $this->assertSame('change_password', end($adminItems));

        // Verify items in injected module sections
        $restaurantItems = collect($menu['restaurant_operations']['items'])->pluck('key')->all();
        $this->assertContains('floor_plan', $restaurantItems);
        $this->assertContains('kitchen_display', $restaurantItems);
        $this->assertContains('dining_history', $restaurantItems);
        $this->assertContains('restaurant_pos', $restaurantItems);

        $pharmacyItems = collect($menu['pharmacy_management']['items'])->pluck('key')->all();
        $this->assertContains('pharmacy_pos', $pharmacyItems);
        $this->assertTrue(in_array('new_prescription_intake', $pharmacyItems, true) || in_array('new_rx_intake', $pharmacyItems, true));
        $this->assertTrue(in_array('prescriptions_queue', $pharmacyItems, true) || in_array('pharmacy_prescriptions', $pharmacyItems, true));
        $this->assertTrue(in_array('batch_inventory', $pharmacyItems, true) || in_array('pharmacy_batches', $pharmacyItems, true));

        $serviceItems = collect($menu['salon_bookings']['items'])->pluck('key')->all();
        $this->assertContains('salon_pos', $serviceItems);
        $this->assertTrue(in_array('book_appointment', $serviceItems, true) || in_array('book_service_appointment', $serviceItems, true));
        $this->assertTrue(in_array('booking_calendar', $serviceItems, true) || in_array('service_booking_calendar', $serviceItems, true) || in_array('service_calendar', $serviceItems, true));
        $this->assertContains('service_stylists', $serviceItems);
        $this->assertTrue(in_array('service_catalog_rates', $serviceItems, true) || in_array('service_catalog', $serviceItems, true));
        $this->assertTrue(in_array('add_new_service', $serviceItems, true) || in_array('service_create', $serviceItems, true));
        $this->assertNotContains('service_orders', $serviceItems);
    }
}

