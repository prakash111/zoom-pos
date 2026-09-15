<?php

use App\Models\Company;
use App\Models\Configuration;
use App\Models\GlobalSetting;
use App\Models\PlatformBranding;
use App\Support\HtmlSanitizer;

if (! function_exists('clean_html')) {
    /**
     * Clean and sanitize rich-text user HTML to prevent XSS.
     */
    function clean_html(?string $html): string
    {
        return HtmlSanitizer::clean($html);
    }
}

if (! function_exists('setting')) {
    /**
     * Get a global platform setting value with fallback default.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        $val = GlobalSetting::get($key, $default);
        if ($val === 'true' || $val === '1' || $val === 1 || $val === true) {
            return true;
        }
        if ($val === 'false' || $val === '0' || $val === 0 || $val === false) {
            return false;
        }

        return $val;
    }
}

if (! function_exists('set_setting')) {
    /**
     * Store or update a global platform setting value.
     */
    function set_setting(string $key, mixed $value): void
    {
        GlobalSetting::set($key, $value);
    }
}

if (! function_exists('get_appearance_settings')) {
    /**
     * Retrieve appearance and visual theme customization settings for landing views.
     */
    function get_appearance_settings(): array
    {
        $branding = PlatformBranding::current();

        return [
            'theme' => setting('landing_page_theme', 'theme_fast'),
            'primary_color' => $branding->primary_color ?? '#4f46e5',
            'landing_primary_color' => $branding->landing_primary_color ?? '#10b981',
            'landing_accent_color' => $branding->landing_accent_color ?? '#d7f24e',
            'superadmin_sidebar_color' => $branding->superadmin_sidebar_color ?? '#4338ca',
            'platform_name' => $branding->platform_name ?? config('app.name', 'Smart Inventory & Sales'),
            'logo_url' => $branding->logo_url,
            'favicon_url' => $branding->favicon_url,
            'support_email' => $branding->support_email,
            'support_phone' => $branding->support_phone,
        ];
    }
}

if (! function_exists('appearance_defaults')) {
    /** Retrieve the platform-wide navigation defaults configured by superadmin. */
    function appearance_defaults(): array
    {
        $items = json_decode((string) setting('appearance_nav_visible_items', '[]'), true);

        return [
            'version' => (string) setting('appearance_defaults_version', '1'),
            'layout' => setting('appearance_nav_layout', 'slim'),
            'position' => setting('appearance_nav_position', 'left'),
            'mode' => setting('appearance_nav_mode', 'docked'),
            'customBg' => setting('appearance_nav_custom_bg', ''),
            'uiAccentColor' => setting('appearance_ui_accent_color', '#4f46e5'),
            'navTextColor' => setting('appearance_nav_text_color', '#ffffff'),
            'navTextActiveColor' => setting('appearance_nav_text_active_color', '#60a5fa'),
            'visibleItems' => is_array($items) && $items !== [] ? array_values($items) : ['dashboard', 'tenants', 'plans', 'settings', 'smtp'],
        ];
    }
}

if (! function_exists('tenant_setting')) {
    /**
     * Get a tenant company setting value with fallback default.
     * Supports both tenant_setting($key, $default) and tenant_setting($tenantId, $key, $default).
     */
    function tenant_setting(mixed $arg1, mixed $arg2 = null, mixed $default = null): mixed
    {
        if (func_num_args() === 1) {
            $companyId = null;
            $key = (string) $arg1;
            $fallback = null;
        } elseif (func_num_args() >= 3) {
            $companyId = is_object($arg1) ? $arg1->id : $arg1;
            $key = (string) $arg2;
            $fallback = $default;
        } else { // 2 arguments
            if (is_numeric($arg1) || (is_string($arg1) && str_starts_with($arg1, 'emp_'))) {
                $companyId = $arg1;
                $key = (string) $arg2;
                $fallback = null;
            } else {
                $companyId = null;
                $key = (string) $arg1;
                $fallback = $arg2;
            }
        }

        if (! $companyId) {
            $companyId = app()->bound('tenant.company_id')
                ? app('tenant.company_id')
                : (auth('web')->user()?->company_id ?? auth('tenant_api')->user()?->company_id ?? auth()->user()?->company_id);
        }

        if (! $companyId) {
            $companyId = Company::first()?->id;
        }

        if ($key === 'sms_gateway') {
            if (class_exists(Configuration::class) && $companyId) {
                $configVal = Configuration::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('key', 'sms_gateway')
                    ->value('value');
                if ($configVal !== null && $configVal !== '') {
                    $decoded = is_string($configVal) ? json_decode($configVal, true) : $configVal;
                    if (is_array($decoded) && ! empty($decoded['gateway_url'])) {
                        return $decoded;
                    }
                }
            }

            if (class_exists(\App\Models\TenantNotificationGateway::class) && $companyId) {
                $gw = \App\Models\TenantNotificationGateway::withoutGlobalScope('company')
                    ->where('company_id', $companyId)
                    ->where('channel', \App\Models\TenantNotificationGateway::CHANNEL_SMS)
                    ->first();
                if ($gw && is_array($gw->credentials) && ! empty($gw->credentials['url'])) {
                    return [
                        'gateway_url' => $gw->credentials['url'],
                        'method' => strtoupper($gw->credentials['method'] ?? 'GET'),
                        'api_token' => $gw->credentials['api_key'] ?? ($gw->credentials['api_token'] ?? ''),
                        'is_enabled' => (bool) $gw->is_enabled,
                    ];
                }
            }

            if ($fallback !== null) {
                return $fallback;
            }

            // Do not expose or implicitly enable a platform SMS gateway for
            // tenants that have not configured one themselves.
            return [
                'gateway_url' => '',
                'method' => 'GET',
                'api_token' => '',
                'is_enabled' => false,
            ];
        }

        if (! $companyId) {
            return $fallback;
        }

        $company = Company::find($companyId);
        if (! $company) {
            return $fallback;
        }

        $aliases = [
            'quote_default_terms' => 'quote_terms',
            'invoice_default_terms' => 'invoice_terms',
            'quote_terms' => 'quote_terms',
            'invoice_terms' => 'invoice_terms',
            'card_fee_debit' => 'card_fee_debit',
            'card_fee_credit_1x' => 'card_fee_credit_1x',
            'card_fee_credit_installments' => 'card_fee_credit_installments',
            'pix_holder_name' => 'pix_merchant_name',
            'pix_city' => 'pix_merchant_city',
            'enable_consignments' => 'enable_consignments',
        ];

        $column = $aliases[$key] ?? $key;

        if (isset($company->{$column}) && $company->{$column} !== null && $company->{$column} !== '') {
            return $company->{$column};
        }

        if ($key === 'pix_holder_name' && ! empty($company->name)) {
            return $company->name;
        }

        if ($key === 'pix_city' && ! empty($company->city)) {
            return $company->city;
        }

        if (class_exists(Configuration::class)) {
            $configVal = Configuration::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('key', $key)
                ->value('value');

            if ($configVal !== null && $configVal !== '') {
                return $configVal;
            }
        }

        return $fallback;
    }
}

if (! function_exists('tenant_set_setting')) {
    /**
     * Set a tenant company setting value.
     * Supports both tenant_set_setting($key, $value) and tenant_set_setting($tenantId, $key, $value).
     */
    function tenant_set_setting(mixed $arg1, mixed $arg2, mixed $arg3 = null): void
    {
        if (func_num_args() === 2) {
            $companyId = app()->bound('tenant.company_id')
                ? app('tenant.company_id')
                : (auth('web')->user()?->company_id ?? auth('tenant_api')->user()?->company_id ?? auth()->user()?->company_id);
            $key = (string) $arg1;
            $value = $arg2;
        } else {
            $companyId = is_object($arg1) ? $arg1->id : $arg1;
            $key = (string) $arg2;
            $value = $arg3;
        }

        if (! $companyId) {
            $companyId = Company::first()?->id ?? 1;
        }

        $storedVal = is_array($value) ? json_encode($value) : (string) $value;

        if (class_exists(Configuration::class)) {
            Configuration::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $companyId, 'key' => $key],
                ['value' => $storedVal]
            );
        }

        if ($key === 'sms_gateway' && class_exists(\App\Models\TenantNotificationGateway::class)) {
            $arr = is_array($value) ? $value : (json_decode((string) $value, true) ?: []);
            $gwUrl = $arr['gateway_url'] ?? $arr['url'] ?? '';
            $gwMethod = strtoupper($arr['method'] ?? 'GET');
            $gwToken = $arr['api_token'] ?? $arr['api_key'] ?? '';

            \App\Models\TenantNotificationGateway::withoutGlobalScope('company')->updateOrCreate(
                ['company_id' => $companyId, 'channel' => \App\Models\TenantNotificationGateway::CHANNEL_SMS],
                [
                    'tenant_id' => $companyId,
                    'provider' => \App\Models\TenantNotificationGateway::PROVIDER_GENERIC_HTTP,
                    'is_enabled' => (bool) ($arr['is_enabled'] ?? false),
                    'credentials' => [
                        'url' => $gwUrl,
                        'method' => $gwMethod,
                        'api_key' => $gwToken,
                        'api_token' => $gwToken,
                    ],
                ]
            );
        }
    }
}
