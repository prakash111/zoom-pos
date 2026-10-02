<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlatformBranding;
use App\Models\SaaSPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppBuilderAndNotificationDocsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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
                'online_store' => true,
                'cash_register' => true,
                'app_builder_access' => true,
                'white_label_custom_branding' => true,
                'android_web_and_windows_builds' => true,
                'cloud_build_history_and_alerts' => true,
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
            'extensions' => ['leadmanagement'],
            'active' => true,
        ]);

        PlatformBranding::current()->update([
            'landing_page_enabled' => true,
        ]);
    }

    public function test_pricing_view_renders_app_builder_features_and_showcase(): void
    {
        $plans = SaaSPlan::where('active', true)->orderBy('price')->get();
        $branding = PlatformBranding::current();

        $html = view('landing.pricing', [
            'plans' => $plans,
            'branding' => $branding,
        ])->render();

        // 1. App Builder inclusion pill on Professional plan
        $this->assertStringContainsString('Cloud App Builder Included', $html);
        $this->assertStringContainsString('Android • Web • Windows', $html);

        // 2. White-Label Cloud App Builder showcase is managed centrally via License Manager & /marketing
        $this->assertStringNotContainsString('White-Label Cloud App Builder', $html);
    }

    public function test_documentation_portal_contains_app_builder_and_whatsapp_guides(): void
    {
        $docPath = public_path('documentation/index.html');
        $this->assertFileExists($docPath);

        $content = file_get_contents($docPath);

        // Navigation links
        $this->assertStringContainsString('href="#app-builder-guide-and-eligibility"', $content);
        $this->assertStringContainsString('href="#notification-channels-and-whatsapp-api-guide"', $content);

        // Chapter 17: App Builder & Eligibility
        $this->assertStringContainsString('id="app-builder-guide-and-eligibility"', $content);
        $this->assertStringContainsString('Who is Eligible for the App Builder?', $content);
        $this->assertStringContainsString('Core SaaS Script (Regular License)', $content);
        $this->assertStringContainsString('Standalone Modules &amp; Add-on Plugins', $content);
        $this->assertStringContainsString('NOT ELIGIBLE', $content);
        $this->assertStringContainsString('How to Build Your Branded App', $content);
        $this->assertStringContainsString('Download Artifacts &amp; Automatic Email Notification', $content);

        // Chapter 18: Notification Channels & WhatsApp API Integration Guide
        $this->assertStringContainsString('id="notification-channels-and-whatsapp-api-guide"', $content);
        $this->assertStringContainsString('Official Meta WhatsApp Cloud API — Complete Integration Guide', $content);
        $this->assertStringContainsString('Permanent System User Access Token', $content);
        $this->assertStringContainsString('whatsapp_business_messaging', $content);
        $this->assertStringContainsString('Phone Number ID', $content);
        $this->assertStringContainsString('WhatsApp Business Account ID (WABA ID)', $content);
        $this->assertStringContainsString('Twilio WhatsApp API', $content);
        $this->assertStringContainsString('MSG91 (India DLT)', $content);
        $this->assertStringContainsString('Custom SMTP Mail Server', $content);
        $this->assertStringContainsString('Automated Dispatch Triggers &amp; Operational Events', $content);
    }

    public function test_standalone_markdown_guides_exist_with_complete_technical_details(): void
    {
        $builderGuidePath = public_path('documentation/APP_BUILDER_GUIDE.md');
        $this->assertFileExists($builderGuidePath);
        $builderContent = file_get_contents($builderGuidePath);
        $this->assertStringContainsString('Who is Eligible for the App Builder?', $builderContent);
        $this->assertStringContainsString('Core SaaS Script Licenses', $builderContent);
        $this->assertStringContainsString('Standalone Modules & Plugins', $builderContent);
        $this->assertStringContainsString('NOT ELIGIBLE', $builderContent);

        $whatsappGuidePath = public_path('documentation/NOTIFICATION_AND_WHATSAPP_INTEGRATION_GUIDE.md');
        $this->assertFileExists($whatsappGuidePath);
        $whatsappContent = file_get_contents($whatsappGuidePath);
        $this->assertStringContainsString('Official Meta WhatsApp Cloud API (Recommended)', $whatsappContent);
        $this->assertStringContainsString('Permanent System User Access Token', $whatsappContent);
        $this->assertStringContainsString('Phone Number ID', $whatsappContent);
        $this->assertStringContainsString('Twilio SMS', $whatsappContent);
        $this->assertStringContainsString('MSG91 (India DLT)', $whatsappContent);
        $this->assertStringContainsString('Custom SMTP Mail Server', $whatsappContent);

        // ZoomNearby SMS Gateway specification per https://sms.zoomnearby.com/docs/api#authenticating-requests
        $this->assertStringContainsString('https://sms.zoomnearby.com/docs/api#authenticating-requests', $whatsappContent);
        $this->assertStringContainsString('https://sms.zoomnearby.com/api/v1/messages/send', $whatsappContent);
        $this->assertStringContainsString('Authorization: Bearer', $whatsappContent);
        $this->assertStringContainsString('"mobile_numbers"', $whatsappContent);
    }

    public function test_tenant_integrations_view_and_html_docs_link_to_zoomnearby_sms_api_guide(): void
    {
        // 1. Blade integrations view
        $viewContent = file_get_contents(resource_path('views/tenant/settings/integrations.blade.php'));
        $this->assertStringContainsString('https://sms.zoomnearby.com/docs/api#authenticating-requests', $viewContent);
        $this->assertStringContainsString('https://sms.zoomnearby.com/api/v1/messages/send', $viewContent);
        $this->assertStringContainsString('ZoomNearby SMS Gateway', $viewContent);

        // 2. HTML documentation portal
        $htmlDoc = file_get_contents(public_path('documentation/index.html'));
        $this->assertStringContainsString('https://sms.zoomnearby.com/docs/api#authenticating-requests', $htmlDoc);
        $this->assertStringContainsString('https://sms.zoomnearby.com/api/v1/messages/send', $htmlDoc);
        $this->assertStringContainsString('ZoomNearby SMS Gateway', $htmlDoc);
        $this->assertStringContainsString('ZoomNearby SMS Gateway API Integration Guide', $htmlDoc);
    }

    public function test_sms_gateway_service_sends_payload_according_to_zoomnearby_sms_api_spec(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://sms.zoomnearby.com/api/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
                'status'  => 'success',
                'message' => 'Message queued successfully',
            ], 200),
        ]);

        $company = \App\Models\Company::create([
            'name' => 'SMS Gateway Test Store',
            'trade_name' => 'SMS Gateway Store',
            'slug' => 'sms-gateway-store',
            'email' => 'sms-test@example.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'plan_name' => 'starter',
            'expires_at' => now()->addMonth(),
        ]);

        // Configure tenant notification gateway with test credentials
        \App\Models\TenantNotificationGateway::create([
            'company_id'  => $company->id,
            'channel'     => \App\Models\TenantNotificationGateway::CHANNEL_SMS,
            'is_active'   => true,
            'credentials' => [
                'provider'  => 'generic_http',
                'url'       => 'https://sms.zoomnearby.com/api/v1/messages/send',
                'method'    => 'POST',
                'api_token' => 'test-bearer-token-12345',
            ],
        ]);

        $result = \App\Services\SmsGatewayService::send('+12025550199', 'Your verification code is 482910', $company->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status']);

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            $hasAuthBearer = $request->hasHeader('Authorization', 'Bearer test-bearer-token-12345');
            $isCorrectUrl = $request->url() === 'https://sms.zoomnearby.com/api/v1/messages/send';
            $data = $request->data();

            return $hasAuthBearer
                && $isCorrectUrl
                && isset($data['mobile_numbers']) && $data['mobile_numbers'] === ['+12025550199']
                && isset($data['type']) && $data['type'] === 'SMS'
                && isset($data['message']) && $data['message'] === 'Your verification code is 482910'
                && isset($data['sims']) && $data['sims'] === ['*'];
        });
    }

    public function test_builder_documentation_url_and_index_clean_redirect(): void
    {
        $indexContent = file_get_contents(base_path('lic/index.php'));
        $this->assertStringNotContainsString('ZoomNearby Central Platform', $indexContent);
        $this->assertStringNotContainsString('PRODUCTION CENTRAL ENGINE', $indexContent);
        $this->assertStringNotContainsString('cards-grid', $indexContent);
        $this->assertStringNotContainsString('portal-card', $indexContent);
        $this->assertStringContainsString('buy.php', $indexContent);
        $this->assertStringContainsString('str_contains($uri, \'/app-builder\')', $indexContent);

        $layoutContent = file_get_contents(base_path('lic/app-builder/lib/layout.php'));
        $this->assertStringContainsString('function builder_documentation_info()', $layoutContent);
        $this->assertStringContainsString('documentation_url', $layoutContent);

        $process = new \Symfony\Component\Process\Process(['php', '-r', '
            require_once "lic/app-builder/lib/bootstrap.php";
            require_once "lic/app-builder/lib/layout.php";
            echo json_encode(builder_documentation_info());
        '], base_path());
        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Process error: ' . $process->getErrorOutput());
        $docInfo = json_decode($process->getOutput(), true);
        $this->assertIsArray($docInfo);
        $this->assertNotEmpty($docInfo['url']);
        $this->assertStringContainsString('documentation', $docInfo['url']);
    }
}
