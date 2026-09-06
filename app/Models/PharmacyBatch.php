<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyBatch extends Model
{
    use BelongsToCompany;

    protected $table = 'pharmacy_batches';

    protected $fillable = [
        'company_id',
        'tenant_id',
        'product_id',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'cost_price',
        'selling_price',
        'stock_qty',
        'alert_days_before_expiry',
        'is_active',
        'is_demo',
        'rack_location',
        // Adapter aliases used by the domain-neutral ProductBatch contract.
        'stock_quantity',
        'unit_cost',
    ];

    protected $appends = [
        'days_until_expiry',
        'expiry_status',
        'expiry_color',
        'stock_quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'manufacturing_date' => 'date',
            'expiry_date' => 'date',
            'cost_price' => 'float',
            'selling_price' => 'float',
            'stock_qty' => 'integer',
            'alert_days_before_expiry' => 'integer',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getStockQuantityAttribute(): int
    {
        return (int) $this->stock_qty;
    }

    public function setStockQuantityAttribute(int|float|string $value): void
    {
        $this->attributes['stock_qty'] = (int) $value;
    }

    public function getUnitCostAttribute(): float
    {
        return (float) $this->cost_price;
    }

    public function setUnitCostAttribute(int|float|string $value): void
    {
        $this->attributes['cost_price'] = (float) $value;
    }

    public function getDaysUntilExpiryAttribute(): int
    {
        if (! $this->expiry_date) {
            return 9999;
        }

        return (int) Carbon::today()->diffInDays(Carbon::parse($this->expiry_date), false);
    }

    public function getExpiryStatusAttribute(): string
    {
        $days = $this->days_until_expiry;
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= ($this->alert_days_before_expiry ?? 90)) {
            return 'near_expiry';
        }

        return 'safe';
    }

    public function getExpiryColorAttribute(): string
    {
        return match ($this->expiry_status) {
            'expired' => '#ef4444',
            'near_expiry' => '#f59e0b',
            default => '#10b981',
        };
    }
}
