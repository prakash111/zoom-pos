<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Quotes\Index as QuotesIndex;
use App\Livewire\Tenant\Quotes\Show as QuotesShow;
use App\Livewire\Tenant\Sales\Create as PosCreate;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GranularActionPermissionsAndConsignmentTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $adminUser;
    protected User $cashierUser;
    protected Customer $customer;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Store POS Test',
            'email' => 'store@pos.test',
            'subscription_status' => 'active',
            'pos_mode' => 'general',
            'enable_consignments' => true,
        ]);

        $this->adminUser = User::create([
            'company_id' => $this->company->id,
            'name' => 'Admin Boss',
            'email' => 'boss@pos.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->cashierUser = User::create([
            'company_id' => $this->company->id,
            'name' => 'Cashier Staff',
            'email' => 'cashier@pos.test',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'permissions' => [
                'pos' => ['view' => true, 'create' => true],
                'sales' => ['view' => true, 'create' => true],
                'quotes' => ['view' => true, 'create' => true, 'edit' => true, 'convert_to_sale' => false],
                'consignments' => ['view' => true, 'create' => true],
            ],
        ]);

        CashRegister::create([
            'company_id' => $this->company->id,
            'opened_by' => $this->adminUser->id,
            'opened_at' => now(),
            'status' => 'open',
            'terminal_id' => 'Main POS',
            'opening_balance' => 200.00,
        ]);

        $this->customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Partner Boutique',
            'phone' => '555-9876',
        ]);

        $this->product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Consignment Dress',
            'sku' => 'DRESS-001',
            'sale_price' => 200.00,
            'cost_price' => 100.00,
            'current_stock' => 20,
        ]);
    }

    public function test_quotes_convert_to_sale_permission_is_strictly_enforced(): void
    {
        $quote = Sale::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'sale_number' => 'QUO-2026-001',
            'operation_type' => 'quotation',
            'subtotal' => 200.00,
            'tax_amount' => 0.00,
            'total' => 200.00,
            'status' => 'approved',
            'items' => [
                ['id' => $this->product->id, 'name' => $this->product->name, 'price' => 200.00, 'quantity' => 1, 'total' => 200.00],
            ],
        ]);

        // Cashier without quotes.convert_to_sale must be aborted with 403
        Livewire::actingAs($this->cashierUser)
            ->test(QuotesIndex::class)
            ->call('convertToSale', $quote->id)
            ->assertForbidden();

        Livewire::actingAs($this->cashierUser)
            ->test(QuotesShow::class, ['quote' => $quote])
            ->call('convertToSale')
            ->assertForbidden();

        // Admin with convert_to_sale permission can successfully convert
        Livewire::actingAs($this->adminUser)
            ->test(QuotesIndex::class)
            ->call('convertToSale', $quote->id)
            ->assertRedirect();

        $quote->refresh();
        $this->assertEquals('converted', $quote->status);
    }

    public function test_pos_checkout_with_consignment_payment_creates_consignment_order(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(PosCreate::class)
            ->set('customerId', $this->customer->id)
            ->call('addProductToCart', $this->product->id)
            ->set('paymentMethod', 'consignment')
            ->call('save')
            ->assertHasNoErrors();

        // Verify stock deducted
        $this->product->refresh();
        $this->assertEquals(19, (float) $this->product->current_stock);

        // Verify Consignment record was created for customer
        $consignment = Consignment::where('company_id', $this->company->id)
            ->where('customer_id', $this->customer->id)
            ->first();

        $this->assertNotNull($consignment);
        $this->assertEquals(200.00, (float) $consignment->total_dispatched_amount);
    }
}
