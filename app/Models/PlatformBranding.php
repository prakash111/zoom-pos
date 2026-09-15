<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PlatformBranding extends Model
{
    protected $table = 'platform_branding';

    protected static function booted(): void
    {
        // Any Superadmin save of the branding row invalidates a public-config
        // response cache so the pre-auth clients see the new colours on their
        // next fetch.
        $flush = function () {
            Cache::forget('public_settings');
            Cache::forget('platform_branding_settings');
            if (Cache::has('landing_page_cache_version')) {
                Cache::increment('landing_page_cache_version');
            } else {
                Cache::forever('landing_page_cache_version', 2);
            }
        };

        static::saved($flush);
        static::deleted($flush);
    }

    protected $fillable = [
        'platform_name', 'logo_url', 'favicon_url', 'primary_color',
        'secondary_color', 'accent_color', 'splash_bg_color', 'auth_bg_color',
        'auth_headline', 'auth_description',
        'superadmin_sidebar_color', 'landing_primary_color', 'landing_accent_color',
        'support_email', 'support_phone', 'smtp_host', 'smtp_port',
        'smtp_username', 'smtp_password', 'smtp_encryption',
        'smtp_from_address', 'smtp_from_name',
        'expiration_reminder_thresholds', 'otp_registration_enabled',
        'landing_page_enabled', 'landing_page_id',
        'landing_hero_badge', 'landing_hero_title', 'landing_hero_subtitle',
        'landing_hero_cta_primary_text', 'landing_hero_cta_primary_url',
        'landing_hero_cta_secondary_text', 'landing_hero_cta_secondary_url',
        'landing_hero_banner_image_url', 'landing_sections_config',
        'landing_playstore_url', 'landing_playstore_enabled',
        'landing_windows_url', 'landing_windows_enabled',
        'landing_section_meta', 'landing_faqs',
        'landing_features', 'landing_testimonials',
        'landing_content',
    ];

    protected function casts(): array
    {
        return [
            'smtp_password' => \App\Casts\SafeEncryptedString::class,
            'expiration_reminder_thresholds' => 'array',
            'otp_registration_enabled' => 'boolean',
            'landing_page_enabled' => 'boolean',
            'landing_sections_config' => 'array',
            'landing_playstore_enabled' => 'boolean',
            'landing_windows_enabled' => 'boolean',
            'landing_section_meta' => 'array',
            'landing_faqs' => 'array',
            'landing_features' => 'array',
            'landing_testimonials' => 'array',
            'landing_content' => 'array',
        ];
    }

    /**
     * Full public URL for the platform logo. `logo_url` is normally a
     * superadmin-pasted external link (see the Branding settings page), but
     * this also resolves a bare local storage path defensively — same
     * fallback chain as Company::getLogoUrl().
     */
    public function getLogoPublicUrl(): ?string
    {
        if (empty($this->logo_url)) {
            return null;
        }

        if (str_starts_with($this->logo_url, 'data:')) {
            return $this->logo_url;
        }

        if (preg_match('#(?:https?://[^/]+)?/?storage/(.+)#i', $this->logo_url, $matches)) {
            $cleanPath = $matches[1];
            return Storage::disk('public')->url($cleanPath);
        }

        if (str_starts_with($this->logo_url, 'http://') || str_starts_with($this->logo_url, 'https://')) {
            return $this->logo_url;
        }

        $cleanPath = ltrim(preg_replace('#^/?storage/#', '', $this->logo_url), '/');

        if (Storage::disk('public')->exists($cleanPath)) {
            return Storage::disk('public')->url($cleanPath);
        }

        if (str_starts_with($this->logo_url, '/')) {
            return asset(ltrim($this->logo_url, '/'));
        }

        return Storage::disk('public')->url($cleanPath);
    }

    /** Same resolution chain as [getLogoPublicUrl] for the favicon. */
    public function getFaviconPublicUrl(): ?string
    {
        if (empty($this->favicon_url)) {
            return null;
        }

        if (str_starts_with($this->favicon_url, 'data:')) {
            return $this->favicon_url;
        }

        if (preg_match('#(?:https?://[^/]+)?/?storage/(.+)#i', $this->favicon_url, $matches)) {
            $cleanPath = $matches[1];
            return Storage::disk('public')->url($cleanPath);
        }

        if (str_starts_with($this->favicon_url, 'http://') || str_starts_with($this->favicon_url, 'https://')) {
            return $this->favicon_url;
        }

        $cleanPath = ltrim(preg_replace('#^/?storage/#', '', $this->favicon_url), '/');

        if (Storage::disk('public')->exists($cleanPath)) {
            return Storage::disk('public')->url($cleanPath);
        }

        if (str_starts_with($this->favicon_url, '/')) {
            return asset(ltrim($this->favicon_url, '/'));
        }

        return Storage::disk('public')->url($cleanPath);
    }

    /**
     * A valid `#RRGGBB` hex or the given fallback. Guards against a blank /
     * "#ffffff" / malformed value stored in the branding row producing an
     * unreadable auth screen.
     */
    private function hexOr(?string $value, string $fallback): string
    {
        $v = trim((string) $value);

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $v) ? strtoupper($v) : $fallback;
    }

    /**
     * The Superadmin global branding + theme contract consumed, unauthenticated,
     * by the pre-auth screens (Splash / Login / Register / Forgot Password).
     * Tenants override this after sign-in via Company::getThemeTokens().
     *
     * @return array{platform: array<string, mixed>, theme: array<string, string>}
     */
    public function publicSettings(): array
    {
        $primary = $this->hexOr($this->primary_color, '#F95700');
        // A white / near-white primary is a leftover placeholder, never a
        // deliberate brand colour — fall back to the platform default.
        if (in_array(strtoupper($primary), ['#FFFFFF', '#FEFEFE', '#FDFDFD', '#000000'], true)) {
            $primary = '#F95700';
        }

        return [
            'platform' => [
                'name' => $this->platform_name ?: config('app.name', 'POS Systems'),
                // Purely Superadmin-authored — no app-side default marketing
                // copy. `null` tells the client to render nothing.
                'headline' => $this->auth_headline ?: null,
                'description' => $this->auth_description ?: null,
                'logo_url' => $this->getLogoPublicUrl(),
                'favicon_url' => $this->getFaviconPublicUrl(),
            ],
            'theme' => [
                'primary_color' => $primary,
                // Alias so a client keyed on `theme.primary` still resolves it.
                'primary' => $primary,
                'secondary_color' => $this->hexOr($this->secondary_color, '#0F172A'),
                'accent_color' => $this->hexOr($this->accent_color, $primary),
                'splash_bg_color' => $this->hexOr($this->splash_bg_color, '#0F172A'),
                'auth_bg_color' => $this->hexOr($this->auth_bg_color, '#F8FAFC'),
            ],
        ];
    }

    public function landingPage()
    {
        return $this->belongsTo(Page::class, 'landing_page_id');
    }

    public function getHeroBadge(): string
    {
        return $this->landing_hero_badge ?: __('All-in-one POS, Inventory & Restaurant Platform');
    }

    public function getHeroTitle(): string
    {
        return $this->landing_hero_title ?: __('The Most Modern POS & Inventory Platform for Your Business');
    }

    public function getHeroSubtitle(): string
    {
        return $this->landing_hero_subtitle ?: __('Unified retail checkout, real-time stock inventory, dining floor KOT, and automated financial ledgers — all in one fast, offline-ready cloud platform.');
    }

    public function getHeroCtaPrimaryText(): string
    {
        return $this->landing_hero_cta_primary_text ?: __('Start Free Trial');
    }

    public function getHeroCtaPrimaryUrl(): string
    {
        return $this->landing_hero_cta_primary_url ?: route('tenant.register');
    }

    public function getHeroCtaSecondaryText(): string
    {
        return $this->landing_hero_cta_secondary_text ?: __('Explore Features');
    }

    public function getHeroCtaSecondaryUrl(): string
    {
        return $this->landing_hero_cta_secondary_url ?: '#features';
    }

    public function isSectionEnabled(string $section): bool
    {
        // The downloads section is only meaningful when at least one store
        // link is configured — default it off otherwise.
        $default = $section === 'downloads' ? $this->hasAnyDownloadLink() : true;

        if (empty($this->landing_sections_config)) {
            return $default;
        }

        return (bool) ($this->landing_sections_config[$section] ?? $default);
    }

    /**
     * Ordered landing sections. Older installations stored only boolean
     * flags, so the legacy order remains the safe fallback.
     */
    public function landingSectionOrder(): array
    {
        $default = ['hero', 'trust_bar', 'features', 'solutions', 'downloads', 'stats', 'about', 'testimonials', 'pricing', 'faq', 'contact', 'cta'];
        $configured = $this->landing_sections_config['order'] ?? [];
        if (! is_array($configured)) {
            return $default;
        }

        $allowed = array_flip($default);
        $ordered = array_values(array_filter(array_map('strval', $configured), fn ($key) => isset($allowed[$key])));
        return array_values(array_unique(array_merge($ordered, array_diff($default, $ordered))));
    }

    /** Return all editable presentation tokens for a section. */
    public function sectionMeta(string $section): array
    {
        $meta = $this->landing_section_meta[$section] ?? [];
        $layout = (string) ($meta['layout'] ?? 'default');
        return [
            'title' => trim((string) ($meta['title'] ?? '')),
            'subtitle' => trim((string) ($meta['subtitle'] ?? '')),
            'body' => trim((string) ($meta['body'] ?? '')),
            'background' => $this->hexOr($meta['background'] ?? null, 'transparent'),
            'text' => $this->hexOr($meta['text'] ?? null, 'inherit'),
            'accent' => $this->hexOr($meta['accent'] ?? null, $this->landing_accent_color ?: '#d7f24e'),
            'layout' => in_array($layout, ['default', 'centered', 'wide', 'compact'], true) ? $layout : 'default',
        ];
    }

    /** Resolve any landing-page copy key with a built-in fallback. */
    public function landingText(string $key, string $default = ''): string
    {
        $value = data_get($this->landing_content ?? [], $key);
        return filled($value) ? trim((string) $value) : $default;
    }

    /** Resolve editable list content (highlights, stats, hardware, etc.). */
    public function landingList(string $key, array $default = []): array
    {
        $value = data_get($this->landing_content ?? [], $key);
        return is_array($value) && $value !== [] ? $value : $default;
    }

    /** Section title override configured by the SuperAdmin, or the given default. */
    public function getSectionTitle(string $section, string $default = ''): string
    {
        $value = trim((string) ($this->landing_section_meta[$section]['title'] ?? ''));

        return $value !== '' ? $value : $default;
    }

    /** Section subtitle override configured by the SuperAdmin, or the given default. */
    public function getSectionSubtitle(string $section, string $default = ''): string
    {
        $value = trim((string) ($this->landing_section_meta[$section]['subtitle'] ?? ''));

        return $value !== '' ? $value : $default;
    }

    /** Public Google Play Store URL, or null when disabled / blank. */
    public function playStoreLink(): ?string
    {
        return $this->landing_playstore_enabled && filled($this->landing_playstore_url)
            ? $this->landing_playstore_url
            : null;
    }

    /** Windows installer download URL, or null when disabled / blank. */
    public function windowsAppLink(): ?string
    {
        return $this->landing_windows_enabled && filled($this->landing_windows_url)
            ? $this->landing_windows_url
            : null;
    }

    public function hasAnyDownloadLink(): bool
    {
        return $this->playStoreLink() !== null || $this->windowsAppLink() !== null;
    }

    /**
     * FAQ entries for the landing page: the SuperAdmin-authored list, or a
     * sensible built-in set when none has been configured.
     *
     * @return array<int, array{q: string, a: string}>
     */
    public function landingFaqs(): array
    {
        $configured = collect($this->landing_faqs ?? [])
            ->map(fn ($row) => [
                'q' => trim((string) ($row['q'] ?? '')),
                'a' => trim((string) ($row['a'] ?? '')),
            ])
            ->filter(fn ($row) => $row['q'] !== '' && $row['a'] !== '')
            ->values()
            ->all();

        if ($configured !== []) {
            return $configured;
        }

        return [
            ['q' => __('Do I need to install anything to get started?'), 'a' => __('No. Create a workspace and start ringing up sales from any modern browser in minutes. Native Android and Windows apps are optional and add full-screen terminal mode, faster hardware access and offline-first speed.')],
            ['q' => __('Does the POS keep working when the internet drops?'), 'a' => __('Yes. Checkout, product search, stock lookups and cash register actions all run from a local copy of your data. Sales made offline are queued safely and sync automatically the moment the connection returns — nothing is lost, and duplicates are prevented.')],
            ['q' => __('Can I run more than one store, branch or warehouse?'), 'a' => __('Yes. A single workspace supports unlimited locations with a shared product catalogue, per-branch stock and pricing, inter-branch transfers with receiving audit trails, and consolidated reporting across the whole business.')],
            ['q' => __('Which hardware does it support?'), 'a' => __('Any standard USB or Bluetooth barcode scanner, 80mm and 58mm thermal receipt printers, auto-kick cash drawers, EMV/NFC card readers, and Kitchen Display Screens. If it works with Windows or Android, it works here — no proprietary terminal to buy.')],
            ['q' => __('Is it built for restaurants and cafés as well as retail?'), 'a' => __('Yes. Switch on restaurant mode for interactive dining floor plans, Kitchen Order Tickets routed to KDS screens, QR-code table ordering, per-seat items and modifiers, course pacing, and one-tap table merge or bill split.')],
            ['q' => __('Are the tax invoices compliant?'), 'a' => __('Compliant tax invoices (VAT / GST / HSN) are generated automatically with correct tax breakdowns, sequential numbering, multi-currency pricing, and thermal or A4 PDF output that can be sent to the customer over WhatsApp or email instantly.')],
            ['q' => __('Can I import my existing products and customers?'), 'a' => __('Yes. Bulk-import products, categories, barcodes, prices and stock from a CSV file, and add customers the same way, so you can move off spreadsheets or another POS without re-typing your catalogue.')],
            ['q' => __('Can I control what each staff member can see and do?'), 'a' => __('Yes. Assign granular, per-module roles — for example a cashier who can sell but not edit prices or view reports — and every sensitive action is written to an audit log.')],
            ['q' => __('Is my data secure and backed up?'), 'a' => __('Every tenant\'s data is fully isolated. All data is encrypted in transit and at rest, with automated cloud redundancy and point-in-time recovery.')],
            ['q' => __('What do the native Android and Windows apps add?'), 'a' => __('A distraction-free full-screen till, quicker access to scanners, printers and cash drawers, remembered window size on desktop, and a hardened offline-first sync engine for busy counters and unreliable connections.')],
            ['q' => __('Can I use my own brand, domain and pricing?'), 'a' => __('Yes. White-label the platform name, logo, favicon, colours and landing page, run it on your own custom domain, and publish your own subscription plans from the admin panel.')],
            ['q' => __('Is there a free trial, and are there setup fees or contracts?'), 'a' => __('You can launch a workspace and evaluate the full system with no card required, no setup fee and no long-term contract. Upgrade, downgrade or cancel from the billing screen at any time.')],
        ];
    }

    /**
     * "Feature Modules" cards for the landing page: the SuperAdmin-authored
     * list, or the built-in default set when none has been configured.
     *
     * Shape: [{ icon, title, body, mockup? }]. `mockup` is an optional
     * decorative-panel key used only by the modern theme's tabbed layout
     * (`components/landing/features.blade.php`) — custom entries omit it and
     * fall back to a cycling default panel.
     *
     * @return array<int, array{icon: string, title: string, body: string, mockup?: string}>
     */
    public function landingFeatures(): array
    {
        $configured = collect($this->landing_features ?? [])
            ->map(fn ($row) => array_filter([
                'icon' => trim((string) ($row['icon'] ?? '')),
                'title' => trim((string) ($row['title'] ?? '')),
                'body' => trim((string) ($row['body'] ?? '')),
                'mockup' => trim((string) ($row['mockup'] ?? '')) ?: null,
            ], fn ($v) => $v !== null))
            ->filter(fn ($row) => ($row['title'] ?? '') !== '' && ($row['body'] ?? '') !== '')
            ->values()
            ->all();

        if ($configured !== []) {
            return $configured;
        }

        return [
            ['icon' => '📦', 'title' => __('Smart Inventory & Stock Control'), 'body' => __('Real-time stock across every warehouse and branch, one-click barcode & SKU labels, batch and expiry tracking, and automatic low-stock re-order alerts — so you never oversell and shrinkage drops.'), 'mockup' => 'inventory'],
            ['icon' => '🛒', 'title' => __('Lightning Retail POS'), 'body' => __('Sub-second barcode checkout, split cash / card / digital tender, customer credit accounts and held orders. Shorter queues at peak, and not a single lost sale.'), 'mockup' => 'pos'],
            ['icon' => '🍽️', 'title' => __('Restaurant & Dining Service'), 'body' => __('Live floor plans, Kitchen Order Tickets routed to KDS screens, QR table ordering, per-seat modifiers, course pacing, table merge and bill split. Faster table turns with fewer kitchen mistakes.'), 'mockup' => 'restaurant'],
            ['icon' => '🧾', 'title' => __('Finance, Tax & Invoicing'), 'body' => __('Compliant VAT / GST / HSN invoices generated automatically, multi-currency pricing, thermal and A4 receipts, instant WhatsApp or email delivery, and built-in AP / AR ledgers. Books that are always audit-ready.'), 'mockup' => 'finance'],
            ['icon' => '⚡', 'title' => __('Offline-First Reliability'), 'body' => __('Keep selling when the internet drops. Transactions queue locally, sync automatically on reconnect, and survive a mid-sync crash without duplicates or corruption.')],
            ['icon' => '💵', 'title' => __('Cash Register & Shift Control'), 'body' => __('Opening float, paid-in / paid-out, blind counts and automated X and Z shift reports give you tight, per-cashier cash accountability at every close.')],
            ['icon' => '🧑‍🤝‍🧑', 'title' => __('Customers, Credit & Loyalty'), 'body' => __('Customer accounts with credit limits, statement-ready ledgers, payment histories and loyalty points — drive repeat business while keeping receivables under control.')],
            ['icon' => '🏢', 'title' => __('Multi-Location Workspaces'), 'body' => __('Scale from one till to a nationwide franchise with isolated tenant data, a shared catalogue, per-branch pricing, consolidated reporting and your own custom domain.')],
            ['icon' => '🔐', 'title' => __('Roles & Granular Permissions'), 'body' => __('Per-module access control — a cashier who can sell but not discount, a manager who can see reports but not payroll — with a full audit log of every sensitive action.')],
            ['icon' => '📱', 'title' => __('Works On Every Device'), 'body' => __('The same system in any browser, plus native Android and Windows desktop apps for a full-screen till, faster hardware access and offline-first speed.')],
        ];
    }

    /**
     * Customer testimonials for the landing page: the SuperAdmin-authored
     * list, or the built-in default set when none has been configured.
     *
     * @return array<int, array{quote: string, name: string, role: string}>
     */
    public function landingTestimonials(): array
    {
        $configured = collect($this->landing_testimonials ?? [])
            ->map(fn ($row) => [
                'quote' => trim((string) ($row['quote'] ?? '')),
                'name' => trim((string) ($row['name'] ?? '')),
                'role' => trim((string) ($row['role'] ?? '')),
            ])
            ->filter(fn ($row) => $row['quote'] !== '' && $row['name'] !== '')
            ->values()
            ->all();

        if ($configured !== []) {
            return $configured;
        }

        return [
            ['quote' => __('We switched all our retail outlets over in one afternoon. Inventory clears immediately and end-of-day reconciliation takes seconds.'), 'name' => 'Alexander Hayes', 'role' => __('Operations Director · Apex Retail Group')],
            ['quote' => __('The offline checkout saved us during a major fiber cut on a busy weekend. Not a single sale or customer was lost.'), 'name' => 'Elena Rostova', 'role' => __('Founder · Metro Gourmet Markets')],
            ['quote' => __('POS, inventory and KOT kitchen displays in a single dashboard transformed our restaurant chain.'), 'name' => 'Tariq Mansour', 'role' => __('Head of Operations · Urban Dine Hospitality')],
        ];
    }

    /** Two-letter initials for a testimonial avatar chip. */
    public static function testimonialInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return '★';
        }
        $first = mb_substr($parts[0], 0, 1);
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    /**
     * Determine if SMTP has been configured by the SuperAdmin.
     */
    public function isSmtpConfigured(): bool
    {
        return filled($this->smtp_host);
    }

    /**
     * Check if OTP email verification is required for tenant dashboard access.
     */
    public function isOtpVerificationRequired(): bool
    {
        return $this->isSmtpConfigured();
    }

    /**
     * Singleton row (id = 1), created by PlatformDefaultsSeeder during
     * install. Explicitly passes defaults on create.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'platform_name' => 'Smart Inventory & Sales',
            'superadmin_sidebar_color' => '#4338ca',
            'landing_primary_color' => '#10b981',
            'landing_accent_color' => '#d7f24e',
            'otp_registration_enabled' => false,
            'landing_page_enabled' => false,
        ]);
    }
}
