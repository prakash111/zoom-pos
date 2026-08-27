<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class SaaSPlan extends Plan
{
    protected $table = 'plans';

    /**
     * Scope a query to only include active plans.
     */
    public function scopeIsActive(Builder $query, bool $active = true): Builder
    {
        return $query->where('active', $active);
    }
}
