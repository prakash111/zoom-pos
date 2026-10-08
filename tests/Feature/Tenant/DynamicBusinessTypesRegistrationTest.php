<?php

namespace Tests\Feature\Tenant;

use App\Http\Requests\Tenant\RegisterTenantRequest;
use App\Livewire\Auth\TenantRegister;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlatformSystem;
use App\Models\SduiModule;
use App\Models\User;
use App\Services\Module\ModuleManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

class DynamicBusinessTypesRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');

        // Seed default plans
        Plan::firstOrCreate(['name' => 'trial'], [
            'display_name' => 'Trial',
            'billing_cycle' => 'trial',
            'duration_days' => 14,
            'price' => 0.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        // Seed pharmacy, repairtechnician, and salon modules in sdui_modules
        SduiModule::firstOrCreate(['slug' => 'pharmacy'], [
            'name' => 'Pharmacy',
            'version' => '1.0.0',
            'source_type' => 'package',
            'package_path' => 'pharmacy',
            'is_active' => true,
            'registration_allowed' => true,
            'requires_license' => true,
            'license_status' => 'active',
            'type' => 'core',
        ]);

        SduiModule::firstOrCreate(['slug' => 'repairtechnician'], [
            'name' => 'Repair Technician',
            'version' => '1.0.0',
            'source_type' => 'package',
            'package_path' => 'repairtechnician',
            'is_active' => true,
            'registration_allowed' => true,
            'requires_license' => true,
            'license_status' => 'active',
            'type' => 'core',
        ]);

        SduiModule::firstOrCreate(['slug' => 'salon'], [
            'name' => 'Salon & Bookings',
            'version' => '1.0.0',
            'source_type' => 'package',
            'package_path' => 'salon',
            'is_active' => true,
            'registration_allowed' => true,
            'requires_license' => true,
            'license_status' => 'active',
            'type' => 'core',
        ]);

        PlatformSystem::set('allowed_registration_modes', json_encode([
            'retail', 'restaurant', 'pharmacy', 'repair_technician', 'service_booking'
        ]));
    }

    public function test_module_manager_service_returns_available_business_types_including_pharmacy(): void
    {
        $service = app(ModuleManagerService::class);
        $types = $service->getAvailableBusinessTypes();

        $ids = array_column($types, 'id');
        $this->assertContains('retail', $ids);
        $this->assertContains('pharmacy', $ids);
        $this->assertContains('restaurant', $ids);
        $this->assertContains('repair', $ids);
        $this->assertContains('salon', $ids);

        $pharmacy = $service->getBusinessType('pharmacy');
        $this->assertNotNull($pharmacy);
        $this->assertSame('Pharmacy & Healthcare', $pharmacy['name']);
        $this->assertTrue($pharmacy['enabled']);
    }

    public function test_public_api_endpoints_return_dynamic_business_types(): void
    {
        $response = $this->getJson('/api/public/business-types');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => 'pharmacy', 'name' => 'Pharmacy & Healthcare'])
            ->assertJsonFragment(['id' => 'retail', 'name' => 'Retail']);

        $v1Response = $this->getJson('/api/v1/public/business-types');
        $v1Response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => 'pharmacy', 'name' => 'Pharmacy & Healthcare']);
    }

    public function test_tenant_registration_screen_renders_all_active_verticals_including_pharmacy(): void
    {
        $response = $this->get('/tenant/register');
        $response->assertOk();
        $response->assertSee('Pharmacy &amp; Healthcare', false);
        $response->assertSee('Retail');
        $response->assertSee('Cafe &amp; Restaurant', false);
        $response->assertSee('Repair Technician');
        $response->assertSee('Salon &amp; Bookings', false);

        Livewire::test(TenantRegister::class)
            ->assertSee('Pharmacy & Healthcare')
            ->assertSee('Retail')
            ->assertSee('Cafe & Restaurant')
            ->assertSee('Repair Technician')
            ->assertSee('Salon & Bookings');
    }

    public function test_registering_with_pharmacy_mode_sets_operating_mode_and_activates_features(): void
    {
        Livewire::test(TenantRegister::class)
            ->set('storeName', 'CarePlus Health Pharmacy')
            ->set('ownerName', 'Dr. Sarah Connor')
            ->set('email', 'sarah@carepluspharmacy.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('posMode', 'pharmacy')
            ->set('planName', 'trial')
            ->call('register')
            ->assertHasNoErrors();

        $company = Company::where('email', 'sarah@carepluspharmacy.com')->first();
        $this->assertNotNull($company);
        $this->assertSame('pharmacy', $company->pos_mode);
        $this->assertContains('pharmacy', $company->licensedModuleKeys());
        $this->assertTrue($company->hasModule('pharmacy'));

        // Verify user was created
        $user = User::where('email', 'sarah@carepluspharmacy.com')->first();
        $this->assertNotNull($user);
        $this->assertSame($company->id, $user->company_id);
    }

    public function test_register_tenant_request_validates_dynamic_verticals(): void
    {
        $service = app(ModuleManagerService::class);
        $request = new RegisterTenantRequest();
        $rules = $request->rules($service);

        $validData = [
            'store_name' => 'City Care Dispensary',
            'email' => 'dispensary@citycare.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'operating_mode' => 'pharmacy',
        ];

        $validator = Validator::make($validData, $rules);
        $this->assertFalse($validator->fails(), 'Validation should pass for pharmacy mode: ' . json_encode($validator->errors()->all()));

        $invalidData = array_merge($validData, ['operating_mode' => 'non_existent_vertical_xyz']);
        $validatorInvalid = Validator::make($invalidData, $rules);
        $this->assertTrue($validatorInvalid->fails(), 'Validation should reject non-existent vertical');
    }

    public function test_api_registration_with_pharmacy_succeeds_and_sets_pharmacy_mode(): void
    {
        $payload = [
            'store_name' => 'Metro Chemist & Meds',
            'name' => 'David Chemist',
            'email' => 'david@metrochemist.com',
            'password' => 'SecurePass123',
            'pos_mode' => 'pharmacy',
            'plan_name' => 'trial',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);
        $response->assertCreated()
            ->assertJsonPath('success', true);

        $company = Company::where('email', 'david@metrochemist.com')->first();
        $this->assertNotNull($company);
        $this->assertSame('pharmacy', $company->pos_mode);
        $this->assertContains('pharmacy', $company->licensedModuleKeys());
    }
}
