<?php

namespace App\Models\Concerns;

use App\Models\Store;
use App\Scopes\StoreScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait BelongsToStore
{
    protected static function bootBelongsToStore(): void
    {
        static::addGlobalScope(new StoreScope);

        static::creating(function ($model) {
            if (app()->bound('tenant.store_id')
                && app()->bound('tenant.company_id')
                && (string) $model->company_id === (string) app('tenant.company_id')) {
                $model->store_id = app('tenant.store_id');
            } elseif (empty($model->store_id) && Auth::check() && Auth::user()?->current_store_id) {
                $model->store_id = Auth::user()->current_store_id;
            } elseif (! $model->store_id && $model->company_id && Schema::hasTable('stores')) {
                $model->store_id = DB::table('stores')
                    ->where('company_id', $model->company_id)
                    ->where('is_primary', true)
                    ->value('id');
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
