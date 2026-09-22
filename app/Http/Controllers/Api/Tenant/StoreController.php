<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreController extends Controller
{
    private function company(Request $request): Company
    {
        $id = $request->attributes->get('company_id') ?? app('tenant.company_id');

        return Company::withoutGlobalScopes()->findOrFail($id);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($request->user() && PermissionChecker::can($request->user(), 'stores', $action), 403);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        $company = $this->company($request);
        $stores = Store::where('company_id', $company->id)->where('is_active', true);
        if (! $user->isPrivilegedRole()) {
            $stores->whereHas('users', fn ($query) => $query->where('users.id', $user->id));
        }
        $limit = (int) ($company->plan?->store_limit ?? 1);

        return response()->json([
            'success' => true,
            'stores' => $stores->orderByDesc('is_primary')->orderBy('name')->get(),
            'current_store_id' => $request->attributes->get('store_id'),
            'store_limit' => $limit,
            'store_count' => Store::where('company_id', $company->id)->count(),
            'can_create' => PermissionChecker::can($user, 'stores', 'create'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'create');
        $company = $this->company($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'alpha_dash', 'max:64', Rule::unique('stores')->where('company_id', $company->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'tax_id' => ['nullable', 'string', 'max:100'],
        ]);

        $store = DB::transaction(function () use ($company, $data, $request) {
            Company::withoutGlobalScopes()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $limit = (int) ($company->plan?->store_limit ?? 1);
            if ($limit >= 0 && Store::where('company_id', $company->id)->count() >= $limit) {
                abort(422, 'Store limit reached. Upgrade your plan to add another store.');
            }

            $store = Store::create($data + ['company_id' => $company->id]);
            $store->users()->syncWithoutDetaching([$request->user()->id]);
            $request->user()->update(['current_store_id' => $store->id]);

            return $store;
        });

        return response()->json(['success' => true, 'store' => $store, 'current_store_id' => $store->id], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeAction($request, 'edit');
        $store = Store::where('company_id', $this->company($request)->id)->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'alpha_dash', 'max:64', Rule::unique('stores')->where('company_id', $store->company_id)->ignore($store->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'tax_id' => ['nullable', 'string', 'max:100'],
        ]);
        $store->update($data);

        return response()->json(['success' => true, 'store' => $store->fresh()]);
    }

    public function switch(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        $data = $request->validate(['store_id' => ['required', 'integer']]);
        $store = Store::where('company_id', $this->company($request)->id)
            ->where('is_active', true)
            ->findOrFail($data['store_id']);
        if (! $user->isPrivilegedRole()) {
            abort_unless($user->stores()->where('stores.id', $store->id)->exists(), 403);
        }
        $user->update(['current_store_id' => $store->id]);

        return response()->json(['success' => true, 'store' => $store, 'current_store_id' => $store->id]);
    }

    public function staff(Request $request, string $id): JsonResponse
    {
        $this->authorizeAction($request, 'edit');
        $store = Store::where('company_id', $this->company($request)->id)->findOrFail($id);

        return response()->json([
            'success' => true,
            'staff' => $store->users()->get(['users.id', 'users.name', 'users.email', 'users.role']),
        ]);
    }

    public function assignStaff(Request $request, string $id): JsonResponse
    {
        $this->authorizeAction($request, 'edit');
        abort_unless(PermissionChecker::can($request->user(), 'users', 'edit'), 403);
        $company = $this->company($request);
        $store = Store::where('company_id', $company->id)->findOrFail($id);
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
