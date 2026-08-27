<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\Configuration;
use App\Models\Plan;
use App\Models\User;
use App\Services\Auth\DesktopAuthBootstrapService;
use App\Services\Auth\TenantAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_local_login_succeeds_after_bootstrap_using_the_same_password(): void
    {
        Http::fake(['*/api/v1/pos/auth/login' => Http::response($this->fakeRemoteLoginResponse())]);

        app(DesktopAuthBootstrapService::class)->attemptOnlineBootstrap('cashier@remote-store.com', 'secret123');

        $result = app(TenantAuthService::class)->login('cashier@remote-store.com', 'secret123');

        $this->assertSame('usr_remote001', $result['user']->id);
    }

    public function test_returns_null_when_the_server_is_unreachable(): void
    {
        Http::fake(['*/api/v1/pos/auth/login' => fn () => throw new \Illuminate\Http\Client\ConnectionException('offline')]);

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
}
