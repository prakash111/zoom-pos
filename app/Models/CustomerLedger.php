<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CustomerLedger extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'customer_id', 'sale_id', 'order_payment_id',
        'type', 'amount', 'balance_after', 'description', 'created_by', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function payment()
    {
        return $this->belongsTo(OrderPayment::class, 'order_payment_id');
    }
}
