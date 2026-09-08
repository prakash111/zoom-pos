<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PlatformBranding extends Model
{
    protected $table = 'platform_branding';

    protected $fillable = [
        'platform_name', 'logo_url', 'favicon_url', 'primary_color',
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
    ];

    protected function casts(): array
    {
        return [
            'smtp_password' => 'encrypted',
            'expiration_reminder_thresholds' => 'array',
            'otp_registration_enabled' => 'boolean',
            'landing_page_enabled' => 'boolean',
            'landing_sections_config' => 'array',
            'landing_playstore_enabled' => 'boolean',
            'landing_windows_enabled' => 'boolean',
            'landing_section_meta' => 'array',
            'landing_faqs' => 'array',
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

        if (str_starts_with($this->logo_url, 'http://') || str_starts_with($this->logo_url, 'https://') || str_starts_with($this->logo_url, 'data:')) {
            return $this->logo_url;
        }

        $cleanPath = preg_replace('#^/?storage/#', '', $this->logo_url);

        if (Storage::disk('public')->exists($cleanPath)) {
            return Storage::disk('public')->url($cleanPath);
        }

        if (str_starts_with($this->logo_url, '/')) {
            return asset(ltrim($this->logo_url, '/'));
        }

        return asset('storage/'.ltrim($cleanPath, '/'));
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
            ['q' => __('Do I need to install anything to get started?'), 'a' => __('No. The platform runs in any modern browser. Native Android and Windows apps are optional and available from the download section.')],
            ['q' => __('Does the POS work offline?'), 'a' => __('Yes. Sales are queued locally during a network outage and sync automatically once the connection is restored.')],
            ['q' => __('Can I run more than one store or branch?'), 'a' => __('Yes. Each workspace supports multiple locations with isolated data, shared catalogue and consolidated reporting.')],
            ['q' => __('Is my data secure and backed up?'), 'a' => __('All data is encrypted in transit and at rest, with automated cloud redundancy and point-in-time recovery.')],
        ];
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
