<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Products\Index as ProductsIndex;
use App\Livewire\Tenant\Sales\Create as SalesCreate;
use App\Livewire\Tenant\Sales\Show as SalesShow;
use App\Livewire\Tenant\Settings\Index;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class ProductsAndSalesTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

    public function test_product_crud_and_isolation_between_tenants(): void
    {
        [$companyA] = $this->actingAsTenantAdmin();

        Livewire::test(ProductsIndex::class)
            ->call('newProduct')
            ->set('name', 'Widget')
            ->set('costPrice', 5)
            ->set('salePrice', 10)
            ->set('currentStock', 100)
            ->set('minimumStock', 5)
            ->call('save');

        $product = Product::firstOrFail();
        $this->assertSame($companyA->id, $product->company_id);

        // A second tenant must never see the first tenant's products.
        app()->forgetInstance('tenant.company_id');
        [$companyB] = $this->actingAsTenantAdmin(Company::create(['name' => 'Other Co']));
        app()->instance('tenant.company_id', $companyB->id);

        Livewire::test(ProductsIndex::class)->assertDontSee('Widget');
    }

    public function test_stock_adjustment_is_audited(): void
    {
        $this->actingAsTenantAdmin();
        $product = Product::create(['name' => 'Widget', 'current_stock' => 10, 'sale_price' => 10]);

        Livewire::test(ProductsIndex::class)
            ->call('startAdjust', $product->id)
            ->set('adjustmentQty', -3)
            ->set('adjustmentReason', 'Damaged stock')
            ->call('applyAdjustment');

        $this->assertSame(7.0, (float) $product->fresh()->current_stock);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.stock_adjusted']);
    }

    public function test_completing_a_sale_decrements_stock(): void
    {
        $this->actingAsTenantAdmin();
        $product = Product::create(['name' => 'Widget', 'current_stock' => 10, 'sale_price' => 25, 'active' => true]);

        Livewire::test(SalesCreate::class)
            ->set('items.0.product_id', $product->id)
            ->set('items.0.quantity', 3)
            ->set('items.0.price', 25)
            ->call('save')
            ->assertSet('showSaleSuccessModal', true);

        $this->assertSame(7.0, (float) $product->fresh()->current_stock);
        $this->assertDatabaseHas('sales', ['total' => 75]);
    }

    public function test_cancelling_a_sale_restores_stock(): void
    {
        [, $user] = $this->actingAsTenantAdmin();
        $product = Product::create(['name' => 'Widget', 'current_stock' => 10, 'sale_price' => 25, 'active' => true]);

        $sale = Livewire::test(SalesCreate::class)
            ->set('items.0.product_id', $product->id)
            ->set('items.0.quantity', 3)
            ->set('items.0.price', 25)
            ->call('save');

        $this->assertSame(7.0, (float) $product->fresh()->current_stock);

        $saleModel = Sale::firstOrFail();
        Livewire::test(SalesShow::class, ['sale' => $saleModel])->call('cancel');

        $this->assertSame(10.0, (float) $product->fresh()->current_stock);
        $this->assertSame('cancelled', $saleModel->fresh()->status);
    }

    public function test_sale_rejects_a_product_belonging_to_another_tenant(): void
    {
        $this->actingAsTenantAdmin();
        $otherCompany = Company::create(['name' => 'Other Co']);
        $foreignProduct = Product::create(['company_id' => $otherCompany->id, 'name' => 'Not Mine', 'sale_price' => 10]);

        Livewire::test(SalesCreate::class)
            ->set('items.0.product_id', $foreignProduct->id)
            ->set('items.0.quantity', 1)
            ->set('items.0.price', 10)
            ->call('save')
            ->assertHasErrors(['items.0.product_id']);
    }

    public function test_pos_terminal_cart_stepper_and_hold_order(): void
    {
        $this->actingAsTenantAdmin();
        $product1 = Product::create(['name' => 'Melon', 'current_stock' => 20, 'sale_price' => 8, 'active' => true]);
        $product2 = Product::create(['name' => 'Semangka', 'current_stock' => 15, 'sale_price' => 15, 'active' => true]);

        $pos = Livewire::test(SalesCreate::class)
            ->call('addProductToCart', $product1->id)
            ->call('addProductToCart', $product2->id)
            ->call('increaseQuantity', 0) // Melon qty -> 2
            ->call('increaseQuantity', 1) // Semangka qty -> 2
            ->assertSee('Melon')
            ->assertSee('Semangka');

        $this->assertSame(46.0, (float) $pos->get('total'));

        // Test PENDING hold order
        $pos->call('holdOrder');
        $this->assertDatabaseHas('sales', [
            'status' => 'pending',
            'total' => 46.0,
        ]);
    }

    public function test_pos_barcode_auto_add_and_category_filtering(): void
    {
        $this->actingAsTenantAdmin();
        $catFruit = Category::create(['name' => 'Fresh Fruit']);
        $catVeg = Category::create(['name' => 'Vegetables']);

        $apple = Product::create(['name' => 'Apple', 'barcode' => 'APP-100', 'sale_price' => 5, 'category_id' => $catFruit->id, 'active' => true]);
        $terong = Product::create(['name' => 'Terong', 'barcode' => 'TRG-200', 'sale_price' => 4, 'category_id' => $catVeg->id, 'active' => true]);

        // Category filter test
        Livewire::test(SalesCreate::class)
            ->call('selectCategory', $catFruit->id)
            ->assertSee('Apple')
            ->assertDontSee('Terong');

        // Barcode scan test
        $pos = Livewire::test(SalesCreate::class)
            ->set('search', 'APP-100');

        $this->assertSame(1, count($pos->get('items')));
        $this->assertSame($apple->id, $pos->get('items')[0]['product_id']);
    }

    public function test_pos_checkout_without_explicit_container_binding(): void
    {
        $this->actingAsTenantAdmin();
        $product = Product::create(['name' => 'Pisang', 'current_stock' => 10, 'sale_price' => 6, 'active' => true]);

        // Deliberately forget container instance to mimic a Livewire update cycle
        app()->forgetInstance('tenant.company_id');

        Livewire::test(SalesCreate::class)
            ->call('addProductToCart', $product->id)
            ->call('save')
            ->assertSet('showSaleSuccessModal', true);

        $this->assertDatabaseHas('sales', [
            'total' => 6.0,
            'status' => 'completed',
        ]);
    }

    public function test_pos_quick_customer_creation(): void
    {
        $this->actingAsTenantAdmin();

        $pos = Livewire::test(SalesCreate::class)
            ->set('newCustomerName', 'Jane Doe')
            ->set('newCustomerPhone', '+15550199')
            ->set('newCustomerEmail', 'jane@example.com')
            ->call('createQuickCustomer');

        $this->assertDatabaseHas('customers', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+15550199',
        ]);

        $customer = Customer::where('email', 'jane@example.com')->first();
        $this->assertSame($customer->id, $pos->get('customerId'));
    }

    public function test_tenant_smtp_configuration_and_whatsapp_invoice_generation(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        Livewire::test(Index::class)
            ->set('smtpHost', 'smtp.mailtrap.io')
            ->set('smtpPort', 2525)
            ->set('smtpUsername', 'testuser')
            ->set('smtpPassword', 'secret123')
            ->set('smtpFromAddress', 'billing@store.test')
            ->set('smtpFromName', 'Store Billing')
            ->call('save');

        $this->assertDatabaseHas('configurations', [
            'company_id' => $company->id,
            'key' => 'smtp_host',
            'value' => 'smtp.mailtrap.io',
        ]);

        // Create a sale and verify WhatsApp generation
        $sale = Sale::create([
            'sale_number' => 'S-999',
            'customer_name' => 'Jane Doe',
            'total' => 50.00,
            'items' => [['name' => 'Melon', 'quantity' => 2, 'price' => 25.00]],
            'status' => 'completed',
        ]);

        $show = Livewire::test(SalesShow::class, ['sale' => $sale]);
        $url = $show->get('whatsAppUrl');

        $this->assertStringContainsString('https://api.whatsapp.com/send', $url);
        $this->assertStringContainsString('S-999', $url);
    }

    public function test_product_image_upload_and_url_storage(): void
    {
        Storage::fake('public');
        [$company] = $this->actingAsTenantAdmin();

        $file = UploadedFile::fake()->image('fresh-orange.jpg', 400, 400);

        Livewire::test(ProductsIndex::class)
            ->call('newProduct')
            ->set('name', 'Fresh Orange')
            ->set('costPrice', 1.20)
            ->set('salePrice', 2.50)
            ->set('currentStock', 50)
            ->set('minimumStock', 5)
            ->set('imageFile', $file)
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::where('name', 'Fresh Orange')->firstOrFail();
        $this->assertNotNull($product->image_url);
        $this->assertStringStartsWith('/storage/products/', $product->image_url);
    }

    public function test_pos_customer_modal_search_and_selection_across_layouts(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        $c1 = Customer::create(['company_id' => $company->id, 'name' => 'Alice Walker', 'phone' => '+1111']);
        $c2 = Customer::create(['company_id' => $company->id, 'name' => 'Bob Builder', 'phone' => '+2222']);

        // 1. Touch POS Customer Selection
        $pos = Livewire::test(SalesCreate::class, ['layout' => 'touch'])
            ->call('openCustomerSelectModal')
            ->assertSet('showCustomerSelectModal', true)
            ->set('customerSearch', 'Alice')
            ->assertSee('Alice Walker')
            ->call('selectCustomer', $c1->id)
            ->assertSet('customerId', $c1->id)
            ->assertSet('showCustomerSelectModal', false);

        $this->assertSame($c1->id, $pos->get('selectedCustomer')?->id);

        // 2. Stand POS Customer Selection
        $posStand = Livewire::test(SalesCreate::class, ['layout' => 'stand'])
            ->call('openCustomerSelectModal')
            ->assertSet('showCustomerSelectModal', true)
            ->call('selectCustomer', $c2->id)
            ->assertSet('customerId', $c2->id);

        $this->assertSame($c2->id, $posStand->get('selectedCustomer')?->id);
    }

    public function test_pos_sale_completion_shows_popup_modal_with_pdf_whatsapp_and_email_sharing(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Michael Scott',
            'email' => 'michael@dundermifflin.test',
            'phone' => '+15550188',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Paper Ream',
            'current_stock' => 100,
            'sale_price' => 35.00,
            'active' => true,
        ]);

        $pos = Livewire::test(SalesCreate::class)
            ->set('customerId', $customer->id)
            ->set('items.0.product_id', $product->id)
            ->set('items.0.quantity', 2)
            ->set('items.0.price', 35.00)
            ->set('paymentMethod', 'card')
            ->call('save')
            ->assertSet('showSaleSuccessModal', true)
            ->assertSee('Sale Completed!')
            ->assertSee('Michael Scott')
            ->assertSee('$70.00')
            ->assertSee('Download & Print PDF Invoice', false)
            ->assertSee('Share Receipt on WhatsApp')
            ->assertSee('Send Invoice via Email');

        $completedSale = $pos->get('completedSale');
        $this->assertNotNull($completedSale);
        $this->assertSame(70.0, (float) $completedSale->total);
        $this->assertSame('michael@dundermifflin.test', $pos->get('shareEmail'));
        $this->assertSame('+15550188', $pos->get('sharePhone'));

        // Test WhatsApp URL generator
        $whatsAppUrl = $pos->get('completedSaleWhatsAppUrl');
        $this->assertTrue(str_contains($whatsAppUrl, 'https://wa.me') || str_contains($whatsAppUrl, 'whatsapp.com'));

        // Test Email send action
        $pos->set('shareEmail', 'office@dundermifflin.test')
            ->call('sendSaleEmail')
            ->assertSet('emailStatus', 'Tax Invoice sent successfully to office@dundermifflin.test!');

        // Test Next Order Reset
        $pos->call('startNextSale')
            ->assertSet('showSaleSuccessModal', false)
            ->assertSet('completedSale', null)
            ->assertSet('customerId', null);
    }
}
