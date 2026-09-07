<?php

namespace Modules\pharmacy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $company_id
 * @property string $product_name
 * @property \Illuminate\Support\Carbon|null $expiry_date
 */
class DrugBatch extends Model
{
    protected $table = 'pharmacy_mod_drug_batches';

    protected $guarded = [];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity' => 'decimal:2',
        'mrp' => 'decimal:2',
        'cost_price' => 'decimal:2',
    ];
}
