<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Models\PublishedCatalog;
use App\Models\Sale;
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

        return view('catalog.public', [
            'catalog' => $catalog,
            'products' => $products,
            'company' => $company,
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

        $validated = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_notes' => ['nullable', 'string', 'max:500'],
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
            ];
        }

        $saleCount = Sale::withoutGlobalScopes()->where('company_id', $company->id)->count();
        $saleNumber = 'WEB-'.strtoupper(substr($catalog->id, 0, 4)).'-'.sprintf('%04d', $saleCount + 1);

        $sale = Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'sale_number' => $saleNumber,
            'customer_name' => $validated['customer_name'] ?: 'Online Storefront Guest',
            'customer_phone' => $validated['customer_phone'] ?? null,
            'notes' => $validated['customer_notes'] ?? ('Order placed from online storefront: '.$catalog->title),
            'total' => $subtotal,
            'discount' => 0,
            'payment_method' => 'unpaid',
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
