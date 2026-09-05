<?php

namespace App\Models;

use App\Services\Auth\PermissionChecker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A named permission set. `is_system` rows (company_id NULL) are the built-in
 * roles shared by every tenant; the rest are tenant-authored custom roles.
 * `permissions` is a { module: [action, ...] } map using the same vocabulary
 * as PermissionChecker::MODULE_ACTIONS.
 *
 * Deliberately NOT BelongsToCompany: the global company scope would hide the
 * shared system rows. Callers scope explicitly via forTenant().
 */
class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'is_system',
        'description',
        'permissions',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_demo' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /* ---------------------------------------------------------------------
     | Query helpers
     | --------------------------------------------------------------------- */

    /**
     * Built-in system roles plus the given tenant's own custom roles.
     */
    public function scopeForTenant($query, ?string $companyId)
    {
        return $query->where(function ($q) use ($companyId) {
            $q->where('is_system', true);
            if ($companyId !== null) {
                $q->orWhere('company_id', $companyId);
            }
        });
    }

    /**
     * Resolve the effective { module: [actions] } map for a role slug, giving a
     * tenant's own custom role precedence over a system role of the same slug.
     * Returns null when no matching role row exists.
     *
     * @return array<string, list<string>>|null
     */
    public static function permissionMapFor(?string $companyId, ?string $slug): ?array
    {
        $slug = strtolower(trim((string) $slug));
        if ($slug === '') {
            return null;
        }

        $role = static::query()
            ->forTenant($companyId)
            ->where('slug', $slug)
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 1 ELSE 0 END') // tenant row first
            ->first();

        if ($role === null) {
            return null;
        }

        $map = is_array($role->permissions) ? $role->permissions : [];

        // Normalise: keep only known modules/actions and drop empties.
        $clean = [];
        foreach ($map as $module => $actions) {
            if (! is_array($actions) || ! isset(PermissionChecker::MODULE_ACTIONS[$module])) {
                continue;
            }
            $valid = array_values(array_intersect(
                array_map('strval', $actions),
                array_keys(PermissionChecker::getActionsForModule($module))
            ));
            if ($valid !== []) {
                $clean[$module] = $valid;
            }
        }

        return $clean;
    }

    public static function makeSlug(string $name): string
    {
        return Str::slug($name, '_') ?: 'role_'.Str::lower(Str::random(6));
    }
}
