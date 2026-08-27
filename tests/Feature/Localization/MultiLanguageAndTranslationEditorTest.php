<?php

namespace Tests\Feature\Localization;

use App\Livewire\SuperAdmin\Languages\Index as SuperAdminLanguagesIndex;
use App\Livewire\Tenant\Languages\Index as TenantLanguagesIndex;
use App\Models\Company;
use App\Models\Language;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\PlatformBranding;
use App\Models\User;
use App\Services\Localization\LocalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MultiLanguageAndTranslationEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
    }

    protected function tearDown(): void
    {
        if (file_exists(storage_path('installed'))) {
            @unlink(storage_path('installed'));
        }
        parent::tearDown();
    }

    protected function createSuperAdmin(): PlatformAdmin
    {
        return PlatformAdmin::create([
            'name' => 'Root Super Admin',
            'email' => 'admin@platform.test',
            'password' => bcrypt('password123'),
            'role' => 'super_admin',
        ]);
    }

    protected function createTenantAdmin(): array
    {
        Plan::firstOrCreate(
            ['name' => 'professional'],
            [
                'display_name' => 'Professional',
                'price' => 199.00,
                'billing_cycle' => 'yearly',
                'active' => true,
                'is_featured' => true,
            ]
        );

        $company = Company::create([
            'id' => 'comp_'.bin2hex(random_bytes(8)),
            'name' => 'Global Retailers Ltd',
            'slug' => 'global-retail-'.bin2hex(random_bytes(4)),
            'email' => 'admin@globalretail.test',
            'currency' => 'USD',
            'status' => 'active',
            'plan_name' => 'professional',
            'language' => 'en',
        ]);

        $admin = User::create([
            'company_id' => $company->id,
            'name' => 'Store Owner',
            'login' => 'store_owner_'.bin2hex(random_bytes(3)),
            'email' => 'owner@globalretail.test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        return [$company, $admin];
    }

    public function test_default_platform_languages_are_populated_and_active(): void
    {
        $service = app(LocalizationService::class);
        $languages = $service->getActiveLanguages();

        $this->assertNotEmpty($languages);
        $codes = $languages->pluck('code')->toArray();

        $this->assertContains('en', $codes);
        $this->assertContains('es', $codes);
        $this->assertContains('fr', $codes);
        $this->assertContains('ar', $codes);
        $this->assertContains('hi', $codes);
    }

    public function test_locale_switcher_route_updates_session_and_user_locale_without_affecting_store_primary_language(): void
    {
        [$company, $admin] = $this->createTenantAdmin();

        $response = $this->actingAs($admin, 'web')
            ->get(route('locale.switch', 'es'));

        $response->assertRedirect();
        $this->assertSame('es', session('locale'));
        $this->assertSame('es', $admin->fresh()->locale);
        $this->assertSame('en', $company->fresh()->language); // Store primary language is isolated from individual user switcher
    }

    public function test_superadmin_can_add_translation_key_and_save_language_file(): void
    {
        $superAdmin = $this->createSuperAdmin();

        Livewire::actingAs($superAdmin, 'platform_web')
            ->test(SuperAdminLanguagesIndex::class)
            ->set('selectedLocale', 'en')
            ->set('newKey', 'Welcome to Modern POS')
            ->set('newValue', 'Welcome to Modern Point of Sale')
            ->call('addKey')
            ->assertHasNoErrors();

        $service = app(LocalizationService::class);
        $enTranslations = $service->getLanguageFileContent('en');

        $this->assertArrayHasKey('Welcome to Modern POS', $enTranslations);
        $this->assertSame('Welcome to Modern Point of Sale', $enTranslations['Welcome to Modern POS']);
    }

    public function test_superadmin_can_create_new_platform_language(): void
    {
        $superAdmin = $this->createSuperAdmin();

        Livewire::actingAs($superAdmin, 'platform_web')
            ->test(SuperAdminLanguagesIndex::class)
            ->set('newCode', 'nl')
            ->set('newName', 'Dutch')
            ->set('newNativeName', 'Nederlands')
            ->set('newFlag', '🇳🇱')
            ->set('newDirection', 'ltr')
            ->call('createLanguage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('languages', [
            'code' => 'nl',
            'name' => 'Dutch',
            'direction' => 'ltr',
        ]);
    }

    public function test_tenant_admin_can_save_store_specific_translation_overrides(): void
    {
        [$company, $admin] = $this->createTenantAdmin();

        Livewire::actingAs($admin, 'web')
            ->test(TenantLanguagesIndex::class)
            ->set('storeDefaultLanguage', 'es')
            ->call('saveStoreDefaultLanguage')
            ->assertHasNoErrors()
            ->set('selectedLocale', 'es')
            ->set('customKey', 'Receipt Greeting')
            ->set('customValue', '¡Gracias por su compra en Global Retail!')
            ->call('addCustomPhrase')
            ->assertHasNoErrors();

        $this->assertSame('es', $company->fresh()->language);

        $this->assertDatabaseHas('company_translations', [
            'company_id' => $company->id,
            'locale' => 'es',
            'key' => 'Receipt Greeting',
            'value' => '¡Gracias por su compra en Global Retail!',
        ]);

        $service = app(LocalizationService::class);
        $merged = $service->getMergedTranslations('es', $company->id);

        $this->assertSame('¡Gracias por su compra en Global Retail!', $merged['Receipt Greeting']);
    }

    public function test_landing_page_renders_language_switcher_options(): void
    {
        $branding = PlatformBranding::current();
        $branding->update(['landing_page_enabled' => true]);

        $response = $this->get('/');
        $response->assertOk();

        $response->assertSee('Select Language');
        $response->assertSee(route('locale.switch', 'en'));
        $response->assertSee(route('locale.switch', 'es'));
    }

    public function test_landing_page_renders_translated_content_when_switched_to_spanish(): void
    {
        $branding = PlatformBranding::current();
        $branding->update(['landing_page_enabled' => true]);

        $switch = $this->get(route('locale.switch', 'es'));
        $switch->assertRedirect();

        $response = $this->withSession(['locale' => 'es'])->get('/');
        $response->assertOk();

        $response->assertSee('Plataforma');
        $response->assertSee('Productos');
        $response->assertSee('Soluciones');
        $response->assertSee('Precios');
        $response->assertSee('Iniciar Sesión');
        $response->assertSee('Iniciar Prueba Gratis');
    }

    public function test_auth_views_render_translated_content_when_switched_to_french(): void
    {
        $loginResponse = $this->withSession(['locale' => 'fr'])->get('/tenant/login');
        $loginResponse->assertOk();
        $loginResponse->assertSee('Connexion magasin');
        $loginResponse->assertSee('Se connecter à la caisse du magasin');

        $registerResponse = $this->withSession(['locale' => 'fr'])->get('/tenant/register');
        $registerResponse->assertOk();
        $registerResponse->assertSee('Créez votre magasin');
        $registerResponse->assertSee('Essai gratuit de 14 jours');
    }
}
