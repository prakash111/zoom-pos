<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class DynamicSetting extends Model
{
    protected $table = 'platform_system';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /**
     * Retrieve a dynamic configuration value by key with optional fallbacks.
     */
    public static function get(string $key, mixed $default = null, mixed $fallback = null): mixed
    {
        $cacheKey = "dyn_setting_{$key}";
        
        $val = Cache::remember($cacheKey, 3600, function () use ($key) {
            return static::query()->find($key)?->value;
        });

        if ($val !== null && $val !== '') {
            if (is_bool($default) || in_array(strtolower($val), ['true', 'false', '1', '0'], true)) {
                if (in_array(strtolower($val), ['true', '1'], true)) {
                    return true;
                }
                if (in_array(strtolower($val), ['false', '0'], true)) {
                    return false;
                }
            }
            return $val;
        }

        // Contextual Fallbacks
        if ($key === 'platform_logo_url') {
            $pb = PlatformBranding::current();
            $logo = $pb?->getLogoPublicUrl();
            if (! empty($logo)) {
                return $logo;
            }
            return $default ?? $fallback;
        }

        if ($key === 'platform_brand_name') {
            $pb = PlatformBranding::current();
            $name = $pb?->platform_name;
            if (! empty($name)) {
                return $name;
            }
            return $default ?? $fallback ?? config('app.name', 'Smart Inventory');
        }

        if ($key === 'enable_registration_domain_setup') {
            return $default !== null ? (bool) $default : true;
        }

        if ($key === 'show_auth_banner') {
            return $default !== null ? (bool) $default : false;
        }

        return $default ?? $fallback;
    }

    /**
     * Store or update a dynamic configuration value.
     */
    public static function put(string $key, mixed $value): void
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif ($value === null) {
            $value = '';
        } else {
            $value = (string) $value;
        }

        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget("dyn_setting_{$key}");
        Cache::forget('public_settings');
        Cache::forget('platform_branding_settings');
    }

    /**
     * Alias for put.
     */
    public static function set(string $key, mixed $value): void
    {
        static::put($key, $value);
    }

    /**
     * Check if a setting key exists and is non-empty.
     */
    public static function has(string $key): bool
    {
        $val = static::get($key);
        return $val !== null && $val !== '';
    }

    /**
     * Forget/delete a setting key.
     */
    public static function forget(string $key): void
    {
        static::query()->where('key', $key)->delete();
        Cache::forget("dyn_setting_{$key}");
        Cache::forget('public_settings');
        Cache::forget('platform_branding_settings');
    }

    /**
     * Retrieve a tenant-scoped dynamic configuration group.
     */
    public static function getTenantSettings(string $tenantId, string $group, mixed $default = []): mixed
    {
        $cacheKey = "tenant_{$tenantId}_dyn_setting_{$group}";

        return Cache::remember($cacheKey, 3600, function () use ($tenantId, $group, $default) {
            // 1. First check tenant dynamic_settings table
            $records = TenantDynamicSetting::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('group', $group)
                ->get();

            if ($records->isNotEmpty()) {
                // If single consolidated group record with key == $group
                $groupRecord = $records->firstWhere('key', $group);
                if ($groupRecord && is_array($groupRecord->value)) {
                    return $groupRecord->value;
                }

                // If keyed records (e.g. key == channel_id)
                $keyed = [];
                foreach ($records as $r) {
                    if ($r->key !== null && $r->key !== '') {
                        $keyed[$r->key] = $r->value;
                    }
                }
                if (! empty($keyed)) {
                    return $keyed;
                }
            }

            // 2. Fallback to configurations table
            $rawConfig = Configuration::withoutGlobalScopes()
                ->where('company_id', $tenantId)
                ->where('key', $group)
                ->value('value');

            if ($rawConfig !== null && $rawConfig !== '') {
                $decoded = json_decode($rawConfig, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }

            return $default;
        });
    }

    /**
     * Store or update a tenant-scoped dynamic configuration group.
     */
    public static function putTenantSettings(string $tenantId, string $group, mixed $value): void
    {
        // For audio notifications: ensure both flat and nested hierarchies are preserved
        if ($group === 'tenant_audio_notifications' && is_array($value)) {
            if (! isset($value['delayed_orders']) && isset($value['delayed_orders_sound_type'])) {
                $value['delayed_orders'] = [
                    'sound_type' => $value['delayed_orders_sound_type'] ?? 'preset',
                    'preset' => $value['delayed_orders_sound_preset'] ?? 'alarm_siren',
                    'custom_url' => $value['delayed_orders_custom_audio_url'] ?? '',
                    'duration_seconds' => (int) ($value['delayed_orders_duration_seconds'] ?? 15),
                    'recurring_interval' => (int) ($value['delayed_orders_recurring_interval'] ?? 60),
                    'enable_vibration' => filter_var($value['delayed_orders_enable_vibration'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'vibration_pattern' => $value['delayed_orders_vibration_pattern'] ?? 'persistent',
                ];
            }
            if (! isset($value['due_invoices']) && isset($value['due_invoices_sound_type'])) {
                $value['due_invoices'] = [
                    'sound_type' => $value['due_invoices_sound_type'] ?? 'preset',
                    'preset' => $value['due_invoices_sound_preset'] ?? 'kitchen_chime',
                    'custom_url' => $value['due_invoices_custom_audio_url'] ?? '',
                    'recurring_interval' => (int) ($value['due_invoices_recurring_interval'] ?? 0),
                    'enable_vibration' => filter_var($value['due_invoices_enable_vibration'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }
        }

        // Store consolidated record in dynamic_settings table
        TenantDynamicSetting::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'group' => $group,
                'key' => $group,
            ],
            [
                'company_id' => $tenantId,
                'value' => $value,
            ]
        );

        // Store in configurations table for fast direct retrieval
        Configuration::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $tenantId,
                'key' => $group,
            ],
            [
                'value' => is_array($value) ? json_encode($value) : (string) $value,
            ]
        );

        // Invalidate tenant caches
        Cache::forget("tenant_{$tenantId}_dyn_setting_{$group}");
        Cache::forget("tenant_{$tenantId}_bootstrap");
        Cache::forget("tenant_{$tenantId}_settings");
        Cache::forget("tenant_audio_notifications_{$tenantId}");
    }
}
