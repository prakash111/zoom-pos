<?php

namespace Tests\Feature;

use App\Livewire\Auth\PlatformLogin;
use App\Livewire\Auth\TenantLogin;
use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_platform_admin_can_sign_in_and_reach_the_dashboard(): void
    {
        PlatformAdmin::create([
            'name' => 'Owner', 'email' => 'owner@example.com',
            'password' => Hash::make('password123'), 'role' => 'super_admin', 'status' => 'active',
        ]);

        Livewire::test(PlatformLogin::class)
            ->set('email', 'owner@example.com')
            ->set('password', 'password123')
            ->call('login')
            ->assertRedirect('/superadmin');

        $this->assertTrue(Auth::guard('platform_web')->check());
    }

    public function test_platform_admin_wrong_password_shows_error_and_does_not_authenticate(): void
    {
        PlatformAdmin::create([
            'name' => 'Owner', 'email' => 'owner@example.com',
            'password' => Hash::make('password123'), 'role' => 'super_admin', 'status' => 'active',
        ]);

        Livewire::test(PlatformLogin::class)
            ->set('email', 'owner@example.com')
            ->set('password', 'wrong')
            ->call('login')
            ->assertSet('error', 'Invalid credentials.');

        $this->assertFalse(Auth::guard('platform_web')->check());
    }

    public function test_tenant_user_can_sign_in_and_reach_the_dashboard(): void
    {
        $company = Company::create(['name' => 'Acme Inc']);
        User::create([
            'company_id' => $company->id, 'name' => 'Jane', 'login' => 'jane', 'email' => 'jane@acme.test',
            'password' => Hash::make('secret1234'), 'role' => 'administrator', 'status' => 'approved',
        ]);

        Livewire::test(TenantLogin::class)
            ->set('identifier', 'jane')
            ->set('password', 'secret1234')
            ->call('login')
            ->assertRedirect('/tenant');

        $this->assertTrue(Auth::guard('web')->check());
    }

    public function test_demo_mode_prefills_and_renders_quick_credentials(): void
    {
        config(['app.demo_mode' => true]);

        // 1. Tenant Login Component & View in Demo Mode
        Livewire::test(TenantLogin::class)
            ->assertSet('identifier', 'demo@zoomnearby.com')
            ->assertSet('password', 'demo1234')
            ->call('fillDemo', 'cashier')
            ->assertSet('identifier', 'cashier@zoommarket.test')
            ->assertSet('password', 'password123')
            ->call('fillDemo', 'manager')
            ->assertSet('identifier', 'demo@zoomnearby.com')
            ->assertSet('password', 'demo1234');

        $tenantResponse = $this->get(route('tenant.login'));
        $tenantResponse->assertOk();
        $tenantResponse->assertSee('Demo Mode Active');
        $tenantResponse->assertSee('https://web.zoomnearby.com');
        $tenantResponse->assertSee('Flutter Web Demo');
        // Consolidated demo module switcher — the 5 store-type chips.
        $tenantResponse->assertSee('Cafe &amp; Restaurant', false);
        $tenantResponse->assertSee('Pharmacy');
        $tenantResponse->assertSee(url('/demo-login/retail'));
        $tenantResponse->assertSee(url('/demo-login/salon'));

        // 2. Super Admin Login Component & View in Demo Mode
        Livewire::test(PlatformLogin::class)
            ->assertSet('email', 'superadmin@gmail.com')
            ->assertSet('password', 'password123');

        $platformResponse = $this->get(route('superadmin.login'));
        $platformResponse->assertOk();
        $platformResponse->assertSee('Demo Mode Active');
        $platformResponse->assertSee('Super Administrator');
    }

    public function test_production_mode_disables_demo_prefills_and_badges(): void
    {
        config(['app.demo_mode' => false]);

        Livewire::test(TenantLogin::class)
            ->assertSet('identifier', '')
            ->assertSet('password', '');

        $tenantResponse = $this->get(route('tenant.login'));
        $tenantResponse->assertOk();
        $tenantResponse->assertDontSee('Demo Mode Active');
        $tenantResponse->assertDontSee('Quick Demo Credentials');
        $tenantResponse->assertSee('https://web.zoomnearby.com');

        Livewire::test(PlatformLogin::class)
            ->assertSet('email', '')
            ->assertSet('password', '');

        $platformResponse = $this->get(route('superadmin.login'));
        $platformResponse->assertOk();
        $platformResponse->assertDontSee('Demo Mode Active');
    }
}
