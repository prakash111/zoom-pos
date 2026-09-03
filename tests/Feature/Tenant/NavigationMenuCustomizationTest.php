<?php

namespace Tests\Feature\Tenant;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class NavigationMenuCustomizationTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

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

    public function test_hidden_item_is_absent_from_the_rendered_drawer(): void
    {
        [$company] = $this->actingAsTenantAdmin();
        $company->update([
            'nav_config' => [
                'sections' => [],
                'items' => [['key' => 'quotations', 'section' => null, 'order' => null, 'visible' => false]],
            ],
        ]);

        $response = $this->get(route('tenant.dashboard'));

        // "Quotations" text/markup can legitimately still appear elsewhere on
        // the page (the separate, per-device dock bar isn't in scope here —
        // see resources/js/dockable-nav.js) — what must be gone is the main
        // drawer's own rendering of this destination.
        $response->assertOk();
        $response->assertDontSee('data-item-key="quotations"', false);
        $response->assertSee('data-item-key="pos"', false);
    }

    public function test_nav_config_is_embedded_for_client_side_reordering(): void
    {
        [$company] = $this->actingAsTenantAdmin();
        $company->update([
            'nav_config' => [
                'sections' => [
                    ['key' => 'financial_management', 'order' => 0],
                    ['key' => 'cashier_sales', 'order' => 1],
                ],
                'items' => [
                    ['key' => 'pos', 'section' => 'financial_management', 'order' => 0, 'visible' => true],
                ],
            ],
        ]);

        $response = $this->get(route('tenant.dashboard'));

        $response->assertOk();
        $response->assertSee('id="tenant-drawer-nav"', false);
        $response->assertSee('data-section-key="cashier_sales"', false);
        $response->assertSee('"key":"financial_management","order":0', false);
        $response->assertSee('"key":"pos","section":"financial_management","order":0,"visible":true', false);
    }

    public function test_no_saved_config_leaves_the_drawer_completely_unchanged(): void
    {
        [$company] = $this->actingAsTenantAdmin();
        $this->assertNull($company->nav_config);

        $response = $this->get(route('tenant.dashboard'));

        $response->assertOk();
        // Every default item still renders — nothing hidden or restructured.
        $response->assertSee('data-item-key="pos"', false);
        $response->assertSee('data-item-key="sales"', false);
        $response->assertSee('data-item-key="quotations"', false);
        $response->assertSee('data-section-key="cashier_sales"', false);
        $response->assertSee('data-section-key="financial_management"', false);
        $response->assertSee('data-section-key="administration"', false);
        $response->assertSee('"sections":[]', false);
        $response->assertSee('"items":[]', false);
    }
}
