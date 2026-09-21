<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Branding\Index as BrandingStudio;
use App\Livewire\SuperAdmin\Settings\Index as SettingsIndex;
use App\Models\PlatformBranding;
use App\Models\SaaSPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class ModularSectionsStudioTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
        PlatformBranding::current()->update(['landing_page_enabled' => true]);

        SaaSPlan::create([
            'name' => 'starter',
            'display_name' => 'Starter Plan',
            'price' => 29.00,
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

    public function test_superadmin_settings_whitelabel_tab_renders_modular_sections_studio_and_repeater_actions(): void
    {
        $this->actingAsSuperAdmin();

        $test = Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'whitelabel')
            ->set('homepageMode', 'modular')
            // Assert all 12 sections are present in catalog
            ->assertSee('Modular Sections Studio: Add, Edit & Delete Content')
            ->assertSee('1. Hero Showcase')
            ->assertSee('2. Hardware Bar')
            ->assertSee('3. Features Suite')
            ->assertSee('4. Solutions & Verticals')
            ->assertSee('5. App Downloads')
            ->assertSee('6. Live Metrics & Stats')
            ->assertSee('7. Mission & About')
            ->assertSee('8. Customer Reviews')
            ->assertSee('9. Pricing Plans')
            ->assertSee('10. FAQ')
            ->assertSee('11. Contact Inquiry')
            ->assertSee('12. Conversion CTA');

        // Test repeaters: add and remove items
        $test->call('addHeroHighlight')
            ->set('landingHeroHighlights.0', 'Instant Offline Checkout Sync')
            ->call('addHardware')
            ->set('landingHardwareItems.0.label', 'Zebra Wireless Scanner')
            ->set('landingHardwareItems.0.tag', '2D Bluetooth')
            ->call('addFeature')
            ->set('landingFeatures.0.title', 'Smart Multi-Branch Inventory')
            ->call('addSolution')
            ->set('landingSolutions.0.title', 'Zero Latency Terminal Network')
            ->call('addStat')
            ->set('landingStats.0.value', '10M+')
            ->set('landingStats.0.label', 'Transactions Processed')
            ->call('addTestimonial')
            ->set('landingTestimonials.0.name', 'Jordan Taylor')
            ->set('landingTestimonials.0.quote', 'Transformed our 12 stores.')
            ->call('addFaq')
            ->set('landingFaqs.0.q', 'Can we print ESC/POS kitchen orders?')
            ->set('landingFaqs.0.a', 'Yes, full KOT and KDS integration is included.');

        // Update section badges & titles
        $test->set('sectionMeta.features.badge', 'Unified Power')
            ->set('sectionMeta.features.title', 'Everything Built For Speed')
            ->set('sectionMeta.faq.badge', 'Common Questions')
            ->set('sectionMeta.faq.title', 'Everything You Want To Know')
            ->call('saveBranding')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $branding = PlatformBranding::current();
        $this->assertSame('Instant Offline Checkout Sync', $branding->landingList('hero.highlights')[0]);
        $this->assertSame('Zebra Wireless Scanner', $branding->landingHardware()[0]['label']);
        $this->assertSame('Smart Multi-Branch Inventory', $branding->landingFeatures()[0]['title']);
        $this->assertSame('Zero Latency Terminal Network', $branding->landingSolutionsList()[0]['title']);
        $this->assertSame('10M+', $branding->landingStatsList()[0]['value']);
        $this->assertSame('Jordan Taylor', $branding->landingTestimonials()[0]['name']);
        $this->assertSame('Can we print ESC/POS kitchen orders?', $branding->landingFaqs()[0]['q']);
        $this->assertSame('Unified Power', $branding->getSectionBadge('features'));
        $this->assertSame('Everything Built For Speed', $branding->getSectionTitle('features'));
    }

    public function test_superadmin_branding_page_renders_modular_sections_studio(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(BrandingStudio::class)
            ->set('activeTab', 'landing')
            ->set('homepageMode', 'modular')
            ->assertSee('Modular Sections Studio: Add, Edit & Delete Content')
            ->assertSee('1. Hero Showcase')
            ->assertSee('2. Hardware Bar')
            ->assertSee('3. Features Suite')
            ->assertSee('4. Solutions & Verticals')
            ->assertSee('5. App Downloads')
            ->assertSee('6. Live Metrics & Stats')
            ->assertSee('7. Mission & About')
            ->assertSee('8. Customer Reviews')
            ->assertSee('9. Pricing Plans')
            ->assertSee('10. FAQ')
            ->assertSee('11. Contact Inquiry')
            ->assertSee('12. Conversion CTA');
    }

    public function test_public_landing_page_renders_customized_modular_sections(): void
    {
        PlatformBranding::current()->update([
            'landing_page_enabled' => true,
            'landing_section_meta' => [
                'features' => ['badge' => 'Exclusive Tech', 'title' => 'Advanced Retail Capabilities', 'subtitle' => 'Unified and synced across counters.'],
                'faq' => ['badge' => 'Quick Help', 'title' => 'Got Questions?', 'subtitle' => 'Answers for busy owners.'],
            ],
            'landing_faqs' => [
                ['q' => 'Is there any monthly contract?', 'a' => 'No, cancel anytime with zero penalty.'],
            ],
            'landing_content' => [
                'homepage_mode' => 'modular',
                'trust' => [
                    'hardware' => [
                        ['label' => 'SuperScanner 9000', 'tag' => 'Wireless Laser', 'icon' => 'barcode'],
                    ],
                ],
            ],
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Exclusive Tech');
        $response->assertSee('Advanced Retail Capabilities');
        $response->assertSee('Got Questions?');
        $response->assertSee('Is there any monthly contract?');
        $response->assertSee('SuperScanner 9000');
    }

    public function test_superadmin_can_configure_head_office_address_and_working_hours_in_contact_studio(): void
    {
        $this->actingAsSuperAdmin();

        $test = Livewire::test(SettingsIndex::class)
            ->set('activeTab', 'whitelabel')
            ->set('homepageMode', 'modular')
            ->assertSee('Head Office Physical Address')
            ->assertSee('Working Hours')
            ->set('headOfficeAddress', '123 Innovation Boulevard, Suite 500, Austin, TX 78701')
            ->set('workingHours', 'Monday - Saturday (08:00 AM - 08:00 PM)')
            ->call('saveBranding')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $branding = PlatformBranding::current();
        $this->assertSame('123 Innovation Boulevard, Suite 500, Austin, TX 78701', $branding->head_office_address);
        $this->assertSame('Monday - Saturday (08:00 AM - 08:00 PM)', $branding->working_hours);
        $this->assertSame('123 Innovation Boulevard, Suite 500, Austin, TX 78701', $branding->getHeadOfficeAddress());
        $this->assertSame('Monday - Saturday (08:00 AM - 08:00 PM)', $branding->getWorkingHours());

        // Check public landing page rendering
        $response = $this->get('/');
        $response->assertOk()
            ->assertSee('123 Innovation Boulevard, Suite 500, Austin, TX 78701')
            ->assertSee('Monday - Saturday (08:00 AM - 08:00 PM)')
            ->assertDontSee('Metrotech Center, NY 11201');

        // Check public contact route rendering
        $contactResponse = $this->get('/contact');
        $contactResponse->assertOk()
            ->assertSee('123 Innovation Boulevard, Suite 500, Austin, TX 78701')
            ->assertSee('Monday - Saturday (08:00 AM - 08:00 PM)')
            ->assertDontSee('Metrotech Center, NY 11201');
    }
}
