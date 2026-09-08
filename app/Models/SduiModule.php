<?php

namespace App\Models;

use App\Services\Localization\LocalizationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SduiModule extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'layout_type',
        'features',
        'routes',
        'navigation',
        'translation_keys',
        'is_active',
        'registration_allowed',
        'sort_order',
        'version',
        'author',
        'min_system_version',
        'source_type',
        'package_path',
        'installed_at',
        'requires_license',
        'license_status',
        'license_key_hash',
        'license_key_prefix',
        'license_key_encrypted',
        'license_driver',
        'license_buyer',
        'license_verified_at',
        'license_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'routes' => 'array',
            'navigation' => 'array',
            'translation_keys' => 'array',
            'is_active' => 'boolean',
            'registration_allowed' => 'boolean',
            'sort_order' => 'integer',
            'installed_at' => 'datetime',
            'requires_license' => 'boolean',
            'license_key_encrypted' => 'encrypted',
            'license_verified_at' => 'datetime',
            'license_expires_at' => 'datetime',
        ];
    }

    public function screens(): HasMany
    {
        return $this->hasMany(SduiScreen::class);
    }

    /**
     * A module is usable when it needs no license, or when it holds one that is
     * currently marked active. The daily `license:check-status` job keeps the
     * `is_active` ⇒ licensed invariant true.
     */
    public function isLicensed(): bool
    {
        return ! $this->requires_license || $this->license_status === 'active';
    }

    public function licenseIsExpired(): bool
    {
        return $this->license_expires_at !== null && $this->license_expires_at->isPast();
    }

    /**
     * Package modules whose license must be re-verified on a schedule.
     */
    public function scopeLicenseManaged($query)
    {
        return $query->where('source_type', 'package')->where('requires_license', true);
    }

    protected static function booted(): void
    {
        static::saving(function (SduiModule $module): void {
            $module->slug = Str::slug($module->slug ?: $module->name);

            $navigation = $module->navigation ?? [];
            if (is_array($navigation)) {
                $normalizedNav = [];
                foreach ($navigation as $sectionIndex => $section) {
                    if (is_array($section)) {
                        if (! isset($section['key']) && isset($section['id'])) {
                            $section['key'] = (string) $section['id'];
                        }
                        if (isset($section['items']) && is_array($section['items'])) {
                            $section['items'] = self::normalizeNavigationItems($section['items']);
                        }
                    }
                    $normalizedNav[] = $section;
                }
                $module->navigation = $normalizedNav;
            }

            foreach (($module->navigation ?? []) as $sectionIndex => $section) {
                if (! is_array($section)
                    || trim((string) ($section['key'] ?? '')) === ''
                    || ! is_array($section['items'] ?? null)) {
                    throw new InvalidArgumentException("Invalid SDUI navigation section at index {$sectionIndex}.");
                }

                self::validateNavigationItems($section['items'], "navigation.{$sectionIndex}.items");
            }
        });

        static::saved(function (SduiModule $module): void {
            $strings = $module->translation_keys ?? [];
            $strings[$module->name] ??= $module->name;
            if (filled($module->description)) {
                $strings[$module->description] ??= $module->description;
            }

            app(LocalizationService::class)
                ->registerModuleTranslations($module->slug, $strings);
        });
    }

    private static function normalizeNavigationItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $normalized[] = $item;

                continue;
            }

            if (! isset($item['key'])) {
                if (isset($item['id'])) {
                    $item['key'] = (string) $item['id'];
                } elseif (! empty($item['title'])) {
                    $item['key'] = Str::slug($item['title'], '_');
                } elseif (! empty($item['route'])) {
                    $item['key'] = Str::slug(basename($item['route']), '_');
                } else {
                    $item['key'] = 'item_'.$index;
                }
            }

            if (! isset($item['target_endpoint']) && isset($item['route'])) {
                $item['target_endpoint'] = $item['route'];
            }

            if (isset($item['children']) && is_array($item['children'])) {
                $item['children'] = self::normalizeNavigationItems($item['children']);
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    private static function validateNavigationItems(array $items, string $path): void
    {
        foreach ($items as $index => $item) {
            if (! is_array($item) || trim((string) ($item['key'] ?? '')) === '') {
                throw new InvalidArgumentException("Invalid SDUI navigation item at {$path}.{$index}.");
            }

            $children = $item['children'] ?? [];
            if (is_array($children) && $children !== []) {
                self::validateNavigationItems($children, "{$path}.{$index}.children");

                continue;
            }

            $endpoint = trim((string) ($item['target_endpoint'] ?? ''));
            if (! str_starts_with($endpoint, '/api/')) {
                throw new InvalidArgumentException("SDUI navigation leaf {$path}.{$index} requires a same-origin target_endpoint.");
            }
        }
    }
}
