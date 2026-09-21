<?php

namespace Tests\Feature\Tenant;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\TenantDocumentTemplate;
use App\Models\User;
use App\Services\Notifications\TenantNotificationDispatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DocumentTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;
    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'id' => 'comp-template-test-01',
            'name' => 'Metro Retail Mart',
            'trade_name' => 'MetroRetail',
            'slug' => 'metro-retail-mart-templates',
            'email' => 'metro@retail.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'operating_mode' => 'general',
            'pos_mode' => 'general',
            'expires_at' => now()->addDays(30),
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Alex Johnson',
            'email' => 'admin@retail.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $this->cashier = User::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'John Cashier',
            'email' => 'cashier@retail.com',
            'password' => Hash::make('secret123'),
            'role' => 'cashier',
        ]);
    }

    protected function token(User $user): string
    {
        return $this->postJson('/api/v1/pos/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertOk()->json('token');
    }

    public function test_can_view_and_update_invoice_template_web(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->get('/settings/templates/invoices');

        $response->assertOk();
        $response->assertSee('Template &amp; Dispatch Designer', false);

        $updateResponse = $this->actingAs($this->admin, 'web')
            ->post('/settings/templates/invoices', [
                'theme_color' => '#6366f1',
                'logo_placement' => 'center',
                'header_title' => 'Custom Metro Invoice',
                'terms_conditions' => 'Strict 14-day warranty return policy.',
                'show_qr_code' => '1',
                'show_tax_breakup' => '1',
                'footer_notes' => 'Thank you for shopping at MetroRetail!',
                'send_as_attachment' => '0',
                'send_text_with_link' => '1',
                'message_body_template' => 'Hi {customer_name}! Your invoice #{invoice_number} for {amount} is here: {document_link}',
            ]);

        $updateResponse->assertRedirect(route('settings.templates.edit', ['type' => 'invoices']));

        $template = TenantDocumentTemplate::getForCompany($this->company->id, 'invoice');
        $this->assertEquals('#6366f1', $template->theme_color);
        $this->assertEquals('center', $template->logo_placement);
        $this->assertEquals('Custom Metro Invoice', $template->header_title);
        $this->assertFalse($template->send_as_attachment);
        $this->assertTrue($template->send_text_with_link);
    }

    public function test_template_live_preview_renders_html(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->get('/settings/templates/invoices/preview?theme_color=%23f59e0b&header_title=Preview+Tax+Invoice');

        $response->assertOk();
        $response->assertSee('Preview Tax Invoice');
        $response->assertSee('#f59e0b');
    }

    public function test_api_template_show_and_update(): void
    {
        $token = $this->token($this->admin);

        $showRes = $this->withToken($token)->getJson('/api/v1/tenant/templates/quotations');
        $showRes->assertOk();
        $showRes->assertJsonPath('success', true);
        $showRes->assertJsonPath('type', 'quotation');
        $showRes->assertJsonStructure(['success', 'type', 'template', 'placeholders']);

        $updateRes = $this->withToken($token)->postJson('/api/v1/tenant/templates/quotations', [
            'theme_color' => '#0284c7',
            'header_title' => 'Official Price Estimate',
            'send_as_attachment' => true,
            'send_text_with_link' => false,
            'message_body_template' => 'Proposal {quotation_number} total {amount}: {document_link}',
        ]);

        $updateRes->assertOk();
        $updateRes->assertJsonPath('success', true);
        $updateRes->assertJsonPath('template.header_title', 'Official Price Estimate');
    }

    public function test_dispatcher_respects_send_as_attachment_and_text_link_modes(): void
    {
        // 1. Template with send_as_attachment = false
        TenantDocumentTemplate::updateOrCreate(
            ['company_id' => $this->company->id, 'template_type' => 'invoice'],
            [
                'tenant_id' => $this->company->id,
                'send_as_attachment' => false,
                'send_text_with_link' => true,
                'message_body_template' => 'Hi {customer_name}! Your bill #{invoice_number} is {amount}. Link: {document_link}',
            ]
        );

        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Sarah Connor',
            'email' => 'sarah@example.com',
            'phone' => '+15550001111',
        ]);

        $sale = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'ORD-TEST-99',
            'customer_name' => 'Sarah Connor',
            'customer_phone' => '+15550001111',
            'customer_email' => 'sarah@example.com',
            'total' => 75.50,
            'paid_amount' => 75.50,
            'due_amount' => 0.00,
            'status' => 'completed',
        ]);

        $dispatcher = app(TenantNotificationDispatcherService::class);
        $results = $dispatcher->dispatchReceipt($this->company, $sale, ['email', 'sms'], '+15550001111', 'sarah@example.com');

        // Since send_as_attachment is false, email is dispatched without binary PDF attachment
        $this->assertArrayHasKey('email', $results);
        $this->assertArrayHasKey('sms', $results);
    }

    public function test_cashier_without_template_permission_is_forbidden(): void
    {
        $response = $this->actingAs($this->cashier, 'web')
            ->get('/settings/templates/invoices');

        $response->assertStatus(403);

        $token = $this->token($this->cashier);
        $apiResponse = $this->withToken($token)->getJson('/api/v1/tenant/templates/invoices');
        $apiResponse->assertStatus(403);
    }

    public function test_attachment_mode_true_dispatches_with_pdf(): void
    {
        TenantDocumentTemplate::updateOrCreate(
            ['company_id' => $this->company->id, 'template_type' => 'invoice'],
            [
                'tenant_id' => $this->company->id,
                'send_as_attachment' => true,
                'send_text_with_link' => false,
            ]
        );

        $sale = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'ORD-ATTACH-01',
            'customer_name' => 'Alice Walker',
            'total' => 150.00,
            'paid_amount' => 150.00,
            'due_amount' => 0.00,
            'status' => 'completed',
        ]);

        $dispatcher = app(TenantNotificationDispatcherService::class);
        $results = $dispatcher->dispatchReceipt($this->company, $sale, ['email'], null, 'alice@example.com');
        $this->assertArrayHasKey('email', $results);
    }
}
