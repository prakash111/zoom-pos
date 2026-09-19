<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ReceivablesController;
use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomNotificationChannel;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\PlatformBranding;
use App\Models\PlatformSystem;
use App\Models\Product;
use App\Models\PushDevice;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\TaxRule;
use App\Models\TenantApiKey;
use App\Models\Unit;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use App\Services\Delivery\MessageQueueService;
use App\Services\Delivery\WebhookDispatchService;
use App\Services\Financial\CustomerLedgerService;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Modular\ModuleRegistry;
use App\Services\Notifications\TenantNotificationDispatcherService;
use App\Services\Payment\SubscriptionPaymentGatewayService;
use App\Services\Sdui\SchemaResponse;
use App\Services\SmsGatewayService;
use App\Services\TaxCalculationService;
use App\Services\TaxEngineService;
use App\Services\Tenancy\TenantProvisioningService;
use Carbon\Carbon;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PosSyncApiController extends Controller
{
    use ResolvesTenantSyncContext;

    protected function desktopPermissions(User $user): array
    {
        $company = $user->company;
        $hasLeadMod = (bool) ($company && ($company->hasModule('leadmanagement') || $company->hasModule('lead_management') || $company->hasModule('leads')));

        $permissions = [
            'pos' => true,
            'pos.view' => true,
            'pos.create' => $user->hasPermission('pos', 'create'),
            'pos.edit' => $user->hasPermission('pos', 'edit'),
            'sales' => true,
            'sales.view' => $user->hasPermission('sales', 'view'),
            'quotes' => $user->hasPermission('quotes', 'view'),
            'quotes.view' => $user->hasPermission('quotes', 'view'),
            'quotations' => $user->hasPermission('quotes', 'view'),
            'quotations.view' => $user->hasPermission('quotes', 'view'),
            'leads' => $hasLeadMod && $user->hasPermission('leads', 'view'),
            'leads.view' => $hasLeadMod && $user->hasPermission('leads', 'view'),
            'lead_management' => $hasLeadMod && $user->hasPermission('leads', 'view'),
            'lead_management.view' => $hasLeadMod && $user->hasPermission('leads', 'view'),
            'consignments' => $user->hasPermission('consignments', 'view'),
            'consignments.view' => $user->hasPermission('consignments', 'view'),
            'products.view' => $user->hasPermission('products', 'view'),
            'products.create' => $user->hasPermission('products', 'create'),
            'products.edit' => $user->hasPermission('products', 'edit'),
            'customers' => $user->hasPermission('customers', 'view'),
            'customers.view' => $user->hasPermission('customers', 'view'),
            'customers.create' => $user->hasPermission('customers', 'create'),
            'customers.edit' => $user->hasPermission('customers', 'edit'),
            'reports.view' => $user->hasPermission('reports', 'view'),
            'settings.view' => $user->hasPermission('settings', 'view'),
        ];

        if ($user->isPrivilegedRole()) {
            $permissions['*'] = true;
        }

        // A `.view` flag for every remaining module (mirrors
        // PermissionChecker::MODULES exactly) so the mobile drawer/menu can
        // gate each feature tile by the user's own authorized modules
        // instead of showing every module to every role.
        foreach (array_keys(PermissionChecker::MODULES) as $module) {
            $permissions["{$module}.view"] ??= $user->hasPermission($module, 'view');
            $permissions[$module] ??= $permissions["{$module}.view"];
        }

        return $permissions;
    }

    /**
     * 1. Public Tenant Login
     * POST /api/v1/pos/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
            'account_id' => ['nullable', 'string'],
            'fcm_token' => ['nullable', 'string', 'max:512'],
            'push_token' => ['nullable', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Please provide email and password.',
                'details' => $validator->errors(),
            ], 422);
        }

        $identifier = trim($request->input('email'));
        $password = $request->input('password');
        $accountId = $request->input('account_id');

        $company = null;
        if ($accountId) {
            $company = Company::query()
                ->where('unique_account_id', $accountId)
                ->orWhere('slug', $accountId)
                ->orWhere('id', $accountId)
                ->first();

            if (! $company) {
                return response()->json([
                    'success' => false,
                    'error' => 'Company account ID not found.',
                ], 404);
            }
        }

        $userQuery = User::query()->withoutGlobalScope('company')
            ->where(function ($q) use ($identifier) {
                $q->where('email', $identifier)->orWhere('login', $identifier);
            });

        if ($company) {
            $userQuery->where('company_id', $company->id);
        }

        $user = $userQuery->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid email/login or password.',
            ], 401);
        }

        $company ??= Company::query()->find($user->company_id);

        if (! $company || $company->isSuspended()) {
            return response()->json([
                'success' => false,
                'error' => 'This tenant account has been suspended. Please contact support.',
            ], 403);
        }

        // Email-verification gate. When the SuperAdmin has SMTP configured, OTP
        // verification is mandatory: an account that registered but never
        // entered its 6-digit code must not be handed a working POS token.
        // Return a structured JSON payload (HTTP 200, success:true — same shape
        // the register endpoint uses) that the mobile client routes to its
        // Verify screen on. This is deliberately NOT a 302 redirect: the Dio
        // client throws on any 3xx status.
        $branding = PlatformBranding::current();
        $otpRequired = method_exists($branding, 'isSmtpConfigured') && $branding->isSmtpConfigured();

        if ($otpRequired && is_null($user->email_verified_at)) {
            return response()->json([
                'success' => true,
                'status' => 'requires_verification',
                'requires_otp' => true,
                'action' => 'navigate',
                'route' => '/api/tenant/views/verify-otp',
                'email' => $user->email,
                'expires_in' => 600,
                'message' => 'Please verify your email address to continue. Enter the 6-digit code sent to your email, or tap Resend Code.',
                'arguments' => [
                    'email' => $user->email,
                    'message' => 'Please verify your email address to continue.',
                ],
            ], 200);
        }

        // Detect client terminal / device platform metadata
        $deviceName = $request->input('device_name')
            ?? $request->header('X-Device-Name')
            ?? null;

        if (! $deviceName) {
            $ua = strtolower((string) $request->userAgent());
            if (str_contains($ua, 'android')) {
                $platform = 'Android POS Terminal';
            } elseif (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ios')) {
                $platform = 'iOS POS Terminal';
            } elseif (str_contains($ua, 'dart') || str_contains($ua, 'flutter')) {
                $platform = 'Mobile POS Terminal';
            } elseif (str_contains($ua, 'macintosh') || str_contains($ua, 'mac os')) {
                $platform = 'macOS POS Desktop';
            } elseif (str_contains($ua, 'windows')) {
                $platform = 'Windows POS Desktop';
            } else {
                $platform = 'POS Terminal';
            }
            $deviceName = $platform.' ('.($user->name ?: 'Staff').')';
        }

        // Generate or fetch active Tenant API Key for POS terminal authentication
        $apiKey = TenantApiKey::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('active', true)
            ->first();

        if ($apiKey) {
            $apiKey->update([
                'name' => $deviceName,
                'last_used_at' => now(),
            ]);
        } else {
            $apiKey = TenantApiKey::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'name' => $deviceName,
                'token' => 'zk_live_'.Str::random(40),
                'permissions' => ['*'],
                'active' => true,
                'last_used_at' => now(),
            ]);
        }

        // Associate or reactivate push notification device token if provided
        $pushToken = $request->input('fcm_token')
            ?? $request->input('push_token')
            ?? null;

        if ($pushToken && is_string($pushToken) && trim($pushToken) !== '') {
            $pushToken = trim($pushToken);
            $devicePlatform = strtolower((string) ($request->input('platform') ?? 'android'));
            if (! in_array($devicePlatform, ['android', 'ios', 'web'], true)) {
                $devicePlatform = 'android';
            }
            PushDevice::withoutGlobalScope('company')->updateOrCreate(
                ['token' => $pushToken],
                [
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'platform' => $devicePlatform,
                    'device_name' => $deviceName,
                    'last_seen_at' => now(),
                    'revoked_at' => null,
                ]
            );
        }

        $subscription = Subscription::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->latest('started_at')
            ->first();

        $businessType = strtoupper($company->pos_mode ?: 'RETAIL');
        $activeFeatures = ModuleRegistry::activeFeaturesFor($company);

        return response()->json([
            'success' => true,
            'token' => $apiKey->token,
            'business_type' => $businessType,
            'plan_features' => $activeFeatures,
            'features' => $activeFeatures,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'login' => $user->login,
                'email' => $user->email,
                'role' => $user->role,
                'company_id' => $user->company_id,
                'permissions' => $this->desktopPermissions($user),
            ],
            'drawer_header' => $company->getDrawerHeaderPayload(),
            'header' => $company->getDrawerHeaderPayload(),
            'tenant' => [
                'id' => (string) $company->id,
                'name' => $company->display_name,
                'business_name' => $company->display_name,
                'trade_name' => $company->getEffectiveTradeName(),
                'trading_name' => $company->getEffectiveTradeName(),
                'business_type' => $businessType,
                'plan_features' => $activeFeatures,
                'features' => $activeFeatures,
                'active_mode' => strtolower(trim(ModuleRegistry::resolveActiveMode($company))),
                'available_modes' => ModuleRegistry::availableModes($company),
            ],
            'company' => [
                'id' => $company->id,
                'name' => $company->display_name,
                'business_name' => $company->display_name,
                'trade_name' => $company->getEffectiveTradeName(),
                'trading_name' => $company->getEffectiveTradeName(),
                'store_name' => $company->display_name,
                'display_name' => $company->display_name,
                'slug' => $company->slug,
                'currency' => $company->currency ?? 'USD',
                'currency_symbol' => $company->currency_symbol ?? '$',
                'tax_number' => $company->document ?? $company->tax_id ?? '',
                'tax_id' => $company->tax_id ?? $company->document ?? '',
                'country' => $company->country ?? 'IN',
                'timezone' => $company->resolveTimezone(),
                'address' => $company->address ?? '',
                'city' => $company->city ?? '',
                'state' => $company->state ?? '',
                'postal_code' => $company->postal_code ?? '',
                'phone' => $company->phone ?? '',
                'email' => $company->email ?? '',
                'plan_name' => $company->plan_name ?? 'trial',
                'expires_at' => $company->expires_at?->toIso8601String(),
                'pos_mode' => $company->isRestaurantMode() ? 'restaurant' : 'general',
                'business_type' => $businessType,
                'plan_features' => $activeFeatures,
                'features' => $activeFeatures,
                'restaurant_mode_locked' => (bool) $company->restaurant_mode_locked,
                'drawer_cover_url' => $company->getDrawerCoverUrl(),
                'logo_url' => $company->getLogoUrl(),
                'favicon_url' => $company->getFaviconUrl(),
                'drawer_header' => $company->getDrawerHeaderPayload(),
                'header' => $company->getDrawerHeaderPayload(),
            ],
            // Only present when the tenant is actually on a plan — lets a fresh
            // desktop device provision a local mirror of both rows (companies.plan_name
            // is a foreign key) without a second round trip. See DesktopAuthBootstrapService.
            'plan' => $company->plan ? $company->plan->only([
                'name', 'display_name', 'billing_cycle', 'duration_days', 'price', 'currency', 'features', 'limits', 'active',
            ]) : null,
            'subscription' => [
                'plan_name' => $subscription?->plan_name ?? $company->plan_name ?? 'trial',
                'status' => $subscription?->status ?? ($company->isExpired() ? 'expired' : 'active'),
                'expires_at' => $company->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * 2. Public Tenant Registration
     * POST /api/v1/pos/auth/register
     */
    /**
     * Pre-auth config for the "Create your store" screen — which store-type
     * cards (Retail / Cafe & Restaurant / Pharmacy / Service & Salon) it should offer,
     * per the Superadmin's global "Allowed Registration Modes" setting.
     * GET /api/v1/pos/auth/registration-config
     */
    public function registrationConfig(): JsonResponse
    {
        return $this->registrationMeta();
    }

    /**
     * Unauthenticated endpoint returning active registration modes licensed by SuperAdmin.
     * GET /api/app/registration-meta
     * GET /api/v1/pos/app/registration-meta
     */
    public function registrationMeta(): JsonResponse
    {
        $enabled = ModuleRegistry::enabledRegistrationModes();
        $legacyString = in_array('restaurant', $enabled, true) && in_array('retail', $enabled, true)
            ? 'both'
            : (in_array('restaurant', $enabled, true) ? 'restaurant_only' : 'retail_only');

        $activeModulesMap = ModuleRegistry::registrationModules();

        $registrationModes = [];
        foreach ($enabled as $key) {
            if (! isset($activeModulesMap[$key])) {
                continue;
            }
            $mod = $activeModulesMap[$key];
            $registrationModes[] = [
                'key' => $mod['id'] ?? $key,
                'title' => $mod['title'] ?? ucfirst(str_replace('_', ' ', $key)),
                'subtitle' => $mod['subtitle'] ?? $mod['description'] ?? '',
                'icon' => $mod['icon'] ?? 'widgets',
            ];
        }

        $activeModules = array_values(array_map(function ($mod) {
            return [
                'id' => $mod['id'],
                'key' => $mod['id'],
                'title' => $mod['title'],
                'subtitle' => $mod['subtitle'] ?? $mod['description'] ?? '',
                'description' => $mod['description'],
                'icon' => $mod['icon'] ?? 'widgets',
                'layout_type' => $mod['layout_type'] ?? 'standard_grid',
            ];
        }, $activeModulesMap));

        return response()->json([
            'success' => true,
            'registration_modes' => $registrationModes,
            'active_modules' => $activeModules,
            'allowed_registration_modes' => $legacyString,
            'enabled_modes' => $enabled,
            'default_mode' => $registrationModes[0]['key'] ?? 'retail',
        ]);
    }

    /**
     * Platform-wide (not tenant-scoped) branding + theme for the pre-auth
     * screens (Splash / Login / Register / Forgot Password), set by the
     * Superadmin in Branding settings. Tenants override this after sign-in.
     *
     * GET /api/v1/pos/auth/branding
     * GET /api/v1/pos/auth/public-settings   (same payload, canonical name)
     */
    public function branding(): JsonResponse
    {
        $branding = PlatformBranding::current();
        $settings = $branding->publicSettings();

        return response()->json([
            'success' => true,
            // Superadmin global defaults — the canonical nested contract.
            'platform' => $settings['platform'],
            'theme' => $settings['theme'],
            // Flat aliases kept for older clients — the Superadmin "Platform
            // Title / App Name" under every name the pre-auth screens read.
            'platform_name' => $settings['platform']['name'],
            'platform_title' => $settings['platform']['name'],
            'app_name' => $settings['platform']['name'],
            'brand_logo_url' => $settings['platform']['logo_url'],
            // Primary colour under every name a client might parse.
            'primary_color' => $settings['theme']['primary_color'],
            'brand_color' => $settings['theme']['primary_color'],
            'support_phone' => $settings['platform']['support_phone'],
            'support_whatsapp' => $settings['platform']['support_whatsapp'],
            'support_email' => $settings['platform']['support_email'],
            'auth_banner_image_url' => $settings['platform']['auth_banner_image_url'],
            'show_auth_banner' => $settings['platform']['show_auth_banner'],
            'platform_tagline' => null,
            'header_inline' => true,
            'show_tagline' => false,
        ]);
    }

    /** Alias of [branding] under a clearer name. GET .../auth/public-settings */
    public function publicSettings(): JsonResponse
    {
        return $this->branding();
    }

    /**
     * Unauthenticated public auth & social OAuth configuration.
     * GET /api/app/auth-config
     * GET /api/v1/pos/auth/auth-config
     */
    public function authConfig(): JsonResponse
    {
        $googleEnabled = filter_var(PlatformSystem::get('social_google_enabled', false), FILTER_VALIDATE_BOOLEAN)
            || (bool) config('services.google.enabled', true);
        $googleClientId = (string) (PlatformSystem::get('social_google_client_id') ?: config('services.google.client_id', ''));

        $facebookEnabled = filter_var(PlatformSystem::get('social_facebook_enabled', false), FILTER_VALIDATE_BOOLEAN)
            || (bool) config('services.facebook.enabled', true);
        $facebookClientId = (string) (PlatformSystem::get('social_facebook_client_id') ?: config('services.facebook.client_id', ''));

        $demoMode = (bool) config('app.demo_mode');
        $demoAccounts = [];
        if ($demoMode) {
            // Keep the mobile quick-fill accounts aligned with the credentials
            // advertised on the tenant web login. Return one canonical chip
            // per workspace so legacy aliases cannot create duplicates.
            $demoAccounts[] = [
                'label' => 'All Modules / Enterprise',
                'store_type' => 'ENTERPRISE_OMNICHANNEL',
                'email' => 'demo@zoomnearby.com',
                'password' => 'demo1234',
            ];
            foreach ([
                ['label' => 'Retail', 'store_type' => 'RETAIL', 'email' => 'retail.demo@zoomnearby.com'],
                ['label' => 'Cafe & Restaurant', 'store_type' => 'RESTAURANT', 'email' => 'restaurant.demo@zoomnearby.com'],
                ['label' => 'Pharmacy', 'store_type' => 'PHARMACY', 'email' => 'pharmacy.demo@zoomnearby.com'],
                ['label' => 'Repair', 'store_type' => 'REPAIR', 'email' => 'repairs.demo@zoomnearby.com'],
                ['label' => 'Salon', 'store_type' => 'SALON', 'email' => 'salon.demo@zoomnearby.com'],
            ] as $account) {
                $account['password'] = 'demo1234';
                $demoAccounts[] = $account;
            }
        }

        return response()->json([
            'success' => true,
            'social_login' => [
                'google' => (bool) $googleEnabled,
                'facebook' => (bool) $facebookEnabled,
                'google_client_id' => $googleClientId ?: null,
                'facebook_client_id' => $facebookClientId ?: null,
            ],
            // Zero-Flutter-touch 1-click demo login: the client renders a
            // quick-fill chip bar from this list and auto-submits on tap.
            'demo_mode' => $demoMode,
            'demo_accounts' => $demoAccounts,
        ]);
    }

    public function register(Request $request, TenantProvisioningService $provisioner): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'store_name' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', 'unique:companies,slug'],
            'custom_domain' => ['nullable', 'string', 'max:100', 'unique:companies,custom_domain'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'max:10'],
            'pos_mode' => ['nullable', 'string'],
            'plan_name' => ['nullable', 'string', 'in:trial,starter,professional'],
            'activation_code' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error during tenant registration.',
                'details' => $validator->errors(),
            ], 422);
        }

        $requestedMode = strtolower(trim((string) $request->input('pos_mode', 'general')));
        $normalizedMode = $requestedMode === 'general' ? 'retail' : $requestedMode;
        $enabledModes = ModuleRegistry::enabledRegistrationModes();

        if (! in_array($normalizedMode, $enabledModes, true)) {
            return response()->json([
                'success' => false,
                'error' => "Registration for mode '{$requestedMode}' is currently disabled.",
            ], 422);
        }

        try {
            $regData = [
                'store_name' => $request->input('store_name'),
                'slug' => $request->input('slug'),
                'custom_domain' => $request->input('custom_domain'),
                'owner_name' => $request->input('name'),
                'admin_name' => $request->input('name'),
                'email' => $request->input('email'),
                'admin_email' => $request->input('email'),
                'password' => $request->input('password'),
                'admin_password' => $request->input('password'),
                'phone' => $request->input('phone'),
                'currency' => $request->filled('currency') ? $request->input('currency') : null,
                'language' => $request->filled('language') ? $request->input('language') : ($request->filled('locale') ? $request->input('locale') : null),
                'default_locale' => $request->filled('default_locale') ? $request->input('default_locale') : null,
                'timezone' => $request->filled('timezone') ? $request->input('timezone') : null,
                'pos_mode' => $request->input('pos_mode', 'general'),
                'plan_name' => $request->input('plan_name', 'trial'),
                'activation_code' => $request->input('activation_code'),
            ];

            $result = $provisioner->registerTenant($regData);
            $company = $result['company'];
            $user = $result['user'];

            $branding = PlatformBranding::current();
            $smtpConfigured = ! empty($branding?->smtp_host);
            $otpRequired = ($branding?->otp_registration_enabled ?? false) || ($smtpConfigured && ($branding?->otp_registration_enabled !== false));

            if ($otpRequired) {
                $otp = (string) random_int(100000, 999999);
                $expiresAt = now()->addMinutes(10);

                $user->update([
                    'verification_code' => Hash::make($otp),
                    'verification_code_expires_at' => $expiresAt,
                    'status' => 'pending',
                    'email_verified_at' => null,
                ]);

                if (Schema::hasTable('email_verifications')) {
                    DB::table('email_verifications')->insert([
                        'email' => strtolower(trim($user->email)),
                        'otp_hash' => Hash::make($otp),
                        'expires_at' => $expiresAt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                try {
                    app(AuthApiController::class)->sendOtpEmail($user->email, $otp, $branding);
                } catch (\Throwable $e) {
                    Log::warning("Failed to send OTP verification email to {$user->email}: ".$e->getMessage());
                }

                return response()->json([
                    'status' => 'success',
                    'success' => true,
                    'action' => 'navigate',
                    'route' => '/api/tenant/views/verify-otp',
                    'message' => 'Enter the 6-digit code sent to your email.',
                    'arguments' => [
                        'email' => $user->email,
                        'message' => 'Enter the 6-digit code sent to your email.',
                    ],
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                ], 200);
            }

            $apiKey = TenantApiKey::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'name' => 'Desktop POS Client ('.$user->name.')',
                'token' => 'zk_live_'.Str::random(40),
                'permissions' => ['*'],
                'active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tenant registered and provisioned successfully.',
                'token' => $apiKey->token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'login' => $user->login,
                    'email' => $user->email,
                    'role' => $user->role,
                    'company_id' => $user->company_id,
                    'permissions' => $this->desktopPermissions($user),
                ],
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'currency' => $company->currency ?? 'USD',
                    'currency_symbol' => $company->currency_symbol ?? '$',
                    'timezone' => $company->resolveTimezone(),
                    'plan_name' => $company->plan_name ?? 'trial',
                    'expires_at' => $company->expires_at?->toIso8601String(),
                    'pos_mode' => $company->isRestaurantMode() ? 'restaurant' : 'general',
                    'restaurant_mode_locked' => (bool) $company->restaurant_mode_locked,
                    'drawer_cover_url' => $company->getDrawerCoverUrl(),
                    'logo_url' => $company->getLogoUrl(),
                    'favicon_url' => $company->getFaviconUrl(),
                ],
                'plan' => $company->plan ? $company->plan->only([
                    'name', 'display_name', 'billing_cycle', 'duration_days', 'price', 'currency', 'features', 'limits', 'active',
                ]) : null,
                'subscription' => [
                    'plan_name' => $company->plan_name,
                    'status' => 'active',
                    'expires_at' => $company->expires_at?->toIso8601String(),
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('POS Tenant Registration Failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'error' => 'Registration failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 3. Session Info
     * GET /api/v1/pos/auth/session
     */
    public function session(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $businessType = strtoupper($company->pos_mode ?: 'RETAIL');
        $activeFeatures = ModuleRegistry::activeFeaturesFor($company);

        return response()->json([
            'success' => true,
            'business_type' => $businessType,
            'plan_features' => $activeFeatures,
            'features' => $activeFeatures,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'company_id' => $user->company_id,
                'permissions' => $this->desktopPermissions($user),
            ] : null,
            'drawer_header' => $company->getDrawerHeaderPayload(),
            'header' => $company->getDrawerHeaderPayload(),
            'tenant' => [
                'id' => (string) $company->id,
                'name' => $company->display_name,
                'business_name' => $company->display_name,
                'trade_name' => $company->getEffectiveTradeName(),
                'trading_name' => $company->getEffectiveTradeName(),
                'business_type' => $businessType,
                'plan_features' => $activeFeatures,
                'features' => $activeFeatures,
                'active_mode' => strtolower(trim(ModuleRegistry::resolveActiveMode($company))),
                'available_modes' => ModuleRegistry::availableModes($company),
            ],
            'company' => [
                'id' => $company->id,
                'name' => $company->display_name,
                'business_name' => $company->display_name,
                'trade_name' => $company->getEffectiveTradeName(),
                'trading_name' => $company->getEffectiveTradeName(),
                'store_name' => $company->display_name,
                'display_name' => $company->display_name,
                'slug' => $company->slug,
                'currency' => $company->currency ?? 'USD',
                'currency_symbol' => $company->currency_symbol ?? '$',
                'tax_number' => $company->document ?? $company->tax_id ?? '',
                'tax_id' => $company->tax_id ?? $company->document ?? '',
                'country' => $company->country ?? 'IN',
                'timezone' => $company->resolveTimezone(),
                'address' => $company->address ?? '',
                'city' => $company->city ?? '',
                'state' => $company->state ?? '',
                'postal_code' => $company->postal_code ?? '',
                'phone' => $company->phone ?? '',
                'email' => $company->email ?? '',
                'plan_name' => $company->plan_name ?? 'trial',
                'expires_at' => $company->expires_at?->toIso8601String(),
                'pos_mode' => $company->isRestaurantMode() ? 'restaurant' : 'general',
                'business_type' => $businessType,
                'plan_features' => $activeFeatures,
                'features' => $activeFeatures,
                'restaurant_mode_locked' => (bool) $company->restaurant_mode_locked,
                'drawer_cover_url' => $company->getDrawerCoverUrl(),
                'logo_url' => $company->getLogoUrl(),
                'favicon_url' => $company->getFaviconUrl(),
                'navigation_labels' => $company->navigation_labels ?? new \stdClass,
                'form_field_customizations' => $company->form_field_customizations ?? new \stdClass,
                'drawer_header' => $company->getDrawerHeaderPayload(),
                'header' => $company->getDrawerHeaderPayload(),
            ],
        ]);
    }

    /** Mint a short-lived, one-use bridge into the session-based web UI. */
    public function desktopWebSession(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = Auth::user();

        if (! $user || $user->company_id !== $company->id) {
            return response()->json([
                'success' => false,
                'error' => 'This desktop credential is not bound to a user. Please sign in again.',
            ], 403);
        }

        $destination = (string) $request->input('destination', '/tenant');
        if (! str_starts_with($destination, '/tenant') || str_starts_with($destination, '//')) {
            $destination = '/tenant';
        }

        $bridge = Str::random(80);
        Cache::put('desktop-web-session:'.hash('sha256', $bridge), [
            'user_id' => $user->id,
            'company_id' => $company->id,
            'destination' => $destination,
        ], now()->addMinutes(2));

        return response()->json([
            'success' => true,
            'url' => url('/desktop/session/'.$bridge),
        ]);
    }

    /**
     * 4. Handshake and verify terminal connectivity and settings.
     * GET /api/v1/pos/status
     */
    public function status(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        return response()->json([
            'success' => true,
            'status' => 'online',
            'server_time' => now()->toIso8601String(),
            'header' => $company->getDrawerHeaderPayload(),
            'drawer_header' => $company->getDrawerHeaderPayload(),
            'company' => [
                'id' => $company->id,
                'name' => $company->display_name,
                'business_name' => $company->display_name,
                'trade_name' => $company->getEffectiveTradeName(),
                'trading_name' => $company->getEffectiveTradeName(),
                'store_name' => $company->display_name,
                'display_name' => $company->display_name,
                'currency' => $company->currency ?? 'USD',
                'currency_symbol' => $company->currency_symbol ?? '$',
                'tax_number' => $company->document ?? $company->tax_id ?? '',
                'timezone' => $company->resolveTimezone(),
                'address' => $company->address ?? '',
                'city' => $company->city ?? '',
                'state' => $company->state ?? '',
                'phone' => $company->phone ?? '',
                'receipt_footer_note' => $company->receipt_footer_note ?? 'Thank you for your business!',
                'logo_url' => $company->getLogoUrl(),
                'favicon_url' => $company->getFaviconUrl(),
                'drawer_cover_url' => $company->getDrawerCoverUrl(),
                'drawer_header' => $company->getDrawerHeaderPayload(),
                'header' => $company->getDrawerHeaderPayload(),
            ],
            'features' => [
                'offline_sync' => true,
                'thermal_printing' => true,
                'barcode_scanner' => true,
                'inventory_management' => true,
                'customer_ledger' => true,
                'analytics' => true,
                'subscription_management' => true,
                'show_powered_by' => (bool) setting('show_powered_by', true),
                'version' => '1.0.0',
            ],
        ]);
    }

    /**
     * 5. Inbound Catalog Pull (Delta synchronization since a given timestamp).
     * GET /api/v1/pos/sync-catalog
     * GET /api/v1/pos/sync-pull
     */
    public function syncPull(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $since = $request->query('since');

        $sinceCarbon = null;
        if (! empty($since)) {
            try {
                $sinceCarbon = Carbon::parse($since);
            } catch (\Throwable) {
                $sinceCarbon = null;
            }
        }

        // 1. Fetch Products Delta
        $productsQuery = Product::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id);

        if ($sinceCarbon) {
            $productsQuery->where('updated_at', '>=', $sinceCarbon);
        }

        $products = $productsQuery->get()->map(function (Product $p) {
            return [
                'id' => (string) ($p->external_id ?: $p->id),
                'server_id' => $p->id,
                'name' => $p->name,
                'barcode' => $p->barcode ?: $p->code ?: '',
                'sku' => $p->sku ?: '',
                'price' => (float) ($p->sale_price ?? 0),
                'cost_price' => (float) ($p->cost_price ?? 0),
                'stock' => (float) ($p->current_stock ?? 0),
                'min_stock' => (float) ($p->minimum_stock ?? 0),
                'unit' => $p->unit ?? 'pcs',
                'category_id' => $p->category_id ? (string) $p->category_id : null,
                'category_name' => $p->category_name ?? $p->category?->name ?? 'General',
                'category' => $p->category_name ?? $p->category?->name ?? 'General',
                'brand_name' => $p->brand_name ?? $p->brand?->name ?? '',
                'image_url' => $p->getImageUrlOrDefault(),
                'tax_rate' => (float) ($p->tax_rate ?? 0),
                'active' => (bool) $p->active,
                'variants' => $p->variants ?? [],
                'modifiers' => $p->modifiers ?? [],
                'spice_levels' => $p->spice_levels ?? [],
                'updated_at' => $p->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        });

        // 2. Fetch Categories Delta
        $categoriesQuery = Category::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id);

        if ($sinceCarbon) {
            $categoriesQuery->where('updated_at', '>=', $sinceCarbon);
        }

        $categories = $categoriesQuery->get()->map(function (Category $c) {
            return [
                'id' => (string) ($c->external_id ?: $c->id),
                'server_id' => $c->id,
                'name' => $c->name,
                'color' => $c->color ?? '#3b82f6',
                'icon' => $c->getIconAttribute(),
                'active' => (bool) ($c->active ?? true),
                'updated_at' => $c->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        });

        // 3. Fetch Customers Delta
        $customersQuery = Customer::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id);

        if ($sinceCarbon) {
            $customersQuery->where('updated_at', '>=', $sinceCarbon);
        }

        $customers = $customersQuery->get()->map(function (Customer $cust) {
            return [
                'id' => (string) ($cust->external_id ?: $cust->id),
                'server_id' => $cust->id,
                'name' => $cust->name,
                'phone' => $cust->phone ?? '',
                'email' => $cust->email ?? '',
                'document' => $cust->document ?? $cust->tax_id ?? '',
                'balance_due' => $cust->total_due,
                'loyalty_points' => (int) ($cust->loyalty_points ?? 0),
                'updated_at' => $cust->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        });

        // 4. Fetch Quotations Delta
        $quotationsQuery = Sale::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('operation_type', 'quotation');

        if ($sinceCarbon) {
            $quotationsQuery->where('updated_at', '>=', $sinceCarbon);
        }

        $quotations = $quotationsQuery->latest('created_at')->limit(100)->get()->map(function (Sale $q) {
            $items = is_array($q->items) ? $q->items : (json_decode($q->items ?? '', true) ?: []);

            return [
                'id' => (string) ($q->external_id ?: $q->id),
                'server_id' => $q->id,
                'quote_number' => $q->sale_number,
                'customer_id' => $q->customer_id ? (string) $q->customer_id : null,
                'customer_name' => $q->customer_name ?: ($q->customer?->name ?? 'Customer'),
                'customer_phone' => $q->customer?->phone ?? '',
                'customer_email' => $q->customer?->email ?? '',
                'items' => $items,
                'subtotal' => (float) ($q->subtotal ?? $q->total),
                'tax' => (float) ($q->tax_amount ?? 0),
                'discount' => (float) ($q->discount ?? 0),
                'total' => (float) ($q->total ?? 0),
                'notes' => $q->notes ?? '',
                'terms' => $q->terms ?? '',
                'valid_until' => $q->due_date?->toIso8601String() ?? '',
                'status' => $q->status ?: 'draft',
                'updated_at' => $q->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                'createdAt' => $q->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        });

        // 5. Fetch Sales Delta (completed sales, not quotations — pushed by the client
        // via sync-push/sync-batch but never previously pulled back down, so a sale
        // made on the web never reached the desktop client's local history).
        $salesQuery = Sale::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) {
                // operation_type is null for regular sales created via the
                // existing sync-push/sync-batch path (it's only ever set
                // explicitly to 'quotation') — a plain != excludes nulls in SQL.
                $q->whereNull('operation_type')->orWhere('operation_type', '!=', 'quotation');
            });

        if ($sinceCarbon) {
            $salesQuery->where('updated_at', '>=', $sinceCarbon);
        }

        $sales = $salesQuery->latest('created_at')->limit(200)->get()->map(function (Sale $s) {
            $items = is_array($s->items) ? $s->items : (json_decode($s->items ?? '', true) ?: []);

            return [
                'id' => (string) ($s->external_id ?: $s->id),
                'server_id' => $s->id,
                'sale_number' => $s->sale_number,
                'customer_id' => $s->customer_id ? (string) $s->customer_id : null,
                'customer_name' => $s->customer_name,
                'items' => $items,
                'total' => (float) ($s->total ?? 0),
                'net_amount' => (float) ($s->net_amount ?? $s->total ?? 0),
                'discount' => (float) ($s->discount ?? 0),
                'tax' => (float) ($s->tax_amount ?? 0),
                'payment_method' => $s->payment_method,
                'payment_status' => $s->payment_status,
                'paid_amount' => (float) ($s->paid_amount ?? 0),
                'due_amount' => (float) ($s->due_amount ?? 0),
                'status' => $s->status,
                'notes' => $s->notes ?? '',
                'updated_at' => $s->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                'createdAt' => $s->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        });

        // 5b. Fetch Suppliers Delta
        $suppliersQuery = Supplier::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id);

        if ($sinceCarbon) {
            $suppliersQuery->where('updated_at', '>=', $sinceCarbon);
        }

        $suppliers = $suppliersQuery->get()->map(function (Supplier $sup) {
            return [
                'id' => (string) ($sup->external_id ?: $sup->id),
                'server_id' => $sup->id,
                'name' => $sup->name,
                'legal_name' => $sup->legal_name,
                'phone' => $sup->phone ?? '',
                'email' => $sup->email ?? '',
                'active' => (bool) ($sup->active ?? true),
                'updated_at' => $sup->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        });

        // 5c. Fetch Brands & Units Delta (small, low-churn — always sent in full)
        $brands = Brand::query()->withoutGlobalScope('company')->where('company_id', $company->id)
            ->get()->map(fn (Brand $b) => [
                'id' => (string) ($b->external_id ?: $b->id),
                'server_id' => $b->id,
                'name' => $b->name,
                'active' => (bool) ($b->active ?? true),
                'updated_at' => $b->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            ]);

        $units = Unit::query()->withoutGlobalScope('company')->where('company_id', $company->id)
            ->get()->map(fn (Unit $u) => [
                'id' => (string) ($u->external_id ?: $u->id),
                'server_id' => $u->id,
                'name' => $u->name,
                'abbreviation' => $u->abbreviation,
                'updated_at' => $u->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            ]);

        // 6. Fetch Taxes Delta
        $taxesQuery = TaxRule::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id);

        if ($sinceCarbon) {
            $taxesQuery->where('updated_at', '>=', $sinceCarbon);
        }

        $taxes = $taxesQuery->get()->map(function (TaxRule $t) {
            return [
                'id' => (string) $t->id,
                'name' => $t->tax_name,
                'rate' => (float) $t->rate,
                'is_default' => (bool) $t->is_default,
                'active' => (bool) $t->active,
                'type' => $t->type ?? 'percentage',
                'calc_type' => $t->calc_type ?? 'exclusive',
                'updated_at' => $t->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        });

        // Company/business settings — always sent in full (a single row,
        // no "since" delta to compute) so a change made in Settings on the
        // web reaches every device on the very next sync cycle. Deliberately
        // a plain business-info subset: billing/plan fields (plan_name,
        // expires_at, max_users/max_devices), the tax gateway credentials,
        // and identity fields (slug/custom_domain/unique_account_id) are
        // intentionally excluded — those are platform-controlled or would
        // risk a stale device overwriting billing state, not something a
        // desktop sync cycle should ever push back either.
        $companySettings = [
            'name' => $company->name,
            'trade_name' => $company->trade_name,
            'legal_name' => $company->legal_name,
            'tax_id' => $company->tax_id,
            'email' => $company->email,
            'phone' => $company->phone,
            'website' => $company->website,
            'address' => $company->address,
            'city' => $company->city,
            'state' => $company->state,
            'postal_code' => $company->postal_code,
            'country' => $company->country,
            'currency' => $company->currency,
            'currency_symbol' => $company->currency_symbol,
            'currency_decimals' => $company->currency_decimals,
            'currency_symbol_position' => $company->currency_symbol_position,
            'pos_mode' => $company->pos_mode,
            'pos_layout' => $company->pos_layout,
            'receipt_format' => $company->receipt_format,
            'invoice_prefix' => $company->invoice_prefix,
            'quotation_prefix' => $company->quotation_prefix,
            'invoice_terms' => $company->invoice_terms,
            'quote_terms' => $company->quote_terms,
            'bank_details' => $company->bank_details,
            'logo_url' => $company->getLogoUrl(),
            'favicon_url' => $company->getFaviconUrl(),
            'drawer_cover_url' => $company->getDrawerCoverUrl(),
        ];

        // Tombstones: rows deleted (by a delta-only client's queued offline
        // delete, replayed through syncBatch) since `since`. Keyed by the
        // plural entity name, matching the data arrays. Additive — a client
        // that predates this key simply never prunes, which is the previous
        // behaviour. With no `since` every tombstone is returned so a fresh
        // client starts already converged.
        $tombstoneQuery = DB::table('sync_tombstones')->where('company_id', $company->id);
        if ($sinceCarbon) {
            $tombstoneQuery->where('deleted_at', '>=', $sinceCarbon);
        }
        $entityPlural = [];
        foreach (self::syncEntityConfig() as $entity => $cfg) {
            $entityPlural[$entity] = $cfg['plural'];
        }
        $deletedIds = array_fill_keys(array_values($entityPlural), []);
        foreach ($tombstoneQuery->get(['entity', 'external_id']) as $tomb) {
            $bucket = $entityPlural[$tomb->entity] ?? null;
            if ($bucket !== null) {
                $deletedIds[$bucket][] = $tomb->external_id;
            }
        }

        $payload = [
            'success' => true,
            'server_time' => now()->toIso8601String(),
            'counts' => [
                'products' => $products->count(),
                'categories' => $categories->count(),
                'customers' => $customers->count(),
                'quotations' => $quotations->count(),
                'sales' => $sales->count(),
                'suppliers' => $suppliers->count(),
                'brands' => $brands->count(),
                'units' => $units->count(),
                'taxes' => $taxes->count(),
            ],
            'products' => $products,
            'categories' => $categories,
            'customers' => $customers,
            'quotations' => $quotations,
            'sales' => $sales,
            'suppliers' => $suppliers,
            'brands' => $brands,
            'units' => $units,
            'taxes' => $taxes,
            'company' => $companySettings,
            'deleted_ids' => $deletedIds,
        ];

        // Optional `?entities=products,customers` — let a client pull one
        // entity at a time (progress UI, retry a single failed table) without
        // downloading the whole delta each cycle. Always keeps `success`,
        // `server_time`, `company`, and the matching `deleted_ids` subset.
        $wanted = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $request->query('entities', '')),
        )));
        if ($wanted !== []) {
            $keep = [
                'success' => true,
                'server_time' => $payload['server_time'],
                'company' => $companySettings,
                'counts' => [],
                'deleted_ids' => [],
            ];
            foreach ($wanted as $w) {
                if (array_key_exists($w, $payload) && ! in_array($w, ['success', 'server_time', 'company', 'counts', 'deleted_ids'], true)) {
                    $keep[$w] = $payload[$w];
                    $keep['counts'][$w] = $payload['counts'][$w] ?? 0;
                }
                if (array_key_exists($w, $deletedIds)) {
                    $keep['deleted_ids'][$w] = $deletedIds[$w];
                }
            }
            $payload = $keep;
        }

        return response()->json($payload);
    }

    /**
     * 6. Outbound Sales Push (Batch ingestion of offline-recorded sales).
     * POST /api/v1/pos/sync-sales
     * POST /api/v1/pos/sync-push
     */
    public function syncPush(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'sales' => ['required', 'array', 'min:1'],
            'sales.*.id' => ['required', 'string'],
            'sales.*.total' => ['required', 'numeric', 'min:0'],
            'sales.*.payment_method' => ['nullable', 'string'],
            'sales.*.items' => ['required', 'array', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error in sales sync payload.',
                'details' => $validator->errors(),
            ], 422);
        }

        $salesPayload = $request->input('sales', []);
        [$syncedIds, $rejected] = $this->processSalesBatch($salesPayload, $company, $user);

        return response()->json([
            'success' => true,
            'message' => sprintf('Successfully synchronized %d sale(s).', count($syncedIds)),
            'synced_ids' => $syncedIds,
            'rejected' => $rejected,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * The eight entity kinds the offline desktop client can push
     * creates / edits / deletes for: the Eloquent model, the plural key used
     * in request/response arrays (`deleted_<plural>`, `deleted_ids.<plural>`),
     * and whether the table carries a client `external_id`. Quotations are
     * Sale rows with operation_type = 'quotation'.
     *
     * @return array<string, array{model: class-string<Model>, plural: string, external_id: bool}>
     */
    protected static function syncEntityConfig(): array
    {
        return [
            'product' => ['model' => Product::class, 'plural' => 'products', 'external_id' => true],
            'customer' => ['model' => Customer::class, 'plural' => 'customers', 'external_id' => true],
            'quotation' => ['model' => Sale::class, 'plural' => 'quotations', 'external_id' => true],
            'category' => ['model' => Category::class, 'plural' => 'categories', 'external_id' => true],
            'brand' => ['model' => Brand::class, 'plural' => 'brands', 'external_id' => true],
            'supplier' => ['model' => Supplier::class, 'plural' => 'suppliers', 'external_id' => true],
            'unit' => ['model' => Unit::class, 'plural' => 'units', 'external_id' => true],
            'tax_rule' => ['model' => TaxRule::class, 'plural' => 'tax_rules', 'external_id' => false],
        ];
    }

    /**
     * Last-write-wins guard for a pushed field edit: apply it unless the
     * payload carries an updated_at that is not newer than the server row's
     * — a client with no updated_at (or one already applied) always applies,
     * matching prior behavior for rows synced before this field existed.
     */
    protected function clientRowIsNewer(Model $existing, array $payload): bool
    {
        if (empty($payload['updated_at'])) {
            return true;
        }

        try {
            return Carbon::parse($payload['updated_at'])->greaterThan($existing->updated_at ?? Carbon::createFromTimestamp(0));
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * One row for the sync-batch `conflicts` array: an offline edit/delete the
     * server dropped because its own copy of the row was already newer. The
     * desktop Sync panel lists these so the change isn't lost silently; the
     * client also re-pulls the row so its local copy converges on the server's.
     */
    protected function conflictEntry(string $entity, string $externalId, Model $serverRow, string $reason = 'server_newer'): array
    {
        return [
            'entity' => $entity,
            'id' => $externalId,
            'server_id' => (string) $serverRow->getKey(),
            'reason' => $reason,
            'server_updated_at' => optional($serverRow->updated_at)->toIso8601String(),
            'label' => $serverRow->name
                ?? $serverRow->tax_name
                ?? $serverRow->sale_number
                ?? null,
        ];
    }

    /**
     * Helper to process sales batch transactionally
     */
    protected function processSalesBatch(array $salesPayload, Company $company, ?User $user): array
    {
        $syncedIds = [];
        $rejected = [];
        // Register sessions are an accounting aid, not a prerequisite for
        // creating a sale. Associate the batch when a shift is open and
        // deliberately persist null when registers are closed or disabled.
        $cashRegisterId = CashRegister::openFor($company->id)?->id;

        DB::transaction(function () use ($salesPayload, $company, $user, $cashRegisterId, &$syncedIds, &$rejected) {
            foreach ($salesPayload as $saleData) {
                $clientUuid = (string) ($saleData['id'] ?? $saleData['client_uuid'] ?? Str::uuid()->toString());

                // Idempotency: skip if already ingested
                $existingSale = Sale::query()
                    ->withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->where('external_id', $clientUuid)
                    ->first();

                if ($existingSale) {
                    $syncedIds[] = $clientUuid;

                    continue;
                }

                $items = (array) ($saleData['items'] ?? []);
                $normalizedItems = [];
                $rateItems = [];
                $calcSubtotal = 0;

                foreach ($items as $item) {
                    $qty = (float) ($item['quantity'] ?? $item['qty'] ?? 1);
                    $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                    $productId = $item['id'] ?? $item['product_id'] ?? null;
                    $name = $item['name'] ?? $item['product_name'] ?? 'Product';
                    $lineTotal = round($qty * $price, 2);
                    $calcSubtotal += $lineTotal;

                    $normalizedItems[] = [
                        'id' => $productId,
                        'product_id' => $productId,
                        'name' => $name,
                        'price' => $price,
                        'quantity' => $qty,
                        'subtotal' => $lineTotal,
                        'total' => $lineTotal,
                    ];

                    // Decrement Stock in Cloud Database
                    $product = null;
                    if ($productId) {
                        $product = Product::query()
                            ->withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where(function ($q) use ($productId) {
                                $q->where('id', $productId)
                                    ->orWhere('external_id', (string) $productId);
                            })
                            ->first();

                        if ($product) {
                            $product->decrementStock($qty, "POS Offline Sync Sale #{$clientUuid}");
                        }
                    }

                    // The product's own tax_rate is authoritative (it's what the POS
                    // cart used to compute this line's tax); fall back to whatever
                    // rate the client attached to the item itself.
                    $itemTaxRate = (float) ($product->tax_rate ?? $item['tax_rate'] ?? 0);
                    if ($itemTaxRate > 0) {
                        $rateItems[] = [
                            'rate' => $itemTaxRate,
                            'taxable' => $lineTotal,
                            'amount' => round($lineTotal * $itemTaxRate / 100, 2),
                        ];
                    }
                }

                $total = (float) ($saleData['total'] ?? $calcSubtotal);
                $discount = (float) ($saleData['discount'] ?? 0);
                $taxAmount = (float) ($saleData['tax_amount'] ?? 0);
                $taxName = $saleData['tax_name'] ?? null;
                $taxRate = (float) ($saleData['tax_rate'] ?? 0);
                $taxBreakdown = null;

                if (! empty($rateItems)) {
                    $taxBreakdown = TaxEngineService::buildTaxSummaryFromRates(
                        $rateItems,
                        TaxEngineService::isIndia($company),
                        (string) ($taxName ?? '')
                    );
                    $firstTax = $taxBreakdown[0] ?? null;
                    if ($firstTax) {
                        $taxName = $firstTax['tax_name'];
                        $taxRate = (float) $firstTax['rate'];
                    }
                }

                $paymentMethod = $saleData['payment_method'] ?? 'cash';
                $orderNumber = $saleData['order_number'] ?? $saleData['sale_number'] ?? ('POS-'.strtoupper(substr($clientUuid, 0, 8)));
                $createdAt = isset($saleData['createdAt']) ? Carbon::parse($saleData['createdAt']) : now();

                // Associate Customer if passed
                $customerId = null;
                $customerName = $saleData['customer_name'] ?? null;
                if (! empty($saleData['customer_id'])) {
                    $cust = Customer::query()
                        ->withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->where(function ($q) use ($saleData) {
                            $q->where('id', $saleData['customer_id'])
                                ->orWhere('external_id', (string) $saleData['customer_id']);
                        })
                        ->first();

                    if ($cust) {
                        $customerId = $cust->id;
                        $customerName = $cust->name;
                        // Accrue loyalty points (1 point per 10 units spent)
                        $earnedPoints = (int) floor($total / 10);
                        if ($earnedPoints > 0) {
                            $cust->increment('loyalty_points', $earnedPoints);
                        }
                    }
                }

                // Split/multi-tender payments: an optional `payments` array of
                // {payment_method, amount, tendered?, change_returned?,
                // reference_number?} rows. Falls back to the legacy
                // credit/khata-vs-full-payment behavior when absent, so
                // payloads queued offline before this field existed keep
                // working unchanged.
                $paymentsInput = array_values(array_filter((array) ($saleData['payments'] ?? []), fn ($p) => is_array($p)));
                $isCredit = strtolower($paymentMethod) === 'credit' || strtolower($paymentMethod) === 'khata';

                if (! empty($paymentsInput)) {
                    $paidAmount = min($total, round(array_sum(array_map(fn ($p) => (float) ($p['amount'] ?? 0), $paymentsInput)), 2));
                } elseif (array_key_exists('paid_amount', $saleData)) {
                    // Explicit override for a zero/partial payment made with a
                    // single tender (no split rows needed) — takes precedence
                    // over the legacy credit/khata string-matching below.
                    $paidAmount = min($total, max(0, round((float) $saleData['paid_amount'], 2)));
                } else {
                    $paidAmount = $isCredit ? 0 : $total;
                }

                $dueAmount = max(0, round($total - $paidAmount, 2));
                $paymentStatus = $dueAmount <= 0.001 ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'pending');

                // A due/credit/partial sale must be attached to a customer so
                // the balance has somewhere to be tracked — reject just this
                // sale rather than the whole batch.
                if ($dueAmount > 0 && empty($customerId)) {
                    $rejected[] = [
                        'id' => $clientUuid,
                        'error' => 'A customer must be selected for due, partial, or credit sales.',
                    ];

                    continue;
                }

                $sale = Sale::create([
                    'company_id' => $company->id,
                    'external_id' => $clientUuid,
                    'sale_number' => $orderNumber,
                    'user_id' => $user?->id,
                    'customer_id' => $customerId,
                    'customer_name' => $customerName,
                    'cash_register_id' => $cashRegisterId,
                    'total' => $total,
                    'net_amount' => max(0, $total - $discount),
                    'discount' => $discount,
                    'tax_amount' => $taxAmount,
                    'tax_name' => $taxName,
                    'tax_rate' => $taxRate,
                    'tax_breakdown' => $taxBreakdown,
                    'payment_method' => ! empty($paymentsInput) ? 'split' : $paymentMethod,
                    'status' => 'completed',
                    'payment_status' => $paymentStatus,
                    'paid_amount' => $paidAmount,
                    'due_amount' => $dueAmount,
                    'due_date' => $dueAmount > 0 ? ($saleData['due_date'] ?? null) : null,
                    'due_reminder_at' => $dueAmount > 0 && ! empty($saleData['due_reminder_at'])
                        ? Carbon::parse($saleData['due_reminder_at'])->utc()
                        : null,
                    'items' => $normalizedItems,
                    'created_at' => $createdAt,
                    'updated_at' => now(),
                ]);

                if (! empty($paymentsInput)) {
                    foreach ($paymentsInput as $row) {
                        $rowAmount = (float) ($row['amount'] ?? 0);
                        if ($rowAmount <= 0) {
                            continue;
                        }

                        OrderPayment::create([
                            'company_id' => $company->id,
                            'sale_id' => $sale->id,
                            'cash_register_id' => $cashRegisterId,
                            'payment_method' => $row['payment_method'] ?? $paymentMethod,
                            'amount' => $rowAmount,
                            'tendered' => $row['tendered'] ?? null,
                            'change_returned' => (float) ($row['change_returned'] ?? 0),
                            'reference_number' => $row['reference_number'] ?? null,
                        ]);
                    }
                } elseif ($paidAmount > 0) {
                    OrderPayment::create([
                        'company_id' => $company->id,
                        'sale_id' => $sale->id,
                        'cash_register_id' => $cashRegisterId,
                        'payment_method' => $paymentMethod,
                        'amount' => $paidAmount,
                        'tendered' => $saleData['tendered'] ?? null,
                        'change_returned' => (float) ($saleData['change_returned'] ?? 0),
                    ]);
                }

                // Ledger entry for any due balance is written automatically
                // by SaleObserver on Sale creation.

                $syncedIds[] = $clientUuid;
            }
        });

        return [$syncedIds, $rejected];
    }

    /**
     * 7. Full Combined Batch Sync
     * POST /api/v1/pos/sync-batch
     */
    public function syncBatch(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $syncedSales = [];
        $syncedAdjustments = [];
        $syncedPayments = [];
        $syncedProducts = [];
        $syncedCustomers = [];
        $syncedQuotations = [];
        $syncedCategories = [];
        $syncedBrands = [];
        $syncedSuppliers = [];
        $syncedUnits = [];
        // entity => list of external_ids the client asked us to delete and we
        // either deleted or already had gone. Server-newer rows are skipped
        // (not listed) so the client re-pulls them on the next delta.
        $deleted = [];
        // Offline edits/deletes that lost the last-write-wins race: the server
        // row was already newer, so the client's change was dropped. Surfaced
        // to the desktop's Sync panel so the change isn't silently lost.
        $conflicts = [];

        DB::beginTransaction();
        try {
            // 1. Process Offline Created Products
            if ($request->has('created_products') && is_array($request->input('created_products'))) {
                foreach ($request->input('created_products') as $prodData) {
                    $extId = (string) ($prodData['id'] ?? Str::uuid()->toString());
                    $p = Product::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        // Match the client id against external_id first; fall
                        // back to the numeric primary key so an offline *edit*
                        // of a row that was created on the web (no external_id)
                        // still lands on the right record instead of inserting
                        // a duplicate. A UUID compared to a bigint column just
                        // never matches, so this is safe for offline creates.
                        ->where(fn ($q) => $q->where('external_id', $extId)->orWhere('id', $extId))
                        ->first();
                    if (! $p && ! empty($prodData['barcode'])) {
                        $p = Product::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where('barcode', $prodData['barcode'])
                            ->first();
                    }

                    // Descriptive/pricing fields only — current_stock is deliberately
                    // excluded here and can only change via decrementStock()/inventory
                    // adjustments, never a blind overwrite from a possibly-stale offline
                    // snapshot (concurrent online sales may have moved stock meanwhile).
                    $attrs = [
                        'name' => $prodData['name'] ?? 'New Product',
                        'barcode' => $prodData['barcode'] ?? null,
                        'sku' => $prodData['sku'] ?? null,
                        'sale_price' => (float) ($prodData['price'] ?? $prodData['sale_price'] ?? 0),
                        'cost_price' => (float) ($prodData['cost_price'] ?? 0),
                        'minimum_stock' => (float) ($prodData['min_stock'] ?? 0),
                        'unit' => $prodData['unit'] ?? 'pcs',
                        'category_name' => $prodData['category_name'] ?? 'General',
                        'tax_rate' => (float) ($prodData['tax_rate'] ?? 0),
                    ];

                    if (! $p) {
                        $p = Product::create($attrs + [
                            'company_id' => $company->id,
                            'external_id' => $extId,
                            'current_stock' => (float) ($prodData['stock'] ?? $prodData['current_stock'] ?? 0),
                            'active' => true,
                        ]);
                    } elseif ($this->clientRowIsNewer($p, $prodData)) {
                        // A field edit made offline (e.g. name/price change) — apply it
                        // unless the server has a strictly newer edit for the same row.
                        $p->update($attrs);
                    } elseif (! empty($prodData['updated_at'])) {
                        $conflicts[] = $this->conflictEntry('product', $extId, $p);
                    }
                    $syncedProducts[] = $extId;
                }
            }

            // 2. Process Offline Created Customers
            if ($request->has('created_customers') && is_array($request->input('created_customers'))) {
                foreach ($request->input('created_customers') as $custData) {
                    $extId = (string) ($custData['id'] ?? Str::uuid()->toString());
                    $c = Customer::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        // Match the client id against external_id first; fall
                        // back to the numeric primary key so an offline *edit*
                        // of a row that was created on the web (no external_id)
                        // still lands on the right record instead of inserting
                        // a duplicate. A UUID compared to a bigint column just
                        // never matches, so this is safe for offline creates.
                        ->where(fn ($q) => $q->where('external_id', $extId)->orWhere('id', $extId))
                        ->first();
                    if (! $c && ! empty($custData['phone'])) {
                        $c = Customer::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where('phone', $custData['phone'])
                            ->first();
                    }

                    // loyalty_points is deliberately excluded from the update path — it
                    // only ever changes via increment() from an actual sale, never a
                    // blind overwrite from a possibly-stale offline snapshot.
                    $attrs = [
                        'name' => $custData['name'] ?? 'Customer',
                        'phone' => $custData['phone'] ?? null,
                        'email' => $custData['email'] ?? null,
                        'document' => $custData['document'] ?? null,
                    ];

                    if (! $c) {
                        $c = Customer::create($attrs + [
                            'company_id' => $company->id,
                            'external_id' => $extId,
                            'loyalty_points' => (int) ($custData['loyalty_points'] ?? 0),
                        ]);
                    } elseif ($this->clientRowIsNewer($c, $custData)) {
                        $c->update($attrs);
                    } elseif (! empty($custData['updated_at'])) {
                        $conflicts[] = $this->conflictEntry('customer', $extId, $c);
                    }
                    $syncedCustomers[] = $extId;
                }
            }

            // 2b. Process Offline Created/Edited Categories, Brands, Suppliers,
            // Units — created on the desktop app the same way products/customers
            // are (via the same web UI running locally), but until now nothing
            // ever pushed them back up: DesktopSyncClient only pulled these four
            // tables, so a category or brand added on desktop stayed stuck on
            // that one device forever. Kept intentionally simple (create-or-
            // update by external_id, name fallback to avoid an obvious dupe)
            // since these are small reference tables, not transactional data.
            if ($request->has('created_categories') && is_array($request->input('created_categories'))) {
                foreach ($request->input('created_categories') as $catData) {
                    $extId = (string) ($catData['id'] ?? Str::uuid()->toString());
                    $cat = Category::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        // Match the client id against external_id first; fall
                        // back to the numeric primary key so an offline *edit*
                        // of a row that was created on the web (no external_id)
                        // still lands on the right record instead of inserting
                        // a duplicate. A UUID compared to a bigint column just
                        // never matches, so this is safe for offline creates.
                        ->where(fn ($q) => $q->where('external_id', $extId)->orWhere('id', $extId))
                        ->first();
                    if (! $cat && ! empty($catData['name'])) {
                        $cat = Category::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where('name', $catData['name'])
                            ->first();
                    }

                    $attrs = [
                        'name' => $catData['name'] ?? 'Category',
                        'color' => $catData['color'] ?? null,
                        'description' => $catData['description'] ?? null,
                        'active' => array_key_exists('active', $catData) ? (bool) $catData['active'] : true,
                    ];

                    if (! $cat) {
                        $cat = Category::create($attrs + ['company_id' => $company->id, 'external_id' => $extId]);
                    } elseif ($this->clientRowIsNewer($cat, $catData)) {
                        $cat->update($attrs);
                    } elseif (! empty($catData['updated_at'])) {
                        $conflicts[] = $this->conflictEntry('category', $extId, $cat);
                    }
                    $syncedCategories[] = $extId;
                }
            }

            if ($request->has('created_brands') && is_array($request->input('created_brands'))) {
                foreach ($request->input('created_brands') as $brandData) {
                    $extId = (string) ($brandData['id'] ?? Str::uuid()->toString());
                    $brand = Brand::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        // Match the client id against external_id first; fall
                        // back to the numeric primary key so an offline *edit*
                        // of a row that was created on the web (no external_id)
                        // still lands on the right record instead of inserting
                        // a duplicate. A UUID compared to a bigint column just
                        // never matches, so this is safe for offline creates.
                        ->where(fn ($q) => $q->where('external_id', $extId)->orWhere('id', $extId))
                        ->first();
                    if (! $brand && ! empty($brandData['name'])) {
                        $brand = Brand::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where('name', $brandData['name'])
                            ->first();
                    }

                    $attrs = [
                        'name' => $brandData['name'] ?? 'Brand',
                        'active' => array_key_exists('active', $brandData) ? (bool) $brandData['active'] : true,
                    ];

                    if (! $brand) {
                        $brand = Brand::create($attrs + ['company_id' => $company->id, 'external_id' => $extId]);
                    } elseif ($this->clientRowIsNewer($brand, $brandData)) {
                        $brand->update($attrs);
                    } elseif (! empty($brandData['updated_at'])) {
                        $conflicts[] = $this->conflictEntry('brand', $extId, $brand);
                    }
                    $syncedBrands[] = $extId;
                }
            }

            if ($request->has('created_suppliers') && is_array($request->input('created_suppliers'))) {
                foreach ($request->input('created_suppliers') as $supData) {
                    $extId = (string) ($supData['id'] ?? Str::uuid()->toString());
                    $sup = Supplier::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        // Match the client id against external_id first; fall
                        // back to the numeric primary key so an offline *edit*
                        // of a row that was created on the web (no external_id)
                        // still lands on the right record instead of inserting
                        // a duplicate. A UUID compared to a bigint column just
                        // never matches, so this is safe for offline creates.
                        ->where(fn ($q) => $q->where('external_id', $extId)->orWhere('id', $extId))
                        ->first();
                    if (! $sup && ! empty($supData['name'])) {
                        $sup = Supplier::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where('name', $supData['name'])
                            ->first();
                    }

                    $attrs = [
                        'name' => $supData['name'] ?? 'Supplier',
                        'legal_name' => $supData['legal_name'] ?? null,
                        'trade_name' => $supData['trade_name'] ?? null,
                        'tax_id' => $supData['tax_id'] ?? null,
                        'email' => $supData['email'] ?? null,
                        'phone' => $supData['phone'] ?? null,
                        'city' => $supData['city'] ?? null,
                        'state' => $supData['state'] ?? null,
                        'active' => array_key_exists('active', $supData) ? (bool) $supData['active'] : true,
                    ];

                    if (! $sup) {
                        $sup = Supplier::create($attrs + ['company_id' => $company->id, 'external_id' => $extId]);
                    } elseif ($this->clientRowIsNewer($sup, $supData)) {
                        $sup->update($attrs);
                    } elseif (! empty($supData['updated_at'])) {
                        $conflicts[] = $this->conflictEntry('supplier', $extId, $sup);
                    }
                    $syncedSuppliers[] = $extId;
                }
            }

            if ($request->has('created_units') && is_array($request->input('created_units'))) {
                foreach ($request->input('created_units') as $unitData) {
                    $extId = (string) ($unitData['id'] ?? Str::uuid()->toString());
                    $unit = Unit::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        // Match the client id against external_id first; fall
                        // back to the numeric primary key so an offline *edit*
                        // of a row that was created on the web (no external_id)
                        // still lands on the right record instead of inserting
                        // a duplicate. A UUID compared to a bigint column just
                        // never matches, so this is safe for offline creates.
                        ->where(fn ($q) => $q->where('external_id', $extId)->orWhere('id', $extId))
                        ->first();
                    if (! $unit && ! empty($unitData['name'])) {
                        $unit = Unit::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where('name', $unitData['name'])
                            ->first();
                    }

                    $attrs = [
                        'name' => $unitData['name'] ?? 'Unit',
                        'abbreviation' => $unitData['abbreviation'] ?? null,
                    ];

                    if (! $unit) {
                        $unit = Unit::create($attrs + ['company_id' => $company->id, 'external_id' => $extId]);
                    } elseif ($this->clientRowIsNewer($unit, $unitData)) {
                        $unit->update($attrs);
                    } elseif (! empty($unitData['updated_at'])) {
                        $conflicts[] = $this->conflictEntry('unit', $extId, $unit);
                    }
                    $syncedUnits[] = $extId;
                }
            }

            // 3. Process Sales
            if ($request->has('sales') && is_array($request->input('sales')) && count($request->input('sales')) > 0) {
                [$syncedSales] = $this->processSalesBatch($request->input('sales'), $company, $user);
            }

            // 4. Process Inventory Adjustments
            if ($request->has('inventory_adjustments') && is_array($request->input('inventory_adjustments'))) {
                foreach ($request->input('inventory_adjustments') as $adj) {
                    $adjId = (string) ($adj['id'] ?? Str::uuid()->toString());
                    $isNewOperation = DB::table('desktop_sync_receipts')->insertOrIgnore([
                        'company_id' => $company->id,
                        'operation_type' => 'inventory_adjustment',
                        'external_id' => $adjId,
                        'created_at' => now(),
                    ]) === 1;
                    if (! $isNewOperation) {
                        $syncedAdjustments[] = $adjId;

                        continue;
                    }
                    $prodId = $adj['product_id'] ?? null;
                    $type = $adj['type'] ?? 'add';
                    $qty = (float) ($adj['quantity'] ?? $adj['qty'] ?? 0);

                    if ($prodId) {
                        $prod = Product::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where(fn ($q) => $q->where('id', $prodId)->orWhere('external_id', (string) $prodId))
                            ->first();

                        if ($prod) {
                            if ($type === 'add') {
                                $prod->increment('current_stock', $qty);
                            } elseif ($type === 'subtract') {
                                $prod->decrement('current_stock', $qty);
                            } elseif ($type === 'set') {
                                $prod->update(['current_stock' => $qty]);
                            }
                        }
                    }
                    $syncedAdjustments[] = $adjId;
                }
            }

            // 5. Process Customer Ledger Payments
            if ($request->has('customer_payments') && is_array($request->input('customer_payments'))) {
                foreach ($request->input('customer_payments') as $pay) {
                    $payId = (string) ($pay['id'] ?? Str::uuid()->toString());
                    $isNewOperation = DB::table('desktop_sync_receipts')->insertOrIgnore([
                        'company_id' => $company->id,
                        'operation_type' => 'customer_payment',
                        'external_id' => $payId,
                        'created_at' => now(),
                    ]) === 1;
                    if (! $isNewOperation) {
                        $syncedPayments[] = $payId;

                        continue;
                    }
                    $custId = $pay['customer_id'] ?? null;
                    $amount = (float) ($pay['amount'] ?? 0);
                    $method = $pay['payment_method'] ?? 'cash';
                    $ref = $pay['reference'] ?? null;
                    $notes = $pay['notes'] ?? 'POS Offline Customer Ledger Payment';

                    if ($custId && $amount > 0) {
                        $cust = Customer::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where(fn ($q) => $q->where('id', $custId)->orWhere('external_id', (string) $custId))
                            ->first();

                        if ($cust) {
                            $dueSales = Sale::withoutGlobalScope('company')
                                ->where('company_id', $company->id)
                                ->where('customer_id', $cust->id)
                                ->where('due_amount', '>', 0)
                                ->where('status', '!=', 'cancelled')
                                ->orderBy('created_at')
                                ->get();

                            $rem = $amount;
                            foreach ($dueSales as $sale) {
                                if ($rem <= 0) {
                                    break;
                                }
                                $apply = min($rem, (float) $sale->due_amount);
                                $newPaid = (float) $sale->paid_amount + $apply;
                                $newDue = max(0, (float) $sale->total - $newPaid);
                                $sale->update([
                                    'paid_amount' => $newPaid,
                                    'due_amount' => $newDue,
                                    'payment_status' => $newDue <= 0.001 ? 'paid' : 'partially_paid',
                                ]);

                                $orderPayment = OrderPayment::create([
                                    'company_id' => $company->id,
                                    'sale_id' => $sale->id,
                                    'payment_method' => $method,
                                    'amount' => $apply,
                                    'tendered' => $apply,
                                    'change_returned' => 0,
                                    'reference_number' => $ref,
                                    'notes' => $notes,
                                ]);
                                app(CustomerLedgerService::class)->recordPayment($sale, $orderPayment);
                                $rem -= $apply;
                            }
                        }
                    }
                    $syncedPayments[] = $payId;
                }
            }

            // 6. Process Quotations
            if ($request->has('quotations') && is_array($request->input('quotations'))) {
                foreach ($request->input('quotations') as $quoteData) {
                    $extId = (string) ($quoteData['id'] ?? Str::uuid()->toString());
                    $quote = Sale::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        // Match the client id against external_id first; fall
                        // back to the numeric primary key so an offline *edit*
                        // of a row that was created on the web (no external_id)
                        // still lands on the right record instead of inserting
                        // a duplicate. A UUID compared to a bigint column just
                        // never matches, so this is safe for offline creates.
                        ->where(fn ($q) => $q->where('external_id', $extId)->orWhere('id', $extId))
                        ->first();

                    $items = (array) ($quoteData['items'] ?? []);
                    $total = (float) ($quoteData['total'] ?? 0);
                    $discount = (float) ($quoteData['discount'] ?? 0);
                    $tax = (float) ($quoteData['tax'] ?? 0);
                    $quoteNumber = $quoteData['quote_number'] ?? $quoteData['sale_number'] ?? ('QUO-'.strtoupper(substr($extId, 0, 8)));
                    $createdAt = isset($quoteData['createdAt']) ? Carbon::parse($quoteData['createdAt']) : now();

                    $customerId = null;
                    $customerName = $quoteData['customer_name'] ?? null;
                    if (! empty($quoteData['customer_id'])) {
                        $cust = Customer::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where(function ($q) use ($quoteData) {
                                $q->where('id', $quoteData['customer_id'])
                                    ->orWhere('external_id', (string) $quoteData['customer_id']);
                            })
                            ->first();

                        if ($cust) {
                            $customerId = $cust->id;
                            $customerName = $cust->name;
                        }
                    }

                    if ($quote) {
                        $quote->update([
                            'customer_id' => $customerId,
                            'customer_name' => $customerName,
                            'total' => $total,
                            'net_amount' => max(0, $total - $discount),
                            'discount' => $discount,
                            'tax_amount' => $tax,
                            'status' => $quoteData['status'] ?? $quote->status ?? 'draft',
                            'notes' => $quoteData['notes'] ?? $quote->notes,
                            'terms' => $quoteData['terms'] ?? $quote->terms,
                            'items' => $items,
                        ]);
                    } else {
                        Sale::create([
                            'company_id' => $company->id,
                            'external_id' => $extId,
                            'sale_number' => $quoteNumber,
                            'user_id' => $user?->id,
                            'customer_id' => $customerId,
                            'customer_name' => $customerName,
                            'total' => $total,
                            'net_amount' => max(0, $total - $discount),
                            'discount' => $discount,
                            'tax_amount' => $tax,
                            'status' => $quoteData['status'] ?? 'draft',
                            'operation_type' => 'quotation',
                            'notes' => $quoteData['notes'] ?? null,
                            'terms' => $quoteData['terms'] ?? null,
                            'items' => $items,
                            'created_at' => $createdAt,
                            'updated_at' => now(),
                        ]);
                    }
                    $syncedQuotations[] = $extId;
                }
            }

            // 7. Process Offline Deletes (create/edit already handled above).
            //
            // The desktop client can delete a record while disconnected; it
            // queues the delete and replays it here in `deleted_<entity>`
            // arrays of `{id, deleted_at, updated_at?}`. Rules:
            //   - idempotent: a `desktop_sync_receipts` row (`delete_<entity>`)
            //     guards against re-processing the same queued delete;
            //   - last-write-wins: if the server row was edited *after* the
            //     offline delete (clientRowIsNewer === false) the delete is
            //     skipped and the row survives — the client re-pulls it next
            //     delta and converges to the server;
            //   - otherwise the row is removed and a `sync_tombstones` row is
            //     written so every other delta-only client learns it is gone
            //     (`deleted_ids` in sync-pull).
            foreach (self::syncEntityConfig() as $entity => $cfg) {
                $modelClass = $cfg['model'];
                $plural = $cfg['plural'];
                $hasExternalId = $cfg['external_id'];
                $key = 'deleted_'.$plural;
                if (! $request->has($key) || ! is_array($request->input($key))) {
                    continue;
                }
                $rows = [];
                foreach ($request->input($key) as $delData) {
                    if (is_string($delData)) {
                        $delData = ['id' => $delData];
                    }
                    if (! is_array($delData)) {
                        continue;
                    }
                    $extId = (string) ($delData['id'] ?? $delData['external_id'] ?? '');
                    if ($extId === '') {
                        continue;
                    }

                    $isNewOperation = DB::table('desktop_sync_receipts')->insertOrIgnore([
                        'company_id' => $company->id,
                        'operation_type' => 'delete_'.$entity,
                        'external_id' => $extId,
                        'created_at' => now(),
                    ]) === 1;

                    $row = $modelClass::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->where(function ($q) use ($extId, $hasExternalId) {
                            $q->where('id', $extId);
                            if ($hasExternalId) {
                                $q->orWhere('external_id', $extId);
                            }
                        })
                        ->first();

                    // Quotations live in the sales table — only ever delete the
                    // quotation, never a completed sale.
                    if ($entity === 'quotation' && $row && $row->operation_type !== 'quotation') {
                        $row = null;
                    }

                    if ($row === null) {
                        // Already gone (or never reached us) — still a success
                        // for the client, and still worth a tombstone for peers.
                        $rows[] = $extId;
                        DB::table('sync_tombstones')->updateOrInsert(
                            ['company_id' => $company->id, 'entity' => $entity, 'external_id' => $extId],
                            ['deleted_at' => $delData['deleted_at'] ?? now(), 'created_at' => now()],
                        );

                        continue;
                    }

                    if (! $isNewOperation) {
                        $rows[] = $extId;

                        continue;
                    }

                    // Server edited the row after the offline delete → keep it,
                    // and tell the client its delete was overridden.
                    if (! $this->clientRowIsNewer($row, $delData + ['updated_at' => $delData['updated_at'] ?? $delData['deleted_at'] ?? null])) {
                        $conflicts[] = $this->conflictEntry($entity, $extId, $row, 'deleted_offline_kept_on_server');

                        continue;
                    }

                    $serverId = (string) $row->getKey();
                    // A queued offline delete is an explicit removal and we
                    // hold a tombstone — hard-delete so a later re-create with
                    // the same external_id doesn't collide with a hidden
                    // soft-deleted row (only Product has SoftDeletes today).
                    method_exists($row, 'forceDelete') ? $row->forceDelete() : $row->delete();
                    DB::table('sync_tombstones')->updateOrInsert(
                        ['company_id' => $company->id, 'entity' => $entity, 'external_id' => $extId],
                        [
                            'server_id' => $serverId,
                            'deleted_at' => $delData['deleted_at'] ?? now(),
                            'created_at' => now(),
                        ],
                    );
                    $rows[] = $extId;

                    AuditLog::record('desktop_sync.deleted', $company->id, $user?->id, [
                        'entity' => $entity,
                        'external_id' => $extId,
                        'server_id' => $serverId,
                    ]);
                }
                if ($rows !== []) {
                    $deleted[$plural] = array_values(array_unique($rows));
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('POS Sync Batch Ingestion Failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'error' => 'Batch sync failed: '.$e->getMessage(),
            ], 500);
        }

        // Generic queued mutations: offline SDUI `form_submit` / `api_post`
        // actions the typed arrays above don't model. Replayed AFTER the
        // transaction has committed — each one is an independent internal
        // request against its own real route (so its own validation, auth and
        // permission middleware run exactly as for a live call), and a single
        // bad mutation is recorded as failed instead of poisoning the batch.
        // Idempotent via `desktop_sync_receipts` keyed by the client's
        // `idempotency_key`.
        [$mutationsApplied, $mutationsFailed] = $this->replayQueuedMutations($request, $company);

        // Resolve every external_id we just created/updated to its server
        // primary key so the client can backfill `server_id` on its local
        // rows and stop re-sending them. Additive: older clients ignore it.
        $idMap = [];
        $mapFor = function (string $modelClass, array $externalIds, bool $hasExternalId = true) use (&$idMap, $company) {
            $externalIds = array_values(array_filter(array_unique($externalIds)));
            if ($externalIds === []) {
                return;
            }
            $columns = $hasExternalId ? ['id', 'external_id'] : ['id'];
            $rows = $modelClass::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where(function ($q) use ($externalIds, $hasExternalId) {
                    $q->whereIn('id', $externalIds);
                    if ($hasExternalId) {
                        $q->orWhereIn('external_id', $externalIds);
                    }
                })
                ->get($columns);
            foreach ($rows as $row) {
                $ext = ($hasExternalId && ! empty($row->external_id)) ? (string) $row->external_id : (string) $row->id;
                $idMap[$ext] = (string) $row->id;
            }
        };
        $mapFor(Product::class, $syncedProducts);
        $mapFor(Customer::class, $syncedCustomers);
        $mapFor(Category::class, $syncedCategories);
        $mapFor(Brand::class, $syncedBrands);
        $mapFor(Supplier::class, $syncedSuppliers);
        $mapFor(Unit::class, $syncedUnits);
        $mapFor(Sale::class, $syncedQuotations);

        return response()->json([
            'success' => true,
            'message' => 'Batch sync processed successfully.',
            'server_time' => now()->toIso8601String(),
            'synced' => [
                'sales' => $syncedSales,
                'adjustments' => $syncedAdjustments,
                'payments' => $syncedPayments,
                'products' => $syncedProducts,
                'customers' => $syncedCustomers,
                'quotations' => $syncedQuotations,
                'categories' => $syncedCategories,
                'brands' => $syncedBrands,
                'suppliers' => $syncedSuppliers,
                'units' => $syncedUnits,
            ],
            // entity(plural) => [external_id, ...] the client can drop locally.
            'deleted' => $deleted,
            // Offline edits/deletes the server dropped because its row was
            // already newer — [{entity, id, server_id, reason, label,
            // server_updated_at}]. The client logs these + re-pulls the rows.
            'conflicts' => $conflicts,
            // external_id => server primary key, for every row touched above.
            'id_map' => $idMap,
            // idempotency_key => outcome, for the generic offline mutation
            // queue (SDUI form_submit / api_post). Additive: clients that
            // never queue generic mutations get empty arrays.
            'mutations_applied' => $mutationsApplied,
            'mutations_failed' => $mutationsFailed,
        ]);
    }

    /**
     * Replay the optional `mutations: []` array on a sync-batch request.
     *
     * Each entry is `{idempotency_key|id, endpoint, method, payload}` — a
     * verbatim record of an SDUI `form_submit` / `api_post` the client could
     * not send while offline. We dispatch it as a fresh internal HTTP request
     * against the app's own router, carrying the caller's bearer token so the
     * target route's auth + `tenant.api.permission` middleware authorise it
     * identically to a live call. Runs outside the batch transaction; one
     * failure never rolls back the rest.
     *
     * @return array{0: list<string>, 1: list<array<string,string>>} [applied keys, failures]
     */
    protected function replayQueuedMutations(Request $request, Company $company): array
    {
        $raw = $request->input('mutations');
        if (! is_array($raw) || $raw === []) {
            return [[], []];
        }

        $applied = [];
        $failed = [];
        // Bound the work per batch — a client with a huge backlog still makes
        // progress across several sync cycles rather than timing out here.
        $raw = array_slice($raw, 0, 100);

        $outerRequest = app()->bound('request') ? app('request') : null;

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = (string) ($entry['idempotency_key'] ?? $entry['id'] ?? '');
            $endpoint = trim((string) ($entry['endpoint'] ?? ''));
            $method = strtoupper((string) ($entry['method'] ?? 'POST'));
            $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];

            if ($key === '' || $endpoint === '') {
                $failed[] = ['idempotency_key' => $key, 'reason' => 'Missing endpoint or idempotency_key.'];

                continue;
            }

            // Never let a queued item re-enter the sync pipeline itself.
            if (preg_match('#sync-(batch|pull|push|catalog|sales)#', $endpoint)) {
                $failed[] = ['idempotency_key' => $key, 'reason' => 'Endpoint is not replayable.', 'endpoint' => $endpoint];

                continue;
            }

            // Idempotency ledger: insertOrIgnore returns 1 only the first time
            // we see this key for this company.
            $isNew = DB::table('desktop_sync_receipts')->insertOrIgnore([
                'company_id' => $company->id,
                'operation_type' => 'mutation',
                'external_id' => $key,
                'created_at' => now(),
            ]) === 1;

            if (! $isNew) {
                // Already handled on an earlier attempt — report success so the
                // client drops it from the queue.
                $applied[] = $key;

                continue;
            }

            try {
                [$status, $body] = $this->dispatchInternalRequest($request, $endpoint, $method, $payload);

                if ($status >= 200 && $status < 300) {
                    $applied[] = $key;
                } else {
                    // Let a future retry (or a fixed client) try again.
                    $this->forgetMutationReceipt($company->id, $key);
                    $failed[] = [
                        'idempotency_key' => $key,
                        'reason' => 'Replay returned HTTP '.$status.'.',
                        'endpoint' => $endpoint,
                        'response' => mb_substr($body, 0, 500),
                    ];
                }
            } catch (\Throwable $e) {
                $this->forgetMutationReceipt($company->id, $key);
                $failed[] = ['idempotency_key' => $key, 'reason' => $e->getMessage(), 'endpoint' => $endpoint];
                Log::warning('POS sync: queued mutation replay failed', [
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Router dispatch rebinds the container's `request`; restore ours so
        // anything downstream in this action still sees the real one.
        if ($outerRequest !== null) {
            app()->instance('request', $outerRequest);
        }

        return [$applied, $failed];
    }

    private function forgetMutationReceipt(int|string $companyId, string $key): void
    {
        DB::table('desktop_sync_receipts')
            ->where('company_id', $companyId)
            ->where('operation_type', 'mutation')
            ->where('external_id', $key)
            ->delete();
    }

    /**
     * Build a fresh internal request for [$endpoint] (an absolute URL or a
     * bare path), copy the caller's credential headers onto it, and run it
     * through the HTTP kernel.
     *
     * @return array{0: int, 1: string} [status code, response body]
     */
    private function dispatchInternalRequest(Request $original, string $endpoint, string $method, array $payload): array
    {
        $path = $endpoint;
        if (preg_match('#^https?://#i', $endpoint)) {
            $parts = parse_url($endpoint);
            $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $isRead = in_array($method, ['GET', 'HEAD'], true);
        $sub = Request::create(
            $path,
            $method,
            $isRead ? [] : $payload,
            [],
            [],
            [],
            $isRead ? null : json_encode($payload)
        );

        // Carry only the headers the tenant guard / permission middleware read.
        foreach (['Authorization', 'X-API-Key', 'X-Auth-Token', 'X-Tenant', 'X-Company-Id'] as $header) {
            if ($original->headers->has($header)) {
                $sub->headers->set($header, $original->headers->get($header));
            }
        }
        $sub->headers->set('Accept', 'application/json');
        if (! $isRead) {
            $sub->headers->set('Content-Type', 'application/json');
        }

        /** @var Kernel $kernel */
        $kernel = app(Kernel::class);
        $response = $kernel->handle($sub);

        return [$response->getStatusCode(), (string) $response->getContent()];
    }

    /**
     * 8. Inventory Management: List Products
     * GET /api/v1/pos/inventory
     */
    public function inventoryIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $defaultTaxRule = TaxRule::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->where('is_default', true)
            ->first();
        $defaultTaxRate = $defaultTaxRule ? (float) $defaultTaxRule->rate : 0.0;

        $productsQuery = Product::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['category', 'brand']);

        if ($request->boolean('parts_only') || $request->query('type') === 'parts' || $request->query('department') === 'parts') {
            $productsQuery->spareParts();
        } elseif ($request->boolean('exclude_services') || $request->query('type') === 'physical') {
            $productsQuery->where(function ($q) {
                $q->where('unit', '!=', 'service')
                    ->orWhereNull('unit');
            })->where(function ($q) {
                $q->whereNull('duration_minutes')
                    ->orWhere('duration_minutes', '<=', 0);
            });
        } elseif ($catType = $request->query('category_type')) {
            $productsQuery->whereHas('category', fn ($q) => $q->where('type', $catType));
        }

        if ($catId = $request->query('category_id')) {
            $productsQuery->where('category_id', $catId);
        }

        if ($dept = $request->query('department')) {
            if ($dept !== 'parts') {
                $productsQuery->where(function ($q) use ($dept) {
                    $q->where('category_name', $dept)
                        ->orWhereHas('category', fn ($cq) => $cq->where('name', $dept)->orWhere('type', $dept));
                });
            }
        }

        $products = $productsQuery->orderBy('name')
            ->get()
            ->map(function (Product $p) use ($defaultTaxRate) {
                return [
                    'id' => (string) ($p->external_id ?: $p->id),
                    'server_id' => $p->id,
                    'name' => $p->name,
                    'barcode' => $p->barcode ?: $p->code ?: '',
                    'sku' => $p->sku ?: '',
                    'sale_price' => (float) ($p->sale_price ?? 0),
                    'cost_price' => (float) ($p->cost_price ?? 0),
                    'current_stock' => (float) ($p->current_stock ?? 0),
                    'minimum_stock' => (float) ($p->minimum_stock ?? 0),
                    'is_low_stock' => (float) ($p->current_stock ?? 0) <= (float) ($p->minimum_stock ?? 0),
                    'unit' => $p->unit ?? 'pcs',
                    'category_id' => $p->category_id ? (string) $p->category_id : null,
                    'category_name' => $p->category_name ?? $p->category?->name ?? 'General',
                    'category_type' => $p->category?->type ?? 'retail',
                    'duration_minutes' => $p->duration_minutes ? (int) $p->duration_minutes : null,
                    'is_service' => $p->isService(),
                    'brand_name' => $p->brand_name ?? $p->brand?->name ?? '',
                    'image_url' => $p->getImageUrlOrDefault(),
                    'tax_rate' => $p->tax_rate !== null && (float) $p->tax_rate > 0 ? (float) $p->tax_rate : $defaultTaxRate,
                    'active' => (bool) $p->active,
                    'variants' => $p->variants ?? [],
                    'modifiers' => $p->modifiers ?? [],
                    'spice_levels' => $p->spice_levels ?? [],
                    'updated_at' => $p->updated_at?->toIso8601String(),
                ];
            });

        $categoriesQuery = Category::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->orderBy('name');

        if ($request->boolean('parts_only') || $request->query('type') === 'parts' || $request->query('department') === 'parts') {
            $categoriesQuery->whereNotIn('type', ['salon', 'service'])
                ->whereNotIn('name', ['Hair & Styling', 'Facials & Skincare', 'Spa & Body Treatments']);
        } elseif ($catType = $request->query('category_type')) {
            $categoriesQuery->where('type', $catType);
        }

        $categories = $categoriesQuery->get(['id', 'name', 'color', 'type']);

        $brands = Brand::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $paymentMethods = PaymentMethod::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('order_index')
            ->get()
            ->map(fn (PaymentMethod $pm) => [
                'id' => (string) $pm->id,
                'name' => $pm->name,
                'code' => $pm->code ?: Str::slug($pm->name, '_'),
                'description' => $pm->description ?? '',
                'is_active' => (bool) $pm->is_active,
                'order_index' => (int) $pm->order_index,
            ])
            ->values()
            ->all();

        if (empty($paymentMethods)) {
            $paymentMethods = [
                ['id' => 'cash', 'name' => 'Cash', 'code' => 'cash', 'description' => '', 'is_active' => true, 'order_index' => 0],
                ['id' => 'card', 'name' => 'Card', 'code' => 'card', 'description' => '', 'is_active' => true, 'order_index' => 1],
                ['id' => 'upi', 'name' => 'UPI', 'code' => 'upi', 'description' => '', 'is_active' => true, 'order_index' => 2],
                ['id' => 'credit', 'name' => 'Credit', 'code' => 'credit', 'description' => '', 'is_active' => true, 'order_index' => 3],
                ['id' => 'other', 'name' => 'Other', 'code' => 'other', 'description' => '', 'is_active' => true, 'order_index' => 4],
            ];
        }

        return response()->json([
            'success' => true,
            'total_products' => $products->count(),
            'low_stock_count' => $products->where('is_low_stock', true)->count(),
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'payment_methods' => $paymentMethods,
        ]);
    }

    /**
     * 9. Inventory Management: Create/Update Product
     * POST /api/v1/pos/inventory/product
     */
    public function inventoryStoreProduct(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:200'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'current_stock' => ['nullable', 'numeric'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'sku' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50'],
            'category_name' => ['nullable', 'string', 'max:100'],
            'brand_name' => ['nullable', 'string', 'max:100'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
            'external_id' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error saving product.',
                'details' => $validator->errors(),
            ], 422);
        }

        $extId = $request->input('external_id') ?: Str::uuid()->toString();

        $product = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($request, $extId) {
                if ($request->filled('id')) {
                    $q->where('id', $request->input('id'));
                } elseif ($extId) {
                    $q->where('external_id', $extId);
                }
            })
            ->first();

        $data = [
            'company_id' => $company->id,
            'external_id' => $extId,
            'name' => $request->input('name'),
            'sale_price' => (float) $request->input('sale_price', 0),
            'cost_price' => (float) $request->input('cost_price', 0),
            'current_stock' => (float) $request->input('current_stock', 0),
            'minimum_stock' => (float) $request->input('minimum_stock', 0),
            'barcode' => $request->input('barcode') ?: null,
            'sku' => $request->input('sku') ?: null,
            'unit' => $request->input('unit', 'pcs'),
            'category_name' => $request->input('category_name', 'General'),
            'brand_name' => $request->input('brand_name') ?: null,
            'tax_rate' => (float) $request->input('tax_rate', 0),
            'active' => true,
        ];

        if ($request->filled('image_url')) {
            $data['image_url'] = $request->input('image_url');
        }

        if ($product) {
            $product->update($data);
        } else {
            $product = Product::create($data);
        }

        AuditLog::record('inventory.product_saved', $company->id, $user?->id, [
            'product_id' => $product->id,
            'name' => $product->name,
            'stock' => $product->current_stock,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product saved successfully.',
            'product' => [
                'id' => (string) ($product->external_id ?: $product->id),
                'server_id' => $product->id,
                'name' => $product->name,
                'barcode' => $product->barcode,
                'sku' => $product->sku,
                'sale_price' => (float) $product->sale_price,
                'cost_price' => (float) $product->cost_price,
                'current_stock' => (float) $product->current_stock,
                'minimum_stock' => (float) $product->minimum_stock,
                'unit' => $product->unit,
                'category_name' => $product->category_name,
                'tax_rate' => (float) $product->tax_rate,
                'image_url' => $product->image_url,
            ],
        ]);
    }

    /**
     * 9c. Inventory Management: Delete / Soft-Delete / Archive Product
     * DELETE /api/v1/pos/inventory/product/{id}
     * DELETE /api/tenant/products/{id}
     */
    public function inventoryDestroyProduct(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $product = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('external_id', $id);
            })
            ->first();

        if (! $product) {
            return response()->json([
                'success' => false,
                'error' => 'Product not found.',
            ], 404);
        }

        $product->update(['active' => false]);
        $product->delete();

        AuditLog::record('inventory.product_deleted', $company->id, $user?->id, [
            'product_id' => $product->id,
            'name' => $product->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
            'product_id' => (string) ($product->external_id ?: $product->id),
        ]);
    }

    /**
     * 9b. Inventory Management: Upload Product Image
     * POST /api/v1/pos/inventory/product/{id}/image
     */
    public function inventoryUploadProductImage(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $product = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('external_id', $id);
            })
            ->first();

        if (! $product) {
            return response()->json(['success' => false, 'error' => 'Product not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'image' => ['required', 'image', 'max:5120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error uploading image.',
                'details' => $validator->errors(),
            ], 422);
        }

        $path = $request->file('image')->store('products', 'public');
        $product->update(['image_url' => '/storage/'.$path]);

        return response()->json([
            'success' => true,
            'message' => 'Image uploaded successfully.',
            'image_url' => $product->image_url,
        ]);
    }

    /**
     * 9c. Inventory Management: Bulk Import Products (CSV/TXT)
     * POST /api/v1/pos/inventory/import
     */
    public function inventoryBulkImport(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:10240', 'extensions:csv,txt'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error importing file.',
                'details' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $contents = (string) file_get_contents($file->getRealPath());

        $lines = preg_split('/\R/', $contents, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $rows = array_map(
            fn ($line) => $extension === 'csv' ? str_getcsv($line) : array_map('trim', explode('|', $line)),
            $lines
        );
        if ($rows && strtolower(trim((string) ($rows[0][0] ?? ''))) === 'name') {
            array_shift($rows);
        }

        $imported = 0;

        DB::transaction(function () use ($rows, $company, &$imported) {
            foreach ($rows as $row) {
                if (count($row) < 2 || blank($row[0] ?? null)) {
                    continue;
                }
                $categoryName = trim($row[1] ?: 'General');
                $category = Category::withoutGlobalScope('company')
                    ->firstOrCreate(['company_id' => $company->id, 'name' => $categoryName], ['active' => true]);

                Product::create([
                    'company_id' => $company->id,
                    'name' => trim($row[0]),
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'code' => filled($row[2] ?? null) ? trim($row[2]) : 'SKU-'.random_int(100000, 999999),
                    'barcode' => filled($row[3] ?? null) ? trim($row[3]) : null,
                    'cost_price' => (float) ($row[4] ?? 0),
                    'sale_price' => (float) ($row[5] ?? 0),
                    'current_stock' => (float) ($row[6] ?? 0),
                    'minimum_stock' => 0,
                    'active' => true,
                    'taxable' => true,
                ]);
                $imported++;
            }
        });

        AuditLog::record('inventory.bulk_imported', $company->id, $user?->id, ['imported' => $imported]);

        return response()->json([
            'success' => true,
            'message' => "{$imported} product(s) imported.",
            'imported' => $imported,
        ]);
    }

    /**
     * 10. Inventory Management: Stock Adjustment
     * POST /api/v1/pos/inventory/adjust
     */
    public function inventoryAdjustStock(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'product_id' => ['required'],
            'type' => ['required', 'in:add,subtract,set'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error during stock adjustment.',
                'details' => $validator->errors(),
            ], 422);
        }

        $prodId = $request->input('product_id');
        $product = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($prodId) {
                $q->where('id', $prodId)->orWhere('external_id', (string) $prodId);
            })
            ->firstOrFail();

        $qty = (float) $request->input('quantity');
        $type = $request->input('type');
        $oldStock = (float) $product->current_stock;

        if ($type === 'add') {
            $product->increment('current_stock', $qty);
        } elseif ($type === 'subtract') {
            $product->decrement('current_stock', $qty);
        } elseif ($type === 'set') {
            $product->update(['current_stock' => $qty]);
        }

        $product->refresh();
        $newStock = (float) $product->current_stock;

        AuditLog::record('inventory.stock_adjusted', $company->id, $user?->id, [
            'product_id' => $product->id,
            'name' => $product->name,
            'type' => $type,
            'adjusted_qty' => $qty,
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'reason' => $request->input('reason', 'Manual adjustment via POS Terminal'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Stock adjusted successfully.',
            'product_id' => (string) ($product->external_id ?: $product->id),
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
        ]);
    }

    /**
     * 11. Customer Ledger: List Customers & Balances
     * GET /api/v1/pos/customers
     */
    public function customersIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $customers = Customer::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->withCount(['sales as pending_sales_count' => fn ($q) => $q->where('due_amount', '>', 0)])
            ->withSum(['sales as total_due_balance' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'due_amount')
            ->orderBy('name')
            ->get()
            ->map(function (Customer $c) {
                return [
                    'id' => (string) ($c->external_id ?: $c->id),
                    'server_id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone ?? '',
                    'email' => $c->email ?? '',
                    'document' => $c->document ?? $c->tax_id ?? '',
                    'balance_due' => (float) ($c->total_due_balance ?? 0),
                    'pending_invoices' => (int) ($c->pending_sales_count ?? 0),
                    'loyalty_points' => (int) ($c->loyalty_points ?? 0),
                    'address' => $c->address ?? '',
                    'city' => $c->city ?? '',
                    'state' => $c->state ?? '',
                    'age' => $c->age,
                    'gender' => $c->gender,
                    'allergies' => $c->allergies,
                    'prescribing_doctor' => $c->prescribing_doctor,
                    'doctor_registration_no' => $c->doctor_registration_no,
                    'custom_fields' => $c->custom_fields ?? (object) [],
                ];
            });

        $totalReceivables = (float) Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', '!=', 'cancelled')
            ->sum('due_amount');

        return response()->json([
            'success' => true,
            'total_customers' => $customers->count(),
            'total_receivables' => $totalReceivables,
            'customers' => $customers,
        ]);
    }

    /**
     * Search Customers by Name, Phone, Email, Document/GSTIN.
     * GET /api/tenant/customers/search
     * GET /api/v1/pos/customers/search
     */
    public function customersSearch(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $query = trim((string) ($request->input('q') ?: $request->input('query') ?: $request->input('search') ?: ''));

        $customers = Customer::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('name', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('document', 'like', "%{$query}%")
                        ->orWhere('tax_id', 'like', "%{$query}%")
                        ->orWhere('gstin', 'like', "%{$query}%")
                        ->orWhere('custom_fields->company_name', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(function (Customer $c) {
                $companyName = $c->company_name ?? ($c->custom_fields['company_name'] ?? '');
                $label = $c->name;
                if ($c->phone) {
                    $label .= " ({$c->phone})";
                }
                if ($companyName && $companyName !== $c->name) {
                    $label .= " - {$companyName}";
                }

                return [
                    'id' => (string) ($c->external_id ?: $c->id),
                    'server_id' => $c->id,
                    'value' => $c->id,
                    'label' => $label,
                    'name' => $c->name,
                    'title' => $c->name,
                    'subtitle' => $c->phone ?: ($c->email ?: ''),
                    'phone' => $c->phone ?? '',
                    'email' => $c->email ?? '',
                    'company_name' => $companyName,
                    'due_amount' => $c->due_balance > 0 ? 'Due: '.number_format((float) $c->due_balance, 2) : null,
                    'avatar_icon' => 'person',
                    'badge_due_bg' => 'rgba(239, 68, 68, 0.15)',
                    'badge_due_tx' => '#F87171',
                    'document' => $c->document ?? $c->tax_id ?? '',
                    'balance_due' => (float) ($c->due_balance ?? 0),
                    'age' => $c->age,
                    'gender' => $c->gender,
                    'allergies' => $c->allergies,
                    'prescribing_doctor' => $c->prescribing_doctor,
                    'doctor_registration_no' => $c->doctor_registration_no,
                    'custom_fields' => $c->custom_fields ?? (object) [],
                ];
            });

        return response()->json([
            'success' => true,
            'count' => $customers->count(),
            'theme' => [
                'container_bg' => '#1E293B',    // High-contrast slate surface
                'dropdown_surface' => '#1E293B',
                'popup_background' => '#1E293B',
                'surface' => '#1E293B',
                'card' => '#1E293B',
                'border_color' => '#334155',    // Slate divider
                'title_color' => '#F8FAFC',    // High-contrast white
                'sub_color' => '#94A3B8',    // Slate-400
                'text_color' => '#F8FAFC',
                'badge_due_bg' => 'rgba(239, 68, 68, 0.15)',
                'badge_due_tx' => '#F87171',
            ],
            'data' => $customers,
            'customers' => $customers,
        ]);
    }

    /**
     * 12. Customer Ledger: Create/Update Customer
     * POST /api/v1/pos/customers
     */
    public function customersStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'document' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'external_id' => ['nullable', 'string'],
            'age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'gender' => ['nullable', 'string', 'max:30'],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'prescribing_doctor' => ['nullable', 'string', 'max:150'],
            'doctor_registration_no' => ['nullable', 'string', 'max:100'],
            'custom_fields' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error saving customer.',
                'details' => $validator->errors(),
            ], 422);
        }

        $extId = $request->input('external_id') ?: Str::uuid()->toString();

        $customer = Customer::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($request, $extId) {
                if ($request->filled('id')) {
                    $q->where('id', $request->input('id'));
                } elseif ($extId) {
                    $q->where('external_id', $extId);
                }
            })
            ->first();

        $data = [
            'company_id' => $company->id,
            'external_id' => $extId,
            'name' => $request->input('name'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'document' => $request->input('document'),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'age' => $request->input('age'),
            'gender' => $request->input('gender'),
            'allergies' => $request->input('allergies'),
            'prescribing_doctor' => $request->input('prescribing_doctor'),
            'doctor_registration_no' => $request->input('doctor_registration_no'),
            'custom_fields' => is_array($request->input('custom_fields')) ? $request->input('custom_fields') : (is_string($request->input('custom_fields')) ? json_decode($request->input('custom_fields'), true) : null),
        ];

        if ($customer) {
            $customer->update($data);
        } else {
            $customer = Customer::create($data);
        }

        AuditLog::record('customer.saved', $company->id, $user?->id, [
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
        ]);

        $payload = [
            'id' => (string) ($customer->external_id ?: $customer->id),
            'server_id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'document' => $customer->document,
            'balance_due' => $customer->total_due,
            'loyalty_points' => (int) $customer->loyalty_points,
            'age' => $customer->age,
            'gender' => $customer->gender,
            'allergies' => $customer->allergies,
            'prescribing_doctor' => $customer->prescribing_doctor,
            'doctor_registration_no' => $customer->doctor_registration_no,
            'custom_fields' => $customer->custom_fields ?? (object) [],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Customer saved successfully.',
            'data' => $payload,
            'customer' => $payload,
        ]);
    }

    /**
     * 13. Customer Ledger: Get Transaction History
     * GET /api/v1/pos/customers/{id}/ledger
     */
    public function customerLedger(Request $request, $id): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $customer = Customer::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('external_id', (string) $id);
            })
            ->firstOrFail();

        $sales = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->with('payments')
            ->orderByDesc('created_at')
            ->get();

        $ledgerEntries = [];

        foreach ($sales as $sale) {
            $ledgerEntries[] = [
                'type' => 'invoice',
                'id' => (string) ($sale->external_id ?: $sale->id),
                'sale_number' => $sale->sale_number,
                'date' => $sale->created_at?->toIso8601String(),
                'total' => (float) $sale->total,
                'paid_amount' => (float) $sale->paid_amount,
                'due_amount' => (float) $sale->due_amount,
                'payment_method' => $sale->payment_method,
                'status' => $sale->payment_status,
                'items_count' => count((array) ($sale->items ?: [])),
                'background_color' => '#182230',
                'border_color' => '#334155',
                'text_color' => '#F8FAFC',
                'secondary_text_color' => '#CBD5E1',
                'style' => [
                    'backgroundColor' => '#182230',
                    'borderColor' => '#334155',
                    'borderWidth' => 1,
                    'borderRadius' => 10,
                ],
            ];

            foreach ($sale->payments as $pay) {
                $ledgerEntries[] = [
                    'type' => 'payment',
                    'id' => (string) $pay->id,
                    'sale_number' => $sale->sale_number,
                    'date' => $pay->created_at?->toIso8601String(),
                    'amount' => (float) $pay->amount,
                    'payment_method' => $pay->payment_method,
                    'reference' => $pay->reference_number,
                    'notes' => $pay->notes,
                    'background_color' => '#132A24',
                    'border_color' => '#10B981',
                    'border_opacity' => 0.3,
                    'text_color' => '#F8FAFC',
                    'secondary_text_color' => '#CBD5E1',
                    'amount_text_color' => '#6EE7B7',
                    'style' => [
                        'backgroundColor' => '#132A24',
                        'borderColor' => '#10B981',
                        'borderOpacity' => 0.3,
                        'borderWidth' => 1,
                        'borderRadius' => 10,
                    ],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => (string) ($customer->external_id ?: $customer->id),
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'document' => $customer->document,
                'balance_due' => $customer->total_due,
                'loyalty_points' => (int) $customer->loyalty_points,
            ],
            'ledger' => $ledgerEntries,
        ]);
    }

    /**
     * 14. Customer Ledger: Record Receivable Payment
     * POST /api/v1/pos/customers/{id}/payment
     */
    public function customerRecordPayment(Request $request, $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'sale_id' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error recording customer payment.',
                'details' => $validator->errors(),
            ], 422);
        }

        $customer = Customer::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('external_id', (string) $id);
            })
            ->firstOrFail();

        $amount = (float) $request->input('amount');
        $method = $request->input('payment_method', 'cash');
        $ref = $request->input('reference');
        $notes = $request->input('notes', 'Khata / Receivable collection via POS Terminal');

        $appliedTo = [];

        DB::transaction(function () use ($company, $customer, $amount, $method, $ref, $notes, $request, &$appliedTo) {
            $salesQuery = Sale::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('customer_id', $customer->id)
                ->where('due_amount', '>', 0)
                ->where('status', '!=', 'cancelled');

            if ($request->filled('sale_id')) {
                $sId = $request->input('sale_id');
                $salesQuery->where(fn ($q) => $q->where('id', $sId)->orWhere('external_id', (string) $sId));
            }

            $dueSales = $salesQuery->orderBy('created_at')->lockForUpdate()->get();
            $remaining = $amount;

            foreach ($dueSales as $sale) {
                if ($remaining <= 0) {
                    break;
                }

                $apply = min($remaining, (float) $sale->due_amount);
                $newPaid = round((float) $sale->paid_amount + $apply, 2);
                $newDue = max(0, round((float) $sale->total - $newPaid, 2));

                $sale->update([
                    'paid_amount' => $newPaid,
                    'due_amount' => $newDue,
                    'payment_status' => $newDue <= 0.001 ? 'paid' : 'partially_paid',
                ]);

                $orderPayment = OrderPayment::create([
                    'company_id' => $company->id,
                    'sale_id' => $sale->id,
                    'payment_method' => $method,
                    'amount' => $apply,
                    'tendered' => $apply,
                    'change_returned' => 0,
                    'reference_number' => $ref,
                    'notes' => $notes,
                ]);

                app(CustomerLedgerService::class)->recordPayment($sale, $orderPayment);

                $appliedTo[] = [
                    'sale_id' => (string) ($sale->external_id ?: $sale->id),
                    'sale_number' => $sale->sale_number,
                    'amount_applied' => $apply,
                    'remaining_due' => $newDue,
                ];

                $remaining -= $apply;
            }
        });

        AuditLog::record('financials.customer_payment_received', $company->id, $user?->id, [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'amount' => $amount,
            'payment_method' => $method,
            'applied_invoices' => count($appliedTo),
        ]);

        return response()->json([
            'success' => true,
            'message' => sprintf('Payment of %s%.2f successfully recorded.', $company->currency_symbol ?? '$', $amount),
            'amount' => $amount,
            'new_balance_due' => $customer->fresh()->total_due,
            'applied_invoices' => $appliedTo,
        ]);
    }

    /**
     * 15. Analytics: Executive Dashboard KPIs & Sales Trends
     * GET /api/v1/pos/analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $salesBase = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', '!=', 'cancelled');

        // Interactive dashboard date-range filter (the "Filter" control). The
        // range scopes the headline metric cards + the revenue trend; the
        // rolling monthly activity / top products stay whole-history for
        // context.
        [$rangeStart, $rangeEnd, $rangeKey, $rangeLabel] = $this->resolveAnalyticsRange($request);
        $rangeSpanDays = max(1, $rangeStart->diffInDays($rangeEnd) + 1);
        $prevRangeStart = (clone $rangeStart)->subDays($rangeSpanDays);
        $prevRangeEnd = (clone $rangeStart)->subSecond();

        $rangeSales = (clone $salesBase)->whereBetween('created_at', [$rangeStart, $rangeEnd]);
        $rangeRevenue = (float) (clone $rangeSales)->sum('total');
        $rangeOrders = (clone $rangeSales)->count();
        $prevRangeSales = (clone $salesBase)->whereBetween('created_at', [$prevRangeStart, $prevRangeEnd]);
        $prevRangeRevenue = (float) (clone $prevRangeSales)->sum('total');
        $prevRangeOrders = (clone $prevRangeSales)->count();

        $todaySales = (clone $salesBase)->whereDate('created_at', now()->toDateString());
        $todayRevenue = (float) (clone $todaySales)->sum('total');
        $todayOrders = (clone $todaySales)->count();

        $monthSales = (clone $salesBase)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        $monthRevenue = (float) (clone $monthSales)->sum('total');
        $monthOrders = (clone $monthSales)->count();

        $allTimeRevenue = (float) (clone $salesBase)->sum('total');
        $allTimeOrders = (clone $salesBase)->count();
        $avgTicket = $allTimeOrders > 0 ? round($allTimeRevenue / $allTimeOrders, 2) : 0;

        // Payment Method Breakdown
        $paymentMethods = (clone $salesBase)
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($row) => [
                'method' => ucfirst($row->payment_method ?: 'Cash'),
                'count' => (int) $row->count,
                'total' => (float) $row->total,
            ]);

        // Revenue trend across the selected range. A single-day view ("Today" /
        // "Yesterday" / a one-day custom range) grouped by DATE() collapses to
        // one point, which fl_chart treats as empty ("No revenue yet") — so a
        // single day is bucketed into 24 zero-filled hourly intervals in the
        // tenant's timezone instead. Multi-day ranges keep daily buckets
        // (capped at 31 points; longer ranges roll up to weekly), with every
        // calendar day in between zero-filled so the line never breaks.
        if ($rangeStart->isSameDay($rangeEnd)) {
            $tz = $company->resolveTimezone();
            $localDay = ($rangeKey === 'yesterday')
                ? Carbon::now($tz)->subDay()->startOfDay()
                : (($rangeKey === 'today')
                    ? Carbon::now($tz)->startOfDay()
                    : Carbon::parse($rangeStart)->setTimezone($tz)->startOfDay());

            $hourTotals = array_fill(0, 24, 0.0);
            (clone $rangeSales)
                ->select('created_at', 'total')
                ->get()
                ->each(function ($row) use (&$hourTotals, $tz) {
                    $hour = (int) Carbon::parse($row->created_at)->setTimezone($tz)->format('G');
                    $hourTotals[$hour] += (float) $row->total;
                });

            $sevenDaysTrend = [];
            for ($h = 0; $h < 24; $h++) {
                $label = sprintf('%02d:00', $h);
                $amount = round($hourTotals[$h], 2);
                $slot = $localDay->copy()->addHours($h);
                $sevenDaysTrend[] = [
                    'date' => $slot->toDateString(),
                    'day' => $label,
                    'label' => $label,
                    'revenue' => $amount,
                    'amount' => $amount,
                    'timestamp' => $slot->timestamp,
                ];
            }
        } else {
            $trendBucketDays = $rangeSpanDays > 31 ? (int) ceil($rangeSpanDays / 31) : 1;
            $trendRows = (clone $rangeSales)
                ->select(DB::raw('DATE(created_at) as d'), DB::raw('SUM(total) as t'))
                ->groupBy('d')
                ->pluck('t', 'd');
            $sevenDaysTrend = [];
            $cursor = (clone $rangeStart);
            while ($cursor->lte($rangeEnd)) {
                $bucketEnd = (clone $cursor)->addDays($trendBucketDays - 1);
                $sum = 0.0;
                $probe = (clone $cursor);
                while ($probe->lte($bucketEnd) && $probe->lte($rangeEnd)) {
                    $sum += (float) ($trendRows[$probe->format('Y-m-d')] ?? 0);
                    $probe->addDay();
                }
                $label = $cursor->format($trendBucketDays > 1 ? 'M d' : 'D');
                $sum = round($sum, 2);
                $sevenDaysTrend[] = [
                    'date' => $cursor->format('Y-m-d'),
                    'day' => $label,
                    'label' => $label,
                    'revenue' => $sum,
                    'amount' => $sum,
                ];
                $cursor->addDays($trendBucketDays);
            }
        }

        // Top 5 Products by Sales
        $allRecentSales = (clone $salesBase)->latest('created_at')->limit(100)->get();
        $productStats = [];
        foreach ($allRecentSales as $s) {
            $items = (array) ($s->items ?: []);
            foreach ($items as $item) {
                $pName = $item['name'] ?? 'Product';
                $pQty = (float) ($item['quantity'] ?? $item['qty'] ?? 1);
                $pTot = (float) ($item['total'] ?? ($pQty * ($item['price'] ?? 0)));

                if (! isset($productStats[$pName])) {
                    $productStats[$pName] = ['name' => $pName, 'units_sold' => 0, 'revenue' => 0];
                }
                $productStats[$pName]['units_sold'] += $pQty;
                $productStats[$pName]['revenue'] += $pTot;
            }
        }
        usort($productStats, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        $topProducts = array_slice($productStats, 0, 5);

        // Low stock count & Receivables
        $lowStockCount = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->whereRaw('current_stock <= minimum_stock')
            ->count();

        $totalReceivables = (float) (clone $salesBase)->sum('due_amount');

        // Previous calendar month — drives the ▲/▼ deltas on the redesigned
        // "Statistics" card.
        $prevMonthSales = (clone $salesBase)->whereBetween('created_at', [
            now()->subMonthNoOverflow()->startOfMonth(),
            now()->subMonthNoOverflow()->endOfMonth(),
        ]);
        $prevMonthRevenue = (float) (clone $prevMonthSales)->sum('total');
        $prevMonthOrders = (clone $prevMonthSales)->count();

        $productCount = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)->count();
        $customerCount = Customer::withoutGlobalScope('company')
            ->where('company_id', $company->id)->count();

        // Monthly purchase activity for the last 9 months: a "completed" sale
        // is fully paid, a "pending" one still carries a balance.
        $monthlyActivity = [];
        for ($i = 8; $i >= 0; $i--) {
            $start = now()->subMonthsNoOverflow($i)->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $rows = (clone $salesBase)->whereBetween('created_at', [$start, $end]);
            $completed = (clone $rows)->where('due_amount', '<=', 0.01)->count();
            $pending = (clone $rows)->where('due_amount', '>', 0.01)->count();
            $monthlyActivity[] = [
                'month' => $start->format('M'),
                'year' => (int) $start->format('Y'),
                'completed' => $completed,
                'pending' => $pending,
            ];
        }

        // "Popular tags" — the catalogue's most-used category names.
        $popularTags = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->whereNotNull('category_name')
            ->where('category_name', '!=', '')
            ->select('category_name', DB::raw('COUNT(*) as c'))
            ->groupBy('category_name')
            ->orderByDesc('c')
            ->limit(14)
            ->pluck('category_name')
            ->values();

        // Latest transactions table. The status chip is a fixed-width column
        // on the mobile dashboard, so the label must stay short: "Completed"
        // (9 chars) wrapped onto two lines, "Paid" does not. Date is the
        // compact "10 Sep" form for the same reason.
        //
        // Machine key vs display label are kept separate:
        //  - `status`        — the short display text the current client renders
        //  - `status_key`    — stable machine key ('completed' | 'pending')
        //  - `status_label`  — explicit display label
        //  - `status_color`  — semantic token ('success' | 'warning')
        //  - `badge`         — a fully resolved chip spec (text + hex colors)
        // so a paid row can be styled green ('success') even though its label
        // is no longer the literal string "completed".
        $recentTransactions = (clone $salesBase)
            ->latest('created_at')
            ->limit(8)
            ->get(['id', 'sale_number', 'customer_name', 'total', 'due_amount', 'status', 'created_at'])
            ->map(function ($s) {
                $isPaid = ((float) $s->due_amount) <= 0.01;
                $label = $isPaid ? 'Paid' : 'Pending';

                return [
                    'id' => (string) $s->id,
                    'reference' => $s->sale_number ?: ('TR-'.str_pad((string) $s->id, 6, '0', STR_PAD_LEFT)),
                    'customer' => $s->customer_name ?: 'Walk-in',
                    'date' => optional($s->created_at)->format('j M'),
                    'status' => $label,
                    'status_key' => $isPaid ? 'completed' : 'pending',
                    'status_label' => $label,
                    'status_color' => $isPaid ? 'success' : 'warning',
                    'badge' => [
                        'text' => $label,
                        'variant' => $isPaid ? 'success' : 'warning',
                        'color' => $isPaid ? '#10B981' : '#F59E0B',
                        'background_color' => $isPaid ? '#132A24' : '#2A1E17',
                        'border_color' => $isPaid ? '#10B981' : '#D97706',
                        'border_opacity' => $isPaid ? 0.3 : 1,
                        'text_color' => $isPaid ? '#D1FAE5' : '#FCD34D',
                        'white_space' => 'nowrap',
                    ],
                    'amount' => (float) $s->total,
                ];
            })
            ->values();

        // Recent customers — stands in for the design's "Recent Messages".
        $recentCustomers = Customer::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->latest('created_at')
            ->limit(6)
            ->get(['id', 'name', 'phone', 'email', 'created_at'])
            ->map(fn ($c) => [
                'id' => (string) $c->id,
                'name' => $c->name ?: 'Customer',
                'detail' => $c->phone ?: ($c->email ?: 'No contact on file'),
                'time' => optional($c->created_at)->format('d M, h:i A'),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'currency_symbol' => $company->currency_symbol ?? '$',
            'server_time' => now()->toIso8601String(),
            'range' => [
                'key' => $rangeKey,
                'label' => $rangeLabel,
                'from' => $rangeStart->toIso8601String(),
                'to' => $rangeEnd->toIso8601String(),
            ],
            'kpis' => [
                'today_revenue' => $todayRevenue,
                'today_orders' => $todayOrders,
                'range_revenue' => $rangeRevenue,
                'range_orders' => $rangeOrders,
                'prev_range_revenue' => $prevRangeRevenue,
                'prev_range_orders' => $prevRangeOrders,
                'month_revenue' => $monthRevenue,
                'month_orders' => $monthOrders,
                'prev_month_revenue' => $prevMonthRevenue,
                'prev_month_orders' => $prevMonthOrders,
                'all_time_revenue' => $allTimeRevenue,
                'all_time_orders' => $allTimeOrders,
                'average_order_value' => $avgTicket,
                'total_receivables' => $totalReceivables,
                'low_stock_count' => $lowStockCount,
                'product_count' => $productCount,
                'customer_count' => $customerCount,
            ],
            'payment_breakdown' => $paymentMethods,
            'revenue_trend' => $sevenDaysTrend,
            // Alias of revenue_trend under the schema key some clients expect;
            // each point carries both {revenue} and {amount}, {day} and {label}.
            'chart_data' => $sevenDaysTrend,
            'monthly_activity' => $monthlyActivity,
            'popular_tags' => $popularTags,
            'recent_transactions' => $recentTransactions,
            'recent_customers' => $recentCustomers,
            'top_products' => $topProducts,
        ]);
    }

    /**
     * Resolve the dashboard "Filter" date range from the request.
     * Accepts ?range=today|yesterday|last7|last30|month|last_month|year|custom
     * (+ ?from=Y-m-d&to=Y-m-d for custom). Defaults to the current month.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon, 2: string, 3: string}
     */
    private function resolveAnalyticsRange(Request $request): array
    {
        $key = strtolower(trim((string) $request->query('range', 'month')));

        return match ($key) {
            'today' => [now()->startOfDay(), now()->endOfDay(), 'today', 'Today'],
            'yesterday' => [
                now()->subDay()->startOfDay(),
                now()->subDay()->endOfDay(),
                'yesterday',
                'Yesterday',
            ],
            'last7', 'last_7_days', '7d' => [
                now()->subDays(6)->startOfDay(), now()->endOfDay(), 'last7', 'Last 7 Days',
            ],
            'last30', 'last_30_days', '30d' => [
                now()->subDays(29)->startOfDay(), now()->endOfDay(), 'last30', 'Last 30 Days',
            ],
            'last_month', 'prev_month' => [
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->subMonthNoOverflow()->endOfMonth(),
                'last_month',
                'Last Month',
            ],
            'year', 'this_year' => [
                now()->startOfYear(), now()->endOfYear(), 'year', 'This Year',
            ],
            'all', 'all_time' => [
                now()->subYears(5)->startOfDay(), now()->endOfDay(), 'all', 'All Time',
            ],
            'custom' => (function () use ($request) {
                $from = rescue(fn () => \Illuminate\Support\Carbon::parse((string) $request->query('from'))->startOfDay(), null);
                $to = rescue(fn () => \Illuminate\Support\Carbon::parse((string) $request->query('to'))->endOfDay(), null);
                if (! $from || ! $to || $from->gt($to)) {
                    return [now()->startOfMonth(), now()->endOfMonth(), 'month', 'This Month'];
                }

                return [$from, $to, 'custom', $from->format('d M').' – '.$to->format('d M')];
            })(),
            default => [now()->startOfMonth(), now()->endOfMonth(), 'month', 'This Month'],
        };
    }

    /**
     * 16. Subscription Management: Plan Status & Available Plans
     * GET /api/v1/pos/subscription
     */
    public function subscription(Request $request, SubscriptionPaymentGatewayService $gatewayService): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $subscription = Subscription::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->latest('started_at')
            ->first();

        $plan = Plan::find($company->plan_name) ?? Plan::first();
        $availablePlans = Plan::where('active', true)->get()->map(function (Plan $p) {
            $features = is_array($p->features) ? $p->features : (is_string($p->features) ? (json_decode($p->features, true) ?: []) : []);
            $limits = is_array($p->limits) ? $p->limits : (is_string($p->limits) ? (json_decode($p->limits, true) ?: []) : []);

            return [
                'name' => $p->name,
                'display_name' => $p->display_name ?: ucfirst($p->name),
                'price' => (float) ($p->price ?? 0),
                'currency' => $p->currency ?: 'USD',
                'billing_cycle' => $p->billing_cycle,
                'duration_days' => (int) ($p->duration_days ?? 30),
                'features' => array_values($features),
                'limits' => $limits,
            ];
        })->values()->all();

        $productsCount = Product::withoutGlobalScope('company')->where('company_id', $company->id)->count();
        $usersCount = User::withoutGlobalScope('company')->where('company_id', $company->id)->count();

        $daysRemaining = null;
        if ($company->expires_at) {
            $daysRemaining = max(0, (int) now()->diffInDays($company->expires_at, false));
        }

        return response()->json([
            'success' => true,
            'subscription' => [
                'plan_name' => $company->plan_name ?? 'trial',
                'display_name' => $plan?->display_name ?? ucfirst($company->plan_name ?? 'Trial'),
                'status' => $company->isExpired() ? 'expired' : 'active',
                'started_at' => $subscription?->started_at?->toIso8601String(),
                'expires_at' => $company->expires_at?->toIso8601String(),
                'days_remaining' => $daysRemaining,
                'is_lifetime' => $company->expires_at === null,
            ],
            'usage' => [
                'products_count' => $productsCount,
                'products_limit' => $company->max_products ?? $plan?->limits['products'] ?? 'Unlimited',
                'users_count' => $usersCount,
                'users_limit' => $company->max_users ?? $plan?->limits['usuarios'] ?? 'Unlimited',
            ],
            'available_plans' => $availablePlans,
            'enabled_gateways' => array_keys($gatewayService->getEnabledGateways()),
        ]);
    }

    /**
     * 17. Subscription Management: Redeem Activation Code
     * POST /api/v1/pos/subscription/redeem
     */
    public function subscriptionRedeem(Request $request, TenantProvisioningService $provisioner): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Please enter a valid activation code.',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $res = $provisioner->redeemActivationCode($company, $request->input('code'), $user);

            return response()->json([
                'success' => true,
                'message' => 'Activation code redeemed successfully! Plan updated.',
                'plan_name' => $res['plan_name'],
                'expires_at' => $res['expires_at']?->toIso8601String(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Activation Code Redemption Failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'error' => 'Redemption failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 17b. Subscription Management: Activate a Free ($0) Plan
     * POST /api/v1/pos/subscription/plans/{plan}/activate-free
     */
    public function subscriptionActivateFree(Request $request, string $plan, TenantProvisioningService $provisioner): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $planModel = Plan::find($plan);
        if (! $planModel) {
            return response()->json(['success' => false, 'error' => 'Subscription plan not found.'], 404);
        }
        if ((float) $planModel->price > 0) {
            return response()->json(['success' => false, 'error' => 'This plan requires payment and cannot be activated for free.'], 422);
        }

        try {
            $provisioner->activatePlan($company, $planModel, 'free_trial', ['user' => $user]);
            $company->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Plan activated.',
                'plan_name' => $company->plan_name,
                'expires_at' => $company->expires_at?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Free Plan Activation Failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 17c. Subscription Management: Create Razorpay Order for Plan Purchase
     * POST /api/v1/pos/subscription/plans/{plan}/razorpay/order
     */
    public function subscriptionRazorpayOrder(Request $request, string $plan, SubscriptionPaymentGatewayService $gatewayService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $planModel = Plan::find($plan);
        if (! $planModel) {
            return response()->json(['success' => false, 'error' => 'Subscription plan not found.'], 404);
        }

        try {
            $basePrice = (float) $planModel->price;
            $taxRate = 18.00;
            $totalAmount = $basePrice + round(($basePrice * $taxRate) / 100, 2);

            $order = $gatewayService->createRazorpayOrder($planModel, $totalAmount, $company->currency ?: 'USD', $company, $user);

            return response()->json(array_merge(['success' => true, 'total_amount' => $totalAmount], $order));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * 17d. Subscription Management: Verify Razorpay Payment & Activate Plan
     * POST /api/v1/pos/subscription/plans/{plan}/razorpay/verify
     */
    public function subscriptionRazorpayVerify(
        Request $request,
        string $plan,
        SubscriptionPaymentGatewayService $gatewayService,
        TenantProvisioningService $provisioner
    ): JsonResponse {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $planModel = Plan::find($plan);
        if (! $planModel) {
            return response()->json(['success' => false, 'error' => 'Subscription plan not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'payment_id' => ['required', 'string'],
            'order_id' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Missing payment verification details.',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $gatewayService->verifyRazorpayPayment(
                $request->input('payment_id'),
                $request->input('order_id'),
                $request->input('signature')
            );

            $provisioner->activatePlan($company, $planModel, 'razorpay', [
                'user' => $user,
                'activation_code' => $request->input('payment_id'),
            ]);
            $company->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Payment verified. Plan activated.',
                'plan_name' => $company->plan_name,
                'expires_at' => $company->expires_at?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Razorpay Payment Verification Failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['success' => false, 'error' => 'Payment verification failed: '.$e->getMessage()], 422);
        }
    }

    /**
     * 17e. Subscription Management: Create Mercado Pago Checkout Preference
     * POST /api/v1/pos/subscription/plans/{plan}/mercadopago/preference
     */
    public function subscriptionMercadoPagoPreference(Request $request, string $plan, SubscriptionPaymentGatewayService $gatewayService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $planModel = Plan::find($plan);
        if (! $planModel) {
            return response()->json(['success' => false, 'error' => 'Subscription plan not found.'], 404);
        }

        try {
            $totalAmount = (float) $planModel->price * 1.18;
            $preference = $gatewayService->createMercadoPagoPreference($planModel, $totalAmount, $company->currency ?: 'USD', $company, $user);

            return response()->json([
                'success' => true,
                'checkout_url' => $preference['checkout_url'],
                'total_amount' => $totalAmount,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * 17f. Subscription Management: Verify Mercado Pago Payment & Activate Plan
     * POST /api/v1/pos/subscription/plans/{plan}/mercadopago/verify
     */
    public function subscriptionMercadoPagoVerify(
        Request $request,
        string $plan,
        SubscriptionPaymentGatewayService $gatewayService,
        TenantProvisioningService $provisioner
    ): JsonResponse {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $planModel = Plan::find($plan);
        if (! $planModel) {
            return response()->json(['success' => false, 'error' => 'Subscription plan not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'payment_id' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Missing payment_id.', 'details' => $validator->errors()], 422);
        }

        try {
            $gatewayService->verifyMercadoPagoPayment($request->input('payment_id'), $company);

            $provisioner->activatePlan($company, $planModel, 'mercadopago', [
                'user' => $user,
                'activation_code' => $request->input('payment_id'),
            ]);
            $company->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Payment verified. Plan activated.',
                'plan_name' => $company->plan_name,
                'expires_at' => $company->expires_at?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Mercado Pago Payment Verification Failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['success' => false, 'error' => 'Payment verification failed: '.$e->getMessage()], 422);
        }
    }

    /**
     * 18. Send WhatsApp / Email Delivery for Invoices & Quotations
     * POST /api/v1/pos/send-delivery
     */
    public function sendDelivery(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'in:email,whatsapp,sms,custom'],
            'document_type' => ['required', 'string', 'in:invoice,quotation,kot,kitchen_order_ticket,kitchen-ticket'],
            'recipient' => ['required_unless:type,custom', 'nullable', 'string'],
            'channel_id' => ['required_if:type,custom', 'nullable', 'integer'],
            'document_id' => ['nullable', 'string'],
            'custom_message' => ['nullable', 'string'],
            'document_data' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error',
                'details' => $validator->errors(),
            ], 422);
        }

        $type = $request->input('type');
        $docType = $request->input('document_type');
        if (in_array($docType, ['kot', 'kitchen_order_ticket', 'kitchen-ticket'], true)) {
            $request->validate(['document_id' => ['required']]);

            return app(\App\Http\Controllers\Api\DocumentDispatchController::class)->dispatchKot($request, $request->input('document_id'), $type);
        }
        $recipient = trim((string) $request->input('recipient'));
        $docId = $request->input('document_id');
        $customMessage = $request->input('custom_message');
        $docData = $request->input('document_data', []);

        // Find Sale/Quotation in Database or hydrate
        $sale = null;
        if (! empty($docId)) {
            $sale = Sale::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where(function ($q) use ($docId) {
                    $q->where('external_id', $docId)
                        ->orWhere('sale_number', $docId)
                        ->orWhere('id', $docId);
                })
                ->first();
        }

        if (! $sale && ! empty($docData)) {
            // Reconcile/create sale if it was queued offline before being batch-synced
            $extId = (string) ($docData['id'] ?? $docId ?? Str::uuid()->toString());
            $items = (array) ($docData['items'] ?? []);
            $total = (float) ($docData['total'] ?? 0);
            $orderNumber = $docData['order_number'] ?? $docData['quote_number'] ?? $docData['sale_number'] ?? ('POS-'.strtoupper(substr($extId, 0, 8)));

            $sale = Sale::create([
                'company_id' => $company->id,
                'external_id' => $extId,
                'sale_number' => $orderNumber,
                'user_id' => $user?->id,
                'customer_name' => $docData['customer_name'] ?? null,
                'total' => $total,
                'net_amount' => $total,
                'discount' => (float) ($docData['discount'] ?? 0),
                'tax_amount' => (float) ($docData['tax'] ?? 0),
                'payment_method' => $docData['payment_method'] ?? 'cash',
                'status' => $docType === 'quotation' ? 'draft' : 'completed',
                'operation_type' => $docType === 'quotation' ? 'quotation' : 'sale',
                'items' => $items,
                'notes' => $docData['notes'] ?? null,
                'created_at' => isset($docData['createdAt']) ? Carbon::parse($docData['createdAt']) : now(),
                'updated_at' => now(),
            ]);
        }

        if (! $sale) {
            return response()->json([
                'success' => false,
                'error' => 'Document not found and cannot be delivered.',
            ], 404);
        }

        $messageQueue = app(MessageQueueService::class);

        try {
            if ($type === 'custom') {
                $channel = CustomNotificationChannel::where('company_id', $company->id)
                    ->where('is_active', true)
                    ->find($request->input('channel_id'));

                if (! $channel || ! $channel->handlesEvent($docType)) {
                    return response()->json(['success' => false, 'error' => 'That notification channel is unavailable.'], 422);
                }

                app(WebhookDispatchService::class)->dispatch($channel, [
                    'customer_name' => $sale->customer?->name ?? $sale->customer_name ?? '',
                    'invoice_no' => $sale->sale_number,
                    'total' => (float) $sale->total,
                    'due_amount' => (float) $sale->due_amount,
                    'receipt_link' => route('sales.public', $sale->sale_number),
                ]);

                AuditLog::record('pos.delivery_dispatched', $company->id, $user?->id, [
                    'type' => 'custom',
                    'channel_id' => $channel->id,
                    'document_type' => $docType,
                    'document_number' => $sale->sale_number,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Dispatched to {$channel->name}.",
                    'document_number' => $sale->sale_number,
                ]);
            }

            $dispatcher = app(TenantNotificationDispatcherService::class);

            if ($type === 'sms') {
                $smsResult = $docType === 'quotation'
                    ? $dispatcher->dispatchQuotation($company, $sale, ['sms'], $recipient)
                    : $dispatcher->dispatchReceipt($company, $sale, ['sms'], $recipient);

                AuditLog::record('pos.delivery_dispatched', $company->id, $user?->id, [
                    'type' => 'sms',
                    'document_type' => $docType,
                    'recipient' => $recipient,
                    'document_number' => $sale->sale_number,
                ]);

                $result = $smsResult['sms'] ?? ['success' => false, 'status' => 'failed', 'message' => 'SMS dispatch failed.'];
                return response()->json($result + ['document_number' => $sale->sale_number], ($result['success'] ?? false) ? 200 : 422);
            }

            if ($type === 'email') {
                $dispatchResult = $docType === 'quotation'
                    ? $dispatcher->dispatchQuotation($company, $sale, ['email'], null, $recipient)
                    : $dispatcher->dispatchReceipt($company, $sale, ['email'], null, $recipient);

                $emailRes = $dispatchResult['email'] ?? [];
                if (($emailRes['status'] ?? '') === 'manual_link') {
                    return response()->json($emailRes + ['document_number' => $sale->sale_number]);
                }
                if (! empty($emailRes['success'])) {
                    AuditLog::record('pos.delivery_dispatched', $company->id, $user?->id, [
                        'type' => 'email',
                        'document_type' => $docType,
                        'recipient' => $recipient,
                        'document_number' => $sale->sale_number,
                        'status' => 'sent',
                    ]);

                    return response()->json([
                        'success' => true,
                        'status' => 'sent',
                        'message' => $emailRes['message'] ?? "Email sent successfully to {$recipient}.",
                        'document_number' => $sale->sale_number,
                        'sent_at' => now()->toIso8601String(),
                    ]);
                }

                $result = $messageQueue->sendOrQueueEmail($sale, $recipient, $customMessage, true);

                AuditLog::record('pos.delivery_dispatched', $company->id, $user?->id, [
                    'type' => 'email',
                    'document_type' => $docType,
                    'recipient' => $recipient,
                    'document_number' => $sale->sale_number,
                    'status' => $result['status'],
                ]);

                return response()->json([
                    'success' => true,
                    'status' => $result['status'],
                    'message' => $result['status'] === 'sent'
                        ? "Email sent successfully to {$recipient}."
                        : "No connection right now — queued and will send to {$recipient} automatically once back online.",
                    'document_number' => $sale->sale_number,
                    'sent_at' => $result['status'] === 'sent' ? now()->toIso8601String() : null,
                ]);
            } else {
                $dispatchResult = $docType === 'quotation'
                    ? $dispatcher->dispatchQuotation($company, $sale, ['whatsapp'], $recipient)
                    : $dispatcher->dispatchReceipt($company, $sale, ['whatsapp'], $recipient);

                $wa = $dispatchResult['whatsapp'] ?? [];
                $status = $wa['status'] ?? null;
                $url = $wa['url'] ?? $wa['whatsapp_url'] ?? null;

                if ($status === 'sent') {
                    AuditLog::record('pos.delivery_dispatched', $company->id, $user?->id, [
                        'type' => 'whatsapp',
                        'document_type' => $docType,
                        'recipient' => $recipient,
                        'document_number' => $sale->sale_number,
                        'status' => 'sent',
                    ]);

                    return response()->json([
                        'success' => true,
                        'status' => 'sent',
                        'message' => $wa['message'] ?? "WhatsApp message sent successfully to {$recipient}.",
                        'whatsapp_url' => $url,
                        'document_number' => $sale->sale_number,
                        'sent_at' => now()->toIso8601String(),
                    ]);
                }

                $result = $messageQueue->sendOrQueueWhatsApp($sale, $recipient, $customMessage);

                AuditLog::record('pos.delivery_dispatched', $company->id, $user?->id, [
                    'type' => 'whatsapp',
                    'document_type' => $docType,
                    'recipient' => $recipient,
                    'document_number' => $sale->sale_number,
                    'status' => $result['status'],
                ]);

                return response()->json([
                    'success' => true,
                    'status' => $result['status'],
                    'message' => match ($result['status']) {
                        'sent' => "WhatsApp message sent successfully to {$recipient}.",
                        'queued' => "No connection right now — queued and will send to {$recipient} automatically once back online.",
                        default => "WhatsApp message prepared for {$recipient}.",
                    },
                    'whatsapp_url' => $url ?? $result['url'] ?? null,
                    'document_number' => $sale->sale_number,
                    'sent_at' => $result['status'] === 'sent' ? now()->toIso8601String() : null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('POS Delivery Exception: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'error' => 'Delivery failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exact sale payload used by notification deep links. Unlike sync-pull,
     * this is not limited to the latest 200 records.
     */
    public function saleDetails(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $sale = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(fn ($query) => $query->where('id', $id)
                ->orWhere('external_id', $id)
                ->orWhere('sale_number', $id))
            ->first();

        if (! $sale) {
            return response()->json(['success' => false, 'error' => 'Sale not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'sale' => [
                'id' => (string) ($sale->external_id ?: $sale->id),
                'server_id' => (string) $sale->id,
                'sale_number' => $sale->sale_number,
                'customer_name' => $sale->customer_name,
                'items' => is_array($sale->items) ? $sale->items : (json_decode($sale->items ?? '', true) ?: []),
                'total' => (float) $sale->total,
                'discount' => (float) $sale->discount,
                'tax' => (float) $sale->tax_amount,
                'payment_method' => $sale->payment_method,
                'payment_status' => $sale->payment_status,
                'paid_amount' => (float) $sale->paid_amount,
                'due_amount' => (float) $sale->due_amount,
                'status' => $sale->status,
                'createdAt' => $sale->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * 18b. Sale/Invoice PDF (same document InvoiceDeliveryService already
     * produces for the web app and for email/WhatsApp delivery).
     * GET /api/v1/pos/sales/{id}/pdf?format=a4|80mm|58mm
     */
    public function salePdf(Request $request, string $id)
    {
        $company = $this->resolveCompany($request);

        $sale = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)
                    ->orWhere('external_id', $id)
                    ->orWhere('sale_number', $id);
            })
            ->first();

        if (! $sale) {
            return response()->json(['success' => false, 'error' => 'Sale not found.'], 404);
        }

        $pdf = app(InvoiceDeliveryService::class)->generateInvoicePdf($sale, $request->query('format'));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$sale->sale_number.'.pdf"',
            'Cache-Control' => 'no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * 19. Taxes Management: List Tax Rules
     * GET /api/v1/pos/taxes
     */
    public function taxRulesIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $taxes = TaxRule::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->get()
            ->map(function (TaxRule $t) {
                return [
                    'id' => (string) $t->id,
                    'name' => $t->tax_name,
                    'rate' => (float) $t->rate,
                    'is_default' => (bool) $t->is_default,
                    'active' => (bool) $t->active,
                    'type' => $t->type ?? 'percentage',
                    'calc_type' => $t->calc_type ?? 'exclusive',
                    'updated_at' => $t->updated_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'taxes' => $taxes,
        ]);
    }

    /**
     * 20. Taxes Management: Store / Update Tax Rule
     * POST /api/v1/pos/taxes
     */
    public function taxRulesStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error',
                'details' => $validator->errors(),
            ], 422);
        }

        if ($request->boolean('is_default')) {
            TaxRule::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->update(['is_default' => false]);
        }

        $taxRule = TaxRule::create([
            'company_id' => $company->id,
            'tax_name' => $request->input('name'),
            'tax_code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $request->input('name')), 0, 8)),
            'rate' => (float) $request->input('rate'),
            'is_default' => $request->boolean('is_default', false),
            'active' => $request->boolean('active', true),
            'calc_type' => 'exclusive',
            'type' => 'percentage',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tax rule saved successfully.',
            'tax' => [
                'id' => (string) $taxRule->id,
                'name' => $taxRule->tax_name,
                'rate' => (float) $taxRule->rate,
                'is_default' => (bool) $taxRule->is_default,
                'active' => (bool) $taxRule->active,
            ],
        ]);
    }

    /**
     * 21. Taxes Management: Update Tax Rule
     * PUT /api/v1/pos/taxes/{id}
     */
    public function taxRulesUpdate(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $taxRule = TaxRule::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $taxRule) {
            return response()->json(['success' => false, 'error' => 'Tax rule not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error',
                'details' => $validator->errors(),
            ], 422);
        }

        if ($request->boolean('is_default')) {
            TaxRule::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->update(['is_default' => false]);
        }

        $taxRule->update([
            'tax_name' => $request->input('name'),
            'rate' => (float) $request->input('rate'),
            'is_default' => $request->boolean('is_default', $taxRule->is_default),
            'active' => $request->boolean('active', $taxRule->active),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tax rule updated successfully.',
            'tax' => [
                'id' => (string) $taxRule->id,
                'name' => $taxRule->tax_name,
                'rate' => (float) $taxRule->rate,
                'is_default' => (bool) $taxRule->is_default,
                'active' => (bool) $taxRule->active,
            ],
        ]);
    }

    /**
     * Auto-add the standard fiscal tax rules for a country (SDUI "Add New Tax
     * Rule" tab -> "Auto-add for <Country>"). Same presets the web Settings >
     * Taxes tab pre-seeds — see TaxCalculationService::seedTenantDefaultTaxRules().
     * POST /api/tenant/settings/tax-rules/seed-country
     */
    public function taxRulesSeedCountry(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $country = strtoupper(trim((string) ($request->input('country') ?: $company->country ?: 'US')));

        $service = app(TaxCalculationService::class);
        $service->seedTenantDefaultTaxRules($company, $country);

        $presets = $service->getJurisdictionPresets($country);
        $countryName = $presets['country'] ?? $country;
        $count = TaxRule::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('country', $country)
            ->count();

        return response()->json([
            'success' => true,
            'message' => "Standard tax rules for {$countryName} added.",
            'country' => $country,
            'count' => $count,
        ]);
    }

    /**
     * Flip a tax rule's active flag (SDUI "Enable / Disable" button on the
     * Taxes & Compliance screen). Update needs name + rate; this does not.
     * POST /api/tenant/settings/tax-rules/{id}/toggle
     */
    public function taxRulesToggle(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $taxRule = TaxRule::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $taxRule) {
            return response()->json(['success' => false, 'error' => 'Tax rule not found.'], 404);
        }

        $taxRule->update(['active' => ! $taxRule->active]);

        return response()->json([
            'success' => true,
            'message' => $taxRule->tax_name.' is now '.($taxRule->active ? 'active' : 'disabled').'.',
        ]);
    }

    /**
     * SDUI bottom-sheet schema for editing one tax rule, opened by the "Edit"
     * button on SchemaResponse::taxesView. Submits to taxRulesUpdate().
     * GET /api/tenant/settings/tax-rules/{id}/edit-sheet
     */
    public function taxRulesEditSheet(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $taxRule = TaxRule::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $taxRule) {
            return response()->json(['success' => false, 'error' => 'Tax rule not found.'], 404);
        }

        $sheet = SchemaResponse::screen("Edit {$taxRule->tax_name}", [
            SchemaResponse::card([
                SchemaResponse::text('Edit Tax Rule', 'title_medium', ['bold' => true]),
                SchemaResponse::text('Changes apply immediately at checkout.', 'body_small', ['color' => '#64748b']),
                SchemaResponse::divider(),
                SchemaResponse::textInput('name', 'Name', $taxRule->tax_name),
                SchemaResponse::textInput('rate', 'Rate (%)', number_format((float) $taxRule->rate, 3, '.', ''), ['keyboard_type' => 'decimal']),
                SchemaResponse::toggleSwitch('is_default', 'Set as default', (bool) $taxRule->is_default),
                SchemaResponse::toggleSwitch('active', 'Active', (bool) $taxRule->active),
                SchemaResponse::buttonPrimary('Save Changes', SchemaResponse::formSubmitAction(
                    "/api/tenant/settings/tax-rules/{$taxRule->id}",
                    'POST',
                    'Tax rule updated.',
                    navigateBack: true,
                    reload: true
                ), 'save'),
            ]),
        ]);

        return response()->json($sheet);
    }

    /**
     * 22. Taxes Management: Set Default Tax Rule
     * POST /api/v1/pos/taxes/{id}/set-default
     */
    public function taxRulesSetDefault(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $taxRule = TaxRule::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $taxRule) {
            return response()->json(['success' => false, 'error' => 'Tax rule not found.'], 404);
        }

        TaxRule::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->update(['is_default' => false]);
        $taxRule->update(['is_default' => true]);

        return response()->json(['success' => true, 'message' => $taxRule->tax_name.' is now the default tax rule.']);
    }

    /**
     * 23. Taxes Management: Delete Tax Rule
     * DELETE /api/v1/pos/taxes/{id}
     */
    public function taxRulesDestroy(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $taxRule = TaxRule::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $taxRule) {
            return response()->json(['success' => false, 'error' => 'Tax rule not found.'], 404);
        }

        $taxRule->delete();

        return response()->json(['success' => true, 'message' => 'Tax rule deleted.']);
    }

    /**
     * 24. Due Payments / Receivables: per-invoice due list for the mobile
     * dashboard's "Due Payments / Receivables" panel.
     * GET /api/v1/pos/receivables/due
     */
    public function dueReceivables(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $sales = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(fn ($operation) => $operation->whereNull('operation_type')->orWhere('operation_type', 'sale'))
            ->where('status', '!=', 'cancelled')
            ->where('due_amount', '>', 0)
            ->with('customer')
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'receivables' => collect($sales->items())->map(function (Sale $sale) {
                $postSaleData = SchemaResponse::postSaleActionData($sale);
                $documentType = str_starts_with(strtoupper((string) $sale->sale_number), 'POS-') ? 'sale' : 'invoice';
                $postSaleData['actions_endpoint'] = "/api/v1/tenant/receivables/{$sale->id}/reminder-sheet?document_type={$documentType}";
                $nativeSheetAction = [
                    'type' => 'show_post_sale_sheet',
                    'action_type' => 'show_post_sale_sheet',
                    'data' => $postSaleData,
                ];

                return [
                    'sale_id' => (string) ($sale->external_id ?: $sale->id),
                    'document_id' => (string) $sale->id,
                    'document_type' => $documentType,
                    'sale_number' => $sale->sale_number,
                    'customer_name' => $sale->customer?->name ?? $sale->customer_name ?? 'Walk-in',
                    'phone' => $sale->customer?->phone,
                    'email' => $sale->customer?->email,
                    'date' => $sale->created_at?->toIso8601String(),
                    'due_date' => $sale->due_date?->toIso8601String(),
                    'due_reminder_at' => $sale->due_reminder_at?->toIso8601String(),
                    'due_reminder_sent_at' => $sale->due_reminder_sent_at?->toIso8601String(),
                    'total' => (float) $sale->total,
                    'paid_amount' => (float) $sale->paid_amount,
                    'due_amount' => (float) $sale->due_amount,
                    'status' => $sale->payment_status,
                    // Card taps and the visible reminder affordance both use
                    // the exact native POS post-sale bottom-sheet contract.
                    'action' => $nativeSheetAction,
                    'on_tap' => $nativeSheetAction,
                    'modal_endpoint' => "/api/v1/tenant/documents/{$documentType}/{$sale->id}/actions-sheet",
                    'post_sale_sheet' => [
                        'action' => 'show_post_sale_sheet',
                        'data' => $postSaleData,
                    ],
                    'actions' => [
                        [
                            'label' => 'Schedule push reminder',
                            'icon' => 'schedule',
                            'action' => [
                                'type' => 'OPEN_DIALOG',
                                'action_type' => 'OPEN_DIALOG',
                                'title' => 'Schedule push reminder',
                                'endpoint' => "/api/v1/pos/receivables/{$sale->id}/reminder",
                            ],
                        ],
                        [
                            'label' => 'Send Reminder',
                            'icon' => 'send',
                            'action' => $nativeSheetAction,
                        ],
                    ],
                ];
            })->values(),
            'total' => $sales->total(),
            'current_page' => $sales->currentPage(),
            'last_page' => $sales->lastPage(),
        ]);
    }

    /**
     * 25. Due Payments / Receivables: dispatch a reminder for one sale.
     * POST /api/v1/pos/receivables/{sale}/remind
     */
    public function remindReceivable(Request $request, string $sale): JsonResponse
    {
        $company = $this->resolveCompany($request);

        if ($request->isMethod('get')) {
            return app(ReceivablesController::class)->reminderSheet($request, $sale);
        }

        $validator = Validator::make($request->all(), [
            'channel' => ['required', 'string', 'in:whatsapp,email,sms,custom'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $saleModel = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(fn ($q) => $q->where('id', $sale)->orWhere('external_id', $sale))
            ->with('customer')
            ->first();

        if (! $saleModel) {
            return response()->json(['success' => false, 'error' => 'Sale not found.'], 404);
        }

        $delivery = app(InvoiceDeliveryService::class);
        $channel = $request->input('channel');

        if ($channel === 'sms') {
            $phone = preg_replace('/[^0-9+]/', '', (string) ($saleModel->customer?->phone ?? $saleModel->customer_phone ?? ''));
            if (empty($phone)) {
                return response()->json(['success' => false, 'error' => 'This customer has no phone number on file.'], 422);
            }

            $smsBody = $delivery->buildDueReminderMessage($saleModel);
            $res = app(TenantNotificationDispatcherService::class)->dispatchSms($company, $phone, $smsBody);

            AuditLog::record('pos.delivery_dispatched', $company->id, $this->resolveUser($request, $company)?->id, [
                'type' => 'sms',
                'document_type' => 'receivable_reminder',
                'recipient' => $phone,
                'document_number' => $saleModel->sale_number,
                'status' => ($res['success'] ?? false) ? 'sent' : 'failed',
            ]);

            if (! ($res['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error' => $res['error'] ?? 'Failed to send SMS reminder.',
                    'details' => $res['body'] ?? null,
                ], 422);
            }

            if (($res['status'] ?? '') === 'manual_link') {
                return response()->json($res + ['document_number' => $saleModel->sale_number]);
            }

            return response()->json([
                'success' => true,
                'status' => 'sent',
                'message' => 'Payment reminder sent via SMS (Text Message).',
                'document_number' => $saleModel->sale_number,
            ]);
        }

        if ($channel === 'whatsapp') {
            $phone = $saleModel->customer?->phone;
            if (empty($phone)) {
                return response()->json(['success' => false, 'error' => 'This customer has no phone number on file.'], 422);
            }

            $result = app(MessageQueueService::class)->sendOrQueueWhatsApp($saleModel, $phone, $delivery->buildDueReminderMessage($saleModel));

            if ($result['status'] === 'manual_link') {
                return response()->json(['success' => true, 'fallback_url' => $result['url']]);
            }

            return response()->json(['success' => true, 'message' => 'Reminder sent via WhatsApp.']);
        }

        if ($channel === 'email') {
            $email = $saleModel->customer?->email;
            $smtp = $delivery->getSmtpConfig($company);

            if ($email && ! empty($smtp['host'])) {
                try {
                    $delivery->sendDueReminderEmail($saleModel, $email);

                    return response()->json(['success' => true, 'message' => 'Reminder sent via email.']);
                } catch (\Throwable $e) {
                    return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
                }
            }

            $subject = rawurlencode("Payment Reminder: Invoice #{$saleModel->sale_number}");
            $body = rawurlencode($delivery->buildDueReminderMessage($saleModel));

            return response()->json([
                'success' => true,
                'fallback_mailto' => "mailto:{$email}?subject={$subject}&body={$body}",
            ]);
        }

        // custom
        app(WebhookDispatchService::class)->dispatchEvent($company->id, 'due_reminder', [
            'customer_name' => $saleModel->customer?->name ?? $saleModel->customer_name ?? '',
            'invoice_no' => $saleModel->sale_number,
            'due_amount' => (float) $saleModel->due_amount,
            'due_date' => $saleModel->due_date?->toDateString() ?? '',
            'receipt_link' => route('sales.public', $saleModel->sale_number),
        ]);

        return response()->json(['success' => true, 'message' => 'Reminder dispatched to custom notification channels.']);
    }

    public function scheduleReceivableReminder(Request $request, string $sale): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $validator = Validator::make($request->all(), [
            'due_date' => ['required', 'date'],
            'reminder_at' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $saleModel = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(fn ($query) => $query->where('id', $sale)->orWhere('external_id', $sale))
            ->where('due_amount', '>', 0)
            ->first();

        if (! $saleModel) {
            return response()->json(['success' => false, 'error' => 'Due invoice not found.'], 404);
        }

        $saleModel->update([
            'due_date' => Carbon::parse($request->input('due_date'))->toDateString(),
            'due_reminder_at' => Carbon::parse($request->input('reminder_at'))->utc(),
            'due_reminder_sent_at' => null,
            'due_reminder_dismissed_at' => null,
        ]);

        AuditLog::record('receivable.reminder_scheduled', $company->id, $user?->id, [
            'sale_id' => $saleModel->id,
            'reminder_at' => $saleModel->due_reminder_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invoice reminder scheduled.',
            'due_date' => $saleModel->due_date?->toDateString(),
            'due_reminder_at' => $saleModel->due_reminder_at?->toIso8601String(),
        ]);
    }
}
