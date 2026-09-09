<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Pharmacy\Batches;
use App\Livewire\Tenant\Pharmacy\Prescriptions;
use App\Models\Company;
use App\Models\PharmacyBatch;
use App\Models\PharmacyPrescription;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PharmacyVerticalWebTest extends TestCase
{
    use RefreshDatabase;

    private function pharmacyTenant(): array
    {
        $company = Company::create([
            'name' => 'City Pharmacy',
            'status' => 'active',
            'pos_mode' => 'pharmacy',
            'licensed_modules' => ['pharmacy'],
            'currency_symbol' => '$',
            'expires_at' => now()->addYear(),
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Rx Admin',
            'login' => 'rx',
            'email' => 'rx@city.test',
            'password' => Hash::make('secret1234'),
            'role' => 'administrator',
            'status' => 'approved',
        ]);

        $this->actingAs($user, 'web');
        app()->instance('tenant.company_id', $company->id);

        return [$company, $user];
    }

    public function test_pharmacy_routes_load_for_a_licensed_tenant(): void
    {
        $this->pharmacyTenant();

        $this->get(route('tenant.pharmacy.dashboard'))->assertOk()->assertSee('Pharmacy');
        $this->get(route('tenant.pharmacy.batches'))->assertOk()->assertSee('Drug Batches');
        $this->get(route('tenant.pharmacy.prescriptions'))->assertOk()->assertSee('Prescriptions');
    }

    public function test_sidebar_shows_the_pharmacy_group_only_for_a_pharmacy_tenant(): void
    {
        $this->pharmacyTenant();
        $this->get(route('tenant.dashboard'))
            ->assertOk()
            ->assertSee('Pharmacy Operations')
            ->assertSee(route('tenant.pharmacy.batches'))
            ->assertDontSee('Salon &amp; Bookings')
            ->assertDontSee('Repair Operations');
    }

    public function test_retail_tenant_never_sees_the_pharmacy_group(): void
    {
        $company = Company::create([
            'name' => 'Corner Shop', 'status' => 'active', 'pos_mode' => 'general',
            'licensed_modules' => ['retail'], 'currency_symbol' => '$', 'expires_at' => now()->addYear(),
        ]);
        $user = User::create([
            'company_id' => $company->id, 'name' => 'Shop Admin', 'login' => 'shop2',
            'email' => 'shop2@corner.test', 'password' => Hash::make('secret1234'),
            'role' => 'administrator', 'status' => 'approved',
        ]);
        $this->actingAs($user, 'web');
        app()->instance('tenant.company_id', $company->id);

        $this->get(route('tenant.dashboard'))->assertOk()->assertDontSee('Pharmacy Operations');
    }

    public function test_pharmacy_routes_are_blocked_for_a_retail_tenant(): void
    {
        $company = Company::create([
            'name' => 'Corner Shop', 'status' => 'active', 'pos_mode' => 'general',
            'licensed_modules' => ['retail'], 'currency_symbol' => '$', 'expires_at' => now()->addYear(),
        ]);
        $user = User::create([
            'company_id' => $company->id, 'name' => 'Shop Admin', 'login' => 'shop',
            'email' => 'shop@corner.test', 'password' => Hash::make('secret1234'),
            'role' => 'administrator', 'status' => 'approved',
        ]);
        $this->actingAs($user, 'web');
        app()->instance('tenant.company_id', $company->id);

        $this->get(route('tenant.pharmacy.batches'))->assertRedirect(route('tenant.dashboard'));
    }

    public function test_can_register_a_drug_batch_and_stock_flows_to_the_product(): void
    {
        [$company] = $this->pharmacyTenant();

        $product = Product::create([
            'company_id' => $company->id, 'name' => 'Amoxicillin 500mg', 'sku' => 'AMOX-500',
            'sale_price' => 5.00, 'cost_price' => 2.00, 'current_stock' => 0, 'active' => true,
        ]);

        Livewire::test(Batches::class)
            ->call('newBatch')
            ->set('productId', $product->id)
            ->set('batchNumber', 'B-2026-01')
            ->set('expiryDate', now()->addYears(2)->toDateString())
            ->set('stockQty', 40)
            ->set('sellingPrice', 6.50)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pharmacy_batches', [
            'company_id' => $company->id, 'batch_number' => 'B-2026-01', 'stock_qty' => 40,
        ]);
        $this->assertSame(40.0, (float) $product->fresh()->current_stock);
    }

    public function test_can_take_a_prescription_and_dispense_it(): void
    {
        [$company] = $this->pharmacyTenant();

        Livewire::test(Prescriptions::class)
            ->call('newIntake')
            ->set('patientName', 'John Patient')
            ->set('doctorName', 'Dr Smith')
            ->set('medicines', 'Amoxicillin 500mg TDS x 7 days')
            ->call('save')
            ->assertHasNoErrors();

        $rx = PharmacyPrescription::where('company_id', $company->id)->firstOrFail();
        $this->assertSame('pending', $rx->status);

        Livewire::test(Prescriptions::class)
            ->call('dispense', $rx->id);

        $this->assertSame('dispensed', $rx->fresh()->status);
        $this->assertNotNull($rx->fresh()->dispensed_at);
    }

    public function test_batch_adjust_and_vendor_return_move_product_stock(): void
    {
        [$company] = $this->pharmacyTenant();
        $product = Product::create([
            'company_id' => $company->id, 'name' => 'Paracetamol 650', 'sku' => 'PARA-650',
            'sale_price' => 2.0, 'cost_price' => 1.0, 'current_stock' => 100, 'active' => true,
        ]);
        $batch = PharmacyBatch::create([
            'company_id' => $company->id, 'product_id' => $product->id, 'batch_number' => 'P-1',
            'expiry_date' => now()->addYear()->toDateString(), 'stock_qty' => 100, 'is_active' => true,
        ]);

        Livewire::test(Batches::class)
            ->call('startAdjust', $batch->id)
            ->set('adjustNewStock', 90)
            ->set('reason', 'Cycle count')
            ->call('confirmAction')
            ->assertHasNoErrors();

        $this->assertSame(90, (int) $batch->fresh()->stock_qty);
        $this->assertSame(90.0, (float) $product->fresh()->current_stock);

        Livewire::test(Batches::class)
            ->call('startReturn', $batch->id)
            ->set('returnQty', 10)
            ->set('reason', 'Damaged')
            ->call('confirmAction')
            ->assertHasNoErrors();

        $this->assertSame(80, (int) $batch->fresh()->stock_qty);
        $this->assertSame(80.0, (float) $product->fresh()->current_stock);
    }
}
