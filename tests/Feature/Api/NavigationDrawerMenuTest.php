<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\NavigationController;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\Navigation\TenantNavigationConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NavigationDrawerMenuTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected string $token;

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
            'name' => 'Test Company',
            'display_name' => 'Test Store',
            'slug' => 'test-store',
            'email' => 'store@test.com',
            'pos_mode' => 'retail',
            'currency' => 'USD',
            'currency_symbol' => '$',
        ]);

        $this->user = User::create([
            'company_id' => $this->company->id,
            'name' => 'Admin User',
            'login' => 'admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $loginRes = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);
        $this->token = $loginRes->json('token') ?? '';
    }

    public function test_get_drawer_menu_controller_returns_correct_sdui_schema(): void
    {
        $controller = new NavigationController;
        $response = $controller->getDrawerMenu(Request::create('/api/navigation/menu'));

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertIsArray($data['components']);
        $this->assertIsArray($data['menu']);
        $this->assertSame($data['components'], $data['menu']);

        $components = $data['components'];

        // Item 0: Home
        $this->assertSame('list_tile', $components[0]['type']);
        $this->assertSame('Home', $components[0]['title']);
        $this->assertSame('home', $components[0]['icon']);
        $this->assertSame('NAVIGATE_TO', $components[0]['action_type']);
        $this->assertSame('/dashboard', $components[0]['route']);

        // Item 1: Divider for Point of Sale / Cashier
        $this->assertSame('divider', $components[1]['type']);

        // Item 2: Point of Sale clickable header
        $this->assertSame('list_tile', $components[2]['type']);
        $this->assertSame('Point of Sale', $components[2]['title']);
        $this->assertSame('point_of_sale', $components[2]['icon']);
        $this->assertSame(['fontWeight' => 'bold', 'textColor' => '#F97316'], $components[2]['style']);
        $this->assertSame('NAVIGATE_TO', $components[2]['action_type']);
        $this->assertSame('/pos', $components[2]['route']);

        // Items 3-6: Cashier sub items
        $this->assertSame('Sales & Invoices', $components[3]['title']);
        $this->assertSame('/invoices', $components[3]['route']);
        $this->assertSame('Quotations & Proposals', $components[4]['title']);
        $this->assertSame('/tenant/views/quotations', $components[4]['route']);
        $this->assertSame('Consignments', $components[5]['title']);
        $this->assertSame('/consignments', $components[5]['route']);
        $this->assertSame('Customers & CRM', $components[6]['title']);
        $this->assertSame('/customers', $components[6]['route']);

        // Item 7: Divider for Leads & CRM
        $this->assertSame('divider', $components[7]['type']);

        // Item 8: Lead Dashboard clickable parent header
        $this->assertSame('list_tile', $components[8]['type']);
        $this->assertSame('Lead Dashboard', $components[8]['title']);
        $this->assertSame('grid_view', $components[8]['icon']);
        $this->assertSame(['fontWeight' => 'bold', 'textColor' => '#F97316'], $components[8]['style']);
        $this->assertSame('NAVIGATE_TO', $components[8]['action_type']);
        $this->assertSame('/tenant/views/leads', $components[8]['route']);

        // Item 9: Divider for Products & Inventory
        $this->assertSame('divider', $components[9]['type']);

        // Item 10: Product Catalog clickable header with children
        $this->assertSame('list_tile', $components[10]['type']);
        $this->assertSame('Product Catalog', $components[10]['title']);
        $this->assertSame('inventory_2', $components[10]['icon']);
        $this->assertSame(['fontWeight' => 'bold', 'textColor' => '#F97316'], $components[10]['style']);
        $this->assertSame('NAVIGATE_TO', $components[10]['action_type']);
        $this->assertSame('/products', $components[10]['route']);
        $this->assertIsArray($components[10]['children']);
        $this->assertCount(6, $components[10]['children']);
        $this->assertSame('list_tile', $components[10]['children'][0]['type']);
        $this->assertSame('Categories', $components[10]['children'][0]['title']);
        $this->assertSame('NAVIGATE_TO', $components[10]['children'][0]['action_type']);
        $this->assertSame('/categories', $components[10]['children'][0]['route']);
        $this->assertSame('list_tile', $components[10]['children'][5]['type']);
        $this->assertSame('Online Digital Catalog', $components[10]['children'][5]['title']);
        $this->assertSame('NAVIGATE_TO', $components[10]['children'][5]['action_type']);
        $this->assertSame('/digital-catalog', $components[10]['children'][5]['route']);

        // Item 11: Divider for Financial Management
        $this->assertSame('divider', $components[11]['type']);

        // Item 12: Cash Register clickable header with children
        $this->assertSame('list_tile', $components[12]['type']);
        $this->assertSame('Cash Register', $components[12]['title']);
        $this->assertSame('account_balance_wallet', $components[12]['icon']);
        $this->assertSame(['fontWeight' => 'bold', 'textColor' => '#F97316'], $components[12]['style']);
        $this->assertSame('NAVIGATE_TO', $components[12]['action_type']);
        $this->assertSame('/register', $components[12]['route']);
        $this->assertIsArray($components[12]['children']);
        $this->assertCount(4, $components[12]['children']);
        $this->assertSame('list_tile', $components[12]['children'][0]['type']);
        $this->assertSame('Accounts Receivable', $components[12]['children'][0]['title']);
        $this->assertSame('NAVIGATE_TO', $components[12]['children'][0]['action_type']);
        $this->assertSame('/receivables', $components[12]['children'][0]['route']);
        $this->assertSame('list_tile', $components[12]['children'][3]['type']);
        $this->assertSame('Reports', $components[12]['children'][3]['title']);
        $this->assertSame('NAVIGATE_TO', $components[12]['children'][3]['action_type']);
        $this->assertSame('/reports', $components[12]['children'][3]['route']);
    }

    public function test_drawer_menu_api_endpoints_return_200_and_components(): void
    {
        $endpoints = [
            '/api/navigation/menu',
            '/api/drawer/menu',
            '/api/drawer-menu',
            '/api/menu',
            '/api/tenant/navigation/menu',
            '/api/app/navigation/menu',
            '/api/v1/tenant/navigation/menu',
            '/api/v1/navigation/menu',
            '/api/tenant/drawer/menu',
            '/api/app/drawer/menu',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->withToken($this->token)->getJson($endpoint);
            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('components.0.type', 'list_tile')
                ->assertJsonPath('components.0.title', 'Home')
                ->assertJsonPath('components.1.type', 'divider')
                ->assertJsonPath('components.2.type', 'section_header')
                ->assertJsonPath('components.2.title', 'Retail & Cashier');

            $components = collect($response->json('components'));
            $this->assertSame('/api/tenant/views/pos', $components->firstWhere('key', 'pos')['route']);
        }
    }

    public function test_drawer_navigation_endpoint_injects_sdui_components_and_dividers(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/tenant/navigation/drawer');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'header',
                'drawer_header',
                'sections',
                'navigation',
                'components',
                'menu',
            ])
            ->assertJsonPath('components.0.title', 'Home')
            ->assertJsonPath('components.1.type', 'divider')
            ->assertJsonPath('components.2.type', 'section_header')
            ->assertJsonPath('components.2.title', 'Retail & Cashier');

        $components = collect($response->json('components'));
        $this->assertSame('/api/tenant/views/pos', $components->firstWhere('key', 'pos')['route']);

        // Check that sections have clickable list_tile parent metadata
        $sections = $response->json('sections');
        $this->assertNotEmpty($sections);
        $cashierSection = $sections[0];
        $this->assertSame('list_tile', $cashierSection['type']);
        $this->assertSame('NAVIGATE_TO', $cashierSection['action_type']);
        $this->assertSame('/api/tenant/views/pos', $cashierSection['route']);
        $this->assertSame(['fontWeight' => 'bold', 'textColor' => '#F97316'], $cashierSection['style']);
        $this->assertSame(['type' => 'divider'], $cashierSection['divider']);
    }

    public function test_sdui_drawer_view_screen_returns_screen_with_components(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/tenant/views/drawer');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.type', 'screen')
            ->assertJsonPath('schema.components.0.title', 'Home')
            ->assertJsonPath('schema.components.1.type', 'divider')
            ->assertJsonPath('schema.components.2.type', 'section_header')
            ->assertJsonPath('schema.components.2.title', 'Retail & Cashier');

        $menuResponse = $this->withToken($this->token)->getJson('/api/tenant/views/drawer-menu');
        $menuResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.type', 'screen');
        $this->assertSame('/api/tenant/views/pos', collect($menuResponse->json('schema.components'))->firstWhere('key', 'pos')['route']);
    }

    public function test_drawer_navigation_keeps_parent_route_children_and_custom_section_title_separate(): void
    {
        $nav = app(TenantNavigationConfigService::class)->normalize([
            'sections' => [[
                'key' => 'cashier_sales',
                'order' => 0,
                'custom_title' => 'Front Desk & POS',
            ]],
            'tree' => [[
                'key' => 'cashier_sales',
                'order' => 0,
                'custom_title' => 'Front Desk & POS',
                'items' => [[
                    'key' => 'pos',
                    'visible' => true,
                    'children' => [[
                        'key' => 'sales',
                        'visible' => true,
                        'children' => [],
                    ]],
                ]],
            ]],
        ]);
        $this->company->update(['nav_config' => $nav]);

        $response = $this->withToken($this->token)
            ->getJson('/api/tenant/navigation/drawer')
            ->assertOk();

        $cashier = collect($response->json('sections'))->firstWhere('key', 'cashier_sales');
        $this->assertSame('Front Desk & POS', $cashier['custom_title']);
        $this->assertSame('Point of Sale', $cashier['first_item']['title']);
        $this->assertSame('/api/tenant/views/pos', $cashier['first_item']['route']);
        $this->assertSame('NAVIGATE_TO', $cashier['first_item']['action_type']);
        $this->assertContains('sales', collect($cashier['items'])->pluck('key')->all());
    }

    public function test_saved_store_settings_root_and_custom_section_are_used_by_drawer_components(): void
    {
        $nav = app(TenantNavigationConfigService::class)->normalize([
            'sections' => [[
                'key' => 'administration',
                'order' => 0,
                'custom_title' => 'Administration & Settings',
            ]],
            'items' => [
                ['key' => 'subscription', 'section' => 'administration', 'parent' => null, 'order' => 0, 'visible' => true],
                // Simulate the bad saved offset. The canonical normalizer must
                // promote Store Settings before it reaches any drawer reader.
                ['key' => 'settings', 'section' => 'administration', 'parent' => 'subscription', 'order' => 1, 'visible' => true],
                ['key' => 'settings_profile', 'section' => 'administration', 'parent' => 'settings', 'order' => 0, 'visible' => true],
            ],
        ]);
        $this->company->update(['nav_config' => $nav]);

        $response = $this->withToken($this->token)
            ->getJson('/api/tenant/navigation/menu')
            ->assertOk();

        $components = collect($response->json('components'));
        $administrationHeader = $components->first(
            fn (array $component) => ($component['type'] ?? null) === 'section_header'
                && ($component['section_key'] ?? null) === 'administration'
        );
        $settings = $components->firstWhere('key', 'settings');
        $subscription = $components->firstWhere('key', 'subscription');

        $this->assertSame('Administration & Settings', $administrationHeader['title']);
        $this->assertNull($settings['parent_id']);
        $this->assertSame(0, $settings['level']);
        $this->assertSame('settings_profile', $settings['children'][0]['key']);
        $this->assertSame('settings', $settings['children'][0]['parent_id']);
        $this->assertSame([], $subscription['children'] ?? []);
    }

    public function test_save_menu_settings_persists_to_tenant_settings_and_drawer_reads_it(): void
    {
        $payload = [
            'sections' => [
                [
                    'key' => 'administration',
                    'order' => 0,
                    'custom_title' => 'Admin Suite',
                    'items' => [
                        [
                            'key' => 'subscription',
                            'title' => 'Subscription & Billing',
                            'visible' => true,
                            'children' => [],
                        ],
                        [
                            'key' => 'settings',
                            'title' => 'Store Settings',
                            'visible' => true,
                            'children' => [
                                [
                                    'key' => 'settings_profile',
                                    'title' => 'Store Profile',
                                    'visible' => true,
                                    'children' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveResponse = $this->withToken($this->token)
            ->postJson('/api/navigation/menu', $payload)
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Navigation layout saved successfully.',
            ]);

        // Verify tenant_settings has persisted record
        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $this->company->id,
            'key' => 'navigation_menu_custom',
        ]);

        // Verify getDrawerMenu returns customized layout
        $drawerResponse = $this->withToken($this->token)
            ->getJson('/api/navigation/menu')
            ->assertOk();

        $components = collect($drawerResponse->json('components'));
        $adminHeader = $components->first(
            fn (array $c) => ($c['type'] ?? null) === 'section_header' && ($c['section_key'] ?? null) === 'administration'
        );
        $this->assertNotNull($adminHeader);
        $this->assertSame('Admin Suite', $adminHeader['title']);

        $settings = $components->firstWhere('key', 'settings');
        $this->assertNotNull($settings);
        $this->assertNull($settings['parent_id']);
        $this->assertSame(0, $settings['level']);

        $subscription = $components->firstWhere('key', 'subscription');
        $this->assertNotNull($subscription);
        $this->assertSame([], $subscription['children'] ?? []);
    }

    public function test_flat_item_list_breaks_out_of_parent_when_level_is_zero(): void
    {
        $controller = new \App\Http\Controllers\Api\NavigationMenuController;

        $rawItems = [
            ['key' => 'settings_profile', 'title' => 'Store Profile', 'level' => 0, 'parent_id' => null, 'visible' => true],
            ['key' => 'languages', 'title' => 'Languages & Translations', 'level' => 0, 'parent_id' => null, 'visible' => true],
            ['key' => 'staff', 'title' => 'Users & Permissions', 'level' => 1, 'parent_id' => 'languages', 'visible' => true],
            ['key' => 'roles', 'title' => 'Roles & Access Levels', 'level' => 1, 'parent_id' => 'languages', 'visible' => true],
            ['key' => 'devices', 'title' => 'Terminals & Devices', 'level' => 0, 'parent_id' => null, 'visible' => true],
            ['key' => 'hardware_printer', 'title' => 'Printer & Hardware Setup', 'level' => 0, 'parent_id' => null, 'visible' => true],
            ['key' => 'change_password', 'title' => 'Change Password', 'level' => 0, 'parent_id' => null, 'visible' => true],
        ];

        $components = collect($controller->formatCustomMenuComponents($rawItems));

        $storeProfile = $components->firstWhere('key', 'settings_profile');
        $this->assertNotNull($storeProfile);
        $this->assertSame(0, $storeProfile['level']);
        $this->assertNull($storeProfile['parent_id']);
        $this->assertSame([], $storeProfile['children'] ?? []);

        $languages = $components->firstWhere('key', 'languages');
        $this->assertNotNull($languages);
        $this->assertSame(0, $languages['level']);
        $this->assertNull($languages['parent_id']);
        $this->assertCount(2, $languages['children']);
        $this->assertSame('staff', $languages['children'][0]['key']);
        $this->assertSame('roles', $languages['children'][1]['key']);

        $devices = $components->firstWhere('key', 'devices');
        $this->assertNotNull($devices);
        $this->assertSame(0, $devices['level']);
        $this->assertNull($devices['parent_id']);
        $this->assertSame([], $devices['children'] ?? []);

        $printer = $components->firstWhere('key', 'hardware_printer');
        $this->assertNotNull($printer);
        $this->assertSame(0, $printer['level']);
        $this->assertNull($printer['parent_id']);
        $this->assertSame([], $printer['children'] ?? []);

        $password = $components->firstWhere('key', 'change_password');
        $this->assertNotNull($password);
        $this->assertSame(0, $password['level']);
        $this->assertNull($password['parent_id']);
        $this->assertSame([], $password['children'] ?? []);
    }

    public function test_drawer_header_prioritizes_business_name_over_trading_name_dba(): void
    {
        // Setup company matching user diagnostic case:
        // Business Name = 'ZoomNearby Demo India'
        // Trading Name (DBA) = 'ZoomNearby Demo'
        $this->company->update([
            'name' => 'ZoomNearby Demo India',
            'trade_name' => 'ZoomNearby Demo',
        ]);
        $this->company->refresh();

        // 1. Model level assertion
        $this->assertSame('ZoomNearby Demo India', $this->company->display_name);
        $headerPayload = $this->company->getDrawerHeaderPayload();
        $this->assertSame('ZoomNearby Demo India', $headerPayload['store_name']);
        $this->assertSame('ZoomNearby Demo India', $headerPayload['business_name']);
        $this->assertSame('ZoomNearby Demo India', $headerPayload['tenant_name']);
        $this->assertSame('ZoomNearby Demo India', $headerPayload['title']);
        $this->assertSame('ZoomNearby Demo India', $headerPayload['display_name']);
        $this->assertSame('ZoomNearby Demo', $headerPayload['dba_name']);

        // 2. Drawer Navigation endpoint (/api/tenant/navigation/drawer)
        $drawerResponse = $this->withToken($this->token)->getJson('/api/tenant/navigation/drawer');
        $drawerResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('store_name', 'ZoomNearby Demo India')
            ->assertJsonPath('business_name', 'ZoomNearby Demo India')
            ->assertJsonPath('tenant_name', 'ZoomNearby Demo India')
            ->assertJsonPath('title', 'ZoomNearby Demo India')
            ->assertJsonPath('display_name', 'ZoomNearby Demo India')
            ->assertJsonPath('header.store_name', 'ZoomNearby Demo India')
            ->assertJsonPath('header.business_name', 'ZoomNearby Demo India')
            ->assertJsonPath('header.title', 'ZoomNearby Demo India')
            ->assertJsonPath('drawer_header.store_name', 'ZoomNearby Demo India')
            ->assertJsonPath('drawer_header.business_name', 'ZoomNearby Demo India')
            ->assertJsonPath('drawer_header.dba_name', 'ZoomNearby Demo');

        // 3. Drawer Menu endpoint (/api/tenant/navigation/menu)
        $menuResponse = $this->withToken($this->token)->getJson('/api/tenant/navigation/menu');
        $menuResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('store_name', 'ZoomNearby Demo India')
            ->assertJsonPath('business_name', 'ZoomNearby Demo India')
            ->assertJsonPath('header.store_name', 'ZoomNearby Demo India')
            ->assertJsonPath('drawer_header.business_name', 'ZoomNearby Demo India');

        // 4. SDUI Drawer Screen (/api/tenant/views/drawer)
        $sduiResponse = $this->withToken($this->token)->getJson('/api/tenant/views/drawer');
        $sduiResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'ZoomNearby Demo India')
            ->assertJsonPath('schema.app_bar.title', 'ZoomNearby Demo India')
            ->assertJsonPath('schema.store_name', 'ZoomNearby Demo India')
            ->assertJsonPath('schema.business_name', 'ZoomNearby Demo India')
            ->assertJsonPath('schema.drawer_header.business_name', 'ZoomNearby Demo India')
            ->assertJsonPath('schema.drawer_header.dba_name', 'ZoomNearby Demo');

        // 5. Bootstrap endpoint (/api/v1/tenant/bootstrap)
        $bootstrapResponse = $this->withToken($this->token)->getJson('/api/v1/tenant/bootstrap');
        $bootstrapResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('store_name', 'ZoomNearby Demo India')
            ->assertJsonPath('business_name', 'ZoomNearby Demo India')
            ->assertJsonPath('header.business_name', 'ZoomNearby Demo India')
            ->assertJsonPath('drawer_header.business_name', 'ZoomNearby Demo India')
            ->assertJsonPath('tenant.business_name', 'ZoomNearby Demo India')
            ->assertJsonPath('tenant.store_name', 'ZoomNearby Demo India');
    }

    public function test_saving_store_profile_updates_business_name_and_purges_drawer_cache(): void
    {
        $tenantId = (string) $this->company->id;

        // Seed tenant caches
        \Illuminate\Support\Facades\Cache::put("tenant_{$tenantId}_profile", ['dummy' => 'stale'], 3600);
        \Illuminate\Support\Facades\Cache::put("tenant_{$tenantId}_drawer", ['dummy' => 'stale'], 3600);
        \Illuminate\Support\Facades\Cache::put("tenant_{$tenantId}_drawer_menu", ['dummy' => 'stale'], 3600);
        \Illuminate\Support\Facades\Cache::put("navigation_menu_{$tenantId}", ['dummy' => 'stale'], 3600);
        \Illuminate\Support\Facades\Cache::put("store_profile_{$tenantId}", ['dummy' => 'stale'], 3600);

        // POST /api/tenant/settings/profile via wizard Save & Continue
        $response = $this->withToken($this->token)->postJson('/api/tenant/settings/profile', [
            'name' => 'ZoomNearby Demo India',
            'trade_name' => 'ZoomNearby Demo',
            'wizard_tab_index' => 0,
            'wizard_total_tabs' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('store_name', 'ZoomNearby Demo India')
            ->assertJsonPath('business_name', 'ZoomNearby Demo India')
            ->assertJsonPath('header.business_name', 'ZoomNearby Demo India')
            ->assertJsonPath('drawer_header.business_name', 'ZoomNearby Demo India')
            ->assertJsonPath('company.business_name', 'ZoomNearby Demo India');

        // Verify caches have been cleared
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has("tenant_{$tenantId}_profile"));
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has("tenant_{$tenantId}_drawer"));
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has("tenant_{$tenantId}_drawer_menu"));
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has("navigation_menu_{$tenantId}"));
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has("store_profile_{$tenantId}"));

        // Verify fresh drawer call returns updated name
        $freshDrawer = $this->withToken($this->token)->getJson('/api/tenant/navigation/drawer');
        $freshDrawer->assertOk()
            ->assertJsonPath('business_name', 'ZoomNearby Demo India')
            ->assertJsonPath('header.business_name', 'ZoomNearby Demo India')
            ->assertJsonPath('drawer_header.business_name', 'ZoomNearby Demo India');
    }

    public function test_drawer_text_and_icons_color_preference_updates_both_labels_and_icons_dynamically(): void
    {
        // 1. Update Drawer text & icons to Purple (#7C3AED)
        $purpleHex = '#7C3AED';
        $prefResponse = $this->withToken($this->token)->postJson('/api/tenant/preferences', [
            'drawer_text_and_icons' => $purpleHex,
        ]);
        $prefResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('preferences.drawer_text_and_icons', $purpleHex)
            ->assertJsonPath('preferences.drawer_text_icon_color', $purpleHex);

        // 2. Fetch Drawer Menu (/api/tenant/navigation/menu)
        $menuRes = $this->withToken($this->token)->getJson('/api/tenant/navigation/menu');
        $menuRes->assertOk();
        $components = collect($menuRes->json('components'));

        // Point of Sale tile
        $pos = $components->firstWhere('key', 'pos') ?? $components->firstWhere('title', 'Point of Sale');
        $this->assertNotNull($pos);
        $this->assertSame($purpleHex, $pos['icon_color']);
        $this->assertSame($purpleHex, $pos['leading']['color']);
        $this->assertSame($purpleHex, $pos['style']['textColor']);
        $this->assertSame($purpleHex, $pos['style']['iconColor']);

        // Home tile
        $home = $components->firstWhere('key', 'home');
        $this->assertNotNull($home);
        $this->assertSame($purpleHex, $home['icon_color']);
        $this->assertSame($purpleHex, $home['leading']['color']);

        // Sub items (Sales & Invoices)
        $sales = $components->firstWhere('key', 'sales') ?? $components->firstWhere('title', 'Sales & Invoices');
        if ($sales) {
            $this->assertSame($purpleHex, $sales['icon_color']);
            $this->assertSame($purpleHex, $sales['leading']['color']);
        }

        // 3. Fetch Drawer Navigation (/api/tenant/navigation/drawer)
        $navRes = $this->withToken($this->token)->getJson('/api/tenant/navigation/drawer');
        $navRes->assertOk();
        $sections = $navRes->json('sections');
        $this->assertNotEmpty($sections);
        $firstSection = $sections[0];
        $this->assertSame($purpleHex, $firstSection['icon_color']);
        $this->assertSame($purpleHex, $firstSection['leading']['color']);
        $this->assertSame($purpleHex, $firstSection['style']['textColor']);
        $this->assertSame($purpleHex, $firstSection['first_item']['icon_color']);
        $this->assertSame($purpleHex, $firstSection['first_item']['leading']['color']);

        // 4. Fetch SDUI Drawer Screen (/api/tenant/views/drawer)
        $screenRes = $this->withToken($this->token)->getJson('/api/tenant/views/drawer');
        $screenRes->assertOk();
        $screenComponents = collect($screenRes->json('schema.components'));
        $screenPos = $screenComponents->firstWhere('key', 'pos') ?? $screenComponents->firstWhere('title', 'Point of Sale');
        $this->assertNotNull($screenPos);
        $this->assertSame($purpleHex, $screenPos['icon_color']);
        $this->assertSame($purpleHex, $screenPos['leading']['color']);
        $this->assertSame($purpleHex, $screenPos['style']['textColor']);

        // 5. Update to Green (#10B981)
        $greenHex = '#10B981';
        $this->withToken($this->token)->postJson('/api/tenant/settings/app-preferences', [
            'drawer_text_icon_color' => $greenHex,
        ])->assertOk();

        $freshMenuRes = $this->withToken($this->token)->getJson('/api/tenant/navigation/menu');
        $freshComponents = collect($freshMenuRes->json('components'));
        $freshPos = $freshComponents->firstWhere('key', 'pos') ?? $freshComponents->firstWhere('title', 'Point of Sale');
        $this->assertSame($greenHex, $freshPos['icon_color']);
        $this->assertSame($greenHex, $freshPos['leading']['color']);
        $this->assertSame($greenHex, $freshPos['style']['textColor']);

        // 6. Update to Crimson (#DC2626)
        $crimsonHex = '#DC2626';
        $this->withToken($this->token)->postJson('/api/tenant/settings/drawer-surfaces', [
            'drawer_text_and_icons' => $crimsonHex,
        ])->assertOk();

        $freshNavRes = $this->withToken($this->token)->getJson('/api/tenant/navigation/drawer');
        $freshSections = $freshNavRes->json('sections');
        $this->assertSame($crimsonHex, $freshSections[0]['icon_color']);
        $this->assertSame($crimsonHex, $freshSections[0]['leading']['color']);
        $this->assertSame($crimsonHex, $freshSections[0]['style']['textColor']);
    }
}


