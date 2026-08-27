<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\Auth\PlatformLogin;
use App\Livewire\SuperAdmin\Branding\Index as BrandingIndex;
use App\Livewire\SuperAdmin\Smtp\Index as SmtpIndex;
use App\Models\PlatformBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class BrandingAndSmtpTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    public function test_branding_can_be_saved(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(BrandingIndex::class)
            ->set('platformName', 'My SaaS Platform')
            ->set('supportEmail', 'support@example.com')
            ->set('otpRegistrationEnabled', true)
            ->call('save');

        $branding = PlatformBranding::current();
        $this->assertSame('My SaaS Platform', $branding->platform_name);
        $this->assertTrue($branding->otp_registration_enabled);
    }

    public function test_smtp_settings_save_and_password_is_encrypted_at_rest(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SmtpIndex::class)
            ->set('smtpHost', 'smtp.example.com')
            ->set('smtpPort', 587)
            ->set('smtpUsername', 'noreply@example.com')
            ->set('smtpPassword', 'super-secret')
            ->set('smtpFromAddress', 'notifications@mysaas.test')
            ->set('smtpFromName', 'My SaaS Notifications')
            ->call('save')
            ->assertHasNoErrors();

        $branding = PlatformBranding::current();
        $this->assertSame('smtp.example.com', $branding->smtp_host);
        $this->assertSame('super-secret', $branding->smtp_password);
        $this->assertSame('notifications@mysaas.test', $branding->smtp_from_address);
        $this->assertSame('My SaaS Notifications', $branding->smtp_from_name);
        $this->assertStringNotContainsString('super-secret', (string) $branding->getRawOriginal('smtp_password'));
    }

    public function test_blank_smtp_password_on_save_keeps_the_previous_one(): void
    {
        $this->actingAsSuperAdmin();
        PlatformBranding::current()->update(['smtp_host' => 'smtp.old.com', 'smtp_password' => 'keep-me']);

        Livewire::test(SmtpIndex::class)
            ->set('smtpHost', 'smtp.new.com')
            ->set('smtpFromAddress', 'alerts@mysaas.test')
            ->set('smtpFromName', 'My SaaS Alerts')
            ->call('save');

        $branding = PlatformBranding::current();
        $this->assertSame('smtp.new.com', $branding->smtp_host);
        $this->assertSame('keep-me', $branding->smtp_password);
        $this->assertSame('alerts@mysaas.test', $branding->smtp_from_address);
        $this->assertSame('My SaaS Alerts', $branding->smtp_from_name);
    }

    public function test_platform_login_screen_renders_matching_layout(): void
    {
        Livewire::test(PlatformLogin::class)
            ->assertSee('Super Admin')
            ->assertSee('Admin Email Address')
            ->assertSee('Admin Password')
            ->assertSee('Sign In to Control Panel');
    }

    public function test_dynamic_branding_and_landing_customization_can_be_saved(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(BrandingIndex::class)
            ->set('platformName', 'ZoomNearby POS & Inventory')
            ->set('superadminSidebarColor', '#0f766e')
            ->set('landingPrimaryColor', '#059669')
            ->set('landingAccentColor', '#84cc16')
            ->set('landingHeroTitle', 'The Next Generation POS & Stock Manager')
            ->set('landingHeroSubtitle', 'Run all your retail and restaurant branches from one screen.')
            ->set('landingHeroCtaPrimaryText', 'Get Started Now')
            ->set('landingHeroCtaPrimaryUrl', '/register')
            ->set('landingHeroBannerImageUrl', 'https://example.com/pos-banner.png')
            ->set('sectionTrustBar', false)
            ->set('sectionFeatures', true)
            ->call('save')
            ->assertHasNoErrors();

        $branding = PlatformBranding::current();
        $this->assertSame('ZoomNearby POS & Inventory', $branding->platform_name);
        $this->assertSame('#0f766e', $branding->superadmin_sidebar_color);
        $this->assertSame('#059669', $branding->landing_primary_color);
        $this->assertSame('#84cc16', $branding->landing_accent_color);
        $this->assertSame('The Next Generation POS & Stock Manager', $branding->getHeroTitle());
        $this->assertSame('Run all your retail and restaurant branches from one screen.', $branding->getHeroSubtitle());
        $this->assertSame('https://example.com/pos-banner.png', $branding->landing_hero_banner_image_url);
        $this->assertFalse($branding->isSectionEnabled('trust_bar'));
        $this->assertTrue($branding->isSectionEnabled('features'));
    }
}
