<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use App\Services\Sdui\SchemaResponse;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomRolesEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'trial', 'display_name' => 'Free Trial', 'price' => 0.0,
            'currency' => 'USD', 'billing_cycle' => 'monthly', 'duration_days' => 14,
            'features' => ['pos' => true], 'limits' => ['users' => 25], 'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Bolt Repairs', 'trade_name' => 'Bolt', 'slug' => 'bolt',
            'email' => 'admin@bolt.test', 'country' => 'US', 'currency' => 'USD',
            'currency_symbol' => '$', 'plan_name' => 'trial', 'expires_at' => now()->addDays(14),
            'licensed_modules' => ['retail', 'repair_technician'],
        ]);

        User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'admin@bolt.test',
            'password' => Hash::make('secret123'),
            'role' => 'administrator',
        ]);
    }

    private function token(): string
    {
        return $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@bolt.test',
            'password' => 'secret123',
        ])->json('token');
    }

    public function test_built_in_roles_are_seeded_as_system_rows(): void
    {
        $this->assertSame(7, Role::where('is_system', true)->whereNull('company_id')->count());

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/roles');

        $response->assertOk()->assertJsonPath('success', true);
        $slugs = collect($response->json('roles'))->pluck('slug');
        $this->assertTrue($slugs->contains('cashier'));
        $this->assertTrue($slugs->contains('technician'));
        $this->assertNotEmpty($response->json('modules'));
    }

    public function test_create_custom_role_from_flat_permission_checkboxes_and_enforce_it(): void
    {
        $token = $this->token();

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tenant/roles', [
                'name' => 'Senior Technician',
                'description' => 'Lead bench tech',
                'perm__repair__view' => true,
                'perm__repair__diagnose' => true,
                'perm__repair__assign' => '1',
                'perm__pos__view' => true,
                'perm__bogus__nope' => true,
            ]);

        $create->assertCreated()->assertJsonPath('success', true);
        $this->assertSame(
            ['assign', 'diagnose', 'view'],
            collect($create->json('role.permissions.repair'))->sort()->values()->all()
        );

        $role = Role::where('company_id', $this->company->id)->where('slug', 'senior_technician')->firstOrFail();
        $this->assertFalse($role->is_system);

        $tech = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'senior_technician',
        ]);

        $checker = app(PermissionChecker::class);
        PermissionChecker::flushRoleCache();

        $this->assertTrue($checker->allows($tech, 'repair', 'diagnose'));
        $this->assertTrue($checker->allows($tech, 'repair', 'assign'));
        $this->assertTrue($checker->allows($tech, 'pos', 'view'));
        $this->assertFalse($checker->allows($tech, 'repair', 'delete'));
        $this->assertFalse($checker->allows($tech, 'sales', 'export'));
    }

    public function test_custom_role_appears_in_staff_role_map_and_is_assignable(): void
    {
        $token = $this->token();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tenant/roles', ['name' => 'Frontdesk Lead', 'perm__customers__view' => true])
            ->assertCreated();

        $users = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/pos/users');
        $users->assertOk();
        $this->assertArrayHasKey('frontdesk_lead', $users->json('roles'));

        $invite = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/pos/users/invite', [
                'name' => 'Dana Desk',
                'email' => 'dana@bolt.test',
                'role' => 'frontdesk_lead',
                'commission_type' => 'percentage',
                'send_via_email' => false,
            ]);
        $invite->assertSuccessful()->assertJsonPath('success', true);
        $this->assertSame('frontdesk_lead', User::where('email', 'dana@bolt.test')->value('role'));
    }

    public function test_role_in_use_cannot_be_deleted_until_reassigned(): void
    {
        $token = $this->token();

        $roleId = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tenant/roles', ['name' => 'Floor Supervisor', 'perm__pos__view' => true])
            ->json('role.id');

        User::factory()->create(['company_id' => $this->company->id, 'role' => 'floor_supervisor']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/tenant/roles/{$roleId}")
            ->assertStatus(422);

        User::where('company_id', $this->company->id)->where('role', 'floor_supervisor')
            ->update(['role' => 'cashier']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/tenant/roles/{$roleId}")
            ->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    public function test_system_role_cannot_be_edited_or_deleted(): void
    {
        $token = $this->token();
        $systemId = Role::where('slug', 'manager')->whereNull('company_id')->value('id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson("/api/tenant/roles/{$systemId}", ['name' => 'Hacked'])
            ->assertStatus(404);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/tenant/roles/{$systemId}")
            ->assertStatus(404);
    }

    public function test_roles_sdui_view_is_valid_schema(): void
    {
        $schema = SchemaResponse::rolesView($this->company);
        $this->assertSame([], app(SchemaValidator::class)->validate($schema));
        $this->assertSame('Manage Roles', $schema['title']);
    }
}
