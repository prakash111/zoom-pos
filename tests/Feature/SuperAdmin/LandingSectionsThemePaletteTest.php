<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Settings\Index as SettingsIndex;
use App\Models\PlatformBranding;
use App\Models\SaaSPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class LandingSectionsThemePaletteTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    public function test_superadmin_can_save_section_themes_via_controller_endpoint(): void
    {
        $this->actingAsSuperAdmin();

        $customPalette = [
            'hero' => [
                'light_bg' => '#f8fafc',
                'light_text' => '#1e293b',
                'light_muted' => '#64748b',
                'dark_bg' => '#0f172a',
                'dark_text' => '#f8fafc',
                'dark_muted' => '#94a3b8',
            ],
            'pricing' => [
                'light_bg' => '#ffffff',
                'light_text' => '#0f172a',
                'light_muted' => '#475569',
                'dark_bg' => '#1e1b4b',
                'dark_text' => '#e0e7ff',
                'dark_muted' => '#a5b4fc',
            ],
            'faq' => [
                'light_bg' => '#f1f5f9',
                'light_text' => '#0f172a',
                'light_muted' => '#64748b',
                'dark_bg' => '#064e3b',
                'dark_text' => '#ecfdf5',
                'dark_muted' => '#6ee7b7',
            ],
        ];

        $response = $this->post(route('superadmin.theme.customizer.sections'), [
            'palette' => $customPalette,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $saved = get_landing_sections_palette();
        $this->assertSame('#0f172a', $saved['hero']['dark_bg']);
        $this->assertSame('#1e1b4b', $saved['pricing']['dark_bg']);
        $this->assertSame('#064e3b', $saved['faq']['dark_bg']);
        $this->assertSame('#6ee7b7', $saved['faq']['dark_muted']);

        if (Schema::hasTable('system_settings')) {
            $raw = DB::table('system_settings')->where('key', 'landing_sections_theme_palette')->value('value');
            $this->assertNotNull($raw);
            $decoded = json_decode($raw, true);
            $this->assertSame('#1e1b4b', $decoded['pricing']['dark_bg']);
        }
    }

    public function test_superadmin_can_save_section_themes_via_livewire_save_appearance(): void
    {
        $this->actingAsSuperAdmin();

        $customPalette = default_landing_sections_palette();
        $customPalette['mission']['dark_bg'] = '#2e1065';
        $customPalette['mission']['dark_text'] = '#f3e8ff';
        $customPalette['mission']['dark_muted'] = '#d8b4fe';
        $customPalette['cta']['dark_bg'] = '#000000';
        $customPalette['cta']['dark_text'] = '#ffffff';

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
                'landingDarkBg' => '#2e1065',
                'palette' => $customPalette,
            ])
            ->assertDispatched('appearance-defaults-saved')
            ->assertDispatched('notify');

        $saved = get_landing_sections_palette();
        $this->assertSame('#2e1065', $saved['mission']['dark_bg']);
        $this->assertSame('#f3e8ff', $saved['mission']['dark_text']);
        $this->assertSame('#000000', $saved['cta']['dark_bg']);
        $this->assertSame('#2e1065', setting('landing_dark_bg'));
    }

    public function test_invalid_hex_is_sanitized_to_defaults(): void
    {
        $this->actingAsSuperAdmin();

        $this->post(route('superadmin.theme.customizer.sections'), [
            'palette' => [
                'hero' => [
                    'dark_bg' => 'not-a-hex-color',
                    'dark_text' => '123456', // missing hash prefix, should be normalized to #123456
                ],
            ],
        ]);

        $saved = get_landing_sections_palette();
        // invalid hex should fall back to default
        $this->assertSame(default_landing_sections_palette()['hero']['dark_bg'], $saved['hero']['dark_bg']);
        // 6-character hex without hash should have hash prepended
        $this->assertSame('#123456', $saved['hero']['dark_text']);
    }

    public function test_cache_is_cleared_on_palette_update(): void
    {
        Cache::forever('landing_sections_theme_palette', ['dummy' => 'data']);
        Cache::forever('superadmin_theme_settings', ['dummy' => 'data']);
        Cache::forever('landing_page_theme_config', ['dummy' => 'data']);
        Cache::forever('app_landing_page_theme', 'cached_theme');

        $this->actingAsSuperAdmin();
        $this->post(route('superadmin.theme.customizer.sections'), [
            'palette' => default_landing_sections_palette(),
        ]);

        $this->assertNull(Cache::get('superadmin_theme_settings'));
        $this->assertNull(Cache::get('landing_page_theme_config'));
        $this->assertNull(Cache::get('app_landing_page_theme'));
    }

    public function test_public_landing_page_renders_css_variables_and_semantic_classes(): void
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

        $customPalette = default_landing_sections_palette();
        $customPalette['hero']['dark_bg'] = '#0a192f';
        $customPalette['hero']['light_bg'] = '#f0f9ff';
        $customPalette['features']['dark_bg'] = '#172a45';
        $customPalette['mission']['dark_bg'] = '#0d1b2a';
        $customPalette['pricing']['dark_bg'] = '#1b263b';
        $customPalette['faq']['dark_bg'] = '#22223b';
        $customPalette['cta']['dark_bg'] = '#10002b';
        $customPalette['contact']['light_bg'] = '#f8fafc';
        $customPalette['contact']['light_card_bg'] = '#ffffff';
        $customPalette['contact']['dark_bg'] = '#020617';
        $customPalette['contact']['dark_card_bg'] = '#0f172a';

        $this->actingAsSuperAdmin();
        $this->post(route('superadmin.theme.customizer.sections'), [
            'palette' => $customPalette,
        ]);

        auth('platform_web')->logout();
        auth('web')->logout();

        $response = $this->get('/');
        $response->assertStatus(200);

        // Verify CSS Variables for Light mode (:root)
        $response->assertSee('--landing-hero-bg: #f0f9ff', false);
        $response->assertSee('--landing-contact-bg: #f8fafc', false);
        $response->assertSee('--landing-contact-card-bg: #ffffff', false);

        // Verify CSS Variables for Dark mode (html.dark)
        $response->assertSee('--landing-hero-bg: #0a192f', false);
        $response->assertSee('--landing-features-bg: #172a45', false);
        $response->assertSee('--landing-mission-bg: #0d1b2a', false);
        $response->assertSee('--landing-pricing-bg: #1b263b', false);
        $response->assertSee('--landing-faq-bg: #22223b', false);
        $response->assertSee('--landing-cta-bg: #10002b', false);
        $response->assertSee('--landing-contact-dark-bg: #020617', false);
        $response->assertSee('--landing-contact-card-dark-bg: #0f172a', false);

        // Verify Semantic Classes in HTML
        $response->assertSee('landing-sec-hero', false);
        $response->assertSee('landing-sec-features', false);
        $response->assertSee('landing-sec-mission', false);
        $response->assertSee('landing-sec-pricing', false);
        $response->assertSee('landing-sec-faq', false);
        $response->assertSee('landing-sec-cta', false);
        $response->assertSee('landing-sec-contact', false);
        $response->assertSee('contact-form-card', false);
        $response->assertSee('contact-form-input', false);
    }

    public function test_contact_form_custom_theme_saves_and_persists_in_palette(): void
    {
        $this->actingAsSuperAdmin();

        $customPalette = default_landing_sections_palette();
        $customPalette['contact'] = [
            'light_bg' => '#f1f5f9',
            'light_card_bg' => '#ffffff',
            'light_text' => '#0f172a',
            'light_muted' => '#475569',
            'dark_bg' => '#0b0f19',
            'dark_card_bg' => '#131e29',
            'dark_text' => '#ffffff',
            'dark_muted' => '#94a3b8',
        ];

        Livewire::test(SettingsIndex::class)
            ->call('saveAppearance', [
                'layout' => 'slim',
                'position' => 'left',
                'mode' => 'docked',
                'palette' => $customPalette,
            ])
            ->assertDispatched('appearance-defaults-saved');

        $saved = get_landing_sections_palette();
        $this->assertArrayHasKey('contact', $saved);
        $this->assertSame('#f1f5f9', $saved['contact']['light_bg']);
        $this->assertSame('#ffffff', $saved['contact']['light_card_bg']);
        $this->assertSame('#0b0f19', $saved['contact']['dark_bg']);
        $this->assertSame('#131e29', $saved['contact']['dark_card_bg']);
    }

    public function test_artisan_command_sets_matching_color_combination_pattern(): void
    {
        $this->artisan('landing:setup-matching-colors', ['--theme' => 'obsidian'])
            ->expectsOutputToContain("Applying 'Obsidian Executive' matching color combination pattern")
            ->expectsOutputToContain('✓ Pattern \'Obsidian Executive\' applied successfully!')
            ->assertSuccessful();

        $saved = get_landing_sections_palette();
        $this->assertSame('#ffffff', $saved['hero']['light_bg']);
        $this->assertSame('#f8fafc', $saved['features']['light_bg']);
        $this->assertSame('#0b0f19', $saved['hero']['dark_bg']);
        $this->assertSame('#0f172a', $saved['features']['dark_bg']);
        $this->assertSame('#0b0f19', setting('landing_dark_bg'));
    }

    public function test_controller_can_apply_matching_color_pattern(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->post(route('superadmin.theme.customizer.matching-pattern'), [
            'theme' => 'midnight',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $saved = get_landing_sections_palette();
        $this->assertSame('#0f172a', $saved['hero']['dark_bg']);
        $this->assertSame('#1e293b', $saved['features']['dark_bg']);
        $this->assertSame('#0f172a', setting('landing_dark_bg'));
    }

    public function test_livewire_can_apply_matching_color_pattern(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SettingsIndex::class)
            ->call('applyMatchingPalettePattern', 'emerald')
            ->assertDispatched('palette-pattern-applied')
            ->assertDispatched('notify');

        $saved = get_landing_sections_palette();
        $this->assertSame('#064e3b', $saved['hero']['dark_bg']);
        $this->assertSame('#022c22', $saved['features']['dark_bg']);
        $this->assertSame('#064e3b', setting('landing_dark_bg'));
    }

    public function test_light_theme_renders_semantic_classes_and_scoped_dark_sections(): void
    {
        $branding = PlatformBranding::firstOrCreate([], [
            'platform_name' => 'Zoom POS',
            'landing_page_enabled' => true,
        ]);
        $branding->update([
            'landing_section_meta' => [
                'trust_bar' => ['background' => '#0F172A', 'accent' => '#D7F24E'],
                'stats' => ['background' => '#0F172A', 'accent' => '#D7F24E'],
            ],
        ]);

        SaaSPlan::create([
            'name' => 'pro',
            'display_name' => 'Pro Plan',
            'price' => 59,
            'currency' => '$',
            'billing_cycle' => 'monthly',
            'is_active' => true,
            'is_popular' => true,
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);

        // Verify Light mode CSS Variables
        $response->assertSee('--landing-trust-bg: var(--landing-hero-bg)', false);
        $response->assertSee('--landing-stats-bg: var(--landing-features-bg)', false);
        $response->assertSee('--landing-solutions-bg: var(--landing-features-bg)', false);
        $response->assertSee('--landing-downloads-bg: var(--landing-features-bg)', false);
        $response->assertSee('--landing-testimonials-bg: var(--landing-features-bg)', false);

        // Verify Semantic Classes on sections
        $response->assertSee('landing-sec-hero', false);
        $response->assertSee('landing-sec-trust', false);
        $response->assertSee('landing-sec-features', false);
        $response->assertSee('landing-sec-solutions', false);
        $response->assertSee('landing-sec-stats', false);
        $response->assertSee('landing-sec-mission', false);
        $response->assertSee('landing-sec-pricing', false);
        $response->assertSee('landing-sec-faq', false);
        $response->assertSee('landing-sec-cta', false);
        $response->assertSee('landing-sec-contact', false);

        // Verify .landing-dark-section is scoped to html.dark
        $response->assertSee('html.dark .landing-dark-section', false);
        $response->assertDontSee("\n      .landing-dark-section,\n      .bg-dark-hero,", false);

        // Verify section background rules from branding are scoped to dark mode on fast theme
        $response->assertSee('html.dark #trust_bar{background-color:', false);
        $response->assertSee('html.dark #stats{background-color:', false);
    }
}
