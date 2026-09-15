<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Sale
{
    protected $table = 'sales';

    protected static function booted(): void
    {
        static::addGlobalScope('quotation_operation', function (Builder $builder) {
            $builder->where('operation_type', 'quotation');
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function getQuotationNumberAttribute(): ?string
    {
        return $this->sale_number ?: (string) $this->id;
    }
}
