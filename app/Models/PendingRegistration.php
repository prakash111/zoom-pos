<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    use HasLegacyStringId;

    protected $fillable = ['email', 'payload', 'otp_hash', 'attempts', 'expires_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function idPrefix(): string
    {
        return 'pend_';
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
