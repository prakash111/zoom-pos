<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairTicketItem extends Model
{
    use BelongsToCompany;

    public const TYPE_SPARE_PART = 'spare_part';

    public const TYPE_SERVICE_LABOR = 'service_labor';

    protected $table = 'repair_ticket_items';

    protected $fillable = [
        'company_id',
        'tenant_id',
        'ticket_id',
        'product_id',
        'item_name',
        'item_type',
        'quantity',
        'unit_price',
        'subtotal',
        'tax_id',
        'tax_amount',
        'total',
        'billed_to_customer',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'billed_to_customer' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $sync = static function (RepairTicketItem $item): void {
            RepairTicket::withoutGlobalScope('company')->find($item->ticket_id)?->syncStoredTotal();
        };

        static::saved($sync);
        static::deleted($sync);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class, 'ticket_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class, 'tax_id');
    }

    public function getPartNameAttribute(): ?string
    {
        return $this->item_name;
    }
}
