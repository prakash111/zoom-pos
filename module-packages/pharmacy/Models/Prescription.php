<?php

namespace Modules\pharmacy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    protected $table = 'pharmacy_mod_prescriptions';

    protected $guarded = [];

    protected $casts = [
        'dispensed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class, 'prescription_id');
    }
}
