<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\Sdui\SchemaResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_sdui_plug_and_play_future_module_view_generates_dynamically(): void
    {
        // Pharmacy module
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/pharmacy');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Pharmacy POS');

        // Arbitrary future vertical without compile touchpoints
        $futureResponse = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/salon-spa');

        $futureResponse->assertOk()
            ->assertJsonPath('success', true);
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

    public function test_bootstrap_delivers_accordion_navigation_and_target_endpoints(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/app/bootstrap');

        $response->assertOk();
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
