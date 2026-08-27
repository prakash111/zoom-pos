<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\SyncableModel;
use Illuminate\Database\Eloquent\Model;

class CashRegisterTransaction extends Model
{
    use BelongsToCompany;
    use SyncableModel;

    protected $fillable = [
        'company_id',
        'external_id',
        'cash_register_id',
        'voucher_number',
        'type', // cash_in, cash_out
        'category', // sangria, suprimento, petty_cash, supplier_payment, bank_drop, change_injection, opening_float, other
        'amount',
        'balance_before',
        'balance_after',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getCategoryLabel(): string
    {
        return match ($this->category) {
            'sangria' => 'Cash Withdrawal (Sangria)',
            'suprimento' => 'Cash Addition',
            'petty_cash' => 'Petty Cash Expense',
            'supplier_payment' => 'Supplier / Vendor Payment',
            'bank_drop' => 'Safe / Bank Deposit',
            'change_injection' => 'Change Float Top-up',
            'opening_float' => 'Initial Float',
            default => ($this->type === 'cash_in' ? 'Cash Addition' : 'Cash Withdrawal'),
        };
    }
}
