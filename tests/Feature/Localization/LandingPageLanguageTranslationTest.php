<?php

namespace Tests\Feature\Localization;

use App\Livewire\SuperAdmin\Languages\Index as SuperAdminLanguagesIndex;
use App\Models\PlatformAdmin;
use App\Models\PlatformBranding;
use App\Models\SystemTranslation;
use App\Services\Localization\LocalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class LandingPageLanguageTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformBranding::current()->update([
            'landing_page_enabled' => true,
        ]);

        \App\Models\Plan::create([
            'name' => 'starter',
            'display_name' => 'Starter',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'price' => 19.00,
            'currency' => 'USD',
            'invoice_limit' => -1,
            'product_limit' => 1000,
            'device_limit' => 2,
            'staff_limit' => 3,
            'active' => true,
            'is_active' => true,
        ]);
    }

    protected function createSuperAdmin(): PlatformAdmin
    {
        return PlatformAdmin::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    public function test_landing_page_renders_in_default_english(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Monthly Billing');
        $response->assertSee('Start Free Trial');
    }

    public function test_landing_page_switches_to_spanish_and_renders_translated_strings(): void
    {
        $switch = $this->get(route('locale.switch', 'es'));
        $switch->assertRedirect();
        $this->assertEquals('es', session('locale'));

        $response = $this->withSession(['locale' => 'es'])->get('/');
        $response->assertStatus(200);
        $response->assertSee('Facturación mensual');
        $response->assertSee('Facturas ilimitadas');
    }

    public function test_landing_page_switches_to_french_and_renders_translated_strings(): void
    {
        $response = $this->withSession(['locale' => 'fr'])->get('/');
        $response->assertStatus(200);
        $response->assertSee('Facturation mensuelle');
        $response->assertSee('Factures illimitées');
    }

    public function test_landing_page_switches_to_arabic_and_renders_translated_strings(): void
    {
        $response = $this->withSession(['locale' => 'ar'])->get('/');
        $response->assertStatus(200);
        $response->assertSee('الفوترة الشهرية');
        $response->assertSee('فواتير غير محدودة');
    }

    public function test_superadmin_translation_filtering_returns_all_keys_across_locales(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $service = app(LocalizationService::class);

        $component = Livewire::actingAs($superAdmin, 'platform_web')
            ->test(SuperAdminLanguagesIndex::class)
            ->call('selectLocale', 'es')
            ->set('searchQuery', 'Facturas');

        $component->assertSee('Facturas ilimitadas');
    }

    public function test_superadmin_saving_translation_persists_to_database_and_invalidates_landing_cache(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $service = app(LocalizationService::class);

        $versionBefore = Cache::get('landing_page_cache_version', 1);

        Livewire::actingAs($superAdmin, 'platform_web')
            ->test(SuperAdminLanguagesIndex::class)
            ->set('selectedLocale', 'es')
            ->call('updateKey', 'Unlimited Invoices', 'Facturación Sin Límites Total')
            ->call('saveTranslations');

        $this->assertDatabaseHas('system_translations', [
            'locale' => 'es',
            'key' => 'Unlimited Invoices',
            'value' => 'Facturación Sin Límites Total',
        ]);

        $versionAfter = Cache::get('landing_page_cache_version', 1);
        $this->assertGreaterThan($versionBefore, $versionAfter);

        // Cleanup
        $es = $service->getLanguageFileContent('es');
        $es['Unlimited Invoices'] = 'Facturas ilimitadas';
        $service->saveLanguageFileContent('es', $es);
    }
}
