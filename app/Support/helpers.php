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
     */
    function tenant_setting(string $key, mixed $default = null): mixed
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : (auth('web')->user()?->company_id ?? auth('tenant_api')->user()?->company_id);

        if (! $companyId) {
            return $default;
        }

        $company = Company::find($companyId);
        if (! $company) {
            return $default;
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

        return $default;
    }
}
