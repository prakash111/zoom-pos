<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Store;
use App\Models\User;
use App\Services\Navigation\NavigationSanitizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerDrivenPosRouteAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private Company $restaurantCompany;
    private User $restaurantAdmin;
    private Store $restaurantStore;
    private string $restaurantToken;

    private Company $retailCompany;
    private User $retailAdmin;
    private Store $retailStore;
    private string $retailToken;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create(['name' => 'professional', 'display_name' => 'Professional', 'store_limit' => 5]);

        // Setup Restaurant Company & Store
        $this->restaurantCompany = Company::create([
            'name' => 'Urban Bistro & Cafe',
            'trade_name' => 'The Copper Kettle Café',
            'slug' => 'urban-bistro-cafe',
            'email' => 'bistro@demo.com',
            'country' => 'IN',
            'plan_name' => 'professional',
            'pos_mode' => 'restaurant',
            'is_seeding_complete' => true,
            'expires_at' => now()->addMonth(),
        ]);

        $this->restaurantAdmin = User::factory()->create([
            'company_id' => $this->restaurantCompany->id,
            'role' => 'admin',
            'email' => 'bistro-admin@demo.com',
            'password' => 'secret123',
        ]);

        $this->restaurantStore = Store::create([
            'company_id' => $this->restaurantCompany->id,
            'tenant_id' => $this->restaurantCompany->id,
            'name' => 'The Copper Kettle Café',
            'code' => 'CKC-01',
            'branch_code' => 'CKC-01',
            'is_primary' => true,
            'is_active' => true,
            'settings' => [
                'operating_mode' => 'restaurant',
                'pos_layout' => 'restaurant_terminal',
                'default_terminal_view' => 'restaurant_terminal',
            ],
        ]);
        $this->restaurantAdmin->update(['current_store_id' => $this->restaurantStore->id]);

        $resRestaurant = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'bistro-admin@demo.com',
            'password' => 'secret123',
        ])->assertOk();
        $this->restaurantToken = $resRestaurant->json('token');

        // Setup Retail Company & Store
        $this->retailCompany = Company::create([
            'name' => 'Metro Retail Mart',
            'trade_name' => 'Metro Retail Mart',
            'slug' => 'metro-retail-mart',
            'email' => 'metro@demo.com',
            'country' => 'IN',
            'plan_name' => 'professional',
            'pos_mode' => 'retail',
            'is_seeding_complete' => true,
            'expires_at' => now()->addMonth(),
        ]);

        $this->retailAdmin = User::factory()->create([
            'company_id' => $this->retailCompany->id,
            'role' => 'admin',
            'email' => 'retail-admin@demo.com',
            'password' => 'secret123',
        ]);

        $this->retailStore = Store::create([
            'company_id' => $this->retailCompany->id,
            'tenant_id' => $this->retailCompany->id,
            'name' => 'Metro Retail Mart Branch',
            'code' => 'MRM-01',
            'branch_code' => 'MRM-01',
            'is_primary' => true,
            'is_active' => true,
            'settings' => [
                'operating_mode' => 'retail',
                'pos_layout' => 'grid_catalog',
                'default_terminal_view' => 'grid_catalog',
            ],
        ]);
        $this->retailAdmin->update(['current_store_id' => $this->retailStore->id]);

        $resRetail = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'retail-admin@demo.com',
            'password' => 'secret123',
        ])->assertOk();
        $this->retailToken = $resRetail->json('token');
    }

    public function test_restaurant_store_navigation_config_aligns_to_restaurant_terminal(): void
    {
        $config = NavigationSanitizerService::getStoreNavigationConfig($this->restaurantStore);

        $this->assertTrue($config['is_restaurant']);
        $this->assertSame('restaurant_terminal', $config['primary_pos_route']);
        $this->assertSame('restaurant_terminal', $config['center_action_route']);
        $this->assertSame('restaurant_pos', $config['drawer_pos_route']);
        $this->assertSame('restaurant_pos', $config['default_pos_action']);
        $this->assertSame('RestaurantPosTerminalScreen', $config['default_pos_screen']);
        $this->assertSame('restaurant_terminal', $config['pos_layout']);
        $this->assertSame('/restaurant-pos-terminal', $config['quick_actions']['add_sale']['target_route']);
        $this->assertSame('restaurant_terminal', $config['quick_actions']['add_sale']['screen_type']);
        $this->assertSame('restaurant_pos', $config['quick_actions']['add_sale']['route_key']);
    }

    public function test_restaurant_store_api_endpoints_expose_aligned_navigation_routes(): void
    {
        // 1. GET /api/v1/tenant/stores/current
        $responseCurrent = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->restaurantToken,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/tenant/stores/current');

        $responseCurrent->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_restaurant', true)
            ->assertJsonPath('operating_mode', 'restaurant')
            ->assertJsonPath('pos_layout', 'restaurant_terminal')
            ->assertJsonPath('primary_pos_route', 'restaurant_terminal')
            ->assertJsonPath('center_action_route', 'restaurant_terminal')
            ->assertJsonPath('primary_action', 'restaurant_terminal')
            ->assertJsonPath('default_pos_screen', 'RestaurantPosTerminalScreen')
            ->assertJsonPath('default_pos_action', 'restaurant_pos')
            ->assertJsonPath('quick_actions.add_sale.target_route', '/restaurant-pos-terminal')
            ->assertJsonPath('quick_actions.add_sale.screen_type', 'restaurant_terminal')
            ->assertJsonPath('quick_actions.add_sale.route_key', 'restaurant_pos')
            ->assertJsonPath('data.primary_pos_route', 'restaurant_terminal')
            ->assertJsonPath('data.center_action_route', 'restaurant_terminal')
            ->assertJsonPath('data.default_pos_screen', 'RestaurantPosTerminalScreen');

        // 2. GET /api/v1/tenant/stores
        $responseStores = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->restaurantToken,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/tenant/stores');

        $responseStores->assertOk()
            ->assertJsonPath('data.0.primary_pos_route', 'restaurant_terminal')
            ->assertJsonPath('data.0.center_action_route', 'restaurant_terminal')
            ->assertJsonPath('data.0.default_pos_screen', 'RestaurantPosTerminalScreen')
            ->assertJsonPath('data.0.quick_actions.add_sale.target_route', '/restaurant-pos-terminal')
            ->assertJsonPath('data.0.pos_layout', 'restaurant_terminal');
    }

    public function test_restaurant_settings_and_navigation_endpoints_expose_aligned_routes(): void
    {
        // 1. GET /api/v1/tenant/settings
        $responseSettings = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->restaurantToken,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/tenant/settings');

        $responseSettings->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pos_mode', 'restaurant')
            ->assertJsonPath('is_restaurant', true)
            ->assertJsonPath('primary_pos_route', 'restaurant_terminal')
            ->assertJsonPath('center_action_route', 'restaurant_terminal')
            ->assertJsonPath('primary_action', 'restaurant_terminal')
            ->assertJsonPath('default_pos_screen', 'RestaurantPosTerminalScreen')
            ->assertJsonPath('default_pos_action', 'restaurant_pos')
            ->assertJsonPath('pos_layout', 'restaurant_terminal')
            ->assertJsonPath('quick_actions.add_sale.target_route', '/restaurant-pos-terminal');

        // 2. GET /api/v1/tenant/navigation
        $responseNav = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->restaurantToken,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/tenant/navigation');

        $responseNav->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_restaurant', true)
            ->assertJsonPath('primary_pos_route', 'restaurant_terminal')
            ->assertJsonPath('center_action_route', 'restaurant_terminal')
            ->assertJsonPath('default_pos_screen', 'RestaurantPosTerminalScreen')
            ->assertJsonPath('default_pos_action', 'restaurant_pos')
            ->assertJsonPath('pos_layout', 'restaurant_terminal');
    }

    public function test_restaurant_app_bootstrap_returns_restaurant_terminal_route_alignment(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->restaurantToken,
            'Accept' => 'application/json',
        ])->getJson('/api/app/bootstrap');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_restaurant', true)
            ->assertJsonPath('primary_pos_route', 'restaurant_terminal')
            ->assertJsonPath('center_action_route', 'restaurant_terminal')
            ->assertJsonPath('primary_action', 'restaurant_terminal')
            ->assertJsonPath('default_pos_screen', 'RestaurantPosTerminalScreen')
            ->assertJsonPath('default_pos_action', 'restaurant_pos')
            ->assertJsonPath('pos_layout', 'restaurant_terminal')
            ->assertJsonPath('quick_actions.add_sale.target_route', '/restaurant-pos-terminal')
            ->assertJsonPath('quick_actions.add_sale.screen_type', 'restaurant_terminal')
            ->assertJsonPath('quick_actions.add_sale.route_key', 'restaurant_pos')
            ->assertJsonPath('config.primary_pos_route', 'restaurant_terminal')
            ->assertJsonPath('config.center_action_route', 'restaurant_terminal')
            ->assertJsonPath('config.pos_layout', 'restaurant_terminal')
            ->assertJsonPath('tenant.primary_pos_route', 'restaurant_terminal')
            ->assertJsonPath('nav.primary_pos_route', 'restaurant_terminal');
    }

    public function test_retail_store_uses_standard_pos_route(): void
    {
        $responseCurrent = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->retailToken,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/tenant/stores/current');

        $responseCurrent->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_restaurant', false)
            ->assertJsonPath('primary_pos_route', 'standard_pos')
            ->assertJsonPath('center_action_route', 'standard_pos')
            ->assertJsonPath('primary_action', 'standard_pos')
            ->assertJsonPath('default_pos_screen', 'PosGridScreen')
            ->assertJsonPath('default_pos_action', 'pos')
            ->assertJsonPath('quick_actions.add_sale.target_route', '/pos')
            ->assertJsonPath('quick_actions.add_sale.screen_type', 'pos_catalog')
            ->assertJsonPath('quick_actions.add_sale.route_key', 'pos');
    }

    public function test_dynamic_bottom_navigation_bar_and_center_action_router(): void
    {
        // 1. Restaurant Mode Bottom Navigation Schema
        $resRestaurant = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->restaurantToken,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/tenant/navigation/bottom-bar');

        $resRestaurant->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('center_action.id', 'primary_action')
            ->assertJsonPath('center_action.icon', 'add')
            ->assertJsonPath('center_action.target_route', '/restaurant-pos-terminal')
            ->assertJsonPath('center_action.route_key', 'restaurant_terminal')
            ->assertJsonPath('items.0.route', '/dashboard')
            ->assertJsonPath('items.1.route', '/sales')
            ->assertJsonPath('items.2.route', '/orders')
            ->assertJsonPath('items.3.route', 'drawer');

        // 2. Retail Mode Bottom Navigation Schema
        $resRetail = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->retailToken,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/tenant/navigation/bottom-bar');

        $resRetail->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('center_action.id', 'primary_action')
            ->assertJsonPath('center_action.icon', 'add')
            ->assertJsonPath('center_action.target_route', '/pos')
            ->assertJsonPath('center_action.route_key', 'pos')
            ->assertJsonPath('items.0.route', '/dashboard')
            ->assertJsonPath('items.1.route', '/sales')
            ->assertJsonPath('items.2.route', '/orders')
            ->assertJsonPath('items.3.route', 'drawer');
    }
}

