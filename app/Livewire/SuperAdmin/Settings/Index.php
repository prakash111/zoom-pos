<?php

namespace App\Livewire\SuperAdmin\Settings;

use App\Models\AuditLog;
use App\Models\Page;
use App\Models\PlatformBranding;
use App\Models\PlatformSystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.superadmin', ['title' => 'System & Platform Settings'])]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $activeTab = 'general';

    public string $landingTheme = 'theme_modern';

    public array $social = [
        'google' => ['enabled' => false, 'client_id' => '', 'client_secret' => ''],
        'facebook' => ['enabled' => false, 'client_id' => '', 'client_secret' => ''],
    ];

    // --- TAB 1: GENERAL & SYSTEM SETTINGS ---
    public string $appName = '';

    public string $appCurrency = 'USD';

    public string $appTimezone = 'UTC';

    public bool $maintenanceMode = false;

    public string $maintenanceMessage = '';

    public string $minClientBuildVersion = '0';

    public string $appVersion = '1.0.0';

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

    // --- TAB 3: WHITE-LABEL & BRANDING ---
    public string $platformName = '';

    public string $logoUrl = '';

    public string $faviconUrl = '';

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

    // --- TAB 4: CUSTOM PAGES (CMS) ---
    public string $pageSearch = '';

    public function mount(): void
    {
        abort_unless(auth('platform_web')->user()?->hasRole('super_admin'), 403);

        $allowedTabs = ['general', 'smtp', 'branding', 'whitelabel', 'social', 'pages', 'appearance'];
        if (! in_array($this->activeTab, $allowedTabs, true)) {
            $this->activeTab = 'general';
        }

        // Load Platform System Settings
        $this->appName = (string) PlatformSystem::get('app_name', config('app.name', 'Smart Inventory & Sales'));
        $this->appCurrency = (string) PlatformSystem::get('app_currency', 'USD');
        $this->appTimezone = (string) PlatformSystem::get('app_timezone', config('app.timezone', 'UTC'));
        $this->maintenanceMode = filter_var(PlatformSystem::get('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN);
        $this->maintenanceMessage = (string) PlatformSystem::get('maintenance_message', '');
        $this->minClientBuildVersion = (string) PlatformSystem::get('min_client_build_version', '0');
        $this->appVersion = (string) PlatformSystem::get('app_version', '1.0.0');

        // Load Platform Branding & SMTP
        $branding = PlatformBranding::current();
        $this->platformName = (string) ($branding->platform_name ?: 'Smart Inventory & Sales');
        $this->logoUrl = (string) $branding->logo_url;
        $this->faviconUrl = (string) $branding->favicon_url;
        $this->primaryColor = $branding->primary_color ?? '#4f46e5';
        $this->superadminSidebarColor = $branding->superadmin_sidebar_color ?? '#4338ca';
        $this->landingPrimaryColor = $branding->landing_primary_color ?? '#10b981';
        $this->landingAccentColor = $branding->landing_accent_color ?? '#d7f24e';
        $this->supportEmail = (string) $branding->support_email;
        $this->supportPhone = (string) $branding->support_phone;
        $this->otpRegistrationEnabled = (bool) $branding->otp_registration_enabled;
        $this->landingPageEnabled = (bool) $branding->landing_page_enabled;
        $this->landingPageId = $branding->landing_page_id;

        $this->landingHeroBadge = (string) ($branding->landing_hero_badge ?? '');
        $this->landingHeroTitle = (string) ($branding->landing_hero_title ?? '');
        $this->landingHeroSubtitle = (string) ($branding->landing_hero_subtitle ?? '');
        $this->landingHeroCtaPrimaryText = (string) ($branding->landing_hero_cta_primary_text ?? '');
        $this->landingHeroCtaPrimaryUrl = (string) ($branding->landing_hero_cta_primary_url ?? '');
        $this->landingHeroCtaSecondaryText = (string) ($branding->landing_hero_cta_secondary_text ?? '');
        $this->landingHeroCtaSecondaryUrl = (string) ($branding->landing_hero_cta_secondary_url ?? '');
        $this->landingHeroBannerImageUrl = (string) ($branding->landing_hero_banner_image_url ?? '');

        $cfg = $branding->landing_sections_config ?? [];
        $this->sectionTrustBar = (bool) ($cfg['trust_bar'] ?? true);
        $this->sectionFeatures = (bool) ($cfg['features'] ?? true);
        $this->sectionSolutions = (bool) ($cfg['solutions'] ?? true);
        $this->sectionStats = (bool) ($cfg['stats'] ?? true);
        $this->sectionAbout = (bool) ($cfg['about'] ?? true);
        $this->sectionTestimonials = (bool) ($cfg['testimonials'] ?? true);
        $this->sectionPricing = (bool) ($cfg['pricing'] ?? true);
        $this->sectionContact = (bool) ($cfg['contact'] ?? true);
        $this->sectionCta = (bool) ($cfg['cta'] ?? true);

        $this->landingTheme = (string) setting('landing_page_theme', 'theme_modern');

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
    }

    public function setTab(string $tab): void
    {
        $allowedTabs = ['general', 'smtp', 'branding', 'whitelabel', 'social', 'pages', 'appearance'];
        if (in_array($tab, $allowedTabs, true)) {
            $this->activeTab = $tab;
        }
    }

    public function setLandingTheme(string $themeKey): void
    {
        $allowedThemes = ['theme_modern', 'theme_enterprise', 'theme_minimal', 'theme_dark_studio'];
        if (in_array($themeKey, $allowedThemes, true)) {
            $this->landingTheme = $themeKey;
            set_setting('landing_page_theme', $themeKey);
            cache()->forget('app_landing_page_theme');
            $this->dispatch('toast', ['message' => 'Landing page layout updated successfully!', 'type' => 'success']);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Landing page layout updated successfully!']);
        }
    }

    public function saveAppearance(array $appearance): void
    {
        $allowedLayouts = ['slim', 'expanded', 'macos-dock', 'speed-dial'];
        $allowedPositions = ['left', 'right', 'top', 'bottom', 'floating'];
        $allowedModes = ['docked', 'floating'];
        $allowedItems = ['dashboard', 'tenants', 'plans', 'codes', 'taxes', 'menus', 'pages', 'settings', 'smtp'];

        $layout = in_array($appearance['layout'] ?? null, $allowedLayouts, true) ? $appearance['layout'] : 'slim';
        $position = in_array($appearance['position'] ?? null, $allowedPositions, true) ? $appearance['position'] : 'left';
        $mode = in_array($appearance['mode'] ?? null, $allowedModes, true) ? $appearance['mode'] : 'docked';
        $visibleItems = array_values(array_intersect($allowedItems, (array) ($appearance['visibleItems'] ?? [])));

        $values = [
            'appearance_nav_layout' => $layout,
            'appearance_nav_position' => $position,
            'appearance_nav_mode' => $mode,
            'appearance_nav_custom_bg' => mb_substr((string) ($appearance['customBg'] ?? ''), 0, 255),
            'appearance_ui_accent_color' => $this->validatedColor($appearance['uiAccentColor'] ?? null, '#4f46e5'),
            'appearance_nav_text_color' => $this->validatedColor($appearance['navTextColor'] ?? null, '#ffffff'),
            'appearance_nav_text_active_color' => $this->validatedColor($appearance['navTextActiveColor'] ?? null, '#60a5fa'),
            'appearance_nav_visible_items' => json_encode($visibleItems ?: ['dashboard', 'tenants', 'plans', 'settings', 'smtp']),
        ];

        foreach ($values as $key => $value) {
            set_setting($key, $value);
        }
        set_setting('appearance_defaults_version', (string) now()->getTimestampMs());

        AuditLog::record('appearance.defaults_updated', null, auth('platform_web')->id(), ['after' => $values]);
        $this->dispatch('appearance-defaults-saved', defaults: appearance_defaults());
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Global appearance defaults saved successfully.']);
        session()->flash('status', 'Global appearance defaults saved successfully.');
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
        $value = (string) $value;

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $default;
    }

    // --- GENERAL TAB SAVE ---
    public function saveGeneral(): void
    {
        $this->validate([
            'appName' => ['required', 'string', 'max:255'],
            'appCurrency' => ['required', 'string', 'max:10'],
            'appTimezone' => ['required', 'string', 'max:100'],
            'maintenanceMessage' => ['nullable', 'string', 'max:500'],
            'minClientBuildVersion' => ['required', 'string', 'max:50'],
            'appVersion' => ['required', 'string', 'max:50'],
        ]);

        $before = [
            'maintenance_mode' => PlatformSystem::get('maintenance_mode', '0'),
            'min_client_build_version' => PlatformSystem::get('min_client_build_version', '0'),
        ];

        PlatformSystem::set('app_name', $this->appName);
        PlatformSystem::set('app_currency', $this->appCurrency);
        PlatformSystem::set('app_timezone', $this->appTimezone);
        PlatformSystem::set('maintenance_mode', $this->maintenanceMode ? '1' : '0');
        PlatformSystem::set('maintenance_message', $this->maintenanceMessage);
        PlatformSystem::set('min_client_build_version', $this->minClientBuildVersion);
        PlatformSystem::set('app_version', $this->appVersion);

        AuditLog::record('system.settings_updated', null, auth('platform_web')->id(), [
            'before' => $before,
            'after' => [
                'maintenance_mode' => $this->maintenanceMode ? '1' : '0',
                'min_client_build_version' => $this->minClientBuildVersion,
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
    public function saveBranding(): void
    {
        $data = $this->validate([
            'platformName' => ['required', 'string', 'max:255'],
            'logoUrl' => ['nullable', 'string', 'max:500'],
            'faviconUrl' => ['nullable', 'string', 'max:500'],
            'primaryColor' => ['nullable', 'string', 'max:32'],
            'superadminSidebarColor' => ['nullable', 'string', 'max:32'],
            'landingPrimaryColor' => ['nullable', 'string', 'max:32'],
            'landingAccentColor' => ['nullable', 'string', 'max:32'],
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
        ]);

        $sectionsConfig = [
            'trust_bar' => $this->sectionTrustBar,
            'features' => $this->sectionFeatures,
            'solutions' => $this->sectionSolutions,
            'stats' => $this->sectionStats,
            'about' => $this->sectionAbout,
            'testimonials' => $this->sectionTestimonials,
            'pricing' => $this->sectionPricing,
            'contact' => $this->sectionContact,
            'cta' => $this->sectionCta,
        ];

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
        ]);

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
        ]);
    }
}
