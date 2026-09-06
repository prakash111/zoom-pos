<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\ServiceOrders\Index as ServiceOrdersIndex;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Reminder;
use App\Models\Sale;
use App\Models\ServiceOrder;
use App\Models\TenantApiKey;
use App\Models\User;
use App\Services\Navigation\TenantNavRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $user;
    protected Customer $customer;
    protected TenantApiKey $apiKey;

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

        $this->apiKey = TenantApiKey::create([
            'company_id' => $this->company->id,
            'name' => 'Tech Repairs Key',
            'token' => 'zk_live_' . bin2hex(random_bytes(16)),
            'permissions' => ['*'],
            'active' => true,
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

    public function test_can_create_service_order_with_uuid_customer_id_via_livewire(): void
    {
        $uuidCustomer = Customer::create([
            'company_id' => $this->company->id,
            'external_id' => '8cdf07bb-39de-4bab-be79-e6f3b40900ca',
            'name' => 'John Doe UUID',
            'phone' => '555-9876',
            'email' => 'john@uuid.test',
        ]);

        Livewire::actingAs($this->user)
            ->test(ServiceOrdersIndex::class)
            ->call('openCreateModal')
            ->call('selectCustomer', '8cdf07bb-39de-4bab-be79-e6f3b40900ca')
            ->set('equipmentName', 'Samsung TV 55')
            ->set('reportedDefect', 'No power display')
            ->call('save')
            ->assertHasNoErrors();

        $order = ServiceOrder::where('company_id', $this->company->id)->where('customer_id', '8cdf07bb-39de-4bab-be79-e6f3b40900ca')->first();
        $this->assertNotNull($order);
        $this->assertEquals('8cdf07bb-39de-4bab-be79-e6f3b40900ca', $order->customer_id);
        $this->assertEquals('John Doe UUID', $order->customer_name);
        $this->assertNotNull($order->customer_record);
        $this->assertEquals($uuidCustomer->id, $order->customer_record->id);
    }

    public function test_can_create_service_order_with_uuid_customer_id_via_api(): void
    {
        $payload = [
            'customer_id' => '8cdf07bb-39de-4bab-be79-e6f3b40900ca',
            'customer_name' => 'Jane UUID',
            'customer_phone' => '555-4321',
            'customer_email' => 'jane@uuid.test',
            'equipment_name' => 'Air Conditioner 1.5 Ton',
            'brand_model' => 'Daikin Inverter',
            'reported_defect' => 'Cooling coil leak',
            'status' => 'received',
            'priority' => 'high',
        ];

        $response = $this->withToken($this->apiKey->token)
            ->postJson('/api/v1/pos/service-orders', $payload);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('service_orders', [
            'company_id' => $this->company->id,
            'customer_id' => '8cdf07bb-39de-4bab-be79-e6f3b40900ca',
            'equipment_name' => 'Air Conditioner 1.5 Ton',
        ]);
    }

    public function test_parts_index_api_filters_out_salon_services(): void
    {
        $salonCat = Category::create([
            'company_id' => $this->company->id,
            'name' => 'Salon Services',
            'type' => 'salon',
        ]);

        $repairCat = Category::create([
            'company_id' => $this->company->id,
            'name' => 'Display Assemblies',
            'type' => 'repair',
        ]);

        Product::create([
            'company_id' => $this->company->id,
            'name' => 'Beard Trim & Hot Towel',
            'sale_price' => 15.00,
            'unit' => 'service',
            'duration_minutes' => 30,
            'category_id' => $salonCat->id,
            'active' => true,
        ]);

        Product::create([
            'company_id' => $this->company->id,
            'name' => 'OLED Screen Assembly',
            'sale_price' => 95.00,
            'unit' => 'pcs',
            'category_id' => $repairCat->id,
            'active' => true,
        ]);

        $response = $this->withToken($this->apiKey->token)
            ->getJson('/api/v1/pos/service-orders/parts');

        $response->assertOk()->assertJson(['success' => true]);
        $partNames = collect($response->json('parts'))->pluck('name')->all();

        $this->assertContains('OLED Screen Assembly', $partNames);
        $this->assertNotContains('Beard Trim & Hot Towel', $partNames);
    }

    public function test_pos_inventory_parts_only_flag_excludes_salon_services(): void
    {
        $salonCat = Category::create([
            'company_id' => $this->company->id,
            'name' => 'Hair Care',
            'type' => 'salon',
        ]);

        $repairCat = Category::create([
            'company_id' => $this->company->id,
            'name' => 'AC Spare Parts',
            'type' => 'repair',
        ]);

        Product::create([
            'company_id' => $this->company->id,
            'name' => 'Hair Spa Treatment',
            'sale_price' => 45.00,
            'unit' => 'service',
            'duration_minutes' => 45,
            'category_id' => $salonCat->id,
            'active' => true,
        ]);

        Product::create([
            'company_id' => $this->company->id,
            'name' => 'Capacitor 45uF',
            'sale_price' => 12.00,
            'unit' => 'pcs',
            'category_id' => $repairCat->id,
            'active' => true,
        ]);

        $response = $this->withToken($this->apiKey->token)
            ->getJson('/api/v1/pos/inventory?parts_only=1');

        $response->assertOk();
        $items = collect($response->json('products') ?? $response->json('data') ?? []);
        $names = $items->pluck('name')->all();

        $this->assertContains('Capacitor 45uF', $names);
        $this->assertNotContains('Hair Spa Treatment', $names);
    }

    public function test_livewire_part_search_excludes_salon_services(): void
    {
        $salonCat = Category::create([
            'company_id' => $this->company->id,
            'name' => 'Barber',
            'type' => 'salon',
        ]);

        Product::create([
            'company_id' => $this->company->id,
            'name' => 'Shave & Facial Deluxe',
            'sale_price' => 25.00,
            'unit' => 'service',
            'duration_minutes' => 30,
            'category_id' => $salonCat->id,
            'active' => true,
        ]);

        Product::create([
            'company_id' => $this->company->id,
            'name' => 'Facial Shield Part',
            'sale_price' => 8.00,
            'unit' => 'pcs',
            'active' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(ServiceOrdersIndex::class)
            ->call('openCreateModal')
            ->set('partSearch', 'Facial')
            ->assertDontSee('Shave & Facial Deluxe')
            ->assertSee('Facial Shield Part');
    }

    public function test_tenant_navigation_labels_can_be_updated_and_alters_nav_tree(): void
    {
        $this->company->update([
            'licensed_modules' => ['repair_technician', 'retail'],
        ]);

        $response = $this->withToken($this->apiKey->token)
            ->postJson('/api/tenant/settings/navigation-labels', [
                'labels' => [
                    'repair_workbench' => 'HVAC / Service Desk',
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'navigation_labels' => [
                    'repair_workbench' => 'HVAC / Service Desk',
                ],
            ]);

        $this->company->refresh();
        $this->assertEquals(['repair_workbench' => 'HVAC / Service Desk'], $this->company->navigation_labels);

        $getRes = $this->withToken($this->apiKey->token)
            ->getJson('/api/tenant/settings/navigation-labels');
        $getRes->assertOk()
            ->assertJsonPath('navigation_labels.repair_workbench', 'HVAC / Service Desk');

        // Test TenantNavRegistry applies this label
        $sections = TenantNavRegistry::getEffectiveNavForTenant($this->company);
        $found = false;
        foreach ($sections as $section) {
            foreach ($section['items'] ?? [] as $item) {
                if (in_array(($item['key'] ?? ''), ['repair_workbench', 'repair_dashboard'], true)) {
                    $this->assertEquals('HVAC / Service Desk', $item['title']);
                    $found = true;
                }
            }
        }
        $this->assertTrue($found, 'Expected repair_workbench/repair_dashboard to have custom title applied');
    }

    public function test_customer_custom_fields_persisted_via_pos_api(): void
    {
        $response = $this->withToken($this->apiKey->token)
            ->postJson('/api/v1/pos/customers', [
                'name' => 'Custom Field Customer',
                'phone' => '9876543210',
                'email' => 'custom@test.com',
                'custom_fields' => [
                    'GSTIN' => '27AAPFU0939F1ZV',
                    'Alternate Phone' => '9876500000',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('customer.custom_fields.GSTIN', '27AAPFU0939F1ZV');

        $customer = Customer::where('name', 'Custom Field Customer')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('27AAPFU0939F1ZV', $customer->custom_fields['GSTIN'] ?? null);

        // Fetch index and verify custom_fields returned
        $indexRes = $this->withToken($this->apiKey->token)
            ->getJson('/api/v1/pos/customers');
        $indexRes->assertOk();
        $matched = collect($indexRes->json('customers'))->firstWhere('server_id', $customer->id);
        $this->assertNotNull($matched);
        $this->assertEquals('27AAPFU0939F1ZV', $matched['custom_fields']['GSTIN'] ?? null);
    }

    public function test_service_order_custom_specifications_and_tax_calculation(): void
    {
        $part = Product::create([
            'company_id' => $this->company->id,
            'name' => 'AC Capacitor',
            'sale_price' => 50.00,
            'active' => true,
        ]);

        $response = $this->withToken($this->apiKey->token)
            ->postJson('/api/v1/pos/service-orders', [
                'customer_id' => (string) $this->customer->id,
                'customer_name' => $this->customer->name,
                'equipment_name' => 'Split AC Outdoor Unit',
                'reported_defect' => 'Fan not spinning',
                'parts_used' => [
                    [
                        'product_id' => $part->id,
                        'name' => $part->name,
                        'quantity' => 1,
                        'unit_price' => 50.00,
                    ],
                ],
                'labor_cost' => 50.00,
                'discount' => 0.00,
                'extra_attributes' => [
                    'IMEI / Serial' => 'AC-998877',
                    'Gas Level' => '40 PSI',
                ],
            ]);

        $response->assertSuccessful()
            ->assertJsonPath('service_order.extra_attributes.Gas Level', '40 PSI');

        $order = ServiceOrder::where('company_id', $this->company->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals('40 PSI', $order->extra_attributes['Gas Level'] ?? null);
        $this->assertGreaterThan(0, (float) $order->total_amount);
        $this->assertArrayHasKey('tax_amount', $response->json('service_order'));
    }

    public function test_product_soft_delete_and_exclusion_from_pos_sync(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Deletable Gadget',
            'sale_price' => 29.99,
            'active' => true,
        ]);

        $deleteRes = $this->withToken($this->apiKey->token)
            ->deleteJson("/api/v1/pos/inventory/product/{$product->id}");

        $deleteRes->assertOk()
            ->assertJson(['success' => true]);

        $product->refresh();
        $this->assertNotNull($product->deleted_at);
        $this->assertFalse($product->active);

        // Catalog sync must not include soft-deleted product
        $syncRes = $this->withToken($this->apiKey->token)
            ->getJson('/api/v1/pos/sync-catalog');
        $syncRes->assertOk();
        $catalogProductIds = collect($syncRes->json('products'))->pluck('server_id')->all();
        $this->assertNotContains($product->id, $catalogProductIds);
    }

    public function test_delivery_settlement_creates_sale_reminder_and_post_sale_sheet(): void
    {
        $order = ServiceOrder::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'equipment_name' => 'Laptop',
            'reported_defect' => 'Screen flicker',
            'labor_cost' => 80.00,
            'total_amount' => 80.00,
            'status' => 'ready_for_pickup',
            'priority' => 'normal',
        ]);

        $response = $this->withToken($this->apiKey->token)
            ->postJson("/api/v1/pos/service-orders/{$order->id}/status", [
                'status' => 'delivered_settled',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('service_order.status', 'delivered_settled')
            ->assertJsonPath('show_post_sale_sheet', true);

        $order->refresh();
        $this->assertNotNull($order->sale_id);

        $sale = Sale::find($order->sale_id);
        $this->assertNotNull($sale);
        $this->assertEquals(80.00, (float) $sale->total_amount);

        // Follow-up reminder scheduled
        $reminder = Reminder::where('company_id', $this->company->id)
            ->where('customer_id', $this->customer->id)
            ->first();
        $this->assertNotNull($reminder);
        $this->assertEquals('warranty_follow_up', $reminder->type);
        $this->assertNotNull($reminder->due_date);
    }
}
