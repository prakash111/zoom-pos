<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Settings\Index as SettingsIndex;
use App\Models\Page;
use App\Models\PlatformBranding;
use App\Models\PlatformSystem;
use App\Models\SduiModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class TabbedSettingsAndAppearanceTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    public function test_superadmin_settings_tabbed_page_renders_all_tabs_cleanly(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->get(route('superadmin.settings.index'));
        $response->assertOk();
        $response->assertSee('Platform & System Settings');
        $response->assertSee('General & Platform');
        $response->assertSee('SMTP & Email');
        $response->assertSee('White-label & Branding');
        $response->assertSee('Custom Pages & CMS');
        $response->assertSee('Navigation & Appearance');
    }

    public function test_general_tab_saves_configuration_and_maintenance_mode(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'general')
            ->set('appName', 'Smart Super SaaS')
            ->set('appCurrency', 'EUR')
            ->set('appTimezone', 'Europe/Paris')
            ->set('maintenanceMode', true)
            ->set('maintenanceMessage', 'Upgrading servers...')
            ->set('minClientBuildVersion', '1.2.0')
            ->set('appVersion', '2.0.0')
            ->call('saveGeneral')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $this->assertSame('Smart Super SaaS', PlatformSystem::get('app_name'));
        $this->assertSame('EUR', PlatformSystem::get('app_currency'));
        $this->assertSame('Europe/Paris', PlatformSystem::get('app_timezone'));
        $this->assertSame('1', PlatformSystem::get('maintenance_mode'));
        $this->assertSame('Upgrading servers...', PlatformSystem::get('maintenance_message'));
    }

    public function test_general_tab_drops_unknown_and_unpurchased_store_type_keys(): void
    {
        $this->actingAsSuperAdmin();

        // retail is free; pharmacy is premium + not licensed; ghost is unknown.
        Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'general')
            ->set('appName', 'X')
            ->set('appCurrency', 'USD')
            ->set('appTimezone', 'UTC')
            ->set('minClientBuildVersion', '1.0.0')
            ->set('appVersion', '1.0.0')
            ->set('enabledRegistrationModules', ['retail', 'restaurant', 'pharmacy', 'ghost_module_xyz'])
            ->call('saveGeneral')
            ->assertHasNoErrors();

        $saved = json_decode((string) PlatformSystem::get('allowed_registration_modes'), true);
        $this->assertContains('retail', $saved);
        $this->assertContains('restaurant', $saved);
        $this->assertNotContains('pharmacy', $saved);       // premium, not purchased
        $this->assertNotContains('ghost_module_xyz', $saved); // unknown
    }

    public function test_a_licensed_premium_vertical_can_be_enabled_as_a_store_type(): void
    {
        $this->actingAsSuperAdmin();

        SduiModule::create([
            'name' => 'Pharmacy', 'slug' => 'pharmacy', 'source_type' => 'package',
            'package_path' => 'pharmacy', 'installed_at' => now(), 'is_active' => true,
            'requires_license' => true, 'license_status' => 'active',
            'navigation' => [], 'features' => [],
        ]);

        Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'general')
            ->set('appName', 'X')
            ->set('appCurrency', 'USD')
            ->set('appTimezone', 'UTC')
            ->set('minClientBuildVersion', '1.0.0')
            ->set('appVersion', '1.0.0')
            ->set('enabledRegistrationModules', ['retail', 'pharmacy'])
            ->call('saveGeneral')
            ->assertHasNoErrors();

        $saved = json_decode((string) PlatformSystem::get('allowed_registration_modes'), true);
        $this->assertContains('pharmacy', $saved);
    }

    public function test_smtp_tab_saves_and_encrypts_credentials(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'smtp')
            ->set('smtpHost', 'smtp.mailtrap.io')
            ->set('smtpPort', 2525)
            ->set('smtpUsername', 'user_mailtrap')
            ->set('smtpPassword', 'secret_pass_123')
            ->set('smtpFromAddress', 'support@myplatform.com')
            ->set('smtpFromName', 'Platform Support')
            ->call('saveSmtp')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $branding = PlatformBranding::current();
        $this->assertSame('smtp.mailtrap.io', $branding->smtp_host);
        $this->assertSame(2525, $branding->smtp_port);
        $this->assertSame('secret_pass_123', $branding->smtp_password);
        $this->assertSame('support@myplatform.com', $branding->smtp_from_address);
    }

    public function test_branding_tab_saves_white_label_and_landing_options(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'branding')
            ->set('platformName', 'ZoomNearby Cloud POS')
            ->set('logoUrl', 'https://example.com/brand-logo.png')
            ->set('faviconUrl', 'https://example.com/brand-fav.ico')
            ->set('primaryColor', '#2563eb')
            ->set('superadminSidebarColor', '#1e1b4b')
            ->set('landingPrimaryColor', '#10b981')
            ->set('landingHeroTitle', 'Ultimate Point of Sale')
            ->set('otpRegistrationEnabled', true)
            ->call('saveBranding')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $branding = PlatformBranding::current();
        $this->assertSame('ZoomNearby Cloud POS', $branding->platform_name);
        $this->assertSame('https://example.com/brand-logo.png', $branding->logo_url);
        $this->assertSame('#2563eb', $branding->primary_color);
        $this->assertTrue($branding->otp_registration_enabled);
    }

    public function test_branding_tab_saves_custom_features_and_testimonials(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'branding')
            ->set('platformName', 'ZoomNearby Cloud POS')
            ->set('landingFeatures', [
                ['icon' => '🚀', 'title' => 'Blazing Checkout', 'body' => "Sub-second scans.\nOffline queue."],
                ['icon' => '', 'title' => '', 'body' => ''], // blank row is dropped
            ])
            ->set('landingTestimonials', [
                ['quote' => 'Cut our closing time in half.', 'name' => 'Dana Lee', 'role' => 'COO · Northwind Retail'],
            ])
            ->call('saveBranding')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $branding = PlatformBranding::current();

        $this->assertCount(1, $branding->landing_features);
        $this->assertSame('Blazing Checkout', $branding->landing_features[0]['title']);
        $this->assertSame('🚀', $branding->landing_features[0]['icon']);

        $this->assertCount(1, $branding->landing_testimonials);
        $this->assertSame('Dana Lee', $branding->landing_testimonials[0]['name']);

        // The accessors now return the authored lists.
        $this->assertSame('Blazing Checkout', $branding->landingFeatures()[0]['title']);
        $this->assertSame('Dana Lee', $branding->landingTestimonials()[0]['name']);
        $this->assertSame('DL', PlatformBranding::testimonialInitials('Dana Lee'));
    }

    public function test_feature_and_testimonial_lists_fall_back_to_defaults_when_empty(): void
    {
        $branding = PlatformBranding::current();
        $branding->update(['landing_features' => null, 'landing_testimonials' => null]);

        $this->assertNotEmpty($branding->fresh()->landingFeatures());
        $this->assertNotEmpty($branding->fresh()->landingTestimonials());
    }

    public function test_pages_cms_tab_allows_searching_and_deleting_pages(): void
    {
        $this->actingAsSuperAdmin();

        $page = Page::create([
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'content' => '<p>Your privacy is respected.</p>',
            'published' => true,
        ]);

        Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'pages')
            ->assertSee('Privacy Policy')
            ->call('deletePage', $page->id)
            ->assertDispatched('notify');

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    public function test_navigation_and_appearance_tab_renders_all_controls(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'appearance')
            ->assertSee('Navigation & Layout Customization')
            ->assertSee('Menu Layout Structure')
            ->assertSee('Navigation Menu Item Text Colors & Typography')
            ->assertSee('Default Inactive Text Color')
            ->assertSee('Active Highlight Text Color')
            ->assertSee('Dashboard Hero & UI Accent Color')
            ->assertSee('Dock Navigation Background')
            ->assertSee('Docking Screen Position')
            ->assertSee('Visible Dock Items (Pinning & Presets)');
    }

    public function test_top_right_profile_dropdown_is_streamlined(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->get(route('superadmin.settings.index'));

        // Profile identity & streamlined actions
        $response->assertSee('Tenant Store Portal');
        $response->assertSee('Reset Menu Position');
        $response->assertSee('Sign Out');

        // Header should have CSS variables for nav item typography
        $response->assertSee('--nav-item-color', false);
        $response->assertSee('--nav-item-active-color', false);
    }

    public function test_landing_page_theme_switcher_renders_options_and_saves_theme_with_cache_invalidation(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'whitelabel')
            ->assertSee('Landing Page Theme')
            ->assertSee('Modern Cloud POS')
            ->assertSee('Enterprise Showcase')
            ->assertSee('Minimal Conversion')
            ->assertSee('Dark Studio POS')
            ->call('setLandingTheme', 'theme_enterprise')
            ->assertSet('landingTheme', 'theme_enterprise')
            ->assertDispatched('toast')
            ->assertDispatched('notify');

        $this->assertSame('theme_enterprise', setting('landing_page_theme'));
        $this->assertNull(cache()->get('app_landing_page_theme'));

        // Theme selection is omitted from Appearance tab (consolidated in White-label & Branding)
        Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'appearance')
            ->assertDontSee('1. Public Landing Page Theme & Layout', false);
    }

    public function test_superadmin_can_save_platform_wide_appearance_defaults(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SettingsIndex::class)
            ->call('saveAppearance', [
                'layout' => 'expanded',
                'position' => 'right',
                'mode' => 'docked',
                'customBg' => '#0f172a',
                'uiAccentColor' => '#123456',
                'navTextColor' => '#eeeeee',
                'navTextActiveColor' => '#abcdef',
                'visibleItems' => ['dashboard', 'tenants', 'invalid-item'],
            ])
            ->assertDispatched('appearance-defaults-saved')
            ->assertDispatched('notify');

        $this->assertSame('expanded', setting('appearance_nav_layout'));
        $this->assertSame('right', setting('appearance_nav_position'));
        $this->assertSame(['dashboard', 'tenants'], appearance_defaults()['visibleItems']);
        $this->assertNotEmpty(setting('appearance_defaults_version'));
    }

    public function test_social_login_is_managed_from_platform_settings_and_preserves_stored_secret(): void
    {
        $this->actingAsSuperAdmin();
        PlatformSystem::set('social_google_client_secret', 'existing-secret');

        Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'social')
            ->assertSee('Authorized callback URL')
            ->set('social.google.enabled', true)
            ->set('social.google.client_id', 'google-client')
            ->set('social.google.client_secret', '')
            ->call('saveSocialLogin')
            ->assertDispatched('notify');

        $this->assertSame('1', PlatformSystem::get('social_google_enabled'));
        $this->assertSame('google-client', PlatformSystem::get('social_google_client_id'));
        $this->assertSame('existing-secret', PlatformSystem::get('social_google_client_secret'));
    }

    public function test_landing_page_controller_dynamically_renders_configured_theme(): void
    {
        PlatformBranding::current()->update(['landing_page_enabled' => true]);

        $themes = ['theme_modern', 'theme_enterprise', 'theme_minimal', 'theme_dark_studio'];

        foreach ($themes as $theme) {
            set_setting('landing_page_theme', $theme);
            cache()->forget('app_landing_page_theme');

            $response = $this->get('/');
            $response->assertOk();

            if ($theme === 'theme_enterprise') {
                $response->assertSee('High-Volume Stores');
                $response->assertSee('Multi-Store POS');
                $response->assertSee('80mm/58mm Thermal Printing');
                $response->assertSee('Fiscal Tax Engine');
            } elseif ($theme === 'theme_minimal') {
                $response->assertSee('Instant 60-Second Setup');
            } elseif ($theme === 'theme_dark_studio') {
                $response->assertSee('Dark Studio POS');
            } else {
                $response->assertSee('Smart Inventory & Sales');
            }
        }
    }
}
