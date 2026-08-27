<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentItem extends Model
{
    protected $fillable = [
        'consignment_id',
        'product_id',
        'product_name',
        'dispatched_quantity',
        'returned_quantity',
        'sold_quantity',
        'unit_price',
        'sold_total',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_quantity' => 'decimal:3',
            'returned_quantity' => 'decimal:3',
            'sold_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'sold_total' => 'decimal:2',
        ];
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
