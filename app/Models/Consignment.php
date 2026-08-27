<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\SyncableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consignment extends Model
{
    use BelongsToCompany;
    use SyncableModel;

    protected $fillable = [
        'company_id',
        'external_id',
        'consignment_number',
        'customer_id',
        'customer_name',
        'user_id',
        'status',
        'dispatched_at',
        'due_date',
        'reconciled_at',
        'total_dispatched_amount',
        'total_sold_amount',
        'total_returned_amount',
        'notes',
        'sale_id',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'due_date' => 'date',
            'reconciled_at' => 'datetime',
            'total_dispatched_amount' => 'decimal:2',
            'total_sold_amount' => 'decimal:2',
            'total_returned_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function recalculateTotals(): void
    {
        $dispatched = 0;
        $sold = 0;
        $returned = 0;

        foreach ($this->items as $item) {
            $dispatched += (float) $item->dispatched_quantity * (float) $item->unit_price;
            $sold += (float) $item->sold_quantity * (float) $item->unit_price;
            $returned += (float) $item->returned_quantity * (float) $item->unit_price;
        }

        $this->update([
            'total_dispatched_amount' => round($dispatched, 2),
            'total_sold_amount' => round($sold, 2),
            'total_returned_amount' => round($returned, 2),
        ]);
    }
}
