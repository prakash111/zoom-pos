<?php

namespace App\Services\Stores;

use App\Models\Company;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StoreContext
{
    public function bind(Request $request, ?User $user, ?string $companyId): ?Store
    {
        if (app()->bound('tenant.store_id')) {
            app()->forgetInstance('tenant.store_id');
        }
        if (app()->bound('tenant.store_is_primary')) {
            app()->forgetInstance('tenant.store_is_primary');
        }
        if (! $companyId || ! Schema::hasTable('stores')) {
            return null;
        }

        $company = Company::withoutGlobalScopes()->find($companyId);
        if (! $company) {
            return null;
        }
        $primary = $this->ensurePrimary($company);
        $requested = $request->header('X-Store-Id') ?: $user?->current_store_id ?: $primary->id;
        $store = Store::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->find($requested);

        abort_unless($store, 403, 'Store is unavailable or belongs to another tenant.');
        if ($user && ! $user->isPrivilegedRole()) {
            if (! $user->stores()->where('stores.id', $store->id)->exists()) {
                abort(403, 'You are not assigned to this store.');
            }
        } elseif (! $user && (int) $store->id !== (int) $primary->id) {
            abort(403, 'A user account is required to select a non-primary store.');
        }

        app()->instance('tenant.store_id', (int) $store->id);
        app()->instance('tenant.store_is_primary', (bool) $store->is_primary);
        $request->attributes->set('store_id', (int) $store->id);

        return $store;
    }

    public function ensurePrimary(Company $company): Store
    {
        $primary = Store::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_primary', true)
            ->first();
        if ($primary) {
            return $primary;
        }

        $primary = Store::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'main'],
            [
                'name' => $company->trade_name ?: $company->name,
                'phone' => $company->phone,
                'email' => $company->email,
                'address' => $company->address,
                'tax_id' => $company->tax_id,
                'is_active' => true,
                'is_primary' => true,
            ]
        );
        if (! $primary->is_primary) {
            $primary->update(['is_primary' => true]);
        }

        // New tenants can be provisioned after the migration has run. Put
        // their pre-existing products and users into the first branch once.
        $now = now();
        foreach (DB::table('products')->where('company_id', $company->id)->select('id', 'current_stock')->cursor() as $product) {
            DB::table('product_store_stock')->insertOrIgnore([
                'product_id' => $product->id,
                'store_id' => $primary->id,
                'quantity' => $product->current_stock ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        foreach (DB::table('users')->where('company_id', $company->id)->pluck('id') as $userId) {
            DB::table('store_user')->insertOrIgnore(['store_id' => $primary->id, 'user_id' => $userId]);
        }
        DB::table('users')->where('company_id', $company->id)->whereNull('current_store_id')->update(['current_store_id' => $primary->id]);
        foreach (['sales', 'cash_registers', 'order_payments'] as $table) {
            DB::table($table)->where('company_id', $company->id)->whereNull('store_id')->update(['store_id' => $primary->id]);
        }

        return $primary;
    }
}
