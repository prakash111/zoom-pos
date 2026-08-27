<?php

namespace Tests\Feature\Sync;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccessControlSyncTest extends TestCase
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

    protected function tokenFor(User $user): string
    {
        app('auth')->forgetGuards();

        return $this->postJson('/api/auth.php', [
            'action' => 'login', 'login' => $user->login, 'senha' => 'secret1234',
        ])->json('token');
    }

    public function test_invite_then_activate_via_sync_api(): void
    {
        $company = Company::create(['name' => 'Acme Inc']);
        $admin = User::create([
            'company_id' => $company->id, 'name' => 'Admin', 'login' => 'admin', 'email' => 'admin@acme.test',
            'password' => Hash::make('secret1234'), 'role' => 'administrator', 'status' => 'approved',
        ]);
        $token = $this->tokenFor($admin);

        app('auth')->forgetGuards();
        $inviteResponse = $this->postJson('/api/mysql.php', [
            'action' => 'criar_convite', 'name' => 'New Hire', 'email' => 'hire@acme.test',
        ], ['Authorization' => "Bearer {$token}"])->assertOk()->assertJson(['success' => true]);

        $code = $inviteResponse->json('code');
        $this->assertNotEmpty($code);

        app('auth')->forgetGuards();
        $activateResponse = $this->postJson('/api/mysql.php', [
            'action' => 'ativar_convite', 'code' => $code, 'password' => 'newpassword123',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertNotEmpty($activateResponse->json('token'));

        $invited = User::withoutGlobalScope('company')->where('email', 'hire@acme.test')->firstOrFail();
        $this->assertSame('approved', $invited->status);
    }

    public function test_set_permission_requires_privileged_caller(): void
    {
        $company = Company::create(['name' => 'Acme Inc']);
        $staff = User::create([
            'company_id' => $company->id, 'name' => 'Staff', 'login' => 'staff', 'email' => 'staff@acme.test',
            'password' => Hash::make('secret1234'), 'role' => 'operador', 'status' => 'approved',
        ]);
        $token = $this->tokenFor($staff);

        app('auth')->forgetGuards();
        $this->postJson('/api/mysql.php', [
            'action' => 'set_permissao_usuario', 'usuario_id' => $staff->id, 'module' => 'customers',
            'permission_action' => 'view', 'allowed' => true,
        ], ['Authorization' => "Bearer {$token}"])->assertForbidden();
    }
}
