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
        'support_email', 'support_phone', 'head_office_address', 'working_hours', 'smtp_host', 'smtp_port',
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
                'support_phone' => $this->support_phone ?: '+918535075196',
                'support_whatsapp' => $this->support_phone ?: '+918535075196',
                'support_email' => $this->support_email ?: 'support@zoomnearby.com',
                'head_office_address' => $this->getHeadOfficeAddress(),
                'working_hours' => $this->getWorkingHours(),
                'auth_banner_image_url' => \App\Models\DynamicSetting::get('auth_banner_image_url') ?: null,
                'show_auth_banner' => (bool) \App\Models\DynamicSetting::get('show_auth_banner', false),
                'landing_page_enabled' => (bool) $this->landing_page_enabled,
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

    public function getHeadOfficeAddress(): string
    {
        return (string) ($this->head_office_address ?: (setting('contact_office_address') ?: (setting('head_office_address') ?: 'Metrotech Center, NY 11201')));
    }

    public function getWorkingHours(): string
    {
        return (string) ($this->working_hours ?: (setting('contact_working_hours') ?: (setting('working_hours') ?: 'Monday - Friday (07 am - 05 pm)')));
    }

    public function landingPage()
    {
        return $this->belongsTo(Page::class, 'landing_page_id');
    }

    public function getHeroBadge(): string
    {
        return $this->landing_hero_badge ?: __('⚡ The #1 Omnichannel POS & Cloud Commerce Engine');
    }

    public function getHeroTitle(): string
    {
        return $this->landing_hero_title ?: __('Scale Your Store Sales Online & In-Person with Zero Downtime');
    }

    public function getHeroSubtitle(): string
    {
        return $this->landing_hero_subtitle ?: __('Unify your online storefront, barcode checkout, multi-warehouse stock, and WhatsApp invoicing into one lightning-fast cloud POS. Built to turn internet visitors into repeat buyers and keep counters ringing up sales even offline.');
    }

    public function getHeroCtaPrimaryText(): string
    {
        return $this->landing_hero_cta_primary_text ?: __('Start Free Trial — Instant Access');
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
            'badge' => trim((string) ($meta['badge'] ?? '')),
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

    /** Section badge override configured by the SuperAdmin, or the given default. */
    public function getSectionBadge(string $section, string $default = ''): string
    {
        $value = trim((string) ($this->landing_section_meta[$section]['badge'] ?? ''));
        if ($value !== '') {
            return $value;
        }

        $contentBadge = trim((string) data_get($this->landing_content ?? [], "{$section}.badge", ''));
        if ($contentBadge !== '') {
            return $contentBadge;
        }

        return $default;
    }

    /** Section body override configured by the SuperAdmin, or the given default. */
    public function getSectionBody(string $section, string $default = ''): string
    {
        $value = trim((string) ($this->landing_section_meta[$section]['body'] ?? ''));
        if ($value !== '') {
            return $value;
        }

        $contentBody = trim((string) data_get($this->landing_content ?? [], "{$section}.body", ''));
        if ($contentBody !== '') {
            return $contentBody;
        }

        return $default;
    }

    /** Resolved hardware cards with icon, label and tag. */
    public function landingHardware(): array
    {
        $configured = $this->landingList('trust.hardware');
        if (! empty($configured)) {
            return array_map(function ($item) {
                if (is_array($item)) {
                    return [
                        'label' => $item[0] ?? ($item['label'] ?? ''),
                        'tag' => $item[1] ?? ($item['tag'] ?? ''),
                        'icon' => $item[2] ?? ($item['icon'] ?? 'barcode'),
                    ];
                }
                return ['label' => (string) $item, 'tag' => '', 'icon' => 'barcode'];
            }, $configured);
        }

        return [
            ['label' => 'Barcode & QR Scanners', 'icon' => 'barcode', 'tag' => 'Instant Zero-Latency Read'],
            ['label' => 'Thermal Receipt Printers', 'icon' => 'printer', 'tag' => '58mm & 80mm ESC/POS'],
            ['label' => 'Card Readers & QR Terminals', 'icon' => 'card', 'tag' => 'UPI, EMV & NFC Pay'],
            ['label' => 'Smart Cash Drawers', 'icon' => 'drawer', 'tag' => 'Auto-Kick Trigger'],
            ['label' => 'Kitchen & Packing Displays', 'icon' => 'display', 'tag' => 'Live KDS Workflow'],
        ];
    }

    /** Resolved statistics and metrics counter pairs. */
    public function landingStatsList(): array
    {
        $configured = $this->landingList('stats');
        if (! empty($configured)) {
            return array_map(function ($item) {
                if (is_array($item)) {
                    return [
                        'value' => $item[0] ?? ($item['value'] ?? ''),
                        'label' => $item[1] ?? ($item['label'] ?? ''),
                    ];
                }
                return ['value' => '', 'label' => (string) $item];
            }, $configured);
        }

        return [
            ['value' => '2,500,000+', 'label' => __('Sales & Orders Processed')],
            ['value' => '1,200+', 'label' => __('Thriving Store Outlets')],
            ['value' => '99.99%', 'label' => __('Enterprise Uptime SLA')],
            ['value' => '< 20ms', 'label' => __('Sub-Second Checkout Latency')],
        ];
    }

    /** Resolved solution pillars cards with icon, title and description. */
    public function landingSolutionsList(): array
    {
        $configured = $this->landingList('solutions.items');
        if (! empty($configured)) {
            return array_map(function ($item) {
                if (is_array($item)) {
                    return [
                        'icon' => $item[0] ?? ($item['icon'] ?? '⚡'),
                        'title' => $item[1] ?? ($item['title'] ?? ''),
                        'body' => $item[2] ?? ($item['body'] ?? ''),
                    ];
                }
                return ['icon' => '⚡', 'title' => (string) $item, 'body' => ''];
            }, $configured);
        }

        return [
            ['icon' => '🚀', 'title' => __('Drive Online & Foot-Traffic Sales'), 'body' => __('Attract internet buyers with instant digital menus, WhatsApp product sharing, QR payments, and digital receipts that capture customer contacts for repeat sales.')],
            ['icon' => '⚡', 'title' => __('Sub-Second Speed & 100% Offline-Ready'), 'body' => __('Checkout keeps running smoothly when the internet drops. Sales queue safely on device and sync automatically on reconnect — zero lost revenue during peak rush.')],
            ['icon' => '📦', 'title' => __('Live Stock Sync & Zero Overselling'), 'body' => __('Synchronize inventory across your online store, physical shops, and central warehouses in real time. Automatic low-stock triggers prevent embarrassing stockouts.')],
            ['icon' => '🧾', 'title' => __('Automated Tax, Invoices & Ledgers'), 'body' => __('Generate compliant GST/VAT tax invoices, dispatch instant PDF receipts to WhatsApp/email, track customer credit limits, and automate daily shift cash reconciliation.')],
        ];
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
            ['q' => __('How does this platform help my online and retail store get more sales?'), 'a' => __('It seamlessly connects your physical counter and internet shoppers. You can publish an interactive digital catalog, share product links directly to customer WhatsApp, take contactless QR & card payments, and automatically capture customer contact details with digital receipts to drive high-converting repeat sales.')],
            ['q' => __('Does the POS continue working when the internet drops?'), 'a' => __('Yes, 100%. Product lookup, barcode scanning, cart calculations, and checkout continue running locally on your device without pause. When internet connection returns, offline sales synchronize automatically in the background with zero data loss and zero duplicate entries.')],
            ['q' => __('Can I manage both an online store and multiple retail branches?'), 'a' => __('Yes. A single workspace lets you manage unlimited physical branches, warehouses, and online catalogs with live synchronized stock, inter-branch transfers with receiving audits, branch-specific pricing, and unified executive analytics.')],
            ['q' => __('Which hardware devices and printers are supported?'), 'a' => __('Any standard USB or Bluetooth barcode scanner, 80mm and 58mm thermal receipt printers, auto-kick cash drawers, EMV/NFC card terminals, and kitchen display monitors. If it connects to Windows, Android, or browser, it works out of the box — no expensive proprietary hardware to buy.')],
            ['q' => __('Are tax invoices and receipts compliant with GST / VAT?'), 'a' => __('Yes. Tax invoices (GST, VAT, HSN/SAC) are generated automatically with itemized tax breakdowns, sequential numbering, thermal receipt formatting, and branded A4 PDF exports that can be sent straight to customers over WhatsApp or email.')],
            ['q' => __('Can I migrate my existing products and customer data?'), 'a' => __('Yes. With our built-in bulk CSV import tool, you can upload your full product catalog, SKUs, barcodes, prices, stock levels, and customer records in minutes without typing them manually.')],
            ['q' => __('Can I use this for restaurants, cafés, and bakeries too?'), 'a' => __('Yes. Simply toggle on restaurant mode to get interactive table floor plans, Kitchen Order Tickets (KOT) sent to kitchen screens, table QR code ordering, food modifiers, and one-tap bill splitting.')],
            ['q' => __('Can I white-label this platform with my own brand and custom domain?'), 'a' => __('Yes. Customize your platform name, logo, favicon, accent colors, and custom domain to run a completely branded SaaS experience for your stores or clients.')],
            ['q' => __('Is there a free trial, and do I need to enter credit card details?'), 'a' => __('You can launch your store workspace and test all features with zero risk. No credit card is required, no setup fees, and no long-term contracts. Upgrade or cancel anytime directly from your dashboard.')],
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
            [
                'icon' => '🌐',
                'title' => __('Online Store & Digital Catalog'),
                'body' => __("Instant mobile-friendly digital storefront with category & brand filtering\nOne-click WhatsApp product link & cart sharing for direct social commerce\nDirect QR code ordering with instant payment gateway integration\nBenefit: Launch eCommerce in minutes, capture internet buyers with zero marketplace fees, and sync orders automatically."),
                'mockup' => 'pos',
            ],
            [
                'icon' => '🛒',
                'title' => __('Sub-Second Barcode POS Checkout'),
                'body' => __("Millisecond barcode scanning with quick-access visual favorites & held carts\nMulti-tender split payments (Cash, Card, UPI, Wallets, Customer Store Credit)\nHigh-speed thermal receipt printing with automated cash drawer kick pulse\nBenefit: Eliminates counter checkout bottlenecks, handles peak holiday crowds effortlessly, and rings up sales 3x faster."),
                'mockup' => 'pos',
            ],
            [
                'icon' => '📦',
                'title' => __('Omnichannel Inventory & Warehouse Sync'),
                'body' => __("Real-time stock synchronization across online store, physical shops, and central warehouses\nBatch, lot, and expiry date tracking with automatic low-stock reorder thresholds\nInter-branch stock consignments and transfers with dispatch/receiving audit trails\nBenefit: Prevents overselling on the web, eliminates stockouts, and stops capital from locking up in excess inventory."),
                'mockup' => 'inventory',
            ],
            [
                'icon' => '⚡',
                'title' => __('Sub-Second 100% Offline-First POS Engine'),
                'body' => __("Full counter operations, barcode search, cart calculations, and receipt printing without internet\nAutomatic background synchronization on reconnect with tamper-proof duplicate prevention\nContinuous local data caching so tills never freeze during network cuts\nBenefit: Zero downtime and zero lost sales when internet drops during peak shopping hours."),
                'mockup' => 'pos',
            ],
            [
                'icon' => '🧾',
                'title' => __('Automated Tax Invoicing (GST/VAT) & WhatsApp Delivery'),
                'body' => __("Compliant tax invoices generated automatically with itemized tax breakdowns and HSN/SAC codes\nOne-tap instant dispatch to customer WhatsApp, SMS, and Email with branded PDF\nThermal receipts (58mm/80mm) alongside enterprise formatted A4 PDF tax invoices\nBenefit: 100% tax and audit compliance, zero paper waste, and 98% WhatsApp receipt open rates for customer re-engagement."),
                'mockup' => 'finance',
            ],
            [
                'icon' => '💼',
                'title' => __('Quotations, Estimates & Proforma Invoicing'),
                'body' => __("Professional quotation builder with customizable discounts, terms, and validity dates\nOne-click automated conversion from Quote to confirmed Sale and Invoice\nBranded PDF downloads and direct customer sharing via email or messaging\nBenefit: Speeds up B2B and wholesale deal closures, eliminates duplicate manual data entry, and accelerates cash flow."),
                'mockup' => 'finance',
            ],
            [
                'icon' => '💵',
                'title' => __('Cash Register Audit & Blind Shift Reconciliation'),
                'body' => __("Opening cash float registration, paid-in/paid-out petty cash vouchers, and blind counts\nAutomated mid-shift X-Reports and end-of-day Z-Reports with per-cashier accountability\nReal-time cash variance detection that isolates discrepancies per drawer\nBenefit: Stops drawer shrinkage, prevents employee theft, and slashes daily register closing time from hours to minutes."),
                'mockup' => 'finance',
            ],
            [
                'icon' => '📊',
                'title' => __('Accounts Receivable (AR), Payables (AP) & Ledgers'),
                'body' => __("Customer credit limits, balance statements, and aged receivables tracking\nSupplier purchase bills, payment schedules, and outstanding ledger balances\nComprehensive transaction history and automated debit/credit balancing\nBenefit: Maximizes working capital visibility, reduces bad debts, and maintains strong supplier trade terms."),
                'mockup' => 'finance',
            ],
            [
                'icon' => '🧑‍🤝‍🧑',
                'title' => __('Customer CRM, Lead Pipeline & Loyalty Points'),
                'body' => __("360-degree customer purchasing profiles, contact directories, and buying habits\nSales lead management pipeline with activity logging, follow-up reminders, and stage tracking\nAutomated customer loyalty reward points that accumulate and redeem at checkout\nBenefit: Boosts customer lifetime value (LTV) and average order value (AOV) by 25% through personalized loyalty perks."),
            ],
            [
                'icon' => '🍽️',
                'title' => __('Restaurant Floor, Table QR & Kitchen KDS'),
                'body' => __("Visual table floor plans with live occupied, dining, and billing status\nContactless Table QR menu ordering — guests scan, browse, and order from phones\nKitchen Order Tickets (KOT) routed directly to live Kitchen Display System (KDS) screens\nBenefit: Accelerates table turns by 35%, eliminates kitchen order errors, and lowers waitstaff overhead."),
                'mockup' => 'restaurant',
            ],
            [
                'icon' => '💊',
                'title' => __('Pharmacy Drug Batch & Expiration Management'),
                'body' => __("Pharmaceutical drug batch and lot tracking with strict expiration date monitoring\nPrescription record management, doctor attribution, and patient dosage instructions\nFlexible unit conversions (box, strip, tablet, bottle) with batch-level costing\nBenefit: Total health regulatory compliance, zero expired medicine dispensed, and minimized shrinkage."),
            ],
            [
                'icon' => '🔧',
                'title' => __('Repair Workshop & Service Ticket Management'),
                'body' => __("Complete repair lifecycle (Received -> Diagnosing -> Parts Ordered -> Ready -> Delivered)\nDevice serial number/IMEI tracking, intake diagnostic notes, and warranty logs\nIntegrated spare parts inventory deduction and technician labor invoicing\nBenefit: Unlocks high-margin repair service revenue for electronics, computer, and bike shops with total transparency."),
            ],
            [
                'icon' => '✂️',
                'title' => __('Salon, Spa & Appointment Scheduling Calendar'),
                'body' => __("Visual appointment calendar with stylist/therapist scheduling and room assignment\nService catalog with custom durations, add-on treatments, and pricing tiers\nAutomatic stylist commission calculation based on completed services and retail product upsells\nBenefit: Eliminates appointment conflicts, optimizes chair utilization, and motivates staff with accurate commission payouts."),
            ],
            [
                'icon' => '📈',
                'title' => __('Sales Targets, Executive Analytics & Real-Time P&L'),
                'body' => __("Branch, cashier, and staff sales target monitoring with real-time achievement progress\nLive Profit & Loss (P&L) statements, gross margins, and cost-of-goods-sold (COGS) analytics\nTop-selling products, category contribution, and dead-stock identification\nBenefit: Gives business owners 100% financial clarity to cut underperforming lines and maximize net profitability."),
                'mockup' => 'finance',
            ],
            [
                'icon' => '🏢',
                'title' => __('Multi-Branch Franchise & Centralized Control'),
                'body' => __("Centralized catalog management with branch-specific pricing and localized tax rates\nInter-branch stock transfer requests with transit tracking and receiving audits\nConsolidated corporate reports with isolated tenant workspace security\nBenefit: Scale effortlessly from one neighborhood shop to hundreds of franchise locations nationwide."),
            ],
            [
                'icon' => '🔐',
                'title' => __('Role-Based Permissions & Tamper-Proof Audit Logs'),
                'body' => __("Granular per-module permissions (cashiers, store managers, stock clerks, accountants)\nDevice authorization and terminal registration to prevent unauthorized logins\nTamper-proof audit trails for every price override, discount, held cart, and refund\nBenefit: Guards profit margins against cashier discount abuse and keeps operations strictly compliant."),
            ],
            [
                'icon' => '📱',
                'title' => __('Native Cross-Platform Apps (Web, Android, Windows)'),
                'body' => __("Dedicated native Android APK and Windows desktop app for full-screen counter immersion\nDirect ESC/POS thermal printer communication via USB, Bluetooth, and LAN/Ethernet\nRuns on existing hardware — tablets, POS all-in-one terminals, laptops, or mobile phones\nBenefit: No expensive proprietary hardware locks — saves thousands in initial setup and maintenance costs."),
            ],
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
            ['quote' => __('Switching to this platform doubled our online order throughput while cutting counter checkout times in half. The live inventory sync between our web store and physical shops prevented overselling completely.'), 'name' => 'Marcus Vance', 'role' => __('Founder & CEO · Urban Horizon Omnichannel')],
            ['quote' => __('During our holiday rush, our fiber internet went down for nearly three hours. The offline engine kept our counters ringing up sales without skipping a beat. It saved us thousands in lost sales.'), 'name' => 'Sophia Sterling', 'role' => __('Head of Operations · Sterling Luxury Retail')],
            ['quote' => __('We run 6 restaurant and bakery outlets. Having table QR ordering, instant KOT kitchen display routing, and automated WhatsApp receipts in one system transformed our bottom line.'), 'name' => 'David Al-Mansoor', 'role' => __('Managing Director · Artisan Dine Group')],
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
