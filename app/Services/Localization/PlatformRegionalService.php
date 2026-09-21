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
     * ISO 3166-1 alpha-2 country codes mapped to international dialing codes.
     *
     * @var array<string, string>
     */
    public const COUNTRY_DIAL_CODES = [
        'IN' => '+91',
        'US' => '+1',
        'CA' => '+1',
        'GB' => '+44',
        'AE' => '+971',
        'SA' => '+966',
        'AU' => '+61',
        'NZ' => '+64',
        'SG' => '+65',
        'MY' => '+60',
        'ID' => '+62',
        'PH' => '+63',
        'TH' => '+66',
        'VN' => '+84',
        'PK' => '+92',
        'BD' => '+880',
        'LK' => '+94',
        'NP' => '+977',
        'DE' => '+49',
        'FR' => '+33',
        'IT' => '+39',
        'ES' => '+34',
        'PT' => '+351',
        'NL' => '+31',
        'BE' => '+32',
        'CH' => '+41',
        'AT' => '+43',
        'SE' => '+46',
        'NO' => '+47',
        'DK' => '+45',
        'FI' => '+358',
        'PL' => '+48',
        'IE' => '+353',
        'RU' => '+7',
        'KZ' => '+7',
        'TR' => '+90',
        'ZA' => '+27',
        'EG' => '+20',
        'NG' => '+234',
        'KE' => '+254',
        'GH' => '+233',
        'BR' => '+55',
        'MX' => '+52',
        'AR' => '+54',
        'CO' => '+57',
        'CL' => '+56',
        'PE' => '+51',
        'QA' => '+974',
        'KW' => '+965',
        'BH' => '+973',
        'OM' => '+968',
        'JO' => '+962',
        'LB' => '+961',
        'IL' => '+972',
        'HK' => '+852',
        'TW' => '+886',
        'KR' => '+82',
        'JP' => '+81',
        'CN' => '+86',
        'AF' => '+93',
        'AL' => '+355',
        'DZ' => '+213',
        'AD' => '+376',
        'AO' => '+244',
        'AM' => '+374',
        'AZ' => '+994',
        'BY' => '+375',
        'BZ' => '+501',
        'BJ' => '+229',
        'BT' => '+975',
        'BO' => '+591',
        'BA' => '+387',
        'BW' => '+267',
        'BN' => '+673',
        'BG' => '+359',
        'BF' => '+226',
        'BI' => '+257',
        'KH' => '+855',
        'CM' => '+237',
        'CR' => '+506',
        'HR' => '+385',
        'CY' => '+357',
        'CZ' => '+420',
        'EC' => '+593',
        'EE' => '+372',
        'ET' => '+251',
        'GE' => '+995',
        'GT' => '+502',
        'HU' => '+36',
        'IS' => '+354',
        'IQ' => '+964',
        'JM' => '+1',
        'KG' => '+996',
        'LV' => '+371',
        'LY' => '+218',
        'LT' => '+370',
        'LU' => '+352',
        'MV' => '+960',
        'MU' => '+230',
        'MD' => '+373',
        'MC' => '+377',
        'MA' => '+212',
        'PA' => '+507',
        'PY' => '+595',
        'RO' => '+40',
        'RW' => '+250',
        'SN' => '+221',
        'RS' => '+381',
        'SK' => '+421',
        'SI' => '+386',
        'TZ' => '+255',
        'TN' => '+216',
        'UG' => '+256',
        'UA' => '+380',
        'UY' => '+598',
        'UZ' => '+998',
        'VE' => '+58',
        'YE' => '+967',
        'ZM' => '+260',
        'ZW' => '+263',
    ];

    /**
     * Currency-to-default Country ISO mapping for smart auto-selection.
     *
     * @var array<string, string>
     */
    public const CURRENCY_DEFAULT_COUNTRIES = [
        'INR' => 'IN',
        'USD' => 'US',
        'EUR' => 'DE',
        'GBP' => 'GB',
        'AED' => 'AE',
        'SAR' => 'SA',
        'CAD' => 'CA',
        'AUD' => 'AU',
        'SGD' => 'SG',
        'MYR' => 'MY',
        'IDR' => 'ID',
        'JPY' => 'JP',
        'BRL' => 'BR',
        'ZAR' => 'ZA',
        'NZD' => 'NZ',
        'CHF' => 'CH',
        'CNY' => 'CN',
        'THB' => 'TH',
        'PHP' => 'PH',
        'PKR' => 'PK',
        'BDT' => 'BD',
        'NGN' => 'NG',
        'KES' => 'KE',
        'EGP' => 'EG',
        'TRY' => 'TR',
        'RUB' => 'RU',
        'MXN' => 'MX',
        'QAR' => 'QA',
        'KWD' => 'KW',
        'BHD' => 'BH',
        'OMR' => 'OM',
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
     * The full ISO 3166-1 country list (code => name) for every "choose a
     * country" dropdown. Regional tax / currency presets still key off the
     * same ISO2 code, so any country here links straight into them.
     *
     * @return array<string, string>
     */
    public static function countryOptions(): array
    {
        $list = (array) config('countries', []);
        asort($list, SORT_FLAG_CASE | SORT_STRING);

        return $list;
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
     * Resolves the international dial code for a given ISO2 country code.
     */
    public static function dialCodeForCountry(string $iso): string
    {
        $upper = strtoupper(trim($iso));

        return self::COUNTRY_DIAL_CODES[$upper] ?? '+91';
    }

    /**
     * Resolves the default ISO2 country code for a given currency code.
     */
    public static function countryForCurrency(string $currency): string
    {
        $upper = strtoupper(trim($currency));

        return self::CURRENCY_DEFAULT_COUNTRIES[$upper] ?? 'IN';
    }

    /**
     * Formatted list of dial code options for dropdown selection.
     *
     * @return array<string, string>
     */
    public static function dialCodeOptions(): array
    {
        $options = [];
        $countries = self::countryOptions();

        // Priority / common countries first
        $priorityIsos = ['IN', 'US', 'GB', 'AE', 'SA', 'CA', 'AU', 'SG', 'MY', 'DE', 'FR', 'ZA', 'NG', 'BR'];
        foreach ($priorityIsos as $iso) {
            if (isset(self::COUNTRY_DIAL_CODES[$iso])) {
                $dial = self::COUNTRY_DIAL_CODES[$iso];
                $name = $countries[$iso] ?? $iso;
                $options[$dial] = "{$name} ({$dial})";
            }
        }

        foreach (self::COUNTRY_DIAL_CODES as $iso => $dial) {
            $name = $countries[$iso] ?? $iso;
            if (! isset($options[$dial])) {
                $options[$dial] = "{$name} ({$dial})";
            }
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
     * Default Platform Country ISO code set by SuperAdmin.
     */
    public static function defaultCountryIso(): string
    {
        $saved = (string) PlatformSystem::get('platform_default_country_iso');
        if (filled($saved)) {
            return strtoupper(trim($saved));
        }

        $currency = self::defaultCurrency();
        if (isset(self::CURRENCY_DEFAULT_COUNTRIES[$currency])) {
            return self::CURRENCY_DEFAULT_COUNTRIES[$currency];
        }

        return (string) config('system.default_country_iso', 'IN');
    }

    /**
     * Default Platform Dial Code set by SuperAdmin.
     */
    public static function defaultDialCode(): string
    {
        $saved = (string) PlatformSystem::get('platform_default_dial_code');
        if (filled($saved)) {
            $trimmed = trim($saved);

            return str_starts_with($trimmed, '+') ? $trimmed : '+'.$trimmed;
        }

        $countryIso = self::defaultCountryIso();

        return self::dialCodeForCountry($countryIso);
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
     *     timezone: string,
     *     default_country_iso: string,
     *     default_country_code: string,
     *     country_iso: string,
     *     dial_code: string
     * }
     */
    public static function getPlatformDefaults(): array
    {
        $currency = self::defaultCurrency();
        $currInfo = self::getCurrencyDetails($currency);
        $lang = self::defaultLanguage();
        $tz = self::defaultTimezone();
        $countryIso = self::defaultCountryIso();
        $dialCode = self::defaultDialCode();

        return [
            'currency' => $currency,
            'currency_symbol' => $currInfo['symbol'],
            'currency_decimals' => $currInfo['decimals'],
            'currency_symbol_position' => $currInfo['position'],
            'language' => $lang,
            'default_locale' => $lang,
            'timezone' => $tz,
            'default_country_iso' => $countryIso,
            'default_country_code' => $dialCode,
            'country_iso' => $countryIso,
            'dial_code' => $dialCode,
        ];
    }

    /**
     * Persists platform regional defaults in SuperAdmin settings.
     */
    public static function setPlatformDefaults(
        string $currency,
        string $language,
        string $timezone,
        ?string $countryIso = null,
        ?string $dialCode = null
    ): void {
        $currency = strtoupper(trim($currency));
        $language = strtolower(trim($language));
        $timezone = trim($timezone);

        PlatformSystem::set('platform_default_currency', $currency);
        PlatformSystem::set('platform_default_language', $language);
        PlatformSystem::set('platform_default_timezone', $timezone);

        if ($countryIso !== null && trim($countryIso) !== '') {
            $countryIso = strtoupper(trim($countryIso));
            PlatformSystem::set('platform_default_country_iso', $countryIso);
        }

        if ($dialCode !== null && trim($dialCode) !== '') {
            $dialCode = trim($dialCode);
            if (! str_starts_with($dialCode, '+')) {
                $dialCode = '+'.$dialCode;
            }
            PlatformSystem::set('platform_default_dial_code', $dialCode);
        } elseif ($countryIso !== null && trim($countryIso) !== '') {
            $computedDial = self::dialCodeForCountry($countryIso);
            PlatformSystem::set('platform_default_dial_code', $computedDial);
        }

        // Keep legacy keys synchronized for backward compatibility
        PlatformSystem::set('app_currency', $currency);
        PlatformSystem::set('app_timezone', $timezone);
    }
}
