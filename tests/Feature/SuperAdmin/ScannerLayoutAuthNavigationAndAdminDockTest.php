<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\Tenant\Sales\Create as SalesCreate;
use App\Models\PlatformBranding;
use App\Models\Product;
use App\Services\Navigation\NavigationAppearanceCustomizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class ScannerLayoutAuthNavigationAndAdminDockTest extends TestCase
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

    public function test_standard_scanner_and_cart_layout_renders_no_stray_leading_dollar_sign(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Artisan Bread',
            'sale_price' => 4.50,
            'current_stock' => 100,
            'active' => true,
        ]);

        $component = Livewire::test(SalesCreate::class)
            ->call('addProductToCart', $product->id);

        // Verify standard cart partial contains the product name and does not have stray <span>$</span> before price input
        $component->assertSee('Artisan Bread');

        $html = $component->html();

        // Must not have <span>$</span> directly before price input
        $this->assertStringNotContainsString('<span>$</span>', $html);

        // Line total must still be formatted with currency
        $component->assertSee('$4.50');

        // Test the dedicated standard-scanner layout blade directly
        $item = [
            'name' => 'Artisan Bread',
            'price' => 4.50,
            'quantity' => 1,
        ];
        $index = 0;
        $rendered = view('tenant.sales.layouts.standard-scanner', compact('item', 'index'))->render();

        $this->assertStringContainsString('Artisan Bread', $rendered);
        $this->assertStringContainsString('$4.50', $rendered);
        $this->assertStringNotContainsString('<span>$</span>', $rendered);
        $this->assertStringNotContainsString('>$<', $rendered);
    }

    public function test_back_to_home_button_is_conditionally_hidden_on_tenant_auth_views(): void
    {
        // 1. When landing page is enabled
        PlatformBranding::current()->update(['landing_page_enabled' => true]);

        $response = $this->get(route('tenant.login'));
        $response->assertStatus(200);
        $response->assertSee(__('Back to Home'));

        $registerResponse = $this->get(route('tenant.register'));
        $registerResponse->assertStatus(200);
        $registerResponse->assertSee(__('Back to Home'));

        // 2. When landing page is disabled
        PlatformBranding::current()->update(['landing_page_enabled' => false]);

        $responseDisabled = $this->get(route('tenant.login'));
        $responseDisabled->assertStatus(200);
        $responseDisabled->assertDontSee(__('Back to Home'));

        $registerResponseDisabled = $this->get(route('tenant.register'));
        $registerResponseDisabled->assertStatus(200);
        $registerResponseDisabled->assertDontSee(__('Back to Home'));
    }

    public function test_super_admin_customizer_is_scoped_to_admin_only_dock_modules(): void
    {
        $customizer = new NavigationAppearanceCustomizer;
        $adminItems = $customizer->getAvailableAdminDockItems();

        $this->assertCount(8, $adminItems);

        $keys = array_column($adminItems, 'key');
        $expectedKeys = ['dashboard', 'tenants', 'plans', 'taxes', 'menus', 'pages', 'settings', 'smtp'];
        $this->assertSame($expectedKeys, $keys);

        // Ensure no tenant specific modules are in admin items
        $tenantOnlyKeys = ['pos', 'restaurant_pos', 'tables', 'kds', 'sales', 'quotes', 'products', 'categories', 'customers', 'register', 'receivables'];
        foreach ($tenantOnlyKeys as $tenantKey) {
            $this->assertNotContains($tenantKey, $keys);
        }

        // Test rendering the appearance modal blade
        $modalHtml = view('superadmin.settings.appearance-modal')->render();
        $this->assertStringContainsString('Customize Super Admin Visible Dock Items (Pinning)', $modalHtml);
        $this->assertStringContainsString('selectAllAdminItems', $modalHtml);
        $this->assertStringContainsString('resetAdminDefaultItems', $modalHtml);
        $this->assertStringContainsString('adminDockItems', $modalHtml);
        $this->assertStringContainsString('visibleAdminItems', $modalHtml);
    }

    public function test_security_headers_allow_google_fonts_and_cdns(): void
    {
        $response = $this->get(route('tenant.login'));
        $response->assertStatus(200);

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString('https://fonts.googleapis.com', $csp);
        $this->assertStringContainsString('https://fonts.gstatic.com', $csp);
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $csp);
        $this->assertStringContainsString('font-src', $csp);
        $this->assertStringContainsString('style-src', $csp);

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }
}
