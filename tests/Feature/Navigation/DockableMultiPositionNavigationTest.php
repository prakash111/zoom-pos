<?php

namespace Tests\Feature\Navigation;

use App\Models\Company;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class DockableMultiPositionNavigationTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');

        Plan::firstOrCreate(['name' => 'trial'], [
            'display_name' => 'Trial',
            'billing_cycle' => 'trial',
            'duration_days' => 14,
            'price' => 0.00,
            'currency' => 'USD',
            'active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_superadmin_layout_renders_draggable_dock_navigation_and_4_layouts_and_4_themes(): void
    {
        $admin = PlatformAdmin::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@platform.test',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($admin, 'platform_web')->get(route('superadmin.dashboard'));
        $response->assertOk();

        // Check for Draggable Dock bindings and Grab Handle
        $response->assertSee('dockableNav(\'sa_dock_nav_state\', \'left\')', false);
        $response->assertSee('data-drag-handle', false);
        $response->assertSee('data-dock-pos', false);
        $response->assertSee('data-nav-layout', false);
        $response->assertSee('data-nav-theme', false);

        // Check 4 Menu Layout Structures
        $response->assertSee('Slim Icon Rail');
        $response->assertSee('Expanded Full Sidebar');
        $response->assertSee('macOS-Style Island Dock');
        $response->assertSee('Speed-Dial Bubble');

        // Check 4 Visual Theme Styles
        $response->assertSee('Signature Violet Glow');
        $response->assertSee('Frosted Glassmorphism');
        $response->assertSee('Enterprise Flat Dark');
        $response->assertSee('Clean Neumorphic');

        // Check Customizer modal & Reset triggers
        $response->assertSee('Navigation & Appearance Settings');
        $response->assertSee('Reset to Defaults');

        // Check Docking Behavior (Fixed Docked vs Floating / Free Drag)
        $response->assertSee('Fixed Docked');
        $response->assertSee('Floating / Free Drag');
        $response->assertSee('Docking Screen Position');
        $response->assertSee('Left Sidebar');
        $response->assertSee('Right Sidebar');
        $response->assertSee('Top Header Bar');
        $response->assertSee('Bottom Dock Bar');
    }

    public function test_tenant_layout_renders_draggable_dock_navigation_and_customizer(): void
    {
        $company = Company::create([
            'name' => 'Test Outlet',
            'slug' => 'test-outlet',
            'status' => 'active',
            'plan_name' => 'trial',
            'expires_at' => now()->addDays(14),
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Tenant Owner',
            'login' => 'tenant_owner',
            'email' => 'owner@testoutlet.test',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_ADMINISTRATOR,
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user, 'web')->get(route('tenant.dashboard'));
        $response->assertOk();

        // Check for Draggable Dock bindings, Layouts & Themes
        $response->assertSee('dockableNav(\'tenant_dock_nav_state\', \'left\'', false);
        $response->assertSee('data-drag-handle', false);
        $response->assertSee('data-dock-pos', false);
        $response->assertSee('data-nav-layout', false);
        $response->assertSee('data-nav-theme', false);

        // Check 4 Menu Layout Structures
        $response->assertSee('Slim Icon Rail');
        $response->assertSee('Expanded Full Sidebar');
        $response->assertSee('macOS-Style Island Dock');
        $response->assertSee('Speed-Dial Bubble');

        // Check 4 Visual Theme Styles
        $response->assertSee('Signature Violet Glow');
        $response->assertSee('Frosted Glassmorphism');
        $response->assertSee('Enterprise Flat Dark');
        $response->assertSee('Clean Neumorphic');

        // Check Customizer modal & Reset triggers
        $response->assertSee('Navigation & Appearance Settings');
        $response->assertSee('Reset to Defaults');

        // Check Docking Behavior (Fixed Docked vs Floating / Free Drag)
        $response->assertSee('Fixed Docked');
        $response->assertSee('Floating / Free Drag');
        $response->assertSee('Docking Screen Position');
        $response->assertSee('Left Sidebar');
        $response->assertSee('Right Sidebar');
        $response->assertSee('Top Header Bar');
        $response->assertSee('Bottom Dock Bar');

        // Check Android-Style Scrollable Dock Navigation Container
        $response->assertSee('data-dock-scroll-container', false);

        // Check Visible Dock Items Customization & Presets
        $response->assertSee('Customize Visible Dock Items (Pinning)');
        $response->assertSee('Select All');
        $response->assertSee('Default (Essential 6)');
        $response->assertSee('Reset / Uncheck All');
    }

    public function test_quotations_menu_and_all_audited_features_render_for_tenant_admin_in_general_mode(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();
        $company->update(['pos_mode' => 'general']);
        $company->refresh();
        $user->refresh();
        $user->unsetRelation('company');

        $response = $this->actingAs($user, 'web')->get(route('tenant.dashboard'));
        $response->assertOk();

        // Quotations links and text in navigation
        $response->assertSee(route('tenant.quotes.index'), false);
        $response->assertSee('Quotations');

        // Cashier POS & Sales / Invoices
        $response->assertSee(route('tenant.sales.create'), false);
        $response->assertSee(route('tenant.sales.index'), false);
        $response->assertSee('Retail POS');
        $response->assertSee('Cashier POS');
        $response->assertSee('Invoices');

        // CRM & Customers
        $response->assertSee(route('tenant.customers.index'), false);
        $response->assertSee('Customers');

        // Financial Management
        $response->assertSee(route('tenant.financials.cash_register'), false);
        $response->assertSee(route('tenant.financials.receivables'), false);
        $response->assertSee(route('tenant.financials.payables'), false);

        // Products & Catalog
        $response->assertSee(route('tenant.products.index'), false);
        $response->assertSee(route('tenant.categories.index'), false);
        $response->assertSee(route('tenant.brands.index'), false);
        $response->assertSee(route('tenant.units.index'), false);
        $response->assertSee(route('tenant.suppliers.index'), false);
        $response->assertSee(route('tenant.catalog.index'), false);

        // Administration & Settings
        $response->assertSee(route('tenant.settings.index'), false);
        $response->assertSee(route('tenant.billing.index'), false);
        $response->assertSee(route('tenant.languages.index'), false);
        $response->assertSee(route('tenant.users.index'), false);
        $response->assertSee(route('tenant.devices.index'), false);
        $response->assertSee('Subscription &amp; Billing', false);
        $response->assertSee('Languages &amp; Translations', false);
        $response->assertSee('Users &amp; Permissions', false);
        $response->assertSee('Terminals &amp; Devices', false);
        $response->assertDontSee('&amp;amp;', false);
        $response->assertSee('class="hidden sm:flex px-2.5 py-1.5', false);

        // Restaurant-specific elements should NOT be visible in General mode
        $response->assertDontSee(route('tenant.restaurant.pos'), false);
        $response->assertDontSee(route('tenant.restaurant.tables'), false);
        $response->assertDontSee(route('tenant.restaurant.kds'), false);
    }

    public function test_quotations_menu_is_hidden_in_restaurant_mode_and_food_pos_renders(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();
        $company->update(['pos_mode' => 'restaurant']);
        $company->refresh();
        $user->refresh();
        $user->unsetRelation('company');

        $response = $this->actingAs($user, 'web')->get(route('tenant.dashboard'));
        $response->assertOk();

        // Quotation links should be omitted in Restaurant mode
        $response->assertDontSee(route('tenant.quotes.index'), false);

        // Restaurant operations must be visible
        $response->assertSee(route('tenant.restaurant.pos'), false);
        $response->assertSee(route('tenant.restaurant.tables'), false);
        $response->assertSee(route('tenant.restaurant.kds'), false);
        $response->assertSee('Food POS');
        $response->assertSee('Restaurant POS');
        $response->assertSee('Kitchen Display (KDS)');
    }

    public function test_quotation_named_routes_and_aliases_are_accessible_for_tenant_admin(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();

        $this->actingAs($user, 'web')->get(route('tenant.quotes.index'))->assertOk();
        $this->actingAs($user, 'web')->get(route('tenant.quotations.index'))->assertOk();
        $this->actingAs($user, 'web')->get(route('tenant.quotes.create'))->assertOk();
        $this->actingAs($user, 'web')->get(route('tenant.quotations.create'))->assertOk();
    }
}
