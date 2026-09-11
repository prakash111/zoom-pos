<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentGatewaySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Inbound receiver for the platform's subscription payment gateways
 * (Stripe / PayPal / Razorpay / Mercado Pago). The URL for each gateway is
 * surfaced, copyable, on its card in Super Admin ▸ Payment Gateways so the
 * operator can paste it into the provider's developer console.
 *
 * Scope: authenticate the caller (per-gateway signature against the stored
 * `webhook_secret`), record the event to the audit log, and acknowledge with
 * 200 so the provider's "send test webhook / verify endpoint" check passes.
 * Plan activation itself still runs through the synchronous verify calls in
 * SubscriptionPaymentGatewayService (Billing\Index); this endpoint is the
 * audited confirmation channel, not a second activation path.
 */
class SubscriptionWebhookController extends Controller
{
    /** Gateways with a real integration in this codebase. */
    public const GATEWAYS = ['stripe', 'paypal', 'razorpay', 'mercadopago'];

    /**
     * POST /api/v1/webhooks/{gateway}
     */
    public function handle(Request $request, string $gateway): JsonResponse
    {
        $gateway = strtolower(trim($gateway));

        if (! in_array($gateway, self::GATEWAYS, true)) {
            return response()->json(['error' => 'unknown_gateway'], 404);
        }

        $setting = PaymentGatewaySetting::query()->where('gateway', $gateway)->first();
        $secret = $setting?->webhook_secret ? (string) $setting->webhook_secret : '';

        if ($secret !== '' && ! $this->signatureIsValid($gateway, $request, $secret)) {
            AuditLog::record('subscription.webhook_rejected', null, null, [
                'gateway' => $gateway,
                'reason' => 'invalid_signature',
                'ip' => $request->ip(),
            ], 'failed');

            return response()->json(['error' => 'invalid_signature'], 400);
        }

        $payload = $request->json()->all() ?: $request->all();
        $eventType = $this->eventType($gateway, $payload, $request);

        AuditLog::record('subscription.webhook_received', null, null, [
            'gateway' => $gateway,
            'event' => $eventType,
            'reference' => $this->paymentReference($gateway, $payload),
            'signature_verified' => $secret !== '',
        ]);

        Log::info("Subscription webhook [{$gateway}] received", [
            'event' => $eventType,
        ]);

        return response()->json(['received' => true, 'gateway' => $gateway, 'event' => $eventType]);
    }

    /**
     * GET /subscription/payment/callback/{gateway}
     *
     * Browser return URL after a hosted checkout / redirect flow. The actual
     * verification happens on the billing page it lands on.
     */
    public function callback(Request $request, string $gateway): RedirectResponse
    {
        $gateway = strtolower(trim($gateway));
        $status = $request->query('status')
            ?: ($request->has('razorpay_payment_id') || $request->has('payment_id') ? 'processing' : 'returned');

        return redirect()
            ->route('tenant.billing.index', array_filter([
                'gateway' => in_array($gateway, self::GATEWAYS, true) ? $gateway : null,
                'payment_status' => $status,
            ]))
            ->with('status', 'Returned from '.ucfirst($gateway).' — verifying your payment…');
    }

    // ---- signature verification -------------------------------------------

    private function signatureIsValid(string $gateway, Request $request, string $secret): bool
    {
        $body = $request->getContent();

        return match ($gateway) {
            // Stripe: t=<ts>,v1=<hmac_sha256("{t}.{body}")>
            'stripe' => $this->verifyStripe($request->header('Stripe-Signature', ''), $body, $secret),
            // Razorpay: X-Razorpay-Signature = hmac_sha256(body)
            'razorpay' => hash_equals(
                hash_hmac('sha256', $body, $secret),
                (string) $request->header('X-Razorpay-Signature', '')
            ),
            // Mercado Pago: ts=<ts>,v1=<hmac> over the manifest — best effort:
            // accept when the v1 digest matches hmac_sha256(body).
            'mercadopago' => $this->verifyKeyedV1($request->header('X-Signature', ''), $body, $secret),
            // PayPal verifies via an API round-trip (transmission id + certs),
            // not a shared HMAC. With only a shared secret configured, fall
            // back to a bearer-style shared token in the Authorization header.
            'paypal' => hash_equals($secret, trim(str_ireplace('Bearer ', '', (string) $request->header('Authorization', '')))),
            default => false,
        };
    }

    private function verifyStripe(string $header, string $body, string $secret): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $kv) {
            [$k, $v] = array_pad(explode('=', trim($kv), 2), 2, '');
            $parts[$k] = $v;
        }
        $timestamp = $parts['t'] ?? '';
        $provided = $parts['v1'] ?? '';
        if ($timestamp === '' || $provided === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', "{$timestamp}.{$body}", $secret), $provided);
    }

    private function verifyKeyedV1(string $header, string $body, string $secret): bool
    {
        $v1 = '';
        foreach (explode(',', $header) as $kv) {
            [$k, $v] = array_pad(explode('=', trim($kv), 2), 2, '');
            if ($k === 'v1') {
                $v1 = $v;
            }
        }

        return $v1 !== '' && hash_equals(hash_hmac('sha256', $body, $secret), $v1);
    }

    // ---- payload helpers -------------------------------------------------

    private function eventType(string $gateway, array $payload, Request $request): string
    {
        return match ($gateway) {
            'stripe' => (string) ($payload['type'] ?? 'unknown'),
            'razorpay' => (string) ($payload['event'] ?? 'unknown'),
            'paypal' => (string) ($payload['event_type'] ?? 'unknown'),
            'mercadopago' => (string) ($payload['type'] ?? $request->query('type', 'unknown')),
            default => 'unknown',
        };
    }

    private function paymentReference(string $gateway, array $payload): ?string
    {
        return match ($gateway) {
            'stripe' => $payload['data']['object']['id'] ?? null,
            'razorpay' => $payload['payload']['payment']['entity']['id']
                ?? $payload['payload']['order']['entity']['id'] ?? null,
            'paypal' => $payload['resource']['id'] ?? null,
            'mercadopago' => isset($payload['data']['id']) ? (string) $payload['data']['id'] : null,
            default => null,
        };
    }
}
