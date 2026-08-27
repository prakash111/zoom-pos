<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantTranslation extends Model
{
    protected $table = 'tenant_translations';

    protected $fillable = [
        'tenant_id',
        'locale',
        'group',
        'key',
        'value',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }

    /**
     * Backward-compatible accessor for company_id.
     */
    public function getCompanyIdAttribute(): ?string
    {
        return $this->attributes['tenant_id'] ?? null;
    }

    /**
     * Backward-compatible mutator for company_id.
     */
    public function setCompanyIdAttribute(?string $value): void
    {
        $this->attributes['tenant_id'] = $value;
    }
}
