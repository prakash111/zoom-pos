<?php

namespace Tests\Feature\Subscription;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\TenantSession;
use App\Models\User;
use App\Services\Subscription\SubscriptionEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class EntitlementAndStorefrontTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_entitlement_service_resolves_limits_and_remaining_quota(): void
    {
        $plan = Plan::create([
            'name' => 'starter',
            'display_name' => 'Starter Plan',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'price' => 19.00,
            'currency' => 'USD',
            'active' => true,
            'invoice_limit' => 5,
            'device_limit' => 2,
            'staff_limit' => 3,
            'extensions' => ['leadmanagement'],
            'features' => ['barcode_scanner' => true, 'receipt_printing' => true],
        ]);

        $company = Company::create([
            'name' => 'Entitlement Test Store',
            'slug' => 'entitlement-store',
            'plan_name' => 'starter',
        ]);

        $service = app(SubscriptionEntitlementService::class);

        $this->assertEquals(5, $service->tenantFeatureLimit($company, 'invoice_limit'));
        $this->assertEquals(2, $service->tenantFeatureLimit($company, 'device_limit'));
        $this->assertEquals(3, $service->tenantFeatureLimit($company, 'staff_limit'));
        $this->assertTrue($service->tenantCanUseExtension($company, 'leadmanagement'));
        $this->assertTrue($service->tenantHasFeature($company, 'leadmanagement'));
        $this->assertTrue($service->tenantHasFeature($company, 'barcode_scanner'));
        $this->assertTrue($service->canCreateInvoice($company));

        // Create 5 invoices to reach limit
        for ($i = 1; $i <= 5; $i++) {
            Sale::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'sale_number' => "INV-00{$i}",
                'total' => 100,
                'status' => 'completed',
            ]);
        }

        $this->assertEquals(0, $service->tenantLimitRemaining($company, 'invoice_limit'));
        $this->assertFalse($service->canCreateInvoice($company));
    }

    public function test_pricing_plans_public_endpoints(): void
    {
        Plan::create([
            'name' => 'growth',
            'display_name' => 'Growth Plan',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'price' => 49.00,
            'currency' => 'USD',
            'active' => true,
            'invoice_limit' => 500,
            'device_limit' => 5,
            'staff_limit' => 10,
            'extensions' => ['leadmanagement', 'whatsapp_api'],
            'features' => ['cloud_backup' => true],
        ]);

        $resList = $this->getJson('/api/pricing-plans');
        $resList->assertOk()
            ->assertJsonPath('success', true);

        $plans = $resList->json('plans');
        $this->assertNotEmpty($plans);

        $resSingle = $this->getJson('/api/pricing-plans/growth');
        $resSingle->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('plan.name', 'Growth Plan')
            ->assertJsonPath('plan.invoice_limit', 500);
    }

    public function test_storefront_customer_registration_and_authentication_flow(): void
    {
        $company = Company::create([
            'name' => 'Storefront Test Shop',
            'slug' => 'test-shop',
        ]);

        // 1. Register Customer
        $regResponse = $this->postJson('/api/storefront/customer/register', [
            'store' => 'test-shop',
            'name' => 'Alice Customer',
            'email' => 'alice@example.com',
            'phone' => '+1555123456',
            'password' => 'secret123',
            'address' => '123 Market St',
            'city' => 'Metropolis',
            'state' => 'NY',
            'postal_code' => '10001',
        ]);

        $regResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token', 'customer']);

        $token = $regResponse->json('token');

        // 2. Login Customer
        $loginResponse = $this->postJson('/api/storefront/customer/login', [
            'store' => 'test-shop',
            'email' => 'alice@example.com',
            'password' => 'secret123',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token', 'customer']);

        $loginToken = $loginResponse->json('token');

        // 3. Customer Profile
        $profileResponse = $this->getJson('/api/storefront/customer/profile?store=test-shop', [
            'Authorization' => "Bearer {$loginToken}",
        ]);

        $profileResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('customer.email', 'alice@example.com')
            ->assertJsonPath('customer.name', 'Alice Customer');

        // 4. Customer Addresses
        $addrResponse = $this->getJson('/api/storefront/customer/addresses?store=test-shop', [
            'Authorization' => "Bearer {$loginToken}",
        ]);

        $addrResponse->assertOk()
            ->assertJsonPath('success', true);
        $this->assertCount(1, $addrResponse->json('addresses'));

        // 5. Customer Wishlist Toggle
        $product = Product::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Test Wireless Mouse',
            'price' => 25.00,
            'sale_price' => 22.00,
            'active' => true,
        ]);

        $wishToggle = $this->postJson('/api/storefront/customer/wishlist/toggle', [
            'store' => 'test-shop',
            'product_id' => $product->id,
        ], [
            'Authorization' => "Bearer {$loginToken}",
        ]);

        $wishToggle->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('in_wishlist', true);

        $wishList = $this->getJson('/api/storefront/customer/wishlist?store=test-shop', [
            'Authorization' => "Bearer {$loginToken}",
        ]);

        $wishList->assertOk()
            ->assertJsonPath('success', true);
        $this->assertCount(1, $wishList->json('wishlist'));
        $this->assertEquals($product->id, $wishList->json('wishlist.0.id'));

        // 6. Authoritative Cart Calculation
        $calcResponse = $this->postJson('/api/storefront/cart/calculate', [
            'store' => 'test-shop',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $calcResponse->assertOk()
            ->assertJsonPath('success', true);
        $this->assertEquals(44.0, (float) $calcResponse->json('subtotal'));
        $this->assertEquals(44.0, (float) $calcResponse->json('grand_total'));

        // 7. Customer Logout
        $logoutResponse = $this->postJson('/api/storefront/customer/logout?store=test-shop', [], [
            'Authorization' => "Bearer {$loginToken}",
        ]);

        $logoutResponse->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_invoice_limit_and_staff_limit_enforcement_via_api(): void
    {
        Plan::create([
            'name' => 'micro',
            'display_name' => 'Micro Plan',
            'billing_cycle' => 'monthly',
            'price' => 5.00,
            'currency' => 'USD',
            'active' => true,
            'invoice_limit' => 1,
            'staff_limit' => 1,
            'device_limit' => 1,
            'extensions' => [],
        ]);

        $company = Company::create([
            'name' => 'Micro Tenant Store',
            'slug' => 'micro-store',
            'plan_name' => 'micro',
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Admin User',
            'login' => 'admin_micro',
            'email' => 'admin@micro.test',
            'password' => Hash::make('password123'),
            'role' => 'administrator',
            'status' => 'approved',
        ]);

        $token = $company->id . '.' . bin2hex(random_bytes(32));
        TenantSession::create([
            'token' => $token,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'expires_at' => now()->addDay(),
            'revoked' => false,
        ]);

        // 1. Staff limit enforcement (already 1 user in DB, max is 1)
        $staffRes = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Company-ID' => $company->id,
        ])->postJson('/api/v1/pos/users/invite', [
            'name' => 'Second Staff',
            'email' => 'second@micro.test',
            'role' => 'cashier',
            'commission_type' => 'percentage',
            'commission_rate' => 0,
        ]);

        $staffRes->assertStatus(403)
            ->assertJsonPath('success', false);

        // 2. First invoice allowed
        Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'sale_number' => 'INV-001',
            'total' => 50,
            'status' => 'completed',
        ]);

        // 3. Second invoice blocked by entitlement service
        $entitlementService = app(SubscriptionEntitlementService::class);
        $this->assertFalse($entitlementService->canCreateInvoice($company));
        $this->assertEquals(0, $entitlementService->tenantLimitRemaining($company, 'invoice_limit'));
    }
}

