<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TenantApiKey extends Model
{
    use BelongsToCompany, HasLegacyStringId;

    protected $fillable = [
        'company_id', 'user_id', 'name', 'token', 'permissions', 'last_used_at', 'active',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'last_used_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function idPrefix(): string
    {
        return 'key_';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function generateToken(): string
    {
        return 'zk_live_'.Str::random(40);
    }

    public function hasPermission(string $permission): bool
    {
        if (empty($this->permissions) || in_array('*', $this->permissions, true)) {
            return true;
        }

        return in_array($permission, $this->permissions, true);
    }
}
