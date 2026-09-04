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

    public function test_every_settings_panel_returns_a_valid_versioned_sdui_tree(): void
    {
        $token = $this->token();

        foreach (['mode', 'profile', 'receipts', 'financial', 'taxes', 'api', 'navigation'] as $panel) {
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
}
