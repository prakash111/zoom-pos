<?php

namespace App\Livewire\SuperAdmin\Branding;

use App\Models\AuditLog;
use App\Models\Page;
use App\Models\PlatformBranding;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.superadmin', ['title' => 'Branding & Landing Page'])]
class Index extends Component
{
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

    public function mount(): void
    {
        $branding = PlatformBranding::current();
        $this->platformName = $branding->platform_name;
        $this->logoUrl = (string) $branding->logo_url;
        $this->faviconUrl = (string) $branding->favicon_url;
        $this->primaryColor = $branding->primary_color ?? '#4f46e5';
        $this->superadminSidebarColor = $branding->superadmin_sidebar_color ?? '#4338ca';
        $this->landingPrimaryColor = $branding->landing_primary_color ?? '#10b981';
        $this->landingAccentColor = $branding->landing_accent_color ?? '#d7f24e';
        $this->supportEmail = (string) $branding->support_email;
        $this->supportPhone = (string) $branding->support_phone;
        $this->otpRegistrationEnabled = $branding->otp_registration_enabled;
        $this->landingPageEnabled = $branding->landing_page_enabled;
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
    }

    protected function rules(): array
    {
        return [
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
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

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
        session()->flash('status', 'Branding & Landing Page settings saved successfully.');
    }

    public function render()
    {
        return view('livewire.superadmin.branding.index', [
            'availablePages' => Page::orderBy('title')->get(),
        ]);
    }
}
