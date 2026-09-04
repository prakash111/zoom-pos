<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepairTicket extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'tenant_id',
        'ticket_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'device_category_id',
        'device_type',
        'brand',
        'model',
        'serial_or_imei',
        'passcode_or_pattern',
        'issue_description',
        'physical_condition_notes',
        'status',
        'priority',
        'technician_id',
        'estimated_cost',
        'advance_paid',
        'labor_fee',
        'parts_cost',
        'total_amount',
        'final_sale_id',
        'internal_notes',
        'intake_at',
        'completed_at',
        'delivered_at',
        'is_demo',
    ];

    protected $appends = [
        'balance_due',
        'status_color',
        'priority_color',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'float',
            'advance_paid' => 'float',
            'labor_fee' => 'float',
            'parts_cost' => 'float',
            'total_amount' => 'float',
            'intake_at' => 'datetime',
            'completed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deviceCategory(): BelongsTo
    {
        return $this->belongsTo(RepairDeviceCategory::class, 'device_category_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function finalSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'final_sale_id');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(RepairTicketPart::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(RepairChecklist::class);
    }

    public function getBalanceDueAttribute(): float
    {
        return max(0, (float) $this->total_amount - (float) $this->advance_paid);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => '#0284c7',
            'diagnosing' => '#8b5cf6',
            'waiting_parts' => '#f59e0b',
            'in_progress' => '#3b82f6',
            'repaired' => '#10b981',
            'delivered' => '#059669',
            'cancelled' => '#ef4444',
            default => '#64748b',
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => '#ef4444',
            'high' => '#f97316',
            'normal' => '#3b82f6',
            default => '#64748b',
        };
    }
}
