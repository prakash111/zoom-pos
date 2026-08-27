<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PlatformAdmin extends Authenticatable implements AuthenticatableContract
{
    use HasFactory, HasLegacyStringId;

    /** super_admin bypasses every role check; others are enumerable. */
    public const ROLES = ['super_admin', 'suporte_admin', 'financeiro_admin', 'suporte_agente'];

    protected $fillable = ['name', 'email', 'password', 'role', 'status'];

    protected $hidden = ['password', 'remember_token'];

    public function idPrefix(): string
    {
        return 'padm_';
    }

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function hasRole(string $role): bool
    {
        return $this->isSuperAdmin() || $this->role === $role;
    }

    public static function normalizeRole(?string $role): string
    {
        $role = strtolower(trim((string) $role));

        return in_array($role, self::ROLES, true) ? $role : 'suporte_agente';
    }
}
