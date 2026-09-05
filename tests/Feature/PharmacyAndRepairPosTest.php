<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\PharmacyBatch;
use App\Models\PharmacyPrescription;
use App\Models\Plan;
use App\Models\Product;
use App\Models\RepairChecklist;
use App\Models\RepairDeviceCategory;
use App\Models\RepairTicket;
use App\Models\RepairTicketPart;
use App\Models\Sale;
use App\Models\User;
use App\Services\Sdui\SchemaValidator;
use App\Services\Tenancy\TenantSampleDataService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PharmacyAndRepairPosTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $admin;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'enterprise',
            'display_name' => 'Enterprise Plan',
            'price' => 99.00,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'features' => ['pos' => true, 'offline' => true, 'inventory' => true],
            'limits' => ['products' => 5000, 'users' => 20],
            'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Care & Repair Solutions',
            'trade_name' => 'Apex Healthcare & Tech',
            'slug' => 'apex-health-tech',
            'email' => 'admin@apexhealthtech.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'plan_name' => 'enterprise',
            'expires_at' => now()->addDays(30),
            'licensed_modules' => ['retail', 'pharmacy', 'repair_technician'],
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'admin@apexhealthtech.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $loginResponse = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@apexhealthtech.com',
            'password' => 'secret123',
        ]);

        $this->token = $loginResponse->json('token');
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_pharmacy_search_and_fefo_sorting(): void
    {
        $medCategory = Category::create([
            'company_id' => $this->company->id,
            'name' => 'Antibiotics',
            'slug' => 'antibiotics',
            'active' => true,
        ]);

        $product = Product::create([
            'company_id' => $this->company->id,
            'category_id' => $medCategory->id,
            'name' => 'Amoxicillin 500mg Trihydrate',
            'generic_name' => 'Amoxicillin',
            'composition' => '500mg Capsules',
            'code' => 'MED-AMX-500',
            'barcode' => '89010001',
            'sale_price' => 12.50,
            'cost_price' => 6.00,
            'current_stock' => 150,
            'requires_prescription' => true,
            'active' => true,
        ]);

        // Batch 1: Expiring in 60 days
        $batchLater = PharmacyBatch::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'batch_number' => 'AMX-2026-B',
            'manufacturing_date' => Carbon::now()->subMonths(2),
            'expiry_date' => Carbon::now()->addDays(60),
            'cost_price' => 6.00,
            'selling_price' => 12.50,
            'stock_qty' => 50,
            'is_active' => true,
        ]);

        // Batch 2: Expiring in 15 days (Earlier expiry -> FEFO should pick this first)
        $batchEarlier = PharmacyBatch::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'batch_number' => 'AMX-2026-A',
            'manufacturing_date' => Carbon::now()->subMonths(5),
            'expiry_date' => Carbon::now()->addDays(15),
            'cost_price' => 5.50,
            'selling_price' => 12.50,
            'stock_qty' => 30,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/pharmacy/search?query=Amoxicillin');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 1);

        $productData = $response->json('products.0');
        $this->assertEquals($product->id, $productData['id']);
        $this->assertEquals($batchEarlier->id, $productData['fefo_recommended_batch_id']);
        $this->assertCount(2, $productData['batches']);
        $this->assertEquals($batchEarlier->id, $productData['batches'][0]['id']);
        $this->assertEquals($batchLater->id, $productData['batches'][1]['id']);
    }

    public function test_pharmacy_checkout_stock_deduction_and_rx_validation(): void
    {
        $otcProduct = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Paracetamol 500mg',
            'generic_name' => 'Paracetamol',
            'code' => 'MED-PCM-500',
            'sale_price' => 4.00,
            'cost_price' => 1.50,
            'current_stock' => 100,
            'requires_prescription' => false,
            'active' => true,
        ]);

        $otcBatch = PharmacyBatch::create([
            'company_id' => $this->company->id,
            'product_id' => $otcProduct->id,
            'batch_number' => 'PCM-101',
            'expiry_date' => Carbon::now()->addMonths(12),
            'cost_price' => 1.50,
            'selling_price' => 4.00,
            'stock_qty' => 60,
            'is_active' => true,
        ]);

        $rxProduct = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Azithromycin 500mg',
            'generic_name' => 'Azithromycin',
            'code' => 'MED-AZI-500',
            'sale_price' => 18.00,
            'cost_price' => 9.00,
            'current_stock' => 40,
            'requires_prescription' => true,
            'active' => true,
        ]);

        $rxBatch = PharmacyBatch::create([
            'company_id' => $this->company->id,
            'product_id' => $rxProduct->id,
            'batch_number' => 'AZI-99',
            'expiry_date' => Carbon::now()->addMonths(6),
            'cost_price' => 9.00,
            'selling_price' => 18.00,
            'stock_qty' => 20,
            'is_active' => true,
        ]);

        // 1. OTC Checkout succeeds without prescription
        $otcPayload = [
            'items' => [
                [
                    'product_id' => $otcProduct->id,
                    'batch_id' => $otcBatch->id,
                    'quantity' => 5,
                    'unit_price' => 4.00,
                ],
            ],
            'payment_method' => 'cash',
            'payments' => [['method' => 'cash', 'amount' => 20.00]],
            'total' => 20.00,
        ];

        $checkoutResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/tenant/pharmacy/checkout', $otcPayload);

        $checkoutResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals(55, $otcBatch->fresh()->stock_qty);
        $this->assertEquals(95, $otcProduct->fresh()->current_stock);

        // 2. Controlled medicine checkout FAILS when no Rx info is provided
        $rxFailPayload = [
            'items' => [
                [
                    'product_id' => $rxProduct->id,
                    'batch_id' => $rxBatch->id,
                    'quantity' => 2,
                    'unit_price' => 18.00,
                ],
            ],
            'payment_method' => 'cash',
        ];

        $failResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/tenant/pharmacy/checkout', $rxFailPayload);

        $failResponse->assertStatus(422)
            ->assertJsonPath('success', false);

        // 3. Controlled medicine checkout SUCCEEDS when prescription details are provided
        $rxSuccessPayload = [
            'items' => [
                [
                    'product_id' => $rxProduct->id,
                    'batch_id' => $rxBatch->id,
                    'quantity' => 2,
                    'unit_price' => 18.00,
                ],
            ],
            'prescription_details' => [
                'patient_name' => 'John Doe',
                'patient_phone' => '+15550199',
                'doctor_name' => 'Dr. Gregory House',
                'doctor_registration_no' => 'MED-REG-8821',
            ],
            'payment_method' => 'card',
            'payments' => [['method' => 'card', 'amount' => 36.00]],
            'total' => 36.00,
        ];

        $rxSuccessResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/tenant/pharmacy/checkout', $rxSuccessPayload);

        $rxSuccessResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals(18, $rxBatch->fresh()->stock_qty);
        $this->assertEquals(38, $rxProduct->fresh()->current_stock);
        $this->assertDatabaseHas('sales', [
            'company_id' => $this->company->id,
            'total' => 36.00,
        ]);
    }

    public function test_pharmacy_batch_crud_adjustment_and_vendor_return(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Ibuprofen 400mg',
            'code' => 'MED-IBU-400',
            'sale_price' => 6.00,
            'cost_price' => 2.50,
            'current_stock' => 0,
            'active' => true,
        ]);

        // 1. Create Batch
        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/tenant/pharmacy/batches', [
                'product_id' => $product->id,
                'batch_number' => 'IBU-B101',
                'manufacturing_date' => Carbon::now()->subMonth()->format('Y-m-d'),
                'expiry_date' => Carbon::now()->addYear()->format('Y-m-d'),
                'cost_price' => 2.50,
                'selling_price' => 6.00,
                'stock_qty' => 50,
            ]);

        $createResponse->assertOk()
            ->assertJsonPath('success', true);

        $batchId = $createResponse->json('batch.id');
        $this->assertEquals(50, $product->fresh()->current_stock);

        // 2. Adjust Batch
        $adjustResponse = $this->withHeaders($this->authHeaders())
            ->postJson("/api/tenant/pharmacy/batches/{$batchId}/adjust", [
                'new_stock' => 45,
                'reason' => 'Inventory physical audit loss',
            ]);

        $adjustResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('batch.stock_qty', 45);

        $this->assertEquals(45, $product->fresh()->current_stock);

        // 3. Vendor Return
        $returnResponse = $this->withHeaders($this->authHeaders())
            ->postJson("/api/tenant/pharmacy/batches/{$batchId}/return", [
                'quantity' => 10,
                'reason' => 'Damaged packaging return to supplier',
            ]);

        $returnResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('batch.stock_qty', 35);

        $this->assertEquals(35, $product->fresh()->current_stock);
    }

    public function test_pharmacy_prescriptions_queue_and_dispense(): void
    {
        // 1. Create Prescription in Queue
        $createRx = $this->withHeaders($this->authHeaders())
            ->postJson('/api/tenant/pharmacy/prescriptions', [
                'patient_name' => 'Jane Smith',
                'patient_phone' => '+15550244',
                'doctor_name' => 'Dr. Robert Chase',
                'doctor_registration_no' => 'DOC-9912',
                'prescription_date' => Carbon::now()->format('Y-m-d'),
                'diagnosis' => 'Acute Bronchitis',
                'medicines' => [
                    ['name' => 'Azithromycin 500mg', 'dosage' => 'Once daily for 3 days', 'qty' => 3],
                ],
                'notes' => 'Patient allergic to Penicillin',
            ]);

        $createRx->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('prescription.status', 'pending');

        $rxId = $createRx->json('prescription.id');

        // 2. List Prescriptions
        $listResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/pharmacy/prescriptions?status=pending');

        $listResponse->assertOk()
            ->assertJsonPath('success', true);
        $this->assertNotEmpty($listResponse->json('prescriptions'));

        // 3. Dispense Prescription
        $dispenseResponse = $this->withHeaders($this->authHeaders())
            ->postJson("/api/tenant/pharmacy/prescriptions/{$rxId}/dispense", [
                'notes' => 'Dispensed full 3-day course with instructions',
            ]);

        $dispenseResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('prescription.status', 'dispensed');

        $this->assertNotNull(PharmacyPrescription::find($rxId)->dispensed_at);
    }

    public function test_repair_ticket_lifecycle_and_parts_settlement(): void
    {
        $sparePartProduct = Product::create([
            'company_id' => $this->company->id,
            'name' => 'iPhone 13 OLED Screen Replacement',
            'code' => 'PART-IPH13-SCR',
            'sale_price' => 120.00,
            'cost_price' => 65.00,
            'current_stock' => 10,
            'active' => true,
        ]);

        // 1. Create Intake Ticket
        $intakePayload = [
            'customer_name' => 'Michael Scott',
            'customer_phone' => '+15559090',
            'device_type' => 'Smartphone',
            'brand' => 'Apple',
            'model' => 'iPhone 13 128GB Blue',
            'serial_or_imei' => '354890123456789',
            'passcode_or_pattern' => '1234',
            'issue_description' => 'Cracked front glass, touch digitizer responsive intermittently',
            'physical_condition_notes' => 'Minor scuffs on rear aluminum frame',
            'priority' => 'high',
            'advance_paid' => 30.00,
            'estimated_cost' => 170.00,
            'checklists' => [
                ['item' => 'Power On', 'status' => 'pass'],
                ['item' => 'Display / Touch', 'status' => 'fail'],
                ['item' => 'Front & Rear Camera', 'status' => 'pass'],
            ],
        ];

        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/tenant/repair/tickets', $intakePayload);

        $createResponse->assertOk()
            ->assertJsonPath('success', true);

        $ticketId = $createResponse->json('ticket.id');
        $this->assertNotNull($ticketId);

        // 2. Check Stats
        $statsResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/repair/stats');

        $statsResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stats.active', 1);

        // 3. Update Status to in_progress
        $statusResponse = $this->withHeaders($this->authHeaders())
            ->postJson("/api/tenant/repair/tickets/{$ticketId}/status", [
                'status' => 'in_progress',
                'notes' => 'Technician opened device and prepped for screen replacement',
            ]);

        $statusResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('ticket.status', 'in_progress');

        // 4. Add Spare Part (Stock deduction & recalculation)
        $partResponse = $this->withHeaders($this->authHeaders())
            ->postJson("/api/tenant/repair/tickets/{$ticketId}/parts", [
                'product_id' => $sparePartProduct->id,
                'part_name' => 'iPhone 13 OLED Screen Replacement',
                'quantity' => 1,
                'unit_price' => 120.00,
            ]);

        $partResponse->assertOk()
            ->assertJsonPath('success', true);
        $this->assertEquals(120.00, (float) $partResponse->json('ticket.parts_cost'));

        $this->assertEquals(9, $sparePartProduct->fresh()->current_stock);

        // 5. Update Labor Fee
        $laborResponse = $this->withHeaders($this->authHeaders())
            ->postJson("/api/tenant/repair/tickets/{$ticketId}/labor", [
                'labor_fee' => 50.00,
            ]);

        $laborResponse->assertOk()
            ->assertJsonPath('success', true);
        $this->assertEquals(50.00, (float) $laborResponse->json('ticket.labor_fee'));
        $this->assertEquals(170.00, (float) $laborResponse->json('ticket.total_amount'));
        $this->assertEquals(140.00, (float) $laborResponse->json('ticket.balance_due'));

        // 6. Settle Ticket (Mark repaired -> delivered and generate sale receipt)
        $settleResponse = $this->withHeaders($this->authHeaders())
            ->postJson("/api/tenant/repair/tickets/{$ticketId}/settle", [
                'payment_method' => 'card',
                'paid_amount' => 140.00,
                'notes' => 'Customer collected device, final inspection passed',
            ]);

        $settleResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('ticket.status', 'delivered');

        $saleId = $settleResponse->json('sale.id');
        $this->assertNotNull($saleId);

        $ticket = RepairTicket::find($ticketId);
        $this->assertEquals('delivered', $ticket->status);
        $this->assertEquals($saleId, $ticket->final_sale_id);
    }

    public function test_sdui_views_for_pharmacy_and_repair_modules(): void
    {
        $ticket = RepairTicket::create([
            'company_id' => $this->company->id,
            'ticket_number' => 'REP-TEST-001',
            'customer_name' => 'Alice Johnson',
            'customer_phone' => '+15557788',
            'device_type' => 'Laptop',
            'brand' => 'Dell',
            'model' => 'XPS 15',
            'issue_description' => 'Battery swelling and not holding charge',
            'status' => 'active',
            'priority' => 'normal',
            'estimated_cost' => 90.00,
            'advance_paid' => 20.00,
            'labor_fee' => 30.00,
            'parts_cost' => 60.00,
            'total_amount' => 90.00,
        ]);

        $views = [
            'pharmacy-batches' => 'Batch & Expiry Manager',
            'pharmacy-prescriptions' => 'Prescriptions Queue',
            'repair-dashboard' => 'Repair Workbench',
            'repair-create-ticket' => 'New Repair Ticket',
            'repair-tickets' => 'Repair Ticket Register',
            'repair-my-jobs' => 'Technician Assigned Jobs',
            "repair-detail?ticket_id={$ticket->id}" => 'Workbench: #REP-TEST-001',
            'repair-categories' => 'Device Categories & Specs',
        ];

        $validator = new SchemaValidator;

        foreach ($views as $viewEndpoint => $expectedTitle) {
            $response = $this->withHeaders($this->authHeaders())
                ->getJson("/api/tenant/views/{$viewEndpoint}");

            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('schema.title', $expectedTitle);

            $schema = $response->json('schema');
            $this->assertIsArray($schema);
            $this->assertNotEmpty($schema['components']);

            $validationErrors = $validator->validate($schema);
            $this->assertEmpty($validationErrors, "SDUI Schema validation failed for view [{$viewEndpoint}]: ".implode(', ', $validationErrors));
        }
    }

    public function test_pharmacy_and_repair_pos_return_the_universal_pos_screen_contract(): void
    {
        $validator = new SchemaValidator;

        foreach (['pharmacy-pos' => '/api/tenant/pharmacy/checkout-sheet', 'repair-pos' => '/api/tenant/repair/checkout-sheet'] as $view => $expectedCheckoutEndpoint) {
            $response = $this->withHeaders($this->authHeaders())
                ->getJson("/api/tenant/views/{$view}");

            $response->assertOk()->assertJsonPath('success', true);

            $schema = $response->json('schema');
            $this->assertSame('pos_screen', $schema['type']);
            $this->assertIsArray($schema['catalog']['items'] ?? null);
            $this->assertSame($expectedCheckoutEndpoint, $schema['cart_bar']['checkout_sheet_endpoint'] ?? null);
            $this->assertArrayHasKey('search', $schema);
            $this->assertArrayHasKey('categories', $schema);

            $validationErrors = $validator->validate($schema);
            $this->assertEmpty($validationErrors, "SDUI Schema validation failed for view [{$view}]: ".implode(', ', $validationErrors));
        }
    }

    public function test_pharmacy_batch_sheet_and_checkout_sheet_endpoints(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Ibuprofen 200mg',
            'sku' => 'IBU-200',
            'sale_price' => 5.00,
            'current_stock' => 20,
            'active' => true,
        ]);

        PharmacyBatch::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'product_id' => $product->id,
            'batch_number' => 'BATCH-IBU-1',
            'expiry_date' => now()->addMonths(4),
            'cost_price' => 2.00,
            'selling_price' => 5.00,
            'stock_qty' => 20,
            'is_active' => true,
        ]);

        $validator = new SchemaValidator;

        $batchSheetResponse = $this->withHeaders($this->authHeaders())
            ->getJson("/api/tenant/pharmacy/batch-sheet?product_id={$product->id}");
        $batchSheetResponse->assertOk()->assertJsonPath('success', true);
        $batchSchema = $batchSheetResponse->json('schema');
        $this->assertSame('sheet', $batchSchema['type']);
        $this->assertEmpty($validator->validate($batchSchema));

        $cart = urlencode(json_encode([['title' => 'Ibuprofen 200mg', 'qty' => 2, 'price' => 5.00]]));
        $checkoutSheetResponse = $this->withHeaders($this->authHeaders())
            ->getJson("/api/tenant/pharmacy/checkout-sheet?cart={$cart}");
        $checkoutSheetResponse->assertOk()->assertJsonPath('success', true);
        $checkoutSchema = $checkoutSheetResponse->json('schema');
        $this->assertSame('sheet', $checkoutSchema['type']);
        $this->assertSame('native_pos_checkout_drawer', $checkoutSchema['presentation']);
        $this->assertSame('pharmacy', $checkoutSchema['module']);
        $this->assertSame(10.0, (float) $checkoutSchema['order_summary']['grand_total']);
        $this->assertCount(4, $checkoutSchema['payment_methods']);
        $this->assertCount(5, $checkoutSchema['quick_cash']['suggestions']);
        $this->assertStringContainsString('Attach Doctor & Rx Details', json_encode($checkoutSchema));
        $this->assertEmpty($validator->validate($checkoutSchema));

        $selectedCashSheet = $this->withHeaders($this->authHeaders())
            ->getJson("/api/tenant/pharmacy/checkout-sheet?cart={$cart}&selected_tendered=20");
        $selectedCashSheet->assertOk()
            ->assertJsonPath('schema.quick_cash.selected_tendered', 20)
            ->assertJsonPath('schema.quick_cash.change_due', 10);
    }

    public function test_repair_pos_checkout_creates_a_real_sale_with_real_items(): void
    {
        $part = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Replacement Screen',
            'sku' => 'SCR-001',
            'sale_price' => 60.00,
            'cost_price' => 25.00,
            'current_stock' => 5,
            'active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/tenant/repair/pos-checkout', [
                'items' => [
                    ['product_id' => $part->id, 'quantity' => 1],
                ],
                'payment_method' => 'cash',
                'customer_name' => 'Walk-in Customer',
            ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(60.0, (float) $response->json('sale.total'));
        $this->assertCount(1, $response->json('sale.items'));

        $part->refresh();
        $this->assertSame(4.0, (float) $part->current_stock);
    }

    public function test_repair_pos_checkout_links_a_ticket_and_updates_its_totals(): void
    {
        $ticket = RepairTicket::create([
            'company_id' => $this->company->id,
            'ticket_number' => 'REP-TEST-LINK',
            'customer_name' => 'Bob Link',
            'customer_phone' => '+15551234',
            'device_type' => 'Phone',
            'brand' => 'Acme',
            'model' => 'X1',
            'issue_description' => 'Cracked screen',
            'status' => 'in_progress',
            'advance_paid' => 10.00,
            'labor_fee' => 20.00,
            'parts_cost' => 0,
            'total_amount' => 20.00,
        ]);

        $part = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Battery Pack',
            'sku' => 'BATT-001',
            'sale_price' => 30.00,
            'current_stock' => 3,
            'active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/tenant/repair/pos-checkout', [
                'items' => [['product_id' => $part->id, 'quantity' => 1]],
                'payment_method' => 'cash',
                'ticket_id' => $ticket->id,
            ]);

        $response->assertOk()->assertJsonPath('success', true);

        $ticket->refresh();
        $this->assertSame(30.0, (float) $ticket->parts_cost);
        $this->assertSame(50.0, (float) $ticket->total_amount);
        $this->assertDatabaseHas('order_payments', [
            'sale_id' => $response->json('sale.id'),
            'payment_method' => 'cash',
            'amount' => 40,
        ]);
        $this->assertDatabaseHas('repair_ticket_parts', [
            'repair_ticket_id' => $ticket->id,
            'product_id' => $part->id,
        ]);

        $drawer = $this->withHeaders($this->authHeaders())
            ->getJson("/api/tenant/repair/tickets/{$ticket->id}/checkout-sheet");
        $drawer->assertOk()
            ->assertJsonPath('schema.presentation', 'native_pos_checkout_drawer')
            ->assertJsonPath('schema.order_summary.advance_paid', 10)
            ->assertJsonPath('schema.order_summary.grand_total', 40);
    }

    public function test_pharmacy_checkout_requires_prescription_details_for_controlled_items(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Codeine 30mg',
            'sku' => 'COD-30',
            'sale_price' => 15.00,
            'current_stock' => 10,
            'active' => true,
            'requires_prescription' => true,
        ]);

        $withoutRx = $this->withHeaders($this->authHeaders())
            ->postJson('/api/tenant/pharmacy/checkout', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
            ]);
        $withoutRx->assertStatus(422);

        $withRx = $this->withHeaders($this->authHeaders())
            ->postJson('/api/tenant/pharmacy/checkout', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'cash',
                'patient_name' => 'Jane Doe',
                'doctor_name' => 'Dr. Smith',
            ]);
        $withRx->assertOk()->assertJsonPath('success', true);
        $this->assertNotNull($withRx->json('prescription'));
    }

    public function test_pharmacy_checkout_accepts_two_fixed_split_payment_rows(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Vitamin C',
            'sku' => 'VITC-01',
            'sale_price' => 25.00,
            'current_stock' => 10,
            'active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/tenant/pharmacy/checkout', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => 'split',
                'payment_1_method' => 'cash',
                'payment_1_amount' => 15,
                'payment_2_method' => 'card',
                'payment_2_amount' => 10,
            ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('order_payments', ['sale_id' => $response->json('sale.id'), 'payment_method' => 'cash', 'amount' => 15]);
        $this->assertDatabaseHas('order_payments', ['sale_id' => $response->json('sale.id'), 'payment_method' => 'card', 'amount' => 10]);
    }

    public function test_pending_prescription_loads_catalog_medicines_into_native_checkout(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Azithromycin 500mg',
            'generic_name' => 'Azithromycin',
            'sale_price' => 18,
            'current_stock' => 10,
            'active' => true,
            'requires_prescription' => true,
            'narcotic_schedule' => 'Schedule H',
        ]);
        PharmacyBatch::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'batch_number' => 'RX-FEFO-1',
            'expiry_date' => now()->addMonths(6),
            'selling_price' => 18,
            'stock_qty' => 10,
            'is_active' => true,
        ]);
        $prescription = PharmacyPrescription::create([
            'company_id' => $this->company->id,
            'prescription_number' => 'RX-QUEUE-001',
            'patient_name' => 'Queue Patient',
            'doctor_name' => 'Dr Queue',
            'prescription_date' => today(),
            'medicines' => [['name' => 'Azithromycin 500mg', 'qty' => 2]],
            'status' => 'pending',
        ]);

        $sheet = $this->withHeaders($this->authHeaders())
            ->getJson("/api/tenant/pharmacy/prescriptions/{$prescription->id}/checkout-sheet");
        $sheet->assertOk()
            ->assertJsonPath('schema.presentation', 'native_pos_checkout_drawer')
            ->assertJsonPath('schema.order_summary.line_item_count', 1)
            ->assertJsonPath('schema.prescription_context.patient_name', 'Queue Patient');

        $checkout = $this->withHeaders($this->authHeaders())
            ->postJson("/api/tenant/pharmacy/prescriptions/{$prescription->id}/checkout", [
                'payment_method' => 'cash',
                'quick_cash_tendered' => 40,
            ]);
        $checkout->assertOk()->assertJsonPath('success', true);
        $this->assertSame('dispensed', $prescription->fresh()->status);
        $this->assertDatabaseHas('order_payments', [
            'sale_id' => $checkout->json('sale.id'),
            'tendered' => 40,
            'change_returned' => 4,
        ]);
    }

    public function test_repair_device_categories_crud_and_ticket_intake(): void
    {
        // 1. Categories Index should auto-initialize presets if empty
        $indexResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/repair/categories');

        $indexResponse->assertOk()
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(6, $indexResponse->json('count'));

        // 2. Create custom device category
        $storeResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/tenant/repair/categories', [
                'name' => 'Smart Watches & Wearables',
                'icon' => 'watch',
                'identifier_type' => 'Serial Number',
                'brands' => 'Apple Watch, Samsung Galaxy Watch, Garmin, Fitbit',
                'checklist_items' => 'Power On, Touch Screen, Heart Rate Sensor, Wireless Charging',
                'common_issues' => 'Cracked OLED, Sensor Failure, Battery Drain',
                'description' => 'Wearable smart health devices',
            ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('category.name', 'Smart Watches & Wearables')
            ->assertJsonPath('category.slug', 'smart-watches-wearables');

        $categoryId = $storeResponse->json('category.id');
        $this->assertNotNull($categoryId);

        $category = RepairDeviceCategory::find($categoryId);
        $this->assertEquals(['Apple Watch', 'Samsung Galaxy Watch', 'Garmin', 'Fitbit'], $category->brands);
        $this->assertEquals(['Power On', 'Touch Screen', 'Heart Rate Sensor', 'Wireless Charging'], $category->checklist_items);

        // 3. Create Ticket using dynamic device_category_id
        $ticketResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/tenant/repair/tickets', [
                'customer_name' => 'Michael Scott',
                'customer_phone' => '+15559988',
                'device_category_id' => $categoryId,
                'brand' => 'Apple Watch',
                'model' => 'Ultra 2',
                'serial_or_imei' => 'WAT-8921-X',
                'issue_description' => 'Heart rate sensor not responding after swimming',
                'priority' => 'high',
                'estimated_cost' => 120.00,
                'advance_paid' => 40.00,
            ]);

        $ticketResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('ticket.device_category_id', $categoryId)
            ->assertJsonPath('ticket.device_type', 'Smart Watches & Wearables')
            ->assertJsonPath('ticket.brand', 'Apple Watch');

        $ticketId = $ticketResponse->json('ticket.id');
        $checklists = RepairChecklist::where('repair_ticket_id', $ticketId)->get();
        // Verifies intake checklist was auto-populated from category specifications
        $this->assertCount(4, $checklists);
        $this->assertTrue($checklists->pluck('item_name')->contains('Heart Rate Sensor'));
    }

    public function test_sample_data_service_seed_and_purge_for_pharmacy_and_repair(): void
    {
        $seeder = new TenantSampleDataService;

        // 1. Seed Pharmacy Demo Data
        $seeder->seed($this->company, 'pharmacy', $this->admin);

        $this->assertGreaterThan(0, PharmacyBatch::withoutGlobalScope('company')->where('company_id', $this->company->id)->where('is_demo', true)->count());
        $this->assertGreaterThan(0, PharmacyPrescription::withoutGlobalScope('company')->where('company_id', $this->company->id)->where('is_demo', true)->count());

        // 2. Seed Repair Demo Data
        $seeder->seed($this->company, 'repair_technician', $this->admin);

        $this->assertGreaterThan(0, RepairDeviceCategory::withoutGlobalScope('company')->where('company_id', $this->company->id)->where('is_demo', true)->count());
        $this->assertGreaterThan(0, RepairTicket::withoutGlobalScope('company')->where('company_id', $this->company->id)->where('is_demo', true)->count());
        $this->assertGreaterThan(0, RepairTicketPart::withoutGlobalScope('company')->where('company_id', $this->company->id)->count());
        $this->assertGreaterThan(0, RepairChecklist::withoutGlobalScope('company')->where('company_id', $this->company->id)->count());

        // 3. Purge Demo Data
        $purgedCounts = $seeder->purgeDemoData($this->company);

        $this->assertGreaterThan(0, $purgedCounts['repair_device_categories'] ?? 0);
        $this->assertGreaterThan(0, $purgedCounts['repair_tickets'] ?? 0);
        $this->assertGreaterThan(0, $purgedCounts['pharmacy_prescriptions'] ?? 0);
        $this->assertGreaterThan(0, $purgedCounts['pharmacy_batches'] ?? 0);

        // Verify zero demo records remain
        $this->assertEquals(0, PharmacyBatch::withoutGlobalScope('company')->where('company_id', $this->company->id)->where('is_demo', true)->count());
        $this->assertEquals(0, PharmacyPrescription::withoutGlobalScope('company')->where('company_id', $this->company->id)->where('is_demo', true)->count());
        $this->assertEquals(0, RepairDeviceCategory::withoutGlobalScope('company')->where('company_id', $this->company->id)->where('is_demo', true)->count());
        $this->assertEquals(0, RepairTicket::withoutGlobalScope('company')->where('company_id', $this->company->id)->where('is_demo', true)->count());
    }

    public function test_sdui_views_operational_depth_and_post_sale_contracts(): void
    {
        // 1. Setup sample batch and prescription
        $med = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Amoxicillin 500mg',
            'code' => 'TEST-AMX-500',
            'sale_price' => 15.00,
            'cost_price' => 8.00,
            'current_stock' => 50,
            'active' => true,
        ]);
        $batch = PharmacyBatch::create([
            'company_id' => $this->company->id,
            'product_id' => $med->id,
            'batch_number' => 'AMX-2026-TEST',
            'manufacturing_date' => now()->subMonths(2),
            'expiry_date' => now()->addDays(20), // Critical <= 30 days
            'cost_price' => 8.00,
            'selling_price' => 15.00,
            'stock_qty' => 50,
            'alert_days_before_expiry' => 90,
        ]);
        $rx = PharmacyPrescription::create([
            'company_id' => $this->company->id,
            'prescription_number' => 'RX-TEST-009',
            'patient_name' => 'Alice Patient',
            'patient_phone' => '1234567890',
            'doctor_name' => 'Dr. Robert Smith',
            'doctor_registration_no' => 'DOC-99881',
            'prescription_date' => now()->toDateString(),
            'status' => 'pending',
            'diagnosis' => 'Bacterial Infection',
            'notes' => 'Amoxicillin 500mg TDS for 5 days',
            'medicines' => [['name' => 'Amoxicillin 500mg', 'quantity' => 15]],
        ]);

        // 2. Test pharmacy-batches view
        $batchViewRes = $this->withHeaders($this->authHeaders())->getJson('/api/tenant/views/pharmacy-batches');
        $batchViewRes->assertOk();
        $batchViewJson = json_encode($batchViewRes->json());
        $this->assertStringContainsString('Expiring in 30 Days', $batchViewJson);
        $this->assertStringContainsString('Print Barcode', $batchViewJson);
        $this->assertStringContainsString('Audit Stock', $batchViewJson);

        // 3. Test pharmacy-prescriptions view with status filter
        $rxViewRes = $this->withHeaders($this->authHeaders())->getJson('/api/tenant/views/pharmacy-prescriptions?status=pending');
        $rxViewRes->assertOk();
        $rxViewJson = json_encode($rxViewRes->json());
        $this->assertStringContainsString('Alice Patient', $rxViewJson);
        $this->assertStringContainsString('Load Prescription into POS', $rxViewJson);

        // 4. Test repair-create-ticket view includes check_battery
        $createTicketRes = $this->withHeaders($this->authHeaders())->getJson('/api/tenant/views/repair-create-ticket');
        $createTicketRes->assertOk();
        $createTicketJson = json_encode($createTicketRes->json());
        $this->assertStringContainsString('check_battery', $createTicketJson);
        $this->assertStringContainsString('Battery Health & State', $createTicketJson);

        // 5. Test repair-dashboard view includes 6-stage Kanban
        $ticket = RepairTicket::create([
            'company_id' => $this->company->id,
            'ticket_number' => 'REP-KANBAN-01',
            'customer_name' => 'John DeviceOwner',
            'customer_phone' => '9876543210',
            'brand' => 'Apple',
            'model' => 'iPhone 13',
            'device_type' => 'Smartphone',
            'issue_description' => 'Broken screen & low battery',
            'status' => 'repaired',
            'labor_fee' => 35.00,
            'parts_cost' => 45.00,
            'total_amount' => 80.00,
            'advance_paid' => 20.00,
        ]);

        $dashRes = $this->withHeaders($this->authHeaders())->getJson('/api/tenant/views/repair-dashboard');
        $dashRes->assertOk();
        $dashContent = $dashRes->getContent();
        $this->assertStringContainsString('6-Stage Workbench Kanban', $dashContent);
        $this->assertStringContainsString('Received', $dashContent);
        $this->assertStringContainsString('5. Repaired & Ready for Pickup', $dashContent);
        $this->assertStringContainsString('Deliver & Settle', $dashContent);

        // 6. Test repair ticket settlement returns post-sale URLs
        $settleRes = $this->withHeaders($this->authHeaders())->postJson("/api/tenant/repair/tickets/{$ticket->id}/settle", [
            'payment_method' => 'cash',
            'tendered' => 15.00,
        ]);
        $settleRes->assertOk()->assertJsonPath('success', true);
        $this->assertNotEmpty($settleRes->json('whatsapp_url'));
        $this->assertNotEmpty($settleRes->json('invoice_url'));
        $this->assertNotEmpty($settleRes->json('thermal_print_url'));
        $this->assertEquals(35.00, (float) $settleRes->json('sale.paid_amount')); // 35 labor - 20 advance = 15 due; total sale = 35 paid

        // 7. Test repair pos checkout returns post-sale URLs
        $posRes = $this->withHeaders($this->authHeaders())->postJson('/api/tenant/repair/checkout', [
            'items' => [
                [
                    'product_id' => $med->id,
                    'quantity' => 1,
                    'unit_price' => 25.00,
                ],
            ],
            'customer_phone' => '9876543210',
            'payment_method' => 'cash',
            'tendered' => 25.00,
        ]);
        $posRes->assertOk()->assertJsonPath('success', true);
        $this->assertNotEmpty($posRes->json('whatsapp_url'));
        $this->assertNotEmpty($posRes->json('invoice_url'));
        $this->assertNotEmpty($posRes->json('thermal_print_url'));
    }
}
