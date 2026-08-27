<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\Superadmin\MenuBuilderComponent;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PlatformBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class MenuBuilderTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_guest_cannot_access_menu_builder(): void
    {
        $response = $this->get(route('superadmin.menus.index'));
        $response->assertRedirect(route('superadmin.login'));
    }

    public function test_superadmin_can_view_menu_builder_page(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->get(route('superadmin.menus.index'));
        $response->assertOk();
        $response->assertSee('Dynamic Navigation Menu Builder');
    }

    public function test_can_add_system_cms_pages_to_header_menu(): void
    {
        $this->actingAsSuperAdmin();

        $terms = Page::create([
            'title' => 'Terms & Conditions',
            'slug' => 'terms-and-conditions',
            'content' => 'Legal Terms',
            'is_active' => true,
        ]);

        $privacy = Page::create([
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'content' => 'Privacy details',
            'is_active' => true,
        ]);

        Livewire::test(MenuBuilderComponent::class)
            ->set('activeLocation', 'header')
            ->set('selectedPages', [$terms->id, $privacy->id])
            ->call('addSelectedPages')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('menu_items', [
            'location' => 'header',
            'title' => 'Terms & Conditions',
            'type' => 'page',
            'url' => '/page/terms-and-conditions',
            'page_id' => $terms->id,
            'target' => '_self',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('menu_items', [
            'location' => 'header',
            'title' => 'Privacy Policy',
            'type' => 'page',
            'url' => '/page/privacy-policy',
            'page_id' => $privacy->id,
            'target' => '_self',
            'is_active' => 1,
        ]);
    }

    public function test_can_add_anchor_link_to_menu(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(MenuBuilderComponent::class)
            ->set('activeLocation', 'header')
            ->call('addAnchorLink', 'Pricing & Plans', '#pricing')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('menu_items', [
            'location' => 'header',
            'title' => 'Pricing & Plans',
            'type' => 'anchor',
            'url' => '#pricing',
            'target' => '_self',
            'is_active' => 1,
        ]);
    }

    public function test_can_add_custom_external_link_with_new_tab_target(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(MenuBuilderComponent::class)
            ->set('activeLocation', 'header')
            ->set('customTitle', 'Partner Portal')
            ->set('customUrl', 'https://partner.zoomnearby.com')
            ->set('customTargetBlank', true)
            ->call('addCustomLink')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('menu_items', [
            'location' => 'header',
            'title' => 'Partner Portal',
            'type' => 'custom',
            'url' => 'https://partner.zoomnearby.com',
            'target' => '_blank',
            'is_active' => 1,
        ]);
    }

    public function test_can_reorder_menu_items_drag_and_drop(): void
    {
        $this->actingAsSuperAdmin();

        $item1 = MenuItem::create([
            'location' => 'header',
            'title' => 'First',
            'type' => 'anchor',
            'url' => '#first',
            'order_index' => 0,
            'is_active' => true,
        ]);

        $item2 = MenuItem::create([
            'location' => 'header',
            'title' => 'Second',
            'type' => 'anchor',
            'url' => '#second',
            'order_index' => 1,
            'is_active' => true,
        ]);

        // Reverse the order
        Livewire::test(MenuBuilderComponent::class)
            ->set('activeLocation', 'header')
            ->call('updateMenuOrder', [$item2->id, $item1->id]);

        $this->assertSame(0, $item2->fresh()->order_index);
        $this->assertSame(1, $item1->fresh()->order_index);
    }

    public function test_can_toggle_status_and_delete_menu_items(): void
    {
        $this->actingAsSuperAdmin();

        $item = MenuItem::create([
            'location' => 'footer_col_1',
            'title' => 'Support',
            'type' => 'anchor',
            'url' => '#contact',
            'order_index' => 0,
            'is_active' => true,
        ]);

        Livewire::test(MenuBuilderComponent::class)
            ->set('activeLocation', 'footer_col_1')
            ->call('toggleStatus', $item->id);

        $this->assertFalse($item->fresh()->is_active);

        Livewire::test(MenuBuilderComponent::class)
            ->set('activeLocation', 'footer_col_1')
            ->call('deleteItem', $item->id);

        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    public function test_can_edit_menu_item_inline(): void
    {
        $this->actingAsSuperAdmin();

        $item = MenuItem::create([
            'location' => 'header',
            'title' => 'Old Title',
            'type' => 'custom',
            'url' => 'https://old.com',
            'target' => '_self',
            'order_index' => 0,
            'is_active' => true,
        ]);

        Livewire::test(MenuBuilderComponent::class)
            ->set('activeLocation', 'header')
            ->call('startEdit', $item->id)
            ->assertSet('editingTitle', 'Old Title')
            ->set('editingTitle', 'New Updated Title')
            ->set('editingUrl', 'https://new.com')
            ->set('editingTarget', '_blank')
            ->call('saveEdit')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('menu_items', [
            'id' => $item->id,
            'title' => 'New Updated Title',
            'url' => 'https://new.com',
            'target' => '_blank',
        ]);
    }

    public function test_public_layout_renders_dynamic_header_and_footer_menu_items(): void
    {
        PlatformBranding::create([
            'platform_name' => 'ZoomNearby POS',
            'landing_page_enabled' => true,
        ]);

        MenuItem::create([
            'location' => 'header',
            'title' => 'Custom Header Link',
            'type' => 'anchor',
            'url' => '#features',
            'order_index' => 0,
            'is_active' => true,
        ]);

        MenuItem::create([
            'location' => 'footer_col_1',
            'title' => 'Custom Footer Quick Link',
            'type' => 'custom',
            'url' => 'https://example.com/quick',
            'order_index' => 0,
            'is_active' => true,
        ]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Custom Header Link');
        $response->assertSee('Custom Footer Quick Link');
    }
}
