<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\ServiceOrders\Index as ServiceOrdersIndex;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $user;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Tech Repairs Inc',
            'email' => 'support@techrepairs.test',
            'subscription_status' => 'active',
            'pos_mode' => 'general',
            'currency_symbol' => '$',
        ]);

        $this->user = User::create([
            'company_id' => $this->company->id,
            'name' => 'Lead Technician',
            'email' => 'tech@techrepairs.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Alice Customer',
            'phone' => '555-1234',
            'email' => 'alice@test.com',
        ]);
    }

    public function test_service_orders_index_renders_for_authorized_user(): void
    {
        Livewire::actingAs($this->user)
            ->test(ServiceOrdersIndex::class)
            ->assertStatus(200)
            ->assertSee('Service Orders & Warranty Repairs');
    }

    public function test_can_create_service_order_with_equipment_and_parts(): void
    {
        $partProduct = Product::create([
            'company_id' => $this->company->id,
            'name' => 'iPhone 13 Display Module',
            'sku' => 'PART-IP13-DISP',
            'sale_price' => 120.00,
            'cost_price' => 50.00,
            'current_stock' => 10,
            'track_stock' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(ServiceOrdersIndex::class)
            ->call('openCreateModal')
            ->set('customerId', $this->customer->id)
            ->set('customerName', $this->customer->name)
            ->set('customerPhone', $this->customer->phone)
            ->set('equipmentName', 'iPhone 13 Pro')
            ->set('brandModel', 'Apple A2638')
            ->set('serialNumber', '354829104829103')
            ->set('reportedDefect', 'Cracked glass and touch glitching')
            ->call('addPart', $partProduct->id)
            ->set('laborCost', 60.00)
            ->set('discount', 10.00)
            ->call('save')
            ->assertHasNoErrors();

        $order = ServiceOrder::where('company_id', $this->company->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals('iPhone 13 Pro', $order->equipment_name);
        $this->assertEquals('354829104829103', $order->serial_number);
        $this->assertEquals(120.00, (float) $order->parts_total);
        $this->assertEquals(60.00, (float) $order->labor_cost);
        $this->assertEquals(10.00, (float) $order->discount);
        $this->assertEquals(170.00, (float) $order->total_amount);

        // Verify parts inventory decrement
        $partProduct->refresh();
        $this->assertEquals(9, (float) $partProduct->current_stock);
    }

    public function test_can_transition_service_order_status_lifecycle(): void
    {
        $order = ServiceOrder::create([
            'company_id' => $this->company->id,
            'order_number' => 'OS-1001',
            'customer_name' => 'Alice Customer',
            'equipment_name' => 'MacBook Pro',
            'reported_defect' => 'Battery draining fast',
            'status' => ServiceOrder::STATUS_RECEIVED,
            'total_amount' => 150.00,
        ]);

        Livewire::actingAs($this->user)
            ->test(ServiceOrdersIndex::class)
            ->call('updateOrderStatus', $order->id, ServiceOrder::STATUS_UNDER_DIAGNOSIS);

        $order->refresh();
        $this->assertEquals(ServiceOrder::STATUS_UNDER_DIAGNOSIS, $order->status);

        Livewire::actingAs($this->user)
            ->test(ServiceOrdersIndex::class)
            ->call('updateOrderStatus', $order->id, ServiceOrder::STATUS_READY_FOR_PICKUP);

        $order->refresh();
        $this->assertEquals(ServiceOrder::STATUS_READY_FOR_PICKUP, $order->status);
        $this->assertNotNull($order->completed_at);
    }

    public function test_can_search_part_products_by_name_code_or_barcode(): void
    {
        Product::create([
            'company_id' => $this->company->id,
            'name' => 'Apple iPhone 13 Screen',
            'code' => 'APL-SCR-13',
            'barcode' => '9988776655',
            'sale_price' => 120.00,
            'cost_price' => 50.00,
            'current_stock' => 10,
        ]);

        Livewire::actingAs($this->user)
            ->test(ServiceOrdersIndex::class)
            ->call('openCreateModal')
            ->set('partSearch', 'apple')
            ->assertSee('Apple iPhone 13 Screen')
            ->set('partSearch', 'APL-SCR')
            ->assertSee('Apple iPhone 13 Screen')
            ->set('partSearch', '998877')
            ->assertSee('Apple iPhone 13 Screen');
    }

    public function test_can_update_discount_and_labor_cost_live_with_empty_strings_and_numbers(): void
    {
        Livewire::actingAs($this->user)
            ->test(ServiceOrdersIndex::class)
            ->call('openCreateModal')
            ->set('laborCost', 100.00)
            ->assertSet('totalAmount', 100.00)
            ->set('discount', 25.00)
            ->assertSet('totalAmount', 75.00)
            ->set('discount', '')
            ->assertSet('totalAmount', 100.00)
            ->set('laborCost', '')
            ->assertSet('totalAmount', 0.00)
            ->set('laborCost', '50')
            ->set('discount', '10')
            ->assertSet('totalAmount', 40.00);
    }
}
