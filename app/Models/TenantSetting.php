<?php

namespace App\Models;

class TenantSetting
{
    /**
     * Get a tenant company setting value with optional fallback default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return tenant_setting($key, $default);
    }
}
