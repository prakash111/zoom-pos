<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $primaryKey = 'name';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name', 'display_name', 'billing_cycle', 'duration_days',
        'price', 'currency', 'features', 'limits', 'active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
            'active' => 'boolean',
            'price' => 'decimal:2',
        ];
    }
}
