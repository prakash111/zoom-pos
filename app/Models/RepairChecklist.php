<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairChecklist extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'tenant_id',
        'repair_ticket_id',
        'item_name',
        'type',
        'status',
        'notes',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }
}
