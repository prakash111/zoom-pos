<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\SyncableModel;
use Illuminate\Database\Eloquent\Model;

class OrderPayment extends Model
{
    use BelongsToCompany;
    use SyncableModel;

    protected $fillable = [
        'company_id',
        'external_id',
        'sale_id',
        'cash_register_id',
        'payment_method',
        'amount',
        'tendered',
        'change_returned',
        'merchant_fee_percentage',
        'merchant_fee_amount',
        'net_amount',
        'installments',
        'reference_number',
        'pix_key',
        'pix_payload',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tendered' => 'decimal:2',
            'change_returned' => 'decimal:2',
            'merchant_fee_percentage' => 'decimal:2',
            'merchant_fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'installments' => 'integer',
        ];
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }
}
