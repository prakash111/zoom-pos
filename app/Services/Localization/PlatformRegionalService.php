<?php

namespace App\Services\Localization;

use App\Models\PlatformSystem;
use DateTimeZone;

class PlatformRegionalService
{
    /**
     * Comprehensive catalog of platform supported currencies.
     *
     * @var array<string, array{code: string, name: string, symbol: string, decimals: int, position: string}>
     */
    public const CURRENCIES = [
        'INR' => ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'decimals' => 2, 'position' => 'prefix'],
        'USD' => ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2, 'position' => 'prefix'],
        'EUR' => ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimals' => 2, 'position' => 'suffix'],
        'GBP' => ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'decimals' => 2, 'position' => 'prefix'],
        'AED' => ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ', 'decimals' => 2, 'position' => 'suffix'],
        'SAR' => ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => '﷼', 'decimals' => 2, 'position' => 'suffix'],
        'CAD' => ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'CA$', 'decimals' => 2, 'position' => 'prefix'],
        'AUD' => ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'decimals' => 2, 'position' => 'prefix'],
        'SGD' => ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'decimals' => 2, 'position' => 'prefix'],
        'MYR' => ['code' => 'MYR', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'decimals' => 2, 'position' => 'prefix'],
        'IDR' => ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'decimals' => 0, 'position' => 'prefix'],
        'JPY' => ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'decimals' => 0, 'position' => 'prefix'],
        'BRL' => ['code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$', 'decimals' => 2, 'position' => 'prefix'],
        'ZAR' => ['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R', 'decimals' => 2, 'position' => 'prefix'],
        'NZD' => ['code' => 'NZD', 'name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'decimals' => 2, 'position' => 'prefix'],
        'CHF' => ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'CHF', 'decimals' => 2, 'position' => 'prefix'],
        'CNY' => ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'decimals' => 2, 'position' => 'prefix'],
        'THB' => ['code' => 'THB', 'name' => 'Thai Baht', 'symbol' => '฿', 'decimals' => 2, 'position' => 'prefix'],
        'PHP' => ['code' => 'PHP', 'name' => 'Philippine Peso', 'symbol' => '₱', 'decimals' => 2, 'position' => 'prefix'],
        'PKR' => ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs', 'decimals' => 2, 'position' => 'prefix'],
        'BDT' => ['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => '৳', 'decimals' => 2, 'position' => 'prefix'],
        'NGN' => ['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'decimals' => 2, 'position' => 'prefix'],
        'KES' => ['code' => 'KES', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'decimals' => 2, 'position' => 'prefix'],
        'EGP' => ['code' => 'EGP', 'name' => 'Egyptian Pound', 'symbol' => 'E£', 'decimals' => 2, 'position' => 'prefix'],
        'TRY' => ['code' => 'TRY', 'name' => 'Turkish Lira', 'symbol' => '₺', 'decimals' => 2, 'position' => 'prefix'],
        'RUB' => ['code' => 'RUB', 'name' => 'Russian Ruble', 'symbol' => '₽', 'decimals' => 2, 'position' => 'suffix'],
        'MXN' => ['code' => 'MXN', 'name' => 'Mexican Peso', 'symbol' => 'MX$', 'decimals' => 2, 'position' => 'prefix'],
        'QAR' => ['code' => 'QAR', 'name' => 'Qatari Riyal', 'symbol' => 'QR', 'decimals' => 2, 'position' => 'suffix'],
        'KWD' => ['code' => 'KWD', 'name' => 'Kuwaiti Dinar', 'symbol' => 'KD', 'decimals' => 3, 'position' => 'suffix'],
        'BHD' => ['code' => 'BHD', 'name' => 'Bahraini Dinar', 'symbol' => 'BD', 'decimals' => 3, 'position' => 'suffix'],
        'OMR' => ['code' => 'OMR', 'name' => 'Omani Rial', 'symbol' => 'OMR', 'decimals' => 3, 'position' => 'suffix'],
    ];

    /**
     * Active supported system languages.
     *
     * @var array<string, array{code: string, name: string, native_name: string, flag: string, direction: string}>
     */
    public const LANGUAGES = [
        'en' => ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'flag' => '🇺🇸', 'direction' => 'ltr'],
        'hi' => ['code' => 'hi', 'name' => 'Hindi', 'native_name' => 'हिन्दी', 'flag' => '🇮🇳', 'direction' => 'ltr'],
        'es' => ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'flag' => '🇪🇸', 'direction' => 'ltr'],
        'fr' => ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'flag' => '🇫🇷', 'direction' => 'ltr'],
        'ar' => ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'flag' => '🇸🇦', 'direction' => 'rtl'],
        'de' => ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'flag' => '🇩🇪', 'direction' => 'ltr'],
        'pt' => ['code' => 'pt', 'name' => 'Portuguese', 'native_name' => 'Português', 'flag' => '🇧🇷', 'direction' => 'ltr'],
        'it' => ['code' => 'it', 'name' => 'Italian', 'native_name' => 'Italiano', 'flag' => '🇮🇹', 'direction' => 'ltr'],
        'ru' => ['code' => 'ru', 'name' => 'Russian', 'native_name' => 'Русский', 'flag' => '🇷🇺', 'direction' => 'ltr'],
        'zh' => ['code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文', 'flag' => '🇨🇳', 'direction' => 'ltr'],
        'ja' => ['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語', 'flag' => '🇯🇵', 'direction' => 'ltr'],
        'id' => ['code' => 'id', 'name' => 'Indonesian', 'native_name' => 'Bahasa Indonesia', 'flag' => '🇮🇩', 'direction' => 'ltr'],
        'tr' => ['code' => 'tr', 'name' => 'Turkish', 'native_name' => 'Türkçe', 'flag' => '🇹🇷', 'direction' => 'ltr'],
    ];

    /**
     * Primary / Recommended timezones ordered for easy discovery.
     */
    public const PRIORITY_TIMEZONES = [
        'Asia/Kolkata',
        'UTC',
        'America/New_York',
        'America/Chicago',
        'America/Denver',
        'America/Los_Angeles',
        'America/Toronto',
        'America/Sao_Paulo',
        'Europe/London',
        'Europe/Paris',
        'Europe/Berlin',
        'Europe/Madrid',
        'Europe/Rome',
        'Asia/Dubai',
        'Asia/Riyadh',
        'Asia/Singapore',
        'Asia/Jakarta',
        'Asia/Tokyo',
        'Asia/Shanghai',
        'Australia/Sydney',
        'Australia/Melbourne',
        'Africa/Cairo',
        'Africa/Johannesburg',
        'Africa/Lagos',
    ];

    /**
     * Returns currency dropdown choices formatted as: 'INR' => 'INR (₹) - Indian Rupee'.
     *
     * @return array<string, string>
     */
    public static function currencyOptions(): array
    {
        $options = [];
        foreach (self::CURRENCIES as $code => $info) {
            $options[$code] = "{$code} ({$info['symbol']}) - {$info['name']}";
        }

        return $options;
    }

    /**
     * Get details for a specific currency code.
     *
     * @return array{code: string, name: string, symbol: string, decimals: int, position: string}
     */
    public static function getCurrencyDetails(string $code): array
    {
        $upper = strtoupper(trim($code));

        return self::CURRENCIES[$upper] ?? [
            'code' => $upper,
            'name' => $upper,
            'symbol' => '$',
            'decimals' => 2,
            'position' => 'prefix',
        ];
    }

    public static function currencySymbol(string $code, string $default = '$'): string
    {
        $upper = strtoupper(trim($code));

        return self::CURRENCIES[$upper]['symbol'] ?? $default;
    }

    public static function currencyDecimals(string $code, int $default = 2): int
    {
        $upper = strtoupper(trim($code));

        return self::CURRENCIES[$upper]['decimals'] ?? $default;
    }

    public static function currencyPosition(string $code, string $default = 'prefix'): string
    {
        $upper = strtoupper(trim($code));

        return self::CURRENCIES[$upper]['position'] ?? $default;
    }

    /**
     * Returns language dropdown choices formatted as: 'en' => 'English (en) 🇺🇸'.
     *
     * @return array<string, string>
     */
    public static function languageOptions(): array
    {
        $options = [];
        foreach (self::LANGUAGES as $code => $lang) {
            $options[$code] = "{$lang['name']} ({$code}) {$lang['flag']}";
        }

        return $options;
    }

    /**
     * Returns searchable list of standard tz database identifiers.
     * Priority timezones appear at the top, followed by all remaining standard IANA timezones.
     *
     * @return array<string, string>
     */
    public static function timezoneOptions(): array
    {
        $all = DateTimeZone::listIdentifiers();
        $merged = array_unique(array_merge(self::PRIORITY_TIMEZONES, $all));

        $options = [];
        foreach ($merged as $tz) {
            $options[$tz] = $tz;
        }

        return $options;
    }

    /**
     * Default Platform Currency set by SuperAdmin.
     */
    public static function defaultCurrency(): string
    {
        return (string) (PlatformSystem::get('platform_default_currency')
            ?: PlatformSystem::get('app_currency', 'USD'));
    }

    /**
     * Default Platform Language set by SuperAdmin.
     */
    public static function defaultLanguage(): string
    {
        return (string) (PlatformSystem::get('platform_default_language') ?: 'en');
    }

    /**
     * Default Platform Timezone set by SuperAdmin.
     */
    public static function defaultTimezone(): string
    {
        return (string) (PlatformSystem::get('platform_default_timezone')
            ?: PlatformSystem::get('app_timezone', config('app.timezone', 'UTC')));
    }

    /**
     * Bundled platform regional defaults dictionary.
     *
     * @return array{
     *     currency: string,
     *     currency_symbol: string,
     *     currency_decimals: int,
     *     currency_symbol_position: string,
     *     language: string,
     *     default_locale: string,
     *     timezone: string
     * }
     */
    public static function getPlatformDefaults(): array
    {
        $currency = self::defaultCurrency();
        $currInfo = self::getCurrencyDetails($currency);
        $lang = self::defaultLanguage();
        $tz = self::defaultTimezone();

        return [
            'currency' => $currency,
            'currency_symbol' => $currInfo['symbol'],
            'currency_decimals' => $currInfo['decimals'],
            'currency_symbol_position' => $currInfo['position'],
            'language' => $lang,
            'default_locale' => $lang,
            'timezone' => $tz,
        ];
    }

    /**
     * Persists platform regional defaults in SuperAdmin settings.
     */
    public static function setPlatformDefaults(string $currency, string $language, string $timezone): void
    {
        $currency = strtoupper(trim($currency));
        $language = strtolower(trim($language));
        $timezone = trim($timezone);

        PlatformSystem::set('platform_default_currency', $currency);
        PlatformSystem::set('platform_default_language', $language);
        PlatformSystem::set('platform_default_timezone', $timezone);

        // Keep legacy keys synchronized for backward compatibility
        PlatformSystem::set('app_currency', $currency);
        PlatformSystem::set('app_timezone', $timezone);
    }
}
