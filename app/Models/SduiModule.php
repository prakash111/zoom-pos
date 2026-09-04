<?php

namespace App\Models;

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
        ];
    }

    public function screens(): HasMany
    {
        return $this->hasMany(SduiScreen::class);
    }

    protected static function booted(): void
    {
        static::saving(function (SduiModule $module): void {
            $module->slug = Str::slug($module->slug ?: $module->name);

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

            app(\App\Services\Localization\LocalizationService::class)
                ->registerModuleTranslations($module->slug, $strings);
        });
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
