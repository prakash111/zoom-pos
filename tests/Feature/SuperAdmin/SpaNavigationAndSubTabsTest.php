<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Settings\Index as SettingsIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class SpaNavigationAndSubTabsTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    public function test_superadmin_layout_includes_turbo_spa_engine_and_cache_control_headers(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->get(route('superadmin.settings.index'));
        $response->assertOk();

        // Verify Turbo SPA Engine Header
        $response->assertSee('assets/libs/turbo.min.js', false);
        $response->assertSee('data-turbo-track="reload"', false);
        $response->assertSee('<meta name="turbo-cache-control" content="no-preview">', false);

        // Verify NProgress SPA bar
        $response->assertSee('assets/libs/nprogress.js', false);
        $response->assertSee('assets/libs/nprogress.css', false);
    }

    public function test_sub_tabs_use_reactive_alpine_and_query_param_binding(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->get(route('superadmin.settings.index'));
        $response->assertOk();

        // Verify Alpine reactive tab manager definition
        $response->assertSee('activeTab:', false);
        $response->assertSee('switchTab(tab)', false);
        $response->assertSee('window.history.replaceState', false);
        $response->assertSee('URLSearchParams', false);

        // Verify sub-tab button clicks bind to switchTab
        $response->assertSee("@click=\"switchTab('general')\"", false);
        $response->assertSee("@click=\"switchTab('smtp')\"", false);
        $response->assertSee("@click=\"switchTab('whitelabel')\"", false);
        $response->assertSee("@click=\"switchTab('pages')\"", false);
        $response->assertSee("@click=\"switchTab('appearance')\"", false);

        // Verify sub-tab pane conditional display
        $response->assertSee('x-show="activeTab === \'general\'"', false);
        $response->assertSee('x-show="activeTab === \'smtp\'"', false);
        $response->assertSee('x-show="activeTab === \'whitelabel\'', false);
        $response->assertSee('x-show="activeTab === \'pages\'"', false);
        $response->assertSee('x-show="activeTab === \'appearance\'"', false);
    }

    public function test_opening_specific_tab_via_query_param_loads_properly(): void
    {
        $this->actingAsSuperAdmin();

        $responseAppearance = $this->get(route('superadmin.settings.index', ['tab' => 'appearance']));
        $responseAppearance->assertOk();
        $responseAppearance->assertSee('Navigation & Layout Customization');

        $responseSmtp = $this->get(route('superadmin.settings.index', ['tab' => 'smtp']));
        $responseSmtp->assertOk();
        $responseSmtp->assertSee('Global Platform SMTP Email Configuration');

        $responseWhitelabel = $this->get(route('superadmin.settings.index', ['tab' => 'whitelabel']));
        $responseWhitelabel->assertOk();
        $responseWhitelabel->assertSee('Platform Identity & Logos');
    }

    public function test_superadmin_nav_links_have_wire_navigate_for_seamless_spa_transitions(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->get(route('superadmin.dashboard'));
        $response->assertOk();

        $response->assertSee('wire:navigate', false);
        $response->assertSee(route('superadmin.dashboard'), false);
        $response->assertSee(route('superadmin.tenants.index'), false);
        $response->assertSee(route('superadmin.plans.index'), false);
        $response->assertSee(route('superadmin.settings.index'), false);
    }

    public function test_all_settings_partials_exist_and_render(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SettingsIndex::class)
            ->assertSee('Platform General Configuration')
            ->assertSee('Global Platform SMTP Email Configuration')
            ->assertSee('Platform Identity & Logos')
            ->assertSee('Custom Public Pages & CMS')
            ->assertSee('Navigation & Layout Customization');
    }

    public function test_global_spa_interceptor_code_is_bundled_in_app_js(): void
    {
        $appJs = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('Global SPA Link Interceptor', $appJs);
        $this->assertStringContainsString('Livewire.navigate', $appJs);
        $this->assertStringContainsString('Turbo.visit', $appJs);
        $this->assertStringContainsString('spa:page-loaded', $appJs);
    }
}
