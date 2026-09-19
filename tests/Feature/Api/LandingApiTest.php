<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\PlatformBranding;
use App\Models\Product;
use App\Models\PublishedCatalog;
use App\Models\SaaSPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_landing_api_returns_all_sections(): void
    {
        PlatformBranding::current()->update([
            'platform_name' => 'ZoomPOS Enterprise',
            'support_phone' => '+918535075196',
            'support_email' => 'support@zoomnearby.com',
        ]);

        SaaSPlan::create([
            'name' => 'pro-business',
            'display_name' => 'Pro Business Plan',
            'slug' => 'pro-business',
            'price' => 49.00,
            'billing_period' => 'monthly',
            'description' => 'For fast scaling shops',
            'features' => ['Multi-location', 'Offline POS'],
            'is_active' => true,
            'is_featured' => true,
        ]);

        foreach (['/api/public/landing', '/api/v1/pos/public/landing'] as $url) {
            $response = $this->getJson($url);

            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('branding.platform_name', 'ZoomPOS Enterprise')
                ->assertJsonPath('branding.support_phone', '+918535075196')
                ->assertJsonPath('branding.support_whatsapp', '+918535075196')
                ->assertJsonPath('branding.support_email', 'support@zoomnearby.com')
                ->assertJsonStructure([
                    'success',
                    'theme',
                    'branding' => [
                        'platform_name',
                        'logo_url',
                        'primary_color',
                        'support_phone',
                        'support_whatsapp',
                        'support_email',
                    ],
                    'hero' => [
                        'badge',
                        'title',
                        'subtitle',
                        'cta_primary_text',
                        'cta_secondary_text',
                    ],
                    'hardware',
                    'solutions',
                    'features',
                    'stats',
                    'plans',
                    'testimonials',
                    'faqs',
                    'downloads',
                    'contact',
                ]);
        }
    }

    public function test_product_description_field_is_persisted(): void
    {
        $company = Company::create([
            'name' => 'Metro Supermarket',
            'trade_name' => 'Metro Mart',
            'slug' => 'metro-mart',
            'email' => 'pos@metromart.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'document' => 'US-123456789',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Thermal Barcode Scanner USB',
            'sku' => 'SCN-101',
            'barcode' => '890123456789',
            'retail_price' => 45.00,
            'cost_price' => 25.00,
            'description' => 'High speed 2D QR omnidirectional continuous laser scanner for counter checkouts.',
            'stock' => 10,
        ]);

        $this->assertEquals(
            'High speed 2D QR omnidirectional continuous laser scanner for counter checkouts.',
            $product->fresh()->description
        );
    }

    public function test_published_catalog_description_and_meta_are_persisted(): void
    {
        $company = Company::create([
            'name' => 'Metro Supermarket',
            'trade_name' => 'Metro Mart',
            'slug' => 'metro-mart-2',
            'email' => 'pos2@metromart.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'document' => 'US-123456789',
        ]);

        $catalog = PublishedCatalog::create([
            'company_id' => $company->id,
            'token' => 'cat_test_token_123',
            'title' => 'Summer Sale Storefront',
            'description' => 'Browse our online summer catalog and place orders via WhatsApp.',
            'product_ids' => [1, 2, 3],
            'whatsapp_number' => '+918535075196',
            'meta' => [
                'enable_whatsapp_order' => true,
                'allow_quotes' => true,
            ],
            'is_active' => true,
        ]);

        $fresh = $catalog->fresh();
        $this->assertEquals('Summer Sale Storefront', $fresh->title);
        $this->assertEquals('Browse our online summer catalog and place orders via WhatsApp.', $fresh->description);
        $this->assertTrue($fresh->meta['enable_whatsapp_order']);
    }
}
