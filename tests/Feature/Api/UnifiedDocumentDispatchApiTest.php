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
use App\Models\TenantNotificationGateway;
use App\Models\MessageQueue;
use App\Services\Notifications\TenantNotificationDispatcherService;
use App\Services\Repair\RepairNotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
        Mail::fake();
        Http::fake(fn () => Http::response(['success' => true], config('testing.dispatch_http_status', 200)));
        config(['mail.mailers.smtp.host' => null]);

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
            ->assertJsonPath('device_channels.whatsapp.available', true)
            ->assertJsonPath('device_channels.email.available', true);
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

        // Prescription and appointment vertical preview modals return preview card
        foreach ([
            ['prescription', $rx->id, '#RX-4002'],
            ['appointment', $apt->id, '#APT-7702'],
        ] as [$type, $docId, $expectedCode]) {
            $response = $this->withToken($this->token)
                ->getJson("/api/v1/tenant/documents/{$type}/{$docId}/preview-modal?format=thermal_80mm")
                ->assertOk()
                ->assertJsonPath('schema.type', 'bottom_sheet')
                ->assertJsonPath('schema.background_color', '#0B1120')
                ->assertJsonPath('schema.components.0.type', 'segmented_tabs')
                ->assertJsonPath('schema.components.0.active_value', 'thermal_80mm')
                ->assertJsonPath('schema.components.1.type', 'document_preview_card');
            $this->assertSame(['thermal_print', 'whatsapp', 'email', 'sms', 'pdf_preview'], collect($response->json('schema.components'))->filter(fn ($item) => isset($item['channel']))->pluck('channel')->all());
        }

        // Repair ticket returns unified dispatch with pre-bound customer data and NO document_preview_card
        $repairModal = $this->withToken($this->token)
            ->getJson("/api/v1/tenant/documents/repair/{$repair->id}/preview-modal")
            ->assertOk()
            ->assertJsonPath('schema.type', 'bottom_sheet')
            ->assertJsonPath('schema.background_color', '#0B1120')
            ->assertJsonPath('schema.header.title', '#REP-9902');

        $componentTypes = collect($repairModal->json('schema.components'))->pluck('type')->all();
        $this->assertNotContains('document_preview_card', $componentTypes, 'Repair tickets must omit document_preview_card.');
        $this->assertNotContains('segmented_tabs', $componentTypes, 'Repair tickets do not require format segmented_tabs.');
        $this->assertContains('section_header', $componentTypes);
        $this->assertContains('list_tile', $componentTypes);

        // Verify customer user data is pre-bound to channel actions
        $whatsappTile = collect($repairModal->json('schema.components'))->firstWhere('title', 'Open WhatsApp App');
        $this->assertNotNull($whatsappTile);
        $this->assertSame('OPEN_URL', $whatsappTile['action']['type']);
        $this->assertArrayNotHasKey('endpoint', $whatsappTile['action']);
        $this->assertStringStartsWith('whatsapp://send?phone=919876566666&text=', $whatsappTile['action']['url']);
        $this->assertStringContainsString('Alice Martin', rawurldecode($whatsappTile['action']['url']));
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
            ->assertJsonPath('device_channels.whatsapp.available', true)
            ->assertJsonPath('device_channels.email.available', true)
            ->assertJsonPath('channels.sms.available', true)
            ->assertJsonPath('channels.webhook.available', true);

        $enabledChannelKeys = collect($response->json('enabled_channels'))->pluck('channel')->all();
        $this->assertNotContains('whatsapp', $enabledChannelKeys);
        $this->assertContains('sms', $enabledChannelKeys);
        $this->assertNotContains('email', $enabledChannelKeys);
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

    public function test_repair_ticket_unified_dispatch_binds_customer_user_data_automatically(): void
    {
        // 1. Create a customer with phone and email
        $customer = \App\Models\Customer::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'name' => 'Diana Prince',
            'phone' => '+91 98765 99999',
            'email' => 'diana@example.test',
            'is_demo' => false,
        ]);

        // 2. Create a repair ticket for this customer
        $ticket = RepairTicket::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'ticket_number' => 'REP-2026-DIANA',
            'brand' => 'Apple',
            'model' => 'MacBook Pro M3',
            'problem_reported' => 'Battery replacement required',
            'status' => RepairTicket::STATUS_DIAGNOSING,
            'total_amount' => 4500.00,
        ]);

        // 3. Verify dispatch-options endpoint pre-binds customer data and targets
        $optionsResponse = $this->withToken($this->token)
            ->getJson("/api/v1/documents/repair/{$ticket->id}/dispatch-options")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('customer.name', 'Diana Prince')
            ->assertJsonPath('customer.phone', '+91 98765 99999')
            ->assertJsonPath('customer.email', 'diana@example.test');

        $channels = $optionsResponse->json('device_channels');
        $this->assertSame('+91 98765 99999', $channels['whatsapp']['target']);
        $this->assertSame('diana@example.test', $channels['email']['target']);

        // 4. Verify share-sheet SDUI endpoint returns unified dispatch without document_preview_card
        $shareSheetResponse = $this->withToken($this->token)
            ->getJson("/api/tenant/repair/tickets/{$ticket->id}/share-sheet")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('header.title', '#REP-2026-DIANA');

        $components = $shareSheetResponse->json('components');
        $types = collect($components)->pluck('type')->all();
        $this->assertNotContains('document_preview_card', $types, 'Ticket dispatch sheet must not contain document_preview_card.');
        $this->assertNotContains('segmented_tabs', $types, 'Ticket dispatch sheet must not contain format segmented_tabs.');

        // 5. Dispatch via server-bound customer data without explicitly passing phone/email in request
        $dispatchRes = $this->withToken($this->token)
            ->postJson('/api/v1/documents/dispatch', [
                'document_type' => 'repair',
                'document_id' => $ticket->id,
                'channels' => ['whatsapp', 'email', 'sms'],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue($dispatchRes->json('results.whatsapp.success'));
        $this->assertTrue($dispatchRes->json('results.email.success'));
        $this->assertTrue($dispatchRes->json('results.sms.success'));
    }
    public function test_invoice_and_quotation_device_fallbacks_preserve_messages_and_links(): void
    {
        foreach (['sale', 'quotation'] as $operation) {
            $sale = Sale::create([
                'company_id' => $this->company->id, 'customer_id' => $this->customer->id,
                'sale_number' => $operation === 'sale' ? 'INV-DEVICE-1' : 'QUO-DEVICE-1',
                'operation_type' => $operation, 'status' => 'draft', 'total' => 60,
                'items' => [['name' => 'Service', 'quantity' => 1, 'price' => 60]],
            ]);
            foreach (['whatsapp' => 'whatsapp://send?phone=919876511111&text=', 'sms' => 'sms:', 'email' => 'mailto:'] as $channel => $prefix) {
                $response = $this->withToken($this->token)->postJson('/api/v1/tenant/dispatch/send', [
                    'type' => $operation === 'sale' ? 'invoice' : 'quotation', 'id' => $sale->id, 'channel' => $channel,
                ])->assertOk()->assertJsonPath('status', 'manual_link')->assertJsonPath('action.type', 'OPEN_URL');
                $this->assertStringStartsWith($prefix, $response->json('url'));
                $message = rawurldecode($response->json('url'));
                $this->assertStringContainsString('Dev Sharma', $message);
                $this->assertStringContainsString($sale->sale_number, $message);
                $this->assertStringContainsString(route($operation === 'quotation' ? 'quotes.public' : 'sales.public', $sale->sale_number), $message);
                $this->assertSame('draft', $sale->fresh()->status);

                $multi = $this->withToken($this->token)->postJson('/api/v1/documents/dispatch', [
                    'document_type' => $operation === 'sale' ? 'invoice' : 'quotation', 'document_id' => $sale->id, 'channels' => [$channel],
                ])->assertOk()->assertJsonPath('status', 'manual_link')->assertJsonCount(1, 'device_actions');
                $this->assertStringStartsWith($prefix, $multi->json('url'));
                $legacy = $this->withToken($this->token)->postJson($operation === 'sale'
                    ? "/api/tenant/sales/{$sale->id}/send-invoice"
                    : "/api/v1/tenant/quotations/{$sale->id}/dispatch", ['channel' => $channel])
                    ->assertOk()->assertJsonPath('status', 'manual_link');
                $this->assertStringStartsWith($prefix, $legacy->json('url'));
                $pos = $this->withToken($this->token)->postJson('/api/v1/pos/send-delivery', [
                    'type' => $channel, 'document_type' => $operation === 'sale' ? 'invoice' : 'quotation',
                    'document_id' => (string) $sale->id,
                    'recipient' => $channel === 'email' ? $this->customer->email : $this->customer->phone,
                ])->assertOk()->assertJsonPath('status', 'manual_link');
                $this->assertStringStartsWith($prefix, $pos->json('url') ?? $pos->json('whatsapp_url'));
                $notification = $this->withToken($this->token)->postJson('/api/v1/tenant/notifications/dispatch', [
                    'document_type' => $operation === 'sale' ? 'invoice' : 'quotation',
                    'document_id' => (string) $sale->id, 'channels' => [$channel],
                ])->assertOk()->assertJsonPath('status', 'manual_link');
                $this->assertStringStartsWith($prefix, $notification->json('url'));

            }
        }
        Mail::assertNothingSent();
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'http://localhost:4000/'));
        $this->assertSame(0, MessageQueue::withoutGlobalScopes()->count());
    }

    public function test_disabled_gateways_never_use_stored_or_legacy_credentials(): void
    {
        foreach (['whatsapp', 'sms', 'email'] as $channel) {
            TenantNotificationGateway::create([
                'company_id' => $this->company->id, 'channel' => $channel, 'is_enabled' => false,
                'provider' => match ($channel) { 'whatsapp' => 'meta_cloud_api', 'sms' => 'generic_http', 'email' => 'smtp' },
                'credentials' => ['url' => 'https://sms.example.test/send', 'phone_number_id' => '12345', 'access_token' => 'test-token', 'host' => 'smtp.example.test', 'username' => 'owner@example.test'],
            ]);
        }
        \App\Models\Configuration::withoutGlobalScopes()->insert([
            ['company_id' => $this->company->id, 'key' => 'whatsapp_phone_number_id', 'value' => '12345'],
            ['company_id' => $this->company->id, 'key' => 'whatsapp_api_token', 'value' => 'legacy-token'],
            ['company_id' => $this->company->id, 'key' => 'smtp_host', 'value' => 'smtp.example.test'],
        ]);
        $dispatcher = app(TenantNotificationDispatcherService::class);
        $this->assertFalse($dispatcher->isChannelActive($this->company, 'whatsapp'));
        $this->assertFalse($dispatcher->isChannelActive($this->company, 'sms'));
        $this->assertFalse($dispatcher->isChannelActive($this->company, 'email'));
        $this->assertSame('manual_link', $dispatcher->dispatchWhatsApp($this->company, $this->customer->phone, 'Hello')['status']);
        $this->assertSame('manual_link', $dispatcher->dispatchSms($this->company, $this->customer->phone, 'Hello')['status']);
        $this->assertSame('manual_link', $dispatcher->dispatchEmail($this->company, $this->customer->email, 'Repair', '<p>Hello</p>')['status']);
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'http://localhost:4000/'));
        Mail::assertNothingSent();
    }

    public function test_incomplete_gateways_offer_device_composers(): void
    {
        foreach (['whatsapp', 'sms', 'email'] as $channel) {
            TenantNotificationGateway::create([
                'company_id' => $this->company->id, 'channel' => $channel, 'is_enabled' => true,
                'provider' => match ($channel) { 'whatsapp' => 'meta_cloud_api', 'sms' => 'generic_http', 'email' => 'smtp' },
                'credentials' => $channel === 'whatsapp' ? ['access_token' => 'partial-token'] : [],
            ]);
        }
        $dispatcher = app(TenantNotificationDispatcherService::class);
        $this->assertSame('manual_link', $dispatcher->dispatchWhatsApp($this->company, $this->customer->phone, 'Hello')['status']);
        $this->assertSame('manual_link', $dispatcher->dispatchSms($this->company, $this->customer->phone, 'Hello')['status']);
        $this->assertSame('manual_link', $dispatcher->dispatchEmail($this->company, $this->customer->email, 'Repair', '<p>Hello</p>')['status']);
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'http://localhost:4000/'));
        Mail::assertNothingSent();
    }

    public function test_repair_device_messages_match_every_stage_and_linked_customer(): void
    {
        $this->company->update(['name' => 'Repair', 'currency_symbol' => '$']);
        $this->customer->update(['name' => 'prakash Kumar Singh']);
        $ticket = RepairTicket::create([
            'company_id' => $this->company->id, 'ticket_number' => 'REP-2026-0016',
            'customer_id' => $this->customer->id, 'brand' => 'samsung', 'model' => 'ZX10R',
            'status' => 'received', 'advance_deposit' => 60, 'estimated_cost' => 100,
        ]);
        $expected = 'Hello prakash Kumar Singh, repair ticket #REP-2026-0016 for your samsung ZX10R has been received at Repair. Advance Paid: $60.00. Track progress: '.route('repair.portal.track', $ticket->ticket_number);
        $service = app(RepairNotificationService::class);
        $this->assertSame($expected, $service->buildCustomerMessage($ticket));
        foreach ([
            'received' => 'has been received', 'diagnosing' => 'is being diagnosed', 'waiting_parts' => 'is waiting for parts',
            'in_progress' => 'Work is in progress', 'ready' => 'ready for pickup', 'delivered' => 'has been delivered and closed', 'cancelled' => 'has been cancelled',
        ] as $stage => $text) {
            $ticket->update(['status' => $stage]);
            $share = $this->withToken($this->token)->getJson("/api/tenant/repair/tickets/{$ticket->id}/share")
                ->assertOk()->json('share.share_text');
            $this->assertStringContainsString($text, $share);
            $this->assertStringContainsString('/portal/repair/REP-2026-0016', $share);
            foreach ([
                "/api/tenant/repair/tickets/{$ticket->id}/share-sheet",
                "/api/v1/tenant/documents/repair/{$ticket->id}/preview-modal",
            ] as $endpoint) {
                $sheet = $this->withToken($this->token)->getJson($endpoint)->assertOk();
                foreach (['whatsapp' => 'whatsapp://send?phone=919876511111&text=', 'sms' => 'sms:', 'email' => 'mailto:'] as $channel => $prefix) {
                    $tile = collect($sheet->json('schema.components'))->firstWhere('channel', $channel);
                    $this->assertFalse($tile['api_enabled']);
                    $this->assertSame('device', $tile['delivery_mode']);
                    $this->assertSame('OPEN_URL', $tile['action_type']);
                    $this->assertArrayNotHasKey('endpoint', $tile['action']);
                    $this->assertStringStartsWith($prefix, $tile['action']['url']);
                    $this->assertStringContainsString($service->buildCustomerMessage($ticket), rawurldecode($tile['action']['url']));
                }
            }
            foreach (['whatsapp', 'sms', 'email'] as $channel) {
                $response = $this->withToken($this->token)->postJson("/api/tenant/repair/tickets/{$ticket->id}/dispatch", [
                    'channel' => $channel, 'phone' => '+15550000000', 'email' => 'someone-else@example.test',
                ])->assertOk()->assertJsonPath('status', 'manual_link');
                $message = rawurldecode($response->json('url'));
                $this->assertStringContainsString($text, $message);
                $this->assertStringContainsString('prakash Kumar Singh', $message);
                $this->assertStringNotContainsString('someone-else@example.test', $message);
                $this->assertStringNotContainsString('15550000000', $message);
                if ($stage === 'received') $this->assertStringContainsString($expected, $message);
            }
        }
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'http://localhost:4000/'));
        Mail::assertNothingSent();
    }

    public function test_configured_provider_failure_is_not_reported_as_sent(): void
    {
        TenantNotificationGateway::create([
            'company_id' => $this->company->id, 'channel' => 'sms', 'provider' => 'generic_http',
            'is_enabled' => true, 'credentials' => ['url' => 'https://sms.example.test/send'],
        ]);
        config(['testing.dispatch_http_status' => 500]);
        $sale = Sale::create(['company_id' => $this->company->id, 'customer_id' => $this->customer->id, 'sale_number' => 'INV-FAIL-1', 'total' => 60, 'status' => 'completed']);
        $this->withToken($this->token)->postJson('/api/v1/documents/dispatch', [
            'document_type' => 'invoice', 'document_id' => $sale->id, 'channels' => ['sms'],
        ])->assertUnprocessable()->assertJsonPath('success', false)->assertJsonPath('results.sms.status', 'failed');
    }

    public function test_repair_share_actions_switch_immediately_with_gateway_enabled_state(): void
    {
        $gateway = TenantNotificationGateway::create([
            'company_id' => $this->company->id, 'channel' => 'whatsapp', 'provider' => 'meta_cloud_api',
            'is_enabled' => false, 'credentials' => ['phone_number_id' => '12345', 'access_token' => 'test-token'],
        ]);
        $ticket = RepairTicket::create([
            'company_id' => $this->company->id, 'customer_id' => $this->customer->id,
            'ticket_number' => 'REP-GATEWAY-TOGGLE', 'brand' => 'Samsung', 'model' => 'ZX10R',
            'status' => 'received', 'advance_deposit' => 60,
        ]);

        foreach ([false, true, false] as $enabled) {
            $gateway->update(['is_enabled' => $enabled]);
            foreach ([
                "/api/tenant/repair/tickets/{$ticket->id}/share-sheet",
                "/api/v1/tenant/documents/repair/{$ticket->id}/preview-modal",
            ] as $endpoint) {
                $response = $this->withToken($this->token)->getJson($endpoint)->assertOk();
                $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
                $tile = collect($response->json('schema.components'))->firstWhere('channel', 'whatsapp');
                $this->assertSame($enabled, $tile['api_enabled']);
                $this->assertSame($enabled ? 'Send via WhatsApp [Cloud API]' : 'Open WhatsApp App', $tile['title']);
                if ($enabled) {
                    $this->assertSame('checkbox', $tile['type']);
                    $this->assertArrayNotHasKey('action', $tile);
                    $batch = collect($response->json('schema.components'))->firstWhere('id', 'dispatch_selected_channels');
                    $this->assertSame('/api/v1/documents/dispatch', $batch['action']['endpoint']);
                } else {
                    $this->assertArrayNotHasKey('endpoint', $tile['action']);
                    $this->assertStringStartsWith('whatsapp://send?phone=919876511111&text=', $tile['action']['url']);
                    $this->assertStringContainsString('Advance Paid: ₹60.00', rawurldecode($tile['action']['url']));
                }
            }
        }
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'http://localhost:4000/'));
        Mail::assertNothingSent();
    }

    public function test_document_sheets_open_device_apps_directly_without_a_dispatch_endpoint(): void
    {
        foreach (['invoice' => 'sale', 'quotation' => 'quotation'] as $type => $operation) {
            $sale = Sale::create([
                'company_id' => $this->company->id, 'customer_id' => $this->customer->id,
                'sale_number' => strtoupper($type).'-DIRECT-APP', 'operation_type' => $operation,
                'status' => 'draft', 'total' => 60,
            ]);
            foreach (['actions-sheet', 'preview-modal'] as $sheet) {
                $response = $this->withToken($this->token)
                    ->getJson("/api/v1/tenant/documents/{$type}/{$sale->id}/{$sheet}")->assertOk();
                $tiles = collect($response->json('schema.components'));
                foreach (['whatsapp' => 'whatsapp://send?', 'sms' => 'sms:', 'email' => 'mailto:'] as $channel => $prefix) {
                    $tile = $tiles->firstWhere('channel', $channel);
                    $this->assertNotNull($tile, "Missing {$channel} in {$type} {$sheet}");
                    $this->assertSame('OPEN_URL', $tile['action']['type']);
                    $this->assertArrayNotHasKey('endpoint', $tile['action']);
                    $this->assertStringStartsWith($prefix, $tile['action']['url']);
                    $this->assertStringContainsString(route($type === 'quotation' ? 'quotes.public' : 'sales.public', $sale->sale_number), rawurldecode($tile['action']['url']));
                }
            }
            $options = $this->withToken($this->token)
                ->getJson("/api/v1/documents/{$type}/{$sale->id}/dispatch-options")->assertOk();
            $options->assertJsonPath('device_channels.whatsapp.action.type', 'OPEN_URL');
            $this->assertStringStartsWith('whatsapp://send?', $options->json('device_channels.whatsapp.action.url'));
            $this->assertSame('draft', $sale->fresh()->status);
        }
    }

    private function mixedChannelInvoice(): Sale
    {
        foreach ([
            ['sms', 'generic_http', true, ['url' => 'https://sms.example.test/send', 'method' => 'POST']],
            ['email', 'smtp', true, ['host' => 'smtp.example.test', 'port' => 587, 'username' => 'mailer@example.test', 'password' => 'smtp-secret']],
            ['whatsapp', 'unofficial_http', false, ['url' => 'https://wa.example.test/send', 'api_token' => 'stored-disabled-token']],
        ] as [$channel, $provider, $enabled, $credentials]) {
            TenantNotificationGateway::create(['company_id' => $this->company->id,
                'channel' => $channel, 'provider' => $provider, 'is_enabled' => $enabled, 'credentials' => $credentials]);
        }

        return Sale::create(['company_id' => $this->company->id, 'customer_id' => $this->customer->id,
            'sale_number' => 'INV-MULTI-001', 'operation_type' => 'sale', 'status' => 'completed',
            'items' => [['name' => 'Sample', 'quantity' => 1, 'unit_price' => 100]], 'total' => 100]);
    }

    public function test_unified_sheet_only_selects_configured_apis_and_keeps_disabled_whatsapp_secondary(): void
    {
        $sale = $this->mixedChannelInvoice();
        foreach (["/api/v1/documents/invoice/{$sale->id}/dispatch-options", '/api/v1/documents/channels'] as $endpoint) {
            $response = $this->withToken($this->token)->getJson($endpoint)->assertOk()
                ->assertJsonPath('channels.sms.api_enabled', true)->assertJsonPath('channels.email.api_enabled', true)
                ->assertJsonPath('channels.whatsapp.mode', 'local_intent')->assertJsonPath('channels.whatsapp.selectable', false)->assertJsonPath('device_channels.whatsapp.selectable', false)
                ->assertJsonPath('device_channels.whatsapp.default', false)->assertJsonPath('device_channels.whatsapp.action.type', 'OPEN_URL');
            $this->assertEqualsCanonicalizing(['sms', 'email'], collect($response->json('enabled_channels'))->pluck('channel')->all());
            $this->assertStringStartsWith('whatsapp://send?', $response->json('secondary_options.0.action.url'));
        }
        foreach (['actions-sheet', 'preview-modal'] as $sheet) {
            $response = $this->withToken($this->token)->getJson("/api/v1/tenant/documents/invoice/{$sale->id}/{$sheet}")->assertOk();
            $components = collect($response->json('schema.components'));
            $this->assertEqualsCanonicalizing(['sms', 'email'], $components->where('type', 'checkbox')->pluck('selection_value')->all());
            $this->assertSame('form_submit', $components->firstWhere('id', 'dispatch_selected_channels')['action']['type']);
            $this->assertTrue($components->firstWhere('id', 'dispatch_selected_channels')['action']['data']['api_only']);
            $device = $components->firstWhere('channel', 'whatsapp');
            $this->assertFalse($device['selectable']);
            $this->assertSame('OPEN_URL', $device['action']['type']);
            $this->assertArrayNotHasKey('endpoint', $device['action']);
        }
    }

    public function test_batch_sends_email_and_sms_together_and_never_uses_disabled_whatsapp(): void
    {
        $sale = $this->mixedChannelInvoice();
        foreach (['/api/v1/documents/dispatch', '/api/v1/tenant/dispatch/batch-send', '/api/v1/tenant/notifications/dispatch'] as $endpoint) {
            $response = $this->withToken($this->token)->postJson($endpoint, [
                'document_type' => 'invoice', 'document_id' => $sale->id, 'channels' => ['email', 'sms'], 'api_only' => true,
            ])->assertOk()->assertJsonPath('status', 'sent')->assertJsonPath('results.email.status', 'sent')
                ->assertJsonPath('results.sms.success', true)->assertJsonMissingPath('results.whatsapp')->assertJsonCount(0, 'device_actions');
            $this->assertEqualsCanonicalizing(['email', 'sms'], $response->json('successful_channels'));
        }
        Mail::assertSent(\App\Mail\InvoiceMailable::class, 3);
        $this->assertCount(3, Http::recorded(fn ($request) => str_starts_with($request->url(), 'https://sms.example.test/')));
        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://wa.example.test/'));
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://sms.example.test/send'));
        $this->assertSame(0, MessageQueue::where('type', 'whatsapp')->count());
    }

    public function test_stale_disabled_channel_is_skipped_while_enabled_channels_are_sent(): void
    {
        $sale = $this->mixedChannelInvoice();
        $this->withToken($this->token)->postJson('/api/v1/tenant/dispatch/batch-send', [
            'document_type' => 'invoice', 'document_id' => $sale->id, 'channels' => ['whatsapp', 'email', 'sms'],
        ])->assertOk()->assertJsonPath('status', 'partial')->assertJsonPath('results.whatsapp.success', false)
            ->assertJsonPath('results.whatsapp.status', 'not_configured')->assertJsonPath('results.email.status', 'sent')
            ->assertJsonPath('results.sms.success', true)->assertJsonPath('skipped_channels', ['whatsapp'])
            ->assertJsonCount(0, 'device_actions')->assertJsonMissingPath('results.whatsapp.url');
        Mail::assertSent(\App\Mail\InvoiceMailable::class, 1);
        $this->assertCount(1, Http::recorded(fn ($request) => str_starts_with($request->url(), 'https://sms.example.test/')));
        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://wa.example.test/'));
        $this->assertSame(0, MessageQueue::where('type', 'whatsapp')->count());
    }

    public function test_channel_failure_does_not_prevent_other_selected_channels_from_sending(): void
    {
        $sale = $this->mixedChannelInvoice();
        config(['testing.dispatch_http_status' => 500]);
        $this->withToken($this->token)->postJson('/api/v1/documents/dispatch', [
            'document_type' => 'invoice', 'document_id' => $sale->id, 'channels' => ['sms', 'email'], 'api_only' => true,
        ])->assertOk()->assertJsonPath('status', 'partial')->assertJsonPath('results.email.success', true)
            ->assertJsonPath('results.sms.success', false)->assertJsonPath('successful_channels', ['email']);
        Mail::assertSent(\App\Mail\InvoiceMailable::class, 1);
    }

    public function test_all_disabled_apis_offer_only_device_actions_and_cannot_claim_a_batch_was_sent(): void
    {
        $sale = $this->mixedChannelInvoice();
        TenantNotificationGateway::withoutGlobalScopes()->where('company_id', $this->company->id)->update(['is_enabled' => false]);
        $response = $this->withToken($this->token)->getJson("/api/v1/documents/invoice/{$sale->id}/dispatch-options")
            ->assertOk()->assertJsonCount(0, 'enabled_channels')->assertJsonCount(3, 'secondary_options')->assertJsonPath('batch_action', null);
        $this->assertEqualsCanonicalizing(['thermal_print', 'whatsapp', 'email', 'sms', 'pdf_preview'], array_keys($response->json('channels')));
        $this->withToken($this->token)->postJson('/api/v1/tenant/dispatch/batch-send', [
            'document_type' => 'invoice', 'document_id' => $sale->id, 'channels' => ['sms', 'email', 'whatsapp'],
        ])->assertUnprocessable()->assertJsonPath('success', false)->assertJsonCount(0, 'successful_channels')->assertJsonCount(0, 'device_actions');
        Mail::assertNothingSent();
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'http://localhost:4000/'));
        $this->assertSame(0, MessageQueue::where('type', 'whatsapp')->count());
    }

    public function test_every_configuration_combination_preserves_all_five_visible_channels(): void
    {
        $sale = $this->mixedChannelInvoice();
        $expected = ['thermal_print', 'whatsapp', 'email', 'sms', 'pdf_preview'];
        foreach (range(0, 7) as $configuration) {
            $enabled = ['whatsapp' => (bool) ($configuration & 1), 'email' => (bool) ($configuration & 2), 'sms' => (bool) ($configuration & 4)];
            foreach ($enabled as $channel => $active) {
                TenantNotificationGateway::withoutGlobalScopes()->where('company_id', $this->company->id)
                    ->where('channel', $channel)->update(['is_enabled' => $active]);
            }
            $nativeData = \App\Services\Sdui\SchemaResponse::postSaleActionData($sale);
            $this->assertSame($expected, array_column($nativeData['channels'], 'channel'));
            $this->assertSame('/api/v1/documents/dispatch', $nativeData['batch_dispatch_endpoint']);
            $this->assertTrue($nativeData['api_only']);
            foreach ([
                "/api/v1/documents/invoice/{$sale->id}/dispatch-options",
                "/api/v1/tenant/documents/invoice/{$sale->id}/actions-sheet",
                "/api/v1/tenant/documents/invoice/{$sale->id}/preview-modal",
                "/api/v1/pos/receivables/{$sale->id}/remind",
            ] as $endpoint) {
                $response = $this->withToken($this->token)->getJson($endpoint)->assertOk();
                $components = collect($response->json('schema.components'));
                $visible = $components->filter(fn ($item) => isset($item['channel']));
                $this->assertSame($expected, $visible->pluck('channel')->all(), $endpoint." configuration {$configuration}");
                $this->assertSame(array_fill(0, 5, true), $visible->pluck('visible')->all());
                $this->assertSame(array_fill(0, 5, true), $visible->pluck('available')->all());
                foreach ($enabled as $channel => $active) {
                    $item = $visible->firstWhere('channel', $channel);
                    $this->assertSame($active, $item['selectable']);
                    $this->assertSame($active ? 'cloud_api' : 'local_intent', $item['mode']);
                    $this->assertSame($active ? 'checkbox' : 'list_tile', $item['type']);
                    if ($active) {
                        $this->assertSame('channels[]', $item['name']);
                        $this->assertSame($channel, $item['selection_value']);
                        $this->assertStringContainsString('[Cloud API]', $item['label']);
                        $this->assertArrayNotHasKey('action', $item);
                    } else {
                        $this->assertSame('Open', $item['launch_label']);
                        $this->assertSame('OPEN_URL', $item['action']['type']);
                        $this->assertArrayNotHasKey('endpoint', $item['action']);
                        $this->assertStringStartsWith(match ($channel) { 'whatsapp' => 'whatsapp://send?', 'email' => 'mailto:', 'sms' => 'sms:' }, $item['action']['url']);
                    }
                }
                $this->assertEqualsCanonicalizing(array_keys(array_filter($enabled)), $components->where('type', 'checkbox')->pluck('channel')->all());
                $batch = $components->firstWhere('id', 'dispatch_selected_channels');
                if ($configuration) {
                    $this->assertSame('/api/v1/documents/dispatch', $batch['action']['endpoint']);
                    $this->assertSame('POST', $batch['action']['method']);
                } else {
                    $this->assertNull($batch);
                }
                $this->assertEmpty(app(\App\Services\Sdui\SchemaValidator::class)->validate($response->json('schema')));
            }
        }
        Mail::assertNothingSent();
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'http://localhost:4000/'));
    }

    public function test_missing_or_incomplete_integrations_always_render_local_launch_rows(): void
    {
        $sale = $this->mixedChannelInvoice();
        foreach (['missing', 'incomplete'] as $state) {
            if ($state === 'missing') {
                TenantNotificationGateway::withoutGlobalScopes()->where('company_id', $this->company->id)->delete();
            } else {
                foreach (['whatsapp' => 'unofficial_http', 'email' => 'smtp', 'sms' => 'generic_http'] as $channel => $provider) {
                    TenantNotificationGateway::create(['company_id' => $this->company->id, 'channel' => $channel,
                        'provider' => $provider, 'is_enabled' => true, 'credentials' => []]);
                }
            }
            $response = $this->withToken($this->token)->getJson("/api/v1/documents/invoice/{$sale->id}/dispatch-options")
                ->assertOk()->assertJsonCount(5, 'channels')->assertJsonCount(0, 'enabled_channels')->assertJsonCount(3, 'device_channels');
            foreach (['whatsapp', 'email', 'sms'] as $channel) {
                $response->assertJsonPath("channels.{$channel}.type", 'list_tile')->assertJsonPath("channels.{$channel}.mode", 'local_intent')
                    ->assertJsonPath("channels.{$channel}.action.type", 'OPEN_URL');
                $this->assertStringContainsString('INV-MULTI-001', rawurldecode($response->json("channels.{$channel}.action.url")));
            }
            $this->assertSame('dev@example.test', rawurldecode(parse_url($response->json('channels.email.action.url'), PHP_URL_PATH)));
            $this->assertStringContainsString('phone=919876511111', $response->json('channels.whatsapp.action.url'));
        }
    }

    public function test_repair_share_sheets_omit_print_preview_and_preserve_sharing_channels(): void
    {
        $ticket = RepairTicket::create(['company_id' => $this->company->id, 'tenant_id' => $this->company->id,
            'ticket_number' => 'REP-2026-PREVIEW', 'customer_id' => $this->customer->id, 'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone, 'brand' => 'Samsung', 'model' => 'ZX10R', 'status' => RepairTicket::STATUS_RECEIVED]);
        foreach ([
            "/api/v1/documents/repair/{$ticket->id}/dispatch-options",
            "/api/tenant/repair/tickets/{$ticket->id}/share-sheet",
            "/api/v1/tenant/documents/repair/{$ticket->id}/preview-modal",
        ] as $endpoint) {
            $share = $this->withToken($this->token)->getJson($endpoint)->assertOk();
            $channels = collect($share->json('components') ?? $share->json('schema.components'))->pluck('channel')->filter()->values()->all();
            $this->assertSame(['thermal_print', 'whatsapp', 'email', 'sms'], $channels);
            $this->assertNotContains('document_preview_card', collect($share->json('components') ?? $share->json('schema.components'))->pluck('type')->all());
        }

        // The standalone intake preview remains accessible outside Share Ticket.
        $preview = $this->withToken($this->token)->getJson("/api/v1/tenant/documents/repair/{$ticket->id}/preview-modal?format=a4&preview_document=1")->assertOk();
        $card = collect($preview->json('schema.components'))->firstWhere('type', 'document_preview_card');
        $this->assertNotNull($card);
        $this->withToken($this->token)->get($card['render_url'])->assertOk()->assertSee('REP-2026-PREVIEW')->assertSee('ZX10R');
    }

    public function test_primary_button_bundles_all_three_enabled_cloud_channels_in_one_request(): void
    {
        $sale = $this->mixedChannelInvoice();
        TenantNotificationGateway::withoutGlobalScopes()->where('company_id', $this->company->id)
            ->where('channel', 'whatsapp')->update(['is_enabled' => true]);
        $sheet = $this->withToken($this->token)->getJson("/api/v1/documents/invoice/{$sale->id}/dispatch-options")->assertOk();
        $components = collect($sheet->json('components'));
        $selected = $components->where('type', 'checkbox')->pluck('selection_value')->all();
        $this->assertEqualsCanonicalizing(['whatsapp', 'email', 'sms'], $selected);
        $button = $components->firstWhere('id', 'dispatch_selected_channels');
        $response = $this->withToken($this->token)->postJson($button['action']['endpoint'],
            $button['action']['data'] + ['channels' => $selected])
            ->assertOk()->assertJsonPath('status', 'sent')->assertJsonPath('results.whatsapp.success', true)
            ->assertJsonPath('results.email.success', true)->assertJsonPath('results.sms.success', true)->assertJsonCount(0, 'device_actions');
        $this->assertEqualsCanonicalizing($selected, $response->json('successful_channels'));
        Mail::assertSent(\App\Mail\InvoiceMailable::class, 1);
        $this->assertCount(2, Http::recorded(fn ($request) => str_starts_with($request->url(), 'https://sms.example.test/') || str_starts_with($request->url(), 'https://wa.example.test/')));
    }

}
