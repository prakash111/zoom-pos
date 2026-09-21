<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Company;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\Faq;
use App\Models\Language;
use App\Models\Product;
use App\Models\Sale;
use App\Models\TenantCustomPage;
use App\Models\TenantStoreMenu;
use App\Services\FinancialAnalyticsService;
use App\Services\Payment\StorefrontPaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StorefrontController extends Controller
{
    public function resolveCompany(Request $request): ?Company
    {
        if (app()->bound('tenant.company_id')) {
            $company = Company::withoutGlobalScopes()->find(app('tenant.company_id'));
            if ($company) {
                return $company;
            }
        }

        // Check host subdomain or custom domain
        $host = strtolower($request->getHost());
        $baseHost = parse_url(config('app.url'), PHP_URL_HOST);

        if ($baseHost && str_ends_with($host, '.'.$baseHost)) {
            $slug = substr($host, 0, -(strlen($baseHost) + 1));
            if ($slug && ! in_array($slug, Company::RESERVED_SLUGS, true)) {
                $company = Company::withoutGlobalScopes()->where('slug', $slug)->first();
                if ($company) {
                    return $company;
                }
            }
        }

        if ($baseHost && $host !== $baseHost && $host !== 'localhost' && $host !== '127.0.0.1') {
            $company = Company::withoutGlobalScopes()->where('custom_domain', $host)->first();
            if ($company) {
                return $company;
            }
        }

        // Fallback: check query parameter, request body, or default active company
        $storeSlug = $request->input('store') ?: $request->query('store') ?: $request->input('store_slug');
        if ($storeSlug) {
            $company = Company::withoutGlobalScopes()->where('slug', $storeSlug)->first();
            if ($company) {
                return $company;
            }
        }

        if ($request->filled('company_id')) {
            $company = Company::withoutGlobalScopes()->find($request->input('company_id'));
            if ($company) {
                return $company;
            }
        }

        return Company::withoutGlobalScopes()->first();
    }

    public function index(Request $request): View
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Storefront not found.');

        // Bind tenant.company_id into container for any scoped operations
        app()->instance('tenant.company_id', $company->id);

        $categories = Category::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->withCount(['products' => function ($q) use ($company) {
                $q->withoutGlobalScopes()->where('company_id', $company->id)->where('active', true);
            }])
            ->get();

        $products = Product::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('id', 'desc')
            ->get();

        $languages = Language::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $faqs = Faq::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->active()
            ->ordered()
            ->get();

        if ($faqs->isEmpty()) {
            Faq::seedDefaultsForCompany($company->id);
            $faqs = Faq::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->active()
                ->ordered()
                ->get();
        }

        return view('tenants.store.index', [
            'company' => $company,
            'categories' => $categories,
            'products' => $products,
            'languages' => $languages,
            'faqs' => $faqs,
            'catalog' => null,
            'storeMenus' => $this->getStoreMenus($company),
        ]);
    }

    /**
     * Retrieve categorized storefront menus for header and footer columns.
     */
    public function getStoreMenus(Company $company): array
    {
        TenantStoreMenu::seedDefaultsForCompany($company->id);

        $items = TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->visible()
            ->with(['page:id,title,slug', 'category:id,name'])
            ->ordered()
            ->get();

        return [
            'header_nav' => $items->where('location', TenantStoreMenu::LOCATION_HEADER)->values(),
            'footer_col_1' => $items->where('location', TenantStoreMenu::LOCATION_FOOTER_1)->values(),
            'footer_col_2' => $items->where('location', TenantStoreMenu::LOCATION_FOOTER_2)->values(),
            'footer_col_3' => $items->where('location', TenantStoreMenu::LOCATION_FOOTER_3)->values(),
        ];
    }

    /**
     * Render dynamic custom CMS page for the storefront.
     * GET /page/{slug}
     * GET /store/page/{slug}
     */
    public function showCmsPage(Request $request, string $slug): View
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Storefront not found.');

        app()->instance('tenant.company_id', $company->id);
        TenantCustomPage::seedDefaultsForCompany($company->id);

        $page = TenantCustomPage::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        abort_if(! $page, 404, 'Page not found.');

        $storeMenus = $this->getStoreMenus($company);

        return view('tenants.store.page', [
            'company' => $company,
            'page' => $page,
            'storeMenus' => $storeMenus,
            'categories' => Category::withoutGlobalScopes()->where('company_id', $company->id)->get(),
            'products' => Product::withoutGlobalScopes()->where('company_id', $company->id)->where('active', true)->get(),
        ]);
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store tenant not found.');

        // Normalize field aliases before validation
        if (! $request->has('delivery_address') && $request->has('address')) {
            $request->merge(['delivery_address' => $request->input('address')]);
        }
        if (! $request->has('customer_email') && $request->has('email')) {
            $request->merge(['customer_email' => $request->input('email')]);
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'delivery_address' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['required', 'string'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable'],
            'items.*.name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $customerEmail = $request->input('customer_email')
            ?? $request->input('email')
            ?? null;

        $deliveryAddress = $request->input('delivery_address')
            ?? $request->input('address')
            ?? null;

        $subtotal = 0;
        $formattedItems = [];

        foreach ($validated['items'] as $item) {
            $qty = (float) $item['quantity'];
            $price = (float) $item['price'];
            $subtotal += ($qty * $price);
            $formattedItems[] = [
                'product_id' => $item['id'] ?? null,
                'name' => $item['name'],
                'quantity' => $qty,
                'price' => $price,
                'total' => $qty * $price,
            ];
        }

        $saleCount = Sale::withoutGlobalScopes()->where('company_id', $company->id)->count();
        $codePart = strtoupper(substr($company->slug ?? preg_replace('/[^a-zA-Z0-9]/', '', $company->name), 0, 4));
        if (strlen($codePart) < 3) {
            $codePart = 'WEB';
        }
        $saleNumber = 'WEB-'.$codePart.'-'.sprintf('%04d', $saleCount + 1);

        $deliveryAddressParts = array_filter([
            $deliveryAddress,
            $validated['city'] ?? null,
            $validated['postal_code'] ?? null,
        ]);
        $fullAddress = implode(', ', $deliveryAddressParts);

        $paymentMethod = $validated['payment_method'] ?? 'cod';
        $discount = max(0, (float) ($request->input('discount', 0)));
        $couponCode = $request->input('coupon_code');
        $netAmount = max(0, $subtotal - $discount);
        $isPrepaid = in_array(strtolower($paymentMethod), ['online', 'card', 'shopcart_card', 'paypal'], true);

        $emailNote = $customerEmail ? ' | Email: '.$customerEmail : '';
        $couponNote = $couponCode ? ' | Coupon: '.$couponCode : '';
        $notes = trim(($validated['customer_notes'] ?? '').' | Phone: '.$validated['customer_phone'].$emailNote.$couponNote);

        $customerId = null;
        $authCustomer = null;

        // Check for authenticated customer via token
        $customerToken = $request->input('auth_token')
            ?? $request->input('customer_token')
            ?? $request->bearerToken()
            ?? $request->header('X-Customer-Token');

        if ($customerToken) {
            $authCustomer = Customer::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('auth_token', $customerToken)
                ->first();
            if ($authCustomer) {
                $customerId = $authCustomer->id;
            }
        }

        if (! $customerId) {
            // If caller explicitly sent an invalid token or requested strict authentication gate
            if ($request->filled('auth_token') || $request->header('X-Customer-Token') || $request->boolean('require_auth')) {
                return response()->json([
                    'success' => false,
                    'auth_required' => true,
                    'message' => __('Authentication required. Please sign in or register to complete your order.'),
                ], 401);
            }

            // Fallback for API callers providing customer contact details
            if (! empty($validated['customer_phone'])) {
                $email = $customerEmail ?? ($validated['customer_phone'].'@guest.zoomnearby.com');
                $customer = Customer::withoutGlobalScopes()->firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'phone' => $validated['customer_phone'],
                    ],
                    [
                        'name' => $validated['customer_name'] ?: 'Online Customer',
                        'email' => $email,
                        'address' => $deliveryAddress,
                        'city' => $validated['city'] ?? null,
                        'source' => 'storefront',
                    ]
                );
                $customerId = $customer->id;
                $authCustomer = $customer;
            } else {
                return response()->json([
                    'success' => false,
                    'auth_required' => true,
                    'message' => __('Authentication required. Please sign in or register to complete your order.'),
                ], 401);
            }
        }

        // Customer Verification Check
        $verificationService = app(\App\Services\Auth\CustomerVerificationService::class);
        if ($verificationService->shouldRequireVerification($company, $authCustomer)) {
            $verificationCode = $request->input('verification_code')
                ?? $request->input('code')
                ?? $request->input('otp');

            if (! empty($verificationCode)) {
                $verResult = $verificationService->verifyCode($authCustomer, (string) $verificationCode);
                if (! $verResult['success']) {
                    return response()->json([
                        'success' => false,
                        'verification_required' => true,
                        'verification_failed' => true,
                        'message' => $verResult['message'],
                    ], 422);
                }
            } else {
                // Code not provided yet — send verification code across active gateways directly!
                $sendResult = $verificationService->sendVerificationCode($company, $authCustomer);

                return response()->json([
                    'success' => false,
                    'verification_required' => true,
                    'message' => $sendResult['message'] ?? __('Account verification required before placing your order. A 6-digit verification code has been sent directly to your email / phone.'),
                    'channels' => $sendResult['channels'] ?? [],
                    'customer_id' => $authCustomer->id,
                    'customer_email' => $authCustomer->email,
                    'customer_phone' => $authCustomer->phone,
                ], 200);
            }
        }

        // Authoritative Coupon Validation and Application
        $appliedCoupon = null;
        if (! empty($couponCode)) {
            $coupon = Coupon::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('code', strtoupper(trim($couponCode)))
                ->first();

            if ($coupon) {
                $cPhone = $authCustomer?->phone ?? $validated['customer_phone'] ?? null;
                $cEmail = $authCustomer?->email ?? $customerEmail ?? null;
                $valResult = $coupon->validateForOrder($subtotal, $customerId, $cPhone, $cEmail);
                if ($valResult['valid']) {
                    $discount = (float) $valResult['discount_amount'];
                    $netAmount = max(0, $subtotal - $discount);
                    $appliedCoupon = $coupon;
                }
            }
        }

        $trackingCode = 'TRK-'.strtoupper(Str::random(8));

        $sale = Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'sale_number' => $saleNumber,
            'tracking_code' => $trackingCode,
            'customer_id' => $customerId,
            'customer_name' => $validated['customer_name'],
            'delivery_address' => $fullAddress ?: null,
            'payment_method' => $paymentMethod,
            'notes' => $notes,
            'total' => $subtotal,
            'net_amount' => $netAmount,
            'due_amount' => $isPrepaid ? 0 : $netAmount,
            'paid_amount' => $isPrepaid ? $netAmount : 0,
            'discount' => $discount,
            'status' => 'pending',
            'operation_type' => 'sale',
            'service_type' => 'storefront',
            'items' => $formattedItems,
        ]);

        if ($appliedCoupon) {
            CouponUsage::create([
                'coupon_id' => $appliedCoupon->id,
                'company_id' => $company->id,
                'customer_id' => $customerId,
                'customer_email' => $authCustomer?->email ?? $customerEmail,
                'customer_phone' => $authCustomer?->phone ?? $validated['customer_phone'],
                'sale_id' => $sale->id,
                'discount_amount' => $discount,
            ]);
            $appliedCoupon->increment('used_count');
        }

        AuditLog::record('storefront.order_placed', $company->id, null, [
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'total' => $subtotal,
            'customer' => $validated['customer_name'],
            'payment_method' => $paymentMethod,
            'discount' => $discount,
        ]);

        app(FinancialAnalyticsService::class)->clearCache($company->id);

        // Dispatch order confirmation notification across enabled gateways per tenant choice
        try {
            app(\App\Services\Notifications\StorefrontOrderNotificationService::class)->notifyOrderPlaced($sale);
        } catch (\Throwable $e) {
            Log::warning("Order placed notification dispatch exception: " . $e->getMessage());
        }

        $gatewayData = null;
        if (in_array($paymentMethod, ['razorpay', 'stripe', 'paypal', 'upi'], true)) {
            try {
                $gatewayData = app(StorefrontPaymentService::class)->initiatePayment($sale, $company, $paymentMethod);
            } catch (\Throwable $e) {
                Log::warning("Storefront gateway payment initiation failed: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Your order has been received by the store!',
            'sale_number' => $sale->sale_number,
            'tracking_code' => $sale->tracking_code,
            'tracking_url' => url('/store/track/'.$sale->tracking_code.'?store='.$company->slug),
            'order_id' => $sale->id,
            'total' => $subtotal,
            'discount' => $discount,
            'net_amount' => $netAmount,
            'payment_method' => $paymentMethod,
            'gateway' => $gatewayData,
            'gateway_data' => $gatewayData,
        ]);
    }

    /**
     * Send or resend customer account verification code.
     * POST /store/auth/send-verification
     */
    public function sendVerification(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');

        $customerId = $request->input('customer_id');
        $phone = $request->input('phone') ?: $request->input('customer_phone');
        $email = $request->input('email') ?: $request->input('customer_email');
        $token = $request->input('auth_token') ?? $request->header('X-Customer-Token') ?? $request->bearerToken();

        $customer = null;
        if ($customerId) {
            $customer = Customer::withoutGlobalScopes()->where('company_id', $company->id)->where('id', $customerId)->first();
        }
        if (! $customer && $token) {
            $customer = Customer::withoutGlobalScopes()->where('company_id', $company->id)->where('auth_token', $token)->first();
        }
        if (! $customer && ! empty($phone)) {
            $customer = Customer::withoutGlobalScopes()->where('company_id', $company->id)->where('phone', $phone)->first();
        }
        if (! $customer && ! empty($email)) {
            $customer = Customer::withoutGlobalScopes()->where('company_id', $company->id)->where('email', $email)->first();
        }

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('Customer account not found. Please enter your contact details.'),
            ], 404);
        }

        $res = app(\App\Services\Auth\CustomerVerificationService::class)->sendVerificationCode($company, $customer);

        return response()->json($res);
    }

    /**
     * Verify customer OTP code.
     * POST /store/auth/verify-code
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');

        $code = (string) ($request->input('code') ?: $request->input('verification_code') ?: $request->input('otp'));
        $customerId = $request->input('customer_id');
        $phone = $request->input('phone') ?: $request->input('customer_phone');
        $email = $request->input('email') ?: $request->input('customer_email');
        $token = $request->input('auth_token') ?? $request->header('X-Customer-Token') ?? $request->bearerToken();

        $customer = null;
        if ($customerId) {
            $customer = Customer::withoutGlobalScopes()->where('company_id', $company->id)->where('id', $customerId)->first();
        }
        if (! $customer && $token) {
            $customer = Customer::withoutGlobalScopes()->where('company_id', $company->id)->where('auth_token', $token)->first();
        }
        if (! $customer && ! empty($phone)) {
            $customer = Customer::withoutGlobalScopes()->where('company_id', $company->id)->where('phone', $phone)->first();
        }
        if (! $customer && ! empty($email)) {
            $customer = Customer::withoutGlobalScopes()->where('company_id', $company->id)->where('email', $email)->first();
        }

        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => __('Customer account not found.'),
            ], 404);
        }

        $res = app(\App\Services\Auth\CustomerVerificationService::class)->verifyCode($customer, $code);

        return response()->json($res, $res['success'] ? 200 : 422);
    }

    /**
     * Validate coupon code for active cart.
     * POST /api/v1/storefront/coupons/validate
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');

        $code = strtoupper(trim((string) $request->input('code', '')));
        $subtotal = (float) $request->input('subtotal', 0);

        if (empty($code)) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => __('Please enter a coupon code.'),
                'discount_amount' => 0.0,
            ], 422);
        }

        $coupon = Coupon::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where(function ($q) use ($code) {
                $q->where('code', $code)
                  ->orWhereRaw('UPPER(TRIM(code)) = ?', [$code]);
            })
            ->first();

        if (! $coupon) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => __('Invalid coupon code. Please check and try again.'),
                'discount_amount' => 0.0,
            ], 422);
        }

        $customerId = $request->input('customer_id');
        $phone = $request->input('phone') ?: $request->input('customer_phone');
        $email = $request->input('email') ?: $request->input('customer_email');

        // Check if customer auth token passed
        $token = $request->input('auth_token') ?? $request->header('X-Customer-Token') ?? $request->bearerToken();
        if ($token && ! $customerId) {
            $cust = Customer::withoutGlobalScopes()->where('company_id', $company->id)->where('auth_token', $token)->first();
            if ($cust) {
                $customerId = $cust->id;
                $phone = $phone ?: $cust->phone;
                $email = $email ?: $cust->email;
            }
        }

        $result = $coupon->validateForOrder($subtotal, $customerId, $phone, $email);

        if (! $result['valid']) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => $result['message'],
                'discount_amount' => 0.0,
            ], 422);
        }

        $discount = (float) $result['discount_amount'];

        return response()->json([
            'success' => true,
            'valid' => true,
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'discount_amount' => $discount,
            'subtotal' => $subtotal,
            'final_total' => max(0, round($subtotal - $discount, 2)),
            'message' => $result['message'],
        ]);
    }

    /**
     * Return tenant's active storefront payment methods.
     * GET /api/v1/storefront/payment-methods
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');

        return response()->json([
            'success' => true,
            'enabled_methods' => $company->getStorefrontPaymentMethods(),
        ]);
    }

    /**
     * Dedicated Customer Account Portal page.
     * GET /store/account
     */
    public function account(Request $request): View
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Storefront not found.');
        app()->instance('tenant.company_id', $company->id);

        $categories = Category::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->withCount(['products' => function ($q) use ($company) {
                $q->withoutGlobalScopes()->where('company_id', $company->id)->where('active', true);
            }])
            ->get();

        $products = Product::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('id', 'desc')
            ->get();

        $languages = Language::query()->where('is_active', true)->orderBy('name')->get();

        return view('tenants.store.account', [
            'company' => $company,
            'categories' => $categories,
            'products' => $products,
            'languages' => $languages,
        ]);
    }

    /**
     * Order Tracking View with real-time status progression.
     * GET /store/track/{code}
     */
    public function trackOrder(Request $request, string $code): View
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Storefront not found.');
        app()->instance('tenant.company_id', $company->id);

        $sale = Sale::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where(function ($q) use ($code) {
                $q->where('tracking_code', $code)
                    ->orWhere('sale_number', $code);
            })
            ->first();

        abort_if(! $sale, 404, 'Order tracking details not found.');

        $categories = Category::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->withCount(['products' => function ($q) use ($company) {
                $q->withoutGlobalScopes()->where('company_id', $company->id)->where('active', true);
            }])
            ->get();

        $products = Product::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('id', 'desc')
            ->get();

        $languages = Language::query()->where('is_active', true)->orderBy('name')->get();

        return view('tenants.store.track', [
            'company' => $company,
            'sale' => $sale,
            'categories' => $categories,
            'products' => $products,
            'languages' => $languages,
        ]);
    }

    public function apiCatalog(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $categories = Category::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->withCount(['products' => function ($q) use ($company) {
                $q->withoutGlobalScopes()->where('company_id', $company->id)->where('active', true);
            }])
            ->get();

        $products = Product::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('id', 'desc')
            ->get();

        $faqs = Faq::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->active()
            ->ordered()
            ->get();

        if ($faqs->isEmpty()) {
            Faq::seedDefaultsForCompany($company->id);
            $faqs = Faq::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->active()
                ->ordered()
                ->get();
        }

        return response()->json([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'logo' => $company->logo ? (str_starts_with($company->logo, 'http') ? $company->logo : asset($company->logo)) : null,
                'phone' => $company->phone,
                'email' => $company->email,
                'address' => $company->address,
                'city' => $company->city,
                'currency' => $company->currency ?? 'USD',
                'banner' => $company->getStoreBanner(),
                'enable_google_login' => (bool) $company->enable_google_login,
            ],
            'enabled_methods' => $company->getStorefrontPaymentMethods(),
            'categories' => $categories,
            'products' => $products,
            'faqs' => $faqs,
        ]);
    }

    public function apiFaqs(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['error' => 'Store not found'], 404);
        }

        $faqs = Faq::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->active()
            ->ordered()
            ->get();

        if ($faqs->isEmpty()) {
            Faq::seedDefaultsForCompany($company->id);
            $faqs = Faq::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->active()
                ->ordered()
                ->get();
        }

        $categories = collect(['all'])
            ->merge($faqs->pluck('category')->filter()->unique())
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'company_id' => $company->id,
            'categories' => $categories,
            'faqs' => $faqs,
        ]);
    }

    public function faqsPage(Request $request): View
    {
        $request->merge(['open_faqs' => 1]);
        return $this->index($request);
    }

    /**
     * Initiate payment for an online order using enabled gateway (Razorpay / Stripe).
     * POST /store/payment/initiate
     * POST /api/v1/storefront/payment/initiate
     */
    public function initiateGatewayPayment(Request $request, StorefrontPaymentService $paymentService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');

        $request->validate([
            'order_id' => ['nullable'],
            'sale_number' => ['nullable'],
            'tracking_code' => ['nullable'],
            'payment_method' => ['required', 'string'],
        ]);

        $gateway = strtolower($request->input('payment_method'));

        $query = Sale::withoutGlobalScopes()->where('company_id', $company->id);
        if ($request->filled('order_id')) {
            $query->where('id', $request->input('order_id'));
        } elseif ($request->filled('sale_number')) {
            $query->where('sale_number', $request->input('sale_number'));
        } elseif ($request->filled('tracking_code')) {
            $query->where('tracking_code', $request->input('tracking_code'));
        } else {
            return response()->json(['error' => 'Order reference required.'], 422);
        }

        $sale = $query->first();
        if (! $sale) {
            return response()->json(['error' => 'Sale not found.'], 404);
        }

        $result = $paymentService->initiatePayment($sale, $company, $gateway);

        return response()->json($result);
    }

    /**
     * Verify online payment and update order status to paid.
     * POST /store/payment/verify
     * POST /api/v1/storefront/payment/verify
     */
    public function verifyGatewayPayment(Request $request, StorefrontPaymentService $paymentService): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');

        // Normalize aliases
        if (! $request->has('payment_method') && $request->has('gateway')) {
            $request->merge(['payment_method' => $request->input('gateway')]);
        }
        if (! $request->has('order_id') && $request->has('sale_id')) {
            $request->merge(['order_id' => $request->input('sale_id')]);
        }

        $request->validate([
            'order_id' => ['nullable'],
            'sale_number' => ['nullable'],
            'payment_method' => ['required', 'string'],
        ]);

        $gateway = strtolower($request->input('payment_method'));

        $query = Sale::withoutGlobalScopes()->where('company_id', $company->id);
        if ($request->filled('order_id')) {
            $query->where('id', $request->input('order_id'));
        } elseif ($request->filled('sale_number')) {
            $query->where('sale_number', $request->input('sale_number'));
        } else {
            return response()->json(['error' => 'Order reference required.'], 422);
        }

        $sale = $query->first();
        if (! $sale) {
            return response()->json(['error' => 'Sale not found.'], 404);
        }

        try {
            $result = $paymentService->verifyAndCapture($sale, $company, $gateway, $request->all());
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
