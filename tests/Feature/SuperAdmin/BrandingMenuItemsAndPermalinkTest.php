<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Branding\Index as BrandingStudio;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PlatformBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class BrandingMenuItemsAndPermalinkTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
        PlatformBranding::current()->update(['landing_page_enabled' => true]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_superadmin_can_manage_navigation_menu_items(): void
    {
        $this->actingAsSuperAdmin();

        $page = Page::create([
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'content' => '<p>Privacy content</p>',
            'is_active' => true,
        ]);

        $test = Livewire::test(BrandingStudio::class)
            // 1. Add Anchor link
            ->call('setMenuLocation', 'header')
            ->call('addAnchorMenuLink', 'Features', '#features')
            ->call('addAnchorMenuLink', 'Pricing', '#pricing');

        $this->assertDatabaseHas('menu_items', [
            'location' => 'header',
            'title' => 'Features',
            'url' => '#features',
            'type' => 'anchor',
        ]);

        // 2. Add CMS Page link
        $test->call('addPageMenuLink', $page->id);

        $this->assertDatabaseHas('menu_items', [
            'location' => 'header',
            'title' => 'Privacy Policy',
            'url' => '/page/privacy-policy',
            'type' => 'page',
            'page_id' => $page->id,
        ]);

        // 3. Add Custom URL link
        $test->set('newMenuTitle', 'External Docs')
            ->set('newMenuUrl', 'https://docs.zoomnearby.com')
            ->set('newMenuTargetBlank', true)
            ->call('addCustomMenuLink')
            ->assertHasNoErrors();

        $customItem = MenuItem::where('title', 'External Docs')->firstOrFail();
        $this->assertSame('https://docs.zoomnearby.com', $customItem->url);
        $this->assertSame('_blank', $customItem->target);
        $this->assertSame('header', $customItem->location);

        // 4. Move item up and down
        $test->call('moveMenuItemUp', $customItem->id);
        $test->call('moveMenuItemDown', $customItem->id);

        // 5. Toggle active
        $test->call('toggleMenuItemActive', $customItem->id);
        $this->assertFalse($customItem->fresh()->is_active);
        $test->call('toggleMenuItemActive', $customItem->id);
        $this->assertTrue($customItem->fresh()->is_active);

        // 6. Edit item inline
        $test->call('editMenuItem', $customItem->id)
            ->set('editingMenuItemTitle', 'Updated Docs Title')
            ->set('editingMenuItemUrl', 'https://help.zoomnearby.com')
            ->call('saveMenuItem');

        $this->assertSame('Updated Docs Title', $customItem->fresh()->title);
        $this->assertSame('https://help.zoomnearby.com', $customItem->fresh()->url);

        // 7. Delete item
        $test->call('deleteMenuItem', $customItem->id);
        $this->assertDatabaseMissing('menu_items', ['id' => $customItem->id]);

        // 8. Footer column switcher
        $test->call('setMenuLocation', 'footer_col_1')
            ->call('addAnchorMenuLink', 'Solutions', '#solutions');

        $this->assertDatabaseHas('menu_items', [
            'location' => 'footer_col_1',
            'title' => 'Solutions',
            'url' => '#solutions',
        ]);
    }

    public function test_superadmin_can_configure_permalink_static_page_landing(): void
    {
        $this->actingAsSuperAdmin();

        $page = Page::create([
            'title' => 'Company Landing',
            'slug' => 'company-landing',
            'content' => '<h1>Direct Permalink Page Content</h1>',
            'is_active' => true,
        ]);

        Livewire::test(BrandingStudio::class)
            ->set('landingPageEnabled', true)
            ->set('homepageMode', 'static_page')
            ->set('landingPageId', $page->id)
            ->call('save')
            ->assertHasNoErrors();

        $branding = PlatformBranding::current()->fresh();
        $this->assertTrue($branding->landing_page_enabled);
        $this->assertSame($page->id, $branding->landing_page_id);
        $this->assertSame('static_page', data_get($branding->landing_content, 'homepage_mode'));

        // Guest hitting root URL `/` renders the static CMS page directly
        auth('platform_web')->logout();
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Direct Permalink Page Content', false);

        // Direct public permalink `/page/company-landing` also renders the same content
        $pageResponse = $this->get('/page/company-landing');
        $pageResponse->assertOk();
        $pageResponse->assertSee('Direct Permalink Page Content', false);
    }

    public function test_superadmin_settings_whitelabel_tab_renders_unified_landing_and_menu_setup(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->get('/superadmin/settings?tab=whitelabel');
        $response->assertOk();
        $response->assertSee('Homepage &amp; Permalinks', false);
        $response->assertSee('Navigation Menu Items');
        $response->assertSee('Brand Identity &amp; White-label', false);
        $response->assertSee('Public Landing Page Access');
    }

    public function test_superadmin_can_manage_menus_and_permalinks_directly_inside_settings_whitelabel_tab(): void
    {
        $this->actingAsSuperAdmin();

        $page = Page::create([
            'title' => 'Settings Landing',
            'slug' => 'settings-landing',
            'content' => '<h1>Settings Unified Landing</h1>',
            'is_active' => true,
        ]);

        \Livewire\Livewire::test(\App\Livewire\SuperAdmin\Settings\Index::class)
            ->set('activeTab', 'whitelabel')
            ->set('platformName', 'Unified Store POS')
            ->set('landingPageEnabled', true)
            ->set('homepageMode', 'static_page')
            ->set('landingPageId', $page->id)
            ->call('setMenuLocation', 'header')
            ->call('addAnchorMenuLink', 'Features Anchor', '#features')
            ->call('saveBranding')
            ->assertHasNoErrors();

        $branding = PlatformBranding::current()->fresh();
        $this->assertSame('Unified Store POS', $branding->platform_name);
        $this->assertTrue($branding->landing_page_enabled);
        $this->assertSame($page->id, $branding->landing_page_id);
        $this->assertSame('static_page', data_get($branding->landing_content, 'homepage_mode'));

        $this->assertDatabaseHas('menu_items', [
            'location' => 'header',
            'title' => 'Features Anchor',
            'url' => '#features',
        ]);
    }

    public function test_superadmin_branding_url_redirects_to_settings_whitelabel_tab(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->get('/superadmin/branding');
        $response->assertRedirect(route('superadmin.settings.index', ['tab' => 'whitelabel']));
    }
}
