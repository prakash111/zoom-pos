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
        'company_id', 'external_id', 'name', 'document', 'person_type', 'email', 'phone', 'source',
        'address', 'city', 'state', 'loyalty_points', 'due_balance', 'state_code', 'gstin', 'taxpayer_type', 'tax_id_label',
        'tax_id', 'is_tax_exempt', 'is_demo',
        'age', 'gender', 'allergies', 'prescribing_doctor', 'doctor_registration_no',
        'custom_fields',
    ];

    protected $casts = [
        'is_tax_exempt' => 'boolean',
        'is_demo' => 'boolean',
        'due_balance' => 'decimal:2',
        'age' => 'integer',
        'custom_fields' => 'array',
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'customer_id');
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

    /**
     * Accessor for customer company name, checking custom_fields and person_type.
     */
    public function getCompanyNameAttribute(): ?string
    {
        return $this->custom_fields['company_name']
            ?? $this->custom_fields['company']
            ?? ($this->person_type === 'company' ? $this->name : null);
    }
}
