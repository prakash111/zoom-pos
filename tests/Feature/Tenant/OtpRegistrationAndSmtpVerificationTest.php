<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Auth\TenantRegister;
use App\Livewire\Auth\VerifyOtp;
use App\Mail\OtpVerificationMail;
use App\Models\Company;
use App\Models\PendingRegistration;
use App\Models\Plan;
use App\Models\PlatformBranding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class OtpRegistrationAndSmtpVerificationTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');

        Plan::firstOrCreate(['name' => 'trial'], [
            'display_name' => 'Trial',
            'billing_cycle' => 'trial',
            'duration_days' => 14,
            'price' => 0.00,
            'currency' => 'USD',
            'active' => true,
        ]);
        Plan::firstOrCreate(['name' => 'starter'], [
            'display_name' => 'Starter',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'price' => 19.00,
            'currency' => 'USD',
            'active' => true,
        ]);
        Plan::firstOrCreate(['name' => 'professional'], [
            'display_name' => 'Professional',
            'billing_cycle' => 'yearly',
            'duration_days' => 365,
            'price' => 199.00,
            'currency' => 'USD',
            'active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_when_smtp_is_not_configured_registration_does_not_require_otp(): void
    {
        Mail::fake();

        // Ensure SMTP is NOT configured
        $branding = PlatformBranding::current();
        $branding->update([
            'smtp_host' => null,
            'smtp_username' => null,
            'smtp_password' => null,
        ]);

        $this->assertFalse($branding->fresh()->isSmtpConfigured());

        Livewire::test(TenantRegister::class)
            ->set('storeName', 'Direct Store')
            ->set('slug', 'direct-store')
            ->set('posMode', 'general')
            ->set('ownerName', 'John Owner')
            ->set('email', 'john@directstore.test')
            ->set('password', 'Secret123!')
            ->set('password_confirmation', 'Secret123!')
            ->set('planName', 'trial')
            ->call('register')
            ->assertRedirect(route('tenant.dashboard'));

        // Company and User exist immediately
        $this->assertDatabaseHas('companies', ['slug' => 'direct-store']);
        $this->assertDatabaseHas('users', ['email' => 'john@directstore.test']);
        $this->assertDatabaseCount('pending_registrations', 0);

        // No verification email was sent
        Mail::assertNothingSent();
    }

    public function test_when_smtp_is_configured_registration_requires_otp_verification(): void
    {
        Mail::fake();

        // Configure SMTP in SuperAdmin
        $branding = PlatformBranding::current();
        $branding->update([
            'smtp_host' => 'smtp.mailtrap.io',
            'smtp_port' => 2525,
            'smtp_username' => 'smtp-user',
            'smtp_password' => 'smtp-pass',
            'smtp_from_address' => 'noreply@platform.test',
            'smtp_from_name' => 'Platform Security',
        ]);

        $this->assertTrue($branding->fresh()->isSmtpConfigured());

        $component = Livewire::test(TenantRegister::class)
            ->set('storeName', 'Secure Store')
            ->set('slug', 'secure-store')
            ->set('posMode', 'general')
            ->set('ownerName', 'Alice Admin')
            ->set('email', 'alice@securestore.test')
            ->set('password', 'Secret123!')
            ->set('password_confirmation', 'Secret123!')
            ->set('planName', 'trial')
            ->call('register');

        // Should transition to step 2 (OTP verification), NOT redirect yet
        $component->assertSet('step', 2)
            ->assertSee('Verify Your Email')
            ->assertSee('alice@securestore.test');

        // Store is NOT yet provisioned in database
        $this->assertDatabaseMissing('companies', ['slug' => 'secure-store']);
        $this->assertDatabaseMissing('users', ['email' => 'alice@securestore.test']);

        // Pending registration record was saved
        $pending = PendingRegistration::where('email', 'alice@securestore.test')->firstOrFail();
        $this->assertNotNull($pending->otp_hash);

        // Mail was sent
        Mail::assertSent(OtpVerificationMail::class, function ($mail) {
            return $mail->hasTo('alice@securestore.test');
        });

        // Test submitting invalid OTP fails
        $component->set('otp', '000000')
            ->call('verifyOtp')
            ->assertSee('Invalid 6-digit verification code');

        $this->assertDatabaseMissing('companies', ['slug' => 'secure-store']);

        // Now set the valid OTP by overriding the pending hash
        $testOtp = '123456';
        $pending->update(['otp_hash' => Hash::make($testOtp)]);

        // Submit correct OTP
        $component->set('otp', $testOtp)
            ->call('verifyOtp')
            ->assertRedirect(route('tenant.dashboard'));

        // Store and user now exist and user is marked verified
        $company = Company::where('slug', 'secure-store')->firstOrFail();
        $user = User::where('email', 'alice@securestore.test')->firstOrFail();
        $this->assertSame($company->id, $user->company_id);
        $this->assertNotNull($user->email_verified_at);

        // Pending registration cleared
        $this->assertDatabaseMissing('pending_registrations', ['email' => 'alice@securestore.test']);
    }

    public function test_when_smtp_is_configured_unverified_logged_in_user_is_redirected_to_verify_otp(): void
    {
        Mail::fake();

        // Configure SMTP in SuperAdmin
        $branding = PlatformBranding::current();
        $branding->update([
            'smtp_host' => 'smtp.mailtrap.io',
            'smtp_port' => 2525,
        ]);

        $company = Company::create([
            'name' => 'Demo Outlet',
            'slug' => 'demo-outlet',
            'status' => 'active',
            'plan_name' => 'trial',
            'expires_at' => now()->addDays(14),
        ]);

        $unverifiedUser = User::create([
            'company_id' => $company->id,
            'name' => 'Unverified User',
            'login' => 'unverified_user',
            'email' => 'unverified@demooutlet.test',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_ADMINISTRATOR,
            'status' => 'approved',
            'email_verified_at' => null, // Not verified
        ]);

        // Attempting to access dashboard with unverified email when SMTP is active redirects to verify-otp
        $response = $this->actingAs($unverifiedUser, 'web')->get('/tenant');
        $response->assertRedirect(route('tenant.verify_otp'));

        // Accessing the verify-otp route directly displays verification UI
        $verifyResponse = $this->actingAs($unverifiedUser, 'web')->get('/tenant/verify-otp');
        $verifyResponse->assertOk();
        $verifyResponse->assertSee('Verify Your Account');
        $verifyResponse->assertSee('unverified@demooutlet.test');

        // Test OTP verification on verify-otp screen
        $pending = PendingRegistration::where('email', 'unverified@demooutlet.test')->firstOrFail();
        $testCode = '654321';
        $pending->update(['otp_hash' => Hash::make($testCode)]);

        Livewire::actingAs($unverifiedUser, 'web')
            ->test(VerifyOtp::class)
            ->set('otp', $testCode)
            ->call('verify')
            ->assertRedirect(route('tenant.dashboard'));

        $this->assertNotNull($unverifiedUser->fresh()->email_verified_at);

        // Now that email is verified, accessing dashboard succeeds
        $dashboardResponse = $this->actingAs($unverifiedUser->fresh(), 'web')->get('/tenant');
        $dashboardResponse->assertOk();
    }

    public function test_when_smtp_is_not_configured_unverified_user_can_access_dashboard_directly(): void
    {
        // Ensure SMTP is NOT configured
        $branding = PlatformBranding::current();
        $branding->update([
            'smtp_host' => null,
        ]);

        $company = Company::create([
            'name' => 'No Smtp Store',
            'slug' => 'no-smtp-store',
            'status' => 'active',
            'plan_name' => 'trial',
            'expires_at' => now()->addDays(14),
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Store Staff',
            'login' => 'store_staff',
            'email' => 'staff@nosmtp.test',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_ADMINISTRATOR,
            'status' => 'approved',
            'email_verified_at' => null, // Even if null, no OTP is required
        ]);

        // When SMTP is not configured, user can access dashboard directly without redirect
        $response = $this->actingAs($user, 'web')->get('/tenant');
        $response->assertOk();
    }
}
