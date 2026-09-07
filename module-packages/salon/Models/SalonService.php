<?php

namespace Modules\salon\Models;

use Illuminate\Database\Eloquent\Model;

class SalonService extends Model
{
    protected $table = 'salon_mod_services';

    protected $guarded = [];

    protected $casts = [
        'duration_minutes' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
