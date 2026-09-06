<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\PlatformBranding;
use App\Models\Product;
use App\Models\SalonAppointment;
use App\Models\TenantApiKey;
use App\Models\User;
use App\Services\Sdui\SchemaValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerSelectorCalendarAndAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $user;
    protected User $specialist;
    protected Product $service;
    protected TenantApiKey $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');

        \App\Models\Plan::firstOrCreate(['name' => 'trial'], [
            'display_name' => 'Free Trial',
            'billing_cycle' => 'monthly',
            'duration_days' => 14,
            'price' => 0,
            'currency' => 'USD',
            'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Luxe Salon & Wellness',
            'slug' => 'luxe-salon',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'timezone' => 'America/New_York',
        ]);

        $this->user = User::create([
            'company_id' => $this->company->id,
            'name' => 'Owner Admin',
            'email' => 'owner@luxe-salon.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'approved',
        ]);

        $this->specialist = User::create([
            'company_id' => $this->company->id,
            'name' => 'Elena Stylist',
            'email' => 'elena@luxe-salon.test',
            'password' => Hash::make('password123'),
            'role' => 'staff',
            'is_specialist' => true,
            'status' => 'approved',
        ]);

        $this->service = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Balayage & Blowdry',
            'type' => 'service',
            'category_type' => 'salon',
            'price' => 150.00,
            'sale_price' => 150.00,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $this->apiKey = TenantApiKey::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Test POS Key',
            'token' => 'zk_live_test_token_1234567890',
            'permissions' => ['*'],
            'active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (file_exists(storage_path('installed'))) {
            @unlink(storage_path('installed'));
        }
        parent::tearDown();
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey->token,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Test 1: Customer selector component renders in salon booking create view
     * and passes SchemaValidator.
     */
    public function test_salon_booking_create_view_contains_customer_selector_and_validates(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/views/salon-booking-create');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Book Appointment');

        $schema = $response->json('schema');
        $validatorErrors = app(SchemaValidator::class)->validate($schema);
        $this->assertEmpty($validatorErrors, 'SchemaValidator failed: ' . implode(', ', $validatorErrors));

        // Find customer_selector component in reservation card
        $components = $schema['components'] ?? [];
        $foundSelector = false;
        foreach ($components as $card) {
            foreach ($card['components'] ?? [] as $col) {
                foreach ($col['components'] ?? [] as $field) {
                    if (($field['type'] ?? '') === 'customer_selector') {
                        $foundSelector = true;
                        $this->assertSame('customer_id', $field['name']);
                        $this->assertSame('/api/tenant/customers/search', $field['search_endpoint']);
                        $this->assertSame('customer_name', $field['fields']['name_field']);
                        $this->assertSame('customer_phone', $field['fields']['phone_field']);
                    }
                }
            }
        }
        $this->assertTrue($foundSelector, 'customer_selector component not found in salon booking create schema.');
    }

    /**
     * Test 2: Customer search API returns matching CRM customers.
     */
    public function test_customer_search_returns_crm_customers(): void
    {
        Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Sophia Loren',
            'phone' => '+1 (555) 234-5678',
            'email' => 'sophia@example.com',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/customers/search?query=Sophia');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'customers');

        $this->assertSame('Sophia Loren', $response->json('customers.0.name'));
        $this->assertSame('+1 (555) 234-5678', $response->json('customers.0.phone'));
    }

    /**
     * Test 3: Booking appointment with client_name / client_phone aliases and customer_id
     * normalizes fields and redirects with date query parameter.
     */
    public function test_appointments_store_normalizes_client_aliases_and_redirects_with_date(): void
    {
        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Isabella Rossellini',
            'phone' => '+1 (555) 987-6543',
        ]);

        $payload = [
            'service_id' => $this->service->id,
            'specialist_id' => $this->specialist->id,
            'client_id' => $customer->id,
            'client_name' => 'Isabella Rossellini',
            'client_phone' => '+1 (555) 987-6543',
            'appointment_date' => '2026-09-15',
            'appointment_time' => '14:30',
            'advance_deposit' => '50.00',
            'deposit_payment_method' => 'card',
        ];

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/tenant/salon/appointments', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('action', 'toast_and_navigate')
            ->assertJsonPath('route', '/api/tenant/views/salon-calendar?date=2026-09-15');

        $this->assertDatabaseHas('salon_appointments', [
            'company_id' => $this->company->id,
            'customer_name' => 'Isabella Rossellini',
            'customer_phone' => '+1 (555) 987-6543',
            'product_id' => $this->service->id,
            'specialist_id' => $this->specialist->id,
            'advance_paid' => 50.00,
            'deposit_payment_method' => 'card',
        ]);
    }

    /**
     * Test 4: Calendar view dynamically queries appointments for selected date and includes date switcher.
     */
    public function test_calendar_view_queries_dynamic_date_and_renders_date_switcher(): void
    {
        $tz = $this->company->resolveTimezone();
        $targetDate = '2026-09-20';
        $startUtc = Carbon::createFromFormat('Y-m-d H:i', "{$targetDate} 10:00", $tz)->utc();

        SalonAppointment::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'appointment_number' => 'APT-TEST-001',
            'customer_name' => 'Dynamic Client',
            'customer_phone' => '+15550001111',
            'product_id' => $this->service->id,
            'specialist_id' => $this->specialist->id,
            'starts_at' => $startUtc,
            'ends_at' => $startUtc->copy()->addMinutes(60),
            'status' => 'scheduled',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/tenant/views/salon-calendar?date={$targetDate}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $schema = $response->json('schema');
        $validatorErrors = app(SchemaValidator::class)->validate($schema);
        $this->assertEmpty($validatorErrors);

        // Verify that the appointment appears for 2026-09-20
        $rawJson = json_encode($schema);
        $this->assertStringContainsString('Dynamic Client', $rawJson);
        $this->assertStringContainsString('Balayage & Blowdry', $rawJson);
        $this->assertStringContainsString('Yesterday', $rawJson);
        $this->assertStringContainsString('Today', $rawJson);
        $this->assertStringContainsString('Tomorrow', $rawJson);

        // Query another date with no appointments
        $emptyResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/views/salon-calendar?date=2026-10-01');

        $emptyResponse->assertOk();
        $emptyJson = json_encode($emptyResponse->json('schema'));
        $this->assertStringNotContainsString('Dynamic Client', $emptyJson);
        $this->assertStringContainsString('No appointments booked', $emptyJson);
    }

    /**
     * Test 5: Email OTP verification flow:
     * - Register tenant with OTP
     * - Verify OTP code
     * - Account activates and returns TenantApiKey token
     */
    public function test_email_otp_registration_and_verification_flow(): void
    {
        $branding = PlatformBranding::current();
        $branding->update([
            'otp_registration_enabled' => true,
        ]);

        $regPayload = [
            'store_name' => 'Aurora Spa',
            'name' => 'Aurora Founder',
            'email' => 'aurora@spa-test.com',
            'password' => 'secret12345',
            'phone' => '+15554443322',
        ];

        $regRes = $this->postJson('/api/auth/register', $regPayload);
        $regRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('action', 'navigate')
            ->assertJsonPath('route', '/api/tenant/views/verify-otp');

        $user = User::withoutGlobalScopes()->where('email', 'aurora@spa-test.com')->firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->verification_code);

        // Extract OTP or generate known OTP
        $otp = '654321';
        $user->update([
            'verification_code' => Hash::make($otp),
            'verification_code_expires_at' => now()->addMinutes(10),
        ]);
        if (Schema::hasTable('email_verifications')) {
            DB::table('email_verifications')->insert([
                'email' => 'aurora@spa-test.com',
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Verify OTP endpoint
        $verifyRes = $this->postJson('/api/auth/verify-email-otp', [
            'email' => 'aurora@spa-test.com',
            'otp' => $otp,
        ]);

        $verifyRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('action', 'navigate');

        $this->assertNotEmpty($verifyRes->json('token'), 'API key token should be issued upon OTP verification.');

        $user->refresh();
        $this->assertSame('approved', $user->status);
        $this->assertNotNull($user->email_verified_at);
    }

    /**
     * Test 6: Public SDUI auth views (verify-otp, login, register) are accessible without authentication
     * and pass SchemaValidator.
     */
    public function test_public_auth_views_accessible_without_token_and_valid(): void
    {
        // 1. verify-otp view
        $otpRes = $this->getJson('/api/tenant/views/verify-otp?email=test@example.com');
        $otpRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Verify Email OTP');
        $this->assertEmpty(app(SchemaValidator::class)->validate($otpRes->json('schema')));

        // 2. login view
        $loginRes = $this->getJson('/api/tenant/views/login');
        $loginRes->assertOk()
            ->assertJsonPath('success', true);
        $this->assertEmpty(app(SchemaValidator::class)->validate($loginRes->json('schema')));
        $loginJson = json_encode($loginRes->json('schema'), JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('/auth/google/redirect', $loginJson);
        $this->assertStringContainsString('/auth/facebook/redirect', $loginJson);

        // 3. register view
        $registerRes = $this->getJson('/api/tenant/views/auth-register');
        $registerRes->assertOk()
            ->assertJsonPath('success', true);
        $this->assertEmpty(app(SchemaValidator::class)->validate($registerRes->json('schema')));
        $regJson = json_encode($registerRes->json('schema'), JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('/auth/google/redirect', $regJson);
        $this->assertStringContainsString('/auth/facebook/redirect', $regJson);
    }

    /**
     * Test 7: Social auth redirect and mobile token endpoints.
     */
    public function test_social_auth_endpoints(): void
    {
        // Redirect endpoint JSON negotiation returns url
        $googleRedirect = $this->getJson('/auth/google/redirect');
        $googleRedirect->assertOk()
            ->assertJsonPath('success', true);
        $this->assertNotEmpty($googleRedirect->json('url'));

        $fbRedirect = $this->getJson('/auth/facebook/redirect');
        $fbRedirect->assertOk()
            ->assertJsonPath('success', true);
        $this->assertNotEmpty($fbRedirect->json('url'));

        // Mobile token validation
        $invalidTokenRes = $this->postJson('/api/auth/google/mobile-token', [
            'token' => 'invalid_dummy_token',
        ]);
        $this->assertFalse($invalidTokenRes->json('success') ?? true);
    }
}
