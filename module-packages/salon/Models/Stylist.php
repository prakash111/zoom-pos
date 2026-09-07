<?php

namespace Modules\salon\Models;

use Illuminate\Database\Eloquent\Model;

class Stylist extends Model
{
    protected $table = 'salon_mod_stylists';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
