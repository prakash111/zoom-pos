<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Database\Eloquent\Model;

class ActivationCode extends Model
{
    use HasLegacyStringId;

    protected $fillable = [
        'code_hash', 'code_prefix', 'plan_name', 'max_uses', 'current_uses',
        'validity_days', 'expires_at', 'failed_attempts', 'revoked', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked' => 'boolean',
        ];
    }

    public function idPrefix(): string
    {
        return 'code_';
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_name', 'name');
    }
}
