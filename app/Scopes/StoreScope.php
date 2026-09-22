<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class StoreScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $storeId = null;

        if (app()->bound('tenant.store_id')) {
            $storeId = app('tenant.store_id');
        } elseif (Auth::check() && Auth::user()?->current_store_id) {
            $storeId = Auth::user()->current_store_id;
        }

        if ($storeId) {
            $table = $model->getTable();
            $builder->where("{$table}.store_id", $storeId);
        }
    }
}
