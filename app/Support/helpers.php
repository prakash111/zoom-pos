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
            'landing_dark_bg' => setting('landing_dark_bg', '#0b0f19'),
            'landing_sections_palette' => get_landing_sections_palette(),
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

if (! function_exists('get_contact_form_fields')) {
    /**
     * Retrieve configured dynamic form fields for the public contact page and landing form.
     */
    function get_contact_form_fields(): array
    {
        return \App\Services\ContactFormService::getFields();
    }
}

if (! function_exists('get_contact_form_settings')) {
    /**
     * Retrieve global contact form settings (titles, buttons, emails).
     */
    function get_contact_form_settings(): array
    {
        return \App\Services\ContactFormService::getSettings();
    }
}

if (! function_exists('default_landing_sections_palette')) {
    /** Default granular color pairs for public landing page sections. */
    function default_landing_sections_palette(): array
    {
        return [
            'hero' => [
                'light_bg' => '#f8fafc',
                'light_text' => '#0f172a',
                'light_muted' => '#64748b',
                'dark_bg' => '#0b0f19',
                'dark_text' => '#f8fafc',
                'dark_muted' => '#94a3b8',
            ],
            'features' => [
                'light_bg' => '#ffffff',
                'light_text' => '#0f172a',
                'light_muted' => '#64748b',
                'dark_bg' => '#0f172a',
                'dark_text' => '#f8fafc',
                'dark_muted' => '#94a3b8',
            ],
            'mission' => [
                'light_bg' => '#ffffff',
                'light_text' => '#0f172a',
                'light_muted' => '#64748b',
                'dark_bg' => '#0b0f19',
                'dark_text' => '#f8fafc',
                'dark_muted' => '#94a3b8',
            ],
            'pricing' => [
                'light_bg' => '#ffffff',
                'light_text' => '#0f172a',
                'light_muted' => '#64748b',
                'dark_bg' => '#0b0f19',
                'dark_text' => '#f8fafc',
                'dark_muted' => '#94a3b8',
            ],
            'faq' => [
                'light_bg' => '#f8fafc',
                'light_text' => '#0f172a',
                'light_muted' => '#64748b',
                'dark_bg' => '#0b0f19',
                'dark_text' => '#f8fafc',
                'dark_muted' => '#94a3b8',
            ],
            'cta' => [
                'light_bg' => '#ffffff',
                'light_text' => '#0f172a',
                'light_muted' => '#64748b',
                'dark_bg' => '#0b0f19',
                'dark_text' => '#f8fafc',
                'dark_muted' => '#94a3b8',
            ],
            'contact' => [
                'light_bg' => '#ffffff',
                'light_card_bg' => '#ffffff',
                'light_text' => '#0f172a',
                'light_muted' => '#64748b',
                'dark_bg' => '#0b0f19',
                'dark_card_bg' => '#131e29',
                'dark_text' => '#f8fafc',
                'dark_muted' => '#94a3b8',
            ],
        ];
    }
}

if (! function_exists('get_landing_sections_palette')) {
    /** Retrieve the cached or stored per-section palette with defaults fallback. */
    function get_landing_sections_palette(): array
    {
        $defaults = default_landing_sections_palette();
        $stored = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
            $raw = \Illuminate\Support\Facades\DB::table('system_settings')->where('key', 'landing_sections_theme_palette')->value('value');
            if ($raw) {
                $stored = json_decode($raw, true);
            }
        }
        if (! is_array($stored)) {
            $val = setting('landing_sections_theme_palette');
            $stored = is_array($val) ? $val : (is_string($val) ? json_decode($val, true) : null);
        }

        if (! is_array($stored)) {
            return $defaults;
        }

        foreach ($defaults as $sec => $props) {
            foreach ($props as $k => $defVal) {
                if (empty($stored[$sec][$k])) {
                    $stored[$sec][$k] = $defVal;
                }
            }
        }

        return $stored;
    }
}

if (! function_exists('get_landing_theme_tokens')) {
    /**
     * Retrieve isolated section theme tokens keyed by both full slugs and short keys.
     * Full slugs: hero_showcase, retail_features, pricing_plans, our_mission, faq, scale_cta, contact_form
     * Short keys: hero, features, pricing, mission, about, cta, contact
     */
    function get_landing_theme_tokens(): array
    {
        $palette = get_landing_sections_palette();

        $slugMap = [
            'hero_showcase' => 'hero',
            'retail_features' => 'features',
            'pricing_plans' => 'pricing',
            'our_mission' => 'mission',
            'faq' => 'faq',
            'scale_cta' => 'cta',
            'contact_form' => 'contact',
        ];

        $tokens = [];
        foreach ($palette as $sec => $props) {
            $entry = array_merge($props, [
                'bg_dark' => $props['dark_bg'] ?? '#0b0f19',
                'bg_light' => $props['light_bg'] ?? '#ffffff',
                'text_dark' => $props['dark_text'] ?? '#f8fafc',
                'text_light' => $props['light_text'] ?? '#0f172a',
                'muted_dark' => $props['dark_muted'] ?? '#94a3b8',
                'muted_light' => $props['light_muted'] ?? '#64748b',
            ]);
            $tokens[$sec] = $entry;
        }

        foreach ($slugMap as $slug => $secKey) {
            if (isset($tokens[$secKey])) {
                $tokens[$slug] = $tokens[$secKey];
            }
        }

        if (isset($tokens['mission']) && ! isset($tokens['about'])) {
            $tokens['about'] = $tokens['mission'];
        }

        return $tokens;
    }
}

if (! function_exists('get_landing_matching_patterns')) {
    /**
     * Pre-defined harmonized matching color combination patterns for all landing page sections.
     * Guarantees alternating rhythmic contrast, accessible typography, and matching dark/light modes.
     */
    function get_landing_matching_patterns(): array
    {
        return [
            'obsidian' => [
                'name' => 'Obsidian Executive',
                'description' => 'Sophisticated Obsidian Navy & Slate rhythm with pure white highlights',
                'color' => '#0b0f19',
                'dark_bg' => '#0b0f19',
                'palette' => [
                    'hero' => [
                        'light_bg' => '#ffffff', 'light_text' => '#0f172a', 'light_muted' => '#64748b',
                        'dark_bg' => '#0b0f19', 'dark_text' => '#f8fafc', 'dark_muted' => '#94a3b8',
                    ],
                    'features' => [
                        'light_bg' => '#f8fafc', 'light_text' => '#0f172a', 'light_muted' => '#64748b',
                        'dark_bg' => '#0f172a', 'dark_text' => '#f8fafc', 'dark_muted' => '#94a3b8',
                    ],
                    'mission' => [
                        'light_bg' => '#ffffff', 'light_text' => '#0f172a', 'light_muted' => '#64748b',
                        'dark_bg' => '#0b0f19', 'dark_text' => '#f8fafc', 'dark_muted' => '#94a3b8',
                    ],
                    'pricing' => [
                        'light_bg' => '#f8fafc', 'light_text' => '#0f172a', 'light_muted' => '#64748b',
                        'dark_bg' => '#0f172a', 'dark_text' => '#f8fafc', 'dark_muted' => '#94a3b8',
                    ],
                    'faq' => [
                        'light_bg' => '#ffffff', 'light_text' => '#0f172a', 'light_muted' => '#64748b',
                        'dark_bg' => '#0b0f19', 'dark_text' => '#f8fafc', 'dark_muted' => '#94a3b8',
                    ],
                    'cta' => [
                        'light_bg' => '#f8fafc', 'light_text' => '#0f172a', 'light_muted' => '#475569',
                        'dark_bg' => '#0f172a', 'dark_text' => '#f8fafc', 'dark_muted' => '#94a3b8',
                    ],
                    'contact' => [
                        'light_bg' => '#ffffff', 'light_card_bg' => '#ffffff', 'light_text' => '#0f172a', 'light_muted' => '#64748b',
                        'dark_bg' => '#0b0f19', 'dark_card_bg' => '#131e29', 'dark_text' => '#f8fafc', 'dark_muted' => '#94a3b8',
                    ],
                ],
            ],
            'midnight' => [
                'name' => 'Midnight Navy',
                'description' => 'Deep ocean slate & navy alternating with crisp light gray surfaces',
                'color' => '#0f172a',
                'dark_bg' => '#0f172a',
                'palette' => [
                    'hero' => [
                        'light_bg' => '#ffffff', 'light_text' => '#0f172a', 'light_muted' => '#64748b',
                        'dark_bg' => '#0f172a', 'dark_text' => '#f8fafc', 'dark_muted' => '#94a3b8',
                    ],
                    'features' => [
                        'light_bg' => '#f1f5f9', 'light_text' => '#0f172a', 'light_muted' => '#64748b',
                        'dark_bg' => '#1e293b', 'dark_text' => '#f8fafc', 'dark_muted' => '#94a3b8',
                    ],
                    'mission' => [
                        'light_bg' => '#ffffff', 'light_text' => '#0f172a', 'light_muted' => '#64748b',
                        'dark_bg' => '#0f172a', 'dark_text' => '#f8fafc', 'dark_muted' => '#94a3b8',
                    ],
                    'pricing' => [
                        'light_bg' => '#f1f5f9', 'light_text' => '#0f172a', 'light_muted' => '#64748b',
                        'dark_bg' => '#1e293b', 'dark_text' => '#f8fafc', 'dark_muted' => '#94a3b8',
                    ],
                    'faq' => [
                        'light_bg' => '#ffffff', 'light_text' => '#0f172a', 'light_muted' => '#64748b',
                        'dark_bg' => '#0f172a', 'dark_text' => '#f8fafc', 'dark_muted' => '#94a3b8',
                    ],
                    'cta' => [
                        'light_bg' => '#f1f5f9', 'light_text' => '#0f172a', 'light_muted' => '#475569',
                        'dark_bg' => '#1e293b', 'dark_text' => '#f8fafc', 'dark_muted' => '#94a3b8',
                    ],
                    'contact' => [
                        'light_bg' => '#ffffff', 'light_card_bg' => '#ffffff', 'light_text' => '#0f172a', 'light_muted' => '#64748b',
                        'dark_bg' => '#0f172a', 'dark_card_bg' => '#1e293b', 'dark_text' => '#f8fafc', 'dark_muted' => '#94a3b8',
                    ],
                ],
            ],
            'emerald' => [
                'name' => 'Deep Emerald',
                'description' => 'FinTech & clean commerce deep emerald with fresh mint accents',
                'color' => '#064e3b',
                'dark_bg' => '#064e3b',
                'palette' => [
                    'hero' => [
                        'light_bg' => '#ffffff', 'light_text' => '#064e3b', 'light_muted' => '#047857',
                        'dark_bg' => '#064e3b', 'dark_text' => '#ecfdf5', 'dark_muted' => '#6ee7b7',
                    ],
                    'features' => [
                        'light_bg' => '#f0fdf4', 'light_text' => '#064e3b', 'light_muted' => '#047857',
                        'dark_bg' => '#022c22', 'dark_text' => '#ecfdf5', 'dark_muted' => '#6ee7b7',
                    ],
                    'mission' => [
                        'light_bg' => '#ffffff', 'light_text' => '#064e3b', 'light_muted' => '#047857',
                        'dark_bg' => '#064e3b', 'dark_text' => '#ecfdf5', 'dark_muted' => '#6ee7b7',
                    ],
                    'pricing' => [
                        'light_bg' => '#f0fdf4', 'light_text' => '#064e3b', 'light_muted' => '#047857',
                        'dark_bg' => '#022c22', 'dark_text' => '#ecfdf5', 'dark_muted' => '#6ee7b7',
                    ],
                    'faq' => [
                        'light_bg' => '#ffffff', 'light_text' => '#064e3b', 'light_muted' => '#047857',
                        'dark_bg' => '#064e3b', 'dark_text' => '#ecfdf5', 'dark_muted' => '#6ee7b7',
                    ],
                    'cta' => [
                        'light_bg' => '#f0fdf4', 'light_text' => '#064e3b', 'light_muted' => '#047857',
                        'dark_bg' => '#022c22', 'dark_text' => '#ecfdf5', 'dark_muted' => '#6ee7b7',
                    ],
                    'contact' => [
                        'light_bg' => '#ffffff', 'light_card_bg' => '#ffffff', 'light_text' => '#064e3b', 'light_muted' => '#047857',
                        'dark_bg' => '#064e3b', 'dark_card_bg' => '#022c22', 'dark_text' => '#ecfdf5', 'dark_muted' => '#6ee7b7',
                    ],
                ],
            ],
            'indigo' => [
                'name' => 'Royal Indigo',
                'description' => 'Modern high-growth SaaS royal indigo with soft lavender undertones',
                'color' => '#1e1b4b',
                'dark_bg' => '#1e1b4b',
                'palette' => [
                    'hero' => [
                        'light_bg' => '#ffffff', 'light_text' => '#1e1b4b', 'light_muted' => '#4338ca',
                        'dark_bg' => '#1e1b4b', 'dark_text' => '#e0e7ff', 'dark_muted' => '#a5b4fc',
                    ],
                    'features' => [
                        'light_bg' => '#eef2ff', 'light_text' => '#1e1b4b', 'light_muted' => '#4338ca',
                        'dark_bg' => '#312e81', 'dark_text' => '#e0e7ff', 'dark_muted' => '#a5b4fc',
                    ],
                    'mission' => [
                        'light_bg' => '#ffffff', 'light_text' => '#1e1b4b', 'light_muted' => '#4338ca',
                        'dark_bg' => '#1e1b4b', 'dark_text' => '#e0e7ff', 'dark_muted' => '#a5b4fc',
                    ],
                    'pricing' => [
                        'light_bg' => '#eef2ff', 'light_text' => '#1e1b4b', 'light_muted' => '#4338ca',
                        'dark_bg' => '#312e81', 'dark_text' => '#e0e7ff', 'dark_muted' => '#a5b4fc',
                    ],
                    'faq' => [
                        'light_bg' => '#ffffff', 'light_text' => '#1e1b4b', 'light_muted' => '#4338ca',
                        'dark_bg' => '#1e1b4b', 'dark_text' => '#e0e7ff', 'dark_muted' => '#a5b4fc',
                    ],
                    'cta' => [
                        'light_bg' => '#eef2ff', 'light_text' => '#1e1b4b', 'light_muted' => '#4338ca',
                        'dark_bg' => '#312e81', 'dark_text' => '#e0e7ff', 'dark_muted' => '#a5b4fc',
                    ],
                    'contact' => [
                        'light_bg' => '#ffffff', 'light_card_bg' => '#ffffff', 'light_text' => '#1e1b4b', 'light_muted' => '#4338ca',
                        'dark_bg' => '#1e1b4b', 'dark_card_bg' => '#312e81', 'dark_text' => '#e0e7ff', 'dark_muted' => '#a5b4fc',
                    ],
                ],
            ],
            'oled' => [
                'name' => 'OLED Minimalist',
                'description' => 'Ultra-clean high-contrast OLED monochrome black and pure white',
                'color' => '#000000',
                'dark_bg' => '#000000',
                'palette' => [
                    'hero' => [
                        'light_bg' => '#ffffff', 'light_text' => '#09090b', 'light_muted' => '#71717a',
                        'dark_bg' => '#000000', 'dark_text' => '#ffffff', 'dark_muted' => '#a1a1aa',
                    ],
                    'features' => [
                        'light_bg' => '#f4f4f5', 'light_text' => '#09090b', 'light_muted' => '#71717a',
                        'dark_bg' => '#09090b', 'dark_text' => '#ffffff', 'dark_muted' => '#a1a1aa',
                    ],
                    'mission' => [
                        'light_bg' => '#ffffff', 'light_text' => '#09090b', 'light_muted' => '#71717a',
                        'dark_bg' => '#000000', 'dark_text' => '#ffffff', 'dark_muted' => '#a1a1aa',
                    ],
                    'pricing' => [
                        'light_bg' => '#f4f4f5', 'light_text' => '#09090b', 'light_muted' => '#71717a',
                        'dark_bg' => '#09090b', 'dark_text' => '#ffffff', 'dark_muted' => '#a1a1aa',
                    ],
                    'faq' => [
                        'light_bg' => '#ffffff', 'light_text' => '#09090b', 'light_muted' => '#71717a',
                        'dark_bg' => '#000000', 'dark_text' => '#ffffff', 'dark_muted' => '#a1a1aa',
                    ],
                    'cta' => [
                        'light_bg' => '#f4f4f5', 'light_text' => '#09090b', 'light_muted' => '#71717a',
                        'dark_bg' => '#09090b', 'dark_text' => '#ffffff', 'dark_muted' => '#a1a1aa',
                    ],
                    'contact' => [
                        'light_bg' => '#ffffff', 'light_card_bg' => '#ffffff', 'light_text' => '#09090b', 'light_muted' => '#71717a',
                        'dark_bg' => '#000000', 'dark_card_bg' => '#121214', 'dark_text' => '#ffffff', 'dark_muted' => '#a1a1aa',
                    ],
                ],
            ],
        ];
    }
}

if (! function_exists('apply_matching_landing_palette')) {
    /**
     * Persistently apply a harmonized matching color combination pattern across all landing page sections.
     */
    function apply_matching_landing_palette(string $themeKey = 'obsidian'): array
    {
        $patterns = get_landing_matching_patterns();
        $selected = $patterns[$themeKey] ?? $patterns['obsidian'];

        $palette = $selected['palette'];
        $darkBg = $selected['dark_bg'];

        if (\Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
            \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(
                ['key' => 'landing_sections_theme_palette'],
                [
                    'value'      => json_encode($palette),
                    'updated_at' => now(),
                ]
            );

            \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(
                ['key' => 'landing_dark_bg'],
                [
                    'value'      => $darkBg,
                    'updated_at' => now(),
                ]
            );

            $existingConfig = \Illuminate\Support\Facades\DB::table('system_settings')->where('key', 'superadmin_theme_customization')->value('value');
            $config = $existingConfig ? json_decode($existingConfig, true) : [];
            $config['landing_dark_bg'] = $darkBg;
            \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(
                ['key' => 'superadmin_theme_customization'],
                [
                    'value'      => json_encode($config),
                    'updated_at' => now(),
                ]
            );
        }

        set_setting('landing_sections_theme_palette', json_encode($palette));
        set_setting('landing_dark_bg', $darkBg);

        \Illuminate\Support\Facades\Cache::forget('landing_sections_theme_palette');
        \Illuminate\Support\Facades\Cache::forget('superadmin_theme_settings');
        \Illuminate\Support\Facades\Cache::forget('landing_page_theme_config');
        \Illuminate\Support\Facades\Cache::forget('app_landing_page_theme');
        if (\Illuminate\Support\Facades\Cache::has('landing_page_cache_version')) {
            \Illuminate\Support\Facades\Cache::increment('landing_page_cache_version');
        } else {
            \Illuminate\Support\Facades\Cache::forever('landing_page_cache_version', 2);
        }

        return [
            'theme'   => $themeKey,
            'name'    => $selected['name'],
            'dark_bg' => $darkBg,
            'palette' => $palette,
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
            'landingDarkBg' => setting('landing_dark_bg', '#0b0f19'),
            'landingSectionsPalette' => get_landing_sections_palette(),
            'visibleItems' => is_array($items) && $items !== [] ? array_values($items) : ['dashboard', 'tenants', 'plans', 'taxes', 'menus', 'pages', 'settings', 'smtp'],
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
