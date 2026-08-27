<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalSetting extends Model
{
    protected $table = 'platform_system';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /**
     * Retrieve a global setting by key with optional fallback.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::query()->find($key)?->value ?? $default;
    }

    /**
     * Store or update a global setting by key.
     */
    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
