<?php

namespace Tests\Feature\Tenant;

use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://saas.zoomnearby.com']);
        file_put_contents(storage_path('installed'), '{}');

        $this->company = Company::withoutGlobalScopes()->firstOrCreate(
            ['slug' => 'pk-digital-test'],
            [
                'name' => 'PK Digital Test Store',
                'phone' => '+15550199',
                'email' => 'store@pkdigital.test',
                'city' => 'Bengaluru',
                'currency' => 'USD',
                'status' => 'active',
            ]
        );

        $category = Category::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $this->company->id, 'name' => 'Electronics'],
            ['slug' => 'electronics']
        );

        $this->product = Product::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Fast USB-C Cable 2m',
            'sale_price' => 15.99,
            'price' => 15.99,
            'current_stock' => 50,
            'category_id' => $category->id,
            'category_name' => $category->name,
            'description' => 'Premium braided nylon fast charging cable with 100W PD.',
            'active' => true,
        ]);

        $this->user = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'admin@pkdigital.test'],
            [
                'name' => 'Store Admin',
                'password' => bcrypt('secret123'),
                'company_id' => $this->company->id,
                'role' => 'admin',
                'status' => 'active',
            ]
        );
    }

    public function test_tenant_subdomain_renders_storefront_with_products(): void
    {
        $response = $this->get('http://pk-digital-test.saas.zoomnearby.com/');

        $response->assertStatus(200);
        $response->assertSee('PK Digital Test Store');
        $response->assertSee('Fast USB-C Cable 2m');
        $response->assertSee('Premium braided nylon fast charging cable');
        $response->assertSee('Add to Cart');
    }

    public function test_storefront_order_submission_creates_pending_sale_and_customer(): void
    {
        $payload = [
            'customer_name' => 'Bob Buyer',
            'customer_phone' => '+15551234567',
            'customer_email' => 'bob@example.com',
            'address' => '100 Market St',
            'city' => 'Metropolis',
            'postal_code' => '12345',
            'payment_method' => 'cod',
            'items' => [
                [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'quantity' => 2,
                    'price' => 15.99,
                ]
            ],
        ];

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'pk-digital-test.saas.zoomnearby.com',
        ])->postJson('/store/order', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'total' => 31.98,
        ]);

        $sale = Sale::withoutGlobalScopes()->where('customer_name', 'Bob Buyer')->first();
        $this->assertNotNull($sale);
        $this->assertEquals('pending', $sale->status);
        $this->assertEquals('storefront', $sale->service_type);
        $this->assertEquals(31.98, $sale->total);

        // Verify Customer was automatically created and linked
        $this->assertNotNull($sale->customer_id);
        $customer = Customer::withoutGlobalScopes()->find($sale->customer_id);
        $this->assertNotNull($customer);
        $this->assertEquals('+15551234567', $customer->phone);
    }

    public function test_storefront_arabic_locale_renders_rtl_with_clean_assets(): void
    {
        $response = $this->withSession(['locale' => 'ar'])
            ->get('http://pk-digital-test.saas.zoomnearby.com/');

        $response->assertStatus(200);
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('lang="ar"', false);
        $response->assertDontSee('css/app.css'); // No broken relative/asset link
    }

    public function test_storefront_api_catalog_returns_json_structure(): void
    {
        $response = $this->getJson('http://pk-digital-test.saas.zoomnearby.com/api/storefront/catalog');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'company' => ['id', 'name', 'slug', 'currency'],
            'categories',
            'products',
        ]);
        $response->assertJsonFragment([
            'name' => 'Fast USB-C Cable 2m',
        ]);
    }
}
