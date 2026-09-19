<?php

namespace Tests\Feature\Webhooks;

use App\Models\AuditLog;
use App\Models\PaymentGatewaySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_gateway_is_404(): void
    {
        $this->postJson('/api/v1/webhooks/paytm', ['x' => 1])->assertStatus(404);
    }

    public function test_webhook_without_a_configured_secret_is_accepted_and_audited(): void
    {
        $res = $this->postJson('/api/v1/webhooks/stripe', [
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_123']],
        ]);

        $res->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('gateway', 'stripe')
            ->assertJsonPath('event', 'checkout.session.completed');

        $log = AuditLog::where('action', 'subscription.webhook_received')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('stripe', $log->details['gateway']);
        $this->assertSame('cs_test_123', $log->details['reference']);
        $this->assertFalse($log->details['signature_verified']);
    }

    public function test_stripe_signature_is_verified_when_a_secret_is_set(): void
    {
        PaymentGatewaySetting::create([
            'gateway' => 'stripe', 'enabled' => true, 'mode' => 'test',
            'webhook_secret' => 'whsec_abc',
        ]);

        $body = json_encode(['type' => 'invoice.payment_succeeded', 'data' => ['object' => ['id' => 'in_1']]]);
        $ts = time();
        $sig = hash_hmac('sha256', "{$ts}.{$body}", 'whsec_abc');

        // Bad signature -> 400 + rejection audit
        $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$ts},v1=deadbeef",
        ], $body)->assertStatus(400)->assertJsonPath('error', 'invalid_signature');

        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.webhook_rejected']);

        // Correct signature -> 200
        $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$ts},v1={$sig}",
        ], $body)->assertOk()->assertJsonPath('received', true);
    }

    public function test_razorpay_hmac_signature_is_verified(): void
    {
        PaymentGatewaySetting::create([
            'gateway' => 'razorpay', 'enabled' => true, 'mode' => 'test',
            'webhook_secret' => 'rzp_whsec',
        ]);

        $body = json_encode([
            'event' => 'order.paid',
            'payload' => ['order' => ['entity' => ['id' => 'order_abc']]],
        ]);
        $sig = hash_hmac('sha256', $body, 'rzp_whsec');

        $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $sig,
        ], $body)->assertOk()->assertJsonPath('event', 'order.paid');

        $this->call('POST', '/api/v1/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => 'wrong',
        ], $body)->assertStatus(400);
    }

    public function test_browser_callback_redirects_to_billing(): void
    {
        $this->get('/subscription/payment/callback/razorpay?status=success')
            ->assertRedirect(route('tenant.billing.index', ['gateway' => 'razorpay', 'payment_status' => 'success']));
    }
}
