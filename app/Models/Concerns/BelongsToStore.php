<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait BelongsToStore
{
    protected static function bootBelongsToStore(): void
    {
        static::addGlobalScope('store', function (Builder $builder) {
            if (app()->bound('tenant.store_id')) {
                $builder->where($builder->getModel()->getTable().'.store_id', app('tenant.store_id'));
            }
        });

        static::creating(function ($model) {
            if (app()->bound('tenant.store_id')
                && app()->bound('tenant.company_id')
                && (string) $model->company_id === (string) app('tenant.company_id')) {
                $model->store_id = app('tenant.store_id');
            } elseif (! $model->store_id && $model->company_id && Schema::hasTable('stores')) {
                $model->store_id = DB::table('stores')
                    ->where('company_id', $model->company_id)
                    ->where('is_primary', true)
                    ->value('id');
            }
        });
    }
}
