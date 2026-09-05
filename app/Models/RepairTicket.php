<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RepairTicket extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $table = 'repair_tickets';

    public const STATUS_RECEIVED = 'received';
    public const STATUS_DIAGNOSING = 'diagnosing';
    public const STATUS_WAITING_PARTS = 'waiting_parts';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_READY = 'ready';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_RECEIVED => 'Received / Intake',
        self::STATUS_DIAGNOSING => 'Diagnosing',
        self::STATUS_WAITING_PARTS => 'Waiting for Parts',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_READY => 'Ready for Pickup',
        self::STATUS_DELIVERED => 'Delivered & Closed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public ?float $initial_labor_fee = null;
    public ?float $temp_parts_cost = null;
    public ?float $temp_total_amount = null;

    protected $fillable = [
        'company_id',
        'tenant_id',
        'ticket_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'category_id',
        'brand',
        'model',
        'serial_number_or_imei',
        'passcode_pattern',
        'problem_reported',
        'technician_diagnosis',
        'physical_condition_notes',
        'assigned_technician_id',
        'status',
        'priority',
        'estimated_cost',
        'advance_deposit',
        'advance_payment_method',
        'advance_sale_id',
        'final_sale_id',
        'inspection_checklist',
        'expected_delivery_at',
        'intake_at',
        'completed_at',
        'delivered_at',
        'is_demo',
        // Compatibility attributes
        'serial_or_imei',
        'passcode_or_pattern',
        'issue_description',
        'advance_paid',
        'device_category_id',
        'technician_id',
        'device_type',
        'labor_fee',
        'parts_cost',
        'total_amount',
    ];

    protected $appends = [
        'parts_cost',
        'labor_fee',
        'total_amount',
        'balance_due',
        'status_color',
        'priority_color',
        'device_type',
        'device_category_id',
        'serial_or_imei',
        'passcode_or_pattern',
        'issue_description',
        'advance_paid',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
            'advance_deposit' => 'decimal:2',
            'inspection_checklist' => 'array',
            'expected_delivery_at' => 'datetime',
            'intake_at' => 'datetime',
            'completed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    /* ---------------------------------------------------------------------
     | Relationships
     | --------------------------------------------------------------------- */

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function advanceSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'advance_sale_id');
    }

    public function finalSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'final_sale_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RepairTicketItem::class, 'ticket_id');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(RepairTicketItem::class, 'ticket_id')->where('item_type', 'spare_part');
    }

    public function laborItems(): HasMany
    {
        return $this->hasMany(RepairTicketItem::class, 'ticket_id')->where('item_type', 'service_labor');
    }

    protected static function booted(): void
    {
        static::saving(function ($ticket) {
            if (empty($ticket->problem_reported)) {
                $ticket->problem_reported = $ticket->issue_description ?: 'General Device Inspection & Repair';
            }
        });

        static::created(function ($ticket) {
            if (! empty($ticket->initial_labor_fee) && (float) $ticket->initial_labor_fee > 0 && $ticket->laborItems()->count() === 0) {
                $ticket->items()->create([
                    'company_id' => $ticket->company_id,
                    'tenant_id' => $ticket->tenant_id,
                    'item_type' => RepairTicketItem::TYPE_SERVICE_LABOR,
                    'item_name' => 'Labor / Repair Service Fee',
                    'quantity' => 1,
                    'unit_price' => (float) $ticket->initial_labor_fee,
                    'subtotal' => (float) $ticket->initial_labor_fee,
                    'total' => (float) $ticket->initial_labor_fee,
                    'billed_to_customer' => true,
                ]);
            }
        });
    }

    /* ---------------------------------------------------------------------
     | Dynamic Calculations & Financial Accessors
     | --------------------------------------------------------------------- */

    public function getPartsCostAttribute(): float
    {
        $parts = round((float) $this->items()->where('item_type', RepairTicketItem::TYPE_SPARE_PART)->sum('total'), 2);

        return $parts > 0 ? $parts : round((float) ($this->attributes['parts_cost'] ?? 0), 2);
    }

    public function getLaborFeeAttribute(): float
    {
        $labor = round((float) $this->items()->where('item_type', RepairTicketItem::TYPE_SERVICE_LABOR)->sum('total'), 2);

        return $labor > 0 ? $labor : round((float) ($this->attributes['labor_fee'] ?? 0), 2);
    }

    public function getTotalAmountAttribute(): float
    {
        $total = round((float) $this->items()->sum('total'), 2);
        if ($total > 0) {
            return $total;
        }
        $fallback = round($this->parts_cost + $this->labor_fee, 2);

        return $fallback > 0 ? $fallback : round((float) ($this->attributes['total_amount'] ?? 0), 2);
    }

    public function getBalanceDueAttribute(): float
    {
        $total = $this->total_amount;
        $deposit = (float) $this->advance_deposit;

        return max(0, round($total - $deposit, 2));
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'received', 'active' => '#0284c7',
            'diagnosing' => '#8b5cf6',
            'waiting_parts' => '#f59e0b',
            'in_progress' => '#3b82f6',
            'ready', 'repaired' => '#10b981',
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

    /* ---------------------------------------------------------------------
     | Backward Compatibility Accessors & Mutators
     | --------------------------------------------------------------------- */

    public function getDeviceTypeAttribute(): ?string
    {
        return $this->category?->name ?? 'General Device';
    }

    public function setDeviceTypeAttribute($value): void
    {
        // No-op
    }

    public function setLaborFeeAttribute($value): void
    {
        $val = (float) $value;
        $this->initial_labor_fee = $val;
    }

    public function setPartsCostAttribute($value): void
    {
        $this->temp_parts_cost = (float) $value;
    }

    public function setTotalAmountAttribute($value): void
    {
        $this->temp_total_amount = (float) $value;
    }

    public function getSerialOrImeiAttribute(): ?string
    {
        return $this->serial_number_or_imei;
    }

    public function setSerialOrImeiAttribute(?string $value): void
    {
        $this->attributes['serial_number_or_imei'] = $value;
    }

    public function getPasscodeOrPatternAttribute(): ?string
    {
        return $this->passcode_pattern;
    }

    public function setPasscodeOrPatternAttribute(?string $value): void
    {
        $this->attributes['passcode_pattern'] = $value;
    }

    public function getIssueDescriptionAttribute(): ?string
    {
        return $this->problem_reported;
    }

    public function setIssueDescriptionAttribute(?string $value): void
    {
        $this->attributes['problem_reported'] = $value;
    }

    public function getAdvancePaidAttribute(): float
    {
        return (float) $this->advance_deposit;
    }

    public function setAdvancePaidAttribute($value): void
    {
        $this->attributes['advance_deposit'] = (float) $value;
    }

    public function getDeviceCategoryIdAttribute()
    {
        return $this->category_id;
    }

    public function setDeviceCategoryIdAttribute($value): void
    {
        $this->attributes['category_id'] = $value;
    }

    public function getTechnicianIdAttribute(): ?string
    {
        return $this->assigned_technician_id;
    }

    public function setTechnicianIdAttribute(?string $value): void
    {
        $this->attributes['assigned_technician_id'] = $value;
    }

    public function setStatusAttribute($value): void
    {
        $val = strtolower(trim((string) $value));
        if ($val === 'active') {
            $val = self::STATUS_RECEIVED;
        } elseif ($val === 'repaired') {
            $val = self::STATUS_READY;
        }
        $this->attributes['status'] = $val;
    }
}
