<?php

namespace Tests\Feature\Auth;

use App\Exceptions\DesktopLocalProvisioningException;
use App\Models\Company;
use App\Models\Configuration;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Services\Auth\DesktopAuthBootstrapService;
use App\Services\Auth\TenantAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DesktopAuthBootstrapServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeRemoteLoginResponse(): array
    {
        return [
            'success' => true,
            'token' => 'zk_live_devicetoken123',
            'user' => [
                'id' => 'usr_remote001',
                'name' => 'Remote Cashier',
                'login' => 'cashier@remote-store.com',
                'email' => 'cashier@remote-store.com',
                'role' => 'cashier',
                'company_id' => 'emp_remote001',
                'permissions' => [],
            ],
            'company' => [
                'id' => 'emp_remote001',
                'name' => 'Remote Store',
                'trade_name' => 'Remote Store',
                'slug' => 'remote-store',
                'currency' => 'USD',
                'currency_symbol' => '$',
                'tax_number' => '',
                'address' => '123 Main St',
                'phone' => '+15550001111',
                'plan_name' => 'professional',
                'expires_at' => now()->addDays(30)->toIso8601String(),
            ],
            'plan' => [
                'name' => 'professional',
                'display_name' => 'Professional Plan',
                'billing_cycle' => 'monthly',
                'duration_days' => 30,
                'price' => 29.00,
                'currency' => 'USD',
                'features' => ['pos' => true],
                'limits' => ['products' => 10000],
                'active' => true,
            ],
            'subscription' => ['plan_name' => 'professional', 'status' => 'active', 'expires_at' => null],
        ];
    }

    public function test_bootstrap_provisions_local_plan_company_and_user(): void
    {
        Http::fake(['*/api/v1/pos/auth/login' => Http::response($this->fakeRemoteLoginResponse())]);

        $user = app(DesktopAuthBootstrapService::class)->attemptOnlineBootstrap('cashier@remote-store.com', 'secret123');

        $this->assertNotNull($user);
        $this->assertSame('usr_remote001', $user->id);

        $company = Company::withoutGlobalScopes()->find('emp_remote001');
        $this->assertNotNull($company);
        $this->assertSame('Remote Store', $company->name);
        $this->assertSame('professional', $company->plan_name);

        $plan = Plan::withoutGlobalScopes()->find('professional');
        $this->assertNotNull($plan);
        $this->assertSame('Professional Plan', $plan->display_name);

        $config = Configuration::withoutGlobalScopes()
            ->where('company_id', 'emp_remote001')->where('key', 'desktop_sync.device_token')->value('value');
        $this->assertNotNull($config, 'Bootstrap must configure DesktopSyncClient with the device token.');
    }

    /**
     * Regression test for the reported bug: logging into an EXISTING cloud
     * account from a brand-new device showed no products, because bootstrap
     * only ever provisioned Company/Plan/User rows and left the catalog to
     * arrive later via the self-rescheduling background sync job (which may
     * not even be running yet at this point in the boot sequence). Bootstrap
     * must now pull the existing catalog synchronously before returning.
     */
    public function test_bootstrap_pulls_the_existing_cloud_catalog_immediately(): void
    {
        Http::fake([
            '*/api/v1/pos/auth/login' => Http::response($this->fakeRemoteLoginResponse()),
            '*/api/health' => Http::response(['ok' => true]),
            '*/api/v1/pos/sync-catalog*' => Http::response([
                'success' => true,
                'server_time' => now()->toIso8601String(),
                'products' => [
                    [
                        'id' => 'prod-existing-1',
                        'server_id' => 501,
                        'name' => 'Existing Cloud Product',
                        'barcode' => '111222333',
                        'sku' => 'SKU-1',
                        'price' => 19.99,
                        'cost_price' => 10,
                        'stock' => 42,
                        'min_stock' => 5,
                        'unit' => 'pcs',
                        'category_name' => 'General',
                        'active' => true,
                        'updated_at' => now()->toIso8601String(),
                    ],
                ],
                'categories' => [], 'customers' => [], 'suppliers' => [], 'brands' => [], 'units' => [], 'sales' => [],
            ]),
            '*/api/v1/pos/desktop-sync/pull*' => Http::response([
                'success' => true, 'server_time' => now()->toIso8601String(), 'data' => [],
            ]),
        ]);

        app(DesktopAuthBootstrapService::class)->attemptOnlineBootstrap('cashier@remote-store.com', 'secret123');

        $product = Product::withoutGlobalScopes()
            ->where('company_id', 'emp_remote001')
            ->where('external_id', 'prod-existing-1')
            ->first();

        $this->assertNotNull($product, 'The existing cloud product must be pulled to the local database during bootstrap.');
        $this->assertSame('Existing Cloud Product', $product->name);
        $this->assertSame(42.0, (float) $product->current_stock);
    }

    public function test_local_login_succeeds_after_bootstrap_using_the_same_password(): void
    {
        Http::fake(['*/api/v1/pos/auth/login' => Http::response($this->fakeRemoteLoginResponse())]);

        app(DesktopAuthBootstrapService::class)->attemptOnlineBootstrap('cashier@remote-store.com', 'secret123');

        $result = app(TenantAuthService::class)->login('cashier@remote-store.com', 'secret123');

        $this->assertSame('usr_remote001', $result['user']->id);
    }

    public function test_returns_null_when_the_server_is_unreachable(): void
    {
        Http::fake(['*/api/v1/pos/auth/login' => fn () => throw new ConnectionException('offline')]);

        $user = app(DesktopAuthBootstrapService::class)->attemptOnlineBootstrap('cashier@remote-store.com', 'secret123');

        $this->assertNull($user);
        $this->assertSame(0, Company::withoutGlobalScopes()->where('id', 'emp_remote001')->count());
    }

    public function test_returns_null_on_invalid_remote_credentials(): void
    {
        Http::fake(['*/api/v1/pos/auth/login' => Http::response(['success' => false, 'error' => 'Invalid email/login or password.'], 401)]);

        $user = app(DesktopAuthBootstrapService::class)->attemptOnlineBootstrap('cashier@remote-store.com', 'wrong-password');

        $this->assertNull($user);
    }

    public function test_repeated_bootstrap_updates_the_same_local_rows_without_duplicating(): void
    {
        Http::fake(['*/api/v1/pos/auth/login' => Http::response($this->fakeRemoteLoginResponse())]);

        app(DesktopAuthBootstrapService::class)->attemptOnlineBootstrap('cashier@remote-store.com', 'secret123');
        app(DesktopAuthBootstrapService::class)->attemptOnlineBootstrap('cashier@remote-store.com', 'secret123');

        $this->assertSame(1, Company::withoutGlobalScopes()->where('id', 'emp_remote001')->count());
        $this->assertSame(1, User::withoutGlobalScopes()->where('id', 'usr_remote001')->count());
    }

    public function test_desktop_registration_creates_remote_account_and_local_offline_mirror(): void
    {
        Http::fake(['*/api/v1/pos/auth/register' => Http::response($this->fakeRemoteLoginResponse(), 201)]);

        $user = app(DesktopAuthBootstrapService::class)->registerOnline([
            'store_name' => 'Remote Store',
            'owner_name' => 'Remote Cashier',
            'email' => 'cashier@remote-store.com',
            'password' => 'secret123',
            'pos_mode' => 'general',
        ]);

        $this->assertSame('usr_remote001', $user->id);
        $this->assertNotNull(Company::withoutGlobalScopes()->find('emp_remote001'));
        $this->assertSame(
            'usr_remote001',
            app(TenantAuthService::class)->login('cashier@remote-store.com', 'secret123')['user']->id
        );

        Http::assertSent(fn ($request) => $request->url() === 'https://saas.zoomnearby.com/api/v1/pos/auth/register'
            && $request['name'] === 'Remote Cashier');
    }

    public function test_bootstrap_throws_a_distinct_exception_when_local_provisioning_fails_after_valid_credentials(): void
    {
        // The server confirms the credentials are correct, but local
        // provisioning still fails (schema present, but this response is
        // missing the company id entirely) — the caller must be able to
        // tell this apart from "wrong password", since retyping the same
        // correct password will never fix it.
        $response = $this->fakeRemoteLoginResponse();
        unset($response['company']['id']);
        Http::fake(['*/api/v1/pos/auth/login' => Http::response($response)]);

        $this->expectException(DesktopLocalProvisioningException::class);

        app(DesktopAuthBootstrapService::class)->attemptOnlineBootstrap('cashier@remote-store.com', 'secret123');
    }

    public function test_desktop_registration_surfaces_server_validation_error(): void
    {
        Http::fake(['*/api/v1/pos/auth/register' => Http::response([
            'success' => false,
            'error' => 'Validation error during tenant registration.',
            'details' => ['email' => ['The email has already been taken.']],
        ], 422)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The email has already been taken.');

        app(DesktopAuthBootstrapService::class)->registerOnline([
            'store_name' => 'Remote Store',
            'owner_name' => 'Remote Cashier',
            'email' => 'cashier@remote-store.com',
            'password' => 'secret123',
        ]);
    }
}
