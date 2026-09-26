<?php

namespace Tests\Feature\Tenant;

use App\Models\Category;
use App\Models\Company;
use App\Models\DynamicSetting;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubdirectoryStorefrontAndPwaToggleTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://saas.zoomnearby.com']);
        config(['app.domain' => 'saas.zoomnearby.com']);
        file_put_contents(storage_path('installed'), '{}');

        $this->company = Company::withoutGlobalScopes()->create([
            'name' => 'Ramu',
            'slug' => 'ramu',
            'subdomain' => 'ramu',
            'phone' => '+15550199',
            'email' => 'ramu@zoomnearby.com',
            'city' => 'Bengaluru',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'General',
            'slug' => 'general',
        ]);

        Product::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Sample Item',
            'sale_price' => 10.00,
            'price' => 10.00,
            'current_stock' => 20,
            'category_id' => $category->id,
            'category_name' => $category->name,
            'active' => true,
        ]);

        $this->user = User::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Ramu Admin',
            'email' => 'ramu.admin@zoomnearby.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
    }

    public function test_storefront_url_uses_subdomain_when_domain_setup_enabled(): void
    {
        DynamicSetting::put('enable_registration_domain_setup', true);

        $url = $this->company->getStorefrontUrl();
        $this->assertEquals('https://ramu.saas.zoomnearby.com', $url);
    }

    public function test_storefront_url_uses_subdirectory_when_domain_setup_disabled(): void
    {
        DynamicSetting::put('enable_registration_domain_setup', false);

        $url = $this->company->getStorefrontUrl();
        $this->assertEquals('https://saas.zoomnearby.com/Ramu', $url);
    }

    public function test_subdirectory_route_renders_storefront_page(): void
    {
        DynamicSetting::put('enable_registration_domain_setup', false);

        $response = $this->get('/Ramu');
        $response->assertStatus(200);
        $response->assertSee('Ramu');
        $response->assertSee('Sample Item');
    }

    public function test_subdirectory_route_case_insensitive_slug_match(): void
    {
        DynamicSetting::put('enable_registration_domain_setup', false);

        $response = $this->get('/ramu');
        $response->assertStatus(200);
        $response->assertSee('Ramu');
    }

    public function test_invalid_subdirectory_returns_404(): void
    {
        $response = $this->get('/non-existent-store-slug-xyz');
        $response->assertStatus(404);
    }

    public function test_pwa_manifest_returns_200_when_enabled(): void
    {
        DynamicSetting::put('pwa_enabled', true);

        $this->actingAs($this->user);
        $response = $this->get(route('tenant.pwa.manifest'));
        $response->assertStatus(200);
        $response->assertJsonPath('scope', '/tenant/');
    }

    public function test_pwa_manifest_returns_404_when_disabled(): void
    {
        DynamicSetting::put('pwa_enabled', false);

        $this->actingAs($this->user);
        $response = $this->get(route('tenant.pwa.manifest'));
        $response->assertStatus(404);
    }

    public function test_api_endpoints_expose_settings(): void
    {
        DynamicSetting::put('enable_registration_domain_setup', false);
        DynamicSetting::put('pwa_enabled', false);

        $authConfigRes = $this->getJson('/api/v1/pos/auth/auth-config');
        $authConfigRes->assertStatus(200);
        $authConfigRes->assertJson([
            'enable_registration_domain_setup' => false,
            'pwa_enabled' => false,
        ]);

        $brandingRes = $this->getJson('/api/v1/pos/auth/branding');
        $brandingRes->assertStatus(200);
        $brandingRes->assertJson([
            'enable_registration_domain_setup' => false,
            'pwa_enabled' => false,
        ]);

        $metaRes = $this->getJson('/api/v1/pos/app/registration-meta');
        $metaRes->assertStatus(200);
        $metaRes->assertJson([
            'enable_registration_domain_setup' => false,
            'pwa_enabled' => false,
        ]);
    }
}
