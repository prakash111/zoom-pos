<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\PublishedCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Mirrors app/Livewire/Tenant/Catalog/Index.php's publish/revoke exactly —
 * a PublishedCatalog is a snapshot of product ids with an optional
 * expiry, viewable at the public GET /c/{id} route (CatalogViewController).
 */
class CatalogApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $catalogs = PublishedCatalog::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PublishedCatalog $c) => $this->present($c));

        $products = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sale_price'])
            ->map(fn (Product $p) => ['id' => (string) $p->id, 'name' => $p->name, 'sale_price' => (float) $p->sale_price]);

        return response()->json(['success' => true, 'catalogs' => $catalogs, 'products' => $products]);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'product_ids' => ['required', 'array', 'min:1'],
            'ttl_days' => ['required', 'integer', 'in:0,1,7,30'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $catalog = PublishedCatalog::create([
            'company_id' => $company->id,
            'title' => $data['title'],
            'product_ids' => array_values($data['product_ids']),
            'expires_at' => $data['ttl_days'] ? now()->addDays($data['ttl_days']) : null,
        ]);

        AuditLog::record('catalog.published', $company->id, $user?->id, ['catalog_id' => $catalog->id]);

        return response()->json([
            'success' => true,
            'message' => 'Catalog published.',
            'catalog' => $this->present($catalog),
        ], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $catalog = PublishedCatalog::withoutGlobalScope('company')->where('company_id', $company->id)->where('id', $id)->first();
        if (! $catalog) {
            return response()->json(['success' => false, 'error' => 'Catalog link not found.'], 404);
        }

        $catalog->delete();
        AuditLog::record('catalog.revoked', $company->id, $user?->id, ['catalog_id' => $id]);

        return response()->json(['success' => true, 'message' => 'Catalog link revoked.']);
    }

    private function present(PublishedCatalog $c): array
    {
        return [
            'id' => (string) $c->id,
            'title' => $c->title,
            'product_ids' => $c->product_ids ?? [],
            'product_count' => count($c->product_ids ?? []),
            'expires_at' => $c->expires_at?->toIso8601String(),
            'is_expired' => $c->expires_at !== null && $c->expires_at->isPast(),
            'url' => route('catalog.show', $c->id),
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
