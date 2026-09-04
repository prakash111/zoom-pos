<?php

namespace Tests\Feature\Notifications;

use App\Models\Company;
use App\Models\CustomNotificationChannel;
use App\Models\KitchenTicket;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\User;
use App\Services\FirebasePushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomNotificationChannelTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'standard',
            'display_name' => 'Standard Plan',
            'price' => 29.00,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'features' => ['pos' => true, 'offline' => true],
            'limits' => ['products' => 1000, 'users' => 10],
            'active' => true,
        ]);

        $this->company = Company::create([
            'unique_account_id' => 'tenant-notif-uuid',
            'name' => 'Apex Dining',
            'trade_name' => 'Apex Dining',
            'slug' => 'apex-dining',
            'email' => 'admin@apexdining.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'plan_name' => 'standard',
            'pos_mode' => 'restaurant',
            'expires_at' => now()->addDays(30),
            'licensed_modules' => ['retail', 'restaurant'],
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'admin@apexdining.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);
    }

    protected function token(): string
    {
        return $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@apexdining.com',
            'password' => 'secret123',
        ])->json('token');
    }

    public function test_can_save_custom_notification_channel_with_payload_format(): void
    {
        $token = $this->token();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tenant/settings/custom-notifications', [
                'name' => 'Custom SMS Gateway',
                'url' => 'https://api.sms-gateway.example/v1/send',
                'method' => 'POST',
                'payload_format' => 'json',
                'headers' => ['Authorization' => 'Bearer token_xyz123'],
                'payload_template' => json_encode([
                    'to' => '{phone}',
                    'text' => 'Hi {customer_name}, invoice {invoice_id} is due for {amount}.',
                ]),
                'event_types' => ['delayed_order_alert', 'due_invoice_reminder'],
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('channel.name', 'Custom SMS Gateway');
        $response->assertJsonPath('channel.payload_format', 'json');

        $this->assertDatabaseHas('custom_notification_channels', [
            'company_id' => $this->company->id,
            'name' => 'Custom SMS Gateway',
            'payload_format' => 'json',
            'is_active' => true,
        ]);
    }

    public function test_can_test_custom_notification_channel_with_tag_replacement(): void
    {
        Http::fake([
            'https://api.sms-gateway.example/*' => Http::response(['status' => 'success', 'message_id' => 'msg_1001'], 200),
        ]);

        $channel = CustomNotificationChannel::create([
            'company_id' => $this->company->id,
            'name' => 'Custom SMS Gateway',
            'url' => 'https://api.sms-gateway.example/v1/send',
            'method' => 'POST',
            'payload_format' => 'json',
            'headers' => ['Authorization' => 'Bearer test_key'],
            'payload_template' => json_encode([
                'recipient' => '{phone}',
                'content' => 'Hello {customer_name}, your order #{invoice_id} of {amount} is ready: {order_link}',
            ]),
            'event_types' => ['invoice'],
            'is_active' => true,
        ]);

        $token = $this->token();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tenant/settings/custom-notifications/test', [
                'channel_id' => $channel->id,
                'phone' => '+15559876543',
                'customer_name' => 'Alice Smith',
                'invoice_id' => 'INV-2026-001',
                'amount' => '$45.50',
                'order_link' => 'https://zoomnearby.com/orders/INV-2026-001',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        Http::assertSent(function ($request) {
            $data = $request->data();
            return $request->url() === 'https://api.sms-gateway.example/v1/send'
                && ($data['recipient'] ?? null) === '+15559876543'
                && str_contains($data['content'] ?? '', 'Hello Alice Smith')
                && str_contains($data['content'] ?? '', 'INV-2026-001')
                && str_contains($data['content'] ?? '', '$45.50')
                && str_contains($data['content'] ?? '', 'https://zoomnearby.com/orders/INV-2026-001');
        });
    }

    public function test_query_params_payload_format_sends_get_request(): void
    {
        Http::fake([
            'https://api.whatsapp-gateway.example/*' => Http::response(['sent' => true], 200),
        ]);

        $channel = CustomNotificationChannel::create([
            'company_id' => $this->company->id,
            'name' => 'Unofficial WhatsApp GET Gateway',
            'url' => 'https://api.whatsapp-gateway.example/send-message',
            'method' => 'GET',
            'payload_format' => 'query_params',
            'headers' => [],
            'payload_template' => json_encode([
                'receiver' => '{phone}',
                'msg' => 'Invoice {invoice_id} total is {amount}',
            ]),
            'event_types' => ['invoice'],
            'is_active' => true,
        ]);

        $token = $this->token();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tenant/settings/custom-notifications/test', [
                'channel_id' => $channel->id,
                'phone' => '+15554443333',
                'invoice_id' => 'INV-888',
                'amount' => '$99.00',
            ]);

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), 'receiver=%2B15554443333')
                && str_contains($request->url(), 'INV-888');
        });
    }

    public function test_dispatch_scheduled_notifications_triggers_delayed_order_alert(): void
    {
        Http::fake([
            'https://api.sms-gateway.example/*' => Http::response(['status' => 'success'], 200),
        ]);

        CustomNotificationChannel::create([
            'company_id' => $this->company->id,
            'name' => 'Kitchen Alert Channel',
            'url' => 'https://api.sms-gateway.example/kitchen-alert',
            'method' => 'POST',
            'payload_format' => 'json',
            'headers' => ['Authorization' => 'Bearer token_kds'],
            'payload_template' => json_encode([
                'alert' => 'KDS delayed order {invoice_id} for table {customer_name}',
                'link' => '{order_link}',
            ]),
            'event_types' => ['delayed_order_alert'],
            'is_active' => true,
        ]);

        // Create delayed kitchen ticket
        $ticket = KitchenTicket::create([
            'company_id' => $this->company->id,
            'kot_number' => 'KOT-2026-99',
            'table_name' => 'Table 5',
            'service_type' => 'dine_in',
            'status' => KitchenTicket::STATUS_PENDING,
            'alarm_at' => now()->subMinutes(10),
            'alarm_sent_at' => null,
            'alarm_dismissed_at' => null,
            'items' => [
                ['name' => 'Burger', 'quantity' => 2],
            ],
        ]);

        // Mock FirebasePushService so push doesn't fail
        $this->mock(FirebasePushService::class, function ($mock) {
            $mock->shouldReceive('sendToCompany')->andReturn(1);
        });

        $this->artisan('notifications:dispatch-scheduled')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            $data = $request->data();
            return $request->url() === 'https://api.sms-gateway.example/kitchen-alert'
                && str_contains($data['alert'] ?? '', 'KOT-2026-99')
                && str_contains($data['alert'] ?? '', 'Table 5')
                && str_contains($data['link'] ?? '', 'restaurant/kds?kot=');
        });
    }

    public function test_dispatch_scheduled_notifications_triggers_due_invoice_reminder(): void
    {
        Http::fake([
            'https://api.sms-gateway.example/*' => Http::response(['status' => 'success'], 200),
        ]);

        CustomNotificationChannel::create([
            'company_id' => $this->company->id,
            'name' => 'Due Invoice Reminder Channel',
            'url' => 'https://api.sms-gateway.example/due-invoice',
            'method' => 'POST',
            'payload_format' => 'json',
            'headers' => ['Authorization' => 'Bearer token_due'],
            'payload_template' => json_encode([
                'phone' => '{phone}',
                'reminder' => 'Dear {customer_name}, invoice {invoice_id} of {amount} is overdue.',
                'link' => '{order_link}',
            ]),
            'event_types' => ['due_invoice_reminder'],
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'INV-OVERDUE-100',
            'customer_name' => 'Bob Builder',
            'total' => 250.00,
            'subtotal' => 250.00,
            'tax_total' => 0.00,
            'discount_total' => 0.00,
            'paid_amount' => 50.00,
            'due_amount' => 200.00,
            'payment_status' => 'partial',
            'status' => 'completed',
            'currency' => 'USD',
            'due_reminder_at' => now()->subHours(2),
            'due_reminder_sent_at' => null,
            'due_reminder_dismissed_at' => null,
        ]);

        // Mock FirebasePushService
        $this->mock(FirebasePushService::class, function ($mock) {
            $mock->shouldReceive('sendToCompany')->andReturn(1);
        });

        $this->artisan('notifications:dispatch-scheduled')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            $data = $request->data();
            return $request->url() === 'https://api.sms-gateway.example/due-invoice'
                && str_contains($data['reminder'] ?? '', 'Bob Builder')
                && str_contains($data['reminder'] ?? '', 'INV-OVERDUE-100')
                && str_contains($data['reminder'] ?? '', '200.00')
                && str_contains($data['link'] ?? '', 'invoices/');
        });
    }
}
