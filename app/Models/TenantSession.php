<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tenant bearer-token sessions. Table is literally "sessions" (the legacy
 * wire-contract name) — NOT Laravel's framework HTTP session store, which
 * uses SESSION_DRIVER=file in this app (see .env) to avoid the name clash.
 */
class TenantSession extends Model
{
    protected $table = 'sessions';

    protected $primaryKey = 'token';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['token', 'user_id', 'company_id', 'expires_at', 'revoked', 'impersonated_by', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive($query)
    {
        return $query->where('revoked', false)->where('expires_at', '>', now());
    }

    public function isImpersonation(): bool
    {
        return ! empty($this->impersonated_by);
    }
}
