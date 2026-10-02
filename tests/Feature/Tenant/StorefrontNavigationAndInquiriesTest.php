<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Storefront\Inquiries;
use App\Models\Company;
use App\Models\Plan;
use App\Models\TenantInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class StorefrontNavigationAndInquiriesTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');

        $plan = Plan::create([
            'name' => 'pro-plan',
            'display_name' => 'Pro Plan',
            'price' => 29.00,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'store_limit' => 5,
            'active' => true,
            'features' => ['ecommerce_storefront' => true],
            'extensions' => ['ecommerce_storefront'],
        ]);

        $this->company = Company::create([
            'name' => 'Acme Store',
            'slug' => 'acme-store',
            'status' => 'active',
            'pos_mode' => 'retail',
            'plan_name' => 'pro-plan',
            'plan_id' => $plan->id,
            'licensed_modules' => ['retail', 'ecommerce_storefront'],
            'currency_symbol' => '$',
            'expires_at' => now()->addYear(),
        ]);

        $this->user = User::create([
            'company_id' => $this->company->id,
            'name' => 'Owner User',
            'login' => 'owner',
            'email' => 'owner@acme.com',
            'password' => Hash::make('secret1234'),
            'role' => 'administrator',
            'status' => 'approved',
        ]);

        $this->actingAs($this->user, 'web');
        app()->instance('tenant.company_id', $this->company->id);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_storefront_section_is_separated_in_expanded_sidebar_with_all_9_items(): void
    {
        $response = $this->get(route('tenant.dashboard'));
        $response->assertOk();

        // 1. Separate section heading exists
        $response->assertSee('Storefront & Online Sales');

        // 2. All 9 items exist in the response
        $response->assertSee('View Live Store');
        $response->assertSee('Store Web Address & Domain');
        $response->assertSee('Store Menus & CMS Pages');
        $response->assertSee('Online Store Inquiries');
        $response->assertSee('Storefront Banner & Auth');
        $response->assertSee('Storefront Payment Gateways');
        $response->assertSee('Coupons & Discounts');
        $response->assertSee('Store FAQs & Help Center');
        $response->assertSee('Product Ratings & Reviews');

        // 3. View Live Store links to live store URL
        $liveUrl = $this->company->getStorefrontUrl();
        $response->assertSee($liveUrl, false);
    }

    public function test_storefront_section_is_rendered_in_drawer_navigation_with_item_keys(): void
    {
        $response = $this->get(route('tenant.dashboard'));
        $response->assertOk();

        // 1. Dedicated drawer section key
        $response->assertSee('data-section-key="sec_storefront"', false);

        // 2. Drawer item keys matching Flutter & registry contract
        $response->assertSee('data-item-key="nav_view_live_store"', false);
        $response->assertSee('data-item-key="nav_storefront_domain"', false);
        $response->assertSee('data-item-key="nav_storefront_menus"', false);
        $response->assertSee('data-item-key="nav_storefront_inquiries"', false);
        $response->assertSee('data-item-key="nav_storefront_banner_auth"', false);
        $response->assertSee('data-item-key="nav_storefront_gateways"', false);
        $response->assertSee('data-item-key="nav_coupons_discounts"', false);
        $response->assertSee('data-item-key="nav_store_faqs"', false);
        $response->assertSee('data-item-key="nav_store_reviews"', false);
    }

    public function test_storefront_rail_item_is_rendered_in_slim_mode(): void
    {
        $response = $this->get(route('tenant.dashboard'));
        $response->assertOk();

        $response->assertSee('data-item-key="nav_storefront_group"', false);
    }

    public function test_storefront_inquiries_livewire_component_functions_correctly(): void
    {
        // Seed inquiries
        $inquiry1 = TenantInquiry::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Alice Customer',
            'email' => 'alice@example.com',
            'phone' => '+1234567890',
            'subject' => 'Product Availability Question',
            'message' => 'Is the blue shirt available in size M?',
            'status' => 'unread',
        ]);

        $inquiry2 = TenantInquiry::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Bob Smith',
            'email' => 'bob@example.com',
            'phone' => '+1987654321',
            'subject' => 'Bulk Order Inquiry',
            'message' => 'Do you provide discounts for orders over 100 pieces?',
            'status' => 'contacted',
        ]);

        Livewire::test(Inquiries::class)
            ->assertSee('Alice Customer')
            ->assertSee('Bob Smith')
            ->assertSee('Product Availability Question')
            ->assertSee('Bulk Order Inquiry')
            // Test status filter
            ->call('setFilter', 'unread')
            ->assertSee('Alice Customer')
            ->assertDontSee('Bob Smith')
            ->call('setFilter', 'contacted')
            ->assertSee('Bob Smith')
            ->assertDontSee('Alice Customer')
            ->call('setFilter', 'all')
            ->assertSee('Alice Customer')
            ->assertSee('Bob Smith')
            // Test updating status
            ->call('updateStatus', $inquiry1->id, 'contacted')
            ->assertDispatched('toast');

        $this->assertEquals('contacted', $inquiry1->fresh()->status);

        // Test view detail modal
        Livewire::test(Inquiries::class)
            ->call('viewInquiry', $inquiry1->id)
            ->assertSet('showDetailModal', true)
            ->assertSet('viewingInquiryId', $inquiry1->id)
            ->call('closeModal')
            ->assertSet('showDetailModal', false);

        // Test delete
        Livewire::test(Inquiries::class)
            ->call('deleteInquiry', $inquiry1->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('tenant_inquiries', ['id' => $inquiry1->id]);
    }

    public function test_storefront_routes_and_aliases_respond_ok(): void
    {
        $this->get(route('tenant.storefront.inquiries'))->assertOk();
        $this->get(route('tenant.storefront.menus'))->assertOk();
        $this->get(route('tenant.settings.storefront'))->assertOk();
        $this->get(route('tenant.settings.storefront.banner'))->assertOk();
        $this->get(route('tenant.settings.storefront.domain'))->assertOk();
        $this->get(route('tenant.settings.storefront.payments'))->assertOk();
        $this->get(route('tenant.settings.storefront.coupons'))->assertOk();
        $this->get(route('tenant.settings.storefront.faqs'))->assertOk();
        $this->get(route('tenant.settings.storefront.reviews'))->assertOk();
    }
}
