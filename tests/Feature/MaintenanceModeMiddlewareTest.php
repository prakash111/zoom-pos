<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PlatformSystem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MaintenanceModeMiddlewareTest extends TestCase
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

    public function test_tenant_sync_requests_are_blocked_during_maintenance(): void
    {
        PlatformSystem::set('maintenance_mode', '1');

        $company = Company::create(['name' => 'Acme Inc']);
        $user = User::create([
            'company_id' => $company->id, 'name' => 'Jane', 'login' => 'jane', 'email' => 'jane@test.com',
            'password' => Hash::make('secret1234'), 'role' => 'administrator', 'status' => 'approved',
        ]);

        $this->postJson('/api/auth.php', [
            'action' => 'login', 'login' => 'jane', 'senha' => 'secret1234',
        ])->assertStatus(503)->assertJson(['success' => false]);
    }

    public function test_sync_requests_work_normally_once_maintenance_is_off(): void
    {
        PlatformSystem::set('maintenance_mode', '0');

        $company = Company::create(['name' => 'Acme Inc']);
        User::create([
            'company_id' => $company->id, 'name' => 'Jane', 'login' => 'jane', 'email' => 'jane@test.com',
            'password' => Hash::make('secret1234'), 'role' => 'administrator', 'status' => 'approved',
        ]);

        $this->postJson('/api/auth.php', [
            'action' => 'login', 'login' => 'jane', 'senha' => 'secret1234',
        ])->assertOk()->assertJson(['success' => true]);
    }
}
