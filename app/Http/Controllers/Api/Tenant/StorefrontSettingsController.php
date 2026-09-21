<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StorefrontSettingsController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * GET /api/v1/tenant/storefront/banner-auth
     */
    public function getBannerAuth(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        return response()->json([
            'success' => true,
            'data' => [
                'banner_tag' => $company->store_banner_tag ?: 'SPECIAL STORE DEALS',
                'banner_title' => $company->store_banner_title ?: 'Grab Up To 50% Off On Selected Products',
                'banner_subtitle' => $company->store_banner_subtitle ?: 'Explore our curated selection of high-quality products.',
                'cta_text' => $company->store_banner_cta_text ?: 'Shop Now',
                'cta_link' => $company->store_banner_cta_link ?: '#products',
                'banner_image_url' => $company->store_banner_image_url ?: '',
                'is_active' => (bool) ($company->store_banner_is_active ?? true),
                'auth_banner_url' => 'https://saas.zoomnearby.com/assets/images/landing/auth-banner.png',
                'enable_google_login' => (bool) ($company->enable_google_login ?? false),
                'google_client_id' => $company->google_client_id ?: '',
                'require_auth_before_checkout' => (bool) ($company->require_customer_verification ?? false),
            ],
        ]);
    }

    /**
     * POST /api/v1/tenant/storefront/banner-auth
     */
    public function updateBannerAuth(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'banner_tag' => ['nullable', 'string', 'max:150'],
            'store_banner_tag' => ['nullable', 'string', 'max:150'],
            'banner_title' => ['nullable', 'string', 'max:255'],
            'store_banner_title' => ['nullable', 'string', 'max:255'],
            'banner_subtitle' => ['nullable', 'string', 'max:500'],
            'store_banner_subtitle' => ['nullable', 'string', 'max:500'],
            'cta_text' => ['nullable', 'string', 'max:100'],
            'store_banner_cta_text' => ['nullable', 'string', 'max:100'],
            'cta_link' => ['nullable', 'string', 'max:255'],
            'store_banner_cta_link' => ['nullable', 'string', 'max:255'],
            'banner_image_url' => ['nullable', 'string', 'max:500'],
            'store_banner_image_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable'],
            'store_banner_is_active' => ['nullable'],
            'enable_google_login' => ['nullable'],
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:500'],
            'require_auth_before_checkout' => ['nullable'],
            'require_customer_verification' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $updates = [];

        if ($request->has('banner_tag') || $request->has('store_banner_tag')) {
            $updates['store_banner_tag'] = trim((string) ($request->input('banner_tag') ?? $request->input('store_banner_tag') ?? ''));
        }
        if ($request->has('banner_title') || $request->has('store_banner_title')) {
            $updates['store_banner_title'] = trim((string) ($request->input('banner_title') ?? $request->input('store_banner_title') ?? ''));
        }
        if ($request->has('banner_subtitle') || $request->has('store_banner_subtitle')) {
            $updates['store_banner_subtitle'] = trim((string) ($request->input('banner_subtitle') ?? $request->input('store_banner_subtitle') ?? ''));
        }
        if ($request->has('cta_text') || $request->has('store_banner_cta_text')) {
            $updates['store_banner_cta_text'] = trim((string) ($request->input('cta_text') ?? $request->input('store_banner_cta_text') ?? ''));
        }
        if ($request->has('cta_link') || $request->has('store_banner_cta_link')) {
            $updates['store_banner_cta_link'] = trim((string) ($request->input('cta_link') ?? $request->input('store_banner_cta_link') ?? ''));
        }
        if ($request->has('banner_image_url') || $request->has('store_banner_image_url')) {
            $updates['store_banner_image_url'] = trim((string) ($request->input('banner_image_url') ?? $request->input('store_banner_image_url') ?? ''));
        }
        if ($request->has('is_active') || $request->has('store_banner_is_active')) {
            $updates['store_banner_is_active'] = $request->boolean('is_active', $request->boolean('store_banner_is_active', true));
        }
        if ($request->has('enable_google_login')) {
            $updates['enable_google_login'] = $request->boolean('enable_google_login');
        }
        if ($request->has('google_client_id')) {
            $updates['google_client_id'] = trim((string) $request->input('google_client_id', ''));
        }
        if ($request->has('google_client_secret') && ! empty($request->input('google_client_secret')) && ! str_contains($request->input('google_client_secret'), '••••')) {
            $updates['google_client_secret'] = trim((string) $request->input('google_client_secret'));
        }
        if ($request->has('require_auth_before_checkout') || $request->has('require_customer_verification')) {
            $updates['require_customer_verification'] = $request->boolean('require_auth_before_checkout', $request->boolean('require_customer_verification'));
        }

        if (! empty($updates)) {
            $company->update($updates);
        }

        AuditLog::record('storefront.banner_auth_updated', $company->id, $user?->id, ['section' => 'storefront_banner_auth']);

        return response()->json([
            'success' => true,
            'message' => 'Storefront banner and authentication settings updated successfully.',
            'data' => [
                'banner_tag' => $company->store_banner_tag ?: 'SPECIAL STORE DEALS',
                'banner_title' => $company->store_banner_title ?: 'Grab Up To 50% Off On Selected Products',
                'banner_subtitle' => $company->store_banner_subtitle ?: 'Explore our curated selection of high-quality products.',
                'cta_text' => $company->store_banner_cta_text ?: 'Shop Now',
                'cta_link' => $company->store_banner_cta_link ?: '#products',
                'banner_image_url' => $company->store_banner_image_url ?: '',
                'is_active' => (bool) ($company->store_banner_is_active ?? true),
                'auth_banner_url' => 'https://saas.zoomnearby.com/assets/images/landing/auth-banner.png',
                'enable_google_login' => (bool) ($company->enable_google_login ?? false),
                'google_client_id' => $company->google_client_id ?: '',
                'require_auth_before_checkout' => (bool) ($company->require_customer_verification ?? false),
            ],
        ]);
    }

    /**
     * GET /api/v1/tenant/storefront/payment-gateways
     */
    public function getPaymentGateways(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $existing = (array) ($company->storefront_payment_gateways ?? []);

        $defaultGateways = [
            [
                'id' => 'cod',
                'name' => 'Cash on Delivery',
                'is_enabled' => (bool) ($existing['cod']['enabled'] ?? $existing['cod']['is_enabled'] ?? true),
                'instructions' => (string) ($existing['cod']['instructions'] ?? 'Pay in cash upon physical delivery to the driver or courier.'),
            ],
            [
                'id' => 'store_pickup',
                'name' => 'Pay at Counter / In-Store',
                'is_enabled' => (bool) ($existing['store_pickup']['enabled'] ?? $existing['store_pickup']['is_enabled'] ?? true),
                'instructions' => (string) ($existing['store_pickup']['instructions'] ?? 'Collect your items at our counter and pay via any store payment method.'),
            ],
            [
                'id' => 'razorpay',
                'name' => 'Razorpay (Cards / UPI / Netbanking)',
                'is_enabled' => (bool) ($existing['razorpay']['enabled'] ?? $existing['razorpay']['is_enabled'] ?? false),
                'key_id' => (string) ($existing['razorpay']['key_id'] ?? ''),
                'key_secret' => ! empty($existing['razorpay']['key_secret']) ? '••••••••' : '',
                'mode' => (string) ($existing['razorpay']['mode'] ?? 'sandbox'),
            ],
            [
                'id' => 'stripe',
                'name' => 'Stripe',
                'is_enabled' => (bool) ($existing['stripe']['enabled'] ?? $existing['stripe']['is_enabled'] ?? false),
                'publishable_key' => (string) ($existing['stripe']['publishable_key'] ?? $existing['stripe']['key_id'] ?? ''),
                'secret_key' => ! empty($existing['stripe']['secret_key'] ?? $existing['stripe']['key_secret'] ?? '') ? '••••••••' : '',
                'mode' => (string) ($existing['stripe']['mode'] ?? 'sandbox'),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'gateways' => $defaultGateways,
            ],
        ]);
    }

    /**
     * POST /api/v1/tenant/storefront/payment-gateways
     */
    public function updatePaymentGateways(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $currentGateways = (array) ($company->storefront_payment_gateways ?? []);
        $gatewaysToSave = $currentGateways;

        // Mode A: Array of gateway objects
        if ($request->has('gateways') && is_array($request->input('gateways'))) {
            foreach ($request->input('gateways') as $gw) {
                if (! is_array($gw) || empty($gw['id'])) {
                    continue;
                }
                $id = (string) $gw['id'];
                $gatewaysToSave[$id] = [
                    'enabled' => (bool) ($gw['is_enabled'] ?? $gw['enabled'] ?? false),
                    'is_enabled' => (bool) ($gw['is_enabled'] ?? $gw['enabled'] ?? false),
                    'name' => (string) ($gw['name'] ?? ($currentGateways[$id]['name'] ?? ucfirst($id))),
                    'instructions' => (string) ($gw['instructions'] ?? ($currentGateways[$id]['instructions'] ?? '')),
                ];

                if ($id === 'razorpay') {
                    $gatewaysToSave[$id]['key_id'] = (string) ($gw['key_id'] ?? ($currentGateways[$id]['key_id'] ?? ''));
                    $sec = trim((string) ($gw['key_secret'] ?? ''));
                    if ($sec !== '' && ! str_contains($sec, '••••')) {
                        $gatewaysToSave[$id]['key_secret'] = $sec;
                    } elseif (isset($currentGateways[$id]['key_secret'])) {
                        $gatewaysToSave[$id]['key_secret'] = $currentGateways[$id]['key_secret'];
                    }
                    $gatewaysToSave[$id]['mode'] = (string) ($gw['mode'] ?? ($currentGateways[$id]['mode'] ?? 'sandbox'));
                }

                if ($id === 'stripe') {
                    $gatewaysToSave[$id]['publishable_key'] = (string) ($gw['publishable_key'] ?? $gw['key_id'] ?? ($currentGateways[$id]['publishable_key'] ?? ''));
                    $sec = trim((string) ($gw['secret_key'] ?? $gw['key_secret'] ?? ''));
                    if ($sec !== '' && ! str_contains($sec, '••••')) {
                        $gatewaysToSave[$id]['secret_key'] = $sec;
                    } elseif (isset($currentGateways[$id]['secret_key'])) {
                        $gatewaysToSave[$id]['secret_key'] = $currentGateways[$id]['secret_key'];
                    }
                    $gatewaysToSave[$id]['mode'] = (string) ($gw['mode'] ?? ($currentGateways[$id]['mode'] ?? 'sandbox'));
                }
            }
        } else {
            // Mode B: Flat form fields (e.g. from SDUI or web form)
            if ($request->has('cod_enabled') || $request->has('cod_instructions')) {
                $gatewaysToSave['cod'] = [
                    'enabled' => $request->boolean('cod_enabled'),
                    'is_enabled' => $request->boolean('cod_enabled'),
                    'name' => 'Cash on Delivery',
                    'instructions' => (string) $request->input('cod_instructions', 'Pay in cash upon physical delivery to the driver or courier.'),
                ];
            }

            if ($request->has('store_pickup_enabled') || $request->has('store_pickup_instructions')) {
                $gatewaysToSave['store_pickup'] = [
                    'enabled' => $request->boolean('store_pickup_enabled'),
                    'is_enabled' => $request->boolean('store_pickup_enabled'),
                    'name' => 'Pay at Counter / In-Store',
                    'instructions' => (string) $request->input('store_pickup_instructions', 'Collect your items at our counter and pay via any store payment method.'),
                ];
            }

            if ($request->has('razorpay_enabled') || $request->has('razorpay_key_id')) {
                $rzpSec = trim((string) $request->input('razorpay_key_secret', ''));
                $gatewaysToSave['razorpay'] = [
                    'enabled' => $request->boolean('razorpay_enabled'),
                    'is_enabled' => $request->boolean('razorpay_enabled'),
                    'name' => 'Razorpay (Cards / UPI / Netbanking)',
                    'instructions' => (string) $request->input('razorpay_instructions', ''),
                    'key_id' => trim((string) $request->input('razorpay_key_id', '')),
                    'key_secret' => ($rzpSec !== '' && ! str_contains($rzpSec, '••••'))
                        ? $rzpSec
                        : ($currentGateways['razorpay']['key_secret'] ?? ''),
                    'mode' => (string) $request->input('razorpay_mode', ($currentGateways['razorpay']['mode'] ?? 'sandbox')),
                ];
            }

            if ($request->has('stripe_enabled') || $request->has('stripe_publishable_key')) {
                $stripeSec = trim((string) $request->input('stripe_secret_key', ''));
                $gatewaysToSave['stripe'] = [
                    'enabled' => $request->boolean('stripe_enabled'),
                    'is_enabled' => $request->boolean('stripe_enabled'),
                    'name' => 'Stripe',
                    'instructions' => (string) $request->input('stripe_instructions', ''),
                    'publishable_key' => trim((string) $request->input('stripe_publishable_key', '')),
                    'secret_key' => ($stripeSec !== '' && ! str_contains($stripeSec, '••••'))
                        ? $stripeSec
                        : ($currentGateways['stripe']['secret_key'] ?? ''),
                    'mode' => (string) $request->input('stripe_mode', ($currentGateways['stripe']['mode'] ?? 'sandbox')),
                ];
            }
        }

        $company->update(['storefront_payment_gateways' => $gatewaysToSave]);
        AuditLog::record('storefront.payment_gateways_updated', $company->id, $user?->id, ['section' => 'storefront_payment_gateways']);

        return response()->json([
            'success' => true,
            'message' => 'Storefront payment gateways updated successfully.',
            'data' => [
                'gateways' => $gatewaysToSave,
            ],
        ]);
    }

    /**
     * GET /api/v1/tenant/storefront/domain-config
     * GET /api/tenant/storefront/domain-config
     */
    public function getDomainConfig(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $baseHost = config('app.domain', 'saas.zoomnearby.com');
        $subdomain = $company->subdomain ?? '';
        $customDomain = $company->custom_domain ?? '';

        $liveStoreUrl = ! empty($customDomain)
            ? 'https://'.$customDomain
            : (! empty($subdomain) ? 'https://'.$subdomain.'.'.$baseHost : url('/'));

        $cnameTarget = 'cname.'.$baseHost;

        return response()->json([
            'success' => true,
            'data' => [
                'subdomain' => $subdomain,
                'subdomain_url' => ! empty($subdomain) ? 'https://'.$subdomain.'.'.$baseHost : '',
                'custom_domain' => $customDomain,
                'live_store_url' => $liveStoreUrl,
                'cname_target' => $cnameTarget,
                'dns_records' => [
                    [
                        'type' => 'CNAME',
                        'host' => '@ / www / store',
                        'value' => $cnameTarget,
                        'ttl' => '3600 (Automatic)',
                        'status' => ! empty($customDomain) ? 'Configured' : 'Pending',
                    ],
                ],
                'ssl_status' => 'Auto-provisioned via SSL certificate provider',
                'propagation_note' => 'DNS changes can take anywhere from 15 minutes up to 24-48 hours to propagate worldwide.',
            ],
        ]);
    }

    /**
     * PUT /api/v1/tenant/storefront/domain-config
     * POST /api/v1/tenant/storefront/domain-config
     */
    public function updateDomainConfig(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'custom_domain' => ['nullable', 'string', 'max:255'],
            'subdomain' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $updates = [];

        if ($request->has('custom_domain')) {
            $rawDomain = trim((string) $request->input('custom_domain', ''));
            $cleanDomain = preg_replace('#^https?://#i', '', $rawDomain);
            $cleanDomain = rtrim($cleanDomain, '/');
            $cleanDomain = strtolower($cleanDomain);

            if (! empty($cleanDomain)) {
                $conflict = Company::withoutGlobalScopes()
                    ->where('id', '!=', $company->id)
                    ->where('custom_domain', $cleanDomain)
                    ->exists();

                if ($conflict) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Domain already in use',
                        'message' => 'This custom domain is already registered to another store.',
                    ], 422);
                }
            }
            $updates['custom_domain'] = $cleanDomain ?: null;
        }

        if ($request->has('subdomain') && ! empty($request->input('subdomain'))) {
            $rawSub = trim((string) $request->input('subdomain'));
            $cleanSub = strtolower(preg_replace('#[^a-z0-9-]#', '-', $rawSub));
            $cleanSub = trim($cleanSub, '-');

            if (! empty($cleanSub) && $cleanSub !== $company->subdomain) {
                $conflict = Company::withoutGlobalScopes()
                    ->where('id', '!=', $company->id)
                    ->where('subdomain', $cleanSub)
                    ->exists();

                if ($conflict) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Subdomain already in use',
                        'message' => 'This subdomain is already taken.',
                    ], 422);
                }
                $updates['subdomain'] = $cleanSub;
            }
        }

        if (! empty($updates)) {
            $company->update($updates);
            AuditLog::record('storefront.domain_updated', $company->id, $user?->id, $updates);
        }

        return $this->getDomainConfig($request);
    }
}
