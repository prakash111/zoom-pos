<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Plans\Index;
use App\Models\Company;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class PlansTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    public function test_super_admin_can_create_a_plan_with_limits(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Index::class)
            ->call('newPlan')
            ->set('name', 'pro')
            ->set('displayName', 'Professional')
            ->set('billingCycle', 'yearly')
            ->set('durationDays', 365)
            ->set('price', 199)
            ->set('currency', 'USD')
            ->set('limitUsers', 25)
            ->set('limitDevices', 10)
            ->call('save');

        $plan = Plan::findOrFail('pro');
        $this->assertSame('Professional', $plan->display_name);
        $this->assertSame(25, $plan->limits['usuarios']);
    }

    public function test_cannot_delete_a_plan_that_tenants_are_on(): void
    {
        $this->actingAsSuperAdmin();
        Plan::create(['name' => 'starter', 'display_name' => 'Starter', 'billing_cycle' => 'monthly', 'price' => 19]);
        Company::create(['name' => 'Acme', 'plan_name' => 'starter']);

        Livewire::test(Index::class)->call('delete', 'starter');

        $this->assertNotNull(Plan::find('starter'));
    }

    public function test_can_delete_an_unused_plan(): void
    {
        $this->actingAsSuperAdmin();
        Plan::create(['name' => 'unused', 'display_name' => 'Unused', 'billing_cycle' => 'monthly', 'price' => 9]);

        Livewire::test(Index::class)->call('delete', 'unused');

        $this->assertNull(Plan::find('unused'));
    }
}
