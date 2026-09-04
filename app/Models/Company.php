<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyStringId;
use App\Services\Navigation\TenantNavigationConfigService;
use App\Support\IdGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasLegacyStringId;

    /**
     * Slugs reserved for platform infrastructure — never assignable as a
     * tenant's subdomain (tenant slugs resolve as {slug}.APP_URL via
     * ResolveTenantContext, so these must stay free for real system use).
     */
    public const RESERVED_SLUGS = [
        'www', 'app', 'saas', 'api', 'admin', 'mail', 'ftp', 'smtp', 'imap',
        'cpanel', 'whm', 'webmail', 'autodiscover', 'autoconfig',
        'static', 'cdn', 'assets', 'ns1', 'ns2', 'portal',
        'blog', 'docs', 'status', 'support', 'help', 'dashboard',
    ];

    protected $fillable = [
        'unique_account_id', 'name', 'slug', 'custom_domain', 'trade_name', 'legal_name', 'tax_id', 'tax_id_label',
        'email', 'phone', 'website', 'address', 'city', 'state', 'postal_code', 'country', 'currency',
        'language', 'default_locale', 'timezone', 'logo', 'favicon', 'drawer_cover', 'primary_color', 'accent_color', 'drawer_bg', 'theme_color', 'pos_layout', 'pos_mode', 'restaurant_mode_locked', 'licensed_modules', 'nav_config', 'receipt_format', 'status', 'plan_name', 'activation_key', 'registered_at', 'expires_at',
        'max_users', 'max_devices', 'pricing_mode', 'tax_api_mode', 'tax_api_key', 'tax_api_endpoint',
        'invoice_prefix', 'quotation_prefix', 'tax_settings', 'invoice_terms', 'quote_terms', 'bank_details',
        'currency_symbol', 'currency_decimals', 'currency_symbol_position', 'other_currencies',
        'default_commission_rate', 'default_commission_type',
        'pix_key_type', 'pix_key', 'pix_merchant_name', 'pix_merchant_city', 'pix_qr_image',
        'card_fee_debit', 'card_fee_credit_1x', 'card_fee_credit_installments',
        'barcode_scale_prefix', 'barcode_scale_type',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'expires_at' => 'datetime',
            'tax_settings' => 'array',
            'tax_api_key' => 'encrypted',
            'currency_decimals' => 'integer',
            'other_currencies' => 'array',
            'restaurant_mode_locked' => 'boolean',
            'licensed_modules' => 'array',
            'nav_config' => 'array',
            'default_commission_rate' => 'decimal:2',
            'card_fee_debit' => 'decimal:2',
            'card_fee_credit_1x' => 'decimal:2',
            'card_fee_credit_installments' => 'array',
        ];
    }

    /**
     * Format an amount using this company's configured currency symbol,
     * decimal precision, and symbol placement (defaults reproduce the
     * historical hardcoded "$" + 2-decimal formatting).
     */
    public function formatMoney($amount): string
    {
        $formatted = number_format((float) $amount, $this->currency_decimals ?? 2);
        $symbol = $this->currency_symbol ?: '$';

        return $this->currency_symbol_position === 'suffix' ? "{$formatted}{$symbol}" : "{$symbol}{$formatted}";
    }

    public function getLogoUrl(): ?string
    {
        if (empty($this->logo)) {
            return null;
        }

        if (str_starts_with($this->logo, 'http://') || str_starts_with($this->logo, 'https://') || str_starts_with($this->logo, 'data:')) {
            return $this->logo;
        }

        $cleanPath = preg_replace('#^/?storage/#', '', $this->logo);

        if (Storage::disk('public')->exists($cleanPath)) {
            return Storage::disk('public')->url($cleanPath);
        }

        if (str_starts_with($this->logo, '/')) {
            return asset(ltrim($this->logo, '/'));
        }

        return asset('storage/'.ltrim($cleanPath, '/'));
    }

    public function getFaviconUrl(): ?string
    {
        if (empty($this->favicon)) {
            return null;
        }

        if (str_starts_with($this->favicon, 'http://') || str_starts_with($this->favicon, 'https://') || str_starts_with($this->favicon, 'data:')) {
            return $this->favicon;
        }

        $cleanPath = preg_replace('#^/?storage/#', '', $this->favicon);

        if (Storage::disk('public')->exists($cleanPath)) {
            return Storage::disk('public')->url($cleanPath);
        }

        if (str_starts_with($this->favicon, '/')) {
            return asset(ltrim($this->favicon, '/'));
        }

        return asset('storage/'.ltrim($cleanPath, '/'));
    }

    public function getDrawerCoverUrl(): ?string
    {
        if (empty($this->drawer_cover)) {
            return null;
        }

        if (str_starts_with($this->drawer_cover, 'http://') || str_starts_with($this->drawer_cover, 'https://') || str_starts_with($this->drawer_cover, 'data:')) {
            return $this->drawer_cover;
        }

        $cleanPath = preg_replace('#^/?storage/#', '', $this->drawer_cover);

        if (Storage::disk('public')->exists($cleanPath)) {
            return Storage::disk('public')->url($cleanPath);
        }

        if (str_starts_with($this->drawer_cover, '/')) {
            return asset(ltrim($this->drawer_cover, '/'));
        }

        return asset('storage/'.ltrim($cleanPath, '/'));
    }

    public function getReceiptFormat(): string
    {
        return $this->receipt_format ?: '80mm';
    }

    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (empty($company->unique_account_id)) {
                $company->unique_account_id = 'ACC-'.strtoupper(bin2hex(random_bytes(4)));
            }
            if (empty($company->activation_key)) {
                $company->activation_key = IdGenerator::make('key_', 16);
            }
            if (empty($company->registered_at)) {
                $company->registered_at = now();
            }
            if (empty($company->pos_mode)) {
                $company->pos_mode = 'general';
            }
        });
    }

    public function idPrefix(): string
    {
        return 'emp_';
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_name', 'name');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class)->orderByDesc('started_at');
    }

    public function subscriptionInvoices()
    {
        return $this->hasMany(SubscriptionInvoice::class)->orderByDesc('invoice_date');
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class)->orderBy('order_index');
    }

    public function isSuspended(): bool
    {
        return in_array($this->status, ['suspended', 'cancelled'], true);
    }

    public function isSubscriptionActive(): bool
    {
        if ($this->isSuspended()) {
            return false;
        }
        if ($this->expires_at === null) {
            return true; // Lifetime / perpetual
        }

        return $this->expires_at->isFuture();
    }

    public function isSubscriptionExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    public function isExpired(): bool
    {
        return $this->isSubscriptionExpired();
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if ($this->expires_at === null) {
            return null; // Lifetime
        }

        return (int) max(0, ceil(now()->diffInDays($this->expires_at, false)));
    }

    public function isRestaurantMode(): bool
    {
        return ! $this->restaurant_mode_locked && in_array($this->pos_mode, ['restaurant', 'food_restaurant'], true);
    }

    /**
     * This tenant's nav customization (Settings > Navigation Menu, mobile
     * and web), normalized to the current `{sections: [{key, order}],
     * items: [{key, section, parent, parent_id, level, order, visible}],
     * tree: [{key, order, items: [{..., children: []}]}]}` shape regardless of
     * which shape `nav_config` actually holds — including this app's older
     * `{hidden_tiles, section_order}` shape (no per-item order, explicit
     * section, or parent existed yet, so those come back null/defaulted:
     * callers fall
     * back to whatever section/position/nesting that item's key defaults to
     * further up the stack — see DashboardScreen._sectionsFor and
     * ALL_DOCK_ITEMS on web). `parent` is another item's key in the same
     * section — null means the item sits at that section's root level; up
     * to two levels of nesting are supported (Main Menu / Sub-Menu /
     * Sub-Sub-Menu — see TenantNavRegistry/buildNavSections()'s depth cap).
     * Every reader
     * (mobile bootstrap, mobile/web settings pages, the web sidebar) goes
     * through this so none of them need to understand a format the others
     * don't.
     */
    public function normalizedNavConfig(): array
    {
        $raw = $this->nav_config ?? [];
        $normalizer = app(TenantNavigationConfigService::class);

        if (! is_array($raw)) {
            Log::warning('Invalid tenant navigation configuration; using defaults.', [
                'company_id' => $this->id,
                'value_type' => get_debug_type($raw),
            ]);

            return $normalizer->normalize([]);
        }

        try {
            if (isset($raw['items']) || isset($raw['sections']) || isset($raw['tree'])) {
                return $normalizer->normalize($raw);
            }

            $sectionOrder = array_values(is_array($raw['section_order'] ?? null) ? $raw['section_order'] : []);
            $hiddenTiles = array_values(is_array($raw['hidden_tiles'] ?? null) ? $raw['hidden_tiles'] : []);

            return $normalizer->normalize([
                'sections' => array_map(
                    fn ($key, $order) => ['key' => $key, 'order' => $order],
                    $sectionOrder,
                    array_keys($sectionOrder)
                ),
                'items' => array_map(
                    fn ($key) => ['key' => $key, 'section' => null, 'parent' => null, 'order' => null, 'visible' => false],
                    $hiddenTiles
                ),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Tenant navigation configuration could not be normalized; using defaults.', [
                'company_id' => $this->id,
                'exception' => $exception,
            ]);

            return $normalizer->normalize([]);
        }
    }

    /**
     * The IANA timezone identifier order timestamps, prep timers, and KOT
     * logs should be shown in: the manual override from Settings > Profile
     * if one is set, else a default derived from `country` — see
     * defaultTimezoneForCountry(). Always returns a valid identifier
     * (falls back to UTC), so callers never need a null check.
     */
    public function resolveTimezone(): string
    {
        if (! empty($this->timezone) && in_array($this->timezone, \DateTimeZone::listIdentifiers(), true)) {
            return $this->timezone;
        }

        return self::defaultTimezoneForCountry($this->country);
    }

    /**
     * One representative IANA zone per ISO-3166 country code, via PHP's
     * built-in per-country tzdata grouping. Countries spanning several
     * zones (US, CA, AU, BR, RU, MX) get a curated pick — PHP's own
     * ordering for those is alphabetical by city, not by population/
     * business relevance (e.g. `US` alone returns `America/Adak` first),
     * so this is not just a plain pass-through of listIdentifiers(). This
     * is always just a *default*: Settings > Profile's manual override
     * exists precisely for the stores it guesses wrong for.
     */
    public static function defaultTimezoneForCountry(?string $country): string
    {
        $code = strtoupper(trim((string) $country));
        if ($code === '') {
            return 'UTC';
        }

        $curated = [
            'US' => 'America/New_York',
            'CA' => 'America/Toronto',
            'AU' => 'Australia/Sydney',
            'BR' => 'America/Sao_Paulo',
            'RU' => 'Europe/Moscow',
            'MX' => 'America/Mexico_City',
            'ID' => 'Asia/Jakarta',
            'CD' => 'Africa/Kinshasa',
            'KZ' => 'Asia/Almaty',
            'MN' => 'Asia/Ulaanbaatar',
            'ES' => 'Europe/Madrid',
            'PT' => 'Europe/Lisbon',
            'MY' => 'Asia/Kuala_Lumpur',
            'PF' => 'Pacific/Tahiti',
            'EC' => 'America/Guayaquil',
            'CL' => 'America/Santiago',
            'UA' => 'Europe/Kyiv',
            'GL' => 'America/Nuuk',
        ];
        if (isset($curated[$code])) {
            return $curated[$code];
        }

        $identifiers = @\DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $code);

        return $identifiers[0] ?? 'UTC';
    }

    public function isGeneralMode(): bool
    {
        return empty($this->pos_mode) || in_array($this->pos_mode, ['general', 'general_retail'], true);
    }

    public function getThemeColor(): string
    {
        return $this->theme_color ?: 'blue';
    }

    public function getPrimaryColor(): string
    {
        return $this->primary_color ?: '#4F46E5';
    }

    public function getAccentColor(): string
    {
        return $this->accent_color ?: '#D97706';
    }

    public function getDrawerBg(): string
    {
        return $this->drawer_bg ?: '#FFF7ED';
    }

    public function getThemeTokens(): array
    {
        return [
            'primary_color' => $this->getPrimaryColor(),
            'accent_color' => $this->getAccentColor(),
            'drawer_bg' => $this->getDrawerBg(),
        ];
    }

    public function getPosLayout(): string
    {
        return $this->pos_layout ?: 'standard';
    }

    public function getThemeColorClasses(): array
    {
        $color = $this->getThemeColor();

        return match ($color) {
            'emerald', 'green' => [
                'bg_primary' => 'bg-emerald-600 dark:bg-emerald-700',
                'bg_hover' => 'hover:bg-emerald-700 dark:hover:bg-emerald-800',
                'text_primary' => 'text-emerald-600 dark:text-emerald-400',
                'border_primary' => 'border-emerald-600 dark:border-emerald-500',
                'ring_primary' => 'ring-emerald-500',
                'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
                'gradient' => 'from-emerald-600 to-teal-700',
                'hex' => '#059669',
            ],
            'indigo' => [
                'bg_primary' => 'bg-indigo-600 dark:bg-indigo-700',
                'bg_hover' => 'hover:bg-indigo-700 dark:hover:bg-indigo-800',
                'text_primary' => 'text-indigo-600 dark:text-indigo-400',
                'border_primary' => 'border-indigo-600 dark:border-indigo-500',
                'ring_primary' => 'ring-indigo-500',
                'badge' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300',
                'gradient' => 'from-indigo-600 to-purple-700',
                'hex' => '#4f46e5',
            ],
            'purple', 'violet' => [
                'bg_primary' => 'bg-purple-600 dark:bg-purple-700',
                'bg_hover' => 'hover:bg-purple-700 dark:hover:bg-purple-800',
                'text_primary' => 'text-purple-600 dark:text-purple-400',
                'border_primary' => 'border-purple-600 dark:border-purple-500',
                'ring_primary' => 'ring-purple-500',
                'badge' => 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300',
                'gradient' => 'from-purple-600 to-pink-700',
                'hex' => '#7c3aed',
            ],
            'amber', 'orange' => [
                'bg_primary' => 'bg-amber-600 dark:bg-amber-700',
                'bg_hover' => 'hover:bg-amber-700 dark:hover:bg-amber-800',
                'text_primary' => 'text-amber-600 dark:text-amber-400',
                'border_primary' => 'border-amber-600 dark:border-amber-500',
                'ring_primary' => 'ring-amber-500',
                'badge' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
                'gradient' => 'from-amber-600 to-orange-700',
                'hex' => '#d97706',
            ],
            'rose', 'red' => [
                'bg_primary' => 'bg-rose-600 dark:bg-rose-700',
                'bg_hover' => 'hover:bg-rose-700 dark:hover:bg-rose-800',
                'text_primary' => 'text-rose-600 dark:text-rose-400',
                'border_primary' => 'border-rose-600 dark:border-rose-500',
                'ring_primary' => 'ring-rose-500',
                'badge' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
                'gradient' => 'from-rose-600 to-red-700',
                'hex' => '#e11d48',
            ],
            'slate', 'dark' => [
                'bg_primary' => 'bg-slate-800 dark:bg-slate-700',
                'bg_hover' => 'hover:bg-slate-900 dark:hover:bg-slate-600',
                'text_primary' => 'text-slate-800 dark:text-slate-200',
                'border_primary' => 'border-slate-800 dark:border-slate-600',
                'ring_primary' => 'ring-slate-700',
                'badge' => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
                'gradient' => 'from-slate-800 to-slate-950',
                'hex' => '#334155',
            ],
            default => [
                'bg_primary' => 'bg-blue-600 dark:bg-blue-700',
                'bg_hover' => 'hover:bg-blue-700 dark:hover:bg-blue-800',
                'text_primary' => 'text-blue-600 dark:text-blue-400',
                'border_primary' => 'border-blue-600 dark:border-blue-500',
                'ring_primary' => 'ring-blue-500',
                'badge' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
                'gradient' => 'from-blue-600 to-indigo-700',
                'hex' => '#2563eb',
            ],
        };
    }

    public function translations(): HasMany
    {
        return $this->hasMany(TenantTranslation::class, 'tenant_id');
    }

    public function getDefaultLocale(): string
    {
        return $this->default_locale ?: ($this->language ?: 'en');
    }
}
