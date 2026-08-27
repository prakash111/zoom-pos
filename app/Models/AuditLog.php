<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['company_id', 'user_id', 'action', 'details', 'result', 'ip'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    /** Writes one row. company_id/user_id null = platform-level event. */
    public static function record(string $action, ?string $companyId = null, ?string $userId = null, array $details = [], string $result = 'success'): self
    {
        return static::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'result' => $result,
            'ip' => request()?->ip(),
        ]);
    }
}
