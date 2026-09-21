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
}
