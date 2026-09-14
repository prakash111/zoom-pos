<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLegacyStringId;
use App\Services\Auth\PermissionChecker;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements AuthenticatableContract
{
    use BelongsToCompany, HasFactory, HasLegacyStringId;

    public const ROLE_ADMINISTRATOR = 'administrator';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_SALESPERSON = 'salesperson';

    public const ROLE_CASHIER = 'cashier';

    public const ROLE_STOCK_CLERK = 'stock clerk';

    public const ROLE_FINANCE = 'finance';

    public const ROLE_TECHNICIAN = 'technician';

    public const ROLES = [
        self::ROLE_ADMINISTRATOR => 'Administrator',
        self::ROLE_MANAGER => 'Manager',
        self::ROLE_SALESPERSON => 'Salesperson',
        self::ROLE_CASHIER => 'Cashier',
        self::ROLE_STOCK_CLERK => 'Stock clerk',
        self::ROLE_FINANCE => 'Finance',
        self::ROLE_TECHNICIAN => 'Technician',
    ];

    /** Roles that bypass the permissions matrix entirely (see PermissionChecker). */
    public const PRIVILEGED_ROLES = ['administrator', 'administrador', 'admin', 'superadmin', 'owner'];

    protected $fillable = [
        'company_id', 'name', 'login', 'email', 'password', 'role', 'locale', 'status',
        'is_demo', 'shift', 'is_specialist', 'dock_position',
        'commission_rate', 'commission_type',
        'invitation_code_hash', 'invitation_expires_at', 'email_verified_at',
        'verification_code', 'verification_code_expires_at',
    ];

    protected $hidden = ['password', 'remember_token', 'invitation_code_hash'];

    public function idPrefix(): string
    {
        return 'usr_';
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->login) && ! empty($user->email)) {
                $user->login = explode('@', $user->email)[0];
            }
        });
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_demo' => 'boolean',
            'is_specialist' => 'boolean',
            'commission_rate' => 'decimal:2',
            'invitation_expires_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function getTenantIdAttribute(): ?string
    {
        return (string) ($this->company_id ?? '');
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }

    public function isPrivilegedRole(): bool
    {
        return in_array(strtolower($this->role ?? ''), self::PRIVILEGED_ROLES, true);
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;
        $userRole = strtolower($this->role ?? '');

        foreach ($roles as $r) {
            $r = strtolower($r);
            if (in_array($r, ['tenant_admin', 'tenant-admin', 'admin', 'administrator', 'owner'], true)) {
                if ($this->isPrivilegedRole()) {
                    return true;
                }
            }
            if ($userRole === $r) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $module, string $action = 'view'): bool
    {
        return app(PermissionChecker::class)->allows($this, $module, $action);
    }
}
