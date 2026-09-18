<?php

namespace Tests\Feature\Api;

use App\Models\CustomNotificationChannel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\KitchenTicket;
use App\Models\MessageQueue;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\TenantNotificationGateway;
use App\Models\TenantApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class KotDocumentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private TenantApiKey $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        Plan::create([
            'name' => 'restaurant-delivery', 'display_name' => 'Restaurant', 'price' => 0,
            'currency' => 'USD', 'billing_cycle' => 'monthly', 'duration_days' => 30,
            'features' => ['pos' => true], 'limits' => ['products' => 100, 'users' => 5], 'active' => true,
        ]);
        $this->company = Company::create([
            'name' => 'Copper Kettle Café', 'slug' => 'copper-kettle', 'email' => 'pos@copperkettle.test',
            'country' => 'US', 'currency' => 'USD', 'currency_symbol' => '$', 'pos_mode' => 'restaurant',
            'plan_name' => 'restaurant-delivery', 'expires_at' => now()->addMonth(), 'licensed_modules' => ['restaurant'],
        ]);
        $user = User::factory()->create(['company_id' => $this->company->id, 'role' => 'admin']);
        $this->apiKey = TenantApiKey::create([
            'company_id' => $this->company->id, 'user_id' => $user->id, 'name' => 'Restaurant POS',
            'token' => 'zk_live_kot_delivery', 'permissions' => ['*'], 'active' => true,
        ]);
    }

    private function ticket(): KitchenTicket
    {
        $customer = Customer::create([
            'company_id' => $this->company->id, 'name' => 'Restaurant Guest',
            'phone' => '+15559876543', 'email' => 'guest@example.test',
        ]);
        $sale = Sale::create([
            'company_id' => $this->company->id, 'sale_number' => 'ORD-KOT-DELIVERY',
            'customer_id' => $customer->id, 'total' => 25, 'operation_type' => 'sale', 'status' => 'pending',
        ]);

        return KitchenTicket::create([
            'company_id' => $this->company->id, 'sale_id' => $sale->id, 'kot_number' => 'KOT-DELIVERY-001',
            'table_name' => 'Table 5', 'service_type' => 'dine_in', 'server_name' => 'Kitchen Server',
            'status' => KitchenTicket::STATUS_PENDING, 'sent_to_kitchen_at' => now(), 'prep_minutes' => 15,
            'kitchen_notes' => "Guest says \"no peanuts\"\nServe together",
            'items' => [[
                'name' => 'Truffle Mushroom Burger with Extra Fries', 'quantity' => 2, 'price' => 12.5,
                'variant' => 'Large', 'modifiers' => [['name' => 'Extra Cheese']], 'spice_level' => 'Mild',
                'note' => "No onion\nSauce on the side", 'seat' => 2,
            ]],
        ]);
    }

    private function custom(string $name, string $url): CustomNotificationChannel
    {
        return CustomNotificationChannel::create([
            'company_id' => $this->company->id, 'name' => $name, 'url' => $url, 'method' => 'POST',
            'payload_format' => 'json', 'is_active' => true, 'event_types' => ['kot'],
            'payload_template' => json_encode([
                'text' => '{message}', 'ticket' => '{kot_number}', 'table' => '{table_name}',
                'items' => '{items}', 'phone' => '{phone}',
            ]),
        ]);
    }

    private function fakeDelivery(int $status = 200): void
    {
        Mail::fake();
        Http::fake(fn () => Http::response(['success' => $status === 200, 'messages' => [['id' => 'wamid.kot']]], $status));
        config(['mail.mailers.smtp.host' => null]);
        $this->withToken($this->apiKey->token);
    }

    public function test_preview_custom_channel_checkbox_sends_only_the_selected_channel_with_valid_json(): void
    {
        $this->fakeDelivery();
        $ticket = $this->ticket();
        $selected = $this->custom('Kitchen Telegram', 'https://kitchen.example.test/send');
        $other = $this->custom('Office Telegram', 'https://office.example.test/send');

        $sheet = $this->getJson("/api/v1/tenant/documents/kot/{$ticket->id}/preview-modal")->assertOk();
        $tile = collect($sheet->json('schema.components'))->firstWhere('channel_id', $selected->id);
        $this->assertNotNull($tile);
        $this->assertSame('checkbox', $tile['type']);
        $this->assertArrayNotHasKey('action', $tile);
        $batch = collect($sheet->json('schema.components'))->firstWhere('id', 'dispatch_selected_channels');
        $this->postJson($batch['action']['endpoint'], $batch['action']['data'] + ['channels' => [$tile['selection_value']]])
            ->assertOk()->assertJsonPath('status', 'sent')->assertJsonPath("results.custom_{$selected->id}.success", true);

        Http::assertSent(function ($request) use ($ticket) {
            $data = json_decode($request->body(), true);

            return $request->url() === 'https://kitchen.example.test/send'
                && is_array($data)
                && $data['ticket'] === $ticket->kot_number
                && $data['table'] === 'Table 5'
                && $data['phone'] === '+15559876543'
                && $data['items'][0]['quantity'] === 2
                && str_contains($data['text'], 'Truffle Mushroom Burger with Extra Fries')
                && str_contains($data['text'], 'Variant: Large')
                && str_contains($data['text'], 'Extra Cheese')
                && str_contains($data['text'], 'Spice: Mild')
                && str_contains($data['text'], "No onion\nSauce on the side")
                && str_contains($data['text'], "Guest says \"no peanuts\"\nServe together");
        });
        Http::assertNotSent(fn ($request) => $request->url() === $other->url);
        Mail::assertNothingSent();
        $this->assertSame(1, MessageQueue::withoutGlobalScopes()->where('status', 'sent')->count());

        $options = $this->getJson("/api/v1/documents/kot/{$ticket->id}/dispatch-options")->assertOk();
        $this->assertSame($selected->id, $options->json("channels.custom:{$selected->id}.channel_id"));
        $this->assertSame($other->id, $options->json("channels.custom:{$other->id}.channel_id"));
        $channels = $this->getJson('/api/v1/documents/channels')->assertOk();
        $this->assertSame($selected->id, $channels->json("channels.custom:{$selected->id}.channel_id"));
        $this->assertSame($other->id, $channels->json("channels.custom:{$other->id}.channel_id"));
    }

    public function test_all_delivery_routes_accept_kot_and_preserve_the_selected_sms_channel(): void
    {
        $this->fakeDelivery();
        $ticket = $this->ticket();
        TenantNotificationGateway::create([
            'company_id' => $this->company->id, 'channel' => 'sms', 'provider' => 'generic_http',
            'is_enabled' => true, 'credentials' => ['url' => 'https://sms.example.test/send'],
        ]);
        TenantNotificationGateway::create([
            'company_id' => $this->company->id, 'channel' => 'whatsapp', 'provider' => 'meta_cloud_api',
            'is_enabled' => true, 'credentials' => ['phone_number_id' => '12345', 'access_token' => 'kot-token'],
        ]);

        foreach ([
            ['/api/v1/documents/dispatch', ['document_type' => 'kot', 'document_id' => $ticket->id, 'channels' => ['sms'], 'phone' => '+15551234567']],
            ['/api/v1/tenant/dispatch/send', ['type' => 'kot', 'id' => $ticket->id, 'channel' => 'sms', 'recipient' => '+15551234567']],
            ["/api/v1/tenant/dispatch/kot/{$ticket->id}", ['channel' => 'sms', 'phone' => '+15551234567']],
            ['/api/v1/pos/send-delivery', ['type' => 'sms', 'document_type' => 'kot', 'document_id' => $ticket->id, 'recipient' => '+15551234567']],
            ['/api/v1/tenant/notifications/dispatch', ['document_type' => 'kot', 'document_id' => $ticket->id, 'channels' => ['sms'], 'recipient_phone' => '+15551234567']],
            ['/api/v1/tenant/dispatch/batch-send', ['document_type' => 'kot', 'document_id' => $ticket->id, 'channels' => ['sms'], 'phone' => '+15551234567']],
        ] as [$endpoint, $payload]) {
            $response = $this->postJson($endpoint, $payload)->assertOk()
                ->assertJsonPath('document_type', 'kot')->assertJsonPath('results.sms.status', 'sent');
            $this->assertSame(['sms'], array_keys($response->json('results')));
        }
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://sms.example.test/send')
            && str_contains($request->data()['message'] ?? '', 'KOT-DELIVERY-001') && str_contains($request->data()['message'] ?? '', 'Extra Cheese')
            && ($request->data()['phone'] ?? '') === '15551234567');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
        Mail::assertNothingSent();
        $this->assertSame(KitchenTicket::STATUS_PENDING, $ticket->fresh()->status);
    }

    public function test_disabled_gateways_open_device_apps_with_the_complete_kot(): void
    {
        $this->fakeDelivery();
        $ticket = $this->ticket();
        $sheet = $this->getJson("/api/v1/tenant/documents/kot/{$ticket->id}/preview-modal")->assertOk();
        foreach (['whatsapp' => 'whatsapp://send?', 'sms' => 'sms:', 'email' => 'mailto:'] as $channel => $prefix) {
            $tile = collect($sheet->json('schema.components'))->firstWhere('channel', $channel);
            $this->assertSame('OPEN_URL', $tile['action']['type']);
            $this->assertArrayNotHasKey('endpoint', $tile['action']);
            $this->assertStringStartsWith($prefix, $tile['action']['url']);
            $this->assertStringContainsString('Extra Cheese', rawurldecode($tile['action']['url']));
            $response = $this->postJson('/api/v1/tenant/dispatch/send', [
                'type' => 'kot', 'id' => $ticket->id, 'channel' => $channel,
            ])->assertOk()->assertJsonPath('status', 'manual_link');
            $this->assertSame([$channel], array_keys($response->json('results')));
            $this->assertStringContainsString('Serve together', rawurldecode($response->json('url')));
        }
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'http://localhost:4000/'));
        Mail::assertNothingSent();
        $this->assertSame(0, MessageQueue::withoutGlobalScopes()->count());
    }

    public function test_creation_delivers_the_kot_to_the_selected_custom_channel(): void
    {
        $this->fakeDelivery();
        $channel = $this->custom('Kitchen', 'https://kitchen.example.test/send');
        $response = $this->postJson('/api/v1/pos/restaurant/orders/send-to-kitchen', [
            'service_type' => 'takeaway', 'channels' => ['custom:'.$channel->id],
            'notes' => "Guest says \"no peanuts\"\nServe together",
            'items' => [['name' => 'Soup', 'quantity' => 2, 'price' => 5, 'note' => 'No salt']],
        ])->assertCreated()->assertJsonPath('delivery_success', true)->assertJsonPath('delivery_status', 'sent');
        $number = $response->json('kot.kot_number');
        Http::assertSent(fn ($request) => $request->url() === $channel->url
            && $request->data()['ticket'] === $number
            && str_contains($request->data()['text'], '2 × Soup')
            && str_contains($request->data()['text'], 'No salt')
            && str_contains($request->data()['text'], 'Serve together'));
    }

    public function test_custom_channel_failure_is_reported_without_falling_back_to_another_channel(): void
    {
        $this->fakeDelivery(500);
        $ticket = $this->ticket();
        $channel = $this->custom('Kitchen', 'https://kitchen.example.test/send');
        $this->postJson('/api/v1/documents/dispatch', [
            'document_type' => 'kot', 'document_id' => $ticket->id, 'channel' => 'custom', 'channel_id' => $channel->id,
        ])->assertUnprocessable()->assertJsonPath('success', false)->assertJsonPath('status', 'failed')
            ->assertJsonPath("results.custom_{$channel->id}.success", false);
        $this->assertDatabaseHas('message_queue', ['company_id' => $this->company->id, 'status' => 'failed']);
        Mail::assertNothingSent();
    }

    public function test_unavailable_channels_and_other_tenant_tickets_are_rejected(): void
    {
        $this->fakeDelivery();
        $ticket = $this->ticket();
        $channel = $this->custom('Disabled Kitchen', 'https://kitchen.example.test/send');
        $channel->update(['is_active' => false]);
        $payload = ['document_type' => 'kot', 'document_id' => $ticket->id];
        $this->postJson('/api/v1/documents/dispatch', $payload + ['channels' => ['custom:'.$channel->id]])
            ->assertUnprocessable()->assertJsonPath('success', false);
        $this->postJson('/api/v1/documents/dispatch', $payload + ['channels' => ['custom:99999']])
            ->assertUnprocessable()->assertJsonPath('success', false);
        $this->postJson('/api/v1/documents/dispatch', $payload + ['channels' => []])->assertUnprocessable();
        $otherCompany = Company::create(['name' => 'Other Restaurant', 'slug' => 'other-restaurant']);
        $ticket->update(['company_id' => $otherCompany->id]);
        $this->postJson('/api/v1/documents/dispatch', $payload + ['channels' => ['whatsapp']])->assertNotFound();
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'http://localhost:4000/'));
        Mail::assertNothingSent();
    }

    public function test_configured_whatsapp_sends_the_full_kot_through_the_selected_gateway(): void
    {
        $this->fakeDelivery();
        $ticket = $this->ticket();
        TenantNotificationGateway::create([
            'company_id' => $this->company->id, 'channel' => 'whatsapp', 'provider' => 'meta_cloud_api',
            'is_enabled' => true, 'credentials' => ['phone_number_id' => '12345', 'access_token' => 'kot-token'],
        ]);
        $sheet = $this->getJson("/api/v1/tenant/documents/kot/{$ticket->id}/preview-modal")->assertOk();
        $tile = collect($sheet->json('schema.components'))->firstWhere('channel', 'whatsapp');
        $this->assertTrue($tile['api_enabled']);
        $this->assertSame('checkbox', $tile['type']);
        $this->assertArrayNotHasKey('action', $tile);
        $batch = collect($sheet->json('schema.components'))->firstWhere('id', 'dispatch_selected_channels');
        $this->postJson($batch['action']['endpoint'], $batch['action']['data'] + ['channels' => [$tile['selection_value']]])->assertOk()
            ->assertJsonPath('status', 'sent')->assertJsonPath('results.whatsapp.success', true);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/12345/messages')
            && ($request->data()['to'] ?? '') === '15559876543'
            && str_contains($request->data()['text']['body'] ?? '', 'KOT-DELIVERY-001')
            && str_contains($request->data()['text']['body'] ?? '', "Guest says \"no peanuts\"\nServe together"));
        Mail::assertNothingSent();
    }

    public function test_explicit_webhook_selection_sends_even_when_automatic_event_subscriptions_differ(): void
    {
        $this->fakeDelivery();
        $ticket = $this->ticket();
        TenantNotificationGateway::create([
            'company_id' => $this->company->id, 'channel' => TenantNotificationGateway::CHANNEL_WEBHOOK,
            'provider' => TenantNotificationGateway::PROVIDER_WEBHOOK, 'is_enabled' => true,
            'credentials' => ['url' => 'https://webhook.example.test/kitchen', 'event_types' => ['receipt_generated']],
        ]);
        $this->postJson('/api/v1/tenant/dispatch/send', [
            'type' => 'kot', 'id' => $ticket->id, 'channel' => 'webhook',
        ])->assertOk()->assertJsonPath('results.webhook.status', 'sent');
        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            return $request->url() === 'https://webhook.example.test/kitchen'
                && ($payload['event'] ?? '') === 'kot_created'
                && ($payload['data']['kot_number'] ?? '') === 'KOT-DELIVERY-001'
                && ($payload['data']['items'][0]['quantity'] ?? 0) === 2
                && str_contains($payload['data']['message'] ?? '', 'Extra Cheese');
        });
    }

    public function test_configured_email_delivery_keeps_kot_details_and_escapes_customer_notes(): void
    {
        $this->fakeDelivery();
        $ticket = $this->ticket();
        $ticket->update(['kitchen_notes' => '<script>alert("test")</script>']);
        TenantNotificationGateway::create([
            'company_id' => $this->company->id, 'channel' => 'email', 'provider' => 'smtp', 'is_enabled' => true,
            'credentials' => ['host' => 'smtp.example.test', 'username' => 'owner@example.test'],
        ]);
        $this->partialMock(\App\Services\Notifications\TenantNotificationDispatcherService::class, function ($mock) {
            $mock->shouldReceive('dispatchEmail')->once()->withArgs(function ($company, $email, $subject, $html) {
                return $company->id === $this->company->id && $email === 'guest@example.test'
                    && str_contains($subject, 'KOT-DELIVERY-001') && str_contains($html, 'Extra Cheese')
                    && str_contains($html, '&lt;script&gt;') && ! str_contains($html, '<script>');
            })->andReturn(['success' => true, 'status' => 'sent', 'channel' => 'email']);
        });
        $this->postJson('/api/v1/tenant/dispatch/send', [
            'type' => 'kot', 'id' => $ticket->id, 'channel' => 'email',
        ])->assertOk()->assertJsonPath('results.email.status', 'sent')->assertJsonCount(1, 'results');
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'http://localhost:4000/'));
    }

    public function test_sms_get_gateways_preserve_existing_query_parameters_and_send_ticket_content(): void
    {
        $this->fakeDelivery();
        $ticket = $this->ticket();
        $gateway = TenantNotificationGateway::create([
            'company_id' => $this->company->id, 'channel' => 'sms', 'provider' => 'generic_http', 'is_enabled' => true,
        ]);
        foreach ([
            ['https://sms.example.test/full?to={phone}&text={message}&key=configured-key', 'to', 'text'],
            ['https://sms.example.test/partial?to={phone}&key=configured-key', 'to', 'message'],
            ['https://sms.example.test/plain?key=configured-key', 'phone', 'message'],
        ] as [$url, $phoneField, $messageField]) {
            $gateway->update(['credentials' => ['url' => $url, 'method' => 'GET']]);
            $this->postJson('/api/v1/tenant/dispatch/send', [
                'type' => 'kot', 'id' => $ticket->id, 'channel' => 'sms', 'recipient' => '+15551234567',
            ])->assertOk()->assertJsonPath('results.sms.status', 'sent');
            $path = parse_url($url, PHP_URL_PATH);
            Http::assertSent(function ($request) use ($path, $phoneField, $messageField) {
                $data = $request->data();

                return parse_url($request->url(), PHP_URL_PATH) === $path
                    && ($data['key'] ?? '') === 'configured-key'
                    && ($data[$phoneField] ?? '') === '15551234567'
                    && str_contains($data[$messageField] ?? '', 'KOT-DELIVERY-001')
                    && str_contains($data[$messageField] ?? '', 'Extra Cheese');
            });
        }
    }
}
