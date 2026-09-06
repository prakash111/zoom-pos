<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\SalonAppointment;
use App\Models\User;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SalonPosTest extends TestCase
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
            'name' => 'Glow & Grace Salon',
            'trade_name' => 'Glow & Grace',
            'slug' => 'glow-and-grace',
            'email' => 'admin@glowandgrace.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'plan_name' => 'enterprise',
            'expires_at' => now()->addDays(30),
            'licensed_modules' => ['retail', 'service_booking'],
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'admin@glowandgrace.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $loginResponse = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@glowandgrace.com',
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

    private function createService(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'company_id' => $this->company->id,
            'name' => 'Haircut & Styling',
            'code' => 'SVC-HAIRCUT',
            'sale_price' => 25.00,
            'current_stock' => 999,
            'active' => true,
            'duration_minutes' => 30,
        ], $overrides));
    }

    private function createSpecialist(array $overrides = []): User
    {
        return User::create(array_merge([
            'company_id' => $this->company->id,
            'name' => 'Jane Stylist',
            'login' => 'jane.stylist',
            'email' => 'jane.stylist@example.test',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_SALESPERSON,
            'status' => 'approved',
            'is_specialist' => true,
        ], $overrides));
    }

    public function test_salon_pos_returns_the_universal_pos_screen_contract(): void
    {
        $this->createService();

        $response = $this->getJson('/api/tenant/views/salon-pos', $this->authHeaders());
        $response->assertOk()->assertJsonPath('success', true);

        $schema = $response->json('schema');
        $this->assertSame('pos_screen', $schema['type']);
        $this->assertSame('/api/tenant/salon/checkout-sheet', $schema['cart_bar']['checkout_sheet_endpoint']);
        $this->assertNotEmpty($schema['catalog']['items']);

        $item = $schema['catalog']['items'][0];
        $this->assertSame('30 mins', $item['badge']['text']);
        $this->assertSame('add_to_cart', $item['on_tap']['type']);

        $errors = app(SchemaValidator::class)->validate($schema);
        $this->assertEmpty($errors);
    }

    public function test_salon_pos_shows_stock_badge_for_non_service_products(): void
    {
        $this->createService(['name' => 'Shampoo Retail Bottle', 'code' => 'RETAIL-SHAMPOO', 'duration_minutes' => null, 'current_stock' => 12]);

        $response = $this->getJson('/api/tenant/views/salon-pos', $this->authHeaders());
        $item = $response->json('schema.catalog.items.0');

        $this->assertSame('Stock: 12', $item['badge']['text']);
    }

    public function test_service_stylists_view_shows_a_real_roster_and_reflects_toggles(): void
    {
        $specialist = $this->createSpecialist();
        $nonSpecialist = $this->createSpecialist([
            'name' => 'Bob Receptionist',
            'login' => 'bob.reception',
            'email' => 'bob.reception@example.test',
            'is_specialist' => false,
        ]);

        $response = $this->getJson('/api/tenant/views/service-stylists', $this->authHeaders());
        $response->assertOk();
        $json = json_encode($response->json());

        $this->assertStringContainsString('Jane Stylist', $json);
        $this->assertStringContainsString('Bob Receptionist', $json);
        $this->assertStringContainsString('Remove Specialist', $json);
        $this->assertStringContainsString('Mark as Specialist', $json);
    }

    public function test_specialists_toggle_flips_the_flag_and_audit_logs(): void
    {
        $specialist = $this->createSpecialist(['is_specialist' => false]);

        $response = $this->postJson("/api/tenant/salon/specialists/{$specialist->id}/toggle", [], $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertTrue((bool) $response->json('user.is_specialist'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'salon.specialist_toggled']);

        $again = $this->postJson("/api/tenant/salon/specialists/{$specialist->id}/toggle", [], $this->authHeaders());
        $this->assertFalse((bool) $again->json('user.is_specialist'));
    }

    public function test_checkout_sheet_includes_specialist_dropdown_only_when_specialists_exist(): void
    {
        $withoutSpecialists = $this->getJson('/api/tenant/salon/checkout-sheet?cart=%5B%5D', $this->authHeaders());
        $withoutSpecialists->assertOk();
        $this->assertStringNotContainsString('specialist_id', json_encode($withoutSpecialists->json()));

        $this->createSpecialist();
        $this->createService();
        $cart = urlencode(json_encode([['title' => 'Haircut & Styling', 'qty' => 1, 'price' => 25.00]]));
        $withSpecialists = $this->getJson("/api/tenant/salon/checkout-sheet?cart={$cart}", $this->authHeaders());
        $withSpecialists->assertOk();
        $json = json_encode($withSpecialists->json());
        $this->assertStringContainsString('specialist_id', $json);
        $this->assertStringContainsString('Jane Stylist', $json);
        $this->assertStringContainsString('line_specialist_0', $json);
        $this->assertSame('native_pos_checkout_drawer', $withSpecialists->json('schema.presentation'));
        $this->assertSame(['cash', 'card', 'transfer'], array_column($withSpecialists->json('schema.payment_methods'), 'value'));
        $this->assertCount(5, $withSpecialists->json('schema.quick_cash.suggestions'));

        $errors = app(SchemaValidator::class)->validate($withSpecialists->json('schema'));
        $this->assertEmpty($errors);
    }

    public function test_pos_checkout_creates_a_real_sale_and_records_the_specialist_in_notes(): void
    {
        $service = $this->createService();
        $specialist = $this->createSpecialist();

        $response = $this->postJson('/api/tenant/salon/pos-checkout', [
            'items' => [['product_id' => $service->id, 'quantity' => 1]],
            'specialist_id' => $specialist->id,
            'payment_method' => 'cash',
            'customer_name' => 'Alice Client',
        ], $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(25.0, (float) $response->json('sale.total'));
        $this->assertStringContainsString('Jane Stylist', $response->json('sale.notes'));

        $service->refresh();
        $this->assertSame(998.0, (float) $service->current_stock);
    }

    public function test_a_salon_sale_appears_in_the_central_sales_ledger_with_dispatch_actions(): void
    {
        $service = $this->createService();

        $checkout = $this->postJson('/api/tenant/salon/pos-checkout', [
            'items' => [['product_id' => $service->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'customer_name' => 'Alice Client',
        ], $this->authHeaders());
        $saleId = $checkout->json('sale.id');

        $response = $this->getJson('/api/tenant/views/sales', $this->authHeaders());
        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString("\\/api\\/tenant\\/sales\\/{$saleId}\\/send-invoice", $content);
        $this->assertStringContainsString('"channel":"whatsapp"', $content);
        $this->assertStringContainsString('"channel":"sms"', $content);
        $this->assertStringContainsString('"channel":"email"', $content);
    }

    public function test_pos_checkout_accepts_two_fixed_split_payment_rows(): void
    {
        $service = $this->createService();

        $response = $this->postJson('/api/tenant/salon/pos-checkout', [
            'items' => [['product_id' => $service->id, 'quantity' => 1]],
            'payment_method' => 'split',
            'payment_1_method' => 'cash',
            'payment_1_amount' => 15,
            'payment_2_method' => 'card',
            'payment_2_amount' => 10,
        ], $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
        $saleId = $response->json('sale.id');
        $this->assertDatabaseHas('order_payments', ['sale_id' => $saleId, 'payment_method' => 'cash', 'amount' => 15]);
        $this->assertDatabaseHas('order_payments', ['sale_id' => $saleId, 'payment_method' => 'card', 'amount' => 10]);
    }

    public function test_calendar_books_specialist_slots_and_rejects_overlap(): void
    {
        $service = $this->createService(['duration_minutes' => 60]);
        $specialist = $this->createSpecialist();
        $date = now()->addDay()->toDateString();

        $booking = $this->postJson('/api/tenant/salon/appointments', [
            'service_id' => $service->id,
            'specialist_id' => $specialist->id,
            'customer_name' => 'Maya Client',
            'customer_phone' => '+15550001',
            'appointment_date' => $date,
            'appointment_time' => '10:00',
        ], $this->authHeaders());
        $booking->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.specialist_name', 'Jane Stylist');

        $this->postJson('/api/tenant/salon/appointments', [
            'service_id' => $service->id,
            'specialist_id' => $specialist->id,
            'customer_name' => 'Overlapping Client',
            'appointment_date' => $date,
            'appointment_time' => '10:30',
        ], $this->authHeaders())->assertUnprocessable();

        $this->getJson("/api/tenant/salon/appointments?date={$date}", $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'appointments');
        $calendar = $this->getJson("/api/tenant/views/service-calendar?date={$date}", $this->authHeaders());
        $calendar->assertOk();
        $this->assertStringContainsString('Maya Client', json_encode($calendar->json('schema')));
        $this->assertDatabaseCount('salon_appointments', 1);
    }

    public function test_checkout_snapshots_per_service_stylist_commission_and_completes_booking(): void
    {
        $service = $this->createService();
        $specialist = $this->createSpecialist(['commission_rate' => 10, 'commission_type' => 'percentage']);
        $appointment = SalonAppointment::create([
            'company_id' => $this->company->id,
            'appointment_number' => 'APT-TEST-001',
            'customer_name' => 'Booked Client',
            'product_id' => $service->id,
            'specialist_id' => $specialist->id,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addMinutes(90),
            'status' => 'checked_in',
        ]);

        $response = $this->postJson('/api/tenant/salon/pos-checkout', [
            'items' => [['product_id' => $service->id, 'quantity' => 1]],
            'line_specialist_0' => $specialist->id,
            'appointment_id' => $appointment->id,
            'payment_method' => 'cash',
            'quick_cash_tendered' => 30,
        ], $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame($specialist->id, $response->json('sale.items.0.specialist_id'));
        $this->assertSame(2.5, (float) $response->json('sale.items.0.commission_amount'));
        $this->assertSame('completed', $appointment->fresh()->status);
        $this->assertDatabaseHas('order_payments', [
            'sale_id' => $response->json('sale.id'),
            'tendered' => 30,
            'change_returned' => 5,
        ]);
    }

    public function test_appointment_checkout_injects_items_and_uses_the_linked_crm_customer(): void
    {
        $service = $this->createService([
            'name' => 'Beard Trim & Hot Towel',
            'sale_price' => 15.00,
        ]);
        $specialist = $this->createSpecialist();
        $date = now()->addDays(3)->toDateString();

        $booking = $this->postJson('/api/tenant/salon/appointments', [
            'service_id' => $service->id,
            'specialist_id' => $specialist->id,
            'customer_name' => 'Timeline Client',
            'customer_phone' => '+1 555 000 9911',
            'appointment_date' => $date,
            'appointment_time' => '09:00',
            'advance_paid' => 500,
        ], $this->authHeaders());

        $booking->assertCreated();
        $appointmentId = $booking->json('appointment.id');
        $customerId = $booking->json('appointment.customer_id');
        $this->assertNotNull($customerId);
        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'company_id' => $this->company->id,
            'name' => 'Timeline Client',
            'phone' => '+1 555 000 9911',
        ]);

        $sheet = $this->getJson("/api/tenant/salon/checkout-sheet?appointment_id={$appointmentId}", $this->authHeaders());
        $sheet->assertOk()
            ->assertJsonPath('schema.booking_context.customer_id', $customerId)
            ->assertJsonPath('schema.booking_context.customer_name', 'Timeline Client')
            ->assertJsonPath('schema.order_summary.line_item_count', 1)
            ->assertJsonPath('schema.order_summary.grand_total', 0);

        $findButton = function (array $nodes, string $label) use (&$findButton): ?array {
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }
                if (($node['label'] ?? null) === $label) {
                    return $node;
                }
                $children = $node['components'] ?? $node['children'] ?? null;
                if (is_array($children) && ($found = $findButton($children, $label))) {
                    return $found;
                }
            }

            return null;
        };
        $completeSale = $findButton($sheet->json('schema.components'), 'Complete Sale · $0.00');
        $this->assertNotNull($completeSale);
        $this->assertSame($appointmentId, $completeSale['action']['payload']['appointment_id']);
        $this->assertSame($service->id, $completeSale['action']['payload']['items'][0]['product_id']);
        $this->assertSame('service', $completeSale['action']['payload']['items'][0]['type']);
        $this->assertSame($specialist->id, $completeSale['action']['payload']['items'][0]['staff_id']);
        $this->assertStringContainsString('CRM LINKED', $sheet->getContent());

        // Deliberately omit items/customer fields to reproduce the timeline
        // serializer path. The controller must resolve all of them from the
        // tenant-scoped appointment before validating.
        $checkout = $this->postJson('/api/tenant/salon/pos-checkout', [
            'appointment_id' => $appointmentId,
            'payment_method' => 'cash',
        ], $this->authHeaders());

        $checkout->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('sale.customer_id', $customerId)
            ->assertJsonPath('sale.customer_name', 'Timeline Client')
            ->assertJsonPath('sale.items.0.product_id', $service->id)
            ->assertJsonPath('sale.items.0.name', 'Beard Trim & Hot Towel')
            ->assertJsonPath('sale.items.0.specialist_id', $specialist->id)
            ->assertJsonPath('post_sale_sheet.action', 'show_post_sale_sheet');
        $this->assertEquals(15, (float) $checkout->json('sale.paid_amount'));
        $this->assertEquals(0, (float) $checkout->json('sale.due_amount'));
    }

    public function test_salon_checkout_accepts_core_pos_item_aliases(): void
    {
        $service = $this->createService();
        $specialist = $this->createSpecialist();

        $response = $this->postJson('/api/tenant/salon/pos-checkout', [
            'items' => [[
                'id' => $service->id,
                'type' => 'service',
                'name' => $service->name,
                'price' => 25,
                'quantity' => 1,
                'staff_id' => $specialist->id,
            ]],
            'payment_method' => 'cash',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('sale.items.0.product_id', $service->id)
            ->assertJsonPath('sale.items.0.specialist_id', $specialist->id);
    }

    public function test_calendar_timeline_uses_fixed_flexible_and_stacked_status_columns(): void
    {
        $service = $this->createService(['name' => 'Beard Trim & Hot Towel']);
        $specialist = $this->createSpecialist(['name' => 'Elena Rostova']);
        $date = now()->addDays(4)->toDateString();
        SalonAppointment::create([
            'company_id' => $this->company->id,
            'appointment_number' => 'APT-LAYOUT-001',
            'customer_name' => 'Layout Client',
            'product_id' => $service->id,
            'specialist_id' => $specialist->id,
            'starts_at' => now()->addDays(4)->setTime(9, 0),
            'ends_at' => now()->addDays(4)->setTime(9, 30),
            'status' => 'in_progress',
            'advance_paid' => 500,
        ]);

        $response = $this->getJson("/api/tenant/views/service-calendar?date={$date}", $this->authHeaders());
        $response->assertOk();

        $timelineRow = $response->json('schema.components.2.components.1.components.0.components.0');
        $this->assertSame('row', $timelineRow['type']);
        $this->assertSame(64, $timelineRow['components'][0]['width']);
        $this->assertFalse($timelineRow['components'][0]['flexible']);
        $this->assertTrue($timelineRow['components'][1]['expanded']);
        $this->assertFalse($timelineRow['components'][2]['flexible']);
        $this->assertSame('end', $timelineRow['components'][2]['cross_axis_alignment']);
        $this->assertSame(4, $timelineRow['components'][2]['spacing']);
        $this->assertSame('IN CHAIR', $timelineRow['components'][2]['components'][0]['label']);
        $this->assertSame(124, $timelineRow['components'][2]['components'][0]['max_width']);
        $this->assertSame('Advance: $500.00', $timelineRow['components'][2]['components'][1]['label']);
        $this->assertSame(124, $timelineRow['components'][2]['components'][1]['max_width']);
    }

    public function test_calendar_booking_with_advance_deposit_and_settlement_parity(): void
    {
        $service = $this->createService(['sale_price' => 50.00]);
        $specialist = $this->createSpecialist();
        $date = now()->addDays(2)->toDateString();

        // 1. Book appointment with advance deposit
        $bookRes = $this->postJson('/api/tenant/salon/appointments', [
            'service_id' => $service->id,
            'specialist_id' => $specialist->id,
            'customer_name' => 'Advance Deposit Client',
            'customer_phone' => '5551234567',
            'appointment_date' => $date,
            'appointment_time' => '11:00',
            'advance_paid' => 15.00,
            'deposit_payment_method' => 'card',
        ], $this->authHeaders());

        $bookRes->assertCreated()->assertJsonPath('success', true);
        $appointmentId = $bookRes->json('appointment.id');
        $this->assertEquals(15.00, (float) $bookRes->json('appointment.advance_paid'));

        // 2. Check service-calendar view displays advance badge and checkout action
        $calendarRes = $this->getJson("/api/tenant/views/service-calendar?date={$date}", $this->authHeaders());
        $calendarRes->assertOk();
        $calContent = $calendarRes->getContent();
        $this->assertStringContainsString('Advance: $15.00', $calContent);
        $this->assertStringContainsString('Settle', $calContent);
        $this->assertStringContainsString('Checkout', $calContent);
        $this->assertStringContainsString('advance_paid', $calContent);
        $this->assertStringContainsString('deposit_payment_method', $calContent);

        // 3. Checkout with appointment_id: 50 total - 15 advance = 35 balance due
        $checkoutRes = $this->postJson('/api/tenant/salon/checkout', [
            'items' => [['product_id' => $service->id, 'quantity' => 1]],
            'appointment_id' => $appointmentId,
            'payment_method' => 'cash',
            'tendered' => 35.00,
        ], $this->authHeaders());

        $checkoutRes->assertOk()->assertJsonPath('success', true);
        // No auto-launch keys — settlement stays inside the app.
        $this->assertNull($checkoutRes->json('url'));
        $this->assertNull($checkoutRes->json('print_url'));
        $this->assertNull($checkoutRes->json('whatsapp_url'));
        $this->assertNull($checkoutRes->json('receipt_pdf_url'));
        $this->assertSame('show_post_sale_sheet', $checkoutRes->json('post_sale_sheet.action'));
        $this->assertStringContainsString('/pdf-stream', (string) $checkoutRes->json('post_sale_sheet.data.pdf_endpoint'));
        $this->assertEquals(50.00, (float) $checkoutRes->json('sale.total'));
        $this->assertEquals(50.00, (float) $checkoutRes->json('sale.paid_amount'));
        $this->assertEquals(0.00, (float) $checkoutRes->json('sale.due_amount'));

        // Verify order payment records: advance deposit of 15 + cash payment of 35
        $saleId = $checkoutRes->json('sale.id');
        $this->assertDatabaseHas('order_payments', [
            'sale_id' => $saleId,
            'payment_method' => 'advance_deposit',
            'amount' => 15.00,
        ]);
        $this->assertDatabaseHas('order_payments', [
            'sale_id' => $saleId,
            'payment_method' => 'cash',
            'amount' => 35.00,
        ]);
    }
}
