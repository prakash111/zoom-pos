<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\TaxRule;
use App\Models\TenantApiKey;
use App\Models\Unit;
use App\Models\User;
use App\Services\Delivery\MessageQueueService;
use App\Services\Financial\CustomerLedgerService;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Payment\SubscriptionPaymentGatewayService;
use App\Services\Tenancy\TenantProvisioningService;
use App\Services\TaxEngineService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PosSyncApiController extends Controller
{
    use ResolvesTenantSyncContext;

    protected function desktopPermissions(User $user): array
    {
        $permissions = [
            'pos.create' => $user->hasPermission('pos', 'create'),
            'pos.edit' => $user->hasPermission('pos', 'edit'),
            'products.view' => $user->hasPermission('products', 'view'),
            'products.create' => $user->hasPermission('products', 'create'),
            'products.edit' => $user->hasPermission('products', 'edit'),
            'customers.view' => $user->hasPermission('customers', 'view'),
            'customers.create' => $user->hasPermission('customers', 'create'),
            'customers.edit' => $user->hasPermission('customers', 'edit'),
            'reports.view' => $user->hasPermission('reports', 'view'),
            'settings.view' => $user->hasPermission('settings', 'view'),
        ];

        // A `.view` flag for every remaining module (mirrors
        // PermissionChecker::MODULES exactly) so the mobile drawer/menu can
        // gate each feature tile by the user's own authorized modules
        // instead of showing every module to every role.
        foreach (array_keys(\App\Services\Auth\PermissionChecker::MODULES) as $module) {
            $permissions["{$module}.view"] ??= $user->hasPermission($module, 'view');
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

        // Generate or fetch active Tenant API Key for POS terminal authentication
        $apiKey = TenantApiKey::firstOrCreate(
            [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'name' => 'Desktop POS Client ('.($user->name ?: 'Terminal').')',
                'active' => true,
            ],
            [
                'token' => 'zk_live_'.Str::random(40),
                'permissions' => ['*'],
            ]
        );

        $subscription = Subscription::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->latest('started_at')
            ->first();

        return response()->json([
            'success' => true,
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
                'trade_name' => $company->trade_name ?? $company->name,
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
                'restaurant_mode_locked' => (bool) $company->restaurant_mode_locked,
                'drawer_cover_url' => $company->getDrawerCoverUrl(),
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
     * cards (Retail / Cafe & Restaurant) it should offer, per the Superadmin's
     * global "Allowed Registration Modes" setting.
     * GET /api/v1/pos/auth/registration-config
     */
    public function registrationConfig(): JsonResponse
    {
        $enabled = \App\Services\Modular\ModuleRegistry::enabledRegistrationModes();
        $legacyString = in_array('restaurant', $enabled, true) && in_array('retail', $enabled, true)
            ? 'both'
            : (in_array('restaurant', $enabled, true) ? 'restaurant_only' : 'retail_only');

        $activeModules = array_values(array_map(function ($mod) {
            return [
                'id' => $mod['id'],
                'title' => $mod['title'],
                'description' => $mod['description'],
                'icon' => $mod['icon'] ?? 'widgets',
                'layout_type' => $mod['layout_type'] ?? 'standard_grid',
            ];
        }, \App\Services\Modular\ModuleRegistry::registrationModules()));

        return response()->json([
            'success' => true,
            'allowed_registration_modes' => $legacyString,
            'enabled_modes' => $enabled,
            'active_modules' => $activeModules,
        ]);
    }

    /**
     * Platform-wide (not tenant-scoped) branding for pre-auth screens —
     * currently just the Sign In screen's logo, set by the Superadmin in
     * Branding settings. GET /api/v1/pos/auth/branding
     */
    public function branding(): JsonResponse
    {
        $branding = \App\Models\PlatformBranding::current();

        return response()->json([
            'success' => true,
            'platform_name' => $branding->platform_name ?: config('app.name', 'Smart Inventory & Sales'),
            'brand_logo_url' => $branding->getLogoPublicUrl(),
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
        $enabledModes = \App\Services\Modular\ModuleRegistry::enabledRegistrationModes();

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
                'currency' => $request->input('currency', 'USD'),
                'pos_mode' => $request->input('pos_mode', 'general'),
                'plan_name' => $request->input('plan_name', 'trial'),
                'activation_code' => $request->input('activation_code'),
            ];

            $result = $provisioner->registerTenant($regData);
            $company = $result['company'];
            $user = $result['user'];

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

        return response()->json([
            'success' => true,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'company_id' => $user->company_id,
                'permissions' => $this->desktopPermissions($user),
            ] : null,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'trade_name' => $company->trade_name ?? $company->name,
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
                'restaurant_mode_locked' => (bool) $company->restaurant_mode_locked,
                'drawer_cover_url' => $company->getDrawerCoverUrl(),
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
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'trade_name' => $company->trade_name ?? $company->name,
                'currency' => $company->currency ?? 'USD',
                'currency_symbol' => $company->currency_symbol ?? '$',
                'tax_number' => $company->document ?? $company->tax_id ?? '',
                'timezone' => $company->resolveTimezone(),
                'address' => $company->address ?? '',
                'city' => $company->city ?? '',
                'state' => $company->state ?? '',
                'phone' => $company->phone ?? '',
                'receipt_footer_note' => $company->receipt_footer_note ?? 'Thank you for your business!',
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
        ];

        return response()->json([
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
        ]);
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
     * Helper to process sales batch transactionally
     */
    protected function processSalesBatch(array $salesPayload, Company $company, ?User $user): array
    {
        $syncedIds = [];
        $rejected = [];

        DB::transaction(function () use ($salesPayload, $company, $user, &$syncedIds, &$rejected) {
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

        DB::beginTransaction();
        try {
            // 1. Process Offline Created Products
            if ($request->has('created_products') && is_array($request->input('created_products'))) {
                foreach ($request->input('created_products') as $prodData) {
                    $extId = (string) ($prodData['id'] ?? Str::uuid()->toString());
                    $p = Product::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->where('external_id', $extId)
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
                        ->where('external_id', $extId)
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
                        ->where('external_id', $extId)
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
                    }
                    $syncedCategories[] = $extId;
                }
            }

            if ($request->has('created_brands') && is_array($request->input('created_brands'))) {
                foreach ($request->input('created_brands') as $brandData) {
                    $extId = (string) ($brandData['id'] ?? Str::uuid()->toString());
                    $brand = Brand::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->where('external_id', $extId)
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
                    }
                    $syncedBrands[] = $extId;
                }
            }

            if ($request->has('created_suppliers') && is_array($request->input('created_suppliers'))) {
                foreach ($request->input('created_suppliers') as $supData) {
                    $extId = (string) ($supData['id'] ?? Str::uuid()->toString());
                    $sup = Supplier::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->where('external_id', $extId)
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
                    }
                    $syncedSuppliers[] = $extId;
                }
            }

            if ($request->has('created_units') && is_array($request->input('created_units'))) {
                foreach ($request->input('created_units') as $unitData) {
                    $extId = (string) ($unitData['id'] ?? Str::uuid()->toString());
                    $unit = Unit::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->where('external_id', $extId)
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
                        ->where('external_id', $extId)
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

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('POS Sync Batch Ingestion Failed: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'error' => 'Batch sync failed: '.$e->getMessage(),
            ], 500);
        }

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
        ]);
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

        $products = Product::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['category', 'brand'])
            ->orderBy('name')
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

        $categories = Category::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

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
                'code' => $pm->code ?: \Illuminate\Support\Str::slug($pm->name, '_'),
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

        return response()->json([
            'success' => true,
            'message' => 'Customer saved successfully.',
            'customer' => [
                'id' => (string) ($customer->external_id ?: $customer->id),
                'server_id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'document' => $customer->document,
                'balance_due' => $customer->total_due,
                'loyalty_points' => (int) $customer->loyalty_points,
            ],
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

        // 7-day revenue trend
        $sevenDaysTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $dayRev = (float) Sale::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('status', '!=', 'cancelled')
                ->whereDate('created_at', $d)
                ->sum('total');

            $sevenDaysTrend[] = [
                'date' => $d,
                'day' => now()->subDays($i)->format('D'),
                'revenue' => $dayRev,
            ];
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

        return response()->json([
            'success' => true,
            'currency_symbol' => $company->currency_symbol ?? '$',
            'kpis' => [
                'today_revenue' => $todayRevenue,
                'today_orders' => $todayOrders,
                'month_revenue' => $monthRevenue,
                'month_orders' => $monthOrders,
                'all_time_revenue' => $allTimeRevenue,
                'all_time_orders' => $allTimeOrders,
                'average_order_value' => $avgTicket,
                'total_receivables' => $totalReceivables,
                'low_stock_count' => $lowStockCount,
            ],
            'payment_breakdown' => $paymentMethods,
            'revenue_trend' => $sevenDaysTrend,
            'top_products' => $topProducts,
        ]);
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
            'type' => ['required', 'string', 'in:email,whatsapp,custom'],
            'document_type' => ['required', 'string', 'in:invoice,quotation'],
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
                $channel = \App\Models\CustomNotificationChannel::where('company_id', $company->id)
                    ->where('is_active', true)
                    ->find($request->input('channel_id'));

                if (! $channel || ! $channel->handlesEvent($docType)) {
                    return response()->json(['success' => false, 'error' => 'That notification channel is unavailable.'], 422);
                }

                app(\App\Services\Delivery\WebhookDispatchService::class)->dispatch($channel, [
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

            if ($type === 'email') {
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
                    'whatsapp_url' => $result['url'] ?? null,
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
            ->where('status', '!=', 'cancelled')
            ->where('due_amount', '>', 0)
            ->with('customer')
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'receivables' => collect($sales->items())->map(fn (Sale $sale) => [
                'sale_id' => (string) ($sale->external_id ?: $sale->id),
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
            ])->values(),
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

        $validator = Validator::make($request->all(), [
            'channel' => ['required', 'string', 'in:whatsapp,email,custom'],
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
        app(\App\Services\Delivery\WebhookDispatchService::class)->dispatchEvent($company->id, 'due_reminder', [
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
