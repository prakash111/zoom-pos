<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Configuration;
use App\Services\Integrations\EcommercePayloadNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EcommerceWebhookController extends Controller
{
    public function handleOrders(
        Request $request,
        string $tenant_uuid,
        EcommercePayloadNormalizer $normalizer
    ): JsonResponse {
        // 1. Resolve Company Context
        $company = Company::withoutGlobalScopes()
            ->where('unique_account_id', $tenant_uuid)
            ->orWhere('id', $tenant_uuid)
            ->orWhere('slug', $tenant_uuid)
            ->first();

        if (! $company) {
            return response()->json([
                'success' => false,
                'error' => 'Tenant not found for provided identifier.',
            ], 404);
        }

        // 2. HMAC Verification
        $secret = Configuration::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('key', 'webhook_hmac_secret')
            ->value('value');

        if (! empty($secret)) {
            $rawContent = $request->getContent();
            $isValid = $this->verifyHmac($request, $rawContent, $secret);

            if (! $isValid) {
                Log::warning("HMAC validation failed for incoming webhook on company {$company->id}");

                return response()->json([
                    'success' => false,
                    'error' => 'Invalid webhook HMAC signature.',
                ], 401);
            }
        }

        // 3. Process & Normalize Payload
        $payload = $request->all();
        if (empty($payload)) {
            $json = json_decode($request->getContent(), true);
            if (is_array($json)) {
                $payload = $json;
            }
        }

        $result = $normalizer->process($company, $payload, $request->headers->all());

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    protected function verifyHmac(Request $request, string $rawContent, string $secret): bool
    {
        // Shopify Header: X-Shopify-Hmac-Sha256
        $shopifySig = $request->header('X-Shopify-Hmac-Sha256') ?: $request->header('x-shopify-hmac-sha256');
        if (! empty($shopifySig)) {
            $expected = base64_encode(hash_hmac('sha256', $rawContent, $secret, true));

            return hash_equals($expected, (string) $shopifySig);
        }

        // WooCommerce Header: X-WC-Webhook-Signature
        $wcSig = $request->header('X-WC-Webhook-Signature') ?: $request->header('x-wc-webhook-signature');
        if (! empty($wcSig)) {
            $expected = base64_encode(hash_hmac('sha256', $rawContent, $secret, true));

            return hash_equals($expected, (string) $wcSig);
        }

        // Generic Webhook Headers
        $genericSig = $request->header('X-Webhook-Signature')
            ?: $request->header('X-Signature')
            ?: $request->header('X-Hub-Signature-256')
            ?: $request->header('x-hub-signature');

        if (! empty($genericSig)) {
            $cleaned = preg_replace('/^sha256=/', '', $genericSig);
            $expectedHex = hash_hmac('sha256', $rawContent, $secret);
            $expectedBase64 = base64_encode(hash_hmac('sha256', $rawContent, $secret, true));

            return hash_equals($expectedHex, $cleaned) || hash_equals($expectedBase64, $genericSig);
        }

        // Secret was required but no supported signature header was supplied
        return false;
    }
}
