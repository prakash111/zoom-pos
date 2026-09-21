<?php

namespace Tests\Feature;

use App\Http\Requests\Traits\NormalizesPhoneNumber;
use App\Livewire\SuperAdmin\Settings\Index as SettingsIndex;
use App\Models\Company;
use App\Models\ContactInquiry;
use App\Models\PlatformSystem;
use App\Models\User;
use App\Services\ContactFormService;
use App\Services\Localization\PlatformRegionalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class PhoneNormalizationAndDialCodeTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    private Company $company;
    private User $tenantAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'id' => 'comp_test_' . uniqid(),
            'name' => 'Test Mart',
            'slug' => 'test-mart',
            'status' => 'active',
            'country' => 'IN',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'is_seeding_complete' => true,
        ]);

        $this->tenantAdmin = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'admin@testmart.com',
            'role' => 'admin',
            'password' => Hash::make('secret123'),
        ]);
    }

    public function test_platform_regional_service_defaults_and_dial_codes(): void
    {
        PlatformRegionalService::setPlatformDefaults('INR', 'en', 'Asia/Kolkata', 'IN', '+91');

        $this->assertSame('IN', PlatformRegionalService::defaultCountryIso());
        $this->assertSame('+91', PlatformRegionalService::defaultDialCode());

        $defaults = PlatformRegionalService::getPlatformDefaults();
        $this->assertSame('IN', $defaults['default_country_iso']);
        $this->assertSame('+91', $defaults['default_country_code']);

        // Test currency to country and dial code suggestions
        $this->assertSame('IN', PlatformRegionalService::countryForCurrency('INR'));
        $this->assertSame('+91', PlatformRegionalService::dialCodeForCountry('IN'));

        $this->assertSame('US', PlatformRegionalService::countryForCurrency('USD'));
        $this->assertSame('+1', PlatformRegionalService::dialCodeForCountry('US'));

        $this->assertSame('AE', PlatformRegionalService::countryForCurrency('AED'));
        $this->assertSame('+971', PlatformRegionalService::dialCodeForCountry('AE'));

        $this->assertSame('GB', PlatformRegionalService::countryForCurrency('GBP'));
        $this->assertSame('+44', PlatformRegionalService::dialCodeForCountry('GB'));
    }

    public function test_normalizes_phone_number_trait(): void
    {
        // Default platform is +91
        PlatformRegionalService::setPlatformDefaults('INR', 'en', 'Asia/Kolkata', 'IN', '+91');

        // Standard 10-digit number
        $this->assertSame('+919876543210', NormalizesPhoneNumber::normalizePhoneNumber('9876543210'));

        // Leading trunk zero
        $this->assertSame('+919876543210', NormalizesPhoneNumber::normalizePhoneNumber('09876543210'));

        // Formatted with spaces and hyphens
        $this->assertSame('+919876543210', NormalizesPhoneNumber::normalizePhoneNumber('+91 98765 43210'));
        $this->assertSame('+919876543210', NormalizesPhoneNumber::normalizePhoneNumber('+91 (98765) 43210'));

        // Starts with dial code without +
        $this->assertSame('+919876543210', NormalizesPhoneNumber::normalizePhoneNumber('919876543210'));

        // International 00 access code
        $this->assertSame('+919876543210', NormalizesPhoneNumber::normalizePhoneNumber('00919876543210'));

        // Non-default country (e.g. US +1)
        $this->assertSame('+15552345678', NormalizesPhoneNumber::normalizePhoneNumber('+1 (555) 234-5678'));

        // Empty / whitespace
        $this->assertNull(NormalizesPhoneNumber::normalizePhoneNumber(''));
        $this->assertNull(NormalizesPhoneNumber::normalizePhoneNumber('   '));
        $this->assertNull(NormalizesPhoneNumber::normalizePhoneNumber(null));
    }

    public function test_superadmin_general_settings_livewire_syncs_country_and_dial_code(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'general')
            ->set('platformDefaultCurrency', 'INR')
            ->assertSet('platformDefaultCountryIso', 'IN')
            ->assertSet('platformDefaultDialCode', '+91')
            ->set('platformDefaultCountryIso', 'US')
            ->assertSet('platformDefaultDialCode', '+1')
            ->call('saveGeneral')
            ->assertHasNoErrors();

        $this->assertSame('US', PlatformRegionalService::defaultCountryIso());
        $this->assertSame('+1', PlatformRegionalService::defaultDialCode());
    }

    public function test_landing_api_exposes_localization_defaults(): void
    {
        PlatformRegionalService::setPlatformDefaults('INR', 'en', 'Asia/Kolkata', 'IN', '+91');

        $response = $this->getJson('/api/v1/public/landing-config');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('localization.default_currency', 'INR')
            ->assertJsonPath('localization.default_country_code', '+91')
            ->assertJsonPath('localization.default_country_iso', 'IN')
            ->assertJsonPath('contact.default_country_code', '+91')
            ->assertJsonPath('contact.default_country_iso', 'IN');
    }

    public function test_bootstrap_api_exposes_localization_and_country_defaults(): void
    {
        PlatformRegionalService::setPlatformDefaults('INR', 'en', 'Asia/Kolkata', 'IN', '+91');

        $token = $this->postJson('/api/v1/pos/auth/login', [
            'email' => $this->tenantAdmin->email,
            'password' => 'secret123',
        ])->assertOk()->json('token');

        $response = $this->withToken($token)
            ->withHeaders(['X-Company-Id' => $this->company->id])
            ->getJson('/api/v1/pos/app/bootstrap?locale=en');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('localization.default_country_code', '+91')
            ->assertJsonPath('localization.default_country_iso', 'IN')
            ->assertJsonPath('system_info.default_country_code', '+91')
            ->assertJsonPath('tenant.default_country_code', '+91');
    }

    public function test_contact_form_submission_normalizes_phone_to_e164(): void
    {
        PlatformRegionalService::setPlatformDefaults('INR', 'en', 'Asia/Kolkata', 'IN', '+91');

        $payload = [
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'email' => 'rahul@example.com',
            'phone' => '09876543210',
            'phone_country' => '+91',
            'message' => 'Interested in retail POS solution.',
        ];

        $response = $this->postJson(route('contact.store'), $payload);
        $response->assertOk();

        $inquiry = ContactInquiry::latest('id')->first();
        $this->assertNotNull($inquiry);
        $this->assertSame('+919876543210', $inquiry->phone);
    }
}
