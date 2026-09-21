<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Company;
use App\Models\TenantCustomPage;
use App\Models\TenantStoreMenu;
use App\Services\Subscription\SubscriptionEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StorefrontMenuController extends Controller
{
    protected function resolveCompany(Request $request): ?Company
    {
        if (app()->bound('tenant.company_id')) {
            $company = Company::withoutGlobalScopes()->find(app('tenant.company_id'));
            if ($company) {
                return $company;
            }
        }

        if ($request->user()?->company_id) {
            $company = Company::withoutGlobalScopes()->find($request->user()->company_id);
            if ($company) {
                return $company;
            }
        }

        $host = strtolower($request->getHost());
        $baseHost = parse_url(config('app.url'), PHP_URL_HOST);

        if ($baseHost && str_ends_with($host, '.' . $baseHost)) {
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

    protected function checkEntitlement(Company $company): void
    {
        $entitlementService = app(SubscriptionEntitlementService::class);
        if (! $entitlementService->tenantCanUseExtension($company, 'ecommerce_storefront')) {
            abort(403, 'This extension is not activated for your store. Upgrade your subscription plan to access this feature.');
        }
    }

    // =========================================================================
    // CMS CUSTOM PAGES CRUD
    // =========================================================================

    /**
     * List all custom CMS pages for the tenant.
     * GET /api/v1/tenant/storefront/pages
     * GET /tenant/storefront/pages
     */
    public function pagesIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');

        TenantCustomPage::seedDefaultsForCompany($company->id);

        $query = TenantCustomPage::withoutGlobalScopes()
            ->where('company_id', $company->id);

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->query('search')) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                  ->orWhere('slug', 'like', $term)
                  ->orWhere('content', 'like', $term);
            });
        }

        if ($request->filled('status')) {
            $status = $request->query('status');
            if ($status === 'published') {
                $query->where('is_published', true);
            } elseif ($status === 'draft') {
                $query->where('is_published', false);
            }
        }

        $pages = $query->orderBy('title')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pages' => $pages->map(fn ($p) => [
                    'id' => (string) $p->id,
                    'title' => $p->title,
                    'slug' => $p->slug,
                    'url' => '/page/' . $p->slug,
                    'content' => $p->content,
                    'meta_title' => $p->meta_title,
                    'meta_description' => $p->meta_description,
                    'is_published' => (bool) $p->is_published,
                    'created_at' => $p->created_at?->toISOString(),
                    'updated_at' => $p->updated_at?->toISOString(),
                ]),
                'total_count' => $pages->count(),
                'published_count' => $pages->where('is_published', true)->count(),
            ],
        ]);
    }

    /**
     * Create a new custom CMS page.
     * POST /api/v1/tenant/storefront/pages
     * POST /tenant/storefront/pages
     */
    public function pagesStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');
        $this->checkEntitlement($company);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150'],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $slug = ! empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['title']);

        // Ensure unique slug for this tenant
        $originalSlug = $slug;
        $counter = 1;
        while (TenantCustomPage::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $page = TenantCustomPage::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'title' => $validated['title'],
            'slug' => $slug,
            'content' => $validated['content'] ?? null,
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'is_published' => $request->boolean('is_published', true),
        ]);

        AuditLog::record('storefront.page_created', $company->id, $request->user()?->id, [
            'page_id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Custom page created successfully.',
            'data' => [
                'id' => (string) $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'url' => '/page/' . $page->slug,
                'is_published' => (bool) $page->is_published,
            ],
        ], 201);
    }

    /**
     * Show single custom CMS page.
     * GET /api/v1/tenant/storefront/pages/{id}
     * GET /tenant/storefront/pages/{id}
     */
    public function pagesShow(Request $request, string|int $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');

        $page = TenantCustomPage::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $page) {
            return response()->json(['success' => false, 'error' => 'Page not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'url' => '/page/' . $page->slug,
                'content' => $page->content,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'is_published' => (bool) $page->is_published,
            ],
        ]);
    }

    /**
     * Update custom CMS page.
     * PUT /api/v1/tenant/storefront/pages/{id}
     * POST /api/v1/tenant/storefront/pages/{id}
     */
    public function pagesUpdate(Request $request, string|int $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');
        $this->checkEntitlement($company);

        $page = TenantCustomPage::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $page) {
            return response()->json(['success' => false, 'error' => 'Page not found'], 404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150'],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $slug = ! empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : $page->slug;

        // Ensure unique slug (excluding current page)
        $existing = TenantCustomPage::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('slug', $slug)
            ->where('id', '!=', $page->id)
            ->exists();

        if ($existing) {
            $slug = $slug . '-' . time();
        }

        $page->update([
            'title' => $validated['title'],
            'slug' => $slug,
            'content' => $validated['content'] ?? $page->content,
            'meta_title' => $validated['meta_title'] ?? $page->meta_title,
            'meta_description' => $validated['meta_description'] ?? $page->meta_description,
            'is_published' => $request->has('is_published') ? $request->boolean('is_published') : $page->is_published,
        ]);

        AuditLog::record('storefront.page_updated', $company->id, $request->user()?->id, [
            'page_id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Custom page updated successfully.',
            'data' => [
                'id' => (string) $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'url' => '/page/' . $page->slug,
                'is_published' => (bool) $page->is_published,
            ],
        ]);
    }

    /**
     * Delete custom CMS page.
     * DELETE /api/v1/tenant/storefront/pages/{id}
     * POST /api/v1/tenant/storefront/pages/{id}/delete
     */
    public function pagesDestroy(Request $request, string|int $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');
        $this->checkEntitlement($company);

        $page = TenantCustomPage::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $page) {
            return response()->json(['success' => false, 'error' => 'Page not found'], 404);
        }

        // Nullify reference in any menu items
        TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('page_id', $page->id)
            ->update(['page_id' => null]);

        $page->delete();

        AuditLog::record('storefront.page_deleted', $company->id, $request->user()?->id, [
            'page_id' => $id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Custom page deleted successfully.',
        ]);
    }

    // =========================================================================
    // STORE NAVIGATION MENUS CRUD & REORDER
    // =========================================================================

    /**
     * List menu items for the tenant.
     * GET /api/v1/tenant/storefront/menus
     * GET /tenant/storefront/menus
     */
    public function menusIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');

        TenantStoreMenu::seedDefaultsForCompany($company->id);

        $query = TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->with(['page:id,title,slug', 'category:id,name']);

        if ($request->filled('location')) {
            $query->where('location', $request->query('location'));
        }

        $items = $query->ordered()->get();

        $formatted = $items->map(fn ($m) => [
            'id' => (string) $m->id,
            'location' => $m->location,
            'title' => $m->title,
            'type' => $m->type,
            'target_url' => $m->target_url,
            'resolved_url' => $m->resolved_url,
            'page_id' => $m->page_id ? (string) $m->page_id : null,
            'page_title' => $m->page?->title,
            'category_id' => $m->category_id ? (string) $m->category_id : null,
            'category_name' => $m->category?->name,
            'sort_order' => (int) $m->sort_order,
            'is_visible' => (bool) $m->is_visible,
            'target' => $m->target ?? '_self',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'menu_items' => $formatted,
                'locations' => TenantStoreMenu::LOCATIONS,
                'types' => TenantStoreMenu::TYPES,
            ],
        ]);
    }

    /**
     * Create menu item.
     * POST /api/v1/tenant/storefront/menus
     * POST /tenant/storefront/menus
     */
    public function menusStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');
        $this->checkEntitlement($company);

        $validated = $request->validate([
            'location' => ['required', 'string', 'in:header_nav,footer_col_1,footer_col_2,footer_col_3'],
            'title' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'in:cms_page,category,anchor,custom_url'],
            'target_url' => ['nullable', 'string', 'max:255'],
            'page_id' => ['nullable', 'exists:tenant_custom_pages,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'target' => ['nullable', 'string', 'in:_self,_blank'],
            'is_visible' => ['nullable', 'boolean'],
        ]);

        $maxOrder = (int) (TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('location', $validated['location'])
            ->max('sort_order') ?? 0);

        $targetUrl = $validated['target_url'] ?? null;
        if ($validated['type'] === TenantStoreMenu::TYPE_CMS_PAGE && ! empty($validated['page_id'])) {
            $page = TenantCustomPage::withoutGlobalScopes()->where('company_id', $company->id)->find($validated['page_id']);
            if ($page) {
                $targetUrl = '/page/' . $page->slug;
            }
        } elseif ($validated['type'] === TenantStoreMenu::TYPE_CATEGORY && ! empty($validated['category_id'])) {
            $targetUrl = '#products-section';
        }

        $menuItem = TenantStoreMenu::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'location' => $validated['location'],
            'title' => $validated['title'],
            'type' => $validated['type'],
            'target_url' => $targetUrl,
            'page_id' => $validated['page_id'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'sort_order' => $maxOrder + 1,
            'is_visible' => $request->boolean('is_visible', true),
            'target' => $validated['target'] ?? '_self',
        ]);

        AuditLog::record('storefront.menu_created', $company->id, $request->user()?->id, [
            'menu_id' => $menuItem->id,
            'title' => $menuItem->title,
            'location' => $menuItem->location,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Menu item added successfully.',
            'data' => [
                'id' => (string) $menuItem->id,
                'title' => $menuItem->title,
                'location' => $menuItem->location,
                'resolved_url' => $menuItem->resolved_url,
                'sort_order' => (int) $menuItem->sort_order,
                'is_visible' => (bool) $menuItem->is_visible,
            ],
        ], 201);
    }

    /**
     * Update menu item.
     * PUT /api/v1/tenant/storefront/menus/{id}
     * POST /api/v1/tenant/storefront/menus/{id}
     */
    public function menusUpdate(Request $request, string|int $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');
        $this->checkEntitlement($company);

        $item = TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $item) {
            return response()->json(['success' => false, 'error' => 'Menu item not found'], 404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'target_url' => ['nullable', 'string', 'max:255'],
            'target' => ['nullable', 'string', 'in:_self,_blank'],
            'location' => ['nullable', 'string', 'in:header_nav,footer_col_1,footer_col_2,footer_col_3'],
            'is_visible' => ['nullable', 'boolean'],
        ]);

        $item->update([
            'title' => $validated['title'],
            'target_url' => $validated['target_url'] ?? $item->target_url,
            'target' => $validated['target'] ?? $item->target,
            'location' => $validated['location'] ?? $item->location,
            'is_visible' => $request->has('is_visible') ? $request->boolean('is_visible') : $item->is_visible,
        ]);

        AuditLog::record('storefront.menu_updated', $company->id, $request->user()?->id, [
            'menu_id' => $item->id,
            'title' => $item->title,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Menu item updated successfully.',
            'data' => [
                'id' => (string) $item->id,
                'title' => $item->title,
                'resolved_url' => $item->resolved_url,
                'is_visible' => (bool) $item->is_visible,
            ],
        ]);
    }

    /**
     * Toggle visibility of menu item.
     * POST /api/v1/tenant/storefront/menus/{id}/toggle-visibility
     * PUT /api/v1/tenant/storefront/menus/{id}/toggle-visibility
     */
    public function toggleVisibility(Request $request, string|int $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');
        $this->checkEntitlement($company);

        $item = TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $item) {
            return response()->json(['success' => false, 'error' => 'Menu item not found'], 404);
        }

        $item->update([
            'is_visible' => ! $item->is_visible,
        ]);

        return response()->json([
            'success' => true,
            'message' => $item->is_visible ? 'Menu item is now visible.' : 'Menu item is now hidden.',
            'data' => [
                'id' => (string) $item->id,
                'is_visible' => (bool) $item->is_visible,
            ],
        ]);
    }

    /**
     * Delete menu item.
     * DELETE /api/v1/tenant/storefront/menus/{id}
     * POST /api/v1/tenant/storefront/menus/{id}/delete
     */
    public function menusDestroy(Request $request, string|int $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');
        $this->checkEntitlement($company);

        $item = TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $item) {
            return response()->json(['success' => false, 'error' => 'Menu item not found'], 404);
        }

        $item->delete();

        AuditLog::record('storefront.menu_deleted', $company->id, $request->user()?->id, [
            'menu_id' => $id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Menu item removed successfully.',
        ]);
    }

    /**
     * Reorder menu items.
     * POST /api/v1/tenant/storefront/menus/reorder
     * POST /tenant/storefront/menus/reorder
     */
    public function reorder(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Store not found');
        $this->checkEntitlement($company);

        $orderedIds = $request->input('ordered_ids') ?? $request->input('ids') ?? [];
        if (! is_array($orderedIds)) {
            $orderedIds = [];
        }

        foreach ($orderedIds as $index => $id) {
            TenantStoreMenu::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('id', $id)
                ->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Menu order updated successfully.',
        ]);
    }

    // =========================================================================
    // PUBLIC STOREFRONT MENU API
    // =========================================================================

    /**
     * Public menu feed for storefront layout.
     * GET /api/v1/storefront/menus
     * GET /storefront/menus
     */
    public function publicMenus(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        abort_if(! $company, 404, 'Storefront not found');

        TenantStoreMenu::seedDefaultsForCompany($company->id);

        $items = TenantStoreMenu::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->visible()
            ->with(['page:id,title,slug', 'category:id,name'])
            ->ordered()
            ->get();

        $formatItems = fn ($collection) => $collection->map(fn ($m) => [
            'id' => (string) $m->id,
            'title' => $m->title,
            'type' => $m->type,
            'url' => $m->resolved_url,
            'target' => $m->target ?? '_self',
            'category_name' => $m->category?->name,
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'header_nav' => $formatItems($items->where('location', TenantStoreMenu::LOCATION_HEADER)),
                'footer_col_1' => $formatItems($items->where('location', TenantStoreMenu::LOCATION_FOOTER_1)),
                'footer_col_2' => $formatItems($items->where('location', TenantStoreMenu::LOCATION_FOOTER_2)),
                'footer_col_3' => $formatItems($items->where('location', TenantStoreMenu::LOCATION_FOOTER_3)),
            ],
        ]);
    }
}
