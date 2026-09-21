<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantInquiry extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'tenant_inquiries';

    protected $fillable = [
        'company_id',
        'tenant_id',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'ip_address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Keep tenant_id synced with company_id.
     */
    public function getTenantIdAttribute(): ?string
    {
        return $this->attributes['tenant_id'] ?? $this->attributes['company_id'] ?? null;
    }

    public function setTenantIdAttribute(?string $value): void
    {
        $this->attributes['tenant_id'] = $value;
        if (empty($this->attributes['company_id'])) {
            $this->attributes['company_id'] = $value;
        }
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('status', 'unread');
    }

    public function scopeContacted(Builder $query): Builder
    {
        return $query->where('status', 'contacted');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    public function scopeFilter(Builder $query, ?string $search = null, ?string $status = null): Builder
    {
        if ($status && in_array(strtolower($status), ['unread', 'contacted', 'closed'], true)) {
            $query->where('status', strtolower($status));
        }

        if ($search && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhere('message', 'like', $term);
            });
        }

        return $query;
    }
}
