<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlatformBranding;
use App\Models\SaaSPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPricingSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed or create the three standard plans matching production
        Plan::create([
            'name' => 'trial',
            'display_name' => 'Trial',
            'billing_cycle' => 'trial',
            'duration_days' => 14,
            'price' => 0.00,
            'currency' => 'USD',
            'features' => [
                'quotations' => true,
                'online_store' => true,
                'cash_register' => true,
                'thermal_printing' => true,
            ],
            'limits' => [
                'filiais' => 1,
                'invoices' => 50,
                'products' => 100,
                'usuarios' => 2,
                'dispositivos' => 1,
            ],
            'invoice_limit' => 50,
            'products_limit' => 100,
            'device_limit' => 1,
            'staff_limit' => 2,
            'extensions' => ['leadmanagement'],
            'active' => true,
        ]);

        Plan::create([
            'name' => 'starter',
            'display_name' => 'Starter',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'price' => 19.00,
            'currency' => 'USD',
            'features' => [
                'api_access' => true,
                'quotations' => true,
                'consignments' => true,
                'customer_crm' => true,
                'online_store' => true,
                'cash_register' => true,
                'multi_location' => false,
                'automatic_backup' => false,
                'thermal_printing' => true,
                'analytics_reports' => true,
            ],
            'limits' => [
                'filiais' => 1,
                'invoices' => 500,
                'products' => 1000,
                'usuarios' => 3,
                'dispositivos' => 2,
            ],
            'invoice_limit' => 500,
            'products_limit' => 1000,
            'device_limit' => 2,
            'staff_limit' => 3,
            'extensions' => ['leadmanagement'],
            'active' => true,
        ]);

        Plan::create([
            'name' => 'professional',
            'display_name' => 'Professional',
            'billing_cycle' => 'yearly',
            'duration_days' => 365,
            'price' => 199.00,
            'currency' => 'USD',
            'features' => [
                'api_access' => true,
                'quotations' => true,
                'consignments' => true,
                'customer_crm' => true,
                'online_store' => true,
                'cash_register' => true,
                'multi_location' => true,
                'restaurant_mode' => true,
                'automatic_backup' => true,
                'thermal_printing' => true,
                'analytics_reports' => true,
            ],
            'limits' => [
                'filiais' => 5,
                'invoices' => -1,
                'products' => -1,
                'usuarios' => -1,
                'dispositivos' => -1,
            ],
            'invoice_limit' => -1,
            'products_limit' => -1,
            'device_limit' => -1,
            'staff_limit' => -1,
            'extensions' => ['leadmanagement', 'whatsapp_api', 'custom_domain'],
            'active' => true,
        ]);

        PlatformBranding::current()->update([
            'landing_page_enabled' => true,
        ]);
    }

    public function test_plan_model_accessors_format_limits_and_features(): void
    {
        $starter = SaaSPlan::find('starter');
        $this->assertSame(500, $starter->invoice_limit);
        $this->assertSame(1000, $starter->product_limit);
        $this->assertSame(2, $starter->device_limit);
        $this->assertSame(3, $starter->staff_limit);
        $this->assertSame(['leadmanagement'], $starter->enabled_extensions);
        $this->assertContains('Quotations', $starter->feature_list);
        $this->assertContains('Online store', $starter->feature_list);
        $this->assertContains('Cash register', $starter->feature_list);
        $this->assertNotContains('Multi location', $starter->feature_list);

        $pro = SaaSPlan::find('professional');
        $this->assertSame(-1, $pro->invoice_limit);
        $this->assertSame(-1, $pro->product_limit);
        $this->assertSame(-1, $pro->device_limit);
        $this->assertSame(-1, $pro->staff_limit);
        $this->assertContains('leadmanagement', $pro->enabled_extensions);
        $this->assertContains('whatsapp_api', $pro->enabled_extensions);
        $this->assertContains('custom_domain', $pro->enabled_extensions);
        $this->assertContains('Multi location', $pro->feature_list);
        $this->assertContains('Restaurant mode', $pro->feature_list);
        $this->assertContains('Automatic backup', $pro->feature_list);
    }

    public function test_pricing_view_renders_rich_badges_and_limits_synchronized_with_flutter(): void
    {
        $plans = SaaSPlan::where('active', true)->orderBy('price')->get();
        $branding = PlatformBranding::current();

        $html = view('landing.pricing', [
            'plans' => $plans,
            'branding' => $branding,
        ])->render();

        // 1. Numerical limits rendered as blue pill boxes (with -1 formatted as Unlimited)
        $this->assertStringContainsString('Unlimited Invoices', $html);
        $this->assertStringContainsString('Unlimited Products', $html);
        $this->assertStringContainsString('Unlimited POS Devices', $html);
        $this->assertStringContainsString('Unlimited Staff', $html);
        $this->assertStringContainsString('50 Invoices/mo', $html);
        $this->assertStringContainsString('100 Products', $html);
        $this->assertStringContainsString('1 Devices', $html);
        $this->assertStringContainsString('2 Staff', $html);
        $this->assertStringContainsString('500 Invoices/mo', $html);
        $this->assertStringContainsString('1000 Products', $html);
        $this->assertStringContainsString('2 Devices', $html);
        $this->assertStringContainsString('3 Staff', $html);

        // 2. Modular Extension Badges
        $this->assertStringContainsString('CRM &amp; Leads', $html);
        $this->assertStringContainsString('WhatsApp API', $html);
        $this->assertStringContainsString('Custom Domain', $html);

        // 3. Feature checklist with checkmark icons
        $this->assertStringContainsString('Quotations', $html);
        $this->assertStringContainsString('Online store', $html);
        $this->assertStringContainsString('Cash register', $html);
        $this->assertStringContainsString('Thermal printing', $html);
        $this->assertStringContainsString('Multi location', $html);
        $this->assertStringContainsString('Restaurant mode', $html);
        $this->assertStringContainsString('Automatic backup', $html);
        $this->assertStringContainsString('Analytics reports', $html);

        // 4. Card styling and CTA button
        $this->assertStringContainsString('bg-[#101726]', $html);
        $this->assertStringContainsString('border-slate-800', $html);
        $this->assertStringContainsString('bg-[#1E293B]', $html);
        $this->assertStringContainsString('Select Plan', $html);
        $this->assertStringContainsString('MOST POPULAR', $html);
    }

    public function test_landing_page_renders_synchronized_pricing_cards(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        // Verify key synchronized pricing elements appear on the landing page
        $response->assertSee('Unlimited Invoices');
        $response->assertSee('Unlimited Products');
        $response->assertSee('Unlimited POS Devices');
        $response->assertSee('Unlimited Staff');
        $response->assertSee('CRM &amp; Leads', false);
        $response->assertSee('WhatsApp API');
        $response->assertSee('Custom Domain');
        $response->assertSee('Select Plan');
        $response->assertSee('MOST POPULAR');
        $response->assertSee('bg-[#101726]', false);
    }

    public function test_pricing_plan_extension_badges_have_refined_padding_and_spacing(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        // Verify comfortable padding, tracking, and styling on extension pills
        $response->assertSee('px-2.5 py-1 text-xs font-semibold rounded-md border border-amber-500/30 bg-amber-500/10 text-amber-300 tracking-wide', false);
        // Verify numerical limits container has comfortable margins
        $response->assertSee('grid grid-cols-2 gap-2 mt-4 mb-3', false);
        // Verify extension badges container spacing
        $response->assertSee('flex flex-wrap items-center gap-2 my-3', false);
    }

    public function test_authenticated_platform_admin_can_view_landing_page_without_redirect(): void
    {
        $admin = \App\Models\PlatformAdmin::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@zoompos.test',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $this->actingAs($admin, 'platform_web');

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('SuperAdmin');
        $response->assertSee(url('/superadmin'));
        $response->assertDontSee('Sign in');
    }

    public function test_authenticated_tenant_user_can_view_landing_page_without_redirect(): void
    {
        $company = \App\Models\Company::create([
            'name' => 'Acme Mart',
            'status' => 'active',
            'pos_mode' => 'general',
        ]);
        $user = \App\Models\User::create([
            'company_id' => $company->id,
            'name' => 'Jane Tenant',
            'login' => 'janetenant',
            'email' => 'jane@acmemart.test',
            'password' => \Illuminate\Support\Facades\Hash::make('secret1234'),
            'role' => 'administrator',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user, 'web');

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Go to Dashboard');
        $response->assertSee(url('/tenant'));
        $response->assertDontSee('Sign in');
    }

    public function test_section_theme_tokens_are_isolated_per_section_slug(): void
    {
        $admin = \App\Models\PlatformAdmin::create([
            'name' => 'Super Admin 2',
            'email' => 'superadmin2@zoompos.test',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $this->actingAs($admin, 'platform_web');

        $defaultPalette = default_landing_sections_palette();
        $customPalette = $defaultPalette;
        $customPalette['hero_showcase'] = [
            'dark_bg' => '#123456',
            'light_bg' => '#f12345',
            'dark_text' => '#ffffff',
            'light_text' => '#000000',
            'dark_muted' => '#999999',
            'light_muted' => '#666666',
        ];

        $response = $this->post(route('superadmin.theme.customizer.sections'), [
            'palette' => $customPalette,
        ]);
        $response->assertRedirect();

        $tokens = get_landing_theme_tokens();

        // Hero showcase updated
        $this->assertSame('#123456', $tokens['hero_showcase']['dark_bg']);
        $this->assertSame('#123456', $tokens['hero']['dark_bg']);
        $this->assertSame('#123456', $tokens['hero_showcase']['bg_dark']);

        // Pricing, features, and mission remain strictly isolated at their defaults
        $this->assertSame($defaultPalette['pricing']['dark_bg'], $tokens['pricing_plans']['dark_bg']);
        $this->assertSame($defaultPalette['pricing']['dark_bg'], $tokens['pricing']['dark_bg']);
        $this->assertSame($defaultPalette['features']['dark_bg'], $tokens['retail_features']['dark_bg']);
        $this->assertSame($defaultPalette['mission']['dark_bg'], $tokens['our_mission']['dark_bg']);
        $this->assertNotSame('#123456', $tokens['pricing_plans']['dark_bg']);
    }
}
