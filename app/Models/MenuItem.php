<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    protected $fillable = [
        'location',
        'title',
        'type',
        'url',
        'page_id',
        'target',
        'icon',
        'order_index',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order_index' => 'integer',
            'page_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (MenuItem $item) {
            static::clearMenuCache($item->location);
        });

        static::deleted(function (MenuItem $item) {
            static::clearMenuCache($item->location);
        });
    }

    public static function clearMenuCache(?string $location = null): void
    {
        if ($location) {
            cache()->forget('public_menu_'.$location);
        } else {
            cache()->forget('public_menu_header');
            cache()->forget('public_menu_footer_col_1');
            cache()->forget('public_menu_footer_col_2');
        }
    }

    /**
     * Retrieve cached menu items as an array of structured items.
     * Caching plain arrays prevents PHP incomplete object unserialization errors.
     *
     * @return array<int, array{id: int, title: string, type: string, url: string, target: string, icon: ?string, order_index: int}>
     */
    public static function getMenu(string $location): array
    {
        return cache()->rememberForever('public_menu_'.$location, function () use ($location) {
            return static::where('location', $location)
                ->where('is_active', true)
                ->orderBy('order_index')
                ->get(['id', 'title', 'type', 'url', 'target', 'icon', 'order_index'])
                ->map(function (MenuItem $item) {
                    return [
                        'id' => (int) $item->id,
                        'title' => (string) $item->title,
                        'type' => (string) $item->type,
                        'url' => (string) $item->url,
                        'target' => (string) ($item->target ?: '_self'),
                        'icon' => $item->icon ? (string) $item->icon : null,
                        'order_index' => (int) $item->order_index,
                    ];
                })
                ->values()
                ->all();
        });
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }
}
