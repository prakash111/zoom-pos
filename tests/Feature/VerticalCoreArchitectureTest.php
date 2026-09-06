<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\NotificationReminder;
use App\Models\PharmacyPrescription;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Services\Documents\DocumentNumberService;
use App\Services\Notifications\VerticalReminderService;
use App\Services\Pos\Adapters\RepairCartAdapter;
use App\Services\Pos\SduiPosAdapterInterface;
use App\Services\Pos\UniversalPosBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VerticalCoreArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Vertical Core Store',
            'slug' => 'vertical-core-store',
            'email' => 'core@example.test',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'invoice_prefix' => 'BILL-',
            'repair_prefix' => 'FIX-',
            'prescription_prefix' => 'MED-',
            'salon_prefix' => 'SPA-',
        ]);
    }

    public function test_repair_adapter_builds_the_universal_drawer_with_dynamic_deposit_balance(): void
    {
        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Repair Customer',
        ]);
        $ticket = RepairTicket::create([
            'company_id' => $this->company->id,
            'ticket_number' => 'FIX-2026-0001',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'problem_reported' => 'Diagnostic required',
            'estimated_cost' => 100,
            'diagnostic_fee' => 20,
            'total_amount' => 120,
            'advance_deposit' => 30,
        ]);

        $adapter = new RepairCartAdapter($this->company, $ticket->load('customer'));

        $this->assertInstanceOf(SduiPosAdapterInterface::class, $adapter);
        $this->assertSame(120.0, collect($adapter->getLineItems())->sum(fn (array $line) => (float) $line['price']));
        $this->assertSame(['service_labor', 'diagnostic_fee'], collect($adapter->getLineItems())->pluck('line_type')->all());
        $this->assertSame(120.0, $ticket->total_amount);
        $this->assertSame(90.0, $ticket->balance_due);

        $sheet = UniversalPosBuilder::buildCartSheet($adapter);
        $this->assertSame('native_pos_checkout_drawer', $sheet['presentation']);
        $this->assertSame('repair', $sheet['module']);
        $this->assertSame(30.0, $sheet['order_summary']['advance_paid']);
        $this->assertSame(90.0, $sheet['order_summary']['grand_total']);
    }

    public function test_every_sale_json_snapshot_is_normalized_into_core_sale_items(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Tracked Medicine',
            'sale_price' => 12,
            'current_stock' => 5,
            'active' => true,
        ]);
        $batch = ProductBatch::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'batch_number' => 'LOT-1',
            'expiry_date' => now()->addYear(),
            'stock_quantity' => 5,
            'unit_cost' => 4,
            'rack_location' => 'A-04',
        ]);

        $sale = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'BILL-0001',
            'module_type' => 'pharmacy',
            'doctor_name' => 'Dr Core',
            'total' => 24,
            'net_amount' => 24,
            'paid_amount' => 24,
            'due_amount' => 0,
            'status' => 'completed',
            'items' => [[
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'name' => $product->name,
                'quantity' => 2,
                'unit_price' => 12,
                'total' => 24,
                'dosage_notes' => 'One tablet twice daily',
            ]],
        ]);

        $this->assertTrue(Schema::hasTable('sale_items'));
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'batch_id' => $batch->id,
            'line_type' => 'medication',
            'quantity' => 2,
            'total' => 24,
        ]);
        $this->assertSame('A-04', $batch->rack_location);
        $this->assertSame(5, $batch->stock_quantity);
    }

    public function test_prefixes_and_refill_reminders_come_from_shared_store_configuration(): void
    {
        $numbers = app(DocumentNumberService::class);
        $this->assertStringStartsWith('FIX-', $numbers->next($this->company, 'repair'));
        $this->assertStringStartsWith('MED-', $numbers->next($this->company, 'prescription'));
        $this->assertStringStartsWith('SPA-', $numbers->next($this->company, 'salon'));
        $this->assertStringStartsWith('BILL-', $numbers->next($this->company, 'invoice'));

        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Patient Core',
            'phone' => '+15551234567',
            'age' => 44,
            'allergies' => 'Penicillin',
        ]);
        $prescription = PharmacyPrescription::create([
            'company_id' => $this->company->id,
            'prescription_number' => 'MED-20260906-0001',
            'customer_id' => $customer->id,
            'patient_name' => $customer->name,
            'patient_phone' => $customer->phone,
            'doctor_name' => 'Dr Core',
            'prescription_date' => today(),
            'status' => 'dispensed',
            'dispensed_at' => now(),
            'dosage_duration_days' => 30,
        ]);
        $sale = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'BILL-0001',
            'module_type' => 'pharmacy',
            'reference_ticket_id' => $prescription->id,
            'customer_id' => $customer->id,
            'total' => 10,
            'net_amount' => 10,
            'paid_amount' => 10,
            'due_amount' => 0,
            'status' => 'completed',
            'items' => [],
        ]);

        $reminder = app(VerticalReminderService::class)->schedulePharmacyRefill($prescription, $sale);

        $this->assertInstanceOf(NotificationReminder::class, $reminder);
        $this->assertSame('pharmacy.refill_due', $reminder->event_type);
        $this->assertSame(25.0, now()->startOfDay()->diffInDays($reminder->scheduled_at->copy()->startOfDay()));
    }
}
