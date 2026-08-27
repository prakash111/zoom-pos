<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantApiLoginTest extends TestCase
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

    public function test_login_mints_a_company_id_dot_hex_token(): void
    {
        $company = Company::create(['name' => 'Acme Inc']);
        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Jane Admin',
            'login' => 'jane',
            'email' => 'jane@acme.test',
            'password' => Hash::make('secret1234'),
            'role' => 'administrator',
            'status' => 'approved',
        ]);

        $response = $this->postJson('/api/auth.php', [
            'action' => 'login',
            'login' => 'jane',
            'senha' => 'secret1234',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $token = $response->json('token');

        $this->assertMatchesRegularExpression('/^'.preg_quote($company->id, '/').'\.[A-Za-z0-9]{64}$/', $token);

        $this->assertDatabaseHas('sessions', [
            'token' => $token,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'revoked' => false,
        ]);
    }

    public function test_login_rejects_invalid_password(): void
    {
        $company = Company::create(['name' => 'Acme Inc']);
        User::create([
            'company_id' => $company->id,
            'name' => 'Jane Admin',
            'login' => 'jane',
            'email' => 'jane@acme.test',
            'password' => Hash::make('secret1234'),
            'role' => 'administrator',
            'status' => 'approved',
        ]);

        $response = $this->postJson('/api/auth.php', [
            'action' => 'login',
            'login' => 'jane',
            'senha' => 'wrong-password',
        ]);

        $response->assertStatus(401)->assertJson(['success' => false]);
    }
}
