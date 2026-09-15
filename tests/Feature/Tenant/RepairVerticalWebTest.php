<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Repair\Categories;
use App\Livewire\Tenant\Repair\TicketDetail;
use App\Livewire\Tenant\Repair\Tickets;
use App\Models\Company;
use App\Models\Product;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class RepairVerticalWebTest extends TestCase
{
    use RefreshDatabase;

    private function repairTenant(): array
    {
        $company = Company::create([
            'name' => 'Fix It Shop', 'status' => 'active', 'pos_mode' => 'repair_technician',
            'licensed_modules' => ['repair_technician'], 'currency_symbol' => '$', 'expires_at' => now()->addYear(),
        ]);
        $user = User::create([
            'company_id' => $company->id, 'name' => 'Repair Admin', 'login' => 'fix',
            'email' => 'admin@fixit.test', 'password' => Hash::make('secret1234'),
            'role' => 'administrator', 'status' => 'approved',
        ]);
        $this->actingAs($user, 'web');
        app()->instance('tenant.company_id', $company->id);

        return [$company, $user];
    }

    public function test_repair_routes_load_for_a_licensed_tenant(): void
    {
        $this->repairTenant();
        $this->get(route('tenant.repair.dashboard'))->assertOk()->assertSee('Repair Workbench');
        $this->get(route('tenant.repair.tickets'))->assertOk()->assertSee('Repair Ticket Register');
        $this->get(route('tenant.repair.categories'))->assertOk()->assertSee('Device Categories');
    }

    public function test_repair_routes_blocked_for_a_retail_tenant(): void
    {
        $company = Company::create([
            'name' => 'Shop', 'status' => 'active', 'pos_mode' => 'general',
            'licensed_modules' => ['retail'], 'currency_symbol' => '$', 'expires_at' => now()->addYear(),
        ]);
        $user = User::create([
            'company_id' => $company->id, 'name' => 'A', 'login' => 'a', 'email' => 'a@shop.test',
            'password' => Hash::make('secret1234'), 'role' => 'administrator', 'status' => 'approved',
        ]);
        $this->actingAs($user, 'web');
        app()->instance('tenant.company_id', $company->id);

        $this->get(route('tenant.repair.tickets'))->assertRedirect(route('tenant.dashboard'));
    }

    public function test_create_ticket_then_add_part_and_labor_on_the_workbench(): void
    {
        [$company] = $this->repairTenant();

        $component = Livewire::test(Tickets::class)
            ->call('newTicket')
            ->set('customerName', 'Sam Owner')
            ->set('brand', 'Apple')
            ->set('model', 'iPhone 13')
            ->set('problemReported', 'Cracked screen, no touch on the left edge')
            ->set('estimatedCost', 120)
            ->call('create')
            ->assertHasNoErrors();

        $ticket = RepairTicket::where('company_id', $company->id)->firstOrFail();
        $this->assertSame(RepairTicket::STATUS_RECEIVED, $ticket->status);

        $part = Product::create([
            'company_id' => $company->id, 'name' => 'iPhone 13 Screen', 'sku' => 'SCR-IP13',
            'sale_price' => 80, 'cost_price' => 40, 'current_stock' => 5, 'active' => true,
        ]);

        Livewire::test(TicketDetail::class, ['ticket' => $ticket])
            ->set('partProductId', $part->id)
            ->set('partQty', 1)
            ->call('addPart')
            ->assertHasNoErrors()
            ->set('laborFee', 30)
            ->call('setLabor')
            ->assertHasNoErrors()
            ->set('newStatus', RepairTicket::STATUS_READY)
            ->call('updateStatus')
            ->assertHasNoErrors();

        $ticket->refresh();
        $this->assertSame(4.0, (float) $part->fresh()->current_stock);
        $this->assertSame(RepairTicket::STATUS_READY, $ticket->status);
        // parts (80) + labor (30) + no diagnostic fee
        $this->assertEqualsWithDelta(110.0, (float) $ticket->total_amount, 0.01);
    }

    public function test_can_create_and_edit_a_device_category(): void
    {
        $this->repairTenant();

        Livewire::test(Categories::class)
            ->call('newCategory')
            ->set('name', 'Laptops')
            ->set('identifierType', 'Serial Number')
            ->set('brands', 'Dell, HP, Lenovo')
            ->set('checklistItems', "Power On\nKeyboard\nBattery Health")
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['name' => 'Laptops', 'type' => 'device']);
    }
}
