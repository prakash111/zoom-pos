<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Dashboard;
use App\Livewire\Tenant\Settings\Index as SettingsIndex;
use App\Models\DiningFloor;
use App\Models\DiningTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class StoreSettingsModeIsolationTest extends TestCase
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

    public function test_admin_can_toggle_operating_mode_in_store_settings(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $this->assertSame('general', $company->pos_mode);
        $this->assertTrue($company->isGeneralMode());
        $this->assertFalse($company->isRestaurantMode());

        // Switch to Restaurant Mode
        Livewire::test(SettingsIndex::class)
            ->set('posMode', 'restaurant')
            ->call('save')
            ->assertHasNoErrors();

        $company->refresh();
        $this->assertSame('restaurant', $company->pos_mode);
        $this->assertTrue($company->isRestaurantMode());
        $this->assertFalse($company->isGeneralMode());

        // Switch back to General POS Mode
        Livewire::test(SettingsIndex::class)
            ->set('posMode', 'general')
            ->call('save')
            ->assertHasNoErrors();

        $company->refresh();
        $this->assertSame('general', $company->pos_mode);
        $this->assertTrue($company->isGeneralMode());
    }

    public function test_general_mode_strictly_restricts_restaurant_routes(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        $company->update(['pos_mode' => 'general']);

        // 1. Retail POS is accessible
        $resRetail = $this->actingAs($admin, 'web')->get(route('tenant.sales.create'));
        $resRetail->assertOk();

        // 2. Restaurant routes are blocked and redirect to dashboard
        $resRestaurantPos = $this->actingAs($admin, 'web')->get(route('tenant.restaurant.pos'));
        $resRestaurantPos->assertRedirect(route('tenant.dashboard'));

        $resTables = $this->actingAs($admin, 'web')->get(route('tenant.restaurant.tables'));
        $resTables->assertRedirect(route('tenant.dashboard'));

        $resKds = $this->actingAs($admin, 'web')->get(route('tenant.restaurant.kds'));
        $resKds->assertRedirect(route('tenant.dashboard'));
    }

    public function test_restaurant_mode_strictly_restricts_retail_routes(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        $company->update(['pos_mode' => 'restaurant']);

        // 1. Restaurant POS & Tables are accessible
        $resRestaurantPos = $this->actingAs($admin, 'web')->get(route('tenant.restaurant.pos'));
        $resRestaurantPos->assertOk();

        $resTables = $this->actingAs($admin, 'web')->get(route('tenant.restaurant.tables'));
        $resTables->assertOk();

        $resKds = $this->actingAs($admin, 'web')->get(route('tenant.restaurant.kds'));
        $resKds->assertOk();

        // 2. Retail POS and Quotes are blocked and redirect to restaurant POS
        $resRetail = $this->actingAs($admin, 'web')->get(route('tenant.sales.create'));
        $resRetail->assertRedirect(route('tenant.restaurant.pos'));

        $resQuotes = $this->actingAs($admin, 'web')->get(route('tenant.quotes.index'));
        $resQuotes->assertRedirect(route('tenant.restaurant.pos'));
    }

    public function test_dashboard_and_navigation_isolate_ui_elements(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $floor = DiningFloor::create(['company_id' => $company->id, 'name' => 'Indoor']);
        DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $floor->id, 'table_number' => 'Table 99', 'status' => 'occupied']);

        // Case A: General Mode
        $company->update(['pos_mode' => 'general']);

        Livewire::test(Dashboard::class)
            ->assertSee('Launch Casier POS')
            ->assertSee('Low Stock Warnings')
            ->assertDontSee('Dining Sales')
            ->assertDontSee('Table 99');

        $navResponse = $this->actingAs($admin, 'web')->get(route('tenant.dashboard'));
        $navResponse->assertSee('Retail POS');
        $navResponse->assertDontSee('Kitchen Display (KDS)');

        // Case B: Restaurant Mode
        $company->update(['pos_mode' => 'restaurant']);
        $company->refresh();
        $admin->refresh();
        $admin->unsetRelation('company');

        Livewire::test(Dashboard::class)
            ->assertSee('Open Restaurant POS')
            ->assertSee('Dining Sales')
            ->assertSee('Table 99')
            ->assertDontSee('Launch Casier POS')
            ->assertDontSee('Low Stock Warnings');

        $navResponseRest = $this->actingAs($admin, 'web')->get(route('tenant.dashboard'));
        $navResponseRest->assertSee('Food POS');
        $navResponseRest->assertSee('Tables');
        $navResponseRest->assertSee('Kitchen');
        $navResponseRest->assertDontSee('Retail Casier Terminal');
    }
}
