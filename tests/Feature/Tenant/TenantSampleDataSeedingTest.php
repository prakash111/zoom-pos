<?php

namespace Tests\Feature\Tenant;

use App\Jobs\SeedTenantSampleDataJob;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\DiningFloor;
use App\Models\DiningTable;
use App\Models\KitchenTicket;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\ServiceOrder;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\Tenancy\TenantProvisioningService;
use App\Services\Tenancy\TenantSampleDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantSampleDataSeedingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::firstOrCreate(['name' => 'trial'], [
            'display_name' => 'Free Trial',
            'billing_cycle' => 'monthly',
            'duration_days' => 14,
            'price' => 0.00,
            'currency' => 'USD',
            'features' => ['pos' => true, 'offline' => true],
            'limits' => ['products' => 500, 'users' => 5],
            'active' => true,
        ]);
    }

    protected function tokenFor(User $user): string
    {
        return $this->postJson('/api/v1/pos/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertOk()->json('token');
    }

    public function test_registration_with_retail_mode_automatically_seeds_retail_demo_data(): void
    {
        $provisioner = app(TenantProvisioningService::class);

        $result = $provisioner->registerTenant([
            'store_name' => 'Super Retail Mart',
            'slug' => 'super-retail-mart',
            'owner_name' => 'Retail Owner',
            'email' => 'owner@retailmart.test',
            'password' => 'secret123',
            'country' => 'US',
            'currency' => 'USD',
            'pos_mode' => 'retail',
            'sync_seed' => true,
        ]);

        /** @var Company $company */
        $company = $result['company']->fresh();
        /** @var User $admin */
        $admin = $result['user'];

        $this->assertTrue((bool) $company->is_seeding_complete);

        // Categories seeded
        $categories = Category::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->get();
        $this->assertCount(4, $categories);
        $this->assertContains('Beverages', $categories->pluck('name')->all());
        $this->assertContains('Packaged Snacks', $categories->pluck('name')->all());
        $this->assertContains('Electronics & Accessories', $categories->pluck('name')->all());
        $this->assertContains('Household Goods', $categories->pluck('name')->all());

        // Products & inventory seeded
        $products = Product::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->get();
        $this->assertGreaterThanOrEqual(9, $products->count());
        $coldBrew = $products->firstWhere('sku', 'RET-BEV-001');
        $this->assertNotNull($coldBrew);
        $this->assertSame('890103000001', $coldBrew->barcode);
        $this->assertSame(45, (int) $coldBrew->current_stock);
        $this->assertSame('3.50', (string) $coldBrew->sale_price);

        // Tax rule seeded
        $this->assertDatabaseHas('tax_rules', [
            'company_id' => $company->id,
            'is_demo' => true,
        ]);

        // Customers seeded
        $walkIn = Customer::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Walk-in Customer')->first();
        $this->assertNotNull($walkIn);
        $this->assertTrue((bool) $walkIn->is_demo);

        $johnDoe = Customer::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'John Doe')->first();
        $this->assertNotNull($johnDoe);
        $this->assertTrue((bool) $johnDoe->is_demo);
        $this->assertEquals(150.00, (float) $johnDoe->due_balance);

        // Customer Ledger seeded
        $this->assertDatabaseHas('customer_ledgers', [
            'company_id' => $company->id,
            'customer_id' => $johnDoe->id,
            'amount' => 150.00,
            'type' => 'invoice',
            'is_demo' => true,
        ]);

        // Invoices seeded (Paid & Due)
        $paidInvoice = Sale::withoutGlobalScopes()->where('company_id', $company->id)->where('sale_number', 'INV-DEMO-001')->first();
        $this->assertNotNull($paidInvoice);
        $this->assertSame('paid', $paidInvoice->payment_status);
        $this->assertTrue((bool) $paidInvoice->is_demo);

        $dueInvoice = Sale::withoutGlobalScopes()->where('company_id', $company->id)->where('sale_number', 'INV-DEMO-002')->first();
        $this->assertNotNull($dueInvoice);
        $this->assertSame('due', $dueInvoice->payment_status);
        $this->assertEquals(150.00, (float) $dueInvoice->due_amount);
        $this->assertNotNull($dueInvoice->due_date);
        $this->assertTrue((bool) $dueInvoice->is_demo);
    }

    public function test_registration_with_restaurant_mode_automatically_seeds_restaurant_demo_data(): void
    {
        $provisioner = app(TenantProvisioningService::class);

        $result = $provisioner->registerTenant([
            'store_name' => 'Bistro Bella',
            'slug' => 'bistro-bella',
            'owner_name' => 'Chef Mario',
            'email' => 'mario@bistrobella.test',
            'password' => 'secret123',
            'country' => 'US',
            'currency' => 'USD',
            'pos_mode' => 'restaurant',
            'sync_seed' => true,
        ]);

        /** @var Company $company */
        $company = $result['company']->fresh();

        $this->assertTrue((bool) $company->is_seeding_complete);

        // Dining Floors seeded
        $floors = DiningFloor::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->get();
        $this->assertCount(2, $floors);
        $this->assertContains('Main Dining Hall', $floors->pluck('name')->all());
        $this->assertContains('Outdoor Patio', $floors->pluck('name')->all());

        // 6 Dining Tables seeded (T-01 to T-06)
        $tables = DiningTable::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->get();
        $this->assertCount(6, $tables);
        $tableNumbers = $tables->pluck('table_number')->all();
        $this->assertContains('T-01', $tableNumbers);
        $this->assertContains('T-02', $tableNumbers);
        $this->assertContains('T-06', $tableNumbers);

        // Table T-02 is occupied with active order
        $table2 = $tables->firstWhere('table_number', 'T-02');
        $this->assertSame('occupied', $table2->status);
        $this->assertSame(3, (int) $table2->guest_count);
        $this->assertNotNull($table2->current_sale_id);

        // Menu items with prep times seeded
        $menuItems = Product::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->get();
        $this->assertGreaterThanOrEqual(5, $menuItems->count());
        $burger = $menuItems->firstWhere('sku', 'RES-MN-001');
        $this->assertNotNull($burger);
        $this->assertSame(15, (int) $burger->duration_minutes);

        // Active Order & KOT seeded on Table T-02
        $sale = Sale::withoutGlobalScopes()->where('company_id', $company->id)->where('sale_number', 'ORD-TAB-002')->first();
        $this->assertNotNull($sale);
        $this->assertSame('in_progress', $sale->status);
        $this->assertSame('in_kitchen', $sale->kot_status);
        $this->assertTrue((bool) $sale->is_demo);

        $kot = KitchenTicket::withoutGlobalScopes()->where('company_id', $company->id)->where('kot_number', 'KOT-DEMO-001')->first();
        $this->assertNotNull($kot);
        $this->assertSame('sent_to_kitchen', $kot->status);
        $this->assertSame('T-02', $kot->table_name);
        $this->assertSame(15, (int) $kot->prep_minutes);
        $this->assertNotNull($kot->target_completion_at);
        $this->assertTrue((bool) $kot->is_demo);
    }

    public function test_registration_with_pharmacy_mode_automatically_seeds_pharmacy_demo_data(): void
    {
        $provisioner = app(TenantProvisioningService::class);

        $result = $provisioner->registerTenant([
            'store_name' => 'City Care Pharmacy',
            'slug' => 'city-care-pharmacy',
            'owner_name' => 'Dr. Wilson',
            'email' => 'wilson@citycarepharmacy.test',
            'password' => 'secret123',
            'country' => 'US',
            'currency' => 'USD',
            'pos_mode' => 'pharmacy',
            'sync_seed' => true,
        ]);

        /** @var Company $company */
        $company = $result['company']->fresh();

        $this->assertTrue((bool) $company->is_seeding_complete);

        // Medicine categories
        $categories = Category::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->get();
        $this->assertCount(4, $categories);
        $this->assertContains('Antibiotics', $categories->pluck('name')->all());
        $this->assertContains('Pain Relief', $categories->pluck('name')->all());

        // Pharmacy products with batch, expiry & requires_prescription
        $drugs = Product::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->get();
        $this->assertGreaterThanOrEqual(8, $drugs->count());

        $amoxicillin = $drugs->firstWhere('sku', 'PHR-ANT-001');
        $this->assertNotNull($amoxicillin);
        $this->assertSame('BATCH-2026-AMX', $amoxicillin->batch_number);
        $this->assertTrue((bool) $amoxicillin->requires_prescription);
        $this->assertSame('2027-11-10', $amoxicillin->expiry_date?->toDateString());

        $paracetamol = $drugs->firstWhere('sku', 'PHR-PN-001');
        $this->assertNotNull($paracetamol);
        $this->assertFalse((bool) $paracetamol->requires_prescription);

        $ibuprofen = $drugs->firstWhere('sku', 'PHR-PN-002');
        $this->assertNotNull($ibuprofen);
        $this->assertNotNull($ibuprofen->expiry_date);

        // Patient customer
        $patient = Customer::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Jane Smith (Patient)')->first();
        $this->assertNotNull($patient);
        $this->assertTrue((bool) $patient->is_demo);

        // Dispensed Rx sale
        $rxSale = Sale::withoutGlobalScopes()->where('company_id', $company->id)->where('sale_number', 'RX-DEMO-001')->first();
        $this->assertNotNull($rxSale);
        $this->assertSame('paid', $rxSale->payment_status);
        $this->assertStringContainsString('Prescription dispensed', (string) $rxSale->notes);
        $this->assertTrue((bool) $rxSale->is_demo);
    }

    public function test_registration_with_service_booking_mode_automatically_seeds_salon_demo_data(): void
    {
        $provisioner = app(TenantProvisioningService::class);

        $result = $provisioner->registerTenant([
            'store_name' => 'Luxe Salon & Spa',
            'slug' => 'luxe-salon-spa',
            'owner_name' => 'Elena Director',
            'email' => 'elena.owner@luxesalon.test',
            'password' => 'secret123',
            'country' => 'US',
            'currency' => 'USD',
            'pos_mode' => 'service_booking',
            'sync_seed' => true,
        ]);

        /** @var Company $company */
        $company = $result['company']->fresh();

        $this->assertTrue((bool) $company->is_seeding_complete);

        // Service categories
        $categories = Category::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->get();
        $this->assertCount(3, $categories);
        $this->assertContains('Hair & Styling', $categories->pluck('name')->all());
        $this->assertContains('Facials & Skincare', $categories->pluck('name')->all());
        $this->assertContains('Spa & Body Treatments', $categories->pluck('name')->all());

        // Service catalog products with duration_minutes
        $services = Product::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->get();
        $haircut = $services->firstWhere('sku', 'SRV-HAR-001');
        $this->assertNotNull($haircut);
        $this->assertSame(30, (int) $haircut->duration_minutes);
        $this->assertSame('service', $haircut->unit);

        $facial = $services->firstWhere('sku', 'SRV-FCL-001');
        $this->assertNotNull($facial);
        $this->assertSame(60, (int) $facial->duration_minutes);

        // Specialists seeded with shift
        $specialists = User::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_demo', true)
            ->get();
        $this->assertCount(2, $specialists);
        $stylist = $specialists->firstWhere('name', 'Elena Rostova (Master Stylist)');
        $this->assertNotNull($stylist);
        $this->assertStringContainsString('Morning Shift', (string) $stylist->shift);

        // Appointment ServiceOrder & Sale
        $order = ServiceOrder::withoutGlobalScopes()->where('company_id', $company->id)->where('order_number', 'SRV-DEMO-001')->first();
        $this->assertNotNull($order);
        $this->assertSame('Haircut & Styling', $order->equipment_name);
        $this->assertTrue((bool) $order->is_demo);

        $appointmentSale = Sale::withoutGlobalScopes()->where('company_id', $company->id)->where('sale_number', 'SRV-BOOK-001')->first();
        $this->assertNotNull($appointmentSale);
        $this->assertSame('appointment', $appointmentSale->service_type);
        $this->assertTrue((bool) $appointmentSale->is_demo);
    }

    public function test_queued_job_seeds_sample_data_asynchronously(): void
    {
        $company = Company::create([
            'name' => 'Async Cafe',
            'trade_name' => 'Async Cafe',
            'slug' => 'async-cafe',
            'email' => 'async@cafe.test',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'plan_name' => 'trial',
            'is_seeding_complete' => false,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'async.admin@cafe.test',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMINISTRATOR,
        ]);

        $job = new SeedTenantSampleDataJob((string) $company->id, 'restaurant', $admin->id);
        $job->handle(app(TenantSampleDataService::class));

        $this->assertTrue((bool) $company->fresh()->is_seeding_complete);
        $this->assertGreaterThanOrEqual(6, DiningTable::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_bootstrap_guarantees_seeding_complete_flag_and_hydrates_unseeded_company(): void
    {
        $company = Company::create([
            'name' => 'On-Demand Retail Store',
            'trade_name' => 'On-Demand Retail',
            'slug' => 'on-demand-retail',
            'email' => 'ondemand@retail.test',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'plan_name' => 'trial',
            'is_seeding_complete' => false,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'admin@ondemandretail.test',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMINISTRATOR,
        ]);

        $token = $this->tokenFor($admin);

        $response = $this->withToken($token)->getJson('/api/v1/pos/app/bootstrap?locale=en');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tenant.is_seeding_complete', true);

        // Verify company was dynamically seeded
        $this->assertTrue((bool) $company->fresh()->is_seeding_complete);
        $this->assertGreaterThan(0, Product::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->count());
    }

    public function test_one_click_demo_data_purge_clears_only_demo_records_preserving_real_data(): void
    {
        $provisioner = app(TenantProvisioningService::class);

        $result = $provisioner->registerTenant([
            'store_name' => 'Purge Test Retail',
            'slug' => 'purge-test-retail',
            'owner_name' => 'Purge Admin',
            'email' => 'admin@purgetest.test',
            'password' => 'secret123',
            'country' => 'US',
            'currency' => 'USD',
            'pos_mode' => 'retail',
            'sync_seed' => true,
        ]);

        /** @var Company $company */
        $company = $result['company']->fresh();
        /** @var User $admin */
        $admin = $result['user'];

        // Confirm demo data was seeded initially
        $this->assertGreaterThan(0, Product::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->count());
        $this->assertGreaterThan(0, Category::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->count());
        $this->assertGreaterThan(0, Customer::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->count());
        $this->assertGreaterThan(0, Sale::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->count());

        // Create REAL tenant entities (is_demo = false)
        $realCategory = Category::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Real Proprietary Category',
            'active' => true,
            'is_demo' => false,
        ]);

        $realProduct = Product::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'sku' => 'REAL-SKU-999',
            'name' => 'Real Tenant Product',
            'category_id' => $realCategory->id,
            'category_name' => $realCategory->name,
            'cost_price' => 100.00,
            'sale_price' => 250.00,
            'current_stock' => 50,
            'active' => true,
            'is_demo' => false,
        ]);

        $realCustomer = Customer::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Real VIP Client',
            'email' => 'vip@realclient.test',
            'is_demo' => false,
        ]);

        $token = $this->tokenFor($admin);

        // Perform Purge via DELETE /api/tenant/demo-data
        $response = $this->withToken($token)->deleteJson('/api/tenant/demo-data');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Sample demo data cleared successfully.');

        // Demo data is 100% removed
        $this->assertSame(0, Product::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->count());
        $this->assertSame(0, Category::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->count());
        $this->assertSame(0, Customer::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->count());
        $this->assertSame(0, Sale::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->count());
        $this->assertSame(0, CustomerLedger::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->count());

        // REAL data is untouched
        $this->assertDatabaseHas('categories', ['id' => $realCategory->id, 'is_demo' => false]);
        $this->assertDatabaseHas('products', ['id' => $realProduct->id, 'is_demo' => false]);
        $this->assertDatabaseHas('customers', ['id' => $realCustomer->id, 'is_demo' => false]);

        // Administrator account is untouched
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'email' => 'admin@purgetest.test']);

        // Seeding complete flag is reset
        $this->assertFalse((bool) $company->fresh()->is_seeding_complete);
    }

    public function test_purge_endpoint_also_works_via_v1_pos_demo_data(): void
    {
        $provisioner = app(TenantProvisioningService::class);

        $result = $provisioner->registerTenant([
            'store_name' => 'Purge POS Route Test',
            'slug' => 'purge-pos-route-test',
            'owner_name' => 'Purge POS Admin',
            'email' => 'posadmin@purgetest.test',
            'password' => 'secret123',
            'country' => 'US',
            'currency' => 'USD',
            'pos_mode' => 'retail',
            'sync_seed' => true,
        ]);

        $company = $result['company']->fresh();
        $admin = $result['user'];
        $token = $this->tokenFor($admin);

        $response = $this->withToken($token)->deleteJson('/api/v1/pos/demo-data');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, Product::withoutGlobalScopes()->where('company_id', $company->id)->where('is_demo', true)->count());
    }

    public function test_purge_endpoint_is_permission_gated(): void
    {
        $company = Company::create([
            'name' => 'Permission Test Co',
            'trade_name' => 'Permission Test',
            'slug' => 'permission-test-co',
            'email' => 'perm@test.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'plan_name' => 'trial',
        ]);

        $cashier = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'cashier@permtest.test',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_CASHIER,
        ]);

        $token = $this->tokenFor($cashier);

        // Cashier has no permission to edit settings / purge data
        $this->withToken($token)->deleteJson('/api/tenant/demo-data')
            ->assertForbidden();
    }

    public function test_sdui_schema_exposes_demo_data_purge_danger_button(): void
    {
        $company = Company::create([
            'name' => 'SDUI Danger Test',
            'trade_name' => 'SDUI Danger',
            'slug' => 'sdui-danger-test',
            'email' => 'danger@test.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'plan_name' => 'trial',
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'admin@sduidanger.test',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMINISTRATOR,
        ]);

        $token = $this->tokenFor($admin);

        // Check settings-advanced screen
        $resAdvanced = $this->withToken($token)->getJson('/api/tenant/views/settings-advanced');
        $resAdvanced->assertOk()
            ->assertJsonPath('success', true);

        $jsonStr = json_encode($resAdvanced->json(), JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('button_danger', $jsonStr);
        $this->assertStringContainsString('/api/tenant/demo-data', $jsonStr);
        $this->assertStringContainsString('Clear Sample Demo Data', $jsonStr);

        // Check settings-profile screen
        $resProfile = $this->withToken($token)->getJson('/api/tenant/views/settings-profile');
        $resProfile->assertOk()
            ->assertJsonPath('success', true);

        $jsonProfileStr = json_encode($resProfile->json(), JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('button_danger', $jsonProfileStr);
        $this->assertStringContainsString('/api/tenant/demo-data', $jsonProfileStr);
    }
}
