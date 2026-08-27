<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Catalog\Index as CatalogIndex;
use App\Models\Company;
use App\Models\Product;
use App\Models\PublishedCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_publishing_a_catalog_creates_a_working_public_link(): void
    {
        $this->actingAsTenantAdmin();
        $product = Product::create(['name' => 'Widget', 'sale_price' => 15, 'active' => true]);

        Livewire::test(CatalogIndex::class)
            ->set('title', 'Summer Collection')
            ->set('selectedProductIds', [$product->id])
            ->call('publish');

        $catalog = PublishedCatalog::firstOrFail();
        $this->assertSame('Summer Collection', $catalog->title);

        $this->get(route('catalog.show', $catalog->id))
            ->assertOk()
            ->assertSee('Acme Inc')
            ->assertSee('Widget')
            ->assertSee('15.00')
            ->assertSee('Add to Cart')
            ->assertSee('Order via WhatsApp');
    }

    public function test_expired_catalog_link_returns_404(): void
    {
        $this->actingAsTenantAdmin();
        $product = Product::create(['name' => 'Widget', 'sale_price' => 15, 'active' => true]);
        $catalog = PublishedCatalog::create([
            'title' => 'Old', 'product_ids' => [$product->id], 'expires_at' => now()->subDay(),
        ]);

        $this->get(route('catalog.show', $catalog->id))->assertNotFound();
    }

    public function test_catalog_never_exposes_another_tenants_products(): void
    {
        $this->actingAsTenantAdmin();
        $ownProduct = Product::create(['name' => 'Mine', 'sale_price' => 10, 'active' => true]);

        $otherCompany = Company::create(['name' => 'Other Co']);
        $foreignProduct = Product::create(['company_id' => $otherCompany->id, 'name' => 'NotMine', 'sale_price' => 20, 'active' => true]);

        $catalog = PublishedCatalog::create([
            'title' => 'Mixed', 'product_ids' => [$ownProduct->id, $foreignProduct->id],
        ]);

        $this->get(route('catalog.show', $catalog->id))
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('NotMine');
    }
}
