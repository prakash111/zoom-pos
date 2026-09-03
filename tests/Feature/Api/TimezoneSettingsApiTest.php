<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TimezoneSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'trial', 'display_name' => 'Free Trial', 'price' => 0.00, 'currency' => 'USD',
            'billing_cycle' => 'monthly', 'duration_days' => 14,
            'features' => ['pos' => true, 'offline' => true],
            'limits' => ['products' => 500, 'users' => 5], 'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Bharat Kirana Store', 'slug' => 'bharat-kirana-'.uniqid(),
            'email' => 'tz@example.com', 'country' => 'IN', 'currency' => 'INR',
            'currency_symbol' => '₹', 'plan_name' => 'trial', 'expires_at' => now()->addDays(14),
        ]);

        $this->admin = User::create([
            'company_id' => $this->company->id, 'name' => 'TZ Admin', 'email' => 'tzadmin@example.com',
            'password' => Hash::make('secret123'), 'role' => 'admin',
        ]);
    }

    protected function token(): string
    {
        return $this->postJson('/api/v1/pos/auth/login', [
            'email' => $this->admin->email, 'password' => 'secret123',
        ])->assertOk()->json('token');
    }

    public function test_country_derived_timezone_defaults_with_no_manual_override(): void
    {
        $this->assertSame('', $this->company->timezone ?? '');
        $this->assertSame('Asia/Kolkata', $this->company->resolveTimezone());

        $response = $this->withToken($this->token())->getJson('/api/v1/pos/settings');
        $response->assertOk()
            ->assertJsonPath('profile.timezone', '')
            ->assertJsonPath('profile.resolved_timezone', 'Asia/Kolkata')
            ->assertJsonPath('profile.default_timezone_for_country', 'Asia/Kolkata')
            ->assertJsonStructure(['timezones']);

        $this->assertContains('Asia/Kolkata', $response->json('timezones'));
        $this->assertContains('Asia/Dubai', $response->json('timezones'));
    }

    public function test_manual_override_wins_over_country_default_and_can_be_cleared(): void
    {
        $token = $this->token();

        $this->withToken($token)->putJson('/api/v1/pos/settings/profile', [
            'name' => $this->company->name,
            'country' => 'IN',
            'timezone' => 'Asia/Dubai',
        ])->assertOk()->assertJsonPath('profile.timezone', 'Asia/Dubai')
            ->assertJsonPath('profile.resolved_timezone', 'Asia/Dubai');

        $this->assertSame('Asia/Dubai', $this->company->fresh()->resolveTimezone());

        // Clearing the override (empty string, not omitted) falls back to
        // the country default again.
        $this->withToken($token)->putJson('/api/v1/pos/settings/profile', [
            'name' => $this->company->name,
            'country' => 'IN',
            'timezone' => '',
        ])->assertOk()->assertJsonPath('profile.timezone', '')
            ->assertJsonPath('profile.resolved_timezone', 'Asia/Kolkata');
    }

    public function test_rejects_an_invalid_timezone_identifier(): void
    {
        $this->withToken($this->token())->putJson('/api/v1/pos/settings/profile', [
            'name' => $this->company->name,
            'country' => 'IN',
            'timezone' => 'Not/AZone',
        ])->assertStatus(422);
    }

    public function test_multi_zone_countries_get_a_sensible_curated_default(): void
    {
        $this->assertSame('America/New_York', Company::defaultTimezoneForCountry('US'));
        $this->assertSame('Australia/Sydney', Company::defaultTimezoneForCountry('AU'));
        $this->assertSame('UTC', Company::defaultTimezoneForCountry(null));
        $this->assertSame('UTC', Company::defaultTimezoneForCountry('ZZ'));
    }
}
