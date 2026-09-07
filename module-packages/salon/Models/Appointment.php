<?php

namespace Modules\salon\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $table = 'salon_mod_appointments';

    protected $guarded = [];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'price' => 'decimal:2',
    ];

    public const STATUSES = [
        'booked', 'confirmed', 'in_service', 'completed', 'no_show', 'cancelled',
    ];

    public function stylist(): BelongsTo
    {
        return $this->belongsTo(Stylist::class, 'stylist_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(SalonService::class, 'service_id');
    }
}
