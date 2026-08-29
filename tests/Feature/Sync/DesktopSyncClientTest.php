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

    /**
     * Regression test: a category created on the desktop app used to have
     * nowhere to go — legacyPushableModels only listed products/customers,
     * so DesktopSyncClient never pushed categories/brands/suppliers/units
     * at all, only ever pulled them. This asserts the outbound sync-batch
     * request actually carries the dirty category and that it's marked
     * synced locally once the server acknowledges it.
     */
    public function test_online_cycle_pushes_dirty_category_and_marks_it_synced(): void
    {
        $client = $this->client();
        $client->configure('https://saas.zoomnearby.test', 'device-token-123');

        $category = \App\Models\Category::create([
            'company_id' => $this->company->id,
            'external_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Made On Desktop',
            'active' => true,
        ]);
        $this->assertNull($category->fresh()->synced_at);

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
        $this->assertNotNull($category->fresh()->synced_at);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sync-batch')
            && ! empty($request->data()['created_categories'])
            && $request->data()['created_categories'][0]['name'] === 'Made On Desktop');
    }

    /**
     * Regression test: business settings edited on the web (Settings page —
     * address, currency, receipt terms, etc.) used to only ever be written
     * to the local Company row once, at first-bootstrap time. Any later edit
     * on the web (or another device) never reached this device again. The
     * catalog pull now carries a 'company' block on every cycle and applies
     * it to the local row (deliberately excludes plan/billing fields).
     */
    public function test_pulled_company_settings_are_applied_to_the_local_company_row(): void
    {
        $client = $this->client();
        $client->configure('https://saas.zoomnearby.test', 'device-token-123');

        $this->assertNotSame('Renamed On Web', $this->company->fresh()->trade_name);

        Http::fake([
            '*/api/health' => Http::response(['ok' => true]),
            '*/api/v1/pos/sync-batch' => Http::response(['success' => true]),
            '*/api/v1/pos/desktop-sync/push' => Http::response(['success' => true, 'synced' => []]),
            '*/api/v1/pos/sync-catalog*' => Http::response([
                'success' => true, 'server_time' => now()->toIso8601String(),
                'products' => [], 'categories' => [], 'customers' => [], 'suppliers' => [], 'brands' => [], 'units' => [], 'sales' => [],
                'company' => [
                    'trade_name' => 'Renamed On Web',
                    'address' => '456 New Address Ave',
                    'currency_symbol' => '€',
                ],
            ]),
            '*/api/v1/pos/desktop-sync/pull*' => Http::response([
                'success' => true, 'server_time' => now()->toIso8601String(), 'data' => [],
            ]),
        ]);

        $client->runCycle();

        $fresh = $this->company->fresh();
        $this->assertSame('Renamed On Web', $fresh->trade_name);
        $this->assertSame('456 New Address Ave', $fresh->address);
        $this->assertSame('€', $fresh->currency_symbol);
    }

    /**
     * Regression test: PosSyncApiController::syncPull() names a product's
     * price/stock fields differently on the wire ("price"/"stock"/"min_stock")
     * than the local Eloquent columns ("sale_price"/"current_stock"/
     * "minimum_stock"). Without translating them, every pulled product used
     * to land locally with the right name but a zeroed price and stock.
     */
    public function test_pulled_product_price_and_stock_fields_are_mapped_to_local_columns(): void
    {
        $client = $this->client();
        $client->configure('https://saas.zoomnearby.test', 'device-token-123');

        Http::fake([
            '*/api/health' => Http::response(['ok' => true]),
            '*/api/v1/pos/sync-batch' => Http::response(['success' => true]),
            '*/api/v1/pos/desktop-sync/push' => Http::response(['success' => true, 'synced' => []]),
            '*/api/v1/pos/sync-catalog*' => Http::response([
                'success' => true, 'server_time' => now()->toIso8601String(),
                'products' => [[
                    'id' => 'prod-1', 'server_id' => 1, 'name' => 'Cloud Product', 'barcode' => '123',
                    'price' => 25.5, 'cost_price' => 12, 'stock' => 8, 'min_stock' => 2, 'unit' => 'pcs',
                    'category_name' => 'General', 'active' => true, 'updated_at' => now()->toIso8601String(),
                ]],
                'categories' => [], 'customers' => [], 'suppliers' => [], 'brands' => [], 'units' => [], 'sales' => [], 'quotations' => [],
            ]),
            '*/api/v1/pos/desktop-sync/pull*' => Http::response([
                'success' => true, 'server_time' => now()->toIso8601String(), 'data' => [],
            ]),
        ]);

        $client->runCycle();

        $product = Product::withoutGlobalScope('company')->where('company_id', $this->company->id)
            ->where('external_id', 'prod-1')->first();

        $this->assertNotNull($product);
        $this->assertSame(25.5, (float) $product->sale_price);
        $this->assertSame(8.0, (float) $product->current_stock);
        $this->assertSame(2.0, (float) $product->minimum_stock);
    }

    /**
     * Regression test: quotations were never pulled to the desktop at all
     * (missing from legacyPullableModels), and the server names a quotation's
     * number/tax fields differently on the wire ("quote_number"/"tax") than
     * the local Sale columns ("sale_number"/"tax_amount").
     */
    public function test_pulled_quotations_are_mapped_and_flagged_as_quotations(): void
    {
        $client = $this->client();
        $client->configure('https://saas.zoomnearby.test', 'device-token-123');

        Http::fake([
            '*/api/health' => Http::response(['ok' => true]),
            '*/api/v1/pos/sync-batch' => Http::response(['success' => true]),
            '*/api/v1/pos/desktop-sync/push' => Http::response(['success' => true, 'synced' => []]),
            '*/api/v1/pos/sync-catalog*' => Http::response([
                'success' => true, 'server_time' => now()->toIso8601String(),
                'products' => [], 'categories' => [], 'customers' => [], 'suppliers' => [], 'brands' => [], 'units' => [], 'sales' => [],
                'quotations' => [[
                    'id' => 'quo-1', 'server_id' => 9, 'quote_number' => 'QUO-0001', 'customer_name' => 'Jane',
                    'items' => [], 'total' => 100, 'tax' => 8.5, 'discount' => 0, 'status' => 'draft',
                    'updated_at' => now()->toIso8601String(), 'createdAt' => now()->toIso8601String(),
                ]],
            ]),
            '*/api/v1/pos/desktop-sync/pull*' => Http::response([
                'success' => true, 'server_time' => now()->toIso8601String(), 'data' => [],
            ]),
        ]);

        $client->runCycle();

        $quote = \App\Models\Sale::withoutGlobalScope('company')->where('company_id', $this->company->id)
            ->where('external_id', 'quo-1')->first();

        $this->assertNotNull($quote, 'The pulled quotation must be saved locally.');
        $this->assertSame('QUO-0001', $quote->sale_number);
        $this->assertSame(8.5, (float) $quote->tax_amount);
        $this->assertSame('quotation', $quote->operation_type);
    }
}
