<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('nav.hidden_tiles', [])
            ->assertJsonPath('nav.section_order', [])
            ->assertJsonPath('config.pos_mode', 'general')
            ->assertJsonPath('config.currency_symbol', '$')
            ->assertJsonStructure(['translations', 'messages']);
    }

    public function test_nav_config_round_trips_through_update_and_bootstrap(): void
    {
        $token = $this->token();

        $this->withToken($token)->postJson('/api/v1/pos/settings/nav-config', [
            'hidden_tiles' => ['quotations', 'consignments'],
            'section_order' => ['financial_management', 'cashier_sales'],
        ])->assertOk()
            ->assertJsonPath('nav.hidden_tiles', ['quotations', 'consignments'])
            ->assertJsonPath('nav.section_order', ['financial_management', 'cashier_sales']);

        $this->withToken($token)->getJson('/api/v1/pos/app/bootstrap?locale=en')
            ->assertOk()
            ->assertJsonPath('nav.hidden_tiles', ['quotations', 'consignments'])
            ->assertJsonPath('nav.section_order', ['financial_management', 'cashier_sales']);

        $this->withToken($token)->getJson('/api/v1/pos/settings')
            ->assertOk()
            ->assertJsonPath('nav.hidden_tiles', ['quotations', 'consignments'])
            ->assertJsonPath('nav.section_order', ['financial_management', 'cashier_sales']);
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
            'hidden_tiles' => ['pos'],
        ])->assertForbidden();
    }
}
