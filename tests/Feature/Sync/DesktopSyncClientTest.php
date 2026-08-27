<?php

namespace Tests\Feature\Sync;

use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Services\Sync\DesktopSyncClient;
use App\Services\Sync\DesktopSyncEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DesktopSyncClientTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'trial', 'display_name' => 'Free Trial', 'price' => 0, 'currency' => 'USD',
            'billing_cycle' => 'monthly', 'duration_days' => 14,
            'features' => ['pos' => true], 'limits' => ['products' => 500, 'users' => 5], 'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Desktop Test Co', 'slug' => 'desktop-test-co', 'email' => 'owner@desktoptest.com',
            'country' => 'US', 'currency' => 'USD', 'currency_symbol' => '$', 'document' => 'US-999',
            'plan_name' => 'trial', 'expires_at' => now()->addDays(14),
        ]);
    }

    protected function client(): DesktopSyncClient
    {
        return new DesktopSyncClient($this->company, app(DesktopSyncEngine::class));
    }

    public function test_unconfigured_client_reports_offline_without_making_requests(): void
    {
        Http::fake();

        $result = $this->client()->runCycle();

        $this->assertSame('offline', $result['status']);
        Http::assertNothingSent();
    }

    public function test_offline_health_check_failure_reports_offline_and_skips_sync(): void
    {
        $client = $this->client();
        $client->configure('https://saas.zoomnearby.test', 'device-token-123');

        Http::fake(['*/api/health' => Http::response('', 500)]);

        $result = $client->runCycle();

        $this->assertSame('offline', $result['status']);
        $this->assertSame('offline', $client->status());
    }

    public function test_online_cycle_pushes_dirty_generic_row_and_marks_it_synced(): void
    {
        $client = $this->client();
        $client->configure('https://saas.zoomnearby.test', 'device-token-123');

        $register = CashRegister::create([
            'company_id' => $this->company->id,
            'terminal_id' => 'POS-1',
            'opening_balance' => 100,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $this->assertTrue($register->fresh()->synced_at === null);

        Http::fake([
            '*/api/health' => Http::response(['ok' => true]),
            '*/api/v1/pos/sync-batch' => Http::response(['success' => true]),
            '*/api/v1/pos/desktop-sync/push' => Http::response(['success' => true, 'synced' => []]),
            '*/api/v1/pos/sync-catalog*' => Http::response([
                'success' => true, 'server_time' => now()->toIso8601String(),
                'products' => [], 'categories' => [], 'customers' => [], 'suppliers' => [], 'brands' => [], 'units' => [], 'sales' => [],
            ]),
            '*/api/v1/pos/desktop-sync/pull*' => Http::response([
                'success' => true, 'server_time' => now()->toIso8601String(), 'data' => [],
            ]),
        ]);

        $result = $client->runCycle();

        $this->assertSame('synced', $result['status']);
        $this->assertSame('synced', $client->status());
        $this->assertNotNull($register->fresh()->synced_at);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'desktop-sync/push')
            && ! empty($request->data()['cash_registers']));
    }

    public function test_a_failed_push_leaves_the_row_dirty_for_the_next_cycle(): void
    {
        $client = $this->client();
        $client->configure('https://saas.zoomnearby.test', 'device-token-123');

        $product = Product::create([
            'company_id' => $this->company->id,
            'external_id' => 'prod-ext-1',
            'name' => 'Dirty Product',
            'sale_price' => 9.99,
            'current_stock' => 5,
            'active' => true,
        ]);

        Http::fake([
            '*/api/health' => Http::response(['ok' => true]),
            '*/api/v1/pos/sync-batch' => Http::response(['success' => false], 500),
        ]);

        $result = $client->runCycle();

        $this->assertSame('online', $result['status']);
        $this->assertArrayHasKey('error', $result);
        $this->assertNull($product->fresh()->synced_at);
    }
}
