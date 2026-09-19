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
        'invoice_limit', 'device_limit', 'staff_limit', 'extensions',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
            'extensions' => 'array',
            'invoice_limit' => 'integer',
            'device_limit' => 'integer',
            'staff_limit' => 'integer',
            'active' => 'boolean',
            'price' => 'decimal:2',
        ];
    }
}
