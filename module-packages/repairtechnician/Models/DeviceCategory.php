<?php

namespace Modules\repairtechnician\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceCategory extends Model
{
    protected $table = 'repair_mod_device_categories';

    protected $guarded = [];

    protected $casts = [
        'checklist_points' => 'array',
        'default_diagnostic_fee' => 'decimal:2',
    ];
}
