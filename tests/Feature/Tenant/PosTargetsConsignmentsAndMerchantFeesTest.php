<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Consignments;
use App\Livewire\Tenant\Reports;
use App\Livewire\Tenant\Sales;
use App\Livewire\Tenant\SalesTargets;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\Payment\PixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosTargetsConsignmentsAndMerchantFeesTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected Customer $customer;

    protected Product $productA;

    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Demo Supermarket Ltd',
            'slug' => 'demo-supermarket',
            'country' => 'BR',
            'currency' => 'BRL',
            'currency_symbol' => 'R$',
            'currency_decimals' => 2,
            'card_fee_debit' => 1.50,
            'card_fee_credit_1x' => 3.20,
            'pix_key_type' => 'cpf_cnpj',
            'pix_key' => '12.345.678/0001-99',
            'pix_merchant_name' => 'SUPERMARKET DEMO',
            'pix_merchant_city' => 'SAO PAULO',
            'barcode_scale_prefix' => '2',
            'barcode_scale_type' => 'weight',
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'admin',
            'commission_rate' => 5.0,
            'commission_type' => 'percentage',
        ]);

        $this->customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Maria Silva',
            'phone' => '11999998888',
        ]);

        $this->productA = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Macas Gala (Kg)',
            'code' => '00123',
            'barcode' => '200123015000',
            'cost_price' => 5.00,
            'sale_price' => 10.00,
            'current_stock' => 50.0,
            'active' => true,
        ]);

        $this->productB = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Queijo Minas',
            'code' => '00456',
            'barcode' => '7891234567890',
            'cost_price' => 25.00,
            'sale_price' => 40.00,
            'current_stock' => 20.0,
            'active' => true,
        ]);

        CashRegister::create([
            'company_id' => $this->company->id,
            'opened_by' => $this->user->id,
            'opened_at' => now(),
            'status' => 'open',
            'terminal_id' => 'Main POS',
            'opening_balance' => 200.00,
        ]);
    }

    public function test_pix_service_generates_valid_emvco_payload()
    {
        $payload = PixService::generatePayload('12345678900', 'LOJA TESTE', 'BRASILIA', 150.00, 'PED001');

        $this->assertStringContainsString('0014br.gov.bcb.pix', $payload);
        $this->assertStringContainsString('12345678900', $payload);
        $this->assertStringContainsString('LOJA TESTE', $payload);
        $this->assertStringContainsString('BRASILIA', $payload);
        $this->assertStringContainsString('150.00', $payload);
        $this->assertStringContainsString('6304', $payload);
    }

    public function test_scale_barcode_is_parsed_in_pos()
    {
        $this->actingAs($this->user);

        // Barcode: 2 (prefix) + 00123 (product code) + 01500 (1.500 kg) + 0 (check digit)
        Livewire::test(Sales\Create::class)
            ->set('search', '200123015000')
            ->assertCount('items', 1)
            ->assertSet('items.0.product_id', $this->productA->id)
            ->assertSet('items.0.quantity', 1.50);
    }

    public function test_quote_can_be_converted_and_loaded_into_pos()
    {
        $this->actingAs($this->user);

        $quote = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'QT-0001',
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'user_id' => $this->user->id,
            'total' => 80.00,
            'paid_amount' => 0.00,
            'due_amount' => 80.00,
            'discount' => 5.00,
            'payment_method' => 'card_credit',
            'agreed_payment_method' => 'card_credit',
            'payment_status' => 'pending',
            'status' => 'sent',
            'operation_type' => 'quotation',
            'items' => [
                ['product_id' => $this->productB->id, 'name' => 'Queijo Minas', 'quantity' => 2, 'price' => 40.00],
            ],
        ]);

        Livewire::test(Sales\Create::class, ['quote_id' => $quote->id])
            ->assertSet('convertedFromQuoteId', $quote->id)
            ->assertSet('customerId', $this->customer->id)
            ->assertSet('discount', 5.00)
            ->assertSet('paymentMethod', 'card_credit')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('converted', $quote->fresh()->status);

        $sale = Sale::where('operation_type', 'sale')->latest()->first();
        $this->assertNotNull($sale);
        $this->assertEquals(75.00, (float) $sale->total);
        $this->assertEquals(3.20, (float) $sale->merchant_fee_percentage);
        $this->assertEquals(2.40, (float) $sale->merchant_fee_amount);
        $this->assertEquals(72.60, (float) $sale->net_amount);
    }

    public function test_sales_targets_crud_progress_and_split()
    {
        $this->actingAs($this->user);

        Livewire::test(SalesTargets\Index::class)
            ->set('companyTargetAmount', 50000.00)
            ->call('splitEvenly')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sales_targets', [
            'company_id' => $this->company->id,
            'user_id' => null,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'target_amount' => 50000.00,
        ]);

        $this->assertDatabaseHas('sales_targets', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'target_amount' => 50000.00,
        ]);

        $progress = SalesTarget::getProgress($this->company->id, null, (int) now()->year, (int) now()->month);
        $this->assertEquals(50000.00, $progress['target']);
    }

    public function test_consignments_lifecycle_dispatch_reconciliation_and_finalization()
    {
        $this->actingAs($this->user);

        // 1. Create Consignment
        $component = Livewire::test(Consignments\Create::class)
            ->set('customerId', $this->customer->id)
            ->set('items.0.product_id', $this->productB->id)
            ->set('items.0.quantity', 5.0)
            ->set('items.0.unit_price', 40.00)
            ->call('save', 'dispatched');

        $consignment = Consignment::latest()->first();
        $this->assertNotNull($consignment);
        $this->assertEquals('dispatched', $consignment->status);
        $this->assertEquals(200.00, (float) $consignment->total_dispatched_amount);

        // 2. Reconcile Consignment (3 sold, 2 returned)
        $itemId = $consignment->items()->first()->id;

        Livewire::test(Consignments\Show::class, ['consignment' => $consignment])
            ->set("reconciliationInputs.{$itemId}.returned", 2.0)
            ->set("reconciliationInputs.{$itemId}.sold", 3.0)
            ->call('saveReconciliation')
            ->assertHasNoErrors();

        $consignment->refresh();
        $this->assertEquals('reconciled', $consignment->status);
        $this->assertEquals(120.00, (float) $consignment->total_sold_amount);
        $this->assertEquals(80.00, (float) $consignment->total_returned_amount);

        // Initial stock was 20
        $initialStock = (float) $this->productB->fresh()->current_stock;
        $this->assertEquals(20.0, $initialStock);

        // 3. Finalize to Sale
        Livewire::test(Consignments\Show::class, ['consignment' => $consignment])
            ->set('paymentMethod', 'pix')
            ->call('finalizeToSale')
            ->assertHasNoErrors();

        $consignment->refresh();
        $this->assertEquals('finalized', $consignment->status);
        $this->assertNotNull($consignment->sale_id);

        $sale = Sale::find($consignment->sale_id);
        $this->assertEquals(120.00, (float) $sale->total);
        $this->assertEquals('pix', $sale->payment_method);

        // Stock decreased by 3 sold items (20 - 3 = 17)
        $this->assertEquals(17.0, (float) $this->productB->fresh()->current_stock);
    }

    public function test_consignments_reconciliation_math_sold_qty_autocalculation_and_zero_sale_prevention()
    {
        $this->actingAs($this->user);

        // Create a consignment with 2 items @ $7.00 = $14.00
        Livewire::test(Consignments\Create::class)
            ->set('customerId', $this->customer->id)
            ->set('items.0.product_id', $this->productA->id)
            ->set('items.0.quantity', 2.0)
            ->set('items.0.unit_price', 7.00)
            ->call('save', 'dispatched');

        $consignment = Consignment::latest()->first();
        $this->assertNotNull($consignment);
        $itemId = $consignment->items()->first()->id;

        // 1. Initial Mount: Returned Qty = 0 -> Sold Qty auto-calculates to 2, Sold Revenue to $14.00
        $showComponent = Livewire::test(Consignments\Show::class, ['consignment' => $consignment])
            ->assertSet("items.{$itemId}.dispatched_qty", 2.0)
            ->assertSet("items.{$itemId}.returned_qty", 0.0)
            ->assertSet("items.{$itemId}.sold_qty", 2.0)
            ->assertSet("items.{$itemId}.billed_total", 14.00)
            ->assertSee('14.00');

        $this->assertEquals(14.00, $showComponent->get('soldRevenue'));
        $this->assertEquals(0.00, $showComponent->get('returnedAmount'));
        $this->assertEquals(14.00, $showComponent->get('totalDispatchedAmount'));

        // 2. Entering 1 in Returned Qty: Sold Qty becomes 1, Sold Revenue becomes $7.00, Returned Amount becomes $7.00
        $showComponent->set("items.{$itemId}.returned_qty", 1.0)
            ->assertSet("items.{$itemId}.sold_qty", 1.0)
            ->assertSet("items.{$itemId}.billed_total", 7.00);

        $this->assertEquals(7.00, $showComponent->get('soldRevenue'));
        $this->assertEquals(7.00, $showComponent->get('returnedAmount'));

        // 3. Entering 2 in Returned Qty (100% returned): Sold Qty becomes 0, Sold Revenue becomes $0.00
        $showComponent->set("items.{$itemId}.returned_qty", 2.0)
            ->assertSet("items.{$itemId}.sold_qty", 0.0)
            ->assertSet("items.{$itemId}.billed_total", 0.00);

        $this->assertEquals(0.00, $showComponent->get('soldRevenue'));
        $this->assertEquals(14.00, $showComponent->get('returnedAmount'));

        // 4. Attempting to open finalize modal when sold revenue is $0.00 blocks and dispatches warning toast
        $showComponent->call('openFinalizeModal')
            ->assertSet('showFinalizeModal', false)
            ->assertDispatched('toast');

        // 5. Reset Returned Qty to 0 (all 2 sold) and open finalize modal
        $showComponent->set("items.{$itemId}.returned_qty", 0.0)
            ->call('openFinalizeModal')
            ->assertSet('showFinalizeModal', true);

        // 6. Confirm and Generate Invoice with PIX
        $initialStockA = (float) $this->productA->fresh()->current_stock; // 50.0

        $showComponent->set('paymentMethod', 'pix')
            ->call('confirmAndGenerateInvoice')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $consignment->refresh();
        $this->assertEquals('finalized', $consignment->status);
        $this->assertEquals(14.00, (float) $consignment->total_sold_amount);
        $this->assertEquals(0.00, (float) $consignment->total_returned_amount);
        $this->assertNotNull($consignment->sale_id);

        $sale = Sale::find($consignment->sale_id);
        $this->assertEquals(14.00, (float) $sale->total);
        $this->assertEquals('pix', $sale->payment_method);
        $this->assertCount(1, $sale->items);
        $this->assertEquals(2.0, (float) $sale->items[0]['quantity']);
        $this->assertEquals(7.00, (float) $sale->items[0]['price']);
        $this->assertEquals(14.00, (float) $sale->items[0]['total']);

        // Stock decreased by 2 (50 - 2 = 48)
        $this->assertEquals($initialStockA - 2.0, (float) $this->productA->fresh()->current_stock);
    }

    public function test_income_statement_dre_calculation()
    {
        $this->actingAs($this->user);

        // Create Sale with COGS and Card Fee
        Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'S-DRE001',
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'user_id' => $this->user->id,
            'total' => 100.00,
            'paid_amount' => 100.00,
            'due_amount' => 0.00,
            'discount' => 10.00,
            'tax_amount' => 5.00,
            'merchant_fee_amount' => 3.00,
            'commission_amount' => 5.00,
            'payment_method' => 'card_credit',
            'payment_status' => 'paid',
            'status' => 'completed',
            'operation_type' => 'sale',
            'items' => [
                ['product_id' => $this->productB->id, 'name' => 'Queijo Minas', 'quantity' => 2, 'price' => 50.00], // COGS = 2 * 25 = 50.00
            ],
        ]);

        $component = Livewire::test(Reports\Index::class, ['tab' => 'dre'])
            ->set('datePreset', 'today');

        $dre = $component->get('dreStatement');

        $this->assertEquals(110.00, (float) $dre['gross_revenue']); // Total (100) + Discount (10)
        $this->assertEquals(10.00, (float) $dre['discounts']);
        $this->assertEquals(5.00, (float) $dre['taxes']);
        $this->assertEquals(95.00, (float) $dre['net_revenue']); // 110 - 10 - 5 = 95
        $this->assertEquals(50.00, (float) $dre['cogs']); // 2 * 25
        $this->assertEquals(45.00, (float) $dre['gross_profit']); // 95 - 50 = 45
        $this->assertEquals(3.00, (float) $dre['card_fees']);
        $this->assertEquals(5.00, (float) $dre['commissions']);
        $this->assertEquals(8.00, (float) $dre['operating_expenses']); // 3 + 5
        $this->assertEquals(37.00, (float) $dre['ebitda']); // 45 - 8 = 37
    }

    public function test_tenant_local_backup_download()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('tenant.settings.backup.download'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $this->assertStringContainsString('backup_demo-supermarket_', $response->headers->get('content-disposition'));
    }
}
