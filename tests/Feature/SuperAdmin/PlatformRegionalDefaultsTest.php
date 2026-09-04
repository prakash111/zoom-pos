<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Settings\Index as SettingsIndex;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\PlatformSystem;
use App\Models\User;
use App\Services\Localization\PlatformRegionalService;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class PlatformRegionalDefaultsTest extends TestCase
{
    use ActsAsPlatformAdmin, ActsAsTenantUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');

        Plan::firstOrCreate(['name' => 'trial'], [
            'display_name' => 'Free Trial',
            'price' => 0.00,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'duration_days' => 14,
            'features' => ['pos' => true, 'offline' => true],
            'limits' => ['products' => 500, 'users' => 5],
            'active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_superadmin_settings_view_displays_global_regional_card_and_options(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->get(route('superadmin.settings.index', ['tab' => 'general']));
        $response->assertOk();
        $response->assertSee('Global Default Localization & Region');
        $response->assertSee('Default Platform Currency');
        $response->assertSee('Default Platform Language');
        $response->assertSee('Default Platform Timezone');
        $response->assertSee('Inheritance &amp; Isolation Notice', false);

        // Redirect check for /admin/settings/regional
        $regionalRedirect = $this->get('/admin/settings/regional');
        $regionalRedirect->assertRedirect('/superadmin/settings?tab=general');
    }

    public function test_superadmin_can_save_platform_regional_defaults(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'general')
            ->set('appName', 'Smart Regional Platform')
            ->set('platformDefaultCurrency', 'INR')
            ->set('platformDefaultLanguage', 'hi')
            ->set('platformDefaultTimezone', 'Asia/Kolkata')
            ->call('saveGeneral')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $this->assertSame('INR', PlatformSystem::get('platform_default_currency'));
        $this->assertSame('hi', PlatformSystem::get('platform_default_language'));
        $this->assertSame('Asia/Kolkata', PlatformSystem::get('platform_default_timezone'));

        // Legacy keys stay synced
        $this->assertSame('INR', PlatformSystem::get('app_currency'));
        $this->assertSame('Asia/Kolkata', PlatformSystem::get('app_timezone'));
    }

    public function test_new_tenant_registration_inherits_superadmin_defaults_when_omitted(): void
    {
        PlatformRegionalService::setPlatformDefaults('INR', 'hi', 'Asia/Kolkata');

        $provisioner = app(TenantProvisioningService::class);
        $result = $provisioner->registerTenant([
            'store_name' => 'Royal Spices',
            'slug' => 'royal-spices-'.uniqid(),
            'owner_name' => 'Rajesh Sharma',
            'email' => 'rajesh@royalspices.test',
            'password' => 'secret123',
            'plan_name' => 'trial',
        ]);

        /** @var Company $company */
        $company = $result['company'];
        /** @var User $admin */
        $admin = $result['user'];

        $this->assertSame('INR', $company->currency);
        $this->assertSame('₹', $company->currency_symbol);
        $this->assertSame(2, (int) $company->currency_decimals);
        $this->assertSame('prefix', $company->currency_symbol_position);
        $this->assertSame('hi', $company->language);
        $this->assertSame('hi', $company->default_locale);
        $this->assertSame('Asia/Kolkata', $company->timezone);
        $this->assertSame('Asia/Kolkata', $company->resolveTimezone());
        $this->assertSame('hi', $admin->locale);
    }

    public function test_api_registration_inherits_superadmin_defaults_or_allows_overrides(): void
    {
        PlatformRegionalService::setPlatformDefaults('AED', 'ar', 'Asia/Dubai');

        // 1. Omitted regional params -> Inherits platform defaults
        $resDefault = $this->postJson('/api/v1/pos/register', [
            'store_name' => 'Dubai Gold Market',
            'slug' => 'dubai-gold-'.uniqid(),
            'name' => 'Ahmed Al-Maktoum',
            'email' => 'ahmed.'.uniqid().'@dubaigold.test',
            'password' => 'secret123',
            'phone' => '+971501234567',
        ]);

        $resDefault->assertCreated()->assertJsonPath('success', true);
        $companyDefault = Company::where('name', 'Dubai Gold Market')->latest('id')->firstOrFail();
        $this->assertSame('AED', $companyDefault->currency);
        $this->assertSame('د.إ', $companyDefault->currency_symbol);
        $this->assertSame('ar', $companyDefault->language);
        $this->assertSame('ar', $companyDefault->default_locale);
        $this->assertSame('Asia/Dubai', $companyDefault->timezone);

        // 2. Explicit params -> Honors tenant registration values
        $resCustom = $this->postJson('/api/v1/pos/register', [
            'store_name' => 'Parisian Boutique',
            'slug' => 'parisian-boutique-'.uniqid(),
            'name' => 'Marie Dubois',
            'email' => 'marie.'.uniqid().'@boutique.test',
            'password' => 'secret123',
            'phone' => '+33612345678',
            'currency' => 'EUR',
            'language' => 'fr',
            'timezone' => 'Europe/Paris',
        ]);

        $resCustom->assertCreated()->assertJsonPath('success', true);
        $companyCustom = Company::where('name', 'Parisian Boutique')->latest('id')->firstOrFail();
        $this->assertSame('EUR', $companyCustom->currency);
        $this->assertSame('€', $companyCustom->currency_symbol);
        $this->assertSame('fr', $companyCustom->language);
        $this->assertSame('fr', $companyCustom->default_locale);
        $this->assertSame('Europe/Paris', $companyCustom->timezone);
    }

    public function test_existing_tenants_are_isolated_and_never_overwritten_by_superadmin_updates(): void
    {
        // 1. Create existing tenant with baseline configuration
        $existing = Company::create([
            'name' => 'Classic NY Diner',
            'slug' => 'classic-ny-diner-'.uniqid(),
            'currency' => 'USD',
            'currency_symbol' => '$',
            'currency_decimals' => 2,
            'currency_symbol_position' => 'prefix',
            'language' => 'en',
            'default_locale' => 'en',
            'country' => 'US',
            'timezone' => 'America/New_York',
            'status' => 'active',
        ]);

        // 2. SuperAdmin changes platform-wide baseline to Brazil
        $this->actingAsSuperAdmin();
        Livewire::test(SettingsIndex::class)
            ->set('platformDefaultCurrency', 'BRL')
            ->set('platformDefaultLanguage', 'pt')
            ->set('platformDefaultTimezone', 'America/Sao_Paulo')
            ->call('saveGeneral')
            ->assertHasNoErrors();

        $this->assertSame('BRL', PlatformSystem::get('platform_default_currency'));

        // 3. Existing tenant retains their configuration intact
        $existing->refresh();
        $this->assertSame('USD', $existing->currency);
        $this->assertSame('$', $existing->currency_symbol);
        $this->assertSame('en', $existing->language);
        $this->assertSame('en', $existing->default_locale);
        $this->assertSame('America/New_York', $existing->timezone);
    }

    public function test_tenant_can_self_override_currency_financial_settings(): void
    {
        $company = Company::create([
            'name' => 'London Books',
            'slug' => 'london-books-'.uniqid(),
            'currency' => 'USD',
            'currency_symbol' => '$',
            'status' => 'active',
        ]);

        $admin = User::create([
            'company_id' => $company->id,
            'name' => 'Arthur Conan',
            'login' => 'arthur_'.uniqid(),
            'email' => 'arthur.'.uniqid().'@books.test',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMINISTRATOR,
            'status' => 'approved',
        ]);

        $token = $this->postJson('/api/v1/pos/auth/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ])->assertOk()->json('token');

        // Override currency to GBP without specifying symbol -> auto-populates '£'
        $response = $this->withToken($token)->putJson('/api/v1/pos/settings/financial', [
            'currency' => 'GBP',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('financial.currency', 'GBP')
            ->assertJsonPath('financial.currency_symbol', '£');

        $company->refresh();
        $this->assertSame('GBP', $company->currency);
        $this->assertSame('£', $company->currency_symbol);
    }

    public function test_tenant_can_self_override_language_and_timezone_profile_settings(): void
    {
        $company = Company::create([
            'name' => 'Tokyo Stationers',
            'slug' => 'tokyo-stationers-'.uniqid(),
            'currency' => 'JPY',
            'currency_symbol' => '¥',
            'country' => 'JP',
            'language' => 'en',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'status' => 'active',
        ]);

        $admin = User::create([
            'company_id' => $company->id,
            'name' => 'Kenji Sato',
            'login' => 'kenji_'.uniqid(),
            'email' => 'kenji.'.uniqid().'@tokyo.test',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMINISTRATOR,
            'locale' => 'en',
            'status' => 'approved',
        ]);

        $token = $this->postJson('/api/v1/pos/auth/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ])->assertOk()->json('token');

        // Self-service override timezone and language
        $response = $this->withToken($token)->putJson('/api/v1/pos/settings/profile', [
            'name' => 'Tokyo Stationers Flagship',
            'timezone' => 'Asia/Tokyo',
            'default_locale' => 'ja',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('profile.timezone', 'Asia/Tokyo')
            ->assertJsonPath('profile.resolved_timezone', 'Asia/Tokyo')
            ->assertJsonPath('profile.default_locale', 'ja');

        $company->refresh();
        $this->assertSame('Asia/Tokyo', $company->timezone);
        $this->assertSame('ja', $company->default_locale);
        $this->assertSame('ja', $company->language);

        $admin->refresh();
        $this->assertSame('ja', $admin->locale);
    }

    public function test_bootstrap_delivers_inherited_and_overridden_regional_configurations(): void
    {
        PlatformRegionalService::setPlatformDefaults('INR', 'hi', 'Asia/Kolkata');

        $provisioner = app(TenantProvisioningService::class);
        $result = $provisioner->registerTenant([
            'store_name' => 'Mumbai Electronics',
            'slug' => 'mumbai-elec-'.uniqid(),
            'owner_name' => 'Sunil Verma',
            'email' => 'sunil.'.uniqid().'@mumbaielec.test',
            'password' => 'secret123',
            'plan_name' => 'trial',
        ]);

        $company = $result['company'];
        $admin = $result['user'];

        $token = $this->postJson('/api/v1/pos/auth/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ])->assertOk()->json('token');

        // 1. Initial Bootstrap delivers platform defaults
        $bootRes = $this->withToken($token)->getJson('/api/v1/pos/app/bootstrap');
        $bootRes->assertOk()
            ->assertJsonPath('tenant.currency', 'INR')
            ->assertJsonPath('tenant.currency_symbol', '₹')
            ->assertJsonPath('tenant.currency_decimals', 2)
            ->assertJsonPath('tenant.currency_symbol_position', 'prefix')
            ->assertJsonPath('tenant.timezone', 'Asia/Kolkata')
            ->assertJsonPath('tenant.default_locale', 'hi')
            ->assertJsonPath('config.currency', 'INR')
            ->assertJsonPath('config.currency_symbol', '₹')
            ->assertJsonPath('config.timezone', 'Asia/Kolkata')
            ->assertJsonPath('config.default_locale', 'hi');

        // 2. Tenant self-overrides to Singapore Dollar & Timezone
        $this->withToken($token)->putJson('/api/v1/pos/settings/financial', ['currency' => 'SGD']);
        $this->withToken($token)->putJson('/api/v1/pos/settings/profile', [
            'name' => 'Mumbai Electronics Global',
            'timezone' => 'Asia/Singapore',
            'default_locale' => 'en',
        ]);

        // 3. Subsequent Bootstrap delivers tenant overridden values
        $bootRes2 = $this->withToken($token)->getJson('/api/v1/pos/app/bootstrap');
        $bootRes2->assertOk()
            ->assertJsonPath('tenant.currency', 'SGD')
            ->assertJsonPath('tenant.currency_symbol', 'S$')
            ->assertJsonPath('tenant.timezone', 'Asia/Singapore')
            ->assertJsonPath('tenant.default_locale', 'en')
            ->assertJsonPath('config.currency', 'SGD')
            ->assertJsonPath('config.currency_symbol', 'S$')
            ->assertJsonPath('config.timezone', 'Asia/Singapore');
    }

    public function test_sdui_views_deliver_declarative_regional_dropdowns(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $token = $this->postJson('/api/v1/pos/auth/login', [
            'email' => $admin->email,
            'password' => 'secret1234',
        ])->assertOk()->json('token');

        // 1. Financial view SDUI
        $finRes = $this->withToken($token)->getJson('/api/v1/pos/views/settings-financial');
        $finRes->assertOk()->assertJsonPath('view', 'settings-financial');
        $finSchema = json_encode($finRes->json('schema'), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Base Operating Currency', $finSchema);
        $this->assertStringContainsString('INR (₹)', $finSchema);
        $this->assertStringContainsString('USD ($)', $finSchema);
        $this->assertStringContainsString('EUR (€)', $finSchema);

        // 2. Profile view SDUI
        $profRes = $this->withToken($token)->getJson('/api/v1/pos/views/settings-profile');
        $profRes->assertOk()->assertJsonPath('view', 'settings-profile');
        $profSchema = json_encode($profRes->json('schema'));
        $this->assertStringContainsString('Store Primary Language', $profSchema);
        $this->assertStringContainsString('Store Timezone', $profSchema);

        // 3. Dedicated Localization view SDUI
        $locRes = $this->withToken($token)->getJson('/api/v1/pos/views/settings-localization');
        $locRes->assertOk()
            ->assertJsonPath('view', 'settings-localization')
            ->assertJsonPath('schema.title', 'Localization & Region');
    }
}
