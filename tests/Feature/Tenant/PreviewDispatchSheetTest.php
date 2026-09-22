<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Sales\Create as SalesCreate;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class PreviewDispatchSheetTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

    public function test_pos_review_shows_preview_and_dispatch_sheet_with_tabs_and_qr_code(): void
    {
        [$company] = $this->actingAsTenantAdmin();
        $company->update(['tax_id' => '29ABCDE1234F1Z5']);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Walk-in Regular Customer',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Braided USB-C Fast Charging Cable 2m',
            'sale_price' => 15.33,
            'active' => true,
        ]);

        Livewire::test(SalesCreate::class)
            ->set('customerId', $customer->id)
            ->set('items.0.product_id', $product->id)
            ->set('items.0.name', 'Braided USB-C Fast Charging Cable 2m')
            ->set('items.0.quantity', 1)
            ->set('items.0.price', 15.33)
            ->call('openInvoicePreview')
            ->assertSet('showInvoicePreview', true)
            ->assertSee('Preview &amp; Dispatch #', false)
            ->assertSee('DRAFT')
            ->assertSee('GSTIN: 29ABCDE1234F1Z5')
            ->assertSee('Standard A4')
            ->assertSee('80mm POS')
            ->assertSee('58mm Receipt')
            ->assertSee('Mobile Slip')
            ->assertSee('Scan to Verify')
            ->assertSee('data:image')
            ->assertSee('Back to Payment')
            ->assertSee('Print Preview')
            ->assertSee('Confirm Sale &amp; Complete', false);
    }

    public function test_document_template_renders_scannable_qr_code_for_sales(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        $sale = Sale::create([
            'company_id' => $company->id,
            'sale_number' => 'S-20260922165718',
            'customer_name' => 'Walk-in Client',
            'total' => 15.33,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        $response = $this->get("/tenant/documents/invoice/{$sale->id}/render-html?format=a4");
        $response->assertStatus(200);
        $response->assertSee('Scan to Verify');
        $response->assertSee('data:image/svg+xml;base64,');
        $response->assertDontSee('<rect x="3" y="3" width="7" height="7"></rect>', false);
    }
}
