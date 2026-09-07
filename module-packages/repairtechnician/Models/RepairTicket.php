<?php

namespace Modules\repairtechnician\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepairTicket extends Model
{
    protected $table = 'repair_mod_tickets';

    protected $guarded = [];

    protected $casts = [
        'inspection_checklist' => 'array',
        'estimated_cost' => 'decimal:2',
        'advance_paid' => 'decimal:2',
        'delivered_at' => 'datetime',
    ];

    public const STATUSES = [
        'received', 'diagnosing', 'waiting_parts', 'in_progress', 'ready', 'delivered', 'cancelled',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(RepairTicketItem::class, 'ticket_id');
    }
}
