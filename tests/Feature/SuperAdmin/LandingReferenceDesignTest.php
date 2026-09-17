<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\MenuItem;
use App\Models\PlatformBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingReferenceDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_applying_reference_preserves_feature_catalog_and_legal_navigation_and_is_idempotent(): void
    {
        $branding = PlatformBranding::current();
        $features = [['icon' => 'cart', 'title' => 'Custom stock workflow', 'body' => 'Keep the existing feature description.']];
        $branding->update([
            'landing_features' => $features,
            'landing_content' => ['about' => ['body' => 'Keep our mission statement.']],
        ]);
        $legal = MenuItem::create([
            'title' => 'Our Terms', 'url' => '/page/our-terms', 'type' => 'custom',
            'location' => 'header', 'is_active' => true, 'order_index' => 0,
        ]);

        $this->artisan('landing:apply-reference-design')->assertSuccessful();

        $this->assertSame($features, $branding->fresh()->landing_features);
        $this->assertSame('Keep our mission statement.', $branding->fresh()->landingText('about.body'));
        $this->assertSame('footer_col_2', $legal->fresh()->location);
        $this->assertSame('/page/our-terms', $legal->fresh()->url);
        $this->assertCount(5, MenuItem::getMenu('header'));

        // Re-applying the preset must preserve edits made after installation.
        $meta = $branding->fresh()->landing_section_meta;
        $meta['hero']['title'] = 'Our updated business headline';
        $branding->update(['landing_section_meta' => $meta]);
        $this->artisan('landing:apply-reference-design')->assertSuccessful();
        $this->assertSame('Our updated business headline', $branding->fresh()->getSectionTitle('hero'));
        $this->assertCount(5, MenuItem::getMenu('header'));
    }

    public function test_reference_homepage_renders_demo_feature_catalog_and_contact_flow(): void
    {
        $this->artisan('landing:apply-reference-design')->assertSuccessful();

        $this->get('/')->assertOk()
            ->assertSee('reference-header', false)
            ->assertSee('landing-device-showcase.png', false)
            ->assertSee('Watch Demo')
            ->assertSee('id="demo"', false)
            ->assertSee('id="all-features"', false)
            ->assertSee('Explore All Features')
            ->assertSee('Point of Sale (POS)')
            ->assertSee('Cloud &amp; Offline Sync', false)
            ->assertSee('130+')
            ->assertSee('contact-form-input', false)
            ->assertSee(route('contact.store'));
    }
}
