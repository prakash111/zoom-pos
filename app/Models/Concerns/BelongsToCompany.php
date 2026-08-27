<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Tenant data isolation for a single shared database (BYODB was dropped).
 * Every tenant-scoped model filters by the "current" company_id (bound into
 * the container by ResolveTenantContext) and auto-fills it on create.
 * Super Admin code that legitimately needs cross-tenant access opts out via
 * Model::withoutGlobalScope('company') or the forCompany() local scope.
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (app()->bound('tenant.company_id')) {
                $builder->where(
                    $builder->getModel()->getTable().'.company_id',
                    app('tenant.company_id')
                );
            }
        });

        static::creating(function ($model) {
            if (empty($model->company_id) && app()->bound('tenant.company_id')) {
                $model->company_id = app('tenant.company_id');
            }
        });
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->withoutGlobalScope('company')->where('company_id', $companyId);
    }
}
