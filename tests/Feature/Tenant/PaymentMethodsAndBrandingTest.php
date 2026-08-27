<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Dashboard;
use App\Livewire\Tenant\Sales\Create as SalesCreate;
use App\Livewire\Tenant\Settings\Index as SettingsIndex;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Invoice\InvoiceDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class PaymentMethodsAndBrandingTest extends TestCase
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

    public function test_admin_can_add_edit_toggle_and_delete_payment_methods(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $component = Livewire::test(SettingsIndex::class);

        // 1. Add new payment method (e.g. PIX / Crypto)
        $component
            ->set('pmName', 'PIX Instant Payment')
            ->set('pmCode', 'pix')
            ->set('pmDescription', 'Instant QR code scan payment')
            ->set('pmIsActive', true)
            ->call('savePaymentMethod')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payment_methods', [
            'company_id' => $company->id,
            'name' => 'PIX Instant Payment',
            'code' => 'pix',
            'is_active' => 1,
        ]);

        $pix = PaymentMethod::where('company_id', $company->id)->where('code', 'pix')->firstOrFail();

        // 2. Edit payment method
        $component
            ->call('openEditPaymentMethodModal', $pix->id)
            ->set('pmName', 'PIX QR')
            ->call('savePaymentMethod');

        $this->assertSame('PIX QR', $pix->fresh()->name);

        // 3. Toggle Status (Disable/Enable)
        $component->call('togglePaymentMethodStatus', $pix->id);
        $this->assertFalse($pix->fresh()->is_active);

        $component->call('togglePaymentMethodStatus', $pix->id);
        $this->assertTrue($pix->fresh()->is_active);

        // 4. Delete payment method
        $component->call('deletePaymentMethod', $pix->id);
        $this->assertDatabaseMissing('payment_methods', ['id' => $pix->id]);
    }

    public function test_admin_can_update_favicon_brand_logo_and_quotation_color(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        Livewire::test(SettingsIndex::class)
            ->set('logo', 'https://example.com/store-logo.png')
            ->set('favicon', 'https://example.com/store-favicon.ico')
            ->set('website', 'https://mystore.example.com')
            ->set('primaryColor', '#7c3aed')
            ->call('save')
            ->assertHasNoErrors();

        $company->refresh();
        $this->assertSame('https://example.com/store-logo.png', $company->logo);
        $this->assertSame('https://example.com/store-favicon.ico', $company->favicon);
        $this->assertSame('https://mystore.example.com', $company->website);
        $this->assertSame('#7c3aed', $company->primary_color);
    }

    public function test_pos_checkout_renders_dynamic_payment_methods(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        PaymentMethod::create([
            'company_id' => $company->id,
            'name' => 'Crypto USD',
            'code' => 'crypto',
            'is_active' => true,
            'order_index' => 4,
        ]);

        // 1. Standard Layout Checkout Modal
        Livewire::test(SalesCreate::class, ['layout' => 'standard'])
            ->assertSee('Crypto USD')
            ->set('paymentMethod', 'crypto')
            ->assertSet('paymentMethod', 'crypto');

        // 2. Supermarket Touch Layout Checkout Modal
        Livewire::test(SalesCreate::class, ['layout' => 'touch'])
            ->assertSee('Crypto USD')
            ->set('paymentMethod', 'card')
            ->assertSet('paymentMethod', 'card');

        // 3. Square Stand Layout Checkout Modal
        Livewire::test(SalesCreate::class, ['layout' => 'stand'])
            ->assertSee('Crypto USD')
            ->set('paymentMethod', 'crypto')
            ->assertSet('paymentMethod', 'crypto');
    }

    public function test_pos_checkout_exposes_fast_vue_payment_and_cash_islands(): void
    {
        $this->actingAsTenantAdmin();

        Livewire::test(SalesCreate::class, ['layout' => 'standard'])
            ->assertSeeHtml('data-vue-payment-selector')
            ->assertSeeHtml('data-vue-cash-calculator')
            ->assertSeeHtml('wire:ignore')
            ->assertDontSee("\$wire.entangle('cashTendered')", false);
    }

    public function test_cash_change_uses_the_current_payable_total(): void
    {
        [$company] = $this->actingAsTenantAdmin();
        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Change Calculation Item',
            'sale_price' => 19.47,
            'cost_price' => 10,
            'current_stock' => 10,
            'active' => true,
        ]);

        $component = Livewire::test(SalesCreate::class)
            ->call('addProductToCart', $product->id)
            ->set('paymentMethod', 'cash')
            ->set('cashTendered', 500.00);

        $this->assertSame(19.47, $component->get('total'));
        $this->assertSame(480.53, $component->get('changeDue'));
        $component
            ->assertSeeHtml('wire:key="vue-cash-calculator-1947"')
            ->assertSee('$480.53');
    }

    public function test_pos_recovers_from_a_malformed_category_cache_entry(): void
    {
        [$company] = $this->actingAsTenantAdmin();
        Cache::put("pos:{$company->id}:catalog-categories:v3", collect(['stale-category-name']), 60);

        Livewire::test(SalesCreate::class, ['layout' => 'standard'])
            ->assertOk()
            ->assertSee('All Items');

        $cached = Cache::get("pos:{$company->id}:catalog-categories:v3");
        $this->assertFalse($cached->contains(fn ($category) => is_string($category)));
    }

    public function test_pos_recovers_from_malformed_checkout_user_and_payment_caches(): void
    {
        [$company] = $this->actingAsTenantAdmin();
        Cache::put("pos:{$company->id}:checkout-approved-users:v2", collect(['stale-user-name']), 300);
        Cache::put("pos:{$company->id}:checkout-payment-methods:v2", collect(['stale-payment-name']), 300);

        Livewire::test(SalesCreate::class, ['layout' => 'standard'])
            ->assertOk()
            ->assertSee('Jane Admin')
            ->assertSee('Cash');

        $users = Cache::get("pos:{$company->id}:checkout-approved-users:v2");
        $methods = Cache::get("pos:{$company->id}:checkout-payment-methods:v2");
        $this->assertFalse($users->contains(fn ($user) => is_string($user)));
        $this->assertFalse($methods->contains(fn ($method) => is_string($method)));
    }

    public function test_cash_receipt_renders_thermal_layout_and_store_website(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $company->update([
            'logo' => 'https://example.com/brand-logo.png',
            'favicon' => 'https://example.com/brand-favicon.ico',
            'website' => 'https://superpos.example.com',
            'phone' => '123-456-7890',
        ]);

        $sale = Sale::create([
            'company_id' => $company->id,
            'sale_number' => 'INV-REC-001',
            'total' => 84.80,
            'discount' => 0,
            'tax' => 8.00,
            'payment_method' => 'cash',
            'status' => 'completed',
            'items' => [
                ['name' => 'Lorem', 'quantity' => 1, 'price' => 6.50],
                ['name' => 'Dolor Sit', 'quantity' => 1, 'price' => 48.00],
            ],
        ]);

        $response = $this->get(route('tenant.sales.pdf', $sale));

        $response->assertStatus(200);
        $response->assertSee('https://superpos.example.com');
        $response->assertSee('123-456-7890');
        $response->assertSee('Thank you for your visit');
        $response->assertSee('INV-REC-001');
        $response->assertDontSee('reallygreatsite.com');
    }

    public function test_admin_can_change_store_theme_color_and_default_pos_layout(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        Livewire::test(SettingsIndex::class)
            ->set('themeColor', 'emerald')
            ->set('posLayout', 'touch')
            ->call('save')
            ->assertHasNoErrors();

        $company->refresh();
        $this->assertSame('emerald', $company->theme_color);
        $this->assertSame('touch', $company->pos_layout);

        $classes = $company->getThemeColorClasses();
        $this->assertStringContainsString('bg-emerald-600', $classes['bg_primary']);
    }

    public function test_dashboard_pos_layout_modal_and_launch_action(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        Livewire::test(Dashboard::class)
            ->call('openPosLayoutModal')
            ->assertSet('showPosLayoutModal', true)
            ->call('selectLayout', 'stand')
            ->assertSet('selectedPosLayout', 'stand')
            ->call('launchPosWithLayout')
            ->assertRedirect(route('tenant.sales.create', ['layout' => 'stand']));

        $this->assertSame('stand', session('tenant_pos_layout'));
        $this->assertSame('stand', $company->fresh()->pos_layout);
    }

    public function test_pos_terminal_layout_modes_and_quick_switching(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        // 1. Standard mode
        Livewire::test(SalesCreate::class)
            ->assertSet('layout', 'standard')
            ->assertSee('Standard Scanner')
            ->call('switchLayout', 'touch')
            ->assertSet('layout', 'touch')
            ->assertSee('Supermarket Touch')
            ->assertSee('BACK / CLEAR')
            ->assertSee('CHECK OUT')
            ->call('switchLayout', 'stand')
            ->assertSet('layout', 'stand')
            ->assertSee('Square Stand')
            ->assertSee('Favorites')
            ->assertSee('Keypad');
    }

    public function test_58mm_and_80mm_thermal_receipt_pdf_generation(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $sale = Sale::create([
            'company_id' => $company->id,
            'sale_number' => 'REC-58MM-TEST',
            'total' => 29.50,
            'discount' => 0,
            'tax' => 2.50,
            'payment_method' => 'cash',
            'status' => 'completed',
            'items' => [
                ['name' => 'Mini Thermal Item 1', 'quantity' => 2, 'price' => 10.00],
                ['name' => 'Mini Thermal Item 2', 'quantity' => 1, 'price' => 7.00],
            ],
        ]);

        $deliveryService = app(InvoiceDeliveryService::class);

        // 1. Generate 58mm PDF
        $pdf58 = $deliveryService->generateInvoicePdf($sale, '58mm');
        $this->assertNotEmpty($pdf58);
        $this->assertStringStartsWith('%PDF', $pdf58);

        // 2. Generate 80mm PDF
        $pdf80 = $deliveryService->generateInvoicePdf($sale, '80mm');
        $this->assertNotEmpty($pdf80);
        $this->assertStringStartsWith('%PDF', $pdf80);

        // 3. Download endpoint with format=58mm
        $response = $this->get(route('tenant.sales.pdf', ['sale' => $sale, 'download' => 1, 'format' => '58mm']));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // 4. Stream endpoint with format=80mm
        $streamResponse = $this->get(route('tenant.sales.pdf', ['sale' => $sale, 'stream' => 1, 'format' => '80mm']));
        $streamResponse->assertStatus(200);
        $streamResponse->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_logo_upload_strict_file_validation(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        Storage::fake('public');

        // 1. Valid PNG upload
        $validFile = UploadedFile::fake()->image('logo.png', 200, 200);
        Livewire::test(SettingsIndex::class)
            ->set('logoFile', $validFile)
            ->call('save')
            ->assertHasNoErrors(['logoFile']);

        // 2. Invalid image mime (e.g. GIF is disallowed by mimes:jpeg,png,jpg,webp,svg)
        $invalidMimeFile = UploadedFile::fake()->image('logo.gif');
        Livewire::test(SettingsIndex::class)
            ->set('logoFile', $invalidMimeFile)
            ->assertHasErrors(['logoFile'])
            ->assertDispatched('notify');

        // 3. Oversized image (> 2048 KB)
        $oversizedFile = UploadedFile::fake()->image('huge.png')->size(3000);
        Livewire::test(SettingsIndex::class)
            ->set('logoFile', $oversizedFile)
            ->assertHasErrors(['logoFile'])
            ->assertDispatched('notify');
    }

    public function test_pos_checkout_in_modal_customer_search_and_creation(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $customerA = Customer::create([
            'company_id' => $company->id,
            'name' => 'Alice Walker',
            'phone' => '111-222-3333',
            'email' => 'alice@example.com',
            'loyalty_points' => 150,
            'credit_limit' => 500,
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Test Coffee',
            'sku' => 'COF-1',
            'sale_price' => 5.00,
            'cost_price' => 2.00,
            'active' => true,
        ]);

        $component = Livewire::test(SalesCreate::class)
            ->call('addProductToCart', $product->id)
            ->set('showCheckoutModal', true);

        // 1. Toggle in-modal customer search
        $component->call('toggleInModalCustomerSearch')
            ->assertSet('inModalCustomerSearchOpen', true)
            ->set('inModalCustomerSearch', 'Alice')
            ->assertSee('Alice Walker')
            ->call('selectInModalCustomer', $customerA->id)
            ->assertSet('customerId', $customerA->id)
            ->assertSet('inModalCustomerSearchOpen', false)
            ->assertSee('Alice Walker');

        // 2. In-modal quick customer creation
        $component->call('openInModalNewCustomer')
            ->assertSet('inModalNewCustomerOpen', true)
            ->set('inModalNewCustomerName', 'Bob Builder')
            ->set('inModalNewCustomerPhone', '555-444-3333')
            ->set('inModalNewCustomerEmail', 'bob@example.com')
            ->call('createInModalCustomer')
            ->assertSet('inModalNewCustomerOpen', false)
            ->assertSee('Bob Builder');

        $this->assertDatabaseHas('customers', [
            'company_id' => $company->id,
            'name' => 'Bob Builder',
            'phone' => '555-444-3333',
        ]);
    }

    public function test_favicon_ico_safe_preview_and_upload(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        Storage::fake('public');

        $icoFile = UploadedFile::fake()->create('favicon.ico', 10, 'image/x-icon');

        $component = Livewire::test(SettingsIndex::class)
            ->set('faviconFile', $icoFile)
            ->call('save')
            ->assertHasNoErrors();

        $company->refresh();
        $this->assertNotNull($company->favicon);
        $this->assertStringContainsString('favicon', $company->getFaviconUrl());
    }

    public function test_saved_logo_and_favicon_persistence_and_url_resolution(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        Storage::fake('public');

        // Store a fake logo file
        Storage::disk('public')->put('tenant-logos/sample-logo.png', 'fake image content');
        Storage::disk('public')->put('tenant-favicons/sample-favicon.ico', 'fake ico content');

        // 1. When saved as /storage/tenant-logos/...
        $company->update([
            'logo' => '/storage/tenant-logos/sample-logo.png',
            'favicon' => '/storage/tenant-favicons/sample-favicon.ico',
        ]);

        $this->assertStringNotContainsString('storage/storage', $company->getLogoUrl());
        $this->assertStringContainsString('tenant-logos/sample-logo.png', $company->getLogoUrl());
        $this->assertStringNotContainsString('storage/storage', $company->getFaviconUrl());
        $this->assertStringContainsString('tenant-favicons/sample-favicon.ico', $company->getFaviconUrl());

        // 2. Component mount renders saved logo & favicon
        Livewire::test(SettingsIndex::class)
            ->assertSee('sample-logo.png')
            ->assertSee('sample-favicon.ico');
    }

    public function test_thermal_receipt_qr_code_and_2_column_layout(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $sale = Sale::create([
            'company_id' => $company->id,
            'sale_number' => 'S-20260823210736',
            'customer_name' => 'Prakash',
            'total' => 10.50,
            'discount' => 0,
            'tax' => 0,
            'paid_amount' => 10.50,
            'payment_method' => 'cash',
            'status' => 'completed',
            'items' => [
                ['name' => 'Artisan Bread', 'quantity' => 1, 'price' => 4.50],
                ['name' => 'Chocolate Doughnut', 'quantity' => 1, 'price' => 6.00],
            ],
        ]);

        $response = $this->get(route('tenant.sales.pdf', $sale));
        $response->assertStatus(200);
        $response->assertSee('Receipt #:');
        $response->assertSee('S-20260823210736');
        $response->assertSee('Customer:');
        $response->assertSee('Prakash');
        $response->assertSee('Staff:');
        $response->assertSee('Payment:');
        $response->assertSee('Cash');
        $response->assertSee('Status:');
        $response->assertSee('Paid');
        $response->assertSee('Artisan Bread');
        $response->assertSee('Chocolate Doughnut');
        $response->assertSee('Subtotal:');
        $response->assertSee('TOTAL AMOUNT:');
        $response->assertSee('Amount Paid:');
        $response->assertSee('Scan for digital e-receipt &amp; verify', false);
        $response->assertSee('<svg', false);

        // Verify PDF binary also builds cleanly with QR code
        $deliveryService = app(InvoiceDeliveryService::class);
        $pdf58 = $deliveryService->generateInvoicePdf($sale, '58mm');
        $this->assertNotEmpty($pdf58);
        $this->assertStringStartsWith('%PDF', $pdf58);
    }
}
