<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Settings\Index as SettingsIndex;
use App\Models\PlatformBranding;
use App\Models\SaaSPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class LandingDarkBgCustomizerTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    public function test_superadmin_can_save_landing_dark_bg_via_controller_endpoint(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->post(route('superadmin.theme.customizer.save'), [
            'landing_dark_bg' => '#064e3b',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame('#064e3b', setting('landing_dark_bg'));

        if (Schema::hasTable('system_settings')) {
            $raw = DB::table('system_settings')->where('key', 'superadmin_theme_customization')->value('value');
            $this->assertNotNull($raw);
            $config = json_decode($raw, true);
            $this->assertSame('#064e3b', $config['landing_dark_bg']);
        }
    }

    public function test_superadmin_can_save_landing_dark_bg_via_livewire_save_appearance(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SettingsIndex::class)
            ->call('saveAppearance', [
                'layout' => 'slim',
                'position' => 'left',
                'mode' => 'docked',
                'customBg' => '',
                'uiAccentColor' => '#4f46e5',
                'navTextColor' => '#ffffff',
                'navTextActiveColor' => '#60a5fa',
                'visibleItems' => ['dashboard', 'tenants', 'plans'],
                'landingDarkBg' => '#064e3b',
            ])
            ->assertDispatched('appearance-defaults-saved')
            ->assertDispatched('notify');

        $this->assertSame('#064e3b', setting('landing_dark_bg'));
        $this->assertSame('#064e3b', appearance_defaults()['landingDarkBg']);

        if (Schema::hasTable('system_settings')) {
            $raw = DB::table('system_settings')->where('key', 'superadmin_theme_customization')->value('value');
            $config = json_decode($raw, true);
            $this->assertSame('#064e3b', $config['landing_dark_bg']);
        }
    }

    public function test_public_landing_page_renders_custom_dark_bg_and_mission_pricing_sections(): void
    {
        PlatformBranding::firstOrCreate([], [
            'platform_name' => 'Zoom POS',
            'landing_page_enabled' => true,
        ]);

        SaaSPlan::create([
            'name' => 'starter',
            'display_name' => 'Starter Plan',
            'price' => 29,
            'currency' => '$',
            'billing_cycle' => 'monthly',
            'is_active' => true,
            'is_popular' => true,
        ]);

        // Save custom deep emerald background
        $this->actingAsSuperAdmin();
        $this->post(route('superadmin.theme.customizer.save'), [
            'landing_dark_bg' => '#064e3b',
        ]);

        // Clear session auth to view as guest
        auth('platform_web')->logout();
        auth('web')->logout();

        $response = $this->get('/');
        $response->assertStatus(200);

        // Verify CSS variable injection
        $response->assertSee('--landing-dark-bg: #064e3b', false);
        $response->assertSee('.landing-dark-section', false);

        // Verify sections carry the landing-dark-section class
        $response->assertSee('id="about"', false);
        $response->assertSee('id="pricing"', false);
    }
}
