<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Financials\CashRegister as CashRegisterComponent;
use App\Livewire\Tenant\Financials\Payables as PayablesComponent;
use App\Livewire\Tenant\Financials\Receivables as ReceivablesComponent;
use App\Livewire\Tenant\Restaurant\Pos as RestaurantPos;
use App\Livewire\Tenant\Sales\Create as SalesCreate;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\VendorBill;
use App\Models\VendorBillPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class FinancialsAndPosEnhancementsTest extends TestCase
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

    public function test_inline_price_override_at_pos_tracks_base_price_and_overridden_status(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Premium Wireless Headset',
            'code' => 'SKU-HEADSET-01',
            'sale_price' => 120.00,
            'cost_price' => 70.00,
            'current_stock' => 50,
            'active' => true,
        ]);

        $component = Livewire::test(SalesCreate::class)
            ->call('addProductToCart', $product->id)
            ->assertSet('items.0.price', 120.00)
            ->assertSet('items.0.base_price', 120.00)
            ->assertSet('items.0.is_overridden', false);

        // Perform inline price override (discounted to $99.00 for special client)
        $component
            ->call('applyPriceOverride', 0, 99.00)
            ->assertSet('items.0.price', 99.00)
            ->assertSet('items.0.base_price', 120.00)
            ->assertSet('items.0.is_overridden', true)
            ->set('notes', 'VIP discount negotiated by store manager')
            ->call('save');

        $sale = Sale::where('company_id', $company->id)->latest()->firstOrFail();
        $this->assertSame(99.00, (float) $sale->total);
        $this->assertSame('VIP discount negotiated by store manager', $sale->notes);

        $savedItem = $sale->items[0];
        $this->assertSame(99.0, (float) $savedItem['price']);
        $this->assertSame(120.0, (float) $savedItem['base_price']);
        $this->assertTrue((bool) $savedItem['is_overridden']);
    }

    public function test_split_payment_checkout_records_order_payments_and_calculates_balance(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Acme Wholesale Corp',
            'phone' => '555-0199',
            'email' => 'billing@acme.test',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Industrial Scanner Unit',
            'code' => 'SKU-ISCAN-01',
            'sale_price' => 300.00,
            'cost_price' => 180.00,
            'current_stock' => 20,
            'active' => true,
        ]);

        $component = Livewire::test(SalesCreate::class)
            ->set('customerId', $customer->id)
            ->call('addProductToCart', $product->id)
            ->call('openCheckoutModal')
            ->call('toggleSplitPayment')
            ->assertSet('isSplitPayment', true)
            ->set('splitPayments', [
                [
                    'payment_method' => 'cash',
                    'amount' => 100.00,
                    'tendered' => 100.00,
                    'reference_number' => 'CASH-01',
                ],
                [
                    'payment_method' => 'card',
                    'amount' => 150.00,
                    'tendered' => 150.00,
                    'reference_number' => 'AUTH-449102',
                ],
            ])
            ->set('dueDate', now()->addDays(15)->format('Y-m-d'))
            ->set('notes', 'Split payment: $100 Cash + $150 Card, $50 due in 15 days')
            ->call('save')
            ->assertSet('showSaleSuccessModal', true);

        $sale = Sale::where('company_id', $company->id)->latest()->firstOrFail();
        $this->assertSame(300.00, (float) $sale->total);
        $this->assertSame(250.00, (float) $sale->paid_amount);
        $this->assertSame(50.00, (float) $sale->due_amount);
        $this->assertSame('partially_paid', $sale->payment_status);
        $this->assertSame('split', $sale->payment_method);
        $this->assertSame('Split payment: $100 Cash + $150 Card, $50 due in 15 days', $sale->notes);

        // Verify relational order_payments table entries
        $payments = OrderPayment::where('sale_id', $sale->id)->get();
        $this->assertCount(2, $payments);

        $cashPay = $payments->firstWhere('payment_method', 'cash');
        $this->assertNotNull($cashPay);
        $this->assertSame(100.00, (float) $cashPay->amount);

        $cardPay = $payments->firstWhere('payment_method', 'card');
        $this->assertNotNull($cardPay);
        $this->assertSame(150.00, (float) $cardPay->amount);
        $this->assertSame('AUTH-449102', $cardPay->reference_number);
    }

    public function test_thermal_receipt_renders_logo_terms_notes_and_split_breakdown(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $company->update([
            'logo' => 'https://example.com/store-thermal-logo.png',
            'invoice_terms' => 'All returns subject to 14-day warranty with original receipt.',
            'phone' => '1-800-555-0199',
            'website' => 'https://mystore.example.com',
        ]);

        $sale = Sale::create([
            'company_id' => $company->id,
            'sale_number' => 'S-20260822-001',
            'total' => 150.00,
            'paid_amount' => 100.00,
            'due_amount' => 50.00,
            'due_date' => now()->addDays(7)->toDateString(),
            'discount' => 0,
            'payment_method' => 'split',
            'payment_status' => 'partially_paid',
            'notes' => 'Deliver to back loading dock entrance.',
            'status' => 'completed',
            'items' => [
                [
                    'name' => 'Custom Assembled Desk',
                    'quantity' => 1,
                    'price' => 150.00,
                    'base_price' => 180.00,
                    'is_overridden' => true,
                ],
            ],
        ]);

        OrderPayment::create([
            'company_id' => $company->id,
            'sale_id' => $sale->id,
            'payment_method' => 'cash',
            'amount' => 50.00,
            'tendered' => 50.00,
            'change_returned' => 0,
            'reference_number' => null,
        ]);

        OrderPayment::create([
            'company_id' => $company->id,
            'sale_id' => $sale->id,
            'payment_method' => 'card',
            'amount' => 50.00,
            'tendered' => 50.00,
            'change_returned' => 0,
            'reference_number' => 'CARD-AUTH-99',
        ]);

        $response = $this->get(route('tenant.sales.pdf', $sale));
        $response->assertStatus(200);

        // Assert store branding and logo
        $response->assertSee('https://example.com/store-thermal-logo.png');
        $response->assertSee('1-800-555-0199');

        // Assert price override indicator on receipt
        $response->assertSee('Custom Assembled Desk');
        $response->assertSee('Std: $180.00');

        // Assert split payment methods breakdown
        $response->assertSee('Cash');
        $response->assertSee('Card');
        $response->assertSee('CARD-AUTH-99');

        // Assert remaining balance due
        $response->assertSee('Remaining Balance:');
        $response->assertSee('50.00');

        // Assert order remarks / notes
        $response->assertSee('Deliver to back loading dock entrance.');

        // Assert terms & conditions footer
        $response->assertSee('All returns subject to 14-day warranty with original receipt.');
    }

    public function test_accounts_receivable_kpi_cards_and_manual_payment_collection(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Metro Retailers Ltd',
            'phone' => '555-4321',
        ]);

        $sale = Sale::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'sale_number' => 'S-REC-1001',
            'total' => 500.00,
            'paid_amount' => 200.00,
            'due_amount' => 300.00,
            'due_date' => now()->addDays(5)->toDateString(),
            'payment_method' => 'card',
            'payment_status' => 'partially_paid',
            'status' => 'completed',
            'items' => [['name' => 'Carton Goods', 'quantity' => 10, 'price' => 50.00]],
        ]);

        $component = Livewire::test(ReceivablesComponent::class)
            ->assertSee('S-REC-1001')
            ->assertSee('Metro Retailers Ltd')
            ->assertSee('$300.00')
            ->call('openPaymentModal', $sale->id)
            ->assertSet('showPaymentModal', true)
            ->assertSet('paymentAmount', 300.00)
            ->set('paymentAmount', 300.00)
            ->set('paymentMethod', 'bank_transfer')
            ->set('referenceNumber', 'WIRE-TXN-5544')
            ->set('paymentNotes', 'Final settlement payment via bank transfer')
            ->call('recordPayment')
            ->assertSet('showPaymentModal', false);

        $sale->refresh();
        $this->assertSame(500.00, (float) $sale->paid_amount);
        $this->assertSame(0.00, (float) $sale->due_amount);
        $this->assertSame('paid', $sale->payment_status);

        $payment = OrderPayment::where('sale_id', $sale->id)->where('reference_number', 'WIRE-TXN-5544')->firstOrFail();
        $this->assertSame(300.00, (float) $payment->amount);
        $this->assertSame('bank_transfer', $payment->payment_method);
    }

    public function test_accounts_payable_bill_creation_and_settlement_installment_logging(): void
    {
        Storage::fake('public');
        [$company, $admin] = $this->actingAsTenantAdmin();

        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'Global Logistics Inc',
            'email' => 'accounts@globallogistics.test',
        ]);

        $billFile = UploadedFile::fake()->create('supplier-invoice.pdf', 100);

        // 1. Create a Vendor Bill
        $component = Livewire::test(PayablesComponent::class)
            ->call('openCreateBillModal')
            ->assertSet('showBillModal', true)
            ->set('supplierId', $supplier->id)
            ->set('billNumber', 'VB-2026-009')
            ->set('category', 'logistics')
            ->set('title', 'Container Freight Shipping Q3')
            ->set('amount', 1200.00)
            ->set('taxAmount', 100.00)
            ->set('billDate', now()->toDateString())
            ->set('dueDate', now()->addDays(20)->toDateString())
            ->set('attachment', $billFile)
            ->set('notes', 'Payment terms 20 days net')
            ->call('saveBill')
            ->assertSet('showBillModal', false);

        $bill = VendorBill::where('company_id', $company->id)->where('bill_number', 'VB-2026-009')->firstOrFail();
        $this->assertSame(1200.00, (float) $bill->amount);
        $this->assertSame(100.00, (float) $bill->tax_amount);
        $this->assertSame(1300.00, (float) $bill->due_amount);
        $this->assertSame(0.00, (float) $bill->paid_amount);
        $this->assertSame('pending', $bill->status);
        $this->assertNotNull($bill->attachment_path);

        // 2. Record Settlement Payment on the Bill
        $proofFile = UploadedFile::fake()->create('bank-wire-proof.png', 50);

        $component
            ->call('openPaymentModal', $bill->id)
            ->assertSet('showPaymentModal', true)
            ->set('settlementAmount', 800.00)
            ->set('settlementMethod', 'bank_transfer')
            ->set('referenceNumber', 'WIRE-889922')
            ->set('settlementProof', $proofFile)
            ->set('settlementNotes', 'First installment 800 paid')
            ->call('recordSettlement')
            ->assertSet('showPaymentModal', false);

        $bill->refresh();
        $this->assertSame(800.00, (float) $bill->paid_amount);
        $this->assertSame(500.00, (float) $bill->due_amount);
        $this->assertSame('partially_paid', $bill->status);

        $settlement = VendorBillPayment::where('vendor_bill_id', $bill->id)->firstOrFail();
        $this->assertSame(800.00, (float) $settlement->amount);
        $this->assertSame('WIRE-889922', $settlement->reference_number);
        $this->assertNotNull($settlement->attachment_path);
    }

    public function test_restaurant_pos_supports_price_override_notes_and_multi_payments(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        $company->update(['business_type' => 'restaurant']);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Gourmet Steak Special',
            'code' => 'DINE-STEAK-01',
            'sale_price' => 45.00,
            'cost_price' => 20.00,
            'active' => true,
        ]);

        $component = Livewire::test(RestaurantPos::class)
            ->call('addItemDirect', $product->id)
            ->assertCount('items', 1);

        $itemId = $component->get('items.0.id');

        // Apply price override to $38.00
        $component
            ->call('applyPriceOverride', $itemId, 38.00)
            ->assertSet('items.0.price', 38.00)
            ->assertSet('items.0.is_overridden', true)
            ->set('notes', 'Guest requested medium rare with extra sauce')
            ->call('toggleSplitPayment')
            ->assertSet('isSplitPayment', true)
            ->set('splitPayments', [
                ['payment_method' => 'cash', 'amount' => 20.00, 'tendered' => 20.00, 'reference_number' => ''],
                ['payment_method' => 'card', 'amount' => 18.00, 'tendered' => 18.00, 'reference_number' => 'TXN-9021'],
            ])
            ->call('settleBill');

        $sale = Sale::where('company_id', $company->id)->latest()->firstOrFail();
        $this->assertSame(38.00, (float) $sale->total);
        $this->assertSame(38.00, (float) $sale->paid_amount);
        $this->assertSame(0.00, (float) $sale->due_amount);
        $this->assertSame('paid', $sale->payment_status);
        $this->assertSame('Guest requested medium rare with extra sauce', $sale->notes);

        $payments = OrderPayment::where('sale_id', $sale->id)->get();
        $this->assertCount(2, $payments);
    }

    public function test_cash_register_open_movements_and_close_produce_correct_z_report(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Bottled Water',
            'code' => 'SKU-WATER-01',
            'sale_price' => 50.00,
            'cost_price' => 20.00,
            'current_stock' => 100,
            'active' => true,
        ]);

        // 1. Open the register with a $100 float.
        Livewire::test(CashRegisterComponent::class)
            ->call('openRegisterModal')
            ->assertSet('showOpenModal', true)
            ->set('openingBalance', 100.00)
            ->call('openRegister')
            ->assertSet('showOpenModal', false);

        $register = CashRegister::where('company_id', $company->id)->where('status', 'open')->firstOrFail();
        $this->assertSame(100.00, (float) $register->opening_balance);

        // 2. Record a cash-in and a cash-out movement.
        Livewire::test(CashRegisterComponent::class)
            ->call('openMovementModal', 'cash_in')
            ->set('movementAmount', 20.00)
            ->set('movementReason', 'Float top-up')
            ->call('recordMovement')
            ->assertSet('showMovementModal', false);

        Livewire::test(CashRegisterComponent::class)
            ->call('openMovementModal', 'cash_out')
            ->set('movementAmount', 10.00)
            ->set('movementReason', 'Petty cash')
            ->call('recordMovement')
            ->assertSet('showMovementModal', false);

        // 3. Complete a cash sale through the POS — its OrderPayment should count toward expected cash.
        Livewire::test(SalesCreate::class)
            ->call('addProductToCart', $product->id)
            ->call('openCheckoutModal')
            ->call('save')
            ->assertSet('showSaleSuccessModal', true);

        // Expected cash in drawer: 100 (opening) + 50 (cash sale) + 20 (cash in) - 10 (cash out) = 160.
        $liveComponent = Livewire::test(CashRegisterComponent::class);
        $liveSummary = $liveComponent->instance()->buildReportSummary($register->fresh());
        $this->assertSame(160.00, (float) $liveSummary['expected_cash']);
        $this->assertSame(50.00, (float) $liveSummary['cash_sales']);

        // 4. Close the register with a counted amount that's $5 short.
        Livewire::test(CashRegisterComponent::class)
            ->call('openCloseModal')
            ->assertSet('showCloseModal', true)
            ->assertSet('closeExpectedCash', 160.00)
            ->set('countedClosingBalance', 155.00)
            ->call('closeRegister')
            ->assertSet('showCloseModal', false);

        $register->refresh();
        $this->assertSame('closed', $register->status);
        $this->assertSame(160.00, (float) $register->expected_closing_balance);
        $this->assertSame(155.00, (float) $register->counted_closing_balance);
        $this->assertSame(-5.00, (float) $register->cash_difference);
        $this->assertNotNull($register->closed_at);
        $this->assertSame($admin->id, $register->closed_by);

        // No open register remains, so opening a new one should be offered again.
        $this->assertNull(CashRegister::openFor($company->id));
    }

    public function test_live_invoice_preview_modal_displays_cart_breakdown_and_notes_before_sale_completion(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Sophia Martinez',
            'phone' => '+1-555-0987',
            'email' => 'sophia@example.test',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Ergonomic Standing Desk',
            'code' => 'SKU-DESK-01',
            'sale_price' => 450.00,
            'cost_price' => 280.00,
            'current_stock' => 10,
            'active' => true,
        ]);

        Livewire::test(SalesCreate::class)
            ->call('addProductToCart', $product->id)
            ->call('selectCustomer', $customer->id)
            ->call('openCheckoutModal')
            ->assertSet('showCheckoutModal', true)
            ->assertSet('showInvoicePreview', false)
            ->set('notes', 'Priority delivery requested by customer')
            ->set('cashTendered', 500.00)
            ->call('openInvoicePreview')
            ->assertSet('showInvoicePreview', true)
            ->assertSee('Live Receipt / Invoice Preview')
            ->assertSee('DRAFT INVOICE PREVIEW')
            ->assertSee('Ergonomic Standing Desk')
            ->assertSee('Sophia Martinez')
            ->assertSee('Priority delivery requested by customer')
            ->call('closeInvoicePreview')
            ->assertSet('showInvoicePreview', false)
            ->assertSet('showCheckoutModal', true)
            ->call('save')
            ->assertSet('showInvoicePreview', false)
            ->assertSet('showCheckoutModal', false)
            ->assertSet('showSaleSuccessModal', true);

        $sale = Sale::where('company_id', $company->id)->latest()->firstOrFail();
        $this->assertSame(450.00, (float) $sale->total);
        $this->assertSame('Priority delivery requested by customer', $sale->notes);
        $this->assertSame($customer->id, $sale->customer_id);
    }
}
