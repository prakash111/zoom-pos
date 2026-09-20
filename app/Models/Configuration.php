<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'key', 'value'];

    public static function setForCompany(mixed $companyId, string $key, mixed $value): self
    {
        return static::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $companyId, 'key' => $key],
            ['value' => is_scalar($value) ? (string) $value : json_encode($value)]
        );
    }

    public static function getForCompany(mixed $companyId, string $key, mixed $default = null): mixed
    {
        $config = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->first();

        return $config ? $config->value : $default;
    }
}
