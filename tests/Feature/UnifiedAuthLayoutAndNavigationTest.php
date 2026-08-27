<?php

namespace Tests\Feature;

use App\Livewire\Auth\PlatformLogin;
use App\Livewire\Auth\TenantLogin;
use App\Livewire\Auth\TenantRegister;
use App\Models\PlatformBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnifiedAuthLayoutAndNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
    }

    protected function tearDown(): void
    {
        if (file_exists(storage_path('installed'))) {
            @unlink(storage_path('installed'));
        }
        parent::tearDown();
    }

    public function test_login_and_register_routes_redirect_to_tenant_auth(): void
    {
        $this->get('/login')->assertRedirect(route('tenant.login'));
        $this->get('/register')->assertRedirect(route('tenant.register'));
    }

    public function test_tenant_login_screen_renders_with_unified_layout_and_navigation(): void
    {
        $branding = PlatformBranding::current();
        $branding->update([
            'platform_name' => 'Zenith POS & Inventory',
            'landing_primary_color' => '#10b981',
            'landing_accent_color' => '#d7f24e',
        ]);

        $response = $this->get(route('tenant.login'));
        $response->assertOk();

        // Verify "Back to Home" link and Brand Logo link
        $response->assertSee('Back to Home');
        $response->assertSee(route('home'));

        // Verify Reciprocal switching link to Register
        $response->assertSee('Register your Store now');
        $response->assertSee(route('tenant.register'));

        // Verify Livewire component state
        Livewire::test(TenantLogin::class)
            ->assertSee('Store Sign In')
            ->assertSee('Email or Username')
            ->assertSee('Password')
            ->assertSee('Sign In to Store Register');
    }

    public function test_tenant_register_screen_renders_with_unified_layout_and_navigation(): void
    {
        $response = $this->get(route('tenant.register'));
        $response->assertOk();

        // Verify "Back to Home" navigation
        $response->assertSee('Back to Home');
        $response->assertSee(route('home'));

        // Verify Reciprocal switching link to Login
        $response->assertSee('Already have an active store account?');
        $response->assertSee('Log In to your Store');
        $response->assertSee(route('tenant.login'));

        // Verify Livewire component state
        Livewire::test(TenantRegister::class)
            ->assertSee('Create Your Store')
            ->assertSee('Store / Business Name')
            ->assertSee('Create Store &amp; Launch POS', false);
    }

    public function test_superadmin_login_screen_renders_with_unified_layout_and_navigation(): void
    {
        $response = $this->get(route('superadmin.login'));
        $response->assertOk();

        // Verify "Back to Home" navigation
        $response->assertSee('Back to Home');
        $response->assertSee(route('home'));

        // Verify Return to Store login
        $response->assertSee('Return to Store Cashier / Admin Login');
        $response->assertSee(route('tenant.login'));

        Livewire::test(PlatformLogin::class)
            ->assertSee('Super Admin')
            ->assertSee('Admin Email Address')
            ->assertSee('Admin Password')
            ->assertSee('Sign In to Control Panel');
    }
}
