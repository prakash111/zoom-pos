<?php

namespace App\Models;

class TenantSetting
{
    /**
     * Get a tenant company setting value with optional fallback default.
     * Supports both TenantSetting::get($key, $default) and TenantSetting::get($tenantId, $key, $default).
     */
    public static function get(mixed ...$args): mixed
    {
        $tenantId = null;
        $key = '';
        $default = null;

        if (count($args) === 1) {
            $key = (string) $args[0];
        } elseif (count($args) === 2) {
            if (is_numeric($args[0]) || (is_string($args[0]) && (str_starts_with($args[0], 'emp_') || str_starts_with($args[0], 'ten_')))) {
                $tenantId = (string) $args[0];
                $key = (string) $args[1];
            } else {
                $key = (string) $args[0];
                $default = $args[1];
            }
        } elseif (count($args) >= 3) {
            $tenantId = is_object($args[0]) ? (string) ($args[0]->id ?? '') : (string) $args[0];
            $key = (string) $args[1];
            $default = $args[2];
        }

        if (! $tenantId) {
            $tenantId = app()->bound('tenant.company_id')
                ? (string) app('tenant.company_id')
                : (string) (auth('web')->user()?->company_id ?? auth('tenant_api')->user()?->company_id ?? auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? '');
        }

        if ($tenantId !== '' && \Illuminate\Support\Facades\Schema::hasTable('tenant_settings')) {
            try {
                $val = \Illuminate\Support\Facades\DB::table('tenant_settings')
                    ->where('tenant_id', $tenantId)
                    ->where('key', $key)
                    ->value('value');

                if ($val !== null && $val !== '') {
                    $decoded = json_decode($val, true);
                    return json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
                }
            } catch (\Throwable) {}
        }

        return tenant_setting(...$args);
    }

    /**
     * Set a tenant company setting value.
     */
    public static function set(mixed ...$args): void
    {
        $tenantId = null;
        $key = '';
        $value = null;

        if (count($args) === 2) {
            $key = (string) $args[0];
            $value = $args[1];
        } elseif (count($args) >= 3) {
            $tenantId = is_object($args[0]) ? (string) ($args[0]->id ?? '') : (string) $args[0];
            $key = (string) $args[1];
            $value = $args[2];
        }

        if (! $tenantId) {
            $tenantId = app()->bound('tenant.company_id')
                ? (string) app('tenant.company_id')
                : (string) (auth('web')->user()?->company_id ?? auth('tenant_api')->user()?->company_id ?? auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? '1');
        }

        $storedVal = is_array($value) ? json_encode($value) : (string) $value;

        if (\Illuminate\Support\Facades\Schema::hasTable('tenant_settings')) {
            try {
                \Illuminate\Support\Facades\DB::table('tenant_settings')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'key' => $key],
                    ['value' => $storedVal, 'updated_at' => now()]
                );
            } catch (\Throwable) {}
        }

        tenant_set_setting($tenantId, $key, $value);

        // Invalidate tenant navigation & drawer caches
        \Illuminate\Support\Facades\Cache::forget("tenant_{$tenantId}_drawer_menu");
        \Illuminate\Support\Facades\Cache::forget("navigation_menu_{$tenantId}");
        \Illuminate\Support\Facades\Cache::forget("tenant_{$tenantId}_drawer");
        \Illuminate\Support\Facades\Cache::forget("drawer_menu_{$tenantId}");
        \Illuminate\Support\Facades\Cache::forget("tenant_{$tenantId}_menu");
    }
}
