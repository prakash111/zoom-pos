<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\KitchenTicket;
use App\Models\PharmacyPrescription;
use App\Models\Plan;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\SalonAppointment;
use App\Models\TenantApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UnifiedDocumentDispatchApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private Customer $customer;
    private string $token = 'zk_live_unified_dispatch_test';

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'unified-dispatch-plan',
            'display_name' => 'Unified Dispatch Plan',
            'price' => 49,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'features' => ['pos' => true, 'quotes' => true, 'repairs' => true, 'pharmacy' => true, 'restaurant' => true, 'salon' => true],
            'limits' => ['products' => 100, 'users' => 5],
            'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Omnichannel Flagship Store',
            'trade_name' => 'Omnichannel Store',
            'slug' => 'omnichannel-store',
            'email' => 'owner@omnichannel.test',
            'country' => 'IN',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'tax_id_label' => 'GSTIN',
            'tax_id' => '27AAAAA0000A1Z5',
            'plan_name' => 'unified-dispatch-plan',
            'expires_at' => now()->addMonth(),
            'licensed_modules' => ['retail', 'restaurant', 'salon', 'pharmacy', 'repair'],
        ]);

        $this->user = User::create([
            'company_id' => $this->company->id,
            'name' => 'Omnichannel Admin',
            'login' => 'omni_admin',
            'email' => 'admin@omnichannel.test',
            'password' => Hash::make('Secret123!'),
            'role' => 'admin',
            'active' => true,
        ]);

        $this->customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Dev Sharma',
            'phone' => '+91 98765 11111',
            'email' => 'dev@example.test',
        ]);

        TenantApiKey::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Dispatch test terminal',
            'token' => $this->token,
            'permissions' => ['*'],
            'active' => true,
        ]);
    }

    public function test_quotation_dispatch_succeeds_with_platform_fallback(): void
    {
        $sale = Sale::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'sale_number' => 'QUO-8801',
            'operation_type' => 'quotation',
            'items' => [['name' => 'Sample Item', 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]],
            'total' => 100,
            'tax_amount' => 0,
            'paid_amount' => 0,
            'due_amount' => 100,
            'payment_status' => 'unpaid',
            'status' => 'draft',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/documents/dispatch', [
                'document_type' => 'quotation',
                'document_id' => $sale->id,
                'channels' => ['whatsapp', 'email'],
                'phone' => '+91 98765 11111',
                'email' => 'dev@example.test',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document_code', '#QUO-8801');

        $this->assertNotNull($response->json('whatsapp_url'));
        $this->assertTrue($response->json('results.whatsapp.success'));
        $this->assertTrue($response->json('results.email.success'));
    }

    public function test_restaurant_kot_dispatch_succeeds_with_platform_fallback(): void
    {
        $kot = KitchenTicket::create([
            'company_id' => $this->company->id,
            'kot_number' => 'KOT-2026-05',
            'table_name' => 'T-04',
            'service_type' => 'dine_in',
            'status' => KitchenTicket::STATUS_PENDING,
            'items' => [['name' => 'Truffle Pasta', 'quantity' => 2]],
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/documents/dispatch', [
                'document_type' => 'kot',
                'document_id' => $kot->id,
                'send_whatsapp' => true,
                'send_email' => false,
                'phone' => '+91 98765 22222',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document_code', '#KOT-2026-05');

        $this->assertTrue($response->json('results.whatsapp.success'));
    }

    public function test_repair_job_sheet_dispatch_succeeds_with_platform_fallback(): void
    {
        $repair = RepairTicket::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'ticket_number' => 'REP-9901',
            'customer_name' => 'Sarah Connor',
            'customer_phone' => '+91 98765 33333',
            'brand' => 'Apple',
            'model' => 'MacBook Pro M3',
            'defect' => 'Screen flicker',
            'status' => RepairTicket::STATUS_RECEIVED,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/documents/dispatch', [
                'document_type' => 'job_sheet',
                'document_id' => $repair->id,
                'channels' => ['whatsapp'],
                'phone' => '+91 98765 33333',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document_code', '#REP-9901');

        $this->assertTrue($response->json('results.whatsapp.success'));
    }

    public function test_pharmacy_prescription_dispatch_succeeds_with_platform_fallback(): void
    {
        $rx = PharmacyPrescription::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'prescription_number' => 'RX-4001',
            'prescription_date' => now()->toDateString(),
            'patient_name' => 'John Doe',
            'patient_phone' => '+91 98765 44444',
            'doctor_name' => 'Dr. Gupta',
            'status' => 'dispensed',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/documents/dispatch', [
                'document_type' => 'prescription',
                'document_id' => $rx->id,
                'channels' => ['whatsapp'],
                'phone' => '+91 98765 44444',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document_code', '#RX-4001');

        $this->assertTrue($response->json('results.whatsapp.success'));
    }

    public function test_salon_appointment_dispatch_succeeds_with_platform_fallback(): void
    {
        $apt = SalonAppointment::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'appointment_number' => 'APT-7701',
            'customer_name' => 'Elena Gilbert',
            'customer_phone' => '+91 98765 55555',
            'status' => 'confirmed',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/documents/dispatch', [
                'document_type' => 'appointment',
                'document_id' => $apt->id,
                'channels' => ['whatsapp', 'email'],
                'phone' => '+91 98765 55555',
                'email' => 'elena@example.test',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document_code', '#APT-7701');

        $this->assertTrue($response->json('results.whatsapp.success'));
        $this->assertTrue($response->json('results.email.success'));
    }

    public function test_get_dispatch_options_endpoint(): void
    {
        $kot = KitchenTicket::create([
            'company_id' => $this->company->id,
            'kot_number' => 'KOT-2026-06',
            'table_name' => 'T-05',
            'service_type' => 'dine_in',
            'status' => KitchenTicket::STATUS_PENDING,
            'items' => [['name' => 'Soup', 'quantity' => 1]],
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/documents/kot/{$kot->id}/dispatch-options")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document_code', '#KOT-2026-06')
            ->assertJsonPath('channels.whatsapp.available', true)
            ->assertJsonPath('channels.email.available', true);
    }

    public function test_all_verticals_preview_modal_return_unified_sdui_schema(): void
    {
        $repair = RepairTicket::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'ticket_number' => 'REP-9902',
            'customer_name' => 'Alice Martin',
            'customer_phone' => '+91 98765 66666',
            'brand' => 'Dell',
            'model' => 'XPS 15',
            'problem_reported' => 'No power',
            'status' => RepairTicket::STATUS_RECEIVED,
        ]);

        $rx = PharmacyPrescription::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'prescription_number' => 'RX-4002',
            'prescription_date' => now()->toDateString(),
            'patient_name' => 'Bob Builder',
            'patient_phone' => '+91 98765 77777',
            'doctor_name' => 'Dr. Banner',
            'status' => 'pending',
        ]);

        $apt = SalonAppointment::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'appointment_number' => 'APT-7702',
            'customer_name' => 'Carol Danvers',
            'customer_phone' => '+91 98765 88888',
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        foreach ([
            ['repair', $repair->id, '#REP-9902'],
            ['prescription', $rx->id, '#RX-4002'],
            ['appointment', $apt->id, '#APT-7702'],
        ] as [$type, $docId, $expectedCode]) {
            $this->withToken($this->token)
                ->getJson("/api/v1/tenant/documents/{$type}/{$docId}/preview-modal?format=thermal_80mm")
                ->assertOk()
                ->assertJsonPath('schema.type', 'bottom_sheet')
                ->assertJsonPath('schema.background_color', '#0B1120')
                ->assertJsonPath('schema.components.0.type', 'segmented_tabs')
                ->assertJsonPath('schema.components.0.active_value', 'thermal_80mm')
                ->assertJsonPath('schema.components.1.type', 'document_preview_card');
        }
    }

    public function test_dispatch_options_automatically_fetches_enabled_channels_from_settings(): void
    {
        // Enable SMS and Webhook in integration settings (TenantNotificationGateway)
        \App\Models\TenantNotificationGateway::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'channel' => \App\Models\TenantNotificationGateway::CHANNEL_SMS,
            'provider' => \App\Models\TenantNotificationGateway::PROVIDER_GENERIC_HTTP,
            'is_enabled' => true,
            'credentials' => [
                'url' => 'https://sms.example.test/send?to={phone}&msg={message}',
                'method' => 'GET',
            ],
        ]);

        \App\Models\TenantNotificationGateway::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'channel' => \App\Models\TenantNotificationGateway::CHANNEL_WEBHOOK,
            'provider' => \App\Models\TenantNotificationGateway::PROVIDER_WEBHOOK,
            'is_enabled' => true,
            'credentials' => [
                'url' => 'https://webhook.site/dispatch-listener',
                'method' => 'POST',
            ],
        ]);

        $sale = Sale::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'sale_number' => 'INV-2026-99',
            'operation_type' => 'sale',
            'items' => [['name' => 'Widget Pro', 'quantity' => 1, 'unit_price' => 250, 'line_total' => 250]],
            'total' => 250,
            'tax_amount' => 0,
            'paid_amount' => 250,
            'due_amount' => 0,
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);

        // 1. Check dispatch-options endpoint returns all enabled channels
        $response = $this->withToken($this->token)
            ->getJson("/api/v1/documents/sale/{$sale->id}/dispatch-options")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('channels.whatsapp.available', true)
            ->assertJsonPath('channels.email.available', true)
            ->assertJsonPath('channels.sms.available', true)
            ->assertJsonPath('channels.webhook.available', true);

        $enabledChannelKeys = collect($response->json('enabled_channels'))->pluck('channel')->all();
        $this->assertContains('whatsapp', $enabledChannelKeys);
        $this->assertContains('sms', $enabledChannelKeys);
        $this->assertContains('email', $enabledChannelKeys);
        $this->assertContains('webhook', $enabledChannelKeys);

        // 2. Check general enabled channels endpoint
        $channelsResponse = $this->withToken($this->token)
            ->getJson('/api/v1/documents/channels')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('channels.sms.available', true)
            ->assertJsonPath('channels.webhook.available', true);

        // 3. Dispatch via all enabled channels (SMS, Webhook, WhatsApp, Email)
        $dispatchResponse = $this->withToken($this->token)
            ->postJson('/api/v1/documents/dispatch', [
                'document_type' => 'sale',
                'document_id' => $sale->id,
                'channels' => ['whatsapp', 'sms', 'email', 'webhook'],
                'phone' => '+91 98765 11111',
                'email' => 'dev@example.test',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue($dispatchResponse->json('results.whatsapp.success'));
        $this->assertTrue($dispatchResponse->json('results.sms.success'));
        $this->assertTrue($dispatchResponse->json('results.email.success'));
        $this->assertTrue($dispatchResponse->json('results.webhook.success'));
    }
}
