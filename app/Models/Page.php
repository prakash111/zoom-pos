<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    protected $fillable = [
        'title', 'slug', 'content', 'meta_description',
        'is_active', 'show_in_footer', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_in_footer' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Page $page) {
            if (empty($page->slug)) {
                $page->slug = static::uniqueSlugFrom($page->title);
            }
        });
    }

    public static function uniqueSlugFrom(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'page';
        $slug = $base;
        $suffix = 1;

        while (
            static::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    public function creator()
    {
        return $this->belongsTo(PlatformAdmin::class, 'created_by');
    }
}
