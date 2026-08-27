<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SyncSaveLoadRoundTripTest extends TestCase
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
        // Auth::viaRequest() guards cache their resolved user for the whole
        // in-process test app; forgetGuards() forces re-resolution against
        // each new simulated request/token, which is what actually happens
        // in production (fresh request per process) but not automatically
        // between multiple $this->postJson() calls in a single test method.
        app('auth')->forgetGuards();

        return $this->postJson('/api/auth.php', [
            'action' => 'login',
            'login' => $user->login,
            'senha' => 'secret1234',
        ])->json('token');
    }

    protected function postAs(string $token, array $payload)
    {
        app('auth')->forgetGuards();

        return $this->postJson('/api/mysql.php', $payload, ['Authorization' => "Bearer {$token}"]);
    }

    public function test_save_table_and_load_all_round_trip_via_kv_fallback(): void
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
        $token = $this->tokenFor($user);

        $this->postAs($token, [
            'action' => 'save_table',
            'table' => 'produtos',
            'data' => ['id' => 'p1', 'nome' => 'Test Product', 'preco_venda' => 19.9],
        ])->assertOk()->assertJson(['success' => true]);

        $response = $this->postAs($token, ['action' => 'load_all'])->assertOk();

        $response->assertJsonPath('data.produtos.0.id', 'p1');
        $response->assertJsonPath('data.produtos.0.nome', 'Test Product');
    }

    public function test_load_all_never_leaks_across_tenants(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $userA = User::create([
            'company_id' => $companyA->id, 'name' => 'A', 'login' => 'usera', 'email' => 'a@test.com',
            'password' => Hash::make('secret1234'), 'role' => 'administrator', 'status' => 'approved',
        ]);
        $companyB = Company::create(['name' => 'Company B']);
        $userB = User::create([
            'company_id' => $companyB->id, 'name' => 'B', 'login' => 'userb', 'email' => 'b@test.com',
            'password' => Hash::make('secret1234'), 'role' => 'administrator', 'status' => 'approved',
        ]);

        $tokenA = $this->tokenFor($userA);
        $tokenB = $this->tokenFor($userB);

        $this->postAs($tokenA, [
            'action' => 'save_table', 'table' => 'produtos', 'data' => ['id' => 'secret-a', 'nome' => 'A only'],
        ])->assertOk();

        $response = $this->postAs($tokenB, ['action' => 'load_all'])->assertOk();

        $this->assertEmpty($response->json('data.produtos') ?? []);
    }
}
