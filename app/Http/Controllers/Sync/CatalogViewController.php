<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Models\PublishedCatalog;
use App\Models\Sale;
use App\Services\Stores\StoreContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mirrors legacy api/catalog_view.php (public "/c/{32-hex-id}" share links).
 * No auth required by design — that's the whole point of the feature. The
 * view itself is rendered inside a sandboxed iframe with no
 * allow-same-origin, so a shared link can never read cookies/session
 * tokens for this domain even though it's served from it.
 */
class CatalogViewController extends Controller
{
    public function show(string $id): View
    {
        $catalog = PublishedCatalog::withoutGlobalScope('company')->find($id);

        abort_if(! $catalog || $catalog->isExpired(), 404, 'This catalog link is not available.');

        $company = $catalog->company ?? Company::find($catalog->company_id);

        $products = Product::withoutGlobalScope('company')
            ->where('company_id', $catalog->company_id)
            ->whereIn('id', $catalog->product_ids)
            ->where('active', true)
            ->get();

        $categories = \App\Models\Category::withoutGlobalScopes()
            ->where('company_id', $catalog->company_id)
            ->withCount(['products' => function ($q) use ($catalog) {
                $q->withoutGlobalScopes()->where('company_id', $catalog->company_id)->whereIn('id', $catalog->product_ids)->where('active', true);
            }])
            ->get();

        $languages = \App\Models\Language::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('tenants.store.index', [
            'catalog' => $catalog,
            'products' => $products,
            'categories' => $categories,
            'company' => $company,
            'languages' => $languages,
        ]);
    }

    /**
     * Submit an order directly from the public digital storefront into tenant POS.
     */
    public function placeOrder(Request $request, string $id): JsonResponse
    {
        $catalog = PublishedCatalog::withoutGlobalScope('company')->find($id);
        abort_if(! $catalog || $catalog->isExpired(), 404, 'This catalog is not available.');

        $company = $catalog->company ?? Company::find($catalog->company_id);
        abort_if(! $company, 404, 'Store tenant not found.');
        $primaryStore = app(StoreContext::class)->ensurePrimary($company);

        // Normalize field aliases before validation
        if (! $request->has('delivery_address') && $request->has('address')) {
            $request->merge(['delivery_address' => $request->input('address')]);
        }
        if (! $request->has('customer_email') && $request->has('email')) {
            $request->merge(['customer_email' => $request->input('email')]);
        }

        $validated = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'customer_notes' => ['nullable', 'string', 'max:500'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['nullable', 'string', 'max:50'],
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
            ];
        }

        $saleCount = Sale::withoutGlobalScopes()->where('company_id', $company->id)->count();
        $saleNumber = 'WEB-'.strtoupper(substr($catalog->id, 0, 4)).'-'.sprintf('%04d', $saleCount + 1);

        $addressParts = array_filter([$deliveryAddress, $validated['city'] ?? null]);
        $fullAddress = implode(', ', $addressParts);

        $customerId = null;
        if (! empty($validated['customer_phone'])) {
            $fallbackEmail = $customerEmail ?: ($validated['customer_phone'].'@guest.zoomnearby.com');
            $customer = \App\Models\Customer::withoutGlobalScopes()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'phone' => $validated['customer_phone'],
                ],
                [
                    'name' => $validated['customer_name'] ?: 'Online Storefront Guest',
                    'email' => $fallbackEmail,
                    'address' => $deliveryAddress,
                    'city' => $validated['city'] ?? null,
                    'source' => 'storefront',
                ]
            );
            $customerId = $customer->id;
        }

        $sale = Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $primaryStore->id,
            'sale_number' => $saleNumber,
            'customer_id' => $customerId,
            'customer_name' => $validated['customer_name'] ?: 'Online Storefront Guest',
            'delivery_address' => $fullAddress ?: null,
            'notes' => $validated['customer_notes'] ?? ('Order placed from online storefront: '.$catalog->title),
            'total' => $subtotal,
            'discount' => 0,
            'payment_method' => $validated['payment_method'] ?? 'cod',
            'status' => 'pending',
            'operation_type' => 'sale',
            'service_type' => 'storefront',
            'items' => $formattedItems,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your order has been placed and received by the store!',
            'sale_number' => $sale->sale_number,
            'order_id' => $sale->id,
        ]);
    }
}
