<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\SyncableModel;
use Illuminate\Database\Eloquent\Model;

class VendorBill extends Model
{
    use BelongsToCompany;
    use SyncableModel;

    protected $fillable = [
        'company_id',
        'external_id',
        'supplier_id',
        'vendor_name',
        'bill_number',
        'category',
        'title',
        'amount',
        'tax_amount',
        'paid_amount',
        'due_amount',
        'status',
        'bill_date',
        'due_date',
        'paid_at',
        'payment_method',
        'attachment_path',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'bill_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments()
    {
        return $this->hasMany(VendorBillPayment::class, 'vendor_bill_id')->orderByDesc('payment_date');
    }

    public function getEffectiveVendorNameAttribute(): string
    {
        return $this->supplier?->name ?: ($this->vendor_name ?: 'General Vendor');
    }

    public function isOverdue(): bool
    {
        if ($this->status === 'paid' || $this->status === 'cancelled') {
            return false;
        }

        return $this->due_date && $this->due_date->isPast();
    }
}
