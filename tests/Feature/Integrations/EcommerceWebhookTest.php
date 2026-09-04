<?php

namespace Tests\Feature\Integrations;

use App\Models\Company;
use App\Models\Configuration;
use App\Models\KitchenTicket;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Integrations\OutboundWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EcommerceWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

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
            'unique_account_id' => 'tenant-uuid-12345',
            'name' => 'SuperStore Online',
            'trade_name' => 'SuperStore',
            'slug' => 'superstore',
            'email' => 'contact@superstore.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'plan_name' => 'standard',
            'pos_mode' => 'retail',
            'expires_at' => now()->addDays(30),
            'licensed_modules' => ['retail', 'restaurant'],
        ]);
    }

    public function test_inbound_shopify_webhook_creates_pos_sale_and_decrements_stock(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Organic Green Tea',
            'sku' => 'TEA-ORG-01',
            'current_stock' => 20.0,
            'minimum_stock' => 5.0,
            'sale_price' => 15.0,
            'cost_price' => 8.0,
            'active' => true,
        ]);

        $payload = [
            'id' => 987654321,
            'order_number' => 1001,
            'financial_status' => 'paid',
            'total_price' => '45.00',
            'customer' => [
                'first_name' => 'Sarah',
                'last_name' => 'Connor',
                'phone' => '+1555123456',
            ],
            'line_items' => [
                [
                    'title' => 'Organic Green Tea',
                    'sku' => 'TEA-ORG-01',
                    'quantity' => 3,
                    'price' => '15.00',
                ],
            ],
        ];

        $response = $this->postJson(
            "/api/v1/integrations/webhooks/{$this->company->unique_account_id}/orders",
            $payload,
            ['X-Shopify-Topic' => 'orders/create']
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_duplicate', false)
            ->assertJsonPath('platform', 'shopify')
            ->assertJsonPath('external_id', '987654321');

        $this->assertDatabaseHas('sales', [
            'company_id' => $this->company->id,
            'external_id' => '987654321',
            'customer_name' => 'Sarah Connor',
            'total' => '45.00',
            'payment_status' => 'paid',
        ]);

        // Verify stock was decremented from 20 to 17
        $this->assertEquals(17.0, (float) $product->fresh()->current_stock);
    }

    public function test_inbound_webhook_deduplicates_orders_idempotently(): void
    {
        $payload = [
            'id' => 11223344,
            'order_number' => 1002,
            'total' => '25.00',
            'customer_name' => 'John Doe',
            'items' => [
                ['name' => 'Widget', 'quantity' => 1, 'price' => 25.00],
            ],
        ];

        $first = $this->postJson("/api/v1/integrations/webhooks/{$this->company->slug}/orders", $payload);
        $first->assertOk()->assertJsonPath('is_duplicate', false);

        // Second call with identical external ID
        $second = $this->postJson("/api/v1/integrations/webhooks/{$this->company->slug}/orders", $payload);
        $second->assertOk()->assertJsonPath('is_duplicate', true);

        // Verify only 1 sale in database
        $this->assertEquals(1, Sale::where('company_id', $this->company->id)->where('external_id', '11223344')->count());
    }

    public function test_inbound_webhook_verifies_shopify_hmac_and_rejects_tampered_payload(): void
    {
        $secret = 'super-secret-hmac-key';
        Configuration::create([
            'company_id' => $this->company->id,
            'key' => 'webhook_hmac_secret',
            'value' => $secret,
        ]);

        $payload = ['id' => 555666, 'order_number' => 1003, 'total' => '10.00'];
        $rawJson = json_encode($payload);

        // 1. Invalid signature should be rejected with 401
        $badResponse = $this->call(
            'POST',
            "/api/v1/integrations/webhooks/{$this->company->id}/orders",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SHOPIFY_HMAC_SHA256' => 'invalid-base64-signature',
            ],
            $rawJson
        );
        $badResponse->assertStatus(401)
            ->assertJsonPath('success', false);

        // 2. Valid signature should pass
        $validSignature = base64_encode(hash_hmac('sha256', $rawJson, $secret, true));
        $goodResponse = $this->call(
            'POST',
            "/api/v1/integrations/webhooks/{$this->company->id}/orders",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $validSignature,
            ],
            $rawJson
        );
        $goodResponse->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_inbound_webhook_creates_kitchen_ticket_in_restaurant_mode(): void
    {
        $this->company->update(['pos_mode' => 'restaurant']);

        $payload = [
            'id' => 777888,
            'number' => 205,
            'billing' => [
                'first_name' => 'Bruce',
                'last_name' => 'Wayne',
            ],
            'line_items' => [
                ['name' => 'Chicken Alfredo Pasta', 'quantity' => 2, 'price' => '18.00', 'total' => '36.00'],
            ],
            'total' => '36.00',
        ];

        $response = $this->postJson(
            "/api/v1/integrations/webhooks/{$this->company->unique_account_id}/orders",
            $payload,
            ['X-WC-Webhook-Topic' => 'order.created']
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('platform', 'woocommerce');

        $saleId = $response->json('sale_id');
        $this->assertNotNull($saleId);

        // Verify KitchenTicket (KDS) was generated
        $this->assertDatabaseHas('kitchen_tickets', [
            'company_id' => $this->company->id,
            'sale_id' => $saleId,
            'status' => KitchenTicket::STATUS_PENDING,
            'service_type' => 'delivery',
        ]);
    }

    public function test_outbound_webhook_dispatches_signed_payload_to_external_url(): void
    {
        Http::fake([
            'https://external-erp.example.com/webhooks/pos-events' => Http::response(['received' => true], 200),
        ]);

        Configuration::create([
            'company_id' => $this->company->id,
            'key' => 'outbound_webhook_url',
            'value' => 'https://external-erp.example.com/webhooks/pos-events',
        ]);

        Configuration::create([
            'company_id' => $this->company->id,
            'key' => 'outbound_webhook_secret',
            'value' => 'my-outbound-secret-999',
        ]);

        $service = app(OutboundWebhookService::class);
        $dispatched = $service->dispatch($this->company, OutboundWebhookService::EVENT_ORDER_CREATED, [
            'sale_id' => 123,
            'sale_number' => 'ORD-123',
            'total' => 150.00,
        ]);

        $this->assertTrue($dispatched);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://external-erp.example.com/webhooks/pos-events'
                && $request->hasHeader('X-Webhook-Signature')
                && str_starts_with($request->header('X-Webhook-Signature')[0], 'sha256=')
                && $request['event'] === 'order.created'
                && $request['data']['sale_number'] === 'ORD-123';
        });
    }
}
