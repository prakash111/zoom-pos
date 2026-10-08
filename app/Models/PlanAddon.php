<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanAddon extends Model
{
    protected $table = 'plan_addons';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_yearly',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
