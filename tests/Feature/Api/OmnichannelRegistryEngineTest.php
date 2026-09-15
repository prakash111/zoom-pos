<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomNotificationChannel;
use App\Models\Plan;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\TenantApiKey;
use App\Models\TenantNotificationGateway;
use App\Models\User;
use App\Services\OmnichannelRegistryService;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OmnichannelRegistryEngineTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Customer $customer;

    private string $token = 'zk_live_omnichannel_registry_test';

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'omnichannel-plan',
            'display_name' => 'Omnichannel Plan',
            'price' => 49,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'features' => ['pos' => true, 'quotes' => true],
            'limits' => ['products' => 100, 'users' => 5],
            'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Zoom Omnichannel Store',
            'trade_name' => 'Zoom Omnichannel Store',
            'slug' => 'zoom-omnichannel-store',
            'email' => 'owner@omnichannel.test',
            'country' => 'IN',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'tax_id_label' => 'GSTIN',
            'tax_id' => '27BBBBB0000B1Z6',
            'plan_name' => 'omnichannel-plan',
            'expires_at' => now()->addMonth(),
            'licensed_modules' => ['retail'],
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
            'name' => 'Rajesh Kumar',
            'phone' => '+91 91234 56789',
            'email' => 'rajesh@example.test',
        ]);

        TenantApiKey::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Omnichannel terminal',
            'token' => $this->token,
            'permissions' => ['*'],
            'active' => true,
        ]);
    }

    public function test_registry_resolves_all_active_channels_dynamically(): void
    {
        TenantNotificationGateway::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'channel' => TenantNotificationGateway::CHANNEL_WHATSAPP,
            'provider' => TenantNotificationGateway::PROVIDER_META_CLOUD,
            'is_enabled' => true,
            'credentials' => [
                'access_token' => 'meta-token-12345',
                'phone_number_id' => '100200300',
            ],
        ]);

        $customChannel = CustomNotificationChannel::create([
            'company_id' => $this->company->id,
            'name' => 'Accounting Webhook',
            'icon' => 'hub',
            'url' => 'https://accounting.example.test/import',
            'method' => 'POST',
            'event_types' => ['invoice'],
            'is_active' => true,
        ]);

        TenantNotificationGateway::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'channel' => TenantNotificationGateway::CHANNEL_SMS,
            'provider' => TenantNotificationGateway::PROVIDER_GENERIC_HTTP,
            'is_enabled' => true,
            'credentials' => [
                'url' => 'https://sms.example.test/send',
                'api_key' => 'sms-key-123',
            ],
        ]);

        TenantNotificationGateway::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'channel' => TenantNotificationGateway::CHANNEL_EMAIL,
            'provider' => TenantNotificationGateway::PROVIDER_SMTP,
            'is_enabled' => true,
            'credentials' => [
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'username' => 'mailer@omnichannel.test',
            ],
        ]);

        TenantNotificationGateway::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'channel' => TenantNotificationGateway::CHANNEL_WEBHOOK,
            'provider' => TenantNotificationGateway::PROVIDER_WEBHOOK,
            'is_enabled' => true,
            'credentials' => [
                'url' => 'https://webhook.site/dispatch-test',
                'method' => 'POST',
            ],
        ]);

        $channels = OmnichannelRegistryService::resolveChannels($this->company->id, [
            'id' => 999,
            'type' => 'invoice',
            'reference' => 'INV-999',
            'phone' => $this->customer->phone,
            'email' => $this->customer->email,
        ]);

        $channelNames = collect($channels)->pluck('channel')->all();

        $this->assertContains('whatsapp', $channelNames);
        $this->assertContains('sms', $channelNames);
        $this->assertContains('email', $channelNames);
        $this->assertContains('webhook', $channelNames);
        $this->assertContains('custom', $channelNames);

        // Check specific SDUI contracts
        $wa = collect($channels)->firstWhere('channel', 'whatsapp');
        $this->assertSame('Send via WhatsApp Business API', $wa['title']);
        $this->assertSame('SUBMIT_FORM', $wa['action_type']);
        $this->assertSame('/api/v1/tenant/dispatch/send', $wa['action']['endpoint']);
        $this->assertSame('POST', $wa['action']['method']);

        $sms = collect($channels)->firstWhere('channel', 'sms');
        $this->assertSame('Send via SMS (Text Message)', $sms['title']);
        $this->assertSame('/api/v1/tenant/dispatch/send', $sms['action']['endpoint']);

        $email = collect($channels)->firstWhere('channel', 'email');
        $this->assertStringContainsString('smtp.gmail.com', $email['title']);
        $this->assertSame('/api/v1/tenant/dispatch/send', $email['action']['endpoint']);

        $webhook = collect($channels)->firstWhere('channel', 'webhook');
        $this->assertSame('Trigger External Webhook', $webhook['title']);
        $this->assertSame('/api/v1/tenant/dispatch/send', $webhook['action']['endpoint']);

        $custom = collect($channels)->firstWhere('channel', 'custom');
        $this->assertSame('Send via Accounting Webhook', $custom['title']);
        $this->assertSame($customChannel->id, $custom['channel_id']);
        $this->assertSame('/api/v1/tenant/dispatch/invoice/999', $custom['action']['endpoint']);
    }

    public function test_registry_omits_disabled_channels_dynamically(): void
    {
        // WhatsApp enabled, SMS disabled, Email disabled, Webhook disabled
        TenantNotificationGateway::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'channel' => TenantNotificationGateway::CHANNEL_WHATSAPP,
            'provider' => TenantNotificationGateway::PROVIDER_META_CLOUD,
            'is_enabled' => true,
            'credentials' => ['access_token' => 'meta-token-12345'],
        ]);

        TenantNotificationGateway::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'channel' => TenantNotificationGateway::CHANNEL_SMS,
            'provider' => TenantNotificationGateway::PROVIDER_GENERIC_HTTP,
            'is_enabled' => false,
            'credentials' => ['url' => 'https://sms.example.test/send'],
        ]);

        TenantNotificationGateway::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'channel' => TenantNotificationGateway::CHANNEL_EMAIL,
            'provider' => TenantNotificationGateway::PROVIDER_SMTP,
            'is_enabled' => false,
            'credentials' => ['host' => 'smtp.gmail.com'],
        ]);

        TenantNotificationGateway::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'channel' => TenantNotificationGateway::CHANNEL_WEBHOOK,
            'provider' => TenantNotificationGateway::PROVIDER_WEBHOOK,
            'is_enabled' => false,
            'credentials' => ['url' => 'https://webhook.site/dispatch-test'],
        ]);

        $channels = OmnichannelRegistryService::resolveChannels($this->company->id, [
            'id' => 999,
            'type' => 'invoice',
            'reference' => 'INV-999',
            'phone' => $this->customer->phone,
            'email' => $this->customer->email,
        ]);

        $channelNames = collect($channels)->pluck('channel')->all();

        $this->assertContains('whatsapp', $channelNames);
        $this->assertNotContains('sms', $channelNames);
        $this->assertNotContains('webhook', $channelNames);
    }

    public function test_unified_dispatch_controller_handles_all_channels(): void
    {
        Mail::fake();
        Http::fake([
            'https://sms.zoomnearby.com/*' => Http::response(['success' => true], 200),
            'https://sms.example.test/*' => Http::response(['success' => true], 200),
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.test']],
            ], 200),
            'https://webhook.example.test/*' => Http::response(['accepted' => true], 200),
        ]);

        foreach ([
            [
                'channel' => TenantNotificationGateway::CHANNEL_WHATSAPP,
                'provider' => TenantNotificationGateway::PROVIDER_META_CLOUD,
                'credentials' => ['phone_number_id' => '12345', 'access_token' => 'meta-test-token'],
            ],
            [
                'channel' => TenantNotificationGateway::CHANNEL_SMS,
                'provider' => TenantNotificationGateway::PROVIDER_GENERIC_HTTP,
                'credentials' => ['url' => 'https://sms.example.test/send'],
            ],
            [
                'channel' => TenantNotificationGateway::CHANNEL_EMAIL,
                'provider' => TenantNotificationGateway::PROVIDER_SMTP,
                'credentials' => [
                    'host' => 'smtp.example.test',
                    'port' => 587,
                    'username' => 'mailer@example.test',
                    'password' => 'secret',
                    'from_address' => 'mailer@example.test',
                    'from_name' => 'Test Store',
                ],
            ],
            [
                'channel' => TenantNotificationGateway::CHANNEL_WEBHOOK,
                'provider' => TenantNotificationGateway::PROVIDER_WEBHOOK,
                'credentials' => ['url' => 'https://webhook.example.test/events', 'event_types' => ['*']],
            ],
        ] as $gateway) {
            TenantNotificationGateway::create(array_merge($gateway, [
                'company_id' => $this->company->id,
                'tenant_id' => $this->company->id,
                'is_enabled' => true,
            ]));
        }

        $invoice = Sale::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'sale_number' => 'INV-DISPATCH-1',
            'operation_type' => 'sale',
            'items' => [['name' => 'Widget', 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]],
            'total' => 100,
            'status' => 'completed',
        ]);
        $quotation = Sale::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'sale_number' => 'QT-DISPATCH-1',
            'operation_type' => 'quotation',
            'items' => [['name' => 'Proposal', 'quantity' => 1, 'unit_price' => 200, 'line_total' => 200]],
            'total' => 200,
            'status' => 'draft',
        ]);

        // 1. SMS Dispatch
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/tenant/dispatch/send', [
                'channel' => 'sms',
                'recipient' => '+919123456789',
                'type' => 'invoice',
                'id' => $invoice->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertStringContainsString('SMS', $response->json('message'));

        // 2. WhatsApp Dispatch
        $this->withToken($this->token)
            ->postJson('/api/v1/tenant/dispatch/send', [
                'channel' => 'whatsapp',
                'recipient' => '+919123456789',
                'type' => 'invoice',
                'id' => $invoice->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('channel', 'whatsapp');

        // 3. Email Dispatch
        $this->withToken($this->token)
            ->postJson('/api/v1/tenant/dispatch/send', [
                'channel' => 'email',
                'recipient' => 'client@example.test',
                'type' => 'quotation',
                'id' => $quotation->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('channel', 'email');

        // 4. Webhook Dispatch
        $this->withToken($this->token)
            ->postJson('/api/v1/tenant/dispatch/send', [
                'channel' => 'webhook',
                'type' => 'invoice',
                'id' => $invoice->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('channel', 'webhook');

        // 5. Unsupported Channel
        $this->withToken($this->token)
            ->postJson('/api/v1/tenant/dispatch/send', [
                'channel' => 'unsupported_carrier',
                'type' => 'invoice',
                'id' => $invoice->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('channel');
    }

    public function test_bottom_sheets_render_dynamic_registry_channels(): void
    {
        TenantNotificationGateway::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'channel' => TenantNotificationGateway::CHANNEL_SMS,
            'provider' => TenantNotificationGateway::PROVIDER_GENERIC_HTTP,
            'is_enabled' => true,
            'credentials' => ['url' => 'https://sms.example.test/send'],
        ]);

        $sale = Sale::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'sale_number' => 'INV-2026-001',
            'operation_type' => 'sale',
            'items' => [['name' => 'Widget', 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]],
            'total' => 100,
            'paid_amount' => 50,
            'due_amount' => 50,
            'due_date' => now()->addDays(5),
            'status' => 'completed',
        ]);

        // DocumentActionController actions-sheet
        $docActionSheet = $this->withToken($this->token)
            ->getJson("/api/v1/tenant/documents/invoice/{$sale->id}/actions-sheet")
            ->assertOk()
            ->assertJsonPath('type', 'bottom_sheet')
            ->assertJsonPath('title', 'Invoice Preview');

        $components = collect($docActionSheet->json('components'));
        $this->assertTrue($components->contains(fn ($c) => ($c['title'] ?? null) === 'Preview & Print'));
        $this->assertTrue($components->contains(fn ($c) => ($c['channel'] ?? null) === 'sms'));
        $this->assertEmpty(app(SchemaValidator::class)->validate($docActionSheet->json('schema')));

        // InvoicePreviewController preview-sheet
        $previewSheet = $this->withToken($this->token)
            ->getJson("/api/v1/tenant/invoices/{$sale->id}/preview-sheet")
            ->assertOk()
            ->assertJsonPath('type', 'bottom_sheet')
            ->assertJsonPath('title', 'Invoice Preview');

        $this->assertEmpty(app(SchemaValidator::class)->validate($previewSheet->json('schema')));

        // ReceivablesController reminder-sheet
        $reminderSheet = $this->withToken($this->token)
            ->getJson("/api/v1/tenant/receivables/{$sale->id}/reminder-sheet")
            ->assertOk()
            ->assertJsonPath('type', 'bottom_sheet')
            ->assertJsonPath('title', 'INV-2026-001')
            ->assertJsonPath('subtitle', 'GSTIN: 27BBBBB0000B1Z6')
            ->assertJsonPath('background_color', '#131E29')
            ->assertJsonPath('document_type', 'invoice')
            ->assertJsonPath('native_action.type', 'show_post_sale_sheet')
            ->assertJsonPath('native_action.data.document_type', 'invoice')
            ->assertJsonPath('native_action.data.actions_endpoint', "/api/v1/tenant/receivables/{$sale->id}/reminder-sheet?document_type=invoice")
            ->assertJsonPath('post_sale_sheet.action', 'show_post_sale_sheet')
            ->assertJsonPath('post_sale_sheet.data.pdf_endpoint', "/api/tenant/invoices/{$sale->id}/pdf-stream");

        $reminderComponents = collect($reminderSheet->json('components'));
        $this->assertSame([
            'Preview & Print',
            'Print on receipt printer',
            'Send via WhatsApp',
            'Send via Email',
        ], $reminderComponents->pluck('title')->all());
        $this->assertSame('OPEN_RECEIPT_PREVIEW', $reminderComponents[0]['action_type']);
        $this->assertSame('TRIGGER_THERMAL_PRINT', $reminderComponents[1]['action_type']);
        $this->assertStringNotContainsString('OPEN_URL', json_encode($reminderSheet->json(), JSON_UNESCAPED_SLASHES));
        $this->assertEmpty(app(SchemaValidator::class)->validate($reminderSheet->json('schema')));

        $posSale = Sale::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'sale_number' => 'POS-63776202',
            'operation_type' => 'sale',
            'total' => 75,
            'paid_amount' => 25,
            'due_amount' => 50,
            'status' => 'completed',
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/tenant/receivables/{$posSale->id}/reminder-sheet")
            ->assertOk()
            ->assertJsonPath('title', 'POS-63776202')
            ->assertJsonPath('document_type', 'sale')
            ->assertJsonPath('components.0.action.document_type', 'sale');
    }

    public function test_real_mobile_app_routes_return_omnichannel_channels_and_sms(): void
    {
        TenantNotificationGateway::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'channel' => TenantNotificationGateway::CHANNEL_SMS,
            'provider' => TenantNotificationGateway::PROVIDER_GENERIC_HTTP,
            'is_enabled' => true,
            'credentials' => ['url' => 'https://sms.example.test/send'],
        ]);

        $sale = Sale::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'sale_number' => 'INV-2026-099',
            'operation_type' => 'sale',
            'items' => [['name' => 'Item 1', 'quantity' => 1, 'unit_price' => 50, 'line_total' => 50]],
            'total' => 50,
            'status' => 'completed',
        ]);

        // 1. Direct preview-sheet without ID (as tested in user's curl prompt)
        $resWithoutId = $this->withToken($this->token)
            ->getJson('/api/v1/tenant/invoices/preview-sheet')
            ->assertOk()
            ->assertJsonPath('type', 'bottom_sheet')
            ->assertJsonPath('title', 'Invoice Preview');

        $components1 = collect($resWithoutId->json('components'));
        $this->assertTrue($components1->contains(fn ($c) => ($c['title'] ?? null) === 'Preview & Print'));
        $this->assertTrue($components1->contains(fn ($c) => ($c['title'] ?? null) === 'Print on receipt printer'));
        $this->assertTrue($components1->contains(fn ($c) => ($c['title'] ?? null) === 'Share as PDF file'));
        $this->assertTrue($components1->contains(fn ($c) => ($c['channel'] ?? null) === 'sms'));
        $this->assertStringContainsString('Send via SMS', json_encode($resWithoutId->json()));
        $this->assertStringContainsString('channel_sms', json_encode($resWithoutId->json()));

        // 2. Direct preview-sheet with ID
        $resWithId = $this->withToken($this->token)
            ->getJson("/api/v1/tenant/invoices/{$sale->id}/preview-sheet")
            ->assertOk();
        $this->assertStringContainsString('channel_sms', json_encode($resWithId->json()));

        // 3. Invoice actions-sheet (legacy and v1)
        $resInvoiceActions = $this->withToken($this->token)
            ->getJson("/api/v1/tenant/invoices/{$sale->id}/actions-sheet")
            ->assertOk();
        $this->assertStringContainsString('channel_sms', json_encode($resInvoiceActions->json()));
        $this->assertStringContainsString('Share as PDF file', json_encode($resInvoiceActions->json()));

        // 4. Quotation actions-sheet
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'sale_number' => 'QT-2026-001',
            'operation_type' => 'quotation',
            'status' => 'draft',
            'total' => 100,
            'subtotal' => 100,
        ]);

        $resQuoteActions = $this->withToken($this->token)
            ->getJson("/api/v1/tenant/quotations/{$quotation->id}/actions-sheet")
            ->assertOk();
        $this->assertStringContainsString('channel_sms', json_encode($resQuoteActions->json()));
        $this->assertStringContainsString('Share as PDF file', json_encode($resQuoteActions->json()));
    }
}
