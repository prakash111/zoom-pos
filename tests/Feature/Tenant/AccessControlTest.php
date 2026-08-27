<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Auth\AcceptInvite;
use App\Livewire\Tenant\Users\Index as UsersIndex;
use App\Livewire\Tenant\Users\Permissions;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class AccessControlTest extends TestCase
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

    public function test_admin_can_invite_a_user_and_the_invitee_can_accept_it(): void
    {
        $this->actingAsTenantAdmin();

        $component = Livewire::test(UsersIndex::class)
            ->set('inviteName', 'New Hire')
            ->set('inviteEmail', 'newhire@acme.test')
            ->set('inviteRole', User::ROLE_CASHIER)
            ->call('invite');

        $code = $component->get('justInvitedCode');
        $this->assertNotEmpty($code);
        $this->assertStringContainsString('accept-invite', $component->get('justInvitedWhatsAppUrl'));
        $this->assertStringContainsString($code, $component->get('justInvitedWhatsAppUrl'));

        $invited = User::withoutGlobalScope('company')->where('email', 'newhire@acme.test')->firstOrFail();
        $this->assertSame('convidado', $invited->status);
        $this->assertSame(User::ROLE_CASHIER, $invited->role);

        Auth::guard('web')->logout();

        Livewire::test(AcceptInvite::class)
            ->set('code', $code)
            ->set('password', 'brandnewpass')
            ->set('password_confirmation', 'brandnewpass')
            ->call('accept')
            ->assertRedirect('/tenant');

        $this->assertSame('approved', $invited->fresh()->status);
        $this->assertTrue(Auth::guard('web')->check());
    }

    public function test_roles_supported_and_can_be_updated(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $staff = User::create([
            'company_id' => $company->id,
            'name' => 'John Staff',
            'login' => 'john',
            'email' => 'john@acme.test',
            'password' => bcrypt('secret1234'),
            'role' => User::ROLE_CASHIER,
            'status' => 'approved',
        ]);

        // Test updating through all 6 roles
        foreach ([User::ROLE_MANAGER, User::ROLE_SALESPERSON, User::ROLE_STOCK_CLERK, User::ROLE_FINANCE, User::ROLE_ADMINISTRATOR] as $role) {
            Livewire::test(UsersIndex::class)
                ->call('updateUserRole', $staff->id, $role);

            $this->assertSame($role, $staff->fresh()->role);
        }
    }

    public function test_admin_can_switch_to_user_account_and_switch_back(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $cashier = User::create([
            'company_id' => $company->id,
            'name' => 'Cashier Bob',
            'login' => 'bob',
            'email' => 'bob@acme.test',
            'password' => bcrypt('secret1234'),
            'role' => User::ROLE_CASHIER,
            'status' => 'approved',
        ]);

        // Switch to cashier account
        $component = Livewire::test(UsersIndex::class)
            ->call('switchToUser', $cashier->id)
            ->assertRedirect(route('tenant.dashboard'));

        $this->assertSame($cashier->id, Auth::guard('web')->id());
        $this->assertSame($admin->id, session('impersonator_id'));

        // Switch back to admin account
        $response = $this->actingAs($cashier, 'web')
            ->withSession(['impersonator_id' => $admin->id])
            ->post(route('tenant.impersonate.stop'));

        $response->assertRedirect(route('tenant.dashboard'));
        $this->assertSame($admin->id, Auth::guard('web')->id());
        $this->assertNull(session('impersonator_id'));
    }

    public function test_applying_role_presets_in_permission_matrix(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $clerk = User::create([
            'company_id' => $company->id,
            'name' => 'Stock Clerk Alice',
            'login' => 'alice',
            'email' => 'alice@acme.test',
            'password' => bcrypt('secret1234'),
            'role' => User::ROLE_STOCK_CLERK,
            'status' => 'approved',
        ]);

        $checker = app(PermissionChecker::class);

        // Apply Finance preset
        Livewire::test(Permissions::class, ['user' => $clerk])
            ->call('applyPreset', User::ROLE_FINANCE)
            ->call('save');

        $this->assertTrue($checker->allows($clerk->fresh(), 'finance', 'view'));
        $this->assertTrue($checker->allows($clerk->fresh(), 'reports', 'export'));
        $this->assertFalse($checker->allows($clerk->fresh(), 'settings', 'edit'));

        // Custom individual checkbox toggles (Read, Write, Edit, Delete)
        Livewire::test(Permissions::class, ['user' => $clerk])
            ->set('grid.products.edit', true)
            ->set('grid.products.delete', false)
            ->call('save');

        $this->assertTrue($checker->allows($clerk->fresh(), 'products', 'edit'));
        $this->assertFalse($checker->allows($clerk->fresh(), 'products', 'delete'));
    }

    public function test_cannot_remove_the_last_administrator(): void
    {
        $this->actingAsTenantAdmin();

        Livewire::test(UsersIndex::class)->call('delete', auth('web')->id());

        $this->assertDatabaseHas('users', ['id' => auth('web')->id()]);
    }

    public function test_unauthorized_user_is_served_custom_permission_denied_screen(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $cashier = User::create([
            'company_id' => $company->id,
            'name' => 'Cashier Tim',
            'login' => 'tim',
            'email' => 'tim@acme.test',
            'password' => bcrypt('secret1234'),
            'role' => User::ROLE_CASHIER,
            'status' => 'approved',
        ]);

        // Cashier attempting to access Settings without permission
        $response = $this->actingAs($cashier, 'web')->get(route('tenant.settings.index'));

        $response->assertStatus(403);
        $response->assertSee('Permission Denied');
        $response->assertSee('Access Restricted');
        $response->assertSee('Cashier Tim');
    }

    public function test_consignments_targets_and_cash_register_rbac_and_route_gating(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $cashier = User::create([
            'company_id' => $company->id,
            'name' => 'Cashier Emma',
            'login' => 'emma',
            'email' => 'emma@acme.test',
            'password' => bcrypt('secret1234'),
            'role' => User::ROLE_CASHIER,
            'status' => 'approved',
        ]);

        $checker = app(PermissionChecker::class);

        // 1. Check Cashier role defaults
        $this->assertTrue($checker->allows($cashier, 'consignments', 'view'));
        $this->assertFalse($checker->allows($cashier, 'consignments', 'create'));
        $this->assertTrue($checker->allows($cashier, 'targets', 'view'));
        $this->assertFalse($checker->allows($cashier, 'targets', 'edit'));
        $this->assertTrue($checker->allows($cashier, 'cash_register', 'view'));
        $this->assertTrue($checker->allows($cashier, 'cash_register', 'create'));

        // 2. Test authorized routes accessible by Cashier
        $this->actingAs($cashier, 'web')
            ->get(route('tenant.consignments.index'))
            ->assertStatus(200);

        $this->actingAs($cashier, 'web')
            ->get(route('tenant.sales-targets.index'))
            ->assertStatus(200);

        $this->actingAs($cashier, 'web')
            ->get(route('tenant.financials.cash_register'))
            ->assertStatus(200);

        // 3. Test unauthorized route: Cashier trying to create a consignment
        $this->actingAs($cashier, 'web')
            ->get(route('tenant.consignments.create'))
            ->assertStatus(403);

        // 4. Test explicit denial via Permissions Matrix
        $this->actingAs($admin, 'web');
        Livewire::test(Permissions::class, ['user' => $cashier])
            ->set('grid.consignments.view', false)
            ->set('grid.targets.view', false)
            ->set('grid.cash_register.view', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($checker->allows($cashier->fresh(), 'consignments', 'view'));
        $this->assertFalse($checker->allows($cashier->fresh(), 'targets', 'view'));
        $this->assertFalse($checker->allows($cashier->fresh(), 'cash_register', 'view'));

        // 5. Test denied routes after matrix update
        $this->actingAs($cashier, 'web')
            ->get(route('tenant.consignments.index'))
            ->assertStatus(403);

        $this->actingAs($cashier, 'web')
            ->get(route('tenant.sales-targets.index'))
            ->assertStatus(403);

        $this->actingAs($cashier, 'web')
            ->get(route('tenant.financials.cash_register'))
            ->assertStatus(403);

        // 6. Test granting custom permissions via Matrix
        $this->actingAs($admin, 'web');
        Livewire::test(Permissions::class, ['user' => $cashier])
            ->set('grid.consignments.create', true)
            ->set('grid.targets.edit', true)
            ->call('save');

        $this->assertTrue($checker->allows($cashier->fresh(), 'consignments', 'create'));
        $this->assertTrue($checker->allows($cashier->fresh(), 'targets', 'edit'));
    }

    public function test_master_table_matrix_row_and_column_toggle_and_persistence(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $cashier = User::create([
            'company_id' => $company->id,
            'name' => 'Cashier Leo',
            'login' => 'leo',
            'email' => 'leo@acme.test',
            'password' => bcrypt('secret1234'),
            'role' => User::ROLE_CASHIER,
            'status' => 'approved',
        ]);

        $checker = app(PermissionChecker::class);

        // Toggle row for consignments (check all actions)
        Livewire::test(Permissions::class, ['user' => $cashier])
            ->call('toggleRow', 'consignments')
            ->call('save');

        foreach (['view', 'create', 'edit', 'delete', 'export'] as $action) {
            $this->assertTrue($checker->allows($cashier->fresh(), 'consignments', $action));
        }

        // Toggle row again (uncheck all actions)
        Livewire::test(Permissions::class, ['user' => $cashier])
            ->call('toggleRow', 'consignments')
            ->call('save');

        foreach (['view', 'create', 'edit', 'delete', 'export'] as $action) {
            $this->assertFalse($checker->allows($cashier->fresh(), 'consignments', $action));
        }

        // Standard 5-action column toggles for cash_register & targets
        Livewire::test(Permissions::class, ['user' => $cashier])
            ->set('grid.cash_register.view', true)
            ->set('grid.cash_register.create', true)
            ->set('grid.cash_register.edit', true)
            ->set('grid.cash_register.delete', true)
            ->set('grid.cash_register.export', true)
            ->set('grid.targets.view', true)
            ->set('grid.targets.create', true)
            ->set('grid.targets.edit', true)
            ->set('grid.targets.delete', true)
            ->set('grid.targets.export', true)
            ->call('save');

        foreach (['view', 'create', 'edit', 'delete', 'export'] as $action) {
            $this->assertTrue($checker->allows($cashier->fresh(), 'cash_register', $action));
            $this->assertTrue($checker->allows($cashier->fresh(), 'targets', $action));
        }
    }
}
