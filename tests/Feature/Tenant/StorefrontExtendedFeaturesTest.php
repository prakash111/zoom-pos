<?php

namespace Tests\Feature\Tenant;

use App\Models\Category;
use App\Models\Company;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontExtendedFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $user;
    protected Product $product;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://saas.zoomnearby.com']);
        file_put_contents(storage_path('installed'), '{}');

        $this->company = Company::withoutGlobalScopes()->create([
            'slug' => 'gadget-hub-test',
            'name' => 'Gadget Hub Online',
            'phone' => '+15554321',
            'email' => 'sales@gadgethub.test',
            'city' => 'San Francisco',
            'currency' => 'USD',
            'status' => 'active',
            'store_banner_tag' => 'WINTER GADGET BLOWOUT',
            'store_banner_title' => 'Save Up To 60% On Smart Accessories',
            'store_banner_subtitle' => 'Limited seasonal discount on all charging accessories and hubs.',
            'store_banner_cta_text' => 'Grab Savings',
            'store_banner_cta_link' => '#products-section',
            'store_banner_is_active' => true,
            'enable_google_login' => true,
            'google_client_id' => 'mock-google-client-id.apps.googleusercontent.com',
            'google_client_secret' => 'mock-secret',
            'storefront_payment_gateways' => [
                'cod' => ['enabled' => true, 'name' => 'Cash on Delivery', 'instructions' => 'Pay cash upon delivery.'],
                'stripe' => ['enabled' => true, 'name' => 'Credit / Debit Card', 'publishable_key' => 'pk_test_123', 'secret_key' => 'sk_test_123'],
                'razorpay' => ['enabled' => false],
            ],
        ]);

        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Accessories',
            'slug' => 'accessories',
        ]);

        $this->product = Product::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Magnetic Phone Mount',
            'sale_price' => 25.00,
            'price' => 25.00,
            'current_stock' => 100,
            'category_id' => $category->id,
            'category_name' => $category->name,
            'active' => true,
        ]);

        $this->customer = Customer::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Jane Techie',
            'email' => 'jane@gadgethub.test',
            'phone' => '+1555998877',
            'city' => 'San Francisco',
            'address' => '789 Market Street',
            'auth_token' => 'cust_tok_' . Str::random(32),
        ]);
    }

    public function test_storefront_renders_customized_promo_banner_when_active(): void
    {
        $response = $this->get('http://gadget-hub-test.saas.zoomnearby.com/');

        $response->assertStatus(200);
        $response->assertSee('WINTER GADGET BLOWOUT');
        $response->assertSee('Save Up To 60% On Smart Accessories');
        $response->assertSee('Grab Savings');
    }

    public function test_storefront_omits_promo_banner_cleanly_when_inactive(): void
    {
        $this->company->update(['store_banner_is_active' => false]);

        $response = $this->get('http://gadget-hub-test.saas.zoomnearby.com/');

        $response->assertStatus(200);
        $response->assertDontSee('WINTER GADGET BLOWOUT');
        $response->assertDontSee('Save Up To 60% On Smart Accessories');
    }

    public function test_storefront_payment_methods_api_returns_only_active_gateways(): void
    {
        $response = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->getJson('/api/v1/storefront/payment-methods');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $methods = $response->json('enabled_methods');
        $methodIds = array_column($methods, 'id');

        $this->assertContains('cod', $methodIds);
        $this->assertContains('stripe', $methodIds);
        $this->assertNotContains('razorpay', $methodIds);
    }

    public function test_coupon_validation_and_checkout_discount_application(): void
    {
        // 1. Create a 20% discount coupon
        $coupon = Coupon::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'code' => 'TECH20',
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
            'min_order_amount' => 30.00,
            'is_active' => true,
        ]);

        // 2. Validate below minimum order amount -> fails
        $responseLow = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->postJson('/store/coupons/validate', [
            'code' => 'TECH20',
            'subtotal' => 25.00,
        ]);
        $responseLow->assertStatus(422);
        $responseLow->assertJson([
            'success' => false,
            'valid' => false,
        ]);

        // 3. Validate meeting minimum order amount ($50) -> succeeds with $10 discount
        $responseOk = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->postJson('/store/coupons/validate', [
            'code' => 'TECH20',
            'subtotal' => 50.00,
        ]);
        $responseOk->assertStatus(200);
        $responseOk->assertJson([
            'success' => true,
            'valid' => true,
            'code' => 'TECH20',
            'discount_amount' => 10.00,
            'final_total' => 40.00,
        ]);

        // 4. Place order with coupon code and customer token
        $orderResponse = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->postJson('/store/order', [
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'customer_email' => $this->customer->email,
            'delivery_address' => '789 Market Street',
            'city' => 'San Francisco',
            'payment_method' => 'cod',
            'coupon_code' => 'TECH20',
            'discount' => 10.00,
            'auth_token' => $this->customer->auth_token,
            'items' => [
                [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'quantity' => 2,
                    'price' => 25.00,
                ],
            ],
        ]);

        $orderResponse->assertStatus(200);
        $orderResponse->assertJson([
            'success' => true,
            'discount' => 10.00,
            'net_amount' => 40.00,
        ]);
        $this->assertNotEmpty($orderResponse->json('tracking_code'));
        $this->assertNotEmpty($orderResponse->json('tracking_url'));

        // 5. Verify coupon usage was recorded in database
        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'customer_id' => $this->customer->id,
            'discount_amount' => 10.00,
        ]);
    }

    public function test_mandatory_customer_authentication_gate_at_checkout(): void
    {
        // When require_auth is set or strict guest token is missing
        $response = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->postJson('/store/order', [
            'customer_name' => 'Anonymous Visitor',
            'customer_phone' => '+1555000000',
            'delivery_address' => 'Unknown St',
            'city' => 'Anytown',
            'payment_method' => 'cod',
            'require_auth' => true,
            'items' => [
                [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'quantity' => 1,
                    'price' => 25.00,
                ],
            ],
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'auth_required' => true,
        ]);
    }

    public function test_google_social_login_redirect_url_generation(): void
    {
        $response = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->get('/store/auth/google/redirect');

        // Socialite redirects to accounts.google.com
        $response->assertStatus(302);
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_customer_account_portal_and_order_tracking_stepper_rendering(): void
    {
        // 1. Account portal renders
        $accountResp = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->get('/store/account');

        $accountResp->assertStatus(200);
        $accountResp->assertSee('Customer Account Portal');

        // 2. Create a sale with tracking code
        $sale = Sale::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'sale_number' => 'WEB-TRACK-001',
            'tracking_code' => 'TRK-' . strtoupper(Str::random(10)),
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'delivery_address' => '789 Market St',
            'delivery_city' => 'San Francisco',
            'status' => 'confirmed',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'subtotal' => 50.00,
            'total_amount' => 50.00,
            'total' => 50.00,
        ]);

        // 3. Track order endpoint renders with stepper
        $trackResp = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->get('/store/track/' . $sale->tracking_code);

        $trackResp->assertStatus(200);
        $trackResp->assertSee('WEB-TRACK-001');
        $trackResp->assertSee($sale->tracking_code);
        $trackResp->assertSee('Order Placed');
        $trackResp->assertSee('Confirmed');
        $trackResp->assertSee('Preparing Items');
        $trackResp->assertSee('Out for Delivery');
        $trackResp->assertSee('Delivered');
    }

    public function test_storefront_faqs_auto_seeding_and_api(): void
    {
        $response = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->getJson('/api/v1/storefront/faqs');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $faqs = $response->json('faqs');
        $this->assertNotEmpty($faqs);
        $this->assertDatabaseHas('faqs', [
            'company_id' => $this->company->id,
        ]);

        $categories = $response->json('categories');
        $this->assertContains('all', $categories);
    }

    public function test_customer_profile_update_supports_dob_gender_avatar(): void
    {
        $response = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->postJson('/api/v1/storefront/customer/profile', [
            'auth_token' => $this->customer->auth_token,
            'name' => 'Jane Techie Updated',
            'gender' => 'female',
            'date_of_birth' => '1995-06-15',
            'avatar_url' => 'https://example.com/avatar.png',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'customer' => [
                'name' => 'Jane Techie Updated',
                'gender' => 'female',
                'date_of_birth' => '1995-06-15',
                'avatar_url' => 'https://example.com/avatar.png',
            ],
        ]);

        $fresh = $this->customer->fresh();
        $this->assertEquals('Jane Techie Updated', $fresh->name);
        $this->assertEquals('female', $fresh->gender);
        $this->assertEquals('1995-06-15', $fresh->date_of_birth?->format('Y-m-d'));
        $this->assertEquals('https://example.com/avatar.png', $fresh->avatar_url);
    }

    public function test_storefront_payment_methods_fallback_to_platform_gateways(): void
    {
        // 1. Reset storefront_payment_gateways to null
        $this->company->update(['storefront_payment_gateways' => null]);

        // 2. Insert platform gateway in payment_gateway_settings
        \Illuminate\Support\Facades\DB::table('payment_gateway_settings')->updateOrInsert(
            ['gateway' => 'razorpay'],
            [
                'enabled' => 1,
                'mode' => 'sandbox',
                'public_key' => 'rzp_test_fallback123',
                'secret_key' => 'rzp_secret_fallback123',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->getJson('/api/v1/storefront/payment-methods');

        $response->assertStatus(200);
        $methods = $response->json('enabled_methods');
        $methodIds = array_column($methods, 'id');

        $this->assertContains('razorpay', $methodIds);
        $this->assertContains('cod', $methodIds);
    }

    public function test_tenant_navigation_and_permissions_include_coupons_and_faqs(): void
    {
        $storefrontSection = \App\Services\Navigation\TenantNavRegistry::getStorefrontSection($this->company);
        $tabKeys = array_column($storefrontSection['items'], 'component');

        $this->assertContains('settings_coupons', $tabKeys);
        $this->assertContains('settings_faqs', $tabKeys);
        $this->assertContains('settings_reviews', $tabKeys);

        $this->assertArrayHasKey('coupons', \App\Services\Auth\PermissionChecker::MODULES);
        $this->assertArrayHasKey('faqs', \App\Services\Auth\PermissionChecker::MODULES);
        $this->assertArrayHasKey('reviews', \App\Services\Auth\PermissionChecker::MODULES);
    }

    public function test_sdui_settings_coupons_and_faqs_views(): void
    {
        $couponsView = \App\Services\Sdui\SchemaResponse::couponsView($this->company);
        $this->assertEquals('screen', $couponsView['type']);
        $this->assertEquals('Coupons & Discounts', $couponsView['title']);

        $faqsView = \App\Services\Sdui\SchemaResponse::faqsView($this->company);
        $this->assertEquals('screen', $faqsView['type']);
        $this->assertEquals('Store FAQs & Help Center', $faqsView['title']);
    }

    public function test_product_reviews_submission_and_retrieval_when_enabled(): void
    {
        $this->company->update([
            'enable_product_reviews' => true,
            'require_review_approval' => false,
        ]);

        // 1. Submit review
        $response = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->postJson("/store/products/{$this->product->id}/reviews", [
            'rating' => 5,
            'title' => 'Exceptional Quality',
            'comment' => 'The magnetic mount holds my phone rock solid even on bumpy roads. Absolutely love it!',
            'customer_name' => 'Alex Gadgeteer',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'review' => [
                'rating' => 5,
                'title' => 'Exceptional Quality',
                'customer_name' => 'Alex Gadgeteer',
                'is_approved' => true,
            ],
        ]);

        $this->assertDatabaseHas('product_reviews', [
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'is_approved' => 1,
        ]);

        // 2. Fetch product reviews
        $listResponse = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->getJson("/store/products/{$this->product->id}/reviews");

        $listResponse->assertStatus(200);
        $listResponse->assertJson([
            'success' => true,
            'reviews_enabled' => true,
            'total_reviews' => 1,
            'average_rating' => 5.0,
        ]);
        $this->assertCount(1, $listResponse->json('reviews'));
        $this->assertEquals(1, $listResponse->json('rating_distribution.5'));
    }

    public function test_product_reviews_require_approval_moderation_flow(): void
    {
        $this->company->update([
            'enable_product_reviews' => true,
            'require_review_approval' => true,
        ]);

        // 1. Submit review under approval mode
        $response = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->postJson("/store/products/{$this->product->id}/reviews", [
            'rating' => 4,
            'title' => 'Good but bit tight',
            'comment' => 'Great mount, but the clamp is quite tight to install initially.',
            'customer_name' => 'Sam Reviewer',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'review' => [
                'is_approved' => false,
            ],
        ]);

        $reviewId = $response->json('review.id');
        $this->assertDatabaseHas('product_reviews', [
            'id' => $reviewId,
            'is_approved' => 0,
        ]);

        // 2. Public product reviews should not include unapproved reviews
        $listResponse = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->getJson("/store/products/{$this->product->id}/reviews");

        $listResponse->assertStatus(200);
        $this->assertEquals(0, $listResponse->json('total_reviews'));
        $this->assertEmpty($listResponse->json('reviews'));

        // 3. Admin approves the review
        ProductReview::find($reviewId)->update(['is_approved' => true]);

        // 4. Public endpoint now serves the approved review
        $listResponse2 = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->getJson("/store/products/{$this->product->id}/reviews");

        $listResponse2->assertStatus(200);
        $this->assertEquals(1, $listResponse2->json('total_reviews'));
        $this->assertEquals(4.0, $listResponse2->json('average_rating'));
    }

    public function test_product_reviews_submission_blocked_when_disabled_by_tenant(): void
    {
        $this->company->update([
            'enable_product_reviews' => false,
        ]);

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->postJson("/store/products/{$this->product->id}/reviews", [
            'rating' => 5,
            'comment' => 'Should be blocked because reviews are turned off.',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
        ]);

        // Product reviews list also indicates disabled
        $listResponse = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->getJson("/store/products/{$this->product->id}/reviews");

        $listResponse->assertStatus(200);
        $this->assertFalse($listResponse->json('reviews_enabled'));
    }

    public function test_verified_buyer_badge_assigned_when_customer_ordered_product(): void
    {
        $this->company->update([
            'enable_product_reviews' => true,
            'require_review_approval' => false,
        ]);

        // Create completed order for customer containing this product
        Sale::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'sale_number' => 'WEB-VERIFIED-01',
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'items' => [
                [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'quantity' => 1,
                    'price' => 25.00,
                ],
            ],
            'subtotal' => 25.00,
            'total_amount' => 25.00,
            'total' => 25.00,
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->postJson("/store/products/{$this->product->id}/reviews", [
            'rating' => 5,
            'comment' => 'Verified purchase review text.',
            'auth_token' => $this->customer->auth_token,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'review' => [
                'is_verified_purchase' => true,
            ],
        ]);

        $this->assertDatabaseHas('product_reviews', [
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'customer_id' => $this->customer->id,
            'is_verified_purchase' => 1,
        ]);
    }

    public function test_guest_checkout_with_stripe_payment_gateway(): void
    {
        $this->company->update([
            'storefront_payment_gateways' => [
                'stripe' => [
                    'enabled' => true,
                    'name' => 'Credit / Debit Card',
                    'publishable_key' => 'pk_test_sample_pub_12345',
                    'secret_key' => 'sk_test_sample_sec_12345',
                ],
            ],
        ]);

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->postJson('/store/order', [
            'customer_name' => 'Guest Shopper',
            'customer_phone' => '+1555443322',
            'customer_email' => 'shopper@example.com',
            'delivery_address' => '456 Elm St',
            'city' => 'San Francisco',
            'payment_method' => 'stripe',
            'items' => [
                [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'quantity' => 1,
                    'price' => 25.00,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'payment_method' => 'stripe',
        ]);
        $this->assertNotNull($response->json('gateway'));
        $this->assertEquals('stripe', $response->json('gateway.gateway'));
        $this->assertEquals('pk_test_sample_pub_12345', $response->json('gateway.key'));
    }

    public function test_guest_checkout_with_upi_payment_gateway(): void
    {
        $this->company->update([
            'storefront_payment_gateways' => [
                'upi' => [
                    'enabled' => true,
                    'name' => 'Instant UPI / QR',
                    'upi_id' => 'gadgethub@okaxis',
                    'merchant_name' => 'Gadget Hub Store',
                ],
            ],
        ]);

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->postJson('/store/order', [
            'customer_name' => 'Guest UPI User',
            'customer_phone' => '+1555776655',
            'delivery_address' => '101 Pine St',
            'city' => 'San Francisco',
            'payment_method' => 'upi',
            'items' => [
                [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'quantity' => 2,
                    'price' => 25.00,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'payment_method' => 'upi',
        ]);
        $gw = $response->json('gateway');
        $this->assertNotNull($gw);
        $this->assertEquals('gadgethub@okaxis', $gw['upi_id']);
        $this->assertStringContainsString('upi://pay?pa=gadgethub@okaxis', $gw['upi_link']);
        $this->assertNotEmpty($gw['upi_qr']);
    }

    public function test_storefront_payment_verification_marks_order_as_paid(): void
    {
        $sale = Sale::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'sale_number' => 'WEB-STRIPE-PAY-01',
            'tracking_code' => 'TRK-STRIPE-001',
            'customer_name' => 'Stripe Customer',
            'customer_phone' => '+1555888111',
            'status' => 'pending',
            'payment_method' => 'stripe',
            'payment_status' => 'pending',
            'subtotal' => 25.00,
            'net_amount' => 25.00,
            'total_amount' => 25.00,
            'total' => 25.00,
            'paid_amount' => 0.00,
            'due_amount' => 25.00,
            'items' => [],
        ]);

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->postJson('/store/payment/verify', [
            'sale_id' => $sale->id,
            'gateway' => 'stripe',
            'payment_id' => 'ch_stripe_verified_123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $sale->refresh();
        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals('confirmed', $sale->status);
        $this->assertEquals(25.00, (float) $sale->paid_amount);
        $this->assertEquals(0.00, (float) $sale->due_amount);
    }

    public function test_guest_checkout_with_coupon_and_invalid_coupon_rejection(): void
    {
        $coupon = Coupon::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'code' => 'WELCOME15',
            'discount_type' => 'percentage',
            'discount_value' => 15.00,
            'min_order_amount' => 20.00,
            'is_active' => true,
        ]);

        // 1. Invalid coupon validation gives 422 with clean error
        $invalidResp = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->postJson('/store/coupons/validate', [
            'code' => 'NONEXISTENT',
            'subtotal' => 50.00,
        ]);
        $invalidResp->assertStatus(422);
        $invalidResp->assertJson([
            'success' => false,
            'valid' => false,
        ]);
        $this->assertNotEmpty($invalidResp->json('message'));

        // 2. Valid coupon works for guest checkout without requiring auth_token
        $orderResp = $this->withServerVariables([
            'HTTP_HOST' => 'gadget-hub-test.saas.zoomnearby.com',
        ])->postJson('/store/order', [
            'customer_name' => 'Guest Coupon User',
            'customer_phone' => '+1555333444',
            'delivery_address' => '999 Willow Ave',
            'city' => 'San Francisco',
            'payment_method' => 'cod',
            'coupon_code' => 'WELCOME15',
            'discount' => 7.50,
            'items' => [
                [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'quantity' => 2,
                    'price' => 25.00,
                ],
            ],
        ]);

        $orderResp->assertStatus(200);
        $orderResp->assertJson([
            'success' => true,
            'discount' => 7.50,
            'net_amount' => 42.50,
        ]);

        // Coupon usage recorded even for guest
        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'discount_amount' => 7.50,
        ]);
    }

    public function test_tenant_settings_saves_product_reviews_toggles(): void
    {
        $user = User::create([
            'company_id' => $this->company->id,
            'name' => 'Admin User',
            'login' => 'admin_test',
            'email' => 'admin@gadgethub.test',
            'password' => Hash::make('password123'),
            'role' => 'administrator',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        app()->instance('tenant.company_id', $this->company->id);

        \Livewire\Livewire::actingAs($user, 'web')
            ->test(\App\Livewire\Tenant\Settings\Index::class)
            ->set('enableProductReviews', false)
            ->set('requireReviewApproval', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->company->refresh();
        $this->assertFalse((bool) $this->company->enable_product_reviews);
        $this->assertTrue((bool) $this->company->require_review_approval);

        // Turn back on
        \Livewire\Livewire::actingAs($user, 'web')
            ->test(\App\Livewire\Tenant\Settings\Index::class)
            ->set('enableProductReviews', true)
            ->set('requireReviewApproval', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->company->refresh();
        $this->assertTrue((bool) $this->company->enable_product_reviews);
        $this->assertFalse((bool) $this->company->require_review_approval);
    }
}
