<?php

namespace App\Models;

use App\Services\Sdui\SchemaValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SduiScreen extends Model
{
    protected $fillable = [
        'sdui_module_id',
        'key',
        'title',
        'permission',
        'schema',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(SduiModule::class, 'sdui_module_id');
    }

    protected static function booted(): void
    {
        static::saving(function (SduiScreen $screen): void {
            $screen->key = Str::slug($screen->key ?: $screen->title);
            $schema = $screen->schema ?? [];
            $schema['layout'] = $schema['layout'] ?? 'scroll_view';
            $schema['components'] = $schema['components'] ?? [];

            $errors = app(SchemaValidator::class)->validate($schema);
            if ($errors !== []) {
                throw new InvalidArgumentException('Invalid SDUI screen schema: '.implode(' ', $errors));
            }
        });
    }
}
