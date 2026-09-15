<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Sale
{
    protected $table = 'sales';

    protected static function booted(): void
    {
        static::addGlobalScope('invoice_operation', function (Builder $builder) {
            $builder->where(function ($q) {
                $q->where('operation_type', 'sale')
                    ->orWhereNull('operation_type');
            });
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function saleItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }
}
