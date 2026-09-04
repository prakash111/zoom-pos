<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\SyncableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrder extends Model
{
    use BelongsToCompany;
    use SyncableModel;

    public const STATUS_RECEIVED = 'received';
    public const STATUS_UNDER_DIAGNOSIS = 'under_diagnosis';
    public const STATUS_WAITING_PARTS_APPROVAL = 'waiting_parts_approval';
    public const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
    public const STATUS_DELIVERED_SETTLED = 'delivered_settled';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_RECEIVED => [
            'label' => 'Received',
            'pt_label' => 'Recebido / Entrada',
            'color' => 'blue',
            'icon' => '📥',
            'badge_classes' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/80 dark:text-blue-300 border-blue-200 dark:border-blue-800',
        ],
        self::STATUS_UNDER_DIAGNOSIS => [
            'label' => 'Under Diagnosis',
            'pt_label' => 'Em Diagnóstico',
            'color' => 'amber',
            'icon' => '🔍',
            'badge_classes' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        ],
        self::STATUS_WAITING_PARTS_APPROVAL => [
            'label' => 'Waiting Parts / Approval',
            'pt_label' => 'Aguardando Peças / Aprovação',
            'color' => 'purple',
            'icon' => '⏳',
            'badge_classes' => 'bg-purple-100 text-purple-800 dark:bg-purple-950/80 dark:text-purple-300 border-purple-200 dark:border-purple-800',
        ],
        self::STATUS_READY_FOR_PICKUP => [
            'label' => 'Ready for Pickup',
            'pt_label' => 'Pronto para Retirada',
            'color' => 'emerald',
            'icon' => '✅',
            'badge_classes' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
        ],
        self::STATUS_DELIVERED_SETTLED => [
            'label' => 'Delivered & Settled',
            'pt_label' => 'Entregue e Finalizado',
            'color' => 'slate',
            'icon' => '🤝',
            'badge_classes' => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        ],
        self::STATUS_CANCELLED => [
            'label' => 'Cancelled',
            'pt_label' => 'Cancelado',
            'color' => 'rose',
            'icon' => '✕',
            'badge_classes' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300 border-rose-200 dark:border-rose-800',
        ],
    ];

    public const PRIORITIES = [
        'low' => ['label' => 'Low', 'color' => 'slate'],
        'normal' => ['label' => 'Normal', 'color' => 'blue'],
        'high' => ['label' => 'High', 'color' => 'amber'],
        'urgent' => ['label' => 'Urgent', 'color' => 'rose'],
    ];

    protected $fillable = [
        'company_id',
        'external_id',
        'order_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'equipment_name',
        'brand_model',
        'serial_number',
        'reported_defect',
        'technical_diagnosis',
        'parts_used',
        'parts_total',
        'labor_cost',
        'discount',
        'total_amount',
        'status',
        'is_demo',
        'priority',
        'warranty_period',
        'warranty_terms',
        'received_at',
        'completed_at',
        'delivered_at',
        'technician_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
            'parts_used' => 'array',
            'parts_total' => 'decimal:2',
            'labor_cost' => 'decimal:2',
            'discount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getTicketNumberAttribute(): string
    {
        return $this->order_number ?? '';
    }

    public function getEquipmentBrandAttribute(): string
    {
        return $this->brand_model ?? '';
    }

    public function getPartsSubtotalAttribute(): float
    {
        return (float) ($this->parts_total ?? 0);
    }

    public function getGrandTotalAttribute(): float
    {
        return (float) ($this->total_amount ?? 0);
    }

    public function getStatusInfo(): array
    {
        return self::STATUSES[$this->status] ?? [
            'label' => ucfirst(str_replace('_', ' ', $this->status)),
            'pt_label' => ucfirst(str_replace('_', ' ', $this->status)),
            'color' => 'slate',
            'icon' => '📦',
            'badge_classes' => 'bg-slate-100 text-slate-700',
        ];
    }

    public function recalculateTotals(): void
    {
        $partsTotal = 0.0;
        foreach ($this->parts_used ?? [] as $part) {
            $qty = (float) ($part['quantity'] ?? 1);
            $price = (float) ($part['unit_price'] ?? 0);
            $partsTotal += ($qty * $price);
        }

        $labor = (float) ($this->labor_cost ?? 0);
        $disc = (float) ($this->discount ?? 0);
        $total = max(0, $partsTotal + $labor - $disc);

        $this->update([
            'parts_total' => round($partsTotal, 2),
            'total_amount' => round($total, 2),
        ]);
    }

    public static function generateOrderNumber(string $companyId): string
    {
        $year = now()->format('Y');
        $count = static::where('company_id', $companyId)
            ->whereYear('created_at', now()->year)
            ->count();

        $seq = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        return "SO-{$year}-{$seq}";
    }
}
