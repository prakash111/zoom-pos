<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * REST CRUD for the catalog-admin lookup tables (Categories, Brands, Units,
 * Suppliers). These previously had no dedicated API — only read access via
 * GET /sync-pull and write access via the generic POST /sync-batch — so a
 * mobile client couldn't manage them directly. Mirrors the validation used
 * by the equivalent web Livewire components (Categories/Brands/Units/
 * Suppliers \Index::save/delete).
 */
class CatalogAdminApiController extends Controller
{
    use ResolvesTenantSyncContext;

    // ---- Categories (permission module: categories) ----

    public function categoriesIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $categories = Category::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => $this->presentCategory($c));

        return response()->json(['success' => true, 'categories' => $categories]);
    }

    public function categoriesStore(Request $request): JsonResponse
    {
        return $this->saveSimple($request, Category::class, [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:1000'],
            'metadata' => ['nullable'],
        ], fn (Category $c) => $this->presentCategory($c), 'category.saved');
    }

    public function categoriesUpdate(Request $request, string $id): JsonResponse
    {
        return $this->saveSimple($request, Category::class, [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:1000'],
            'metadata' => ['nullable'],
        ], fn (Category $c) => $this->presentCategory($c), 'category.saved', $id);
    }

    public function categoriesDestroy(Request $request, string $id): JsonResponse
    {
        return $this->destroySimple($request, Category::class, $id, 'category.deleted');
    }

    // ---- Brands (permission module: categories — matches web's routing) ----

    public function brandsIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $brands = Brand::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Brand $b) => ['id' => (string) $b->id, 'name' => $b->name, 'active' => (bool) $b->active]);

        return response()->json(['success' => true, 'brands' => $brands]);
    }

    public function brandsStore(Request $request): JsonResponse
    {
        return $this->saveSimple($request, Brand::class, [
            'name' => ['required', 'string', 'max:255'],
        ], fn (Brand $b) => ['id' => (string) $b->id, 'name' => $b->name, 'active' => (bool) $b->active], 'brand.saved');
    }

    public function brandsUpdate(Request $request, string $id): JsonResponse
    {
        return $this->saveSimple($request, Brand::class, [
            'name' => ['required', 'string', 'max:255'],
        ], fn (Brand $b) => ['id' => (string) $b->id, 'name' => $b->name, 'active' => (bool) $b->active], 'brand.saved', $id);
    }

    public function brandsDestroy(Request $request, string $id): JsonResponse
    {
        return $this->destroySimple($request, Brand::class, $id, 'brand.deleted');
    }

    // ---- Units (permission module: units) ----

    public function unitsIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $units = Unit::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Unit $u) => ['id' => (string) $u->id, 'name' => $u->name, 'abbreviation' => $u->abbreviation ?? '']);

        return response()->json(['success' => true, 'units' => $units]);
    }

    public function unitsStore(Request $request): JsonResponse
    {
        return $this->saveSimple($request, Unit::class, [
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['nullable', 'string', 'max:16'],
        ], fn (Unit $u) => ['id' => (string) $u->id, 'name' => $u->name, 'abbreviation' => $u->abbreviation ?? ''], 'unit.saved');
    }

    public function unitsUpdate(Request $request, string $id): JsonResponse
    {
        return $this->saveSimple($request, Unit::class, [
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['nullable', 'string', 'max:16'],
        ], fn (Unit $u) => ['id' => (string) $u->id, 'name' => $u->name, 'abbreviation' => $u->abbreviation ?? ''], 'unit.saved', $id);
    }

    public function unitsDestroy(Request $request, string $id): JsonResponse
    {
        return $this->destroySimple($request, Unit::class, $id, 'unit.deleted');
    }

    // ---- Suppliers (permission module: suppliers) ----

    public function suppliersIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $query = Supplier::withoutGlobalScope('company')->where('company_id', $company->id);

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $suppliers = $query->orderBy('name')->get()->map(fn (Supplier $s) => $this->presentSupplier($s));

        return response()->json(['success' => true, 'suppliers' => $suppliers]);
    }

    public function suppliersStore(Request $request): JsonResponse
    {
        return $this->saveSupplier($request);
    }

    public function suppliersUpdate(Request $request, string $id): JsonResponse
    {
        return $this->saveSupplier($request, $id);
    }

    public function suppliersDestroy(Request $request, string $id): JsonResponse
    {
        return $this->destroySimple($request, Supplier::class, $id, 'supplier.deleted');
    }

    // ---- Shared helpers ----

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function saveSimple(
        Request $request,
        string $modelClass,
        array $rules,
        \Closure $present,
        string $auditAction,
        ?string $id = null,
    ): JsonResponse {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if ($id !== null) {
            $record = $modelClass::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where(fn ($q) => $q->where('id', $id)->orWhere('external_id', $id))
                ->first();

            if (! $record) {
                return response()->json(['success' => false, 'error' => 'Record not found.'], 404);
            }

            $record->update($data);
        } else {
            // Refresh so DB-applied column defaults (e.g. `active`) are
            // reflected in the response, not just whatever was in $data.
            $record = $modelClass::create(array_merge($data, ['company_id' => $company->id]))->fresh();
        }

        AuditLog::record($auditAction, $company->id, $user?->id, ['id' => $record->id]);

        return response()->json([
            'success' => true,
            'message' => 'Saved.',
            'data' => $present($record),
        ], $id === null ? 201 : 200);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function destroySimple(Request $request, string $modelClass, string $id, string $auditAction): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $record = $modelClass::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(fn ($q) => $q->where('id', $id)->orWhere('external_id', $id))
            ->first();

        if (! $record) {
            return response()->json(['success' => false, 'error' => 'Record not found.'], 404);
        }

        $record->delete();
        AuditLog::record($auditAction, $company->id, $user?->id, ['id' => $id]);

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    private function saveSupplier(Request $request, ?string $id = null): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['active'] = $data['active'] ?? true;

        if ($id !== null) {
            $supplier = Supplier::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where(fn ($q) => $q->where('id', $id)->orWhere('external_id', $id))
                ->first();

            if (! $supplier) {
                return response()->json(['success' => false, 'error' => 'Supplier not found.'], 404);
            }

            $supplier->update($data);
        } else {
            $supplier = Supplier::create(array_merge($data, ['company_id' => $company->id]));
        }

        AuditLog::record('supplier.saved', $company->id, $user?->id, ['id' => $supplier->id]);

        return response()->json([
            'success' => true,
            'message' => 'Saved.',
            'supplier' => $this->presentSupplier($supplier),
        ], $id === null ? 201 : 200);
    }

    private function presentCategory(Category $c): array
    {
        return [
            'id' => (string) $c->id,
            'name' => $c->name,
            'type' => $c->type ?? 'retail',
            'color' => $c->color ?? '#4f46e5',
            'description' => $c->description ?? '',
            'metadata' => $c->metadata,
            'active' => (bool) $c->active,
        ];
    }

    private function presentSupplier(Supplier $s): array
    {
        return [
            'id' => (string) $s->id,
            'name' => $s->name,
            'legal_name' => $s->legal_name ?? '',
            'tax_id' => $s->tax_id ?? '',
            'email' => $s->email ?? '',
            'phone' => $s->phone ?? '',
            'city' => $s->city ?? '',
            'state' => $s->state ?? '',
            'active' => (bool) $s->active,
        ];
    }
}
