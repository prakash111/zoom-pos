<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Tenants\Create;
use App\Livewire\SuperAdmin\Tenants\Index;
use App\Livewire\SuperAdmin\Tenants\Show;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class TenantsTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    public function test_super_admin_can_create_a_tenant_with_its_first_admin_user(): void
    {
        $this->actingAsSuperAdmin();
        Plan::create(['name' => 'starter', 'display_name' => 'Starter', 'billing_cycle' => 'monthly', 'duration_days' => 30, 'price' => 19]);

        Livewire::test(Create::class)
            ->set('name', 'Acme Retail')
            ->set('email', 'billing@acme.test')
            ->set('country', 'US')
            ->set('planName', 'starter')
            ->set('adminName', 'Jane Admin')
            ->set('adminLogin', 'jane')
            ->set('adminEmail', 'jane@acme.test')
            ->set('adminPassword', 'secret1234')
            ->call('save')
            ->assertRedirect();

        $company = Company::where('name', 'Acme Retail')->firstOrFail();
        $this->assertSame('starter', $company->plan_name);
        $this->assertNotNull($company->expires_at);

        $user = User::withoutGlobalScope('company')->where('company_id', $company->id)->firstOrFail();
        $this->assertSame('administrator', $user->role);
        $this->assertSame('jane', $user->login);
    }

    public function test_super_admin_can_suspend_and_reactivate_a_tenant(): void
    {
        $this->actingAsSuperAdmin();
        $company = Company::create(['name' => 'Acme Inc', 'status' => 'active']);

        Livewire::test(Show::class, ['company' => $company])
            ->call('suspend')
            ->assertSet('status', 'suspended');

        $this->assertSame('suspended', $company->fresh()->status);

        Livewire::test(Show::class, ['company' => $company])
            ->call('activate')
            ->assertSet('status', 'active');

        $this->assertSame('active', $company->fresh()->status);
    }

    public function test_search_filters_the_tenant_list(): void
    {
        $this->actingAsSuperAdmin();
        Company::create(['name' => 'Acme Retail', 'status' => 'active']);
        Company::create(['name' => 'Other Co', 'status' => 'active']);

        Livewire::test(Index::class)
            ->set('search', 'Acme')
            ->assertSee('Acme Retail')
            ->assertDontSee('Other Co');
    }
}
