<?php

namespace Modules\pharmacy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    protected $table = 'pharmacy_mod_prescription_items';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class, 'prescription_id');
    }
}
