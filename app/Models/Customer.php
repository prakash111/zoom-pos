<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksSyncState;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToCompany;
    use TracksSyncState;

    protected $fillable = [
        'company_id', 'external_id', 'name', 'document', 'person_type', 'email', 'phone',
        'address', 'city', 'state', 'loyalty_points', 'due_balance', 'state_code', 'gstin', 'taxpayer_type', 'tax_id_label',
        'tax_id', 'is_tax_exempt', 'is_demo',
        'age', 'gender', 'allergies', 'prescribing_doctor', 'doctor_registration_no',
    ];

    protected $casts = [
        'is_tax_exempt' => 'boolean',
        'is_demo' => 'boolean',
        'due_balance' => 'decimal:2',
        'age' => 'integer',
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function ledgerEntries()
    {
        return $this->hasMany(CustomerLedger::class)->orderByDesc('created_at');
    }

    /**
     * Cached running balance kept in sync by CustomerLedgerService — the
     * authoritative source is the customer_ledgers table, not a live sum
     * over sales.due_amount.
     */
    public function getTotalDueAttribute(): float
    {
        return (float) $this->due_balance;
    }
}
