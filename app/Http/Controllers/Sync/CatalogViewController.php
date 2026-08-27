<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Models\PublishedCatalog;
use Illuminate\Contracts\View\View;

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
}
