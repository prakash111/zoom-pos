<?php

return [
    /*
    |--------------------------------------------------------------------------
    | System Regional & Localization Defaults
    |--------------------------------------------------------------------------
    |
    | Baseline country, dial code, currency, language, and timezone settings
    | configured across the multi-tenant platform.
    |
    */

    'default_country_iso' => env('PLATFORM_DEFAULT_COUNTRY_ISO', 'IN'),
    'default_dial_code' => env('PLATFORM_DEFAULT_DIAL_CODE', '+91'),
    'default_currency' => env('PLATFORM_DEFAULT_CURRENCY', 'INR'),
    'default_language' => env('PLATFORM_DEFAULT_LANGUAGE', 'en'),
    'default_timezone' => env('PLATFORM_DEFAULT_TIMEZONE', 'Asia/Kolkata'),
];
