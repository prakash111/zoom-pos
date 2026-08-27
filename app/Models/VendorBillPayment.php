<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\SyncableModel;
use Illuminate\Database\Eloquent\Model;

class VendorBillPayment extends Model
{
    use BelongsToCompany;
    use SyncableModel;

    protected $fillable = [
        'company_id',
        'external_id',
        'vendor_bill_id',
        'amount',
        'payment_method',
        'payment_date',
        'reference_number',
        'attachment_path',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function vendorBill()
    {
        return $this->belongsTo(VendorBill::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
