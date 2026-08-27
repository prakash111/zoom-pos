<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Devices\Index as DevicesIndex;
use App\Models\Company;
use App\Models\TenantSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class ImpersonationAndDevicesTest extends TestCase
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

    public function test_admin_can_impersonate_a_same_company_user_and_return(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        $staff = User::create([
            'company_id' => $company->id, 'name' => 'Staff', 'login' => 'staff', 'email' => 'staff@acme.test',
            'password' => bcrypt('secret1234'), 'role' => 'operador', 'status' => 'approved',
        ]);

        $this->post(route('tenant.impersonate.start', $staff))->assertRedirect(route('tenant.dashboard'));
        $this->assertSame($staff->id, Auth::guard('web')->id());
        $this->assertSame($admin->id, session('impersonator_id'));

        $this->post(route('tenant.impersonate.stop'))->assertRedirect(route('tenant.dashboard'));
        $this->assertSame($admin->id, Auth::guard('web')->id());
        $this->assertNull(session('impersonator_id'));
    }

    public function test_non_privileged_user_cannot_impersonate(): void
    {
        [$company] = $this->actingAsTenantStaff();
        $other = User::create([
            'company_id' => $company->id, 'name' => 'Other', 'login' => 'other', 'email' => 'other@acme.test',
            'password' => bcrypt('secret1234'), 'role' => 'operador', 'status' => 'approved',
        ]);

        $this->post(route('tenant.impersonate.start', $other))->assertForbidden();
    }

    public function test_admin_cannot_impersonate_a_user_in_another_company(): void
    {
        $this->actingAsTenantAdmin();
        $otherCompany = Company::create(['name' => 'Other Co']);
        $foreignUser = User::create([
            'company_id' => $otherCompany->id, 'name' => 'Foreign', 'login' => 'foreign', 'email' => 'foreign@other.test',
            'password' => bcrypt('secret1234'), 'role' => 'operador', 'status' => 'approved',
        ]);

        // User's BelongsToCompany global scope means route-model binding
        // can't even find a cross-company {user} — 404, not 403. Safer:
        // it never confirms the id exists at all to an unrelated tenant.
        $this->post(route('tenant.impersonate.start', $foreignUser))->assertNotFound();
    }

    public function test_devices_list_never_shows_another_tenants_sessions(): void
    {
        [$companyA, $adminA] = $this->actingAsTenantAdmin();
        TenantSession::create([
            'token' => $adminA->company_id.'.'.'a'.str_repeat('0', 63),
            'user_id' => $adminA->id, 'company_id' => $companyA->id, 'expires_at' => now()->addHour(),
        ]);

        $companyB = Company::create(['name' => 'Other Co']);
        $userB = User::create([
            'company_id' => $companyB->id, 'name' => 'B User', 'login' => 'buser', 'email' => 'b@other.test',
            'password' => bcrypt('secret1234'), 'role' => 'administrator', 'status' => 'approved',
        ]);
        TenantSession::create([
            'token' => $companyB->id.'.'.'b'.str_repeat('0', 63),
            'user_id' => $userB->id, 'company_id' => $companyB->id, 'expires_at' => now()->addHour(),
        ]);

        Livewire::test(DevicesIndex::class)
            ->assertSee('Jane Admin')
            ->assertDontSee('B User');
    }
}
