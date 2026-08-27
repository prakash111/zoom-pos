<?php

namespace Tests\Feature\Api;

use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Plan;
use App\Models\TenantApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosDesktopSyncApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected TenantApiKey $apiKey;

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
            'slug' => 'metro-mart',
            'email' => 'pos@metromart.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'document' => 'US-123456789',
            'plan_name' => 'trial',
            'expires_at' => now()->addDays(14),
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'admin@metromart.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $this->apiKey = TenantApiKey::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Desktop Client',
            'token' => 'zk_live_'.bin2hex(random_bytes(16)),
            'permissions' => ['*'],
            'active' => true,
        ]);
    }

    protected function withApiToken()
    {
        return $this->withToken($this->apiKey->token);
    }

    public function test_push_creates_cash_register_and_child_transaction_with_relationship_preserved(): void
    {
        $registerExternalId = 'reg-'.\Illuminate\Support\Str::uuid();
        $txnExternalId = 'txn-'.\Illuminate\Support\Str::uuid();

        $response = $this->withApiToken()->postJson('/api/v1/pos/desktop-sync/push', [
            'cash_registers' => [[
                'external_id' => $registerExternalId,
                'terminal_id' => 'POS-1',
                'opening_balance' => 100,
                'status' => 'open',
                'opened_at' => now()->toIso8601String(),
            ]],
            'cash_register_transactions' => [[
                'external_id' => $txnExternalId,
                'cash_register_id' => $registerExternalId,
                'type' => 'cash_in',
                'category' => 'suprimento',
                'amount' => 50,
                'balance_before' => 100,
                'balance_after' => 150,
            ]],
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $register = CashRegister::where('company_id', $this->company->id)
            ->where('external_id', $registerExternalId)->firstOrFail();

        $this->assertDatabaseHas('cash_register_transactions', [
            'external_id' => $txnExternalId,
            'cash_register_id' => $register->id,
            'amount' => 50,
        ]);
    }

    public function test_push_is_idempotent_on_retry(): void
    {
        $externalId = 'reg-retry-001';
        $payload = ['cash_registers' => [[
            'external_id' => $externalId,
            'terminal_id' => 'POS-1',
            'opening_balance' => 100,
            'status' => 'open',
            'opened_at' => now()->toIso8601String(),
        ]]];

        $this->withApiToken()->postJson('/api/v1/pos/desktop-sync/push', $payload)->assertOk();
        $this->withApiToken()->postJson('/api/v1/pos/desktop-sync/push', $payload)->assertOk();

        $this->assertSame(1, CashRegister::where('company_id', $this->company->id)
            ->where('external_id', $externalId)->count());
    }

    public function test_pull_returns_web_created_rows_with_generated_external_id(): void
    {
        $register = CashRegister::create([
            'company_id' => $this->company->id,
            'terminal_id' => 'POS-2',
            'opening_balance' => 200,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->assertNotEmpty($register->external_id);

        $response = $this->withApiToken()->getJson('/api/v1/pos/desktop-sync/pull');

        $response->assertOk()->assertJsonPath('success', true);
        $ids = collect($response->json('data.cash_registers'))->pluck('external_id');
        $this->assertContains($register->external_id, $ids);
    }

    public function test_consignment_push_replaces_nested_items_and_recalculates_totals(): void
    {
        $extId = 'consign-001';

        $this->withApiToken()->postJson('/api/v1/pos/desktop-sync/push', [
            'consignments' => [[
                'external_id' => $extId,
                'consignment_number' => 'CON-0001',
                'customer_name' => 'Downtown Kiosk',
                'status' => 'dispatched',
                'items' => [[
                    'product_id' => null,
                    'product_name' => 'Widget',
                    'dispatched_quantity' => 10,
                    'sold_quantity' => 4,
                    'returned_quantity' => 0,
                    'unit_price' => 5,
                ]],
            ]],
        ])->assertOk();

        $consignment = \App\Models\Consignment::where('company_id', $this->company->id)
            ->where('external_id', $extId)->firstOrFail();

        $this->assertSame(1, $consignment->items()->count());
        $this->assertEqualsWithDelta(50.00, (float) $consignment->total_dispatched_amount, 0.01);
        $this->assertEqualsWithDelta(20.00, (float) $consignment->total_sold_amount, 0.01);
    }
}
