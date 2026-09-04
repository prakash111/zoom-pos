<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\PharmacyBatch;
use App\Models\PharmacyPrescription;
use App\Models\Plan;
use App\Models\Product;
use App\Models\RepairChecklist;
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
            'pharmacy-pos' => 'Pharmacy Counter POS',
            'pharmacy-batches' => 'Batch & Expiry Manager',
            'pharmacy-prescriptions' => 'Prescriptions Queue',
            'repair-dashboard' => 'Repair Workbench',
            'repair-create-ticket' => 'New Repair Ticket',
            'repair-tickets' => 'Repair Ticket Register',
            'repair-my-jobs' => 'Technician Assigned Jobs',
            "repair-detail?ticket_id={$ticket->id}" => 'Workbench: #REP-TEST-001',
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

    public function test_sample_data_service_seed_and_purge_for_pharmacy_and_repair(): void
    {
        $seeder = new TenantSampleDataService;

        // 1. Seed Pharmacy Demo Data
        $seeder->seed($this->company, 'pharmacy', $this->admin);

        $this->assertGreaterThan(0, PharmacyBatch::withoutGlobalScope('company')->where('company_id', $this->company->id)->where('is_demo', true)->count());
        $this->assertGreaterThan(0, PharmacyPrescription::withoutGlobalScope('company')->where('company_id', $this->company->id)->where('is_demo', true)->count());

        // 2. Seed Repair Demo Data
        $seeder->seed($this->company, 'repair_technician', $this->admin);

        $this->assertGreaterThan(0, RepairTicket::withoutGlobalScope('company')->where('company_id', $this->company->id)->where('is_demo', true)->count());
        $this->assertGreaterThan(0, RepairTicketPart::withoutGlobalScope('company')->where('company_id', $this->company->id)->count());
        $this->assertGreaterThan(0, RepairChecklist::withoutGlobalScope('company')->where('company_id', $this->company->id)->count());

        // 3. Purge Demo Data
        $purgedCounts = $seeder->purgeDemoData($this->company);

        $this->assertGreaterThan(0, $purgedCounts['repair_tickets'] ?? 0);
        $this->assertGreaterThan(0, $purgedCounts['pharmacy_prescriptions'] ?? 0);
        $this->assertGreaterThan(0, $purgedCounts['pharmacy_batches'] ?? 0);

        // Verify zero demo records remain
        $this->assertEquals(0, PharmacyBatch::withoutGlobalScope('company')->where('company_id', $this->company->id)->where('is_demo', true)->count());
        $this->assertEquals(0, PharmacyPrescription::withoutGlobalScope('company')->where('company_id', $this->company->id)->where('is_demo', true)->count());
        $this->assertEquals(0, RepairTicket::withoutGlobalScope('company')->where('company_id', $this->company->id)->where('is_demo', true)->count());
    }
}
