<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

    protected function casts(): array
    {
        return [
            'smtp_password' => 'encrypted',
            'expiration_reminder_thresholds' => 'array',
            'otp_registration_enabled' => 'boolean',
            'landing_page_enabled' => 'boolean',
            'landing_sections_config' => 'array',
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
        if (empty($this->landing_sections_config)) {
            return true;
        }

        return (bool) ($this->landing_sections_config[$section] ?? true);
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
