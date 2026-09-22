<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Store;
use App\Models\Role;
use App\Models\CashRegister;
use App\Http\Requests\Traits\NormalizesPhoneNumber;
use App\Services\Localization\PlatformRegionalService;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreController extends Controller
{
    use NormalizesPhoneNumber;

    private function company(Request $request): Company
    {
        $id = $request->attributes->get('company_id') ?? app('tenant.company_id');

        return Company::withoutGlobalScopes()->findOrFail($id);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($request->user() && PermissionChecker::can($request->user(), 'stores', $action), 403);
    }

    private function canManage(?User $user): bool
    {
        return $user && (PermissionChecker::can($user, 'stores', 'manage')
            || PermissionChecker::can($user, 'stores', 'edit'));
    }

    private function branch(Request $request, string $id): Store
    {
        $store = Store::where('company_id', $this->company($request)->id)->findOrFail($id);
        if (! $request->user()->isPrivilegedRole()) {
            abort_unless($store->users()->where('users.id', $request->user()->id)->exists(), 403);
        }
        return $store;
    }

    private function resource(Store $store, Company $company, ?int $currentId): array
    {
        return array_merge($store->only(['id', 'name', 'code', 'phone', 'email', 'address', 'tax_id', 'is_primary', 'is_active']), [
            'subdomain' => $company->slug,
            'is_current' => $store->is_active && (int) $store->id === $currentId,
            'receipt_prefix' => $store->settings['invoice_prefix'] ?? $company->invoice_prefix,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'view');
        $user = $request->user();
        $company = $this->company($request);
        $canManage = $this->canManage($user);
        $query = Store::where('company_id', $company->id);
        if (! ($request->boolean('include_inactive') && $canManage)) {
            $query->where('is_active', true);
        }
        if (! $user->isPrivilegedRole()) {
            $query->whereHas('users', fn ($query) => $query->where('users.id', $user->id));
        }
        $currentId = (int) ($request->attributes->get('store_id') ?? $user->current_store_id);
        $stores = $query->orderByDesc('is_primary')->orderBy('name')->get()
            ->map(fn (Store $store) => $this->resource($store, $company, $currentId))->values();
        $limit = (int) ($company->plan?->store_limit ?? 1);
        $count = Store::where('company_id', $company->id)->count();
        $canCreate = PermissionChecker::can($user, 'stores', 'create');
        return response()->json([
            'success' => true,
            'data' => $stores,
            'meta' => [
                'total_stores' => $count,
                'max_allowed_stores' => $limit,
                'can_create_more' => $canCreate && ($limit === -1 || $count < $limit),
                'can_create' => $canCreate,
                'can_manage' => $canManage,
                'default_dial_code' => PlatformRegionalService::dialCodeForCountry($company->country ?: 'IN'),
                'current_store_id' => $currentId,
            ],
            // Compatibility for already installed Flutter releases.
            'stores' => $stores,
            'current_store_id' => $currentId,
            'store_limit' => $limit,
            'store_count' => $count,
            'can_create' => $canCreate,
            'can_manage' => $canManage,
        ]);
    }

    private function validatedDetails(Request $request, Company $company, ?Store $store = null): array
    {
        if ($request->has('phone') && (is_string($request->input('phone')) || $request->input('phone') === null)) {
            $request->merge(['phone' => self::normalizePhoneNumber(
                $request->input('phone'),
                PlatformRegionalService::dialCodeForCountry($company->country ?: 'IN')
            )]);
        }
        return $request->validate([
            'name' => [$store ? 'sometimes' : 'required', 'required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'alpha_dash:ascii', 'max:64', Rule::unique('stores')->where('company_id', $company->id)->ignore($store?->id)],
            'phone' => ['nullable', 'string', 'regex:/^\+[1-9][0-9]{6,14}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'create');
        $company = $this->company($request);
        $data = $this->validatedDetails($request, $company);
        $store = DB::transaction(function () use ($company, $data, $request) {
            Company::withoutGlobalScopes()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $limit = (int) ($company->plan?->store_limit ?? 1);
            if ($limit !== -1 && Store::where('company_id', $company->id)->count() >= $limit) {
                return response()->json([
                    'success' => false, 'error' => 'quota_exceeded',
                    'message' => "You have reached your limit of {$limit} stores. Please upgrade your plan.",
                    'upgrade_required' => true,
                ], 403);
            }
            if (empty($data['code'])) {
                $base = strtoupper(substr(Str::slug($data['name']), 0, 36)) ?: 'BRANCH';
                $data['code'] = $base;
                for ($suffix = 2; Store::where('company_id', $company->id)->where('code', $data['code'])->exists(); $suffix++) {
                    $data['code'] = $base.'-'.$suffix;
                }
            }
            $data['is_active'] = true;
            $store = Store::create($data + [
                'company_id' => $company->id,
                'settings' => [
                    'invoice_prefix' => strtoupper($data['code']).'-INV-',
                    'quotation_prefix' => strtoupper($data['code']).'-QUO-',
                    // CashRegister rows are sessions. Provision the drawer now;
                    // the first opening creates a session with this terminal.
                    'cash_register' => ['name' => 'Main Register', 'terminal_id' => substr($data['code'], 0, 58).'-POS-1'],
                ],
            ]);
            $role = Role::firstOrCreate(
                ['company_id' => $company->id, 'slug' => 'branch_administrator'],
                ['name' => 'Branch Administrator', 'is_system' => false,
                    'permissions' => ['stores' => ['view', 'manage']]]
            );
            $store->users()->syncWithoutDetaching([$request->user()->id => ['role_id' => $role->id]]);
            $now = now();
            DB::table('products')->where('company_id', $company->id)->select('id')->orderBy('id')
                ->chunkById(500, function ($products) use ($store, $now) {
                    DB::table('product_store_stock')->insertOrIgnore($products->map(fn ($product) => [
                        'product_id' => $product->id, 'store_id' => $store->id,
                        'quantity' => 0, 'created_at' => $now, 'updated_at' => $now,
                    ])->all());
                });
            $request->user()->update(['current_store_id' => $store->id]);
            return $store;
        });
        if ($store instanceof JsonResponse) {
            return $store;
        }
        $resource = $this->resource($store, $company, (int) $store->id);
        return response()->json(['success' => true, 'data' => $resource, 'store' => $resource,
            'current_store' => $resource, 'current_store_id' => $store->id], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);
        $store = $this->branch($request, $id);
        $company = $this->company($request);
        $data = $this->validatedDetails($request, $company, $store);
        if (empty($data['code'])) {
            unset($data['code']);
        }
        DB::transaction(function () use ($store, $data) {
            Store::whereKey($store->id)->lockForUpdate()->firstOrFail();
            if (array_key_exists('is_active', $data) && ! $data['is_active']) {
                if ($store->is_primary) {
                    throw ValidationException::withMessages(['is_active' => 'The primary store must remain active.']);
                }
                if (CashRegister::withoutGlobalScope('store')->where('store_id', $store->id)->where('status', 'open')->exists()) {
                    throw ValidationException::withMessages(['is_active' => 'Close the cash register before deactivating this store.']);
                }
                User::withoutGlobalScopes()->where('company_id', $store->company_id)
                    ->where('current_store_id', $store->id)->update(['current_store_id' => null]);
            }
            $store->update($data);
        });
        $resource = $this->resource($store->fresh(), $company, (int) $request->user()->current_store_id);
        return response()->json(['success' => true, 'store' => $resource, 'data' => $resource]);
    }

    public function switch(Request $request, ?string $id = null): JsonResponse
    {
        $this->authorizeAction($request, 'view');
        $id ??= (string) $request->validate(['store_id' => ['required', 'integer']])['store_id'];
        $store = $this->branch($request, $id);
        abort_unless($store->is_active, 422, 'This store is inactive.');
        $request->user()->update(['current_store_id' => $store->id]);
        // TenantApiKey / TenantSession authentication resolves the user on
        // every request; this application does not embed store claims in JWTs.
        $resource = $this->resource($store, $this->company($request), (int) $store->id);
        return response()->json(['success' => true,
            'message' => "Switched to {$store->name} successfully",
            'store' => $resource, 'current_store' => $resource, 'current_store_id' => $store->id]);
    }

    public function management(Request $request)
    {
        $request->merge(['include_inactive' => true]);
        $payload = $this->index($request)->getData(true);
        return view('tenant.settings.stores', ['branches' => $payload['data'], 'meta' => $payload['meta']]);
    }

    public function webStore(Request $request)
    {
        $response = $this->store($request);
        if ($response->getStatusCode() >= 400) {
            return back()->withErrors(['name' => $response->getData(true)['message']])->withInput();
        }
        return redirect()->route('tenant.settings.stores')->with('status', 'Store created and selected.');
    }

    public function webUpdate(Request $request, string $id)
    {
        $this->update($request, $id);
        return redirect()->route('tenant.settings.stores')->with('status', 'Store details saved.');
    }

    public function webSwitch(Request $request, string $id)
    {
        $this->switch($request, $id);
        $destination = $request->input('redirect_to') === 'dashboard' ? 'tenant.dashboard' : 'tenant.settings.stores';
        return redirect()->route($destination)->with('status', 'Active store changed.');
    }

    public function staff(Request $request, string $id): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);
        $store = $this->branch($request, $id);

        return response()->json([
            'success' => true,
            'staff' => $store->users()->get(['users.id', 'users.name', 'users.email', 'users.role']),
        ]);
    }

    public function assignStaff(Request $request, string $id): JsonResponse
    {
        abort_unless($this->canManage($request->user()), 403);
        abort_unless(PermissionChecker::can($request->user(), 'users', 'edit'), 403);
        $company = $this->company($request);
        $store = $this->branch($request, $id);
        $data = $request->validate([
            'user_id' => ['required', 'string', Rule::exists('users', 'id')->where('company_id', $company->id)],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
        ]);
        if (! empty($data['role_id'])) {
            abort_unless(DB::table('roles')->where('id', $data['role_id'])
                ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $company->id))
                ->exists(), 422, 'Role does not belong to this tenant.');
        }
        $store->users()->syncWithoutDetaching([
            $data['user_id'] => ['role_id' => $data['role_id'] ?? null],
        ]);
        User::withoutGlobalScopes()->whereKey($data['user_id'])
            ->where('company_id', $company->id)
            ->whereNull('current_store_id')
            ->update(['current_store_id' => $store->id]);

        return response()->json(['success' => true]);
    }
}
