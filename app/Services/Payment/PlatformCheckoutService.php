<?php

namespace App\Services\Payment;

use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Platform-owner checkout primitives (SuperAdmin buying add-on modules).
 *
 * Deliberately context-free: no Company / Plan. Reuses the platform-global
 * gateway credentials in `payment_gateway_settings` — the same store the tenant
 * subscription flow (SubscriptionPaymentGatewayService) uses — but takes plain
 * scalars so it can be driven from the module marketplace.
 */
class PlatformCheckoutService
{
    public function getGatewaySetting(string $gateway): ?PaymentGatewaySetting
    {
        return PaymentGatewaySetting::where('gateway', $gateway)->where('enabled', true)->first();
    }

    /**
     * @return array<string, array{gateway: string, mode: ?string, public_key: ?string, has_secret: bool}>
     */
    public function getEnabledGateways(): array
    {
        $gateways = [];
        foreach (PaymentGatewaySetting::where('enabled', true)->get() as $setting) {
            $gateways[$setting->gateway] = [
                'gateway' => $setting->gateway,
                'mode' => $setting->mode,
                'public_key' => $setting->public_key,
                'has_secret' => filled($setting->secret_key),
            ];
        }

        return $gateways;
    }

    /**
     * @param  array<string, mixed>  $notes
     * @return array{order_id: string, key_id: string, amount: int, currency: string}
     */
    public function createRazorpayOrder(float $amount, string $currency, array $notes = []): array
    {
        $setting = $this->getGatewaySetting('razorpay');
        if (! $setting || empty($setting->public_key) || empty($setting->secret_key)) {
            throw new RuntimeException('Razorpay payment gateway is not enabled or configured.');
        }

        $response = Http::withBasicAuth($setting->public_key, $setting->secret_key)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => (int) round($amount * 100),
                'currency' => strtoupper($currency ?: 'USD'),
                'receipt' => 'mod_'.substr(md5(uniqid('', true)), 0, 16),
                'notes' => $notes,
            ]);

        if (! $response->successful()) {
            $err = $response->json();
            throw new RuntimeException('Razorpay order creation failed: '.($err['error']['description'] ?? $err['message'] ?? 'unknown error'));
        }

        $data = $response->json();

        return [
            'order_id' => $data['id'],
            'key_id' => $setting->public_key,
            'amount' => (int) $data['amount'],
            'currency' => $data['currency'],
        ];
    }

    /**
     * @return array{verified: bool, payment_id: string, order_id: string, amount: float, currency: string, method: string}
     */
    public function verifyRazorpayPayment(string $paymentId, string $orderId, string $signature): array
    {
        $setting = $this->getGatewaySetting('razorpay');
        if (! $setting || empty($setting->secret_key)) {
            throw new RuntimeException('Razorpay gateway secret key is missing.');
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $setting->secret_key);
        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid Razorpay signature. Payment verification failed.');
        }

        $response = Http::withBasicAuth($setting->public_key, $setting->secret_key)
            ->get("https://api.razorpay.com/v1/payments/{$paymentId}");

        $data = $response->successful() ? $response->json() : [];
        $status = $data['status'] ?? 'unknown';

        if ($status === 'authorized') {
            $capture = Http::withBasicAuth($setting->public_key, $setting->secret_key)
                ->post("https://api.razorpay.com/v1/payments/{$paymentId}/capture", [
                    'amount' => $data['amount'] ?? 0,
                    'currency' => $data['currency'] ?? 'USD',
                ]);
            if ($capture->successful()) {
                $data = $capture->json();
            }
        }

        return [
            'verified' => true,
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'amount' => ($data['amount'] ?? 0) / 100,
            'currency' => $data['currency'] ?? 'USD',
            'method' => $data['method'] ?? 'razorpay',
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{payment_intent_id: string, client_secret: string, publishable_key: ?string}
     */
    public function createStripePaymentIntent(float $amount, string $currency, string $description, array $metadata = []): array
    {
        $setting = $this->getGatewaySetting('stripe');
        if (! $setting || blank($setting->secret_key)) {
            throw new RuntimeException('Stripe payment gateway is not enabled or configured.');
        }

        $response = Http::withToken($setting->secret_key)
            ->asForm()
            ->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => (int) round($amount * 100),
                'currency' => strtolower($currency ?: 'usd'),
                'description' => $description,
                'payment_method_types' => ['card'],
                'metadata' => $metadata,
            ]);

        if (! $response->successful()) {
            $err = $response->json();
            throw new RuntimeException('Stripe error: '.($err['error']['message'] ?? 'payment intent failed.'));
        }

        $pi = $response->json();

        return [
            'payment_intent_id' => $pi['id'],
            'client_secret' => $pi['client_secret'],
            'publishable_key' => $setting->public_key,
        ];
    }

    /**
     * @return array{verified: bool, payment_id: string, amount: float, currency: string, method: string}
     */
    public function verifyStripePaymentIntent(string $paymentIntentId): array
    {
        $setting = $this->getGatewaySetting('stripe');
        if (! $setting || blank($setting->secret_key)) {
            throw new RuntimeException('Stripe gateway is not configured.');
        }

        $pi = Http::withToken($setting->secret_key)
            ->get("https://api.stripe.com/v1/payment_intents/{$paymentIntentId}")
            ->throw()
            ->json();

        if (($pi['status'] ?? null) !== 'succeeded') {
            throw new RuntimeException('Stripe payment is not completed (status: '.($pi['status'] ?? 'unknown').').');
        }

        return [
            'verified' => true,
            'payment_id' => $pi['id'],
            'amount' => ($pi['amount_received'] ?? $pi['amount'] ?? 0) / 100,
            'currency' => strtoupper($pi['currency'] ?? 'USD'),
            'method' => 'stripe',
        ];
    }
}
