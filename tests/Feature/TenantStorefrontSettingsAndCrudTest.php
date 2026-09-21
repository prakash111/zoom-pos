<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Coupon;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Role;
use App\Models\TenantApiKey;
use App\Models\User;
use App\Services\Sdui\SchemaResponse;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantStorefrontSettingsAndCrudTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private TenantApiKey $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Demo Super Store',
            'slug' => 'demo-super-store',
            'email' => 'admin@demosuperstore.com',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'pos_mode' => 'retail',
            'status' => 'active',
            'website' => 'https://metromart.demo',
        ]);

        $role = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Store Admin',
            'slug' => 'admin',
            'permissions' => ['*'],
        ]);

        $this->user = User::create([
            'company_id' => $this->company->id,
            'name' => 'Store Owner',
            'login' => 'owner@demosuperstore.com',
            'email' => 'owner@demosuperstore.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->apiKey = TenantApiKey::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Test POS Client',
            'token' => 'zk_test_' . Str::random(32),
            'permissions' => ['*'],
            'active' => true,
        ]);
    }

    private function authHeaders(): array
    {
        return [
            'X-API-Key' => $this->apiKey->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_get_and_update_storefront_banner_auth(): void
    {
        $res = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/tenant/storefront/banner-auth');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'banner_tag',
                    'banner_title',
                    'banner_subtitle',
                    'cta_text',
                    'cta_link',
                    'is_active',
                    'enable_google_login',
                    'google_client_id',
                ],
            ]);

        $updateRes = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/tenant/storefront/banner-auth', [
                'banner_tag' => 'WINTER SALE',
                'banner_title' => 'Get 40% Off Everything Today',
                'banner_subtitle' => 'Limited time discounts on all store items.',
                'cta_text' => 'Shop Winter Deals',
                'cta_link' => '#winter-deals',
                'is_active' => true,
                'enable_google_login' => true,
                'google_client_id' => 'winter-google-client-id.apps.googleusercontent.com',
                'google_client_secret' => 'google-secret-123',
            ]);

        $updateRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.banner_tag', 'WINTER SALE')
            ->assertJsonPath('data.banner_title', 'Get 40% Off Everything Today')
            ->assertJsonPath('data.enable_google_login', true);

        $this->company->refresh();
        $this->assertSame('WINTER SALE', $this->company->store_banner_tag);
        $this->assertSame('Get 40% Off Everything Today', $this->company->store_banner_title);
        $this->assertTrue($this->company->enable_google_login);
    }

    public function test_get_and_update_storefront_payment_gateways(): void
    {
        $res = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/tenant/storefront/payment-gateways');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'gateways',
                ],
            ]);

        $gateways = $res->json('data.gateways');
        $this->assertNotEmpty($gateways);

        $updateRes = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/tenant/storefront/payment-gateways', [
                'gateways' => [
                    [
                        'id' => 'cod',
                        'is_enabled' => true,
                        'name' => 'Cash on Delivery',
                        'instructions' => 'Pay cash directly upon home delivery.',
                    ],
                    [
                        'id' => 'razorpay',
                        'is_enabled' => true,
                        'name' => 'Razorpay (Cards / UPI)',
                        'key_id' => 'rzp_test_MyCustomKey123',
                        'key_secret' => 'rzp_secret_SecKey456',
                    ],
                ],
            ]);

        $updateRes->assertOk()->assertJsonPath('success', true);

        $this->company->refresh();
        $stored = (array) $this->company->storefront_payment_gateways;
        $this->assertTrue($stored['cod']['enabled']);
        $this->assertSame('Pay cash directly upon home delivery.', $stored['cod']['instructions']);
        $this->assertTrue($stored['razorpay']['enabled']);
        $this->assertSame('rzp_test_MyCustomKey123', $stored['razorpay']['key_id']);
        $this->assertSame('rzp_secret_SecKey456', $stored['razorpay']['key_secret']);
    }

    public function test_sdui_settings_storefront_and_payments_views_render_proper_schemas(): void
    {
        $storefrontView = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/views/settings-storefront');

        $storefrontView->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.type', 'screen')
            ->assertJsonPath('schema.title', 'Storefront Banner & Auth');

        $this->assertNotEmpty($storefrontView->json('schema.components'));

        $paymentsView = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/views/settings-payments');

        $paymentsView->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.type', 'screen')
            ->assertJsonPath('schema.title', 'Storefront Payment Gateways');

        $this->assertNotEmpty($paymentsView->json('schema.components'));
    }

    public function test_sdui_coupons_and_faqs_views_have_fab_and_action_buttons(): void
    {
        Coupon::create([
            'company_id' => $this->company->id,
            'code' => 'TEST50',
            'discount_type' => 'percentage',
            'discount_value' => 50,
            'min_order_amount' => 100,
            'is_active' => true,
            'description' => 'Test Coupon 50% off',
        ]);

        Faq::create([
            'company_id' => $this->company->id,
            'question' => 'How does delivery work?',
            'answer' => 'We deliver within 2 business days.',
            'category' => 'Delivery',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $couponsView = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/views/settings-coupons');

        $couponsView->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Coupons & Discounts');

        // Verify FAB exists
        $fab = $couponsView->json('schema.fab');
        $this->assertNotNull($fab, 'Coupons view must include a Floating Action Button');
        $this->assertSame('fab', $fab['type']);
        $this->assertSame('open_modal', $fab['action']['type']);

        // Verify card actions (Edit and Delete)
        $schemaJson = json_encode($couponsView->json('schema'), JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('Edit', $schemaJson);
        $this->assertStringContainsString('Delete', $schemaJson);
        $this->assertStringContainsString('/api/v1/tenant/coupons', $schemaJson);

        $faqsView = $this->withHeaders($this->authHeaders())
            ->getJson('/api/tenant/views/settings-faqs');

        $faqsView->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Store FAQs & Help Center');

        $faqFab = $faqsView->json('schema.fab');
        $this->assertNotNull($faqFab, 'FAQs view must include a Floating Action Button');
        $this->assertSame('fab', $faqFab['type']);
        $this->assertSame('open_modal', $faqFab['action']['type']);

        $faqSchemaJson = json_encode($faqsView->json('schema'), JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('Edit', $faqSchemaJson);
        $this->assertStringContainsString('Delete', $faqSchemaJson);
        $this->assertStringContainsString('/api/v1/tenant/faqs', $faqSchemaJson);
    }

    public function test_coupon_crud_api_flow(): void
    {
        // 1. Create Coupon
        $createRes = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/tenant/coupons', [
                'code' => 'WELCOME25',
                'discount_type' => 'percentage',
                'discount_value' => 25,
                'min_order_amount' => 50,
                'max_discount_amount' => 20,
                'usage_limit_total' => 100,
                'description' => 'Welcome coupon for new customers',
                'is_active' => true,
            ]);

        $createRes->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'WELCOME25');
        $this->assertEquals(25, $createRes->json('data.discount_value'));

        $couponId = $createRes->json('data.id');

        // 2. List Coupons
        $listRes = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/tenant/coupons');
        $listRes->assertOk()->assertJsonPath('success', true);
        $this->assertCount(1, $listRes->json('data'));

        // 3. Update Coupon
        $updateRes = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/tenant/coupons/{$couponId}", [
                'discount_value' => 30,
                'description' => 'Updated 30% off',
            ]);
        $updateRes->assertOk()
            ->assertJsonPath('success', true);
        $this->assertEquals(30, $updateRes->json('data.discount_value'));

        // 4. Delete Coupon
        $deleteRes = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/tenant/coupons/{$couponId}");
        $deleteRes->assertOk()->assertJsonPath('success', true);

        $this->assertNull(Coupon::find($couponId));
    }

    public function test_faq_crud_api_flow(): void
    {
        // 1. Create FAQ
        $createRes = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/tenant/faqs', [
                'question' => 'What is your return policy?',
                'answer' => 'Items can be returned within 30 days of purchase.',
                'category' => 'Returns',
                'sort_order' => 1,
                'is_active' => true,
            ]);

        $createRes->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.question', 'What is your return policy?');

        $faqId = $createRes->json('data.id');

        // 2. List FAQs
        $listRes = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/tenant/faqs');
        $listRes->assertOk()->assertJsonPath('success', true);

        // 3. Update FAQ
        $updateRes = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/tenant/faqs/{$faqId}", [
                'answer' => 'Items can be returned within 45 days with original receipt.',
            ]);
        $updateRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.answer', 'Items can be returned within 45 days with original receipt.');

        // 4. Delete FAQ
        $deleteRes = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/tenant/faqs/{$faqId}");
        $deleteRes->assertOk()->assertJsonPath('success', true);

        $this->assertNull(Faq::find($faqId));
    }

    public function test_public_subdomain_availability_check(): void
    {
        // 1. Existing subdomain
        $resExisting = $this->getJson('/api/v1/public/check-subdomain?subdomain=demo-super-store');
        $resExisting->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('available', false)
            ->assertJsonStructure(['suggestion', 'url']);

        // 2. Reserved subdomain
        $resReserved = $this->getJson('/api/v1/public/check-subdomain?subdomain=admin');
        $resReserved->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('available', false);

        // 3. Available subdomain
        $uniqueSub = 'store-' . Str::lower(Str::random(10));
        $resAvailable = $this->getJson("/api/v1/public/check-subdomain?subdomain={$uniqueSub}");
        $resAvailable->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('available', true)
            ->assertJsonPath('subdomain', $uniqueSub);

        // 4. POS check-subdomain route
        $resPos = $this->getJson("/api/v1/pos/auth/check-subdomain?subdomain={$uniqueSub}");
        $resPos->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('available', true);
    }

    public function test_store_profile_returns_dynamic_storefront_url_and_custom_domain(): void
    {
        $settingsRes = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/pos/settings');

        $settingsRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'profile' => [
                    'subdomain',
                    'store_website',
                    'storefront_url',
                    'custom_domain',
                    'cname_target',
                    'ssl_status',
                ],
            ]);

        $profile = $settingsRes->json('profile');
        $this->assertSame('demo-super-store', $profile['subdomain']);
        $this->assertStringContainsString('demo-super-store', $profile['store_website']);
        $this->assertSame('cname.saas.zoomnearby.com', $profile['cname_target']);
        $this->assertNotSame('https://metromart.demo', $profile['website']);

        // Update custom domain
        $updateRes = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v1/pos/settings/profile', [
                'custom_domain' => 'https://shop.demostore.com/',
            ]);

        $updateRes->assertOk()->assertJsonPath('success', true);
        $this->company->refresh();
        $this->assertSame('shop.demostore.com', $this->company->custom_domain);
    }

    public function test_reviews_sdui_view_renders_successfully(): void
    {
        $aliases = [
            'settings-reviews',
            'settings_reviews',
            'reviews',
            'product-ratings-reviews',
            'storefront-reviews',
        ];

        foreach ($aliases as $alias) {
            $resp = SchemaResponse::renderView($alias, $this->company);
            $this->assertSame(200, $resp->status(), "Alias {$alias} did not return 200");
            $data = $resp->getData(true);
            $this->assertTrue($data['success']);
            $this->assertSame('Product Ratings & Reviews', $data['schema']['title']);

            $validationErrors = app(SchemaValidator::class)->validate($data['schema']);
            $this->assertEmpty($validationErrors, "Schema for {$alias} had validation errors: " . json_encode($validationErrors));
        }
    }

    public function test_tenant_can_list_moderate_and_delete_reviews(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Organic Honey Jar',
            'sku' => 'HONEY-001',
            'price' => 15.00,
            'status' => 'active',
        ]);

        $review = ProductReview::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'customer_name' => 'Alice Walker',
            'customer_email' => 'alice@example.com',
            'rating' => 5,
            'title' => 'Delicious!',
            'comment' => 'The best natural honey I have ever purchased.',
            'is_approved' => false,
            'is_verified_purchase' => true,
        ]);

        // 1. List reviews
        $listRes = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/tenant/storefront/reviews');

        $listRes->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(1, count($listRes->json('data.reviews')));

        // 2. Toggle approval to true
        $approveRes = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/tenant/storefront/reviews/{$review->id}/toggle-approval", [
                'is_approved' => true,
            ]);

        $approveRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_approved', true);

        $review->refresh();
        $this->assertTrue($review->is_approved);

        // 3. Delete review
        $deleteRes = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/tenant/storefront/reviews/{$review->id}");

        $deleteRes->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull(ProductReview::find($review->id));
    }

    public function test_tenant_can_update_review_moderation_settings(): void
    {
        $res = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/tenant/storefront/reviews/settings', [
                'enable_product_reviews' => false,
                'require_review_approval' => true,
            ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.enable_product_reviews', false)
            ->assertJsonPath('data.require_review_approval', true);

        $this->company->refresh();
        $this->assertFalse((bool) $this->company->enable_product_reviews);
        $this->assertTrue((bool) $this->company->require_review_approval);
    }
}
