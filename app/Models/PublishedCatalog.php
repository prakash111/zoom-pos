<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class PublishedCatalog extends Model
{
    use BelongsToCompany;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['company_id', 'title', 'product_ids', 'expires_at'];

    protected function casts(): array
    {
        return [
            'product_ids' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $catalog) {
            if (empty($catalog->id)) {
                $catalog->id = bin2hex(random_bytes(16));
            }
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
