<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Salon\Calendar;
use App\Livewire\Tenant\Salon\ServiceCatalog;
use App\Livewire\Tenant\Salon\Stylists;
use App\Models\Company;
use App\Models\Product;
use App\Models\SalonAppointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SalonVerticalWebTest extends TestCase
{
    use RefreshDatabase;

    private function salonTenant(): array
    {
        $company = Company::create([
            'name' => 'Glow Salon', 'status' => 'active', 'pos_mode' => 'service_booking',
            'licensed_modules' => ['service_booking'], 'currency_symbol' => '$', 'expires_at' => now()->addYear(),
        ]);
        $user = User::create([
            'company_id' => $company->id, 'name' => 'Salon Admin', 'login' => 'salon',
            'email' => 'admin@glow.test', 'password' => Hash::make('secret1234'),
            'role' => 'administrator', 'status' => 'approved',
        ]);
        $this->actingAs($user, 'web');
        app()->instance('tenant.company_id', $company->id);

        return [$company, $user];
    }

    public function test_salon_routes_load_for_a_licensed_tenant(): void
    {
        $this->salonTenant();
        $this->get(route('tenant.salon.calendar'))->assertOk()->assertSee('Booking Calendar');
        $this->get(route('tenant.salon.services'))->assertOk()->assertSee('Service Catalog');
        $this->get(route('tenant.salon.stylists'))->assertOk()->assertSee('Stylists');
    }

    public function test_salon_routes_blocked_for_a_pharmacy_tenant(): void
    {
        $company = Company::create([
            'name' => 'Rx', 'status' => 'active', 'pos_mode' => 'pharmacy',
            'licensed_modules' => ['pharmacy'], 'currency_symbol' => '$', 'expires_at' => now()->addYear(),
        ]);
        $user = User::create([
            'company_id' => $company->id, 'name' => 'A', 'login' => 'a', 'email' => 'a@rx.test',
            'password' => Hash::make('secret1234'), 'role' => 'administrator', 'status' => 'approved',
        ]);
        $this->actingAs($user, 'web');
        app()->instance('tenant.company_id', $company->id);

        $this->get(route('tenant.salon.calendar'))->assertRedirect(route('tenant.dashboard'));
    }

    public function test_can_create_a_service_then_book_an_appointment(): void
    {
        [$company, $user] = $this->salonTenant();

        Livewire::test(ServiceCatalog::class)
            ->call('newService')
            ->set('name', 'Haircut & Style')
            ->set('price', 25)
            ->set('durationMinutes', 30)
            ->call('save')
            ->assertHasNoErrors();

        $service = Product::where('company_id', $company->id)->where('name', 'Haircut & Style')->firstOrFail();

        $user->update(['is_specialist' => true]);

        Livewire::test(Calendar::class)
            ->set('serviceId', $service->id)
            ->set('specialistId', $user->id)
            ->set('customerName', 'Priya Client')
            ->set('customerPhone', '555-2000')
            ->set('appointmentDate', now()->addDay()->toDateString())
            ->set('appointmentTime', '11:00')
            ->call('book')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('salon_appointments', [
            'company_id' => $company->id, 'customer_name' => 'Priya Client', 'status' => 'scheduled',
        ]);
    }

    public function test_specialist_toggle_and_appointment_status(): void
    {
        [$company, $user] = $this->salonTenant();

        Livewire::test(Stylists::class)
            ->call('toggle', $user->id);
        $this->assertTrue((bool) $user->fresh()->is_specialist);

        $service = Product::create([
            'company_id' => $company->id, 'name' => 'Shave', 'sku' => 'SRV-SHAVE',
            'type' => 'service', 'sale_price' => 10, 'duration_minutes' => 20, 'active' => true, 'current_stock' => 0,
        ]);
        $appt = SalonAppointment::create([
            'company_id' => $company->id, 'appointment_number' => 'APT-TEST-1', 'customer_name' => 'Walk In',
            'product_id' => $service->id, 'specialist_id' => $user->id,
            'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2), 'status' => 'scheduled',
        ]);

        Livewire::test(Calendar::class)
            ->call('setStatus', $appt->id, 'completed');

        $this->assertSame('completed', $appt->fresh()->status);
    }
}
