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
}
