<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Plan;
use App\Models\PlatformBranding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression guard for the mobile "Sign In throws an HTTP 302" crash.
 *
 * The POS login endpoint must always answer with JSON — never a redirect —
 * and must route an unverified account to the OTP flow with a structured
 * `requires_verification` payload rather than handing it a working token.
 */
class LoginEmailVerificationGateTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');

        Plan::firstOrCreate(['name' => 'trial'], [
            'display_name' => 'Free Trial',
            'billing_cycle' => 'monthly',
            'duration_days' => 14,
            'price' => 0,
            'currency' => 'USD',
            'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Verify Gate Store',
            'slug' => 'verify-gate-store',
            'currency' => 'USD',
            'currency_symbol' => '$',
        ]);
    }

    private function configureSmtp(): void
    {
        PlatformBranding::current()->update([
            'smtp_host' => 'smtp.mailtrap.io',
            'smtp_port' => 2525,
            'smtp_username' => 'smtp-user',
            'smtp_password' => 'smtp-pass',
            'smtp_from_address' => 'noreply@platform.test',
            'smtp_from_name' => 'Platform Security',
        ]);
    }

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'company_id' => $this->company->id,
            'name' => 'OTP Pending User',
            'email' => 'test_otp@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'administrator',
            'status' => 'pending',
            'email_verified_at' => null,
        ], $overrides));
    }

    public function test_unverified_account_gets_requires_verification_json_not_a_redirect(): void
    {
        $this->configureSmtp();
        $this->makeUser();

        $response = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'test_otp@gmail.com',
            'password' => 'password',
        ]);

        $response->assertOk(); // 200, never 3xx
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('status', 'requires_verification');
        $response->assertJsonPath('requires_otp', true);
        $response->assertJsonPath('email', 'test_otp@gmail.com');
        $this->assertNull($response->json('token'));
    }

    public function test_login_never_issues_a_302_even_without_the_json_accept_header(): void
    {
        $this->configureSmtp();
        $this->makeUser();

        // Simulate a client that forgot `Accept: application/json`.
        $response = $this->post('/api/v1/pos/auth/login', [
            'email' => 'test_otp@gmail.com',
            'password' => 'password',
        ]);

        $this->assertLessThan(300, $response->baseResponse->getStatusCode());
        $this->assertNull($response->headers->get('Location'));
        $response->assertJsonPath('status', 'requires_verification');
    }

    public function test_verified_account_signs_in_and_receives_a_token(): void
    {
        $this->configureSmtp();
        $this->makeUser([
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'test_otp@gmail.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_gate_is_inert_when_smtp_is_not_configured(): void
    {
        // No SMTP => OTP verification is not enforced; an unverified user still
        // signs in (matches pre-existing behaviour on self-hosted installs
        // that never set up a mail server).
        $this->makeUser();

        $response = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'test_otp@gmail.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_unauthenticated_api_route_returns_401_json_not_a_login_redirect(): void
    {
        $response = $this->getJson('/api/v1/pos/status');

        $response->assertStatus(401);
        $this->assertNull($response->headers->get('Location'));
    }
}
