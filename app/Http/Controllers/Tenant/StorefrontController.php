<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Company;
use App\Models\Language;
use App\Models\Product;
use App\Models\Sale;
use App\Services\FinancialAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        // Fallback: check query parameter or default active company
        if ($request->filled('store')) {
            $company = Company::withoutGlobalScopes()->where('slug', $request->query('store'))->first();
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

        return view('tenants.store.index', [
            'company' => $company,
            'categories' => $categories,
            'products' => $products,
            'languages' => $languages,
            'catalog' => null,
        ]);
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store tenant not found.');

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['nullable', 'string', 'in:cod,counter,online,card,unpaid'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable'],
            'items.*.name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

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
            $validated['address'] ?? null,
            $validated['city'] ?? null,
            $validated['postal_code'] ?? null,
        ]);
        $fullAddress = implode(', ', $deliveryAddressParts);

        $paymentMethod = $validated['payment_method'] ?? 'cod';

        $notes = trim(($validated['customer_notes'] ?? '').' | Phone: '.$validated['customer_phone'].($validated['customer_email'] ? ' | Email: '.$validated['customer_email'] : ''));

        $customerId = null;
        if (! empty($validated['customer_phone'])) {
            $customer = \App\Models\Customer::withoutGlobalScopes()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'phone' => $validated['customer_phone'],
                ],
                [
                    'name' => $validated['customer_name'] ?: 'Online Customer',
                    'email' => $validated['customer_email'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'city' => $validated['city'] ?? null,
                    'source' => 'storefront',
                ]
            );
            $customerId = $customer->id;
        }

        $sale = Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'sale_number' => $saleNumber,
            'customer_id' => $customerId,
            'customer_name' => $validated['customer_name'],
            'delivery_address' => $fullAddress ?: null,
            'payment_method' => $paymentMethod,
            'notes' => $notes,
            'total' => $subtotal,
            'net_amount' => $subtotal,
            'due_amount' => $paymentMethod === 'online' ? 0 : $subtotal,
            'paid_amount' => $paymentMethod === 'online' ? $subtotal : 0,
            'discount' => 0,
            'status' => 'pending',
            'operation_type' => 'sale',
            'service_type' => 'storefront',
            'items' => $formattedItems,
        ]);

        AuditLog::record('storefront.order_placed', $company->id, null, [
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'total' => $subtotal,
            'customer' => $validated['customer_name'],
            'payment_method' => $paymentMethod,
        ]);

        app(FinancialAnalyticsService::class)->clearCache($company->id);

        return response()->json([
            'success' => true,
            'message' => 'Your order has been received by the store!',
            'sale_number' => $sale->sale_number,
            'order_id' => $sale->id,
            'total' => $subtotal,
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
            ],
            'categories' => $categories,
            'products' => $products,
        ]);
    }
}
