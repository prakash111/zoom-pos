<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerWishlist;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StorefrontCustomerApiController extends Controller
{
    /**
     * Resolve the tenant company for storefront requests.
     */
    public function resolveCompany(Request $request): ?Company
    {
        if (app()->bound('tenant.company_id')) {
            $company = Company::withoutGlobalScopes()->find(app('tenant.company_id'));
            if ($company) {
                return $company;
            }
        }

        $headerCompanyId = $request->header('X-Company-ID')
            ?? $request->header('X-Tenant-ID')
            ?? $request->header('X-Store-ID');

        if ($headerCompanyId) {
            $company = Company::withoutGlobalScopes()->where('id', $headerCompanyId)->orWhere('slug', $headerCompanyId)->first();
            if ($company) {
                return $company;
            }
        }

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

        $storeParam = $request->query('store') ?? $request->input('store') ?? $request->query('company_id') ?? $request->input('company_id');
        if (! empty($storeParam)) {
            $company = Company::withoutGlobalScopes()->where('slug', $storeParam)->orWhere('id', $storeParam)->first();
            if ($company) {
                return $company;
            }
        }

        return Company::withoutGlobalScopes()->first();
    }

    /**
     * Resolve the authenticated storefront customer.
     */
    protected function resolveCustomer(Request $request, Company $company): ?Customer
    {
        $token = $request->bearerToken()
            ?? $request->header('X-Customer-Token')
            ?? $request->input('auth_token')
            ?? $request->input('customer_token');

        if (! $token) {
            return null;
        }

        return Customer::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('auth_token', $token)
            ->first();
    }

    /**
     * Register a new customer on the tenant storefront.
     * POST /api/storefront/customer/register
     */
    public function register(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:6'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->input('email');
        $phone = $request->input('phone');

        if (empty($email) && empty($phone)) {
            return response()->json([
                'success' => false,
                'message' => 'Either email or phone number is required.',
            ], 422);
        }

        // Check uniqueness per tenant
        $existsQuery = Customer::withoutGlobalScope('company')->where('company_id', $company->id);
        if (! empty($email)) {
            $existing = (clone $existsQuery)->where('email', $email)->first();
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'An account with this email address already exists in this store. Please log in.',
                ], 422);
            }
        }
        if (! empty($phone)) {
            $existing = (clone $existsQuery)->where('phone', $phone)->first();
            if ($existing && ! empty($existing->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'An account with this phone number already exists in this store. Please log in.',
                ], 422);
            }
        }

        $token = Str::random(64);

        $customer = Customer::withoutGlobalScope('company')->create([
            'company_id' => $company->id,
            'name' => $request->input('name'),
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make($request->input('password')),
            'auth_token' => $token,
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'source' => 'storefront',
        ]);

        // Create default shipping address if address provided
        if (! empty($request->input('address'))) {
            CustomerAddress::withoutGlobalScope('company')->create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'type' => 'shipping',
                'street_address' => $request->input('address'),
                'city' => $request->input('city'),
                'state' => $request->input('state'),
                'postal_code' => $request->input('postal_code'),
                'is_default' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'token' => $token,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'city' => $customer->city,
                'state' => $customer->state,
            ],
        ], 201);
    }

    /**
     * Customer login on the tenant storefront.
     * POST /api/storefront/customer/login
     */
    public function login(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'email' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'login' => ['nullable', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $identifier = $request->input('login')
            ?? $request->input('email')
            ?? $request->input('phone');

        if (empty($identifier)) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide an email or phone number to log in.',
            ], 422);
        }

        $customer = Customer::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($identifier) {
                $q->where('email', $identifier)
                    ->orWhere('phone', $identifier);
            })
            ->first();

        if (! $customer || ! $customer->password || ! Hash::check($request->input('password'), $customer->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials. Please check your email/phone and password.',
            ], 401);
        }

        $token = Str::random(64);
        $customer->auth_token = $token;
        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully.',
            'token' => $token,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'city' => $customer->city,
                'state' => $customer->state,
            ],
        ]);
    }

    /**
     * Customer logout.
     * POST /api/storefront/customer/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($company) {
            $customer = $this->resolveCustomer($request, $company);
            if ($customer) {
                $customer->auth_token = null;
                $customer->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Customer Profile details.
     * GET /api/storefront/customer/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 404);
        }

        $customer = $this->resolveCustomer($request, $company);
        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $addresses = CustomerAddress::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('is_default')
            ->get();

        $wishlistCount = CustomerWishlist::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->count();

        $ordersCount = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->count();

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'city' => $customer->city,
                'state' => $customer->state,
                'gender' => $customer->gender ?: 'Male',
                'date_of_birth' => $customer->date_of_birth instanceof \DateTimeInterface
                    ? $customer->date_of_birth->format('Y-m-d')
                    : ($customer->date_of_birth ?: ($customer->custom_fields['dob'] ?? null)),
                'avatar_url' => $customer->avatar_url ?: ($customer->custom_fields['avatar_url'] ?? null),
                'due_balance' => (float) $customer->due_balance,
                'loyalty_points' => (int) $customer->loyalty_points,
            ],
            'addresses' => $addresses,
            'wishlist_count' => $wishlistCount,
            'orders_count' => $ordersCount,
        ]);
    }

    /**
     * Update Customer Profile.
     * PUT /api/storefront/customer/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 404);
        }

        $customer = $this->resolveCustomer($request, $company);
        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'string', 'max:30'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
            'current_password' => ['nullable', 'string'],
            'new_password' => ['nullable', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->filled('new_password')) {
            if (! $customer->password || ! Hash::check($request->input('current_password'), $customer->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password does not match.',
                ], 422);
            }
            $customer->password = Hash::make($request->input('new_password'));
        }

        if ($request->has('name')) {
            $customer->name = $request->input('name');
        }
        if ($request->has('phone')) {
            $customer->phone = $request->input('phone');
        }
        if ($request->has('address')) {
            $customer->address = $request->input('address');
        }
        if ($request->has('city')) {
            $customer->city = $request->input('city');
        }
        if ($request->has('state')) {
            $customer->state = $request->input('state');
        }
        if ($request->has('gender')) {
            $customer->gender = $request->input('gender');
        }
        if ($request->filled('date_of_birth')) {
            try {
                $rawDob = $request->input('date_of_birth');
                $parsedDob = \Carbon\Carbon::parse(str_replace('/', '-', $rawDob))->format('Y-m-d');
                $customer->date_of_birth = $parsedDob;
            } catch (\Throwable $e) {
                $cf = (array) ($customer->custom_fields ?? []);
                $cf['dob'] = $request->input('date_of_birth');
                $customer->custom_fields = $cf;
            }
        }
        if ($request->has('avatar_url')) {
            $customer->avatar_url = $request->input('avatar_url');
        }

        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'city' => $customer->city,
                'state' => $customer->state,
                'gender' => $customer->gender,
                'date_of_birth' => $customer->date_of_birth instanceof \DateTimeInterface
                    ? $customer->date_of_birth->format('Y-m-d')
                    : ($customer->date_of_birth ?: ($customer->custom_fields['dob'] ?? null)),
                'avatar_url' => $customer->avatar_url,
            ],
        ]);
    }

    /**
     * Get list of addresses for the customer.
     * GET /api/storefront/customer/addresses
     * GET /api/addresses
     */
    public function addresses(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 404);
        }

        $customer = $this->resolveCustomer($request, $company);
        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $addresses = CustomerAddress::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'addresses' => $addresses,
        ]);
    }

    /**
     * Store new shipping address.
     * POST /api/storefront/customer/addresses
     * POST /api/addresses
     */
    public function storeAddress(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 404);
        }

        $customer = $this->resolveCustomer($request, $company);
        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
            'street_address' => ['required', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $isDefault = (bool) $request->input('is_default', false);
        if ($isDefault) {
            CustomerAddress::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('customer_id', $customer->id)
                ->update(['is_default' => false]);
        }

        $address = CustomerAddress::withoutGlobalScope('company')->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'name' => $request->input('name') ?: $customer->name,
            'phone' => $request->input('phone') ?: $customer->phone,
            'type' => $request->input('type', 'home'),
            'street_address' => $request->input('street_address'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'postal_code' => $request->input('postal_code'),
            'country' => $request->input('country', 'India'),
            'is_default' => $isDefault,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Address saved successfully.',
            'address' => $address,
        ], 201);
    }

    /**
     * Update existing address.
     * PUT /api/storefront/customer/addresses/{id}
     * PUT /api/addresses/{id}
     */
    public function updateAddress(Request $request, int $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 404);
        }

        $customer = $this->resolveCustomer($request, $company);
        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $address = CustomerAddress::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->find($id);

        if (! $address) {
            return response()->json(['success' => false, 'message' => 'Address not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
            'street_address' => ['sometimes', 'required', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->boolean('is_default')) {
            CustomerAddress::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('customer_id', $customer->id)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
            $address->is_default = true;
        }

        $address->fill($validator->validated());
        $address->save();

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully.',
            'address' => $address,
        ]);
    }

    /**
     * Delete an address.
     * DELETE /api/storefront/customer/addresses/{id}
     * DELETE /api/addresses/{id}
     */
    public function deleteAddress(Request $request, int $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 404);
        }

        $customer = $this->resolveCustomer($request, $company);
        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $address = CustomerAddress::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->find($id);

        if (! $address) {
            return response()->json(['success' => false, 'message' => 'Address not found.'], 404);
        }

        $address->delete();

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully.',
        ]);
    }

    /**
     * Get Customer Wishlist.
     * GET /api/storefront/customer/wishlist
     * GET /api/wishlist
     */
    public function wishlist(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 404);
        }

        $customer = $this->resolveCustomer($request, $company);
        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $items = CustomerWishlist::withoutGlobalScope('company')
            ->where('customer_wishlists.company_id', $company->id)
            ->where('customer_wishlists.customer_id', $customer->id)
            ->join('products', 'products.id', '=', 'customer_wishlists.product_id')
            ->where('products.active', true)
            ->select('products.*', 'customer_wishlists.created_at as added_at')
            ->orderByDesc('customer_wishlists.created_at')
            ->get();

        return response()->json([
            'success' => true,
            'wishlist' => $items,
        ]);
    }

    /**
     * Toggle product in Customer Wishlist.
     * POST /api/storefront/customer/wishlist/toggle
     * POST /api/wishlist/toggle
     */
    public function toggleWishlist(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 404);
        }

        $customer = $this->resolveCustomer($request, $company);
        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $productId = (int) ($request->input('product_id') ?? $request->input('id'));
        if (! $productId) {
            return response()->json(['success' => false, 'message' => 'Product ID is required.'], 422);
        }

        $product = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->find($productId);

        if (! $product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $existing = CustomerWishlist::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            $existing->delete();
            $inWishlist = false;
            $message = 'Removed from wishlist.';
        } else {
            CustomerWishlist::withoutGlobalScope('company')->create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'product_id' => $productId,
            ]);
            $inWishlist = true;
            $message = 'Added to wishlist.';
        }

        $totalCount = CustomerWishlist::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->count();

        return response()->json([
            'success' => true,
            'message' => $message,
            'in_wishlist' => $inWishlist,
            'product_id' => $productId,
            'wishlist_count' => $totalCount,
        ]);
    }

    /**
     * Remove item from Wishlist.
     * DELETE /api/storefront/customer/wishlist/{productId}
     * DELETE /api/wishlist/{productId}
     */
    public function removeWishlist(Request $request, int $productId): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 404);
        }

        $customer = $this->resolveCustomer($request, $company);
        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        CustomerWishlist::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->where('product_id', $productId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from wishlist.',
        ]);
    }

    /**
     * Authoritative Server-Side Cart Calculation.
     * Ensures client-side price manipulation is impossible.
     * POST /api/storefront/cart/calculate
     */
    public function calculateCart(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide valid cart items.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $rawItems = $request->input('items', []);
        $productIds = collect($rawItems)->pluck('product_id')->unique()->all();

        $products = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->whereIn('id', $productIds)
            ->where('active', true)
            ->get()
            ->keyBy('id');

        $computedItems = [];
        $subtotal = 0.0;
        $totalTax = 0.0;

        foreach ($rawItems as $item) {
            $pId = (int) $item['product_id'];
            $product = $products->get($pId);

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => "Product #{$pId} is no longer available in this store.",
                ], 422);
            }

            $qty = (float) $item['quantity'];
            $unitPrice = (float) ($product->sale_price ?: ($product->price ?: 0));
            $lineNet = round($unitPrice * $qty, 2);

            $taxRate = (float) ($product->tax_rate ?? 0);
            $lineTax = round(($lineNet * $taxRate) / 100, 2);

            $subtotal += $lineNet;
            $totalTax += $lineTax;

            $computedItems[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'unit_price' => $unitPrice,
                'quantity' => $qty,
                'line_net' => $lineNet,
                'tax_rate' => $taxRate,
                'tax_amount' => $lineTax,
                'line_total' => round($lineNet + $lineTax, 2),
            ];
        }

        $shippingFee = 0.0;
        $discountAmount = 0.0;
        $grandTotal = max(0, round($subtotal + $totalTax + $shippingFee - $discountAmount, 2));

        return response()->json([
            'success' => true,
            'items' => $computedItems,
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($totalTax, 2),
            'shipping_fee' => round($shippingFee, 2),
            'discount_amount' => round($discountAmount, 2),
            'grand_total' => $grandTotal,
            'currency' => $company->currency ?: 'INR',
            'currency_symbol' => $company->currency_symbol ?: '₹',
        ]);
    }

    /**
     * Customer Order History.
     * GET /api/storefront/customer/orders
     */
    public function orders(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Store not found.'], 404);
        }

        $customer = $this->resolveCustomer($request, $company);
        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $orders = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'orders' => $orders,
        ]);
    }
}
