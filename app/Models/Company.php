<?php

namespace App\Models;

use App\Casts\SafeEncryptedString;
use App\Models\Concerns\HasLegacyStringId;
use App\Services\Modular\ModuleRegistry;
use App\Services\Navigation\TenantNavigationConfigService;
use App\Services\Navigation\TenantNavRegistry;
use App\Support\IdGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'language', 'default_locale', 'timezone', 'logo', 'favicon', 'drawer_cover', 'primary_color', 'accent_color', 'drawer_bg', 'drawer_gradient_enabled', 'drawer_gradient_start', 'drawer_gradient_end', 'drawer_gradient_direction', 'theme_color', 'pos_layout', 'pos_mode', 'restaurant_mode_locked', 'licensed_modules', 'nav_config', 'receipt_format', 'status', 'is_seeding_complete', 'is_profile_completed', 'is_demo', 'plan_name', 'activation_key', 'registered_at', 'expires_at',
        'max_users', 'max_devices', 'pricing_mode', 'tax_api_mode', 'tax_api_key', 'tax_api_endpoint',
        'navigation_menu_customization', 'navigation_labels', 'form_field_customizations',
        'invoice_prefix', 'quotation_prefix', 'repair_prefix', 'prescription_prefix', 'salon_prefix', 'tax_settings', 'invoice_terms', 'quote_terms', 'bank_details',
        'dispensing_disclaimer', 'repair_warranty_terms', 'salon_policy_terms', 'repair_checklist_schema',
        'currency_symbol', 'currency_decimals', 'currency_symbol_position', 'other_currencies',
        'default_commission_rate', 'default_commission_type',
        'pix_key_type', 'pix_key', 'pix_merchant_name', 'pix_merchant_city', 'pix_qr_image',
        'card_fee_debit', 'card_fee_credit_1x', 'card_fee_credit_installments',
        'barcode_scale_prefix', 'barcode_scale_type',
        'store_banner_tag', 'store_banner_title', 'store_banner_subtitle', 'store_banner_cta_text', 'store_banner_cta_link', 'store_banner_image_url', 'store_banner_is_active',
        'enable_google_login', 'google_client_id', 'google_client_secret', 'storefront_payment_gateways',
        'enable_product_reviews', 'require_review_approval',
        'require_customer_verification', 'verification_channels',
        'enable_order_notifications', 'order_notification_channels', 'order_notification_events',
    ];

    protected $appends = [
        'display_name',
        'business_name',
        'trading_name',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_seeding_complete' => 'boolean',
            'is_profile_completed' => 'boolean',
            'is_demo' => 'boolean',
            'drawer_gradient_enabled' => 'boolean',
            'store_banner_is_active' => 'boolean',
            'enable_product_reviews' => 'boolean',
            'require_review_approval' => 'boolean',
            'enable_google_login' => 'boolean',
            'require_customer_verification' => 'boolean',
            'verification_channels' => 'array',
            'enable_order_notifications' => 'boolean',
            'order_notification_channels' => 'array',
            'order_notification_events' => 'array',
            'storefront_payment_gateways' => 'array',
            'google_client_secret' => SafeEncryptedString::class,
            'tax_settings' => 'array',
            'tax_api_key' => SafeEncryptedString::class,
            'currency_decimals' => 'integer',
            'other_currencies' => 'array',
            'restaurant_mode_locked' => 'boolean',
            'licensed_modules' => 'array',
            'repair_checklist_schema' => 'array',
            'nav_config' => 'array',
            'navigation_menu_customization' => 'array',
            'navigation_labels' => 'array',
            'form_field_customizations' => 'array',
            'default_commission_rate' => 'decimal:2',
            'card_fee_debit' => 'decimal:2',
            'card_fee_credit_1x' => 'decimal:2',
            'card_fee_credit_installments' => 'array',
        ];
    }

    /**
     * Return custom form field labels for a specific form schema.
     */
    public function getFormFieldLabels(string $formKey): array
    {
        $custom = $this->form_field_customizations ?? [];
        if (is_string($custom)) {
            $custom = json_decode($custom, true) ?: [];
        }

        return (array) ($custom[$formKey] ?? []);
    }

    /**
     * Resolve a single form field label with aliases and fallback.
     */
    public function resolveFormFieldLabel(string $formKey, string $fieldKey, string $default): string
    {
        $labels = $this->getFormFieldLabels($formKey);
        $candidateKeys = [
            $fieldKey,
            str_replace('-', '_', $fieldKey),
            str_replace('_', '-', $fieldKey),
        ];
        foreach ($candidateKeys as $k) {
            if (! empty($labels[$k]) && is_string($labels[$k])) {
                return $labels[$k];
            }
        }

        return $default;
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

        if (str_starts_with($this->logo, 'data:')) {
            return $this->logo;
        }

        if (preg_match('#(?:https?://[^/]+)?/?storage/(.+)#i', $this->logo, $matches)) {
            $cleanPath = $matches[1];

            return Storage::disk('public')->url($cleanPath);
        }

        if (str_starts_with($this->logo, 'http://') || str_starts_with($this->logo, 'https://')) {
            return $this->logo;
        }

        $cleanPath = ltrim(preg_replace('#^/?storage/#', '', $this->logo), '/');

        if (Storage::disk('public')->exists($cleanPath)) {
            return Storage::disk('public')->url($cleanPath);
        }

        if (str_starts_with($this->logo, '/')) {
            return asset(ltrim($this->logo, '/'));
        }

        return Storage::disk('public')->url($cleanPath);
    }

    public function getFaviconUrl(): ?string
    {
        if (empty($this->favicon)) {
            return null;
        }

        if (str_starts_with($this->favicon, 'data:')) {
            return $this->favicon;
        }

        if (preg_match('#(?:https?://[^/]+)?/?storage/(.+)#i', $this->favicon, $matches)) {
            $cleanPath = $matches[1];

            return Storage::disk('public')->url($cleanPath);
        }

        if (str_starts_with($this->favicon, 'http://') || str_starts_with($this->favicon, 'https://')) {
            return $this->favicon;
        }

        $cleanPath = ltrim(preg_replace('#^/?storage/#', '', $this->favicon), '/');

        if (Storage::disk('public')->exists($cleanPath)) {
            return Storage::disk('public')->url($cleanPath);
        }

        if (str_starts_with($this->favicon, '/')) {
            return asset(ltrim($this->favicon, '/'));
        }

        return Storage::disk('public')->url($cleanPath);
    }

    public function getDrawerCoverUrl(): ?string
    {
        if (empty($this->drawer_cover)) {
            return null;
        }

        if (str_starts_with($this->drawer_cover, 'data:')) {
            return $this->drawer_cover;
        }

        if (preg_match('#(?:https?://[^/]+)?/?storage/(.+)#i', $this->drawer_cover, $matches)) {
            $cleanPath = $matches[1];

            return Storage::disk('public')->url($cleanPath);
        }

        if (str_starts_with($this->drawer_cover, 'http://') || str_starts_with($this->drawer_cover, 'https://')) {
            return $this->drawer_cover;
        }

        $cleanPath = ltrim(preg_replace('#^/?storage/#', '', $this->drawer_cover), '/');

        if (Storage::disk('public')->exists($cleanPath)) {
            return Storage::disk('public')->url($cleanPath);
        }

        if (str_starts_with($this->drawer_cover, '/')) {
            return asset(ltrim($this->drawer_cover, '/'));
        }

        return Storage::disk('public')->url($cleanPath);
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

        static::saved(function (Company $company) {
            $company->flushTenantCaches();
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

    public function inquiries()
    {
        return $this->hasMany(TenantInquiry::class, 'company_id')->orderByDesc('created_at');
    }

    public function getUnreadInquiriesCountAttribute(): int
    {
        return $this->inquiries()->where('status', 'unread')->count();
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
     * Explicit navigation customization tree if tenant rearranged menus.
     *
     * @return list<array<string, mixed>>|null
     */
    public function getNavigationMenuCustomizationAttribute(): ?array
    {
        $custom = $this->attributes['navigation_menu_customization'] ?? null;
        if (! empty($custom)) {
            if (is_string($custom)) {
                $decoded = json_decode($custom, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } elseif (is_array($custom)) {
                return $custom;
            }
        }

        $raw = $this->nav_config;
        if (! is_array($raw) || empty($raw)) {
            return null;
        }

        if (! empty($raw['custom_tree']) && is_array($raw['custom_tree'])) {
            return $raw['custom_tree'];
        }

        return TenantNavRegistry::buildCustomNavTree($this);
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
        return empty($this->pos_mode) || in_array($this->pos_mode, ['general', 'general_retail', 'retail'], true);
    }

    /**
     * Normalised set of module keys this tenant operates in — one or more of
     * retail | restaurant | pharmacy | service_booking | repair_technician.
     *
     * Sourced from `licensed_modules`, falling back to `pos_mode`. Mirrors the
     * gating in TenantNavRegistry so settings screens can hide vertical fields
     * (Rx / repair / salon prefixes, disclaimers) that don't apply.
     *
     * @return list<string>
     */
    public function licensedModuleKeys(): array
    {
        $raw = is_array($this->licensed_modules) && $this->licensed_modules !== []
            ? $this->licensed_modules
            : [$this->pos_mode ?: 'retail'];

        $planExtensions = is_array($this->plan?->extensions) ? $this->plan->extensions : [];
        if (! empty($planExtensions)) {
            $raw = array_merge($raw, $planExtensions);
        }

        $keys = [];
        foreach ($raw as $item) {
            if (! is_string($item)) {
                continue;
            }
            $clean = strtolower(trim($item));
            $canonical = ModuleRegistry::canonicalKey($clean);
            $key = match ($canonical) {
                'general', 'general_retail', 'retail' => 'retail',
                'food_restaurant', 'restaurant' => 'restaurant',
                'repair', 'repairs', 'technician', 'repair_technician', 'repairtechnician', 'automotive', 'electronics_service' => 'repair_technician',
                'salon', 'spa', 'wellness', 'beauty', 'salon_wellness', 'service_booking', 'service', 'services' => 'service_booking',
                'pharmacy', 'pharmacy_pos', 'chemist' => 'pharmacy',
                'lead', 'leads', 'leadmanagement', 'lead_management' => 'leadmanagement',
                'storefront', 'ecommerce', 'ecommerce_storefront', 'online_store' => 'ecommerce_storefront',
                default => $canonical,
            };
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        if ($this->restaurant_mode_locked) {
            $keys = array_values(array_diff($keys, ['restaurant']));
        }

        $keys = array_values(array_unique($keys));

        // Extensions require an explicit Super Admin assignment (or plan grant) and an active,
        // licensed installation. The primary POS mode never grants an extension.
        $extensions = ModuleRegistry::extensionKeys();
        $active = array_intersect($keys, $extensions) === [] ? [] : array_keys(ModuleRegistry::allModules());
        $explicitModules = (is_array($this->licensed_modules) && $this->licensed_modules !== []) || ! empty($planExtensions);
        $keys = array_values(array_filter($keys, fn ($key) => ! in_array($key, $extensions, true)
            || ($explicitModules && in_array($key, $active, true))
            || in_array($key, $planExtensions, true)));

        return $keys === [] ? ['retail'] : $keys;
    }

    public function hasModule(string $moduleKey): bool
    {
        $clean = strtolower(trim($moduleKey));
        $canonical = ModuleRegistry::canonicalKey($clean);
        $norm = match ($canonical) {
            'general', 'general_retail', 'retail' => 'retail',
            'food_restaurant', 'restaurant' => 'restaurant',
            'repair', 'repairs', 'technician', 'repair_technician', 'repairtechnician', 'automotive', 'electronics_service' => 'repair_technician',
            'salon', 'spa', 'wellness', 'beauty', 'salon_wellness', 'service_booking', 'service', 'services' => 'service_booking',
            'pharmacy', 'pharmacy_pos', 'chemist' => 'pharmacy',
            'lead', 'leads', 'leadmanagement', 'lead_management' => 'leadmanagement',
            'storefront', 'ecommerce', 'ecommerce_storefront', 'online_store' => 'ecommerce_storefront',
            default => $canonical,
        };
        $licensed = $this->licensedModuleKeys();

        if ($norm === 'ecommerce_storefront') {
            if (in_array('ecommerce_storefront', $licensed, true) || in_array('catalog', $licensed, true)) {
                return true;
            }
            if (is_array($this->plan?->extensions) && in_array('ecommerce_storefront', $this->plan->extensions, true)) {
                return true;
            }
            if (is_array($this->plan?->features) && (! empty($this->plan->features['ecommerce_storefront']) || in_array('ecommerce_storefront', $this->plan->features, true))) {
                return true;
            }
            // Active by default for all valid active store accounts unless restricted
            return true;
        }

        return in_array($norm, $licensed, true) || in_array($canonical, $licensed, true) || in_array($clean, $licensed, true);
    }

    /**
     * Default repair intake checkpoints for a tenant that hasn't configured
     * its own. Mobile-device biased — a shop overrides this per its vertical.
     *
     * @return list<array{key:string,label:string,default:string}>
     */
    public const DEFAULT_REPAIR_CHECKLIST = [
        ['key' => 'power', 'label' => 'Power On / Boot Up State', 'default' => 'pass'],
        ['key' => 'display', 'label' => 'Display & Touchscreen', 'default' => 'pass'],
        ['key' => 'cameras', 'label' => 'Front & Back Cameras', 'default' => 'pass'],
        ['key' => 'charging', 'label' => 'Charging Port & Battery', 'default' => 'pass'],
        ['key' => 'speakers', 'label' => 'Audio, Mic & Speakers', 'default' => 'pass'],
        ['key' => 'battery', 'label' => 'Battery Health & State', 'default' => 'pass'],
    ];

    /**
     * The tenant's configured repair checklist, normalised to
     * {key, label, default}. Falls back to [DEFAULT_REPAIR_CHECKLIST].
     *
     * @return list<array{key:string,label:string,default:string}>
     */
    public function repairChecklistSchema(): array
    {
        $raw = $this->repair_checklist_schema;
        if (! is_array($raw) || $raw === []) {
            return self::DEFAULT_REPAIR_CHECKLIST;
        }

        $normalized = [];
        foreach ($raw as $i => $item) {
            if (is_string($item)) {
                $label = trim($item);
                $item = ['label' => $label];
            }
            if (! is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? $item['name'] ?? $item['item_name'] ?? ''));
            if ($label === '') {
                continue;
            }
            $key = trim((string) ($item['key'] ?? ''));
            if ($key === '') {
                $key = Str::slug($label, '_') ?: 'check_'.($i + 1);
            }
            $default = strtolower(trim((string) ($item['default'] ?? 'pass')));
            $default = match ($default) {
                'fail', 'failed', 'damaged' => 'fail',
                'na', 'n/a', 'not_applicable', 'not applicable' => 'not_applicable',
                'pending', 'untested', 'not_tested' => 'pending',
                default => 'pass',
            };
            $normalized[] = ['key' => $key, 'label' => $label, 'default' => $default];
        }

        return $normalized !== [] ? $normalized : self::DEFAULT_REPAIR_CHECKLIST;
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

    /**
     * The drawer/sidebar background sent in the bootstrap theme payload.
     * If drawer_background is empty or #FFFFFF (or white), default to null
     * so dark mode applies cleanly with zero white-leak.
     */
    public function getDrawerBg(): string
    {
        $raw = trim((string) $this->drawer_bg);
        $isUnconfiguredOrWhite = $raw === ''
            || in_array(strtolower($raw), ['#ffffff', '#fff', 'ffffff', 'fff', 'white'], true);

        return $isUnconfiguredOrWhite ? '#FFF7ED' : $raw;
    }

    public function getThemeTokens(): array
    {
        $drawerBg = $this->getDrawerBg();

        $tokens = [
            'primary_color' => $this->getPrimaryColor(),
            'accent_color' => $this->getAccentColor(),
            'drawer_bg' => $drawerBg,
            'drawer_background' => $drawerBg,
            'surface' => '#1E293B',
            'background' => '#0F172A',
            'text_primary' => '#F8FAFC',
            'text_secondary' => '#94A3B8',
            'drawer_gradient_enabled' => (bool) ($this->drawer_gradient_enabled ?? false),
            'drawer_gradient_start' => $this->drawer_gradient_start ?? ($drawerBg ?? '#1E293B'),
            'drawer_gradient_end' => $this->drawer_gradient_end ?? '#0F172A',
            'drawer_gradient_direction' => $this->drawer_gradient_direction ?? 'top_to_bottom',
        ];

        $preferences = TenantSetting::get($this->id, 'app_preferences', []);
        $drawerTextColor = is_array($preferences) ? ($preferences['drawer_text_icon_color'] ?? $preferences['drawer_text_and_icons'] ?? $preferences['drawer_icon_color'] ?? $preferences['drawer_text_color'] ?? null) : null;

        if ($drawerTextColor !== null && $drawerTextColor !== '') {
            $tokens['drawer_text_icon_color'] = $drawerTextColor;
            $tokens['drawer_text_and_icons'] = $drawerTextColor;
            $tokens['drawer_icon_color'] = $drawerTextColor;
            $tokens['drawer_text_color'] = $drawerTextColor;
        }

        return $tokens;
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

    /**
     * Unified Store Display Name Resolver.
     * Priority: Business Name (`business_name`) -> Trading Name (`trading_name`) -> Fallback ('Store').
     */
    public function getDisplayNameAttribute(): string
    {
        if (! empty($this->business_name)) {
            return $this->business_name;
        }

        if (! empty($this->trading_name)) {
            return $this->trading_name;
        }

        return $this->name ?? 'Store';
    }

    public function getBusinessNameAttribute(): string
    {
        return trim((string) ($this->attributes['name'] ?? ''));
    }

    public function getTradingNameAttribute(): string
    {
        return trim((string) ($this->attributes['trade_name'] ?? ''));
    }

    public function getSubdomainAttribute(): string
    {
        return trim((string) ($this->attributes['subdomain'] ?? $this->attributes['slug'] ?? ''));
    }

    public function getStorefrontUrlAttribute(): string
    {
        return $this->getStorefrontUrl();
    }

    public function getStorefrontUrl(): string
    {
        if (! empty($this->custom_domain)) {
            $domain = trim($this->custom_domain);
            return str_starts_with($domain, 'http') ? $domain : "https://{$domain}";
        }

        $slug = trim((string) ($this->subdomain ?: $this->slug ?: ''));
        if (empty($slug) && ! empty($this->name)) {
            $slug = \Illuminate\Support\Str::slug($this->name);
        }
        if (empty($slug)) {
            $slug = 'store';
        }

        $baseDomain = config('tenancy.central_domain')
            ?: config('app.domain')
            ?: parse_url(config('app.url', 'https://saas.zoomnearby.com'), PHP_URL_HOST)
            ?: 'saas.zoomnearby.com';

        return "https://{$slug}.{$baseDomain}";
    }

    public function getStoreTypeAttribute(): string
    {
        return strtoupper((string) ($this->attributes['pos_mode'] ?? 'RESTAURANT'));
    }

    public function getGstinAttribute(): ?string
    {
        return $this->tax_id ?: ($this->attributes['gstin'] ?? 'N/A');
    }

    /**
     * Determine if a given name matches a known demo/sample data placeholder.
     */
    public static function isDemoPlaceholderName(?string $name): bool
    {
        if ($name === null) {
            return false;
        }

        $clean = strtolower(trim($name));
        $placeholders = [
            'the copper kettle café',
            'the copper kettle cafe',
            'metro retail mart',
            'carefirst pharmacy',
            'fixpoint device repairs',
            'lumière salon & spa',
            'lumiere salon & spa',
        ];

        return in_array($clean, $placeholders, true);
    }

    /**
     * Resolve effective trade name, strictly prioritizing the primary updated display_name.
     */
    public function getEffectiveTradeName(): string
    {
        return $this->display_name;
    }

    /**
     * Build the structured drawer and navigation header payload.
     *
     * @return array<string, mixed>
     */
    public function getDrawerHeaderPayload(): array
    {
        $storeType = strtoupper($this->pos_mode ?: 'RESTAURANT');
        $badge = str_replace('_', ' & ', strtoupper($this->pos_mode ?: 'CAFE & RESTAURANT'));

        return [
            'store_name' => $this->display_name,
            'business_name' => $this->display_name,
            'tenant_name' => $this->display_name,
            'title' => $this->display_name,
            'name' => $this->display_name,
            'display_name' => $this->display_name,
            'trading_name' => $this->display_name,
            'trade_name' => $this->display_name,
            'dba_name' => trim((string) ($this->attributes['trade_name'] ?? '')),
            'store_type' => $storeType,
            'badge' => $badge,
            'logo_url' => $this->getLogoUrl(),
            'favicon_url' => $this->getFaviconUrl(),
            'drawer_cover_url' => $this->getDrawerCoverUrl(),
        ];
    }

    /**
     * Force-clear all cached tenant bootstrap, navigation, profile, and settings payloads.
     */
    public function flushTenantCaches(): void
    {
        $tenantId = (string) $this->id;
        Cache::forget("tenant_{$tenantId}_bootstrap");
        Cache::forget("tenant_{$tenantId}_navigation");
        Cache::forget("tenant_{$tenantId}_profile");
        Cache::forget("tenant_{$tenantId}_settings");
        Cache::forget("tenant_nav_{$tenantId}");
        Cache::forget("company_{$tenantId}");
        Cache::forget("tenant_executive_kpis_{$tenantId}");
        Cache::forget("tenant_{$tenantId}_drawer");
        Cache::forget("tenant_{$tenantId}_drawer_menu");
        Cache::forget("navigation_menu_{$tenantId}");
        Cache::forget("store_profile_{$tenantId}");
        Cache::forget("tenant_store_profile_{$tenantId}");
        Cache::forget("drawer_menu_{$tenantId}");
        Cache::forget("tenant_{$tenantId}_menu");
    }

    public function coupons(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function faqs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Faq::class);
    }

    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function reviewsEnabled(): bool
    {
        return (bool) ($this->enable_product_reviews ?? true);
    }

    public function getStoreBanner(): array
    {
        return [
            'is_active' => $this->store_banner_is_active ?? true,
            'tag' => $this->store_banner_tag ?: 'SPECIAL STORE DEALS',
            'title' => $this->store_banner_title ?: 'Grab Up To 50% Off On Selected Products',
            'subtitle' => $this->store_banner_subtitle ?: 'Order authentic items online with direct-to-door verified dispatch and real-time inventory.',
            'cta_text' => $this->store_banner_cta_text ?: 'Shop Now',
            'cta_link' => $this->store_banner_cta_link ?: '#products-section',
            'image_url' => $this->store_banner_image_url ? (str_starts_with($this->store_banner_image_url, 'http') ? $this->store_banner_image_url : asset($this->store_banner_image_url)) : null,
        ];
    }

    public function getStorefrontPaymentMethods(): array
    {
        $gateways = (array) ($this->storefront_payment_gateways ?? []);

        // Default local payment options if not set
        if (! isset($gateways['cod'])) {
            $gateways['cod'] = ['enabled' => true, 'name' => 'Cash on Delivery', 'instructions' => 'Pay in cash upon physical delivery.'];
        }
        if (! isset($gateways['store_pickup'])) {
            $gateways['store_pickup'] = ['enabled' => true, 'name' => 'Pay at Counter / Pickup', 'instructions' => 'Pay when collecting items at our store counter.'];
        }

        // Platform-level inheritance: if superadmin enabled gateways globally and tenant hasn't explicitly disabled them
        try {
            $platformGateways = \Illuminate\Support\Facades\DB::table('payment_gateway_settings')->where('enabled', 1)->get();
            foreach ($platformGateways as $pg) {
                if ($pg->gateway === 'razorpay' && ! empty($pg->public_key)) {
                    $tenantExplicitDisabled = isset($gateways['razorpay']) && empty($gateways['razorpay']['enabled']);
                    if (! $tenantExplicitDisabled) {
                        $gateways['razorpay'] = array_merge([
                            'enabled' => true,
                            'name' => 'Razorpay (Cards, UPI, NetBanking)',
                            'instructions' => 'Fast and secure instant payment via Razorpay checkout.',
                        ], (array) ($gateways['razorpay'] ?? []));
                        $gateways['razorpay']['enabled'] = true;
                    }
                } elseif ($pg->gateway === 'stripe' && ! empty($pg->public_key)) {
                    $tenantExplicitDisabled = isset($gateways['stripe']) && empty($gateways['stripe']['enabled']);
                    if (! $tenantExplicitDisabled) {
                        $gateways['stripe'] = array_merge([
                            'enabled' => true,
                            'name' => 'Credit / Debit Card (Stripe)',
                            'instructions' => 'Secure card payment powered by Stripe.',
                        ], (array) ($gateways['stripe'] ?? []));
                        $gateways['stripe']['enabled'] = true;
                    }
                }
            }
        } catch (\Throwable $e) {}

        $active = [];
        $masterDefinitions = [
            'cod' => ['name' => 'Cash on Delivery', 'icon' => 'truck', 'instructions' => 'Pay in cash upon physical delivery'],
            'store_pickup' => ['name' => 'Pay at Counter', 'icon' => 'store', 'instructions' => 'Pay at store counter upon pickup'],
            'razorpay' => ['name' => 'Razorpay (Cards, UPI, NetBanking)', 'icon' => 'credit-card', 'instructions' => 'Fast and secure payment via Razorpay'],
            'stripe' => ['name' => 'Credit / Debit Card', 'icon' => 'credit-card', 'instructions' => 'Secure card payment powered by Stripe'],
            'paypal' => ['name' => 'PayPal', 'icon' => 'paypal', 'instructions' => 'Safe online payment with your PayPal balance or card'],
            'upi' => ['name' => 'Direct UPI / QR', 'icon' => 'qr-code', 'instructions' => 'Scan QR or pay directly via any UPI app'],
        ];

        foreach ($gateways as $gwId => $cfg) {
            $isEnabled = ! empty($cfg['enabled']) && ($cfg['enabled'] === true || $cfg['enabled'] === 'true' || $cfg['enabled'] == 1);
            if ($isEnabled) {
                $master = $masterDefinitions[$gwId] ?? ['name' => ucfirst($gwId), 'icon' => 'credit-card', 'instructions' => ''];
                $active[] = [
                    'id' => $gwId,
                    'name' => ! empty($cfg['name']) ? $cfg['name'] : $master['name'],
                    'icon' => ! empty($cfg['icon']) ? $cfg['icon'] : $master['icon'],
                    'instructions' => ! empty($cfg['instructions']) ? $cfg['instructions'] : $master['instructions'],
                    'enabled' => true,
                    'upi_id' => $cfg['upi_id'] ?? null,
                ];
            }
        }

        if (empty($active)) {
            $active[] = ['id' => 'cod', 'name' => 'Cash on Delivery', 'icon' => 'truck', 'enabled' => true, 'instructions' => 'Pay in cash upon delivery.'];
        }

        return $active;
    }
}
