<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContactInquiry extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_READ = 'read';
    public const STATUS_REPLIED = 'replied';

    protected $fillable = [
        'name',
        'email',
        'store_type',
        'phone',
        'subject',
        'message',
        'custom_fields',
        'status',
        'ip_address',
    ];

    protected $casts = [
        'custom_fields' => 'array',
    ];

    public function isNew(): bool
    {
        return $this->status === self::STATUS_NEW;
    }

    public function isRead(): bool
    {
        return $this->status === self::STATUS_READ;
    }

    public function isReplied(): bool
    {
        return $this->status === self::STATUS_REPLIED;
    }

    public function markAsRead(): bool
    {
        return $this->update(['status' => self::STATUS_READ]);
    }

    public function markAsReplied(): bool
    {
        return $this->update(['status' => self::STATUS_REPLIED]);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = '%' . trim($term) . '%';

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('phone', 'like', $term)
                ->orWhere('subject', 'like', $term)
                ->orWhere('store_type', 'like', $term)
                ->orWhere('message', 'like', $term);
        });
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status) || $status === 'all') {
            return $query;
        }

        return $query->where('status', $status);
    }
}
