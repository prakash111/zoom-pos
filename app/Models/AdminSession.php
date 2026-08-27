<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminSession extends Model
{
    protected $primaryKey = 'token';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['token', 'platform_admin_id', 'expires_at', 'revoked', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked' => 'boolean',
        ];
    }

    public function admin()
    {
        return $this->belongsTo(PlatformAdmin::class, 'platform_admin_id');
    }

    public function scopeActive($query)
    {
        return $query->where('revoked', false)->where('expires_at', '>', now());
    }
}
