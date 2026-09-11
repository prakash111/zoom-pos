<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Settings\Index as SettingsIndex;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlatformSystem;
use App\Models\Product;
use App\Models\User;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Platform Settings ▸ General ▸ "Auto-Seed Demo Data on Signup" — the
 * SuperAdmin-wide toggle that gates whether a newly registered tenant
 * (self-registration, SuperAdmin-created, or lazily on first app bootstrap)
 * gets auto-populated with sample products/categories/tables/transactions.
 */
class AutoSeedDemoDataToggleTest extends TestCase
{
    use ActsAsPlatformAdmin, ActsAsTenantUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::firstOrCreate(['name' => 'trial'], [
            'display_name' => 'Free Trial',
            'price' => 0.00,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'duration_days' => 14,
            'features' => ['pos' => true, 'offline' => true],
            'limits' => ['products' => 500, 'users' => 5],
            'active' => true,
        ]);
    }

    private function registerCompany(): Company
    {
        $result = app(TenantProvisioningService::class)->registerTenant([
            'store_name' => 'Toggle Test Store '.uniqid(),
            'slug' => 'toggle-test-'.uniqid(),
            'owner_name' => 'Toggle Tester',
            'email' => 'owner-'.uniqid().'@toggletest.test',
            'password' => 'secret123',
            'plan_name' => 'trial',
            'pos_mode' => 'retail',
        ]);

        return $result['company'];
    }

    public function test_the_toggle_defaults_to_on_preserving_existing_behavior(): void
    {
        $this->assertTrue(filter_var(PlatformSystem::get('auto_seed_demo_data_on_registration', true), FILTER_VALIDATE_BOOLEAN));

        $company = $this->registerCompany();

        $this->assertTrue((bool) $company->fresh()->is_seeding_complete);
        $this->assertGreaterThan(0, Product::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_superadmin_can_switch_the_toggle_off_and_it_persists(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'general')
            ->set('autoSeedDemoDataOnRegistration', false)
            ->call('saveGeneral')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $this->assertSame('0', PlatformSystem::get('auto_seed_demo_data_on_registration'));

        // Re-mounting the component reads the persisted value back.
        Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'general')
            ->assertSet('autoSeedDemoDataOnRegistration', false);
    }

    public function test_registration_skips_seeding_entirely_when_the_platform_toggle_is_off(): void
    {
        PlatformSystem::set('auto_seed_demo_data_on_registration', '0');

        $company = $this->registerCompany();

        // Marked complete so bootstrap never tries to lazily seed it either,
        // but genuinely empty — no demo products were created.
        $this->assertTrue((bool) $company->fresh()->is_seeding_complete);
        $this->assertSame(0, Product::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_platform_toggle_off_overrides_a_request_that_explicitly_asked_to_seed(): void
    {
        PlatformSystem::set('auto_seed_demo_data_on_registration', '0');

        $result = app(TenantProvisioningService::class)->registerTenant([
            'store_name' => 'Explicit Seed Request '.uniqid(),
            'slug' => 'explicit-seed-'.uniqid(),
            'owner_name' => 'Tester',
            'email' => 'owner-'.uniqid().'@toggletest.test',
            'password' => 'secret123',
            'plan_name' => 'trial',
            'pos_mode' => 'retail',
            'seed_demo_data' => true,
        ]);

        $company = $result['company'];
        $this->assertSame(0, Product::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_platform_toggle_on_still_respects_a_per_request_opt_out(): void
    {
        PlatformSystem::set('auto_seed_demo_data_on_registration', '1');

        $result = app(TenantProvisioningService::class)->registerTenant([
            'store_name' => 'Per Request Opt Out '.uniqid(),
            'slug' => 'opt-out-'.uniqid(),
            'owner_name' => 'Tester',
            'email' => 'owner-'.uniqid().'@toggletest.test',
            'password' => 'secret123',
            'plan_name' => 'trial',
            'pos_mode' => 'retail',
            'seed_demo_data' => false,
        ]);

        $company = $result['company'];
        $this->assertSame(0, Product::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_bootstrap_does_not_lazily_seed_an_unseeded_tenant_when_the_toggle_is_off(): void
    {
        PlatformSystem::set('auto_seed_demo_data_on_registration', '0');

        $unseededCompany = Company::create([
            'id' => 'test_toggle_off_'.uniqid(),
            'name' => 'Toggle Off Bootstrap Store',
            'pos_mode' => 'retail',
            'is_seeding_complete' => false,
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'company_id' => $unseededCompany->id,
            'email' => 'toggle_off_'.uniqid().'@example.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $token = $this->postJson('/api/v1/pos/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertOk()->json('token');

        $response = $this->withToken($token)
            ->withHeaders(['X-Company-Id' => $unseededCompany->id])
            ->getJson('/api/v1/pos/app/bootstrap?locale=en');

        // Still a successful bootstrap — just no demo data, and the flag is
        // flipped so this check doesn't re-run on every subsequent call.
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tenant.is_seeding_complete', true);

        $this->assertDatabaseMissing('products', [
            'company_id' => $unseededCompany->id,
            'is_demo' => true,
        ]);
    }

    public function test_bootstrap_still_lazily_seeds_when_the_toggle_is_on(): void
    {
        PlatformSystem::set('auto_seed_demo_data_on_registration', '1');

        $unseededCompany = Company::create([
            'id' => 'test_toggle_on_'.uniqid(),
            'name' => 'Toggle On Bootstrap Store',
            'pos_mode' => 'retail',
            'is_seeding_complete' => false,
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'company_id' => $unseededCompany->id,
            'email' => 'toggle_on_'.uniqid().'@example.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $token = $this->postJson('/api/v1/pos/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertOk()->json('token');

        $this->withToken($token)
            ->withHeaders(['X-Company-Id' => $unseededCompany->id])
            ->getJson('/api/v1/pos/app/bootstrap?locale=en')
            ->assertOk()
            ->assertJsonPath('tenant.is_seeding_complete', true);

        $this->assertDatabaseHas('products', [
            'company_id' => $unseededCompany->id,
            'is_demo' => true,
        ]);
    }
}
