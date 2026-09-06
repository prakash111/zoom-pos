<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\SalonAppointment;
use App\Models\User;
use App\Services\Sdui\SchemaResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServiceCatalogAndFormLabelsTest extends TestCase
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
            'name' => 'Luxe Beauty Lounge',
            'trade_name' => 'Luxe Lounge',
            'slug' => 'luxe-lounge',
            'email' => 'contact@luxelounge.test',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'plan_name' => 'enterprise',
            'expires_at' => now()->addDays(30),
            'licensed_modules' => ['retail', 'service_booking', 'salon'],
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'admin@luxelounge.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $loginResponse = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@luxelounge.test',
            'password' => 'secret123',
        ]);

        $this->token = $loginResponse->json('token');
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_can_create_list_update_and_delete_service_catalog_items(): void
    {
        // 1. Create new service
        $createRes = $this->postJson('/api/tenant/salon/services', [
            'name' => 'Keratin Hair Treatment',
            'price' => 120.00,
            'duration_minutes' => 90,
            'description' => 'Premium smoothing treatment',
        ], $this->authHeaders());

        $createRes->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('service.name', 'Keratin Hair Treatment');

        $serviceId = $createRes->json('service.id');
        $this->assertNotNull($serviceId);

        // Verify Product price and sale_price are synced
        $product = Product::withoutGlobalScope('company')->find($serviceId);
        $this->assertEquals(120.00, (float) $product->price);
        $this->assertEquals(120.00, (float) $product->sale_price);
        $this->assertEquals(90, (int) $product->duration_minutes);
        $this->assertSame('service', $product->type);

        // 2. Query with price and duration_minutes
        $queryProducts = Product::withoutGlobalScope('company')
            ->where('company_id', $this->company->id)
            ->where('type', 'service')
            ->get(['id', 'name', 'price', 'duration_minutes']);

        $this->assertCount(1, $queryProducts);
        $this->assertEquals(120.00, (float) $queryProducts->first()->price);

        // 3. Index endpoint
        $indexRes = $this->getJson('/api/tenant/salon/services', $this->authHeaders());
        $indexRes->assertOk()
            ->assertJsonPath('success', true);
        $this->assertCount(1, $indexRes->json('services'));

        // 4. Edit sheet SDUI endpoint
        $sheetRes = $this->getJson("/api/tenant/salon/services/{$serviceId}/edit-sheet", $this->authHeaders());
        $sheetRes->assertOk();
        $this->assertStringContainsString('Edit Keratin Hair Treatment', $sheetRes->getContent());
        $this->assertStringContainsString('120.00', $sheetRes->getContent());

        // 5. Update service
        $updateRes = $this->postJson("/api/tenant/salon/services/{$serviceId}", [
            'name' => 'Keratin Smoothing Deluxe',
            'price' => 140.00,
            'duration_minutes' => 105,
        ], $this->authHeaders());

        $updateRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('service.name', 'Keratin Smoothing Deluxe');

        $updatedProduct = Product::withoutGlobalScope('company')->find($serviceId);
        $this->assertEquals(140.00, (float) $updatedProduct->price);
        $this->assertEquals(140.00, (float) $updatedProduct->sale_price);
        $this->assertEquals(105, (int) $updatedProduct->duration_minutes);

        // 6. Delete (deactivate) service
        $deleteRes = $this->deleteJson("/api/tenant/salon/services/{$serviceId}", [], $this->authHeaders());
        $deleteRes->assertOk()
            ->assertJsonPath('success', true);

        $this->assertFalse((bool) $updatedProduct->fresh()->active);
    }

    public function test_form_label_customizations_api_and_dynamic_schema_resolver(): void
    {
        // 1. Initially default form labels
        $getRes = $this->getJson('/api/tenant/settings/form-labels?form=service_booking', $this->authHeaders());
        $getRes->assertOk()->assertJsonPath('success', true);
        $this->assertSame('Service & Duration', $this->company->resolveFormFieldLabel('service_booking', 'service', 'Service & Duration'));

        // 2. Update form labels
        $updateRes = $this->postJson('/api/tenant/settings/form-labels', [
            'form' => 'service_booking',
            'labels' => [
                'service' => 'Select Hair Package',
                'specialist' => 'Master Stylist',
                'client_name' => 'Guest Name',
                'client_phone' => 'Mobile Number',
                'advance_deposit' => 'Security Deposit',
                'booking_notes' => 'Special Styling Requests',
            ],
        ], $this->authHeaders());

        $updateRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('labels.service', 'Select Hair Package')
            ->assertJsonPath('labels.specialist', 'Master Stylist');

        $refreshed = $this->company->fresh();
        $this->assertSame('Select Hair Package', $refreshed->resolveFormFieldLabel('service_booking', 'service', 'Service & Duration'));
        $this->assertSame('Master Stylist', $refreshed->resolveFormFieldLabel('service_booking', 'specialist', 'Stylist / Specialist'));
        $this->assertSame('Guest Name', $refreshed->resolveFormFieldLabel('service_booking', 'client_name', 'Client Name'));

        // 3. Verify in dedicated salon booking create SDUI schema and calendar
        $bookingRes = $this->getJson('/api/tenant/views/salon-booking-create', $this->authHeaders());
        $bookingRes->assertOk();
        $content = $bookingRes->getContent();
        $this->assertStringContainsString('Select Hair Package', $content);
        $this->assertStringContainsString('Master Stylist', $content);
        $this->assertStringContainsString('Guest Name', $content);
        $this->assertStringContainsString('Special Styling Requests', $content);

        $calendarRes = $this->getJson('/api/tenant/views/service-calendar', $this->authHeaders());
        $calendarRes->assertOk();
        $this->assertStringContainsString('+ Book New Appointment', $calendarRes->getContent());

        // 4. Verify settings-form-labels SDUI schema view
        $labelsViewRes = $this->getJson('/api/tenant/views/settings-form-labels', $this->authHeaders());
        $labelsViewRes->assertOk();
        $labelsContent = $labelsViewRes->getContent();
        $this->assertStringContainsString('Form Field Customizations', $labelsContent);
        $this->assertStringContainsString('Select Hair Package', $labelsContent);

        // 5. Verify service-orders and service-catalog (Service Catalog & Rates) SDUI schema view
        $catalogViewRes = $this->getJson('/api/tenant/views/service-orders', $this->authHeaders());
        $catalogViewRes->assertOk();
        $catalogContent = $catalogViewRes->getContent();
        $this->assertStringContainsString('Service Catalog & Rates', $catalogContent);
        $this->assertStringContainsString('Add New Service', $catalogContent);

        $catRouteRes = $this->getJson('/api/tenant/views/service-catalog', $this->authHeaders());
        $catRouteRes->assertOk();
        $this->assertStringContainsString('Service Catalog & Rates', $catRouteRes->getContent());

        // 6. Verify dedicated service-create SDUI schema view
        $createViewRes = $this->getJson('/api/tenant/views/service-create', $this->authHeaders());
        $createViewRes->assertOk();
        $createContent = $createViewRes->getContent();
        $this->assertStringContainsString('Add New Service', $createContent);
        $this->assertStringContainsString('Save & Add to Catalog', $createContent);
        $createViewRes->assertJsonPath('schema.title', 'Add New Service');
        $createViewRes->assertJsonPath('schema.components.1.components.0.components.8.action.endpoint', '/api/tenant/salon/services');
    }

    public function test_booking_appointment_with_custom_fields_and_duration_fallback(): void
    {
        $service = Product::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'name' => 'Express Beard Trim',
            'sale_price' => 20.00,
            'price' => 20.00,
            'active' => true,
            'duration_minutes' => null, // Omitted duration should fallback to 30 min
        ]);

        $specialist = User::create([
            'company_id' => $this->company->id,
            'name' => 'Marco Barber',
            'email' => 'marco@luxelounge.test',
            'password' => Hash::make('secret'),
            'role' => 'salesperson',
            'status' => 'approved',
            'is_specialist' => true,
        ]);

        $bookingRes = $this->postJson('/api/tenant/salon/appointments', [
            'service_id' => $service->id,
            'specialist_id' => $specialist->id,
            'customer_name' => 'John Doe',
            'customer_phone' => '555-123-4567',
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '14:00',
            'custom_fields' => [
                'beard_style' => 'Viking Fade',
                'preferred_beverage' => 'Espresso',
            ],
        ], $this->authHeaders());

        $bookingRes->assertCreated()->assertJsonPath('success', true);
        $appointmentId = $bookingRes->json('appointment.id');
        $this->assertNotNull($appointmentId);

        $appointment = SalonAppointment::withoutGlobalScope('company')->find($appointmentId);
        $this->assertNotNull($appointment);
        // Duration fell back to 30 mins: starts at 14:00, ends at 14:30
        $diffMinutes = $appointment->starts_at->diffInMinutes($appointment->ends_at);
        $this->assertEquals(30, $diffMinutes);

        // Custom fields saved properly
        $this->assertEquals('Viking Fade', $appointment->custom_fields['beard_style'] ?? null);
        $this->assertEquals('Espresso', $appointment->custom_fields['preferred_beverage'] ?? null);

        // Calendar renders custom field badges
        $calRes = $this->getJson('/api/tenant/views/service-calendar?date=' . now()->toDateString(), $this->authHeaders());
        $calRes->assertOk();
        $this->assertStringContainsString('Beard Style: Viking Fade', $calRes->getContent());
        $this->assertStringContainsString('Preferred Beverage: Espresso', $calRes->getContent());
    }

    public function test_service_orders_are_blocked_in_salon_mode(): void
    {
        // Salon tenant calling /api/v1/pos/service-orders should receive 403 Forbidden
        $response = $this->getJson('/api/v1/pos/service-orders', $this->authHeaders());
        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Service orders (equipment repair) are not available in Salon & Spa mode.');

        // Attempting to create an equipment service order in salon mode should also fail with 403
        $createOrderRes = $this->postJson('/api/v1/pos/service-orders', [
            'customer_name' => 'John Doe',
            'equipment_name' => 'Hair Dryer Professional',
            'reported_defect' => 'Motor sparking',
        ], $this->authHeaders());
        $createOrderRes->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_salon_navigation_excludes_repair_orders_and_has_single_change_password(): void
    {
        $nav = \App\Services\Navigation\TenantNavRegistry::getEffectiveNavForTenant($this->company);

        // Flatten all item keys across sections
        $allKeys = [];
        foreach ($nav as $section) {
            foreach ($section['items'] ?? [] as $item) {
                $allKeys[] = $item['key'];
            }
        }

        // Equipment service_orders must NOT be present in salon navigation
        $this->assertNotContains('service_orders', $allKeys);
        $this->assertNotContains('repair_tickets', $allKeys);
        $this->assertNotContains('repair_create_ticket', $allKeys);

        // Dedicated service catalog items MUST be present
        $this->assertTrue(in_array('service_catalog_rates', $allKeys, true) || in_array('service_catalog', $allKeys, true));
        $this->assertTrue(in_array('add_new_service', $allKeys, true) || in_array('service_create', $allKeys, true));
        $this->assertTrue(in_array('book_appointment', $allKeys, true) || in_array('book_service_appointment', $allKeys, true));

        // Exactly one change_password item exists across entire navigation with lock_reset icon
        $changePasswordItems = [];
        foreach ($nav as $section) {
            foreach ($section['items'] ?? [] as $item) {
                if (($item['key'] ?? '') === 'change_password') {
                    $changePasswordItems[] = $item;
                }
            }
        }
        $this->assertCount(1, $changePasswordItems);
        $this->assertSame('lock_reset', $changePasswordItems[0]['icon']);
    }
}
