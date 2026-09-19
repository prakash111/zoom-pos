<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Branding\Index as BrandingStudio;
use App\Models\DynamicSetting;
use App\Models\PlatformBranding;
use App\Models\SaaSPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class DynamicLandingStudioTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
        PlatformBranding::current()->update(['landing_page_enabled' => true]);

        SaaSPlan::create([
            'name' => 'growth',
            'display_name' => 'Growth Tier',
            'price' => 49.00,
            'currency' => '$',
            'billing_cycle' => 'monthly',
            'active' => true,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_superadmin_can_fully_customize_every_landing_section_via_branding_studio(): void
    {
        $admin = $this->actingAsSuperAdmin();

        Livewire::test(BrandingStudio::class)
            ->set('platformName', 'Omni Retail Pro')
            ->set('landingPageEnabled', true)
            ->set('landingTheme', 'theme_fast')
            // Hero section
            ->set('landingHeroBadge', 'Next-Gen Commerce')
            ->set('landingHeroTitle', 'The Operating Engine for Fast Retail')
            ->set('landingHeroSubtitle', 'Run your multi-branch enterprise without latency or headaches.')
            ->set('landingHeroCtaPrimaryText', 'Start 14-Day Trial')
            ->set('landingHeroCtaPrimaryUrl', 'https://example.com/start-trial')
            ->set('landingHeroCtaSecondaryText', 'Watch 2-Min Demo')
            ->set('landingHeroCtaSecondaryUrl', 'https://example.com/demo')
            ->set('landingHeroHighlights', ['100% Offline Checkout', 'Instant Scale Integration', 'Predictive Stock AI'])
            ->set('landingHeroDashboardTitle', 'Omni Touch POS')
            ->set('landingHeroDashboardStatus', 'System Online')
            ->set('landingHeroTotalLabel', 'Grand Total')
            ->set('landingHeroPaymentLabel', 'Contactless Apple Pay')
            ->set('landingHeroTotalAmount', '$88.50')
            ->set('landingHeroProducts', [
                ['name' => 'Organic Matcha Powder', 'price' => '$24.00', 'status' => 'In Stock', 'tone' => 'emerald'],
                ['name' => 'Cold Brew Concentrate', 'price' => '$16.50', 'status' => 'Low Stock', 'tone' => 'amber'],
            ])
            // Hardware Trust Bar
            ->set('sectionMeta.trust_bar.title', 'Certified Plug & Play Hardware Support')
            ->set('landingHardwareItems', [
                ['label' => 'Zebra High-Speed Scanners', 'tag' => 'Instant Decode', 'icon' => 'barcode'],
                ['label' => 'Epson Thermal Invoicers', 'tag' => 'Ultra Silent', 'icon' => 'printer'],
            ])
            // Features
            ->set('sectionMeta.features.badge', 'Deep Capabilities')
            ->set('sectionMeta.features.title', 'Built For Demanding Counters')
            ->set('sectionMeta.features.subtitle', 'Everything from inventory to kitchen screens.')
            ->set('landingFeatures', [
                ['icon' => '⚡', 'title' => 'Millisecond Barcode Scanning', 'body' => 'Instant response on every keystroke and trigger.'],
                ['icon' => '🛡️', 'title' => 'Encrypted Financial Ledgers', 'body' => 'Compliant accounting with double-entry precision.'],
            ])
            // Solutions / Pillars
            ->set('sectionMeta.solutions.badge', 'Enterprise Grade')
            ->set('sectionMeta.solutions.title', 'Sub-Zero Downtime Architecture')
            ->set('sectionMeta.solutions.subtitle', 'Engineered to withstand Black Friday surges.')
            ->set('landingSolutions', [
                ['icon' => '🚀', 'title' => 'Autonomous Multi-Tenant DBs', 'body' => 'Each client operates on an isolated secure cluster.'],
            ])
            // Downloads
            ->set('sectionDownloads', true)
            ->set('landingPlaystoreEnabled', true)
            ->set('landingPlaystoreUrl', 'https://play.google.com/store/apps/details?id=com.omni.retail')
            ->set('landingWindowsEnabled', true)
            ->set('landingWindowsUrl', 'https://downloads.example.com/OmniPOS-v2.exe')
            ->set('sectionMeta.downloads.badge', 'Desktop & Mobile')
            ->set('sectionMeta.downloads.title', 'Full Native Speed on Any Counter')
            ->set('sectionMeta.downloads.subtitle', 'Download our native clients.')
            // Stats
            ->set('sectionMeta.stats.title', 'Verified Live Telemetry')
            ->set('landingStats', [
                ['value' => '10,000,000+', 'label' => 'Daily Receipts Printed'],
                ['value' => '99.999%', 'label' => 'Certified Uptime'],
            ])
            // About
            ->set('sectionMeta.about.badge', 'The Vision')
            ->set('sectionMeta.about.title', 'Revolutionizing Global Point of Sale')
            ->set('sectionMeta.about.body', 'Omni Retail Pro empowers ambitious retail and dining operators across the globe.')
            // Testimonials
            ->set('sectionMeta.testimonials.badge', 'Verified Reviews')
            ->set('sectionMeta.testimonials.title', 'Loved by 5,000+ Store Managers')
            ->set('landingTestimonials', [
                ['quote' => 'Cut our checkout lines in half during holiday rush.', 'name' => 'Sarah Jenkins', 'role' => 'VP of Retail, Peak Foods'],
            ])
            // Pricing
            ->set('sectionMeta.pricing.badge', 'Transparent Tiers')
            ->set('sectionMeta.pricing.title', 'Zero Hidden Fees, Pure Scale')
            ->set('sectionMeta.pricing.subtitle', 'Pick a plan that matches your till count.')
            ->set('pricingDiscountBadge', 'Save 25% Annually')
            ->set('pricingNote', 'All plans include 24/7 priority hardware phone support.')
            // FAQ
            ->set('sectionMeta.faq.badge', 'Got Questions?')
            ->set('sectionMeta.faq.title', 'Answers from Our Engineering Team')
            ->set('sectionMeta.faq.subtitle', 'Everything you want to know about our sync architecture.')
            ->set('landingFaqs', [
                ['q' => 'Can we use our existing barcode printers?', 'a' => 'Yes, all ESC/POS and standard USB/Bluetooth printers are supported.'],
            ])
            // Contact
            ->set('sectionMeta.contact.badge', 'Direct Support')
            ->set('sectionMeta.contact.title', 'Talk Directly to a Solutions Architect')
            ->set('sectionMeta.contact.subtitle', 'We will respond within 2 hours during market hours.')
            // CTA
            ->set('sectionMeta.cta.badge', 'Immediate Provisioning')
            ->set('sectionMeta.cta.title', 'Start Ringing Sales in Under 5 Minutes')
            ->set('sectionMeta.cta.subtitle', 'Create your store account now. No credit card required.')
            ->set('ctaPrimaryText', 'Launch Free Store')
            ->set('ctaPrimaryUrl', 'https://example.com/launch')
            ->set('ctaSecondaryText', 'Sign In to Portal')
            ->set('ctaSecondaryUrl', 'https://example.com/portal')
            ->call('save')
            ->assertHasNoErrors();

        // 1. Verify PlatformBranding model was updated
        $branding = PlatformBranding::current()->fresh();
        $this->assertSame('Omni Retail Pro', $branding->platform_name);
        $this->assertSame('The Operating Engine for Fast Retail', $branding->getHeroTitle());
        $this->assertSame('Certified Plug & Play Hardware Support', $branding->getSectionTitle('trust_bar'));
        $this->assertSame('Deep Capabilities', $branding->getSectionBadge('features'));
        $this->assertSame('Sub-Zero Downtime Architecture', $branding->getSectionTitle('solutions'));
        $this->assertSame('Full Native Speed on Any Counter', $branding->getSectionTitle('downloads'));
        $this->assertSame('Verified Live Telemetry', $branding->getSectionTitle('stats'));
        $this->assertSame('The Vision', $branding->getSectionBadge('about'));
        $this->assertSame('Revolutionizing Global Point of Sale', $branding->getSectionTitle('about'));
        $this->assertSame('Loved by 5,000+ Store Managers', $branding->getSectionTitle('testimonials'));
        $this->assertSame('Transparent Tiers', $branding->getSectionBadge('pricing'));
        $this->assertSame('Got Questions?', $branding->getSectionBadge('faq'));
        $this->assertSame('Talk Directly to a Solutions Architect', $branding->getSectionTitle('contact'));
        $this->assertSame('Immediate Provisioning', $branding->getSectionBadge('cta'));
        $this->assertSame('Launch Free Store', $branding->landingText('cta.primary_text'));

        // 2. Test public landing page renders all customized strings with theme_fast (as guest visitor)
        auth('platform_web')->logout();

        $resFast = $this->get('/');
        $resFast->assertOk();
        $resFast->assertSee('Omni Retail Pro');
        $resFast->assertSee('The Operating Engine for Fast Retail');
        $resFast->assertSee('Start 14-Day Trial');
        $resFast->assertSee('100% Offline Checkout');
        $resFast->assertSee('Omni Touch POS');
        $resFast->assertSee('Certified Plug & Play Hardware Support');
        $resFast->assertSee('Zebra High-Speed Scanners');
        $resFast->assertSee('Built For Demanding Counters');
        $resFast->assertSee('Millisecond Barcode Scanning');
        $resFast->assertSee('Enterprise Grade');
        $resFast->assertSee('Sub-Zero Downtime Architecture');
        $resFast->assertSee('Autonomous Multi-Tenant DBs');
        $resFast->assertSee('Full Native Speed on Any Counter');
        $resFast->assertSee('Verified Live Telemetry');
        $resFast->assertSee('10,000,000+');
        $resFast->assertSee('The Vision');
        $resFast->assertSee('Revolutionizing Global Point of Sale');
        $resFast->assertSee('Sarah Jenkins');
        $resFast->assertSee('Save 25% Annually');
        $resFast->assertSee('All plans include 24/7 priority hardware phone support.');
        $resFast->assertSee('Answers from Our Engineering Team');
        $resFast->assertSee('Can we use our existing barcode printers?');
        $resFast->assertSee('Talk Directly to a Solutions Architect');
        $resFast->assertSee('Start Ringing Sales in Under 5 Minutes');
        $resFast->assertSee('Launch Free Store');

        // 3. Switch theme to theme_modern and verify dynamic rendering
        auth('platform_web')->login($admin);

        Livewire::test(BrandingStudio::class)
            ->set('landingTheme', 'theme_modern')
            ->call('save')
            ->assertHasNoErrors();

        auth('platform_web')->logout();

        $resModern = $this->get('/');
        $resModern->assertOk();
        $resModern->assertSee('Omni Retail Pro');
        $resModern->assertSee('The Operating Engine for Fast Retail');
        $resModern->assertSee('100% Offline Checkout');
        $resModern->assertSee('Certified Plug & Play Hardware Support');
        $resModern->assertSee('Built For Demanding Counters');
        $resModern->assertSee('Autonomous Multi-Tenant DBs');
        $resModern->assertSee('Verified Live Telemetry');
        $resModern->assertSee('Sarah Jenkins');
        $resModern->assertSee('All plans include 24/7 priority hardware phone support.');
        $resModern->assertSee('Can we use our existing barcode printers?');
        $resModern->assertSee('Talk Directly to a Solutions Architect');
        $resModern->assertSee('Launch Free Store');
    }

    public function test_superadmin_can_reorder_and_toggle_sections(): void
    {
        $this->actingAsSuperAdmin();

        // 1. Move FAQ to the top of section order, disable Pricing and About
        $customOrder = ['faq', 'hero', 'trust_bar', 'features', 'solutions', 'downloads', 'stats', 'testimonials', 'contact', 'cta'];

        Livewire::test(BrandingStudio::class)
            ->set('landingSectionOrder', $customOrder)
            ->set('sectionPricing', false)
            ->set('sectionAbout', false)
            ->set('sectionMeta.faq.title', 'FAQ Is First Section')
            ->set('landingFaqs', [['q' => 'Is FAQ on top?', 'a' => 'Yes, custom ordering places FAQ first.']])
            ->call('save')
            ->assertHasNoErrors();

        $branding = PlatformBranding::current()->fresh();
        $this->assertFalse($branding->isSectionEnabled('pricing'));
        $this->assertFalse($branding->isSectionEnabled('about'));
        $this->assertSame('faq', $branding->landingSectionOrder()[0]);

        auth('platform_web')->logout();

        $res = $this->get('/');
        $res->assertOk();
        $res->assertSee('FAQ Is First Section');
        $res->assertSee('Is FAQ on top?');

        // Verify disabled sections are not rendered
        $res->assertDontSee('Predictable Investment');
        $res->assertDontSee('Our Mission');

        // Verify section order in the rendered HTML: FAQ appears before showcase/hero
        $faqPos = strpos($res->getContent(), 'FAQ Is First Section');
        $heroPos = strpos($res->getContent(), 'id="showcase"');
        $this->assertNotFalse($faqPos);
        $this->assertNotFalse($heroPos);
        $this->assertLessThan($heroPos, $faqPos, 'FAQ section should be rendered before the hero section when ordered first.');
    }

    public function test_repeaters_support_add_and_remove_methods(): void
    {
        $this->actingAsSuperAdmin();

        $test = Livewire::test(BrandingStudio::class);

        // Test Highlight repeater
        $test->call('addHighlight')
            ->set('landingHeroHighlights.0', 'Custom Bullet')
            ->call('removeHighlight', 0);

        // Test Product repeater
        $test->call('addProduct')
            ->set('landingHeroProducts.0.name', 'New Croissant')
            ->call('removeProduct', 0);

        // Test Hardware repeater
        $test->call('addHardware')
            ->set('landingHardwareItems.0.label', 'Custom Drawer')
            ->call('removeHardware', 0);

        // Test Feature repeater
        $test->call('addFeature')
            ->set('landingFeatures.0.title', 'Instant KDS')
            ->call('removeFeature', 0);

        // Test Solution repeater
        $test->call('addSolution')
            ->set('landingSolutions.0.title', 'Edge Sync')
            ->call('removeSolution', 0);

        // Test Stat repeater
        $test->call('addStat')
            ->set('landingStats.0.value', '50,000+')
            ->call('removeStat', 0);

        // Test Testimonial repeater
        $test->call('addTestimonial')
            ->set('landingTestimonials.0.name', 'Alex Store')
            ->call('removeTestimonial', 0);

        // Test FAQ repeater
        $test->call('addFaq')
            ->set('landingFaqs.0.q', 'Can we pay cash?')
            ->call('removeFaq', 0);

        $test->assertHasNoErrors();
    }

    public function test_seeded_production_ready_sales_content_renders_on_landing_page(): void
    {
        $this->artisan('landing:seed-content --force')->assertSuccessful();

        // Test theme_fast
        DynamicSetting::set('landing_page_theme', 'theme_fast');
        $resFast = $this->get('/');
        $resFast->assertOk();
        $resFast->assertSee('Scale Your Store Sales Online', false);
        $resFast->assertSee('Online Store &amp; Digital Catalog', false);
        $resFast->assertSee('Omnichannel Inventory &amp; Warehouse Sync', false);
        $resFast->assertSee('Sub-Second Barcode POS Checkout', false);
        $resFast->assertSee('Automated Tax Invoicing (GST/VAT) &amp; WhatsApp Delivery', false);
        $resFast->assertSee('Why Modern Online Stores &amp; Retailers Choose Our Platform', false);
        $resFast->assertSee('Zero Overselling', false);
        $resFast->assertSee('Built to Turn Every Online Store &amp; Counter into a High-Revenue Machine', false);
        $resFast->assertSee('Ready to Scale Your Online &amp; In-Store Sales?', false);
        $resFast->assertSee('Marcus Vance', false);

        // Test theme_modern
        DynamicSetting::set('landing_page_theme', 'theme_modern');
        $resModern = $this->get('/');
        $resModern->assertOk();
        $resModern->assertSee('Scale Your Store Sales Online', false);
        $resModern->assertSee('Online Store &amp; Digital Catalog', false);
        $resModern->assertSee('Sub-Second Speed &amp; 100% Offline Resilience', false);
        $resModern->assertSee('Ready to Scale Your Online &amp; In-Store Sales?', false);
    }
}
