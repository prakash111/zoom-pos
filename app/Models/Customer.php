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
        'company_id', 'external_id', 'name', 'document', 'person_type', 'email', 'password', 'auth_token', 'phone', 'source',
        'address', 'city', 'state', 'loyalty_points', 'due_balance', 'state_code', 'gstin', 'taxpayer_type', 'tax_id_label',
        'tax_id', 'is_tax_exempt', 'is_demo',
        'age', 'gender', 'allergies', 'prescribing_doctor', 'doctor_registration_no',
        'date_of_birth', 'avatar_url',
        'custom_fields',
        'is_verified', 'verification_code', 'verification_code_expires_at', 'verified_at',
    ];

    protected $hidden = [
        'password',
        'auth_token',
        'verification_code',
    ];

    protected $casts = [
        'is_tax_exempt' => 'boolean',
        'is_demo' => 'boolean',
        'is_verified' => 'boolean',
        'due_balance' => 'decimal:2',
        'age' => 'integer',
        'date_of_birth' => 'date',
        'verification_code_expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'custom_fields' => 'array',
    ];

    public function isVerified(): bool
    {
        return (bool) $this->is_verified;
    }

    public function markVerified(): void
    {
        $this->is_verified = true;
        $this->verified_at = now();
        $this->verification_code = null;
        $this->verification_code_expires_at = null;
        $this->save();
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function wishlists()
    {
        return $this->hasMany(CustomerWishlist::class);
    }

    public function wishlistProducts()
    {
        return $this->belongsToMany(Product::class, 'customer_wishlists', 'customer_id', 'product_id')->withTimestamps();
    }

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
