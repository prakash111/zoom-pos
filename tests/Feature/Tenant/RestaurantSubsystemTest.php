<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Restaurant\Kds;
use App\Livewire\Tenant\Restaurant\Pos as RestaurantPos;
use App\Livewire\Tenant\Restaurant\Tables as RestaurantTables;
use App\Models\Category;
use App\Models\DiningFloor;
use App\Models\DiningTable;
use App\Models\KitchenTicket;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class RestaurantSubsystemTest extends TestCase
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

    public function test_floor_and_table_management_crud(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        $company->update(['pos_mode' => 'restaurant']);

        $component = Livewire::test(RestaurantTables::class);

        // 1. Add new floor
        $component
            ->set('floorName', 'Rooftop Lounge')
            ->set('floorOrderIndex', 2)
            ->call('saveFloor')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dining_floors', [
            'company_id' => $company->id,
            'name' => 'Rooftop Lounge',
        ]);

        $floor = DiningFloor::where('name', 'Rooftop Lounge')->firstOrFail();

        // 2. Add table to floor
        $component
            ->set('tableNumber', 'Roof-01')
            ->set('tableFloorId', $floor->id)
            ->set('tableCapacity', 6)
            ->set('tableStatus', DiningTable::STATUS_AVAILABLE)
            ->call('saveTable')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dining_tables', [
            'company_id' => $company->id,
            'table_number' => 'Roof-01',
            'seating_capacity' => 6,
        ]);

        $table = DiningTable::where('table_number', 'Roof-01')->firstOrFail();

        // 3. Update table status
        $component->call('setTableStatus', $table->id, DiningTable::STATUS_RESERVED);
        $this->assertSame(DiningTable::STATUS_RESERVED, $table->fresh()->status);

        // 4. Delete table
        $component->call('deleteTable', $table->id);
        $this->assertDatabaseMissing('dining_tables', ['id' => $table->id]);
    }

    public function test_restaurant_pos_places_order_and_generates_kot(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        $company->update(['pos_mode' => 'restaurant']);

        $floor = DiningFloor::create(['company_id' => $company->id, 'name' => 'Indoor']);
        $table = DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $floor->id, 'table_number' => 'Table 10', 'seating_capacity' => 4]);

        $category = Category::create(['company_id' => $company->id, 'name' => 'Burgers']);
        $burger = Product::create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => 'Cheeseburger Combo',
            'sale_price' => 12.00,
            'cost_price' => 5.00,
            'current_stock' => 50,
            'variants' => [
                ['name' => 'Regular', 'price' => 12.00],
                ['name' => 'Double', 'price' => 15.00],
            ],
            'modifiers' => [
                ['name' => 'Extra Cheese', 'price' => 1.50],
            ],
            'active' => true,
        ]);

        $component = Livewire::test(RestaurantPos::class, ['table_id' => $table->id]);

        // 1. Select Table & Customize Item
        $component
            ->call('selectTable', $table->id)
            ->call('openModifierModal', $burger->id)
            ->call('selectVariant', 'Double', 15.00)
            ->call('toggleModifier', 'Extra Cheese', 1.50)
            ->set('itemNote', 'Extra crispy fries')
            ->set('activeSeat', 2)
            ->call('addCustomizedItemToCart');

        $this->assertCount(1, $component->get('items'));
        $this->assertSame(16.50, $component->get('items')[0]['price']);
        $this->assertSame(2, $component->get('items')[0]['seat']);

        // 2. Send to Kitchen (Dispatches KOT and Occupies Table)
        $component->call('sendToKitchen');

        $component->assertSet('showKotSuccessModal', true);
        $this->assertSame(DiningTable::STATUS_OCCUPIED, $table->fresh()->status);
        $this->assertDatabaseHas('kitchen_tickets', [
            'company_id' => $company->id,
            'dining_table_id' => $table->id,
            'status' => 'pending',
        ]);

        // 3. Settle Bill (Clears table to Available, shows the post-settlement dispatch modal)
        $component
            ->set('paymentMethod', 'cash')
            ->call('settleBill')
            ->assertSet('showSettledDispatchModal', true);

        $this->assertSame(DiningTable::STATUS_AVAILABLE, $table->fresh()->status);
    }

    public function test_restaurant_settle_bill_with_customer_records_ledger_entry_for_due_balance(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        $company->update(['pos_mode' => 'restaurant']);

        $floor = DiningFloor::create(['company_id' => $company->id, 'name' => 'Indoor']);
        $table = DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $floor->id, 'table_number' => 'Table 20', 'seating_capacity' => 4]);

        $category = Category::create(['company_id' => $company->id, 'name' => 'Mains']);
        $dish = Product::create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => 'Grilled Chicken',
            'sale_price' => 20.00,
            'cost_price' => 8.00,
            'current_stock' => 50,
            'active' => true,
        ]);

        $customer = \App\Models\Customer::create([
            'company_id' => $company->id,
            'name' => 'Jane Diner',
            'phone' => '5551234567',
            'email' => 'jane@example.com',
        ]);

        $component = Livewire::test(RestaurantPos::class, ['table_id' => $table->id]);

        $component
            ->call('selectTable', $table->id)
            ->call('openModifierModal', $dish->id)
            ->call('addCustomizedItemToCart')
            ->call('sendToKitchen')
            ->call('selectCheckoutCustomer', $customer->id)
            ->call('toggleSplitPayment')
            ->set('splitPayments.0.payment_method', 'cash')
            ->set('splitPayments.0.amount', 5.00)
            ->set('dueDate', now()->addDays(7)->toDateString())
            ->call('settleBill')
            ->assertSet('showSettledDispatchModal', true);

        $sale = Sale::where('company_id', $company->id)->where('status', 'completed')->firstOrFail();

        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertGreaterThan(0, (float) $sale->due_amount);

        // The customer_id fix means SaleObserver -> CustomerLedgerService now
        // records this due-creating restaurant sale, where it previously
        // silently skipped it for lacking a customer_id.
        $this->assertDatabaseHas('customer_ledgers', [
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'type' => 'invoice',
        ]);
        $this->assertEquals((float) $sale->due_amount, (float) $customer->fresh()->due_balance);
    }

    public function test_restaurant_pos_table_transfer(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        $company->update(['pos_mode' => 'restaurant']);

        $floor = DiningFloor::create(['company_id' => $company->id, 'name' => 'Indoor']);
        $table1 = DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $floor->id, 'table_number' => 'Table 01', 'status' => DiningTable::STATUS_AVAILABLE]);
        $table2 = DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $floor->id, 'table_number' => 'Table 02', 'status' => DiningTable::STATUS_AVAILABLE]);

        $component = Livewire::test(RestaurantPos::class);

        $component
            ->call('selectTable', $table1->id)
            ->set('items', [
                ['id' => '123', 'product_id' => 1, 'name' => 'Pizza', 'price' => 15.0, 'quantity' => 1, 'seat' => 1],
            ])
            ->call('sendToKitchen');

        $this->assertSame(DiningTable::STATUS_OCCUPIED, $table1->fresh()->status);

        // Transfer from Table 1 to Table 2
        $component
            ->set('transferTargetTableId', $table2->id)
            ->call('transferTable');

        $this->assertSame(DiningTable::STATUS_AVAILABLE, $table1->fresh()->status);
        $this->assertSame(DiningTable::STATUS_OCCUPIED, $table2->fresh()->status);
    }

    public function test_kds_workflow_transitions(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        $company->update(['pos_mode' => 'restaurant']);

        $kot = KitchenTicket::create([
            'company_id' => $company->id,
            'kot_number' => 'KOT-900',
            'table_name' => 'Table 05',
            'service_type' => 'dine_in',
            'status' => KitchenTicket::STATUS_PENDING,
            'items' => [
                ['name' => 'Steak', 'quantity' => 1, 'variant' => 'Medium Rare'],
            ],
        ]);

        $kds = Livewire::test(Kds::class);

        // Pending -> Preparing
        $kds->call('startPreparing', $kot->id);
        $this->assertSame(KitchenTicket::STATUS_PREPARING, $kot->fresh()->status);
        $this->assertNotNull($kot->fresh()->prepared_at);

        // Preparing -> Ready
        $kds->call('markReady', $kot->id);
        $this->assertSame(KitchenTicket::STATUS_READY, $kot->fresh()->status);
        $this->assertNotNull($kot->fresh()->ready_at);

        // Ready -> Served
        $kds->call('markServed', $kot->id);
        $this->assertSame(KitchenTicket::STATUS_SERVED, $kot->fresh()->status);
        $this->assertNotNull($kot->fresh()->served_at);
    }

    public function test_qr_code_digital_menu_ordering(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $floor = DiningFloor::create(['company_id' => $company->id, 'name' => 'Patio']);
        $table = DiningTable::create([
            'company_id' => $company->id,
            'dining_floor_id' => $floor->id,
            'table_number' => 'Patio-01',
            'qr_token' => 'qr_token_test_12345',
            'status' => DiningTable::STATUS_AVAILABLE,
        ]);

        $pizza = Product::create([
            'company_id' => $company->id,
            'name' => 'Margherita Pizza',
            'sale_price' => 14.00,
            'active' => true,
        ]);

        // 1. Visit digital menu
        $response = $this->get(route('restaurant.table.order', ['token' => $table->qr_token]));
        $response->assertOk();
        $response->assertSee('Patio-01');
        $response->assertSee('Margherita Pizza');

        // 2. Submit order from QR digital menu
        $orderPayload = [
            'guest_name' => 'Sarah Guest',
            'guest_count' => 2,
            'special_instructions' => 'Bring water first please',
            'items' => [
                [
                    'product_id' => $pizza->id,
                    'name' => 'Margherita Pizza',
                    'quantity' => 2,
                    'price' => 14.00,
                    'variant' => 'Large',
                    'modifiers' => [['name' => 'Extra Basil']],
                    'note' => 'Crispy crust',
                ],
            ],
        ];

        $orderResponse = $this->post(route('restaurant.table.order.place', ['token' => $table->qr_token]), $orderPayload);
        $orderResponse->assertRedirect();

        // 3. Assert Sale and KOT were created and table occupied
        $this->assertSame(DiningTable::STATUS_OCCUPIED, $table->fresh()->status);
        $this->assertSame(2, $table->fresh()->guest_count);

        $this->assertDatabaseHas('sales', [
            'company_id' => $company->id,
            'customer_name' => 'Sarah Guest',
            'dining_table_id' => $table->id,
            'service_type' => 'dine_in',
            'total' => 28.00,
        ]);

        $this->assertDatabaseHas('kitchen_tickets', [
            'company_id' => $company->id,
            'dining_table_id' => $table->id,
            'server_name' => 'Table QR Order',
            'status' => 'pending',
            'kitchen_notes' => 'Bring water first please',
        ]);
    }

    public function test_send_to_kitchen_validation_and_feedback(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        $company->update(['pos_mode' => 'restaurant']);

        $floor = DiningFloor::create(['company_id' => $company->id, 'name' => 'Main']);
        $table = DiningTable::create(['company_id' => $company->id, 'dining_floor_id' => $floor->id, 'table_number' => 'T-01']);

        $component = Livewire::test(RestaurantPos::class);

        // 1. Try sending with empty cart
        $component->call('sendToKitchen');
        $component->assertSet('showKotSuccessModal', false);

        // 2. Add item to cart and select table
        $component->call('selectTable', $table->id);
        $component->set('items', [
            ['id' => 'item_1', 'product_id' => 1, 'name' => 'Burger', 'price' => 10.0, 'quantity' => 1, 'seat' => 1],
        ]);

        // 3. Dispatch to kitchen
        $component->call('sendToKitchen');
        $component->assertSet('showKotSuccessModal', true);
        $component->assertSee('DISPATCHED');

        // 4. Dismiss modal & reset order
        $component->call('closeKotModalAndResetOrder');
        $component->assertSet('showKotSuccessModal', false);
        $this->assertEmpty($component->get('items'));
    }
}
