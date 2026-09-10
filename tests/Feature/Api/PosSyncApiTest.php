<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\OrderPayment;
use App\Models\Plan;
use App\Models\PlatformBranding;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\TenantApiKey;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosSyncApiTest extends TestCase
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

        Plan::create([
            'name' => 'professional',
            'display_name' => 'Professional Plan',
            'price' => 29.00,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'features' => ['pos' => true, 'offline' => true],
            'limits' => ['products' => 10000, 'users' => 20],
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
            'name' => 'POS Register #1',
            'token' => 'zk_live_'.bin2hex(random_bytes(16)),
            'permissions' => ['*'],
            'active' => true,
        ]);
    }

    public function test_pos_login_endpoint_returns_token_and_tenant_metadata(): void
    {
        $response = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@metromart.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token', 'user', 'company', 'subscription'])
            ->assertJsonPath('company.name', 'Metro Supermarket');

        $token = $response->json('token');
        $this->assertDatabaseHas('tenant_api_keys', [
            'token' => $token,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_auth_branding_serves_inline_header_with_no_tagline(): void
    {
        $this->getJson('/api/v1/pos/auth/branding')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('platform_tagline', null)
            ->assertJsonPath('header_inline', true)
            ->assertJsonPath('show_tagline', false)
            ->assertJsonStructure(['platform_name', 'brand_logo_url']);
    }

    public function test_public_settings_returns_superadmin_platform_and_theme(): void
    {
        PlatformBranding::current()->update([
            'platform_name' => 'POS Systems',
            'auth_headline' => 'Sample platform headline',
            'auth_description' => 'Sample platform description',
            'primary_color' => '#F95700',
            'secondary_color' => '#0F172A',
            'accent_color' => '#FF7A00',
            'splash_bg_color' => '#0F172A',
            'auth_bg_color' => '#F8FAFC',
        ]);

        foreach ([
            '/api/v1/pos/auth/public-settings',
            '/api/v1/pos/public/settings',
            '/api/v1/pos/auth/branding',
        ] as $url) {
            $this->getJson($url)
                ->assertOk()
                ->assertJsonPath('platform.name', 'POS Systems')
                ->assertJsonPath('platform_name', 'POS Systems')
                ->assertJsonPath('platform_title', 'POS Systems')
                ->assertJsonPath('app_name', 'POS Systems')
                ->assertJsonPath('platform.headline', 'Sample platform headline')
                ->assertJsonPath('platform.description', 'Sample platform description')
                ->assertJsonStructure(['platform' => ['logo_url', 'favicon_url']])
                // The primary colour under every alias a client might parse.
                ->assertJsonPath('theme.primary_color', '#F95700')
                ->assertJsonPath('theme.primary', '#F95700')
                ->assertJsonPath('primary_color', '#F95700')
                ->assertJsonPath('brand_color', '#F95700')
                ->assertJsonPath('theme.secondary_color', '#0F172A')
                ->assertJsonPath('theme.accent_color', '#FF7A00')
                ->assertJsonPath('theme.splash_bg_color', '#0F172A')
                ->assertJsonPath('theme.auth_bg_color', '#F8FAFC');
        }
    }

    public function test_public_settings_falls_back_on_a_placeholder_white_primary(): void
    {
        PlatformBranding::current()->update(['primary_color' => '#ffffff']);

        $this->getJson('/api/v1/pos/auth/public-settings')
            ->assertOk()
            ->assertJsonPath('theme.primary_color', '#F95700');
    }

    public function test_saving_branding_forgets_the_public_settings_cache(): void
    {
        Cache::put('public_settings', ['stale' => true], 600);

        PlatformBranding::current()->update(['primary_color' => '#123456']);

        $this->assertFalse(Cache::has('public_settings'));

        $this->getJson('/api/v1/pos/auth/public-settings')
            ->assertOk()
            ->assertJsonPath('theme.primary', '#123456')
            ->assertJsonPath('brand_color', '#123456');
    }

    public function test_public_settings_headline_and_description_are_null_when_unset(): void
    {
        PlatformBranding::current()->update([
            'auth_headline' => null,
            'auth_description' => null,
        ]);

        $this->getJson('/api/v1/pos/auth/public-settings')
            ->assertOk()
            ->assertJsonPath('platform.headline', null)
            ->assertJsonPath('platform.description', null);
    }

    public function test_desktop_web_session_is_user_bound_and_one_time(): void
    {
        $login = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@metromart.com',
            'password' => 'secret123',
        ])->assertOk();

        $bridge = $this->withToken($login->json('token'))
            ->postJson('/api/v1/pos/auth/desktop-session', ['destination' => '/tenant/products'])
            ->assertOk()
            ->assertJsonStructure(['url']);

        $path = parse_url($bridge->json('url'), PHP_URL_PATH);
        $this->get($path)->assertRedirect('/tenant/products');
        $this->get($path)->assertStatus(401);
    }

    public function test_user_bound_desktop_api_obeys_role_permissions(): void
    {
        $cashier = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'cashier@metromart.com',
            'password' => Hash::make('secret123'),
            'role' => 'cashier',
        ]);

        $login = $this->postJson('/api/v1/pos/auth/login', [
            'email' => $cashier->email,
            'password' => 'secret123',
        ])->assertOk();

        $this->withToken($login->json('token'))
            ->getJson('/api/v1/pos/analytics')
            ->assertForbidden();
    }

    public function test_sync_batch_updates_existing_product_fields_but_never_stock(): void
    {
        $extId = (string) Str::uuid();
        $product = Product::create([
            'company_id' => $this->company->id,
            'external_id' => $extId,
            'name' => 'Original Name',
            'sale_price' => 5.00,
            'current_stock' => 42,
            'active' => true,
        ]);

        $response = $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', [
            'created_products' => [[
                'id' => $extId,
                'name' => 'Edited Offline',
                'price' => 7.50,
                'stock' => 999, // must be ignored — stock only moves via adjustments/sales
                'updated_at' => now()->addMinute()->toIso8601String(),
            ]],
        ]);

        $response->assertOk();
        $product->refresh();

        $this->assertSame('Edited Offline', $product->name);
        $this->assertEquals(7.50, $product->sale_price);
        $this->assertEquals(42, $product->current_stock);
        $this->assertSame(1, Product::where('company_id', $this->company->id)->where('external_id', $extId)->count());
        // Clean apply — nothing to report as a conflict.
        $response->assertJsonPath('conflicts', []);
    }

    public function test_sync_batch_reports_an_offline_edit_that_lost_the_last_write_wins_race(): void
    {
        $extId = (string) Str::uuid();
        $product = Product::create([
            'company_id' => $this->company->id, 'external_id' => $extId,
            'name' => 'Server Wins', 'sale_price' => 5, 'current_stock' => 3, 'active' => true,
        ]);
        // The server row was edited AFTER the moment the offline edit was made.
        $product->update(['name' => 'Edited On Web']);
        $staleEditAt = $product->updated_at->copy()->subMinutes(10)->toIso8601String();

        $response = $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', [
            'created_products' => [[
                'id' => $extId, 'name' => 'Edited Offline (stale)', 'price' => 9,
                'updated_at' => $staleEditAt,
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('conflicts.0.entity', 'product')
            ->assertJsonPath('conflicts.0.id', $extId)
            ->assertJsonPath('conflicts.0.reason', 'server_newer')
            ->assertJsonPath('conflicts.0.label', 'Edited On Web');

        // Server row untouched; the id_map still lets the client converge.
        $this->assertSame('Edited On Web', $product->fresh()->name);
        $response->assertJsonPath("id_map.$extId", (string) $product->id);
    }

    /**
     * Regression test: categories/brands/suppliers/units were only ever
     * pulled to the desktop, never pushed back — one created or edited on
     * the desktop app had no path to reach the server (or any other device)
     * at all. DesktopSyncClient now lists them in legacyPushableModels,
     * which routes them through this same sync-batch endpoint under
     * created_categories/created_brands/created_suppliers/created_units.
     */
    public function test_sync_batch_pushes_offline_categories_brands_suppliers_and_units(): void
    {
        $catExtId = (string) Str::uuid();
        $brandExtId = (string) Str::uuid();
        $supplierExtId = (string) Str::uuid();
        $unitExtId = (string) Str::uuid();

        $response = $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', [
            'created_categories' => [[
                'id' => $catExtId, 'name' => 'Snacks', 'color' => '#f59e0b', 'active' => true,
            ]],
            'created_brands' => [[
                'id' => $brandExtId, 'name' => 'Acme', 'active' => true,
            ]],
            'created_suppliers' => [[
                'id' => $supplierExtId, 'name' => 'Global Supply Co', 'phone' => '+1555000999', 'active' => true,
            ]],
            'created_units' => [[
                'id' => $unitExtId, 'name' => 'Box', 'abbreviation' => 'bx',
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('synced.categories.0', $catExtId)
            ->assertJsonPath('synced.brands.0', $brandExtId)
            ->assertJsonPath('synced.suppliers.0', $supplierExtId)
            ->assertJsonPath('synced.units.0', $unitExtId);

        $this->assertDatabaseHas('categories', ['company_id' => $this->company->id, 'external_id' => $catExtId, 'name' => 'Snacks']);
        $this->assertDatabaseHas('brands', ['company_id' => $this->company->id, 'external_id' => $brandExtId, 'name' => 'Acme']);
        $this->assertDatabaseHas('suppliers', ['company_id' => $this->company->id, 'external_id' => $supplierExtId, 'name' => 'Global Supply Co']);
        $this->assertDatabaseHas('units', ['company_id' => $this->company->id, 'external_id' => $unitExtId, 'name' => 'Box']);

        // Re-push the same batch (simulates a retried/duplicated sync cycle) — must upsert, not duplicate.
        $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', [
            'created_categories' => [['id' => $catExtId, 'name' => 'Snacks Renamed', 'active' => true]],
        ])->assertOk();

        $this->assertSame(1, Category::where('company_id', $this->company->id)->where('external_id', $catExtId)->count());
        $this->assertDatabaseHas('categories', ['external_id' => $catExtId, 'name' => 'Snacks Renamed']);
    }

    public function test_sync_batch_category_push_never_leaks_across_tenants(): void
    {
        $otherCompany = Company::create([
            'name' => 'Other Tenant', 'slug' => 'other-tenant', 'email' => 'owner@other.test',
            'country' => 'US', 'currency' => 'USD', 'currency_symbol' => '$', 'document' => 'US-000',
            'plan_name' => 'trial', 'expires_at' => now()->addDays(14),
        ]);
        $otherKey = TenantApiKey::create([
            'company_id' => $otherCompany->id, 'name' => 'Other Register',
            'token' => 'zk_live_'.bin2hex(random_bytes(16)), 'permissions' => ['*'], 'active' => true,
        ]);

        $extId = (string) Str::uuid();
        $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', [
            'created_categories' => [['id' => $extId, 'name' => 'Tenant A Category', 'active' => true]],
        ])->assertOk();

        $this->withToken($otherKey->token)->postJson('/api/v1/pos/sync-batch', [
            'created_categories' => [['id' => $extId, 'name' => 'Tenant B Category', 'active' => true]],
        ])->assertOk();

        $this->assertDatabaseHas('categories', [
            'company_id' => $this->company->id, 'external_id' => $extId, 'name' => 'Tenant A Category',
        ]);
        $this->assertDatabaseHas('categories', [
            'company_id' => $otherCompany->id, 'external_id' => $extId, 'name' => 'Tenant B Category',
        ]);
        $this->assertSame(2, Category::withoutGlobalScope('company')->where('external_id', $extId)->count());
    }

    public function test_inventory_adjustment_retry_is_idempotent(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Retry Safe Product',
            'sale_price' => 10,
            'current_stock' => 10,
            'active' => true,
        ]);
        $payload = ['inventory_adjustments' => [[
            'id' => 'adjustment-retry-001',
            'product_id' => $product->id,
            'type' => 'add',
            'quantity' => 5,
        ]]];

        $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', $payload)->assertOk();
        $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', $payload)->assertOk();

        $this->assertSame(15.0, (float) $product->fresh()->current_stock);
        $this->assertDatabaseCount('desktop_sync_receipts', 1);
    }

    public function test_sync_batch_processes_offline_deletes_and_returns_tombstones_in_pull(): void
    {
        $prodExt = (string) Str::uuid();
        $custExt = (string) Str::uuid();
        $product = Product::create([
            'company_id' => $this->company->id, 'external_id' => $prodExt,
            'name' => 'To Delete Offline', 'sale_price' => 3, 'current_stock' => 0, 'active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $this->company->id, 'external_id' => $custExt, 'name' => 'Ghost Customer',
        ]);

        $response = $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', [
            'deleted_products' => [['id' => $prodExt, 'deleted_at' => now()->addMinute()->toIso8601String()]],
            // bare-string form is also accepted
            'deleted_customers' => [$custExt],
        ]);

        $response->assertOk()
            ->assertJsonPath('deleted.products.0', $prodExt)
            ->assertJsonPath('deleted.customers.0', $custExt);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('sync_tombstones', [
            'company_id' => $this->company->id, 'entity' => 'product', 'external_id' => $prodExt,
        ]);

        // A delta-only client learns the rows are gone via sync-pull.
        $pull = $this->withToken($this->apiKey->token)->getJson('/api/v1/pos/sync-pull');
        $pull->assertOk()
            ->assertJsonPath('deleted_ids.products.0', $prodExt)
            ->assertJsonPath('deleted_ids.customers.0', $custExt);
    }

    public function test_sync_batch_offline_delete_is_idempotent_on_retry(): void
    {
        $ext = (string) Str::uuid();
        Category::create(['company_id' => $this->company->id, 'external_id' => $ext, 'name' => 'Doomed', 'active' => true]);

        $payload = ['deleted_categories' => [['id' => $ext, 'deleted_at' => now()->addMinute()->toIso8601String()]]];
        $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', $payload)->assertOk();
        // Re-push (a retried / duplicated sync cycle) — must not error and must not resurrect anything.
        $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', $payload)
            ->assertOk()
            ->assertJsonPath('deleted.categories.0', $ext);

        $this->assertSame(0, Category::withoutGlobalScope('company')->where('external_id', $ext)->count());
        $this->assertSame(1, DB::table('sync_tombstones')
            ->where('entity', 'category')->where('external_id', $ext)->count());
        $this->assertSame(1, DB::table('desktop_sync_receipts')
            ->where('operation_type', 'delete_category')->where('external_id', $ext)->count());
    }

    public function test_sync_batch_offline_delete_yields_to_a_newer_server_edit(): void
    {
        $ext = (string) Str::uuid();
        $product = Product::create([
            'company_id' => $this->company->id, 'external_id' => $ext,
            'name' => 'Contested', 'sale_price' => 1, 'current_stock' => 0, 'active' => true,
        ]);
        // Server edited the row AFTER the moment the offline delete was queued.
        $product->update(['name' => 'Edited On Web']);
        $offlineDeleteAt = $product->updated_at->copy()->subMinutes(5)->toIso8601String();

        $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', [
            'deleted_products' => [['id' => $ext, 'deleted_at' => $offlineDeleteAt, 'updated_at' => $offlineDeleteAt]],
        ])->assertOk()
            ->assertJsonMissingPath('deleted.products')
            ->assertJsonPath('conflicts.0.reason', 'deleted_offline_kept_on_server')
            ->assertJsonPath('conflicts.0.id', $ext);

        // Row survives; no tombstone — the client re-pulls it and converges.
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Edited On Web']);
        $this->assertSame(0, DB::table('sync_tombstones')
            ->where('entity', 'product')->where('external_id', $ext)->count());
    }

    public function test_sync_batch_returns_an_id_map_for_created_rows(): void
    {
        $ext = (string) Str::uuid();
        $response = $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', [
            'created_products' => [['id' => $ext, 'name' => 'Fresh Offline Product', 'price' => 9]],
        ]);

        $serverId = Product::withoutGlobalScope('company')->where('external_id', $ext)->value('id');
        $response->assertOk()->assertJsonPath("id_map.$ext", (string) $serverId);
    }

    public function test_sync_batch_offline_edit_of_a_web_created_row_matches_by_numeric_id(): void
    {
        // Created on the web — no external_id, so the desktop only knows it by
        // its numeric id (that's what sync-pull hands back as `id`).
        $product = Product::create([
            'company_id' => $this->company->id, 'name' => 'Web Product',
            'sale_price' => 4, 'current_stock' => 7, 'active' => true,
        ]);
        $category = Category::create([
            'company_id' => $this->company->id, 'name' => 'Web Category', 'active' => true,
        ]);

        $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', [
            'created_products' => [[
                'id' => (string) $product->id, 'name' => 'Renamed Offline', 'price' => 6,
                'updated_at' => now()->addMinute()->toIso8601String(),
            ]],
            'created_categories' => [[
                'id' => (string) $category->id, 'name' => 'Category Renamed Offline', 'active' => true,
                'updated_at' => now()->addMinute()->toIso8601String(),
            ]],
        ])->assertOk();

        // Edited in place — never duplicated, stock never touched.
        $this->assertSame(1, Product::withoutGlobalScope('company')->where('name', 'Renamed Offline')->count());
        $this->assertSame(1, Product::withoutGlobalScope('company')->where('company_id', $this->company->id)->count());
        $this->assertEquals(6, $product->fresh()->sale_price);
        $this->assertEquals(7, $product->fresh()->current_stock);
        $this->assertSame(1, Category::withoutGlobalScope('company')->where('name', 'Category Renamed Offline')->count());
        $this->assertSame(1, Category::withoutGlobalScope('company')->where('company_id', $this->company->id)->count());
    }

    public function test_sync_pull_entities_filter_returns_only_the_requested_slices(): void
    {
        Product::create(['company_id' => $this->company->id, 'name' => 'P1', 'sale_price' => 1, 'current_stock' => 0, 'active' => true]);
        Customer::create(['company_id' => $this->company->id, 'name' => 'C1']);

        $pull = $this->withToken($this->apiKey->token)->getJson('/api/v1/pos/sync-pull?entities=products');
        $pull->assertOk()
            ->assertJsonStructure(['success', 'server_time', 'company', 'products', 'counts', 'deleted_ids'])
            ->assertJsonMissing(['customers' => []])
            ->assertJsonCount(1, 'products');
        $this->assertArrayNotHasKey('customers', $pull->json());
    }

    public function test_sync_batch_replays_a_generic_queued_mutation_to_its_real_endpoint(): void
    {
        $key = (string) Str::uuid();

        $response = $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', [
            'mutations' => [[
                'idempotency_key' => $key,
                'op' => 'create',
                'entity' => 'tax_rule',
                'endpoint' => '/api/tenant/settings/tax-rules',
                'method' => 'POST',
                'payload' => ['name' => 'Offline VAT', 'rate' => 7.5],
                'client_updated_at' => now()->toIso8601String(),
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('mutations_applied', [$key])
            ->assertJsonPath('mutations_failed', []);

        $this->assertDatabaseHas('tax_rules', [
            'company_id' => $this->company->id,
            'tax_name' => 'Offline VAT',
        ]);
        $this->assertSame(1, DB::table('desktop_sync_receipts')
            ->where('operation_type', 'mutation')->where('external_id', $key)->count());
    }

    public function test_sync_batch_generic_mutation_is_idempotent_on_replay(): void
    {
        $key = (string) Str::uuid();
        $payload = [
            'mutations' => [[
                'idempotency_key' => $key,
                'endpoint' => '/api/tenant/settings/tax-rules',
                'method' => 'POST',
                'payload' => ['name' => 'Dedupe Tax', 'rate' => 3],
            ]],
        ];

        $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', $payload)
            ->assertOk()->assertJsonPath('mutations_applied', [$key]);
        $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', $payload)
            ->assertOk()->assertJsonPath('mutations_applied', [$key]);

        // Replayed twice, created once.
        $this->assertSame(1, DB::table('tax_rules')
            ->where('company_id', $this->company->id)->where('tax_name', 'Dedupe Tax')->count());
    }

    public function test_sync_batch_generic_mutation_failure_is_isolated_and_reported(): void
    {
        $goodKey = (string) Str::uuid();
        $badKey = (string) Str::uuid();

        $response = $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', [
            'mutations' => [
                [
                    'idempotency_key' => $badKey,
                    'endpoint' => '/api/tenant/settings/tax-rules',
                    'method' => 'POST',
                    'payload' => ['name' => 'No Rate Given'], // fails validation (422)
                ],
                [
                    'idempotency_key' => $goodKey,
                    'endpoint' => '/api/tenant/settings/tax-rules',
                    'method' => 'POST',
                    'payload' => ['name' => 'Valid Tax', 'rate' => 5],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('mutations_applied', [$goodKey])
            ->assertJsonPath('mutations_failed.0.idempotency_key', $badKey);

        // The good one landed; the bad one left no ledger row so it can be
        // retried once the client fixes it.
        $this->assertDatabaseHas('tax_rules', ['company_id' => $this->company->id, 'tax_name' => 'Valid Tax']);
        $this->assertDatabaseMissing('tax_rules', ['company_id' => $this->company->id, 'tax_name' => 'No Rate Given']);
        $this->assertSame(0, DB::table('desktop_sync_receipts')
            ->where('operation_type', 'mutation')->where('external_id', $badKey)->count());
    }

    public function test_sync_batch_refuses_to_replay_a_sync_endpoint(): void
    {
        $key = (string) Str::uuid();

        $this->withToken($this->apiKey->token)->postJson('/api/v1/pos/sync-batch', [
            'mutations' => [[
                'idempotency_key' => $key,
                'endpoint' => '/api/v1/pos/sync-batch',
                'method' => 'POST',
                'payload' => ['created_products' => [['id' => (string) Str::uuid(), 'name' => 'X', 'price' => 1]]],
            ]],
        ])->assertOk()
            ->assertJsonPath('mutations_applied', [])
            ->assertJsonPath('mutations_failed.0.reason', 'Endpoint is not replayable.');
    }

    public function test_pos_register_endpoint_creates_new_tenant_and_api_token(): void
    {
        $response = $this->postJson('/api/v1/pos/auth/register', [
            'store_name' => 'Sunset Cafe',
            'name' => 'Alice Green',
            'email' => 'alice@sunsetcafe.test',
            'password' => 'cafePass123',
            'currency' => 'EUR',
            'pos_mode' => 'restaurant',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token', 'user', 'company'])
            ->assertJsonPath('company.name', 'Sunset Cafe');

        $this->assertDatabaseHas('companies', ['name' => 'Sunset Cafe', 'currency' => 'EUR']);
        $this->assertDatabaseHas('users', ['email' => 'alice@sunsetcafe.test']);
    }

    public function test_pos_status_handshake_returns_company_metadata(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey->token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/pos/status');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'online')
            ->assertJsonPath('company.name', 'Metro Supermarket')
            ->assertJsonPath('company.currency', 'USD')
            ->assertJsonPath('features.offline_sync', true);
    }

    public function test_pos_sync_catalog_pull_returns_products_categories_and_customers(): void
    {
        $category = Category::create([
            'company_id' => $this->company->id,
            'name' => 'Beverages',
            'color' => '#10b981',
            'active' => true,
        ]);

        $product1 = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Organic Coffee',
            'barcode' => '8901234567890',
            'sku' => 'BEV-COF-01',
            'sale_price' => 4.50,
            'cost_price' => 2.00,
            'current_stock' => 100,
            'category_id' => $category->id,
            'active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Jane Doe',
            'phone' => '+1555000111',
            'email' => 'jane@example.com',
            'loyalty_points' => 25,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey->token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/pos/sync-catalog');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('counts.products', 1)
            ->assertJsonPath('counts.categories', 1)
            ->assertJsonPath('counts.customers', 1)
            ->assertJsonFragment([
                'name' => 'Organic Coffee',
                'barcode' => '8901234567890',
                'price' => 4.50,
            ]);
    }

    public function test_sync_catalog_pull_includes_sales_suppliers_brands_and_units(): void
    {
        $supplier = Supplier::create([
            'company_id' => $this->company->id,
            'name' => 'Acme Distribution',
            'active' => true,
        ]);
        $brand = Brand::create([
            'company_id' => $this->company->id,
            'name' => 'Acme Brand',
            'active' => true,
        ]);
        $unit = Unit::create([
            'company_id' => $this->company->id,
            'name' => 'Kilogram',
            'abbreviation' => 'kg',
        ]);
        $sale = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'SALE-0001',
            'total' => 25.00,
            'net_amount' => 25.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'items' => [['id' => 1, 'name' => 'Coffee', 'price' => 25, 'quantity' => 1]],
        ]);

        $response = $this->withToken($this->apiKey->token)->getJson('/api/v1/pos/sync-catalog');

        $response->assertOk()
            ->assertJsonPath('counts.sales', 1)
            ->assertJsonPath('counts.suppliers', 1)
            ->assertJsonPath('counts.brands', 1)
            ->assertJsonPath('counts.units', 1);

        // Sale/Supplier/Brand/Unit predate SyncableModel and only get an
        // external_id when a client supplies one, so the wire id falls back
        // to the numeric server id here — matches the existing products/
        // categories/customers fallback behavior in this same endpoint.
        $this->assertSame((string) $sale->id, $response->json('sales.0.id'));
        $this->assertSame((string) $supplier->id, $response->json('suppliers.0.id'));
        $this->assertSame((string) $brand->id, $response->json('brands.0.id'));
        $this->assertSame((string) $unit->id, $response->json('units.0.id'));
        $this->assertSame('SALE-0001', $response->json('sales.0.sale_number'));
    }

    public function test_pos_sync_sales_push_ingests_offline_sales_and_decrements_stock(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Artisan Bread',
            'barcode' => '112233445566',
            'sale_price' => 3.50,
            'current_stock' => 50,
            'active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Bob Smith',
            'phone' => '+1555222333',
            'loyalty_points' => 0,
        ]);

        $clientSaleUuid = Str::uuid()->toString();

        $payload = [
            'sales' => [
                [
                    'id' => $clientSaleUuid,
                    'order_number' => 'POS-998811',
                    'total' => 7.00,
                    'discount' => 0.00,
                    'payment_method' => 'cash',
                    'customer_id' => $customer->id,
                    'items' => [
                        [
                            'id' => $product->id,
                            'name' => 'Artisan Bread',
                            'price' => 3.50,
                            'quantity' => 2,
                        ],
                    ],
                    'createdAt' => now()->toIso8601String(),
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey->token,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/pos/sync-sales', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('synced_ids.0', $clientSaleUuid);

        $this->assertDatabaseHas('sales', [
            'company_id' => $this->company->id,
            'external_id' => $clientSaleUuid,
            'total' => 7.00,
            'payment_method' => 'cash',
            'customer_id' => $customer->id,
        ]);

        $product->refresh();
        $this->assertEquals(48, $product->current_stock);
    }

    public function test_pos_sync_sales_push_is_idempotent_on_retry(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Sparkling Water',
            'sale_price' => 2.00,
            'current_stock' => 20,
            'active' => true,
        ]);

        $clientSaleUuid = Str::uuid()->toString();

        $payload = [
            'sales' => [
                [
                    'id' => $clientSaleUuid,
                    'order_number' => 'POS-774411',
                    'total' => 4.00,
                    'discount' => 0.00,
                    'payment_method' => 'card',
                    'items' => [
                        [
                            'id' => $product->id,
                            'name' => 'Sparkling Water',
                            'price' => 2.00,
                            'quantity' => 2,
                        ],
                    ],
                ],
            ],
        ];

        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey->token,
        ])->postJson('/api/v1/pos/sync-sales', $payload)->assertStatus(200);

        $product->refresh();
        $this->assertEquals(18, $product->current_stock);
        $this->assertEquals(1, Sale::where('external_id', $clientSaleUuid)->count());

        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey->token,
        ])->postJson('/api/v1/pos/sync-sales', $payload);

        $response2->assertStatus(200)->assertJsonPath('synced_ids.0', $clientSaleUuid);

        $product->refresh();
        $this->assertEquals(18, $product->current_stock);
        $this->assertEquals(1, Sale::where('external_id', $clientSaleUuid)->count());
    }

    public function test_pos_sale_is_allowed_without_an_open_cash_register(): void
    {
        $saleUuid = (string) Str::uuid();

        $this->assertNull(CashRegister::openFor($this->company->id));

        $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->postJson('/api/v1/pos/sync-push', [
                'sales' => [[
                    'id' => $saleUuid,
                    'total' => 25,
                    'payment_method' => 'cash',
                    'items' => [['name' => 'Counter Sale', 'price' => 25, 'quantity' => 1]],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('synced_ids.0', $saleUuid);

        $sale = Sale::where('external_id', $saleUuid)->firstOrFail();
        $this->assertNull($sale->cash_register_id);
        $this->assertNull($sale->payments()->firstOrFail()->cash_register_id);
    }

    public function test_pos_sale_is_associated_when_a_cash_register_is_open(): void
    {
        $register = CashRegister::create([
            'company_id' => $this->company->id,
            'opened_by' => $this->user->id,
            'opening_balance' => 50,
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $saleUuid = (string) Str::uuid();

        $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->postJson('/api/v1/pos/sync-push', [
                'sales' => [[
                    'id' => $saleUuid,
                    'total' => 10,
                    'payment_method' => 'cash',
                    'items' => [['name' => 'Registered Sale', 'price' => 10, 'quantity' => 1]],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('synced_ids.0', $saleUuid);

        $sale = Sale::where('external_id', $saleUuid)->firstOrFail();
        $this->assertSame($register->id, $sale->cash_register_id);
        $this->assertSame($register->id, $sale->payments()->firstOrFail()->cash_register_id);
    }

    /**
     * Regression test: a synced sale's tax_rate/tax_name must be derived
     * from its line items' product tax_rate, not left at 0/null — otherwise
     * the printed receipt shows "Tax (0%)" even though tax_amount is
     * correct (see TaxEngineService::buildTaxSummaryFromRates).
     */
    public function test_pos_sync_sales_push_computes_tax_rate_and_breakdown_from_product_tax_rate(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Imported Cheese',
            'sale_price' => 10.00,
            'tax_rate' => 8.25,
            'current_stock' => 30,
            'active' => true,
        ]);

        $clientSaleUuid = Str::uuid()->toString();

        $payload = [
            'sales' => [
                [
                    'id' => $clientSaleUuid,
                    'order_number' => 'POS-TAX01',
                    'total' => 10.83,
                    'discount' => 0.00,
                    'tax_amount' => 0.83,
                    'payment_method' => 'cash',
                    'items' => [
                        [
                            'id' => $product->id,
                            'name' => 'Imported Cheese',
                            'price' => 10.00,
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey->token,
        ])->postJson('/api/v1/pos/sync-sales', $payload)->assertStatus(200);

        $sale = Sale::where('external_id', $clientSaleUuid)->firstOrFail();
        $this->assertEquals(8.25, (float) $sale->tax_rate);
        $this->assertNotEmpty($sale->tax_name);
        $this->assertNotEmpty($sale->tax_breakdown);
        $this->assertEquals(8.25, (float) $sale->tax_breakdown[0]['rate']);
    }

    /** Same fix, for the sale a converted quotation produces. */
    public function test_quotation_store_and_convert_compute_tax_from_product_tax_rate(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Consulting Hour',
            'sale_price' => 100.00,
            'tax_rate' => 18,
            'current_stock' => 999,
            'active' => true,
        ]);

        $storeResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey->token,
        ])->postJson('/api/v1/pos/quotations', [
            'customer_name' => 'Walk-in Client',
            'items' => [
                [
                    'id' => $product->id,
                    'product_id' => $product->id,
                    'name' => 'Consulting Hour',
                    'price' => 100.00,
                    'quantity' => 1,
                ],
            ],
        ]);

        $storeResponse->assertStatus(201);
        $this->assertEquals(18.0, (float) $storeResponse->json('quotation.tax_rate'));
        $this->assertEquals(18.0, (float) $storeResponse->json('quotation.tax'));

        $quoteId = $storeResponse->json('quotation.id');
        $quote = Sale::where('external_id', $quoteId)->firstOrFail();
        $this->assertNotEmpty($quote->tax_breakdown);
        $this->assertEquals(18.0, (float) $quote->tax_rate);

        $convertResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey->token,
        ])->postJson("/api/v1/pos/quotations/{$quoteId}/convert");

        $convertResponse->assertStatus(200);
        $sale = Sale::where('id', $convertResponse->json('sale.server_id'))->firstOrFail();
        $this->assertEquals(18.0, (float) $sale->tax_rate);
        $this->assertNotEmpty($sale->tax_breakdown);
    }

    public function test_inventory_management_endpoints(): void
    {
        // 1. Create Product
        $createRes = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->postJson('/api/v1/pos/inventory/product', [
                'name' => 'Granola Bar',
                'sale_price' => 2.50,
                'cost_price' => 1.00,
                'current_stock' => 50,
                'minimum_stock' => 10,
                'barcode' => '7788990011',
            ]);

        $createRes->assertStatus(200)->assertJsonPath('success', true);
        $productId = $createRes->json('product.id');

        // 2. Adjust Stock (+25)
        $adjustRes = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->postJson('/api/v1/pos/inventory/adjust', [
                'product_id' => $productId,
                'type' => 'add',
                'quantity' => 25,
                'reason' => 'Shipment received',
            ]);

        $adjustRes->assertStatus(200)->assertJsonPath('new_stock', 75);

        // 3. List Inventory
        $listRes = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->getJson('/api/v1/pos/inventory');

        $listRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('total_products', 1);
    }

    public function test_customer_ledger_and_payment_endpoints(): void
    {
        // 1. Create Customer
        $custRes = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->postJson('/api/v1/pos/customers', [
                'name' => 'Charlie Brown',
                'phone' => '+1555444555',
                'email' => 'charlie@peanuts.test',
            ]);

        $custRes->assertStatus(200)->assertJsonPath('success', true);
        $custId = $custRes->json('customer.id');

        // 2. Create a credit sale for this customer
        $sale = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'POS-CREDIT-01',
            'customer_id' => $custRes->json('customer.server_id'),
            'customer_name' => 'Charlie Brown',
            'total' => 100.00,
            'paid_amount' => 0.00,
            'due_amount' => 100.00,
            'payment_method' => 'credit',
            'status' => 'completed',
            'payment_status' => 'pending',
            'items' => [['name' => 'Bulk Tea', 'price' => 100, 'quantity' => 1]],
        ]);

        // 3. Check Ledger
        $ledgerRes = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->getJson("/api/v1/pos/customers/{$custId}/ledger");

        $ledgerRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('customer.balance_due', 100);

        // 4. Record Payment ($40)
        $payRes = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->postJson("/api/v1/pos/customers/{$custId}/payment", [
                'amount' => 40.00,
                'payment_method' => 'cash',
                'notes' => 'Partial cash settlement',
            ]);

        $payRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('new_balance_due', 60);

        $sale->refresh();
        $this->assertEquals(40.00, $sale->paid_amount);
        $this->assertEquals(60.00, $sale->due_amount);
    }

    public function test_sync_push_rejects_due_sale_without_customer(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->postJson('/api/v1/pos/sync-push', [
                'sales' => [[
                    'id' => (string) Str::uuid(),
                    'total' => 100.0,
                    'payment_method' => 'cash',
                    'paid_amount' => 40.0,
                    'items' => [['name' => 'Widget', 'price' => 100, 'quantity' => 1]],
                ]],
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertCount(0, $response->json('synced_ids'));
        $this->assertCount(1, $response->json('rejected'));
        $this->assertDatabaseMissing('sales', ['company_id' => $this->company->id, 'total' => 100.0]);
    }

    public function test_sync_push_partial_payment_with_customer_creates_ledger_entry(): void
    {
        $customer = Customer::create(['company_id' => $this->company->id, 'name' => 'Partial Pay Customer']);
        $saleUuid = (string) Str::uuid();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->postJson('/api/v1/pos/sync-push', [
                'sales' => [[
                    'id' => $saleUuid,
                    'total' => 500.0,
                    'payment_method' => 'cash',
                    'customer_id' => $customer->id,
                    'paid_amount' => 200.0,
                    'items' => [['name' => 'Bulk Order', 'price' => 500, 'quantity' => 1]],
                ]],
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertContains($saleUuid, $response->json('synced_ids'));

        $sale = Sale::where('external_id', $saleUuid)->firstOrFail();
        $this->assertEquals(200.0, (float) $sale->paid_amount);
        $this->assertEquals(300.0, (float) $sale->due_amount);
        $this->assertEquals('partially_paid', $sale->payment_status);
        $this->assertSame(1, OrderPayment::where('sale_id', $sale->id)->count());
        $this->assertEquals(300.0, (float) $customer->fresh()->due_balance);
        $this->assertSame(1, CustomerLedger::where('customer_id', $customer->id)->count());
    }

    public function test_sync_push_split_payment_creates_multiple_order_payments(): void
    {
        $customer = Customer::create(['company_id' => $this->company->id, 'name' => 'Split Pay Customer']);
        $saleUuid = (string) Str::uuid();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->postJson('/api/v1/pos/sync-push', [
                'sales' => [[
                    'id' => $saleUuid,
                    'total' => 500.0,
                    'payment_method' => 'split',
                    'customer_id' => $customer->id,
                    'payments' => [
                        ['payment_method' => 'cash', 'amount' => 200.0],
                        ['payment_method' => 'upi', 'amount' => 100.0],
                    ],
                    'items' => [['name' => 'Split Sale Item', 'price' => 500, 'quantity' => 1]],
                ]],
            ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        $sale = Sale::where('external_id', $saleUuid)->firstOrFail();
        $this->assertEquals(300.0, (float) $sale->paid_amount);
        $this->assertEquals(200.0, (float) $sale->due_amount);
        $this->assertSame(2, OrderPayment::where('sale_id', $sale->id)->count());
        $this->assertEquals(200.0, (float) $customer->fresh()->due_balance);
    }

    public function test_analytics_and_subscription_endpoints(): void
    {
        // Analytics
        $analyticsRes = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->getJson('/api/v1/pos/analytics');

        $analyticsRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'kpis' => [
                    'month_revenue', 'month_orders', 'prev_month_revenue', 'prev_month_orders',
                    'product_count', 'customer_count',
                ],
                'payment_breakdown',
                'revenue_trend',
                'monthly_activity' => [['month', 'year', 'completed', 'pending']],
                'popular_tags',
                'recent_transactions',
                'recent_customers',
            ]);
        // 9 months of activity buckets, oldest first, ending on the current month.
        $this->assertCount(9, $analyticsRes->json('monthly_activity'));
        $this->assertSame(now()->format('M'), $analyticsRes->json('monthly_activity.8.month'));

        // The dashboard "Filter" date range is honoured.
        $analyticsRes->assertJsonPath('range.key', 'month')
            ->assertJsonStructure(['range' => ['key', 'label', 'from', 'to'], 'kpis' => ['range_revenue', 'range_orders', 'prev_range_revenue', 'prev_range_orders']]);

        $today = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->getJson('/api/v1/pos/analytics?range=today')
            ->assertOk();
        $today->assertJsonPath('range.key', 'today')
            ->assertJsonPath('range.label', 'Today');
        $this->assertSame(now()->startOfDay()->toDateString(),
            Carbon::parse($today->json('range.from'))->toDateString());

        $custom = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->getJson('/api/v1/pos/analytics?range=custom&from=2026-01-01&to=2026-01-31')
            ->assertOk();
        $custom->assertJsonPath('range.key', 'custom');

        // Subscription
        $subRes = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->getJson('/api/v1/pos/subscription');

        $subRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('subscription.plan_name', 'trial');
    }

    public function test_quotations_sync_pull_and_batch_ingestion(): void
    {
        // 1. Create a server-side quotation
        $serverQuote = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'QUO-SRV-001',
            'operation_type' => 'quotation',
            'total' => 250.00,
            'subtotal' => 250.00,
            'payment_status' => 'pending',
            'status' => 'completed',
            'items' => [['name' => 'Premium Service', 'price' => 250, 'quantity' => 1]],
        ]);

        // 2. Pull catalog delta
        $catalogRes = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->getJson('/api/v1/pos/sync-catalog');

        $catalogRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['quotations', 'taxes']);

        $this->assertNotEmpty($catalogRes->json('quotations'));
        $this->assertEquals('QUO-SRV-001', $catalogRes->json('quotations.0.quote_number'));

        // 3. Batch push offline created quotation
        $offlineQuoteId = (string) Str::uuid();
        $batchRes = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->postJson('/api/v1/pos/sync-batch', [
                'sales' => [],
                'inventory_adjustments' => [],
                'customer_payments' => [],
                'created_products' => [],
                'created_customers' => [],
                'quotations' => [
                    [
                        'id' => $offlineQuoteId,
                        'quote_number' => 'QUO-OFFLINE-999',
                        'customer_name' => 'Offline Client',
                        'total' => 450.00,
                        'subtotal' => 450.00,
                        'discount' => 0.00,
                        'tax' => 0.00,
                        'notes' => 'Created during internet outage',
                        'items' => [
                            ['name' => 'Hardware Kit', 'price' => 450, 'qty' => 1],
                        ],
                    ],
                ],
            ]);

        $batchRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('synced.quotations.0', $offlineQuoteId);

        $this->assertDatabaseHas('sales', [
            'company_id' => $this->company->id,
            'operation_type' => 'quotation',
            'external_id' => $offlineQuoteId,
            'total' => 450.00,
        ]);
    }

    public function test_tax_rules_api_endpoints(): void
    {
        // 1. Store a new tax rule
        $storeRes = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->postJson('/api/v1/pos/taxes', [
                'name' => 'State Sales Tax',
                'rate' => 8.5,
                'is_default' => true,
                'active' => true,
            ]);

        $storeRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('tax.name', 'State Sales Tax')
            ->assertJsonPath('tax.rate', 8.5);

        // 2. Fetch all taxes
        $indexRes = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->getJson('/api/v1/pos/taxes');

        $indexRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertNotEmpty($indexRes->json('taxes'));
    }

    public function test_send_delivery_endpoint_for_whatsapp_and_email(): void
    {
        $sale = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'POS-DELIV-001',
            'customer_name' => 'Jane Smith',
            'total' => 85.00,
            'items' => [['name' => 'Organic Honey', 'price' => 85, 'quantity' => 1]],
        ]);

        // WhatsApp delivery dispatch
        $waRes = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->postJson('/api/v1/pos/send-delivery', [
                'type' => 'whatsapp',
                'document_type' => 'invoice',
                'document_id' => (string) $sale->id,
                'recipient' => '+15551234567',
                'custom_message' => 'Your receipt is ready',
            ]);

        $waRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['whatsapp_url', 'document_number']);
    }

    public function test_sale_and_quotation_pdf_endpoints(): void
    {
        $sale = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'POS-PDF-001',
            'customer_name' => 'John Doe',
            'total' => 120.00,
            'items' => [['name' => 'Widget', 'price' => 60, 'quantity' => 2, 'total' => 120]],
        ]);

        $quote = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'QUO-PDF-001',
            'customer_name' => 'Jane Doe',
            'operation_type' => 'quotation',
            'total' => 300.00,
            'items' => [['name' => 'Custom Service', 'price' => 300, 'quantity' => 1, 'total' => 300]],
        ]);

        $salePdfRes = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->get("/api/v1/pos/sales/{$sale->id}/pdf");

        $salePdfRes->assertStatus(200);
        $this->assertEquals('application/pdf', $salePdfRes->headers->get('Content-Type'));

        $quotePdfRes = $this->withHeaders(['Authorization' => 'Bearer '.$this->apiKey->token])
            ->get("/api/v1/pos/quotations/{$quote->id}/pdf");

        $quotePdfRes->assertStatus(200);
        $this->assertEquals('application/pdf', $quotePdfRes->headers->get('Content-Type'));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/v1/pos/status');

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }
}
