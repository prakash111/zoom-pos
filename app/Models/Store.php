<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Store extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'tenant_id',
        'name',
        'code',
        'branch_code',
        'phone',
        'email',
        'address',
        'tax_id',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'pincode',
        'receipt_header',
        'receipt_footer',
        'invoice_prefix',
        'is_active',
        'is_primary',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_primary' => 'boolean',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Store $store) {
            if (empty($store->tenant_id) && ! empty($store->company_id)) {
                $store->tenant_id = $store->company_id;
            } elseif (empty($store->company_id) && ! empty($store->tenant_id)) {
                $store->company_id = $store->tenant_id;
            }

            if (empty($store->branch_code) && ! empty($store->code)) {
                $store->branch_code = $store->code;
            } elseif (empty($store->code) && ! empty($store->branch_code)) {
                $store->code = $store->branch_code;
            }

            if (empty($store->address) && ! empty($store->address_line_1)) {
                $store->address = $store->effective_address;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')->withPivot('role_id');
    }

    public function getEffectiveTaxIdAttribute(): string
    {
        return (string) ($this->tax_id ?: ($this->tenant->tax_id ?? ''));
    }

    public function getEffectiveAddressAttribute(): string
    {
        if ($this->address_line_1) {
            $parts = array_filter([
                $this->address_line_1,
                $this->address_line_2,
                $this->city,
                trim(($this->state ?? '').' '.($this->pincode ?? '')),
            ]);
            return implode(', ', $parts);
        }

        return (string) ($this->address ?: ($this->tenant->address ?? ''));
    }

    public function getEffectivePhoneAttribute(): string
    {
        return (string) ($this->phone ?: ($this->tenant->phone ?? ''));
    }
}
