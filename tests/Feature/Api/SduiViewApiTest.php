<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Plan;
use App\Models\SduiModule;
use App\Models\SduiScreen;
use App\Models\User;
use App\Services\Sdui\SchemaResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class SduiViewApiTest extends TestCase
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
            'name' => 'Apex Retail Solutions',
            'trade_name' => 'Apex Mart',
            'slug' => 'apex-mart',
            'email' => 'admin@apexmart.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'plan_name' => 'trial',
            'expires_at' => now()->addDays(14),
            'licensed_modules' => ['retail', 'pharmacy'],
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'admin@apexmart.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);
    }

    protected function token(): string
    {
        return $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@apexmart.com',
            'password' => 'secret123',
        ])->json('token');
    }

    public function test_sdui_settings_mode_view_returns_valid_schema(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-mode');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Store Operating Mode')
            ->assertJsonPath('schema.layout', 'scroll_view');

        $components = $response->json('schema.components');
        $this->assertNotEmpty($components);
    }

    public function test_sdui_settings_financial_view_returns_inputs_and_action(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-financial');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Financial & Currency');

        $this->assertStringContainsString('financial', json_encode($response->json()));
    }

    public function test_navigation_view_always_returns_a_populated_recursive_tree(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-navigation')
            ->assertOk()
            ->assertJsonPath('success', true);

        $builder = collect($response->json('schema.components'))
            ->firstWhere('type', 'tree_builder');

        $this->assertIsArray($builder);
        $this->assertNotEmpty($builder['tree_data']);
        $this->assertSame($builder['tree_data'], $builder['sections']);
        $this->assertNotEmpty($builder['nav_config']['items']);
        $this->assertSame('cashier_sales', $builder['tree_data'][0]['key']);
        $this->assertNotEmpty($builder['tree_data'][0]['items']);

        $settings = collect($builder['tree_data'])
            ->flatMap(fn (array $section) => $section['items'])
            ->firstWhere('key', 'settings');
        $this->assertNotEmpty($settings['children']);
        $this->assertSame('settings_profile', $settings['children'][1]['key']);
    }

    public function test_database_authored_navigation_screen_is_hydrated_with_tenant_tree_data(): void
    {
        SduiScreen::create([
            'key' => 'settings-navigation',
            'title' => 'Custom Navigation Header',
            'permission' => 'settings.view',
            'schema' => [
                'layout' => 'scroll_view',
                'components' => [
                    SchemaResponse::text('Custom navigation help'),
                    ['type' => 'tree_builder', 'title' => 'Menu editor'],
                ],
            ],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-navigation')
            ->assertOk()
            ->assertJsonPath('schema.title', 'Custom Navigation Header');

        $builder = collect($response->json('schema.components'))
            ->firstWhere('type', 'tree_builder');
        $this->assertNotEmpty($builder['tree_data']);
        $this->assertNotEmpty($builder['nav_config']['items']);
    }

    public function test_profile_brand_colors_are_visual_color_picker_components(): void
    {
        $schema = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-branding')
            ->assertOk()
            ->json('schema');

        $componentsByName = [];
        $visit = function (mixed $nodes) use (&$visit, &$componentsByName): void {
            foreach (is_array($nodes) ? $nodes : [] as $node) {
                if (! is_array($node)) {
                    continue;
                }
                if (! empty($node['name'])) {
                    $componentsByName[$node['name']] = $node;
                }
                $visit($node['components'] ?? $node['children'] ?? []);
            }
        };
        $visit($schema['components']);

        foreach (['primary_color', 'accent_color', 'drawer_bg'] as $name) {
            $this->assertSame('color_picker', $componentsByName[$name]['type']);
            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $componentsByName[$name]['initial_value']);
        }
        $this->assertSame('Sidebar / Drawer Background', $componentsByName['drawer_bg']['label']);
    }

    public function test_database_authored_profile_screen_upgrades_legacy_color_inputs(): void
    {
        SduiScreen::create([
            'key' => 'settings-profile',
            'title' => 'Custom Store Profile',
            'permission' => 'settings.view',
            'schema' => [
                'layout' => 'scroll_view',
                'components' => [
                    SchemaResponse::textInput('primary_color', 'Primary Accent Color', '#112233'),
                    SchemaResponse::textInput('accent_color', 'Secondary Accent Color', '#445566'),
                    SchemaResponse::textInput('drawer_bg', 'Sidebar / Drawer Background', '#778899'),
                ],
            ],
        ]);

        $components = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-profile')
            ->assertOk()
            ->json('schema.components');

        $this->assertSame(
            ['color_picker', 'color_picker', 'color_picker'],
            array_column($components, 'type')
        );
    }

    public function test_every_settings_panel_returns_a_valid_versioned_sdui_tree(): void
    {
        $token = $this->token();

        foreach (['mode', 'profile', 'branding', 'receipts', 'financial', 'taxes', 'api', 'navigation'] as $panel) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/tenant/views/settings-'.$panel)
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('schema.type', 'screen')
                ->assertJsonPath('schema.schema_version', SchemaResponse::SCHEMA_VERSION)
                ->assertJsonStructure(['schema' => ['title', 'layout', 'app_bar', 'components']]);
        }
    }

    public function test_sdui_plug_and_play_future_module_view_generates_dynamically(): void
    {
        // Pharmacy module
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/pharmacy');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Pharmacy POS');

        $salon = SduiModule::create([
            'name' => 'Salon & Spa',
            'slug' => 'salon',
            'description' => 'Appointments and stylist scheduling.',
            'icon' => 'spa',
            'features' => ['appointments' => true],
            'routes' => ['home' => '/api/tenant/views/salon-spa'],
            'navigation' => [],
            'is_active' => true,
        ]);
        SduiScreen::create([
            'sdui_module_id' => $salon->id,
            'key' => 'salon-spa',
            'title' => 'Salon Workspace',
            'permission' => 'pos.view',
            'schema' => [
                'layout' => 'scroll_view',
                'components' => [
                    SchemaResponse::text('Today’s appointments', 'title_large'),
                ],
            ],
        ]);
        $this->company->update(['licensed_modules' => ['retail', 'pharmacy', 'salon']]);

        // A database-authored future vertical needs no Flutter compile touchpoint.
        $futureResponse = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/salon-spa');

        $futureResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Salon Workspace')
            ->assertJsonPath('schema.schema_version', SchemaResponse::SCHEMA_VERSION);
    }

    public function test_sdui_form_submission_updates_financial_settings(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->postJson('/api/tenant/settings/financial', [
                'currency' => 'EUR',
                'currency_symbol' => '€',
                'currency_decimals' => 2,
                'currency_symbol_position' => 'suffix',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('EUR', $this->company->fresh()->currency);
        $this->assertSame('€', $this->company->fresh()->currency_symbol);
    }

    public function test_sdui_form_submission_for_mode_returns_403_forbidden(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->postJson('/api/tenant/settings/mode', [
                'mode' => 'restaurant',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_api_integration_settings_are_persisted_in_tenant_configuration(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->postJson('/api/tenant/settings/api', [
                'webhook_url' => 'https://example.test/order-hook',
                'ai_catalog_enrichment' => false,
                'ai_receipt_ocr' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('configurations', [
            'company_id' => $this->company->id,
            'key' => 'webhook_url',
            'value' => 'https://example.test/order-hook',
        ]);
        $this->assertDatabaseHas('configurations', [
            'company_id' => $this->company->id,
            'key' => 'ai_catalog_enrichment',
            'value' => '0',
        ]);
    }

    public function test_database_registered_module_controls_bootstrap_navigation_and_screen_directory(): void
    {
        $module = SduiModule::create([
            'name' => 'Laundry',
            'slug' => 'laundry',
            'icon' => 'local_laundry_service',
            'features' => ['pickup_tracking' => true],
            'navigation' => [[
                'key' => 'laundry_operations',
                'label' => 'Laundry Operations',
                'color' => '#2563eb',
                'items' => [[
                    'key' => 'laundry_queue',
                    'title' => 'Laundry Queue',
                    'icon' => 'local_laundry_service',
                    'type' => 'link',
                    'target_endpoint' => '/api/tenant/views/laundry-queue',
                    'permission' => 'pos',
                ]],
            ]],
            'is_active' => true,
        ]);
        SduiScreen::create([
            'sdui_module_id' => $module->id,
            'key' => 'laundry-queue',
            'title' => 'Laundry Queue',
            'permission' => 'pos.view',
            'schema' => [
                'layout' => 'column',
                'components' => [SchemaResponse::text('Orders awaiting wash')],
            ],
        ]);
        $this->company->update([
            'pos_mode' => 'laundry',
            'licensed_modules' => ['laundry'],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/app/bootstrap')
            ->assertOk()
            ->assertJsonPath('tenant.active_mode', 'laundry')
            ->assertJsonPath('modules.laundry.source', 'database')
            ->assertJsonPath('menu_structure.0.items.0.target_endpoint', '/api/tenant/views/laundry-queue')
            ->assertJsonFragment([
                'key' => 'laundry-queue',
                'endpoint' => '/api/tenant/views/laundry-queue',
                'permission' => 'pos.view',
            ]);
    }

    public function test_unknown_views_are_not_synthesized_and_invalid_schemas_cannot_be_registered(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/not-registered')
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->expectException(InvalidArgumentException::class);
        SduiScreen::create([
            'key' => 'broken',
            'title' => 'Broken',
            'schema' => [
                'layout' => 'scroll_view',
                'components' => [['type' => 'made_up_widget']],
            ],
        ]);
    }

    public function test_bootstrap_delivers_accordion_navigation_and_target_endpoints(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/app/bootstrap');

        $response->assertOk();
        $response->assertJsonPath('schema_contract.version', SchemaResponse::SCHEMA_VERSION)
            ->assertJsonPath('screens.0.endpoint', '/api/tenant/views/settings-mode');
        $menu = $response->json('menu_structure');
        $this->assertNotEmpty($menu);

        $adminSection = collect($menu)->firstWhere('key', 'administration');
        $this->assertNotNull($adminSection);

        $settingsItem = collect($adminSection['items'])->firstWhere('key', 'settings');
        $this->assertNotNull($settingsItem);
        $this->assertSame('accordion', $settingsItem['type']);
        $this->assertNotEmpty($settingsItem['children']);

        $firstTab = $settingsItem['children'][0];
        $this->assertArrayHasKey('target_endpoint', $firstTab);
        $this->assertSame('/api/tenant/views/settings-mode', $firstTab['target_endpoint']);
    }

    public function test_licensed_module_views_render_valid_sdui_screens(): void
    {
        $token = $this->token();
        $endpoints = [
            'restaurant-tables' => 'Floor Plan & Tables',
            'restaurant-kds' => 'Kitchen Display (KDS)',
            'restaurant-pos' => 'Restaurant POS Terminal',
            'dining-history' => 'KOT Register & Live Orders',
            'pharmacy-batches' => 'Batch & Expiry Manager',
            'pharmacy-prescriptions' => 'Prescriptions Queue',
            'service-calendar' => 'Service Booking Calendar',
            'service-stylists' => 'Specialists & Stylists',
            'service-orders' => 'Service Catalog & Rates',
            'service-catalog' => 'Service Catalog & Rates',
            'service-create' => 'Add New Service',
            'change-password' => 'Change Password',
        ];

        foreach ($endpoints as $viewKey => $expectedTitle) {
            $response = $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson("/api/tenant/views/{$viewKey}");

            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('schema.title', $expectedTitle)
                ->assertJsonPath('schema.layout', 'scroll_view');
        }
    }
}

