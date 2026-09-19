<?php

namespace App\Livewire\SuperAdmin\Settings;

use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PlatformBranding;
use App\Models\PlatformSystem;
use App\Models\PushNotificationSetting;
use App\Models\SduiModule;
use App\Services\Localization\PlatformRegionalService;
use App\Services\Modular\ModuleCatalog;
use App\Services\Modular\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.superadmin', ['title' => 'System & Platform Settings'])]
class Index extends Component
{
    use WithPagination, WithFileUploads;

    #[Url(as: 'tab')]
    public string $activeTab = 'general';

    public string $landingTheme = 'theme_fast';

    public array $social = [
        'google' => ['enabled' => false, 'client_id' => '', 'client_secret' => ''],
        'facebook' => ['enabled' => false, 'client_id' => '', 'client_secret' => ''],
    ];

    // --- TAB 1: GENERAL & SYSTEM SETTINGS ---
    public string $appName = '';

    public string $appCurrency = 'USD';

    public string $appTimezone = 'UTC';

    public string $platformDefaultCurrency = 'USD';

    public string $platformDefaultLanguage = 'en';

    public string $platformDefaultTimezone = 'UTC';

    public bool $maintenanceMode = false;

    public string $maintenanceMessage = '';

    public string $minClientBuildVersion = '0';

    public string $appVersion = '1.0.0';

    public bool $showPoweredBy = true;

    public string $allowedRegistrationModes = 'both';

    /** @var array<int, string> */
    public array $enabledRegistrationModules = [];

    // When on (the existing default), a new tenant signup is auto-seeded
    // with sample products/categories/tables/transactions unless the
    // caller explicitly opts out per-request (seed_demo_data: false). When
    // off, TenantProvisioningService::registerTenant() skips seeding
    // regardless of what the caller sent, so every new tenant starts clean.
    public bool $autoSeedDemoDataOnRegistration = true;

    // Platform-wide AI Product Image Generation
    public bool $aiImageEnabled = false;

    public string $aiImageProvider = 'openai';

    public string $aiImageOpenaiApiKey = '';

    public string $aiImageGeminiApiKey = '';

    public string $aiImageClaudeApiKey = '';

    public bool $hasAiImageOpenaiApiKey = false;

    public bool $hasAiImageGeminiApiKey = false;

    public bool $hasAiImageClaudeApiKey = false;

    // --- TAB 2: SMTP SETTINGS ---
    public string $smtpHost = '';

    public ?int $smtpPort = 587;

    public string $smtpUsername = '';

    public string $smtpPassword = '';

    public string $smtpEncryption = 'tls';

    public string $smtpFromAddress = '';

    public string $smtpFromName = '';

    public bool $hasStoredPassword = false;

    public string $testEmailTo = '';

    // --- GLOBAL PUSH NOTIFICATION SETTINGS ---
    public bool $pushEnabled = false;

    public string $fcmProjectId = '';

    public string $fcmServiceAccountJson = '';

    public string $fcmServerKey = '';

    public bool $hasFcmServiceAccount = false;

    public bool $hasFcmServerKey = false;

    public string $androidApiKey = '';

    public string $androidAppId = '';

    public string $messagingSenderId = '';

    public bool $hasAndroidApiKey = false;

    public string $orderChannelId = 'delayed_orders_alarm';

    public string $orderChannelName = 'Delayed order alarms';

    public string $orderSound = 'alarm';

    public string $invoiceChannelId = 'due_invoice_reminders';

    public string $invoiceChannelName = 'Due invoice reminders';

    public string $invoiceSound = 'alarm';

    public int $alarmRepeatSeconds = 60;

    // --- TAB 3: WHITE-LABEL & BRANDING ---
    public string $homepageMode = 'modular'; // 'modular' or 'static_page'

    // Menu Management State
    public string $menuLocation = 'header'; // 'header', 'footer_col_1', 'footer_col_2'
    public string $newMenuTitle = '';
    public string $newMenuUrl = '';
    public ?int $newMenuPageId = null;
    public bool $newMenuTargetBlank = false;

    public ?int $editingMenuItemId = null;
    public string $editingMenuItemTitle = '';
    public string $editingMenuItemUrl = '';
    public string $editingMenuItemTarget = '_self';
    public bool $editingMenuItemActive = true;

    public string $platformName = '';

    public string $logoUrl = '';

    public $logoImage = null;

    public string $faviconUrl = '';

    public bool $showAuthBanner = false;

    public string $authBannerImageUrl = '';

    public $authBannerImage = null;

    public bool $enableRegistrationDomainSetup = true;

    public string $primaryColor = '#4f46e5';

    public string $superadminSidebarColor = '#4338ca';

    public string $landingPrimaryColor = '#10b981';

    public string $landingAccentColor = '#d7f24e';

    public string $supportEmail = '';

    public string $supportPhone = '';

    public bool $otpRegistrationEnabled = false;

    public bool $landingPageEnabled = true;

    public ?int $landingPageId = null;

    public string $landingHeroBadge = '';

    public string $landingHeroTitle = '';

    public string $landingHeroSubtitle = '';

    public string $landingHeroCtaPrimaryText = '';

    public string $landingHeroCtaPrimaryUrl = '';

    public string $landingHeroCtaSecondaryText = '';

    public string $landingHeroCtaSecondaryUrl = '';

    public string $landingHeroBannerImageUrl = '';

    public bool $sectionTrustBar = true;

    public bool $sectionFeatures = true;

    public bool $sectionSolutions = true;

    public bool $sectionStats = true;

    public bool $sectionAbout = true;

    public bool $sectionTestimonials = true;

    public bool $sectionPricing = true;

    public bool $sectionContact = true;

    public bool $sectionCta = true;

    public bool $sectionHero = true;

    public bool $sectionDownloads = true;

    public bool $sectionFaq = true;

    // App download links
    public string $landingPlaystoreUrl = '';

    public bool $landingPlaystoreEnabled = false;

    public string $landingWindowsUrl = '';

    public bool $landingWindowsEnabled = false;

    /**
     * Per-section title / subtitle overrides, keyed by section slug.
     *
     * @var array<string, array{title: string, subtitle: string}>
     */
    public array $sectionMeta = [
        'hero' => ['badge' => '', 'title' => '', 'subtitle' => '', 'body' => '', 'background' => '#0f172a', 'accent' => '#d7f24e'],
        'trust_bar' => ['badge' => '', 'title' => '', 'subtitle' => '', 'body' => '', 'background' => '#0f172a', 'accent' => '#d7f24e'],
        'features' => ['badge' => '', 'title' => '', 'subtitle' => '', 'body' => '', 'background' => '#ffffff', 'accent' => '#10b981'],
        'solutions' => ['badge' => '', 'title' => '', 'subtitle' => '', 'body' => '', 'background' => '#f8fafc', 'accent' => '#10b981'],
        'downloads' => ['badge' => '', 'title' => '', 'subtitle' => '', 'body' => '', 'background' => '#f8fafc', 'accent' => '#10b981'],
        'stats' => ['badge' => '', 'title' => '', 'subtitle' => '', 'body' => '', 'background' => '#0f172a', 'accent' => '#d7f24e'],
        'about' => ['badge' => '', 'title' => '', 'subtitle' => '', 'body' => '', 'background' => '#0f172a', 'accent' => '#10b981'],
        'testimonials' => ['badge' => '', 'title' => '', 'subtitle' => '', 'body' => '', 'background' => '#f8fafc', 'accent' => '#10b981'],
        'pricing' => ['badge' => '', 'title' => '', 'subtitle' => '', 'body' => '', 'background' => '#0f172a', 'accent' => '#d7f24e'],
        'faq' => ['badge' => '', 'title' => '', 'subtitle' => '', 'body' => '', 'background' => '#f8fafc', 'accent' => '#10b981'],
        'contact' => ['badge' => '', 'title' => '', 'subtitle' => '', 'body' => '', 'background' => '#ffffff', 'accent' => '#10b981'],
        'cta' => ['badge' => '', 'title' => '', 'subtitle' => '', 'body' => '', 'background' => '#0f172a', 'accent' => '#d7f24e'],
    ];

    /** Comma-separated section slugs; saved order is used by compatible themes. */
    public string $landingSectionOrder = 'hero,trust_bar,features,solutions,downloads,stats,about,testimonials,pricing,faq,contact,cta';

    /** @var array<int, array{q: string, a: string}> */
    public array $landingFaqs = [];

    /** @var array<int, array{icon: string, title: string, body: string}> */
    public array $landingFeatures = [];

    /** @var array<int, array{quote: string, name: string, role: string}> */
    public array $landingTestimonials = [];

    public string $landingFeaturesJson = '';

    public string $landingTestimonialsJson = '';

    /** Complete override map for every visible landing-page label/copy. */
    public string $landingContentJson = '';

    public string $landingCustomHtml = '';

    public array $landingHeroHighlights = [];
    public array $landingHeroProducts = [];
    public array $landingHardwareItems = [];
    public array $landingStats = [];
    public array $landingSolutions = [];

    // --- TAB 4: CUSTOM PAGES (CMS) ---
    public string $pageSearch = '';

    public function mount(): void
    {
        abort_unless(auth('platform_web')->user()?->hasRole('super_admin'), 403);

        if (request()->routeIs('superadmin.settings.notifications')) {
            $this->activeTab = 'push';
        }

        if (request()->routeIs('superadmin.settings.regional')) {
            $this->activeTab = 'general';
        }

        $allowedTabs = ['general', 'smtp', 'push', 'branding', 'whitelabel', 'social', 'pages', 'appearance'];
        if (! in_array($this->activeTab, $allowedTabs, true)) {
            $this->activeTab = 'general';
        }

        // Load Platform System Settings
        $this->appName = (string) PlatformSystem::get('app_name', config('app.name', 'Smart Inventory & Sales'));
        $this->platformDefaultCurrency = PlatformRegionalService::defaultCurrency();
        $this->platformDefaultLanguage = PlatformRegionalService::defaultLanguage();
        $this->platformDefaultTimezone = PlatformRegionalService::defaultTimezone();
        $this->appCurrency = $this->platformDefaultCurrency;
        $this->appTimezone = $this->platformDefaultTimezone;
        $this->maintenanceMode = filter_var(PlatformSystem::get('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN);
        $this->maintenanceMessage = (string) PlatformSystem::get('maintenance_message', '');
        $this->minClientBuildVersion = (string) PlatformSystem::get('min_client_build_version', '0');
        $this->appVersion = (string) PlatformSystem::get('app_version', '1.0.0');
        $this->showPoweredBy = filter_var(PlatformSystem::get('show_powered_by', true), FILTER_VALIDATE_BOOLEAN);
        $this->autoSeedDemoDataOnRegistration = filter_var(PlatformSystem::get('auto_seed_demo_data_on_registration', true), FILTER_VALIDATE_BOOLEAN);
        $guard = $this->moduleGovernance();
        $this->enabledRegistrationModules = array_values(array_filter(
            ModuleRegistry::enabledRegistrationModes(),
            fn ($key) => empty($guard[$key]['premium']) || ! empty($guard[$key]['licensed']),
        ));
        $this->allowedRegistrationModes = in_array('restaurant', $this->enabledRegistrationModules, true) && in_array('retail', $this->enabledRegistrationModules, true) ? 'both' : (in_array('restaurant', $this->enabledRegistrationModules, true) ? 'restaurant_only' : 'retail_only');
        $this->aiImageEnabled = filter_var(PlatformSystem::get('ai_image_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $this->aiImageProvider = (string) PlatformSystem::get('ai_image_provider', 'openai');
        $this->hasAiImageOpenaiApiKey = filled(PlatformSystem::get('ai_image_openai_api_key'));
        $this->hasAiImageGeminiApiKey = filled(PlatformSystem::get('ai_image_gemini_api_key'));
        $this->hasAiImageClaudeApiKey = filled(PlatformSystem::get('ai_image_claude_api_key'));

        // Load Platform Branding & SMTP
        $branding = PlatformBranding::current();
        $this->platformName = (string) ($branding->platform_name ?: 'Smart Inventory & Sales');
        $this->logoUrl = (string) ($branding->logo_url ?: \App\Models\DynamicSetting::get('platform_logo_url', ''));
        $this->faviconUrl = (string) $branding->favicon_url;
        $this->showAuthBanner = (bool) \App\Models\DynamicSetting::get('show_auth_banner', false);
        $this->authBannerImageUrl = (string) \App\Models\DynamicSetting::get('auth_banner_image_url', '');
        $this->enableRegistrationDomainSetup = (bool) \App\Models\DynamicSetting::get('enable_registration_domain_setup', true);
        $this->primaryColor = $branding->primary_color ?? '#4f46e5';
        $this->superadminSidebarColor = $branding->superadmin_sidebar_color ?? '#4338ca';
        $this->landingPrimaryColor = $branding->landing_primary_color ?? '#10b981';
        $this->landingAccentColor = $branding->landing_accent_color ?? '#d7f24e';
        $this->supportEmail = (string) $branding->support_email;
        $this->supportPhone = (string) $branding->support_phone;
        $this->otpRegistrationEnabled = (bool) $branding->otp_registration_enabled;
        $this->landingPageEnabled = (bool) $branding->landing_page_enabled;
        $this->landingPageId = $branding->landing_page_id;
        $this->homepageMode = (string) data_get($branding->landing_content, 'homepage_mode', ($branding->landing_page_id ? 'static_page' : 'modular'));

        $this->landingHeroBadge = (string) ($branding->landing_hero_badge ?? '');
        $this->landingHeroTitle = (string) ($branding->landing_hero_title ?? '');
        $this->landingHeroSubtitle = (string) ($branding->landing_hero_subtitle ?? '');
        $this->landingHeroCtaPrimaryText = (string) ($branding->landing_hero_cta_primary_text ?? '');
        $this->landingHeroCtaPrimaryUrl = (string) ($branding->landing_hero_cta_primary_url ?? '');
        $this->landingHeroCtaSecondaryText = (string) ($branding->landing_hero_cta_secondary_text ?? '');
        $this->landingHeroCtaSecondaryUrl = (string) ($branding->landing_hero_cta_secondary_url ?? '');
        $this->landingHeroBannerImageUrl = (string) ($branding->landing_hero_banner_image_url ?? '');

        $cfg = $branding->landing_sections_config ?? [];
        $this->landingSectionOrder = implode(',', $branding->landingSectionOrder());
        $this->sectionTrustBar = (bool) ($cfg['trust_bar'] ?? true);
        $this->sectionFeatures = (bool) ($cfg['features'] ?? true);
        $this->sectionSolutions = (bool) ($cfg['solutions'] ?? true);
        $this->sectionStats = (bool) ($cfg['stats'] ?? true);
        $this->sectionAbout = (bool) ($cfg['about'] ?? true);
        $this->sectionTestimonials = (bool) ($cfg['testimonials'] ?? true);
        $this->sectionPricing = (bool) ($cfg['pricing'] ?? true);
        $this->sectionContact = (bool) ($cfg['contact'] ?? true);
        $this->sectionCta = (bool) ($cfg['cta'] ?? true);
        $this->sectionHero = (bool) ($cfg['hero'] ?? true);
        $this->sectionFaq = (bool) ($cfg['faq'] ?? true);
        $this->sectionDownloads = (bool) ($cfg['downloads'] ?? $branding->hasAnyDownloadLink());

        $this->landingPlaystoreUrl = (string) ($branding->landing_playstore_url ?? '');
        $this->landingPlaystoreEnabled = (bool) $branding->landing_playstore_enabled;
        $this->landingWindowsUrl = (string) ($branding->landing_windows_url ?? '');
        $this->landingWindowsEnabled = (bool) $branding->landing_windows_enabled;

        $meta = $branding->landing_section_meta ?? [];
        foreach (array_keys($this->sectionMeta) as $key) {
            $this->sectionMeta[$key] = [
                'badge' => (string) ($meta[$key]['badge'] ?? ''),
                'title' => (string) ($meta[$key]['title'] ?? ''),
                'subtitle' => (string) ($meta[$key]['subtitle'] ?? ''),
                'body' => (string) ($meta[$key]['body'] ?? ''),
                'background' => (string) ($meta[$key]['background'] ?? ($this->sectionMeta[$key]['background'] ?? '#ffffff')),
                'accent' => (string) ($meta[$key]['accent'] ?? ($this->sectionMeta[$key]['accent'] ?? '#10b981')),
            ];
        }

        $this->landingFaqs = collect($branding->landing_faqs ?? [])
            ->map(fn ($row) => ['q' => (string) ($row['q'] ?? ''), 'a' => (string) ($row['a'] ?? '')])
            ->values()
            ->all();
        if (empty($this->landingFaqs)) {
            $this->landingFaqs = $branding->landingFaqs();
        }

        $this->landingFeatures = collect($branding->landing_features ?? [])
            ->map(fn ($row) => [
                'icon' => (string) ($row['icon'] ?? '✨'),
                'title' => (string) ($row['title'] ?? ''),
                'body' => (string) ($row['body'] ?? ''),
            ])
            ->values()
            ->all();
        if (empty($this->landingFeatures)) {
            $this->landingFeatures = $branding->landingFeatures();
        }

        $this->landingTestimonials = collect($branding->landing_testimonials ?? [])
            ->map(fn ($row) => [
                'quote' => (string) ($row['quote'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'role' => (string) ($row['role'] ?? ''),
            ])
            ->values()
            ->all();
        if (empty($this->landingTestimonials)) {
            $this->landingTestimonials = $branding->landingTestimonials();
        }

        $this->landingFeaturesJson = '';
        $this->landingTestimonialsJson = '';
        $this->landingContentJson = json_encode($branding->landing_content ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '';
        $this->landingCustomHtml = (string) data_get($branding->landing_content ?? [], 'html', '');
        $content = $branding->landing_content ?? [];
        $this->landingHeroHighlights = array_values(data_get($content, 'hero.highlights', []));
        if (empty($this->landingHeroHighlights)) {
            $this->landingHeroHighlights = [
                'Sub-second barcode checkout',
                'Offline-first synchronization',
                'Multi-warehouse inventory tracking',
                'Compliant tax invoicing (VAT/GST)',
            ];
        }

        $this->landingHeroProducts = array_values(data_get($content, 'hero.products', []));
        $this->landingHardwareItems = array_values(data_get($content, 'trust.hardware', []));
        if (empty($this->landingHardwareItems)) {
            $this->landingHardwareItems = [
                ['label' => 'Barcode Scanners', 'tag' => 'Instant Scan', 'icon' => 'barcode'],
                ['label' => 'Thermal Receipt Printers', 'tag' => '58mm / 80mm', 'icon' => 'printer'],
                ['label' => 'Card Readers & Terminals', 'tag' => 'EMV & NFC', 'icon' => 'card'],
                ['label' => 'Smart Cash Drawers', 'tag' => 'Auto Kick', 'icon' => 'drawer'],
                ['label' => 'Kitchen Display Screens', 'tag' => 'Live KDS', 'icon' => 'display'],
            ];
        } else {
            $this->landingHardwareItems = array_map(fn($h) => is_array($h) ? [
                'label' => (string)($h['label'] ?? ($h[0] ?? '')),
                'tag' => (string)($h['tag'] ?? ($h[1] ?? '')),
                'icon' => (string)($h['icon'] ?? ($h[2] ?? 'barcode')),
            ] : ['label' => (string)$h, 'tag' => '', 'icon' => 'barcode'], $this->landingHardwareItems);
        }

        $this->landingStats = array_values(data_get($content, 'stats', []));
        if (empty($this->landingStats)) {
            $this->landingStats = [
                ['value' => '2.5M+', 'label' => 'Transactions Processed'],
                ['value' => '1,200+', 'label' => 'Active Business Outlets'],
                ['value' => '99.99%', 'label' => 'Platform Uptime SLA'],
                ['value' => '< 20ms', 'label' => 'Auth & Checkout Latency'],
            ];
        } else {
            $this->landingStats = array_map(fn($s) => is_array($s) ? [
                'value' => (string)($s['value'] ?? ($s[0] ?? '')),
                'label' => (string)($s['label'] ?? ($s[1] ?? '')),
            ] : ['value' => '', 'label' => (string)$s], $this->landingStats);
        }

        $this->landingSolutions = array_values(data_get($content, 'solutions.items', []));
        if (empty($this->landingSolutions)) {
            $this->landingSolutions = [
                ['icon' => '⚡', 'title' => 'Sub-Second Speed & Offline-Ready', 'body' => 'Checkout keeps running if the internet drops. Sales queue safely and sync automatically on reconnect.'],
                ['icon' => '💳', 'title' => 'Direct Card Issuing & Split Payments', 'body' => 'Issue virtual and physical cards, set spend controls, and take multi-tender checkouts without extra merchant accounts.'],
                ['icon' => '📊', 'title' => 'Real-Time Financial & Ledger Control', 'body' => 'Automated register X/Z reconciliation, payable/receivable balances and compliance-ready tax invoices.'],
                ['icon' => '🏢', 'title' => 'Multi-Location Enterprise Workspaces', 'body' => 'Isolated tenant databases, custom domains and granular role permissions from one till to a national franchise.'],
            ];
        } else {
            $this->landingSolutions = array_map(fn($sol) => is_array($sol) ? [
                'icon' => (string)($sol['icon'] ?? ($sol[0] ?? '⚡')),
                'title' => (string)($sol['title'] ?? ($sol[1] ?? '')),
                'body' => (string)($sol['body'] ?? ($sol[2] ?? '')),
            ] : ['icon' => '⚡', 'title' => (string)$sol, 'body' => ''], $this->landingSolutions);
        }

        $this->landingTheme = (string) setting('landing_page_theme', 'theme_fast');

        foreach (array_keys($this->social) as $provider) {
            $this->social[$provider] = [
                'enabled' => filter_var(PlatformSystem::get("social_{$provider}_enabled", false), FILTER_VALIDATE_BOOLEAN),
                'client_id' => (string) PlatformSystem::get("social_{$provider}_client_id", ''),
                'client_secret' => '',
            ];
        }

        // Load SMTP
        $this->smtpHost = (string) $branding->smtp_host;
        $this->smtpPort = $branding->smtp_port ?? 587;
        $this->smtpUsername = (string) $branding->smtp_username;
        $this->smtpEncryption = $branding->smtp_encryption ?? 'tls';
        $this->smtpFromAddress = (string) ($branding->smtp_from_address ?? $branding->support_email ?? '');
        $this->smtpFromName = (string) ($branding->smtp_from_name ?? $branding->platform_name ?? '');
        $this->hasStoredPassword = filled($branding->smtp_password);
        $this->testEmailTo = (string) (auth('platform_web')->user()?->email ?? '');

        $push = PushNotificationSetting::current();
        $this->pushEnabled = $push->enabled;
        $this->fcmProjectId = (string) $push->fcm_project_id;
        $this->hasFcmServiceAccount = filled($push->fcm_service_account_json);
        $this->hasFcmServerKey = filled($push->fcm_server_key);
        $this->hasAndroidApiKey = filled($push->android_api_key);
        $this->androidAppId = (string) $push->android_app_id;
        $this->messagingSenderId = (string) $push->messaging_sender_id;
        $this->orderChannelId = (string) $push->order_channel_id;
        $this->orderChannelName = (string) $push->order_channel_name;
        $this->orderSound = (string) $push->order_sound;
        $this->invoiceChannelId = (string) $push->invoice_channel_id;
        $this->invoiceChannelName = (string) $push->invoice_channel_name;
        $this->invoiceSound = (string) $push->invoice_sound;
        $this->alarmRepeatSeconds = (int) $push->alarm_repeat_seconds;
    }

    public function setTab(string $tab): void
    {
        $allowedTabs = ['general', 'smtp', 'push', 'branding', 'whitelabel', 'social', 'pages', 'appearance'];
        if (in_array($tab, $allowedTabs, true)) {
            $this->activeTab = $tab;
        }
    }

    public function updatedPlatformDefaultCurrency(string $value): void
    {
        $this->appCurrency = $value;
    }

    public function updatedAppCurrency(string $value): void
    {
        $this->platformDefaultCurrency = $value;
    }

    public function updatedPlatformDefaultTimezone(string $value): void
    {
        $this->appTimezone = $value;
    }

    public function updatedAppTimezone(string $value): void
    {
        $this->platformDefaultTimezone = $value;
    }

    public function savePushNotifications(): void
    {
        $data = $this->validate([
            'pushEnabled' => ['boolean'],
            'fcmProjectId' => ['nullable', 'string', 'max:255'],
            'fcmServiceAccountJson' => ['nullable', 'json'],
            'fcmServerKey' => ['nullable', 'string', 'max:4096'],
            'androidApiKey' => ['nullable', 'string', 'max:1000'],
            'androidAppId' => ['nullable', 'string', 'max:255'],
            'messagingSenderId' => ['nullable', 'string', 'max:255'],
            'orderChannelId' => ['required', 'regex:/^[a-z0-9_.-]+$/', 'max:100'],
            'orderChannelName' => ['required', 'string', 'max:100'],
            'orderSound' => ['required', 'in:alarm,notification,ringtone'],
            'invoiceChannelId' => ['required', 'regex:/^[a-z0-9_.-]+$/', 'max:100'],
            'invoiceChannelName' => ['required', 'string', 'max:100'],
            'invoiceSound' => ['required', 'in:alarm,notification,ringtone'],
            'alarmRepeatSeconds' => ['required', 'integer', 'min:15', 'max:600'],
        ]);

        $push = PushNotificationSetting::current();
        if ($this->pushEnabled
            && blank($this->fcmServiceAccountJson)
            && blank($this->fcmServerKey)
            && blank($push->fcm_service_account_json)
            && blank($push->fcm_server_key)) {
            $this->addError('fcmServiceAccountJson', 'Add a service account JSON or legacy server key before enabling push.');

            return;
        }

        $update = [
            'enabled' => $data['pushEnabled'],
            'fcm_project_id' => $data['fcmProjectId'] ?: null,
            'android_app_id' => $data['androidAppId'] ?: null,
            'messaging_sender_id' => $data['messagingSenderId'] ?: null,
            'order_channel_id' => $data['orderChannelId'],
            'order_channel_name' => $data['orderChannelName'],
            'order_sound' => $data['orderSound'],
            'invoice_channel_id' => $data['invoiceChannelId'],
            'invoice_channel_name' => $data['invoiceChannelName'],
            'invoice_sound' => $data['invoiceSound'],
            'alarm_repeat_seconds' => $data['alarmRepeatSeconds'],
        ];

        if (filled($this->fcmServiceAccountJson)) {
            $credentials = json_decode($this->fcmServiceAccountJson, true);
            if (empty($credentials['client_email']) || empty($credentials['private_key'])) {
                $this->addError('fcmServiceAccountJson', 'The service account must contain client_email and private_key.');

                return;
            }
            $update['fcm_service_account_json'] = $this->fcmServiceAccountJson;
            $update['fcm_project_id'] = $update['fcm_project_id'] ?: ($credentials['project_id'] ?? null);
        }
        if (filled($this->fcmServerKey)) {
            $update['fcm_server_key'] = trim($this->fcmServerKey);
        }
        if (filled($this->androidApiKey)) {
            $update['android_api_key'] = trim($this->androidApiKey);
        }

        // Auto-discover Android app credentials from Google if missing
        if (filled($this->fcmServiceAccountJson) || filled($push->fcm_service_account_json)) {
            $saJson = filled($this->fcmServiceAccountJson) ? $this->fcmServiceAccountJson : $push->fcm_service_account_json;
            if (blank($this->androidApiKey) && blank($push->android_api_key) || blank($update['android_app_id']) || blank($update['messaging_sender_id'])) {
                $discovered = PushNotificationSetting::discoverFirebaseConfig($saJson);
                if ($discovered) {
                    if ((blank($this->androidApiKey) && blank($push->android_api_key)) && ! empty($discovered['android_api_key'])) {
                        $update['android_api_key'] = $discovered['android_api_key'];
                    }
                    if (blank($update['android_app_id']) && ! empty($discovered['android_app_id'])) {
                        $update['android_app_id'] = $discovered['android_app_id'];
                    }
                    if (blank($update['messaging_sender_id']) && ! empty($discovered['messaging_sender_id'])) {
                        $update['messaging_sender_id'] = $discovered['messaging_sender_id'];
                    }
                    if (empty($update['fcm_project_id']) && ! empty($discovered['project_id'])) {
                        $update['fcm_project_id'] = $discovered['project_id'];
                    }
                }
            }
        }

        $push->update($update);
        $fresh = $push->fresh();
        $this->fcmProjectId = (string) $fresh->fcm_project_id;
        $this->androidAppId = (string) $fresh->android_app_id;
        $this->messagingSenderId = (string) $fresh->messaging_sender_id;
        $this->hasFcmServiceAccount = filled($fresh->fcm_service_account_json);
        $this->hasFcmServerKey = filled($fresh->fcm_server_key);
        $this->hasAndroidApiKey = filled($fresh->android_api_key);
        $this->reset('fcmServiceAccountJson', 'fcmServerKey', 'androidApiKey');

        AuditLog::record('push.settings_updated', null, auth('platform_web')->id(), [
            'enabled' => $this->pushEnabled,
            'project_id' => $this->fcmProjectId,
            'order_channel_id' => $this->orderChannelId,
            'invoice_channel_id' => $this->invoiceChannelId,
        ]);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Global push notification settings saved.']);
        session()->flash('status', 'Global push notification settings saved.');
    }

    public function testPushNotifications(): void
    {
        $push = PushNotificationSetting::current();

        if (! $push->enabled) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Push notifications are currently disabled. Enable them and save first.']);
            return;
        }

        if (blank($push->fcm_service_account_json) && blank($push->fcm_server_key)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No Firebase credentials configured. Add a service account JSON first.']);
            return;
        }

        try {
            $pushService = app(\App\Services\Push\FirebasePushService::class);
            $ref = new \ReflectionClass($pushService);

            if ($push->fcm_service_account_json && $push->fcm_project_id) {
                $method = $ref->getMethod('accessToken');
                $method->setAccessible(true);
                $accessToken = $method->invoke($pushService, $push);

                // Perform FCM v1 dry-run validation with Google
                $response = \Illuminate\Support\Facades\Http::asJson()
                    ->withToken($accessToken)
                    ->timeout(15)
                    ->post("https://fcm.googleapis.com/v1/projects/{$push->fcm_project_id}/messages:send", [
                        'validate_only' => true,
                        'message' => [
                            'topic' => 'test-healthcheck',
                            'data' => [
                                'title' => 'Health Check',
                                'body' => 'FCM v1 connection verified',
                            ],
                        ],
                    ]);

                if (! $response->successful()) {
                    throw new \RuntimeException('Google FCM API error: ' . $response->body());
                }

                $activeDevices = \App\Models\PushDevice::withoutGlobalScope('company')
                    ->whereNull('revoked_at')
                    ->count();

                $androidReady = filled($push->android_api_key) && filled($push->android_app_id);
                $androidStatus = $androidReady ? "Android client bootstrap configured." : "Warning: Android API Key is empty.";
                $message = "Firebase Cloud Messaging v1 verified! Authenticated with project {$push->fcm_project_id}. {$androidStatus} ({$activeDevices} active devices)";
                $this->dispatch('notify', ['type' => 'success', 'message' => $message]);
                session()->flash('status', $message);
            } else {
                $this->dispatch('notify', ['type' => 'info', 'message' => 'Legacy server key stored.']);
            }
        } catch (\Throwable $e) {
            $errorMsg = 'Push notification test failed: ' . $e->getMessage();
            $this->dispatch('notify', ['type' => 'error', 'message' => $errorMsg]);
            session()->flash('error', $errorMsg);
        }
    }

    public function setLandingTheme(string $themeKey): void
    {
        $allowedThemes = ['theme_fast', 'theme_modern', 'theme_enterprise', 'theme_minimal', 'theme_dark_studio'];
        if (in_array($themeKey, $allowedThemes, true)) {
            $this->landingTheme = $themeKey;
            set_setting('landing_page_theme', $themeKey);
            cache()->forget('app_landing_page_theme');
            cache()->forget('landing_page_theme_config');
            cache()->forget('landing_sections_theme_palette');
            if (cache()->has('landing_page_cache_version')) {
                cache()->increment('landing_page_cache_version');
            } else {
                cache()->forever('landing_page_cache_version', 2);
            }
            $this->dispatch('toast', ['message' => 'Landing page layout updated successfully!', 'type' => 'success']);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Landing page layout updated successfully!']);
        }
    }

    public function updatedLandingTheme(string $value): void
    {
        $this->setLandingTheme($value);
    }

    public function saveAppearance(array $appearance): void
    {
        $allowedLayouts = ['slim', 'expanded', 'macos-dock', 'speed-dial'];
        $allowedPositions = ['left', 'right', 'top', 'bottom', 'floating'];
        $allowedModes = ['docked', 'floating'];
        $allowedItems = ['dashboard', 'tenants', 'plans', 'codes', 'taxes', 'menus', 'inquiries', 'pages', 'settings', 'smtp'];

        $layout = in_array($appearance['layout'] ?? null, $allowedLayouts, true) ? $appearance['layout'] : 'slim';
        $position = in_array($appearance['position'] ?? null, $allowedPositions, true) ? $appearance['position'] : 'left';
        $mode = in_array($appearance['mode'] ?? null, $allowedModes, true) ? $appearance['mode'] : 'docked';
        $visibleItems = array_values(array_intersect($allowedItems, (array) ($appearance['visibleItems'] ?? [])));

        $landingDarkBg = $this->validatedColor($appearance['landingDarkBg'] ?? $appearance['landing_dark_bg'] ?? null, '#0b0f19');

        $values = [
            'appearance_nav_layout' => $layout,
            'appearance_nav_position' => $position,
            'appearance_nav_mode' => $mode,
            'appearance_nav_custom_bg' => mb_substr((string) ($appearance['customBg'] ?? ''), 0, 255),
            'appearance_ui_accent_color' => $this->validatedColor($appearance['uiAccentColor'] ?? null, '#4f46e5'),
            'appearance_nav_text_color' => $this->validatedColor($appearance['navTextColor'] ?? null, '#ffffff'),
            'appearance_nav_text_active_color' => $this->validatedColor($appearance['navTextActiveColor'] ?? null, '#60a5fa'),
            'appearance_nav_visible_items' => json_encode($visibleItems ?: ['dashboard', 'tenants', 'plans', 'taxes', 'menus', 'inquiries', 'pages', 'settings', 'smtp']),
            'landing_dark_bg' => $landingDarkBg,
        ];

        foreach ($values as $key => $value) {
            set_setting($key, $value);
        }
        set_setting('appearance_defaults_version', (string) now()->getTimestampMs());

        if (\Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
            $settings = \Illuminate\Support\Facades\DB::table('system_settings')->where('key', 'superadmin_theme_customization')->value('value');
            $config = $settings ? json_decode($settings, true) : [];
            $config['landing_dark_bg'] = $landingDarkBg;
            \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(
                ['key' => 'superadmin_theme_customization'],
                [
                    'value'      => json_encode($config),
                    'updated_at' => now(),
                ]
            );
        }

        $paletteInput = $appearance['palette'] ?? $appearance['landingSectionsPalette'] ?? $appearance['landing_sections_theme_palette'] ?? null;
        if ($paletteInput !== null) {
            $defaults = default_landing_sections_palette();
            $sections = array_keys($defaults);

            if (is_string($paletteInput)) {
                $paletteInput = json_decode($paletteInput, true) ?: [];
            }
            if (! is_array($paletteInput)) {
                $paletteInput = [];
            }

            $sanitizedPalette = [];
            foreach ($sections as $sec) {
                $sanitizedPalette[$sec] = [];
                $secKeys = array_keys($defaults[$sec] ?? []);
                foreach ($secKeys as $k) {
                    $val = trim((string) ($paletteInput[$sec][$k] ?? $defaults[$sec][$k] ?? ''));
                    if (! str_starts_with($val, '#') && ! empty($val)) {
                        $val = '#' . $val;
                    }
                    if (empty($val) || ! preg_match('/^#[0-9a-fA-F]{3,8}$/', $val)) {
                        $val = $defaults[$sec][$k] ?? '#ffffff';
                    }
                    $sanitizedPalette[$sec][$k] = $val;
                }
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
                \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(
                    ['key' => 'landing_sections_theme_palette'],
                    [
                        'value'      => json_encode($sanitizedPalette),
                        'updated_at' => now(),
                    ]
                );
            }
            set_setting('landing_sections_theme_palette', json_encode($sanitizedPalette));
            \Illuminate\Support\Facades\Cache::forget('landing_sections_theme_palette');
        }

        \Illuminate\Support\Facades\Cache::forget('superadmin_theme_settings');
        \Illuminate\Support\Facades\Cache::forget('landing_page_theme_config');
        \Illuminate\Support\Facades\Cache::forget('app_landing_page_theme');
        if (\Illuminate\Support\Facades\Cache::has('landing_page_cache_version')) {
            \Illuminate\Support\Facades\Cache::increment('landing_page_cache_version');
        } else {
            \Illuminate\Support\Facades\Cache::forever('landing_page_cache_version', 2);
        }

        AuditLog::record('appearance.defaults_updated', null, auth('platform_web')->id(), ['after' => $values]);
        $this->dispatch('appearance-defaults-saved', defaults: appearance_defaults());
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Global appearance defaults saved successfully.']);
        session()->flash('status', 'Global appearance defaults saved successfully.');
    }

    public function applyMatchingPalettePattern(string $themeKey = 'obsidian'): void
    {
        $result = apply_matching_landing_palette($themeKey);
        AuditLog::record('appearance.matching_palette_applied', null, auth('platform_web')->id(), ['theme' => $themeKey]);
        $this->dispatch('palette-pattern-applied', $result);
        $this->dispatch('notify', ['type' => 'success', 'message' => "Harmonized '{$result['name']}' color pattern applied across all sections!"]);
        session()->flash('status', "Harmonized '{$result['name']}' color pattern applied across all sections!");
    }

    public function saveSocialLogin(): void
    {
        $this->validate([
            'social.*.enabled' => ['boolean'],
            'social.*.client_id' => ['nullable', 'string', 'max:500'],
            'social.*.client_secret' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($this->social as $provider => $config) {
            PlatformSystem::set("social_{$provider}_enabled", $config['enabled'] ? '1' : '0');
            PlatformSystem::set("social_{$provider}_client_id", $config['client_id']);
            if (filled($config['client_secret'])) {
                PlatformSystem::set("social_{$provider}_client_secret", $config['client_secret']);
            }
            $this->social[$provider]['client_secret'] = '';
        }

        AuditLog::record('social_login.updated', null, auth('platform_web')->id());
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Social login settings saved successfully.']);
        session()->flash('status', 'Social login settings saved successfully.');
    }

    private function validatedColor(mixed $value, string $default): string
    {
        $value = trim((string) $value);
        if (! str_starts_with($value, '#') && ! empty($value)) {
            $value = '#' . $value;
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $default;
    }

    // --- GENERAL TAB SAVE ---
    public function saveGeneral(): void
    {
        $this->validate([
            'appName' => ['required', 'string', 'max:255'],
            'platformDefaultCurrency' => ['required', 'string', 'max:10'],
            'platformDefaultLanguage' => ['required', 'string', 'max:10'],
            'platformDefaultTimezone' => ['required', 'string', 'max:100'],
            'appCurrency' => ['nullable', 'string', 'max:10'],
            'appTimezone' => ['nullable', 'string', 'max:100'],
            'maintenanceMessage' => ['nullable', 'string', 'max:500'],
            'minClientBuildVersion' => ['required', 'string', 'max:50'],
            'appVersion' => ['required', 'string', 'max:50'],
            'enabledRegistrationModules' => ['required', 'array', 'min:1'],
            'enabledRegistrationModules.*' => ['string'],
            'aiImageEnabled' => ['boolean'],
            'aiImageProvider' => ['required', 'in:openai,gemini,claude'],
            'aiImageOpenaiApiKey' => ['nullable', 'string', 'max:500'],
            'aiImageGeminiApiKey' => ['nullable', 'string', 'max:500'],
            'aiImageClaudeApiKey' => ['nullable', 'string', 'max:500'],
            'autoSeedDemoDataOnRegistration' => ['boolean'],
        ]);

        $before = [
            'maintenance_mode' => PlatformSystem::get('maintenance_mode', '0'),
            'min_client_build_version' => PlatformSystem::get('min_client_build_version', '0'),
            'auto_seed_demo_data_on_registration' => PlatformSystem::get('auto_seed_demo_data_on_registration', '1'),
        ];

        $this->allowedRegistrationModes = in_array('restaurant', $this->enabledRegistrationModules, true) && in_array('retail', $this->enabledRegistrationModules, true) ? 'both' : (in_array('restaurant', $this->enabledRegistrationModules, true) ? 'restaurant_only' : 'retail_only');

        if ($this->appCurrency !== $this->platformDefaultCurrency) {
            if ($this->platformDefaultCurrency === 'USD' && $this->appCurrency !== 'USD') {
                $this->platformDefaultCurrency = $this->appCurrency;
            } else {
                $this->appCurrency = $this->platformDefaultCurrency;
            }
        }

        if ($this->appTimezone !== $this->platformDefaultTimezone) {
            if ($this->platformDefaultTimezone === 'UTC' && $this->appTimezone !== 'UTC') {
                $this->platformDefaultTimezone = $this->appTimezone;
            } else {
                $this->appTimezone = $this->platformDefaultTimezone;
            }
        }

        PlatformSystem::set('app_name', $this->appName);
        PlatformRegionalService::setPlatformDefaults(
            $this->platformDefaultCurrency,
            $this->platformDefaultLanguage,
            $this->platformDefaultTimezone
        );
        PlatformSystem::set('maintenance_mode', $this->maintenanceMode ? '1' : '0');
        PlatformSystem::set('maintenance_message', $this->maintenanceMessage);
        PlatformSystem::set('min_client_build_version', $this->minClientBuildVersion);
        PlatformSystem::set('app_version', $this->appVersion);
        PlatformSystem::set('show_powered_by', $this->showPoweredBy ? '1' : '0');
        PlatformSystem::set('auto_seed_demo_data_on_registration', $this->autoSeedDemoDataOnRegistration ? '1' : '0');
        // Never persist a key that is no longer a real store type — an
        // uninstalled / deactivated package module must not linger in this
        // list even if it was somehow still in the posted payload.
        $validModeKeys = array_keys(ModuleRegistry::operatingModules());
        $guard = $this->moduleGovernance();
        $this->enabledRegistrationModules = array_values(array_filter(
            array_intersect(array_values($this->enabledRegistrationModules), $validModeKeys),
            // A premium vertical can only be enabled once its module is licensed.
            fn ($key) => empty($guard[$key]['premium']) || ! empty($guard[$key]['licensed']),
        ));
        PlatformSystem::set('allowed_registration_modes', json_encode($this->enabledRegistrationModules));
        PlatformSystem::set('ai_image_enabled', $this->aiImageEnabled ? '1' : '0');
        PlatformSystem::set('ai_image_provider', $this->aiImageProvider);

        foreach ([
            'ai_image_openai_api_key' => 'aiImageOpenaiApiKey',
            'ai_image_gemini_api_key' => 'aiImageGeminiApiKey',
            'ai_image_claude_api_key' => 'aiImageClaudeApiKey',
        ] as $settingKey => $property) {
            if (filled($this->{$property})) {
                PlatformSystem::set($settingKey, trim($this->{$property}));
                $this->{'has'.ucfirst($property)} = true;
                $this->{$property} = '';
            }
        }

        AuditLog::record('system.settings_updated', null, auth('platform_web')->id(), [
            'before' => $before,
            'after' => [
                'maintenance_mode' => $this->maintenanceMode ? '1' : '0',
                'min_client_build_version' => $this->minClientBuildVersion,
                'auto_seed_demo_data_on_registration' => $this->autoSeedDemoDataOnRegistration ? '1' : '0',
            ],
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Platform general settings saved successfully.',
        ]);
        session()->flash('status', 'Platform general settings saved successfully.');
    }

    public function clearSystemCache(): void
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('view:clear');
            Artisan::call('config:clear');

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'System application and view caches cleared successfully.',
            ]);
            session()->flash('status', 'System cache cleared.');
        } catch (\Throwable $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to clear cache: '.$e->getMessage(),
            ]);
        }
    }

    // --- SMTP TAB SAVE & TEST ---
    public function saveSmtp(): void
    {
        $data = $this->validate([
            'smtpHost' => ['nullable', 'string', 'max:255'],
            'smtpPort' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtpUsername' => ['nullable', 'string', 'max:255'],
            'smtpEncryption' => ['nullable', 'in:tls,ssl,'],
            'smtpFromAddress' => ['nullable', 'email', 'max:255'],
            'smtpFromName' => ['nullable', 'string', 'max:255'],
        ]);

        $branding = PlatformBranding::current();
        $update = [
            'smtp_host' => $data['smtpHost'] ?: null,
            'smtp_port' => $data['smtpPort'] ?: null,
            'smtp_username' => $data['smtpUsername'] ?: null,
            'smtp_encryption' => $data['smtpEncryption'] ?: null,
            'smtp_from_address' => $data['smtpFromAddress'] ?: null,
            'smtp_from_name' => $data['smtpFromName'] ?: null,
        ];
        if (filled($this->smtpPassword)) {
            $update['smtp_password'] = $this->smtpPassword;
        }
        $branding->update($update);

        $this->hasStoredPassword = filled($branding->fresh()->smtp_password);
        $this->smtpPassword = '';

        AuditLog::record('smtp.updated', null, auth('platform_web')->id());

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'SMTP server configuration saved successfully.',
        ]);
        session()->flash('status', 'SMTP configuration saved.');
    }

    public function sendSmtpTest(): void
    {
        $this->validate(['testEmailTo' => ['required', 'email']]);

        $branding = PlatformBranding::current();
        if (! $branding->smtp_host) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'Save SMTP server settings before sending a test email.',
            ]);
            session()->flash('error', 'Save SMTP settings before sending a test email.');

            return;
        }

        config([
            'mail.mailers.smtp.host' => $branding->smtp_host,
            'mail.mailers.smtp.port' => $branding->smtp_port,
            'mail.mailers.smtp.username' => $branding->smtp_username,
            'mail.mailers.smtp.password' => $branding->smtp_password,
            'mail.mailers.smtp.encryption' => $branding->smtp_encryption,
            'mail.from.address' => $branding->smtp_from_address ?: ($branding->support_email ?: config('mail.from.address')),
            'mail.from.name' => $branding->smtp_from_name ?: ($branding->platform_name ?: config('mail.from.name')),
        ]);

        try {
            Mail::mailer('smtp')->raw(
                'This is a test verification email from '.$branding->platform_name.' — Your SMTP settings are properly configured and operational.',
                fn ($message) => $message->to($this->testEmailTo)->subject('SMTP Verification Test Email')
            );

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Test verification email dispatched to {$this->testEmailTo}.",
            ]);
            session()->flash('status', "Test email sent to {$this->testEmailTo}.");
        } catch (\Throwable $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to send test email: '.$e->getMessage(),
            ]);
            session()->flash('error', 'Failed to send: '.$e->getMessage());
        }
    }

    // --- BRANDING TAB SAVE ---
    public function addFaq(): void
    {
        if (count($this->landingFaqs) < 20) {
            $this->landingFaqs[] = ['q' => '', 'a' => ''];
        }
    }

    public function removeFaq(int $index): void
    {
        unset($this->landingFaqs[$index]);
        $this->landingFaqs = array_values($this->landingFaqs);
    }

    public function addFeature(): void
    {
        if (count($this->landingFeatures) < 30) {
            $this->landingFeatures[] = ['icon' => '✨', 'title' => '', 'body' => ''];
        }
    }

    public function removeFeature(int $index): void
    {
        unset($this->landingFeatures[$index]);
        $this->landingFeatures = array_values($this->landingFeatures);
    }

    public function addTestimonial(): void
    {
        if (count($this->landingTestimonials) < 20) {
            $this->landingTestimonials[] = ['quote' => '', 'name' => '', 'role' => ''];
        }
    }

    public function removeTestimonial(int $index): void
    {
        unset($this->landingTestimonials[$index]);
        $this->landingTestimonials = array_values($this->landingTestimonials);
    }

    public function addHardware(): void { $this->addHardwareItem(); }
    public function removeHardware(int $index): void { $this->removeHardwareItem($index); }
    public function addHardwareItem(): void
    {
        if (count($this->landingHardwareItems) < 20) {
            $this->landingHardwareItems[] = ['label' => '', 'tag' => '', 'icon' => 'barcode'];
        }
    }
    public function removeHardwareItem(int $index): void
    {
        unset($this->landingHardwareItems[$index]);
        $this->landingHardwareItems = array_values($this->landingHardwareItems);
    }

    public function addSolution(): void
    {
        if (count($this->landingSolutions) < 20) {
            $this->landingSolutions[] = ['icon' => '⚡', 'title' => '', 'body' => ''];
        }
    }
    public function removeSolution(int $index): void
    {
        unset($this->landingSolutions[$index]);
        $this->landingSolutions = array_values($this->landingSolutions);
    }

    public function addStat(): void
    {
        if (count($this->landingStats) < 15) {
            $this->landingStats[] = ['value' => '', 'label' => ''];
        }
    }
    public function removeStat(int $index): void
    {
        unset($this->landingStats[$index]);
        $this->landingStats = array_values($this->landingStats);
    }

    public function addHeroHighlight(): void
    {
        if (count($this->landingHeroHighlights) < 20) {
            $this->landingHeroHighlights[] = '';
        }
    }
    public function removeHeroHighlight(int $index): void
    {
        unset($this->landingHeroHighlights[$index]);
        $this->landingHeroHighlights = array_values($this->landingHeroHighlights);
    }
    public function addHighlight(): void { $this->addHeroHighlight(); }
    public function removeHighlight(int $index): void { $this->removeHeroHighlight($index); }

    public function addHeroProduct(): void
    {
        if (count($this->landingHeroProducts) < 15) {
            $this->landingHeroProducts[] = ['name' => '', 'price' => '', 'status' => 'In Stock', 'tone' => 'emerald'];
        }
    }
    public function removeHeroProduct(int $index): void
    {
        unset($this->landingHeroProducts[$index]);
        $this->landingHeroProducts = array_values($this->landingHeroProducts);
    }
    public function addProduct(): void { $this->addHeroProduct(); }
    public function removeProduct(int $index): void { $this->removeHeroProduct($index); }

    public function addLandingItem(string $type): void
    {
        if ($type === 'highlight') $this->addHeroHighlight();
        elseif ($type === 'product') $this->addHeroProduct();
        elseif ($type === 'hardware') $this->addHardwareItem();
        elseif ($type === 'stat') $this->addStat();
        elseif ($type === 'solution') $this->addSolution();
        elseif ($type === 'feature') $this->addFeature();
        elseif ($type === 'testimonial') $this->addTestimonial();
        elseif ($type === 'faq') $this->addFaq();
    }

    public function removeLandingItem(string $type, int $index): void
    {
        if ($type === 'highlight') $this->removeHeroHighlight($index);
        elseif ($type === 'product') $this->removeHeroProduct($index);
        elseif ($type === 'hardware') $this->removeHardwareItem($index);
        elseif ($type === 'stat') $this->removeStat($index);
        elseif ($type === 'solution') $this->removeSolution($index);
        elseif ($type === 'feature') $this->removeFeature($index);
        elseif ($type === 'testimonial') $this->removeTestimonial($index);
        elseif ($type === 'faq') $this->removeFaq($index);
    }

    public function removeAuthBanner(): void
    {
        $this->authBannerImage = null;
        $this->authBannerImageUrl = '';
        \App\Models\DynamicSetting::put('auth_banner_image_url', '');
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Auth banner image removed.',
        ]);
    }

    public function updatedLandingPageId($value): void
    {
        if ($value) {
            $this->homepageMode = 'static_page';
        }
    }

    public function setHomepageMode(string $mode): void
    {
        if (in_array($mode, ['modular', 'static_page'], true)) {
            $this->homepageMode = $mode;
        }
    }

    public function setMenuLocation(string $loc): void
    {
        if (in_array($loc, ['header', 'footer_col_1', 'footer_col_2'], true)) {
            $this->menuLocation = $loc;
            $this->cancelEditMenuItem();
        }
    }

    public function addAnchorMenuLink(string $title, string $anchor): void
    {
        $anchor = trim($anchor);
        if (! str_starts_with($anchor, '#')) {
            $anchor = '#' . ltrim($anchor, '/#');
        }

        $maxOrder = (int) MenuItem::where('location', $this->menuLocation)->max('order_index');

        MenuItem::create([
            'location' => $this->menuLocation,
            'title' => trim($title),
            'type' => 'anchor',
            'url' => $anchor,
            'page_id' => null,
            'target' => '_self',
            'order_index' => $maxOrder + 1,
            'is_active' => true,
        ]);

        MenuItem::clearMenuCache($this->menuLocation);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Added section anchor link to menu.'),
        ]);
    }

    public function addPageMenuLink(?int $pageId = null): void
    {
        $pId = $pageId ?: $this->newMenuPageId;
        if (! $pId) {
            return;
        }

        $page = Page::find($pId);
        if (! $page) {
            return;
        }

        $maxOrder = (int) MenuItem::where('location', $this->menuLocation)->max('order_index');

        MenuItem::create([
            'location' => $this->menuLocation,
            'title' => $page->title,
            'type' => 'page',
            'url' => '/page/' . $page->slug,
            'page_id' => $page->id,
            'target' => '_self',
            'order_index' => $maxOrder + 1,
            'is_active' => true,
        ]);

        $this->newMenuPageId = null;
        MenuItem::clearMenuCache($this->menuLocation);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Added CMS page link to menu.'),
        ]);
    }

    public function addCustomMenuLink(): void
    {
        $this->validate([
            'newMenuTitle' => 'required|string|max:100',
            'newMenuUrl' => 'required|string|max:255',
        ]);

        $maxOrder = (int) MenuItem::where('location', $this->menuLocation)->max('order_index');

        MenuItem::create([
            'location' => $this->menuLocation,
            'title' => trim($this->newMenuTitle),
            'type' => 'custom',
            'url' => trim($this->newMenuUrl),
            'page_id' => null,
            'target' => $this->newMenuTargetBlank ? '_blank' : '_self',
            'order_index' => $maxOrder + 1,
            'is_active' => true,
        ]);

        $this->newMenuTitle = '';
        $this->newMenuUrl = '';
        $this->newMenuTargetBlank = false;
        MenuItem::clearMenuCache($this->menuLocation);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Added custom menu item.'),
        ]);
    }

    public function moveMenuItemUp(int $id): void
    {
        $items = MenuItem::where('location', $this->menuLocation)->orderBy('order_index')->get();
        $index = $items->search(fn ($i) => $i->id === $id);
        if ($index === false || $index <= 0) {
            return;
        }

        $current = $items[$index];
        $prev = $items[$index - 1];

        $prevOrder = $prev->order_index;
        $currOrder = $current->order_index;

        if ($prevOrder >= $currOrder) {
            $prevOrder = $index - 1;
            $currOrder = $index;
        }

        $current->update(['order_index' => $prevOrder]);
        $prev->update(['order_index' => $currOrder]);

        MenuItem::clearMenuCache($this->menuLocation);
    }

    public function moveMenuItemDown(int $id): void
    {
        $items = MenuItem::where('location', $this->menuLocation)->orderBy('order_index')->get();
        $index = $items->search(fn ($i) => $i->id === $id);
        if ($index === false || $index >= count($items) - 1) {
            return;
        }

        $current = $items[$index];
        $next = $items[$index + 1];

        $nextOrder = $next->order_index;
        $currOrder = $current->order_index;

        if ($nextOrder <= $currOrder) {
            $nextOrder = $index + 1;
            $currOrder = $index;
        }

        $current->update(['order_index' => $nextOrder]);
        $next->update(['order_index' => $currOrder]);

        MenuItem::clearMenuCache($this->menuLocation);
    }

    public function toggleMenuItemActive(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        MenuItem::clearMenuCache($this->menuLocation);
    }

    public function deleteMenuItem(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        $location = $item->location;
        $item->delete();
        MenuItem::clearMenuCache($location);
        if ($this->editingMenuItemId === $id) {
            $this->cancelEditMenuItem();
        }
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Menu item deleted.'),
        ]);
    }

    public function editMenuItem(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        $this->editingMenuItemId = $item->id;
        $this->editingMenuItemTitle = $item->title;
        $this->editingMenuItemUrl = $item->url;
        $this->editingMenuItemTarget = $item->target ?: '_self';
        $this->editingMenuItemActive = (bool) $item->is_active;
    }

    public function saveMenuItem(): void
    {
        if (! $this->editingMenuItemId) {
            return;
        }

        $this->validate([
            'editingMenuItemTitle' => 'required|string|max:100',
            'editingMenuItemUrl' => 'required|string|max:255',
        ]);

        $item = MenuItem::findOrFail($this->editingMenuItemId);
        $item->update([
            'title' => trim($this->editingMenuItemTitle),
            'url' => trim($this->editingMenuItemUrl),
            'target' => $this->editingMenuItemTarget,
            'is_active' => $this->editingMenuItemActive,
        ]);

        $this->cancelEditMenuItem();
        MenuItem::clearMenuCache($item->location);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Menu item updated.'),
        ]);
    }

    public function cancelEditMenuItem(): void
    {
        $this->editingMenuItemId = null;
        $this->editingMenuItemTitle = '';
        $this->editingMenuItemUrl = '';
        $this->editingMenuItemTarget = '_self';
        $this->editingMenuItemActive = true;
    }

    public function saveBranding(): void
    {
        $data = $this->validate([
            'platformName' => ['required', 'string', 'max:255'],
            'logoUrl' => ['nullable', 'string', 'max:500'],
            'logoImage' => ['nullable', 'image', 'max:5120'],
            'faviconUrl' => ['nullable', 'string', 'max:500'],
            'showAuthBanner' => ['boolean'],
            'authBannerImageUrl' => ['nullable', 'string', 'max:500'],
            'authBannerImage' => ['nullable', 'image', 'max:5120'],
            'enableRegistrationDomainSetup' => ['boolean'],
            'primaryColor' => ['nullable', 'string', 'max:32'],
            'superadminSidebarColor' => ['nullable', 'string', 'max:32'],
            'landingPrimaryColor' => ['nullable', 'string', 'max:32'],
            'landingAccentColor' => ['nullable', 'string', 'max:32'],
            'landingTheme' => ['nullable', 'string', 'in:theme_fast,theme_modern,theme_enterprise,theme_minimal,theme_dark_studio'],
            'supportEmail' => ['nullable', 'email'],
            'supportPhone' => ['nullable', 'string', 'max:50'],
            'landingPageId' => ['nullable', 'exists:pages,id'],
            'landingHeroBadge' => ['nullable', 'string', 'max:255'],
            'landingHeroTitle' => ['nullable', 'string', 'max:255'],
            'landingHeroSubtitle' => ['nullable', 'string', 'max:1000'],
            'landingHeroCtaPrimaryText' => ['nullable', 'string', 'max:100'],
            'landingHeroCtaPrimaryUrl' => ['nullable', 'string', 'max:500'],
            'landingHeroCtaSecondaryText' => ['nullable', 'string', 'max:100'],
            'landingHeroCtaSecondaryUrl' => ['nullable', 'string', 'max:500'],
            'landingHeroBannerImageUrl' => ['nullable', 'string', 'max:500'],
            'landingSectionOrder' => ['nullable', 'string', 'max:500'],
            'landingPlaystoreUrl' => ['nullable', 'url', 'max:500'],
            'landingWindowsUrl' => ['nullable', 'url', 'max:500'],
            'sectionMeta.*.badge' => ['nullable', 'string', 'max:120'],
            'sectionMeta.*.title' => ['nullable', 'string', 'max:255'],
            'sectionMeta.*.subtitle' => ['nullable', 'string', 'max:500'],
            'sectionMeta.*.body' => ['nullable', 'string', 'max:2000'],
            'sectionMeta.*.background' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sectionMeta.*.accent' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'landingFaqs' => ['array', 'max:30'],
            'landingFaqs.*.q' => ['nullable', 'string', 'max:255'],
            'landingFaqs.*.a' => ['nullable', 'string', 'max:2000'],
            'landingFeatures' => ['nullable', 'array', 'max:30'],
            'landingFeatures.*.icon' => ['nullable', 'string', 'max:16'],
            'landingFeatures.*.title' => ['nullable', 'string', 'max:120'],
            'landingFeatures.*.body' => ['nullable', 'string', 'max:500'],
            'landingTestimonials' => ['nullable', 'array', 'max:20'],
            'landingTestimonials.*.quote' => ['nullable', 'string', 'max:500'],
            'landingTestimonials.*.name' => ['nullable', 'string', 'max:120'],
            'landingTestimonials.*.role' => ['nullable', 'string', 'max:160'],
            'landingFeaturesJson' => ['nullable', 'string', 'max:50000'],
            'landingTestimonialsJson' => ['nullable', 'string', 'max:50000'],
            'landingContentJson' => ['nullable', 'string', 'max:100000'],
            'landingCustomHtml' => ['nullable', 'string', 'max:500000'],
            'landingHeroHighlights' => ['array', 'max:20'],
            'landingHeroHighlights.*' => ['nullable', 'string', 'max:120'],
            'landingHeroProducts' => ['array', 'max:20'],
            'landingHardwareItems' => ['array', 'max:20'],
            'landingStats' => ['array', 'max:15'],
            'landingSolutions' => ['array', 'max:20'],
        ]);

        foreach (['landingFeaturesJson' => 'landingFeatures', 'landingTestimonialsJson' => 'landingTestimonials'] as $jsonKey => $arrayKey) {
            if (filled($this->{$jsonKey})) {
                $decoded = json_decode($this->{$jsonKey}, true);
                if (! is_array($decoded)) {
                    $this->addError($jsonKey, 'Enter valid JSON array data.');
                    return;
                }
                $this->{$arrayKey} = $decoded;
            }
        }
        $landingContent = [];
        if (filled($this->landingContentJson)) {
            $landingContent = json_decode($this->landingContentJson, true);
            if (! is_array($landingContent)) {
                $this->addError('landingContentJson', 'Enter a valid JSON object of copy overrides.');
                return;
            }
        }
        if (filled($this->landingCustomHtml)) {
            $landingContent['html'] = $this->landingCustomHtml;
        }
        if ($this->landingHeroHighlights !== []) $landingContent['hero']['highlights'] = array_values(array_filter(array_map('trim', $this->landingHeroHighlights)));
        if ($this->landingHeroProducts !== []) $landingContent['hero']['products'] = array_values($this->landingHeroProducts);
        if ($this->landingHardwareItems !== []) {
            $landingContent['trust']['hardware'] = collect($this->landingHardwareItems)
                ->map(fn ($h) => [
                    'label' => trim((string) ($h['label'] ?? ($h[0] ?? ''))),
                    'tag' => trim((string) ($h['tag'] ?? ($h[1] ?? ''))),
                    'icon' => trim((string) ($h['icon'] ?? ($h[2] ?? 'barcode'))),
                ])
                ->filter(fn ($h) => $h['label'] !== '')
                ->values()
                ->all();
        }
        if ($this->landingStats !== []) {
            $landingContent['stats'] = collect($this->landingStats)
                ->map(fn ($st) => [
                    'value' => trim((string) ($st['value'] ?? ($st[0] ?? ''))),
                    'label' => trim((string) ($st['label'] ?? ($st[1] ?? ''))),
                ])
                ->filter(fn ($st) => $st['value'] !== '' || $st['label'] !== '')
                ->values()
                ->all();
        }
        if ($this->landingSolutions !== []) {
            $landingContent['solutions']['items'] = collect($this->landingSolutions)
                ->map(fn ($s) => [
                    'icon' => trim((string) ($s['icon'] ?? ($s[0] ?? '⚡'))) ?: '⚡',
                    'title' => trim((string) ($s['title'] ?? ($s[1] ?? ''))),
                    'body' => trim((string) ($s['body'] ?? ($s[2] ?? ''))),
                ])
                ->filter(fn ($s) => $s['title'] !== '')
                ->values()
                ->all();
        }

        if ($this->homepageMode === 'static_page' && empty($this->landingPageId)) {
            $this->homepageMode = 'modular';
        }
        $landingContent['homepage_mode'] = $this->homepageMode;

        if ($this->logoImage) {
            $logoPath = $this->logoImage->store('branding', 'public');
            $data['logoUrl'] = \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath);
            $this->logoUrl = $data['logoUrl'];
            $this->logoImage = null;
        }

        if ($this->authBannerImage) {
            $bannerPath = $this->authBannerImage->store('branding', 'public');
            $this->authBannerImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($bannerPath);
            $this->authBannerImage = null;
        }

        \App\Models\DynamicSetting::put('show_auth_banner', $this->showAuthBanner);
        \App\Models\DynamicSetting::put('auth_banner_image_url', $this->authBannerImageUrl);
        \App\Models\DynamicSetting::put('enable_registration_domain_setup', $this->enableRegistrationDomainSetup);
        if (! empty($data['logoUrl'])) {
            \App\Models\DynamicSetting::put('platform_logo_url', $data['logoUrl']);
        }
        if (! empty($data['platformName'])) {
            \App\Models\DynamicSetting::put('platform_brand_name', $data['platformName']);
        }

        $sectionsConfig = [
            'hero' => $this->sectionHero,
            'trust_bar' => $this->sectionTrustBar,
            'features' => $this->sectionFeatures,
            'solutions' => $this->sectionSolutions,
            'downloads' => $this->sectionDownloads,
            'stats' => $this->sectionStats,
            'about' => $this->sectionAbout,
            'testimonials' => $this->sectionTestimonials,
            'pricing' => $this->sectionPricing,
            'faq' => $this->sectionFaq,
            'contact' => $this->sectionContact,
            'cta' => $this->sectionCta,
        ];

        $sectionMeta = [];
        foreach ($this->sectionMeta as $key => $meta) {
            $badge = trim((string) ($meta['badge'] ?? ''));
            $title = trim((string) ($meta['title'] ?? ''));
            $subtitle = trim((string) ($meta['subtitle'] ?? ''));
            $body = trim((string) ($meta['body'] ?? ''));
            $background = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($meta['background'] ?? '')) ? strtoupper($meta['background']) : null;
            $accent = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($meta['accent'] ?? '')) ? strtoupper($meta['accent']) : null;
            if ($badge !== '' || $title !== '' || $subtitle !== '' || $body !== '' || $background || $accent) {
                $sectionMeta[$key] = compact('badge', 'title', 'subtitle', 'body', 'background', 'accent');
            }
        }

        $allowedOrder = ['hero', 'trust_bar', 'features', 'solutions', 'downloads', 'stats', 'about', 'testimonials', 'pricing', 'faq', 'contact', 'cta'];
        $order = array_values(array_unique(array_filter(array_map('trim', explode(',', $this->landingSectionOrder)), fn ($key) => in_array($key, $allowedOrder, true))));
        $sectionsConfig['order'] = array_values(array_unique(array_merge($order, array_diff($allowedOrder, $order))));

        $faqs = collect($this->landingFaqs)
            ->map(fn ($row) => ['q' => trim((string) ($row['q'] ?? '')), 'a' => trim((string) ($row['a'] ?? ''))])
            ->filter(fn ($row) => $row['q'] !== '' && $row['a'] !== '')
            ->values()
            ->all();

        $features = collect($this->landingFeatures)
            ->map(fn ($row) => [
                'icon' => trim((string) ($row['icon'] ?? '')),
                'title' => trim((string) ($row['title'] ?? '')),
                'body' => trim((string) ($row['body'] ?? '')),
            ])
            ->filter(fn ($row) => $row['title'] !== '' && $row['body'] !== '')
            ->values()
            ->all();

        $testimonials = collect($this->landingTestimonials)
            ->map(fn ($row) => [
                'quote' => trim((string) ($row['quote'] ?? '')),
                'name' => trim((string) ($row['name'] ?? '')),
                'role' => trim((string) ($row['role'] ?? '')),
            ])
            ->filter(fn ($row) => $row['quote'] !== '' && $row['name'] !== '')
            ->values()
            ->all();

        PlatformBranding::current()->update([
            'platform_name' => $data['platformName'],
            'logo_url' => $data['logoUrl'] ?: null,
            'favicon_url' => $data['faviconUrl'] ?: null,
            'primary_color' => $data['primaryColor'] ?: '#4f46e5',
            'superadmin_sidebar_color' => $data['superadminSidebarColor'] ?: '#4338ca',
            'landing_primary_color' => $data['landingPrimaryColor'] ?: '#10b981',
            'landing_accent_color' => $data['landingAccentColor'] ?: '#d7f24e',
            'support_email' => $data['supportEmail'] ?: null,
            'support_phone' => $data['supportPhone'] ?: null,
            'otp_registration_enabled' => $this->otpRegistrationEnabled,
            'landing_page_enabled' => $this->landingPageEnabled,
            'landing_page_id' => $data['landingPageId'] ?: null,
            'landing_hero_badge' => $data['landingHeroBadge'] ?: null,
            'landing_hero_title' => $data['landingHeroTitle'] ?: null,
            'landing_hero_subtitle' => $data['landingHeroSubtitle'] ?: null,
            'landing_hero_cta_primary_text' => $data['landingHeroCtaPrimaryText'] ?: null,
            'landing_hero_cta_primary_url' => $data['landingHeroCtaPrimaryUrl'] ?: null,
            'landing_hero_cta_secondary_text' => $data['landingHeroCtaSecondaryText'] ?: null,
            'landing_hero_cta_secondary_url' => $data['landingHeroCtaSecondaryUrl'] ?: null,
            'landing_hero_banner_image_url' => $data['landingHeroBannerImageUrl'] ?: null,
            'landing_sections_config' => $sectionsConfig,
            'landing_playstore_url' => $data['landingPlaystoreUrl'] ?: null,
            'landing_playstore_enabled' => $this->landingPlaystoreEnabled,
            'landing_windows_url' => $data['landingWindowsUrl'] ?: null,
            'landing_windows_enabled' => $this->landingWindowsEnabled,
            'landing_section_meta' => $sectionMeta ?: null,
            'landing_faqs' => $faqs ?: null,
            'landing_features' => $features ?: null,
            'landing_testimonials' => $testimonials ?: null,
            'landing_content' => $landingContent ?: null,
        ]);

        $allowedThemes = ['theme_fast', 'theme_modern', 'theme_enterprise', 'theme_minimal', 'theme_dark_studio'];
        if (in_array($this->landingTheme, $allowedThemes, true)) {
            set_setting('landing_page_theme', $this->landingTheme);
        }

        MenuItem::clearMenuCache();
        \Illuminate\Support\Facades\Cache::forget('public_settings');
        \Illuminate\Support\Facades\Cache::forget('platform_branding_settings');
        \Illuminate\Support\Facades\Cache::forget('app_landing_page_theme');
        \Illuminate\Support\Facades\Cache::forget('landing_page_theme_config');
        \Illuminate\Support\Facades\Cache::forget('landing_sections_theme_palette');
        if (\Illuminate\Support\Facades\Cache::has('landing_page_cache_version')) {
            \Illuminate\Support\Facades\Cache::increment('landing_page_cache_version');
        } else {
            \Illuminate\Support\Facades\Cache::forever('landing_page_cache_version', 2);
        }

        AuditLog::record('branding.updated', null, auth('platform_web')->id());

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'White-label & Branding settings saved successfully.',
        ]);
        session()->flash('status', 'White-label Branding settings saved.');
    }

    // --- CMS / PAGES ACTIONS ---
    public function updatingPageSearch(): void
    {
        $this->resetPage();
    }

    public function deletePage(int $id): void
    {
        $page = Page::findOrFail($id);

        $branding = PlatformBranding::current();
        if ($branding->landing_page_id === $page->id) {
            $branding->update(['landing_page_id' => null, 'landing_page_enabled' => false]);
        }

        $title = $page->title;
        $page->delete();

        AuditLog::record('page.deleted', null, auth('platform_web')->id(), ['title' => $title]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Custom Page \"{$title}\" deleted successfully.",
        ]);
        session()->flash('status', "Page \"{$title}\" deleted.");
    }

    public function render()
    {
        $pages = Page::query()
            ->when($this->pageSearch, function ($q) {
                $term = '%'.$this->pageSearch.'%';
                $q->where(fn ($sq) => $sq->where('title', 'like', $term)->orWhere('slug', 'like', $term));
            })
            ->orderByDesc('updated_at')
            ->paginate(10);

        return view('livewire.superadmin.settings.index', [
            'pages' => $pages,
            'availablePages' => Page::orderBy('title')->get(),
            'landingPageId' => PlatformBranding::current()->landing_page_id,
            'currencyOptions' => PlatformRegionalService::currencyOptions(),
            'languageOptions' => PlatformRegionalService::languageOptions(),
            'timezoneOptions' => PlatformRegionalService::timezoneOptions(),
            'moduleGuard' => $this->moduleGovernance(),
            'currentMenuItems' => MenuItem::where('location', $this->menuLocation)->orderBy('order_index')->get(),
            'selectedLandingPage' => $this->landingPageId ? Page::find($this->landingPageId) : null,
        ]);
    }

    /**
     * Per registration-mode gating for the Module Governance card.
     *
     * @return array<string, array{premium: bool, licensed: bool, catalog_slug: string, store_link: ?string}>
     */
    public function moduleGovernance(): array
    {
        $free = (array) config('modules.registration.free', ['retail', 'restaurant']);
        $premium = (array) config('modules.registration.premium', []);

        $licensedSlugs = SduiModule::query()
            ->where('license_status', 'active')
            ->pluck('slug')
            ->all();

        $guard = [];
        foreach (array_keys(ModuleRegistry::operatingModules()) as $key) {
            $isPremium = array_key_exists($key, $premium);
            $catalogSlug = $isPremium ? (string) $premium[$key] : $key;

            $guard[$key] = [
                'premium' => $isPremium,
                'licensed' => ! $isPremium
                    || in_array($key, $free, true)
                    || in_array($catalogSlug, $licensedSlugs, true),
                'catalog_slug' => $catalogSlug,
                'store_link' => $isPremium ? ModuleCatalog::storeLink($catalogSlug) : null,
            ];
        }

        return $guard;
    }
}
