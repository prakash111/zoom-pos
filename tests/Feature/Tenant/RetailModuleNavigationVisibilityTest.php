<?php

namespace Tests\Feature\Tenant;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class RetailModuleNavigationVisibilityTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

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

    public function test_tenant_with_retail_and_vertical_modules_sees_retail_pos_and_drawer_items(): void
    {
        $company = Company::create([
            'name' => 'Subodh Store',
            'slug' => 'subodh-store-novc',
            'status' => 'active',
            'pos_mode' => 'retail',
            'licensed_modules' => ['retail', 'restaurant', 'pharmacy', 'service_booking', 'repair_technician', 'leadmanagement'],
            'currency_symbol' => '$',
            'expires_at' => now()->addYear(),
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Subodh Singh',
            'login' => 'subodh',
            'email' => 'subodhksingh85@gmail.com',
            'password' => Hash::make('secret1234'),
            'role' => 'administrator',
            'status' => 'approved',
        ]);

        $this->actingAs($user, 'web');
        app()->instance('tenant.company_id', $company->id);

        $response = $this->get(route('tenant.dashboard'));
        $response->assertOk();

        // 1. Retail POS drawer items are rendered
        $response->assertSee('data-section-key="cashier_sales"', false);
        $response->assertSee('data-item-key="pos"', false);
        $response->assertSee('data-section-key="products_inventory"', false);
        $response->assertSee('data-item-key="inventory"', false);

        // 2. Sidebar rail and header POS actions are present
        $response->assertSee('Retail POS');
        $response->assertSee('Cashier POS');

        // 3. Vertical drawer items for enabled modules are also rendered
        $response->assertSee('data-section-key="pharmacy_management"', false);
        $response->assertSee('data-section-key="salon_bookings"', false);
        $response->assertSee('data-section-key="repair_service"', false);
        $response->assertSee('data-section-key="restaurant_operations"', false);
    }

    public function test_pure_pharmacy_tenant_does_not_see_retail_drawer_items(): void
    {
        $company = Company::create([
            'name' => 'Pure Rx Store',
            'status' => 'active',
            'pos_mode' => 'pharmacy',
            'licensed_modules' => ['pharmacy'],
            'currency_symbol' => '$',
            'expires_at' => now()->addYear(),
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Rx Admin',
            'login' => 'rxadmin',
            'email' => 'rx@test.com',
            'password' => Hash::make('secret1234'),
            'role' => 'administrator',
            'status' => 'approved',
        ]);

        $this->actingAs($user, 'web');
        app()->instance('tenant.company_id', $company->id);

        $response = $this->get(route('tenant.dashboard'));
        $response->assertOk();

        $response->assertDontSee('data-section-key="cashier_sales"', false);
        $response->assertSee('data-section-key="pharmacy_management"', false);
    }

    public function test_pure_restaurant_tenant_sees_restaurant_pos_not_retail_drawer(): void
    {
        $company = Company::create([
            'name' => 'Bistro',
            'status' => 'active',
            'pos_mode' => 'restaurant',
            'licensed_modules' => ['restaurant'],
            'currency_symbol' => '$',
            'expires_at' => now()->addYear(),
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Chef',
            'login' => 'chef',
            'email' => 'chef@test.com',
            'password' => Hash::make('secret1234'),
            'role' => 'administrator',
            'status' => 'approved',
        ]);

        $this->actingAs($user, 'web');
        app()->instance('tenant.company_id', $company->id);

        $response = $this->get(route('tenant.dashboard'));
        $response->assertOk();

        $response->assertDontSee('data-section-key="cashier_sales"', false);
        $response->assertSee('data-section-key="restaurant_operations"', false);
        $response->assertSee('Food POS');
    }
}
