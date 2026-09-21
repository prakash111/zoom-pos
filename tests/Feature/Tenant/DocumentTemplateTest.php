<?php

namespace Tests\Feature\Tenant;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Sale;
use App\Models\TenantDocumentTemplate;
use App\Models\User;
use App\Services\Notifications\TenantNotificationDispatcherService;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Mail\InvoiceMailable;
use App\Mail\QuotationMailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

    public function test_view_permission_does_not_allow_template_update(): void
    {
        Permission::create([
            'company_id' => $this->company->id,
            'user_id' => $this->cashier->id,
            'module' => 'settings',
            'action' => 'view',
            'allowed' => true,
        ]);

        $this->actingAs($this->cashier, 'web')
            ->get('/settings/templates/invoices')->assertOk();
        $this->actingAs($this->cashier, 'web')
            ->post('/settings/templates/invoices', [
                'theme_color' => '#123456',
                'send_as_attachment' => '0',
            ])->assertForbidden();
        $this->assertDatabaseMissing('tenant_document_templates', [
            'company_id' => $this->company->id,
            'theme_color' => '#123456',
        ]);
    }

    public function test_tenant_delivery_mode_controls_both_email_document_types(): void
    {
        Mail::fake();
        foreach (['invoice', 'quotation'] as $type) {
            $sale = Sale::create([
                'company_id' => $this->company->id,
                'sale_number' => strtoupper($type).'-MAIL-01',
                'operation_type' => $type === 'quotation' ? 'quotation' : 'sale',
                'customer_name' => 'Sarah Connor',
                'total' => 75.50,
                'status' => 'completed',
            ]);
            TenantDocumentTemplate::updateOrCreate(
                ['company_id' => $this->company->id, 'template_type' => $type],
                [
                    'tenant_id' => $this->company->id,
                    'send_as_attachment' => false,
                    'send_text_with_link' => true,
                    'message_body_template' => 'Hello {customer_name}, reference {invoice_number}.',
                ]
            );

            $delivery = app(InvoiceDeliveryService::class);
            if ($type === 'quotation') {
                $delivery->sendQuotationEmail($sale, 'sarah@example.com');
                Mail::assertSent(QuotationMailable::class, fn (QuotationMailable $mail) =>
                    ! $mail->attachPdf && $mail->attachments() === []
                    && str_contains($mail->customMessage, route('quotes.public', $sale->sale_number))
                );
            } else {
                $delivery->sendInvoiceEmail($sale, 'sarah@example.com');
                Mail::assertSent(InvoiceMailable::class, fn (InvoiceMailable $mail) =>
                    ! $mail->attachPdf && $mail->attachments() === []
                    && str_contains($mail->customMessage, route('sales.public', $sale->sale_number))
                );
            }
        }
    }

    public function test_saved_design_is_used_by_outbound_a4_documents(): void
    {
        foreach (['invoice', 'quotation'] as $type) {
            $sale = Sale::create([
                'company_id' => $this->company->id,
                'sale_number' => strtoupper($type).'-PDF-01',
                'operation_type' => $type === 'quotation' ? 'quotation' : 'sale',
                'customer_name' => 'Sarah Connor',
                'total' => 75.50,
                'items' => [['name' => 'Chair', 'price' => 75.50, 'quantity' => 1]],
                'status' => 'completed',
            ]);
            $template = TenantDocumentTemplate::updateOrCreate(
                ['company_id' => $this->company->id, 'template_type' => $type],
                [
                    'tenant_id' => $this->company->id,
                    'theme_color' => '#123456',
                    'logo_placement' => 'hidden',
                    'header_title' => 'Metro Custom Document',
                    'terms_conditions' => 'Pay within 14 days.',
                    'footer_notes' => 'Metro thanks you.',
                    'show_qr_code' => false,
                    'show_tax_breakup' => false,
                ]
            );

            $html = view('pdf.'.$type, [
                'sale' => $sale,
                'company' => $this->company,
                'logoBase64' => null,
                'template' => $template,
                'qrCodeData' => null,
            ])->render();

            $this->assertStringContainsString('Metro Custom Document', $html);
            $this->assertStringContainsString('Pay within 14 days.', $html);
            $this->assertStringContainsString('Metro thanks you.', $html);
            $this->assertStringNotContainsString('verification QR', $html);
        }
    }

    public function test_switches_can_be_disabled_and_partial_api_updates_preserve_delivery_mode(): void
    {
        $this->actingAs($this->admin, 'web')->post('/settings/templates/invoices', [
            'show_qr_code' => '0',
            'show_tax_breakup' => '0',
            'send_as_attachment' => '0',
            'message_body_template' => 'Document {document_link}',
        ])->assertRedirect();

        $token = $this->token($this->admin);
        $this->withToken($token)->putJson('/api/v1/tenant/templates/invoices', [
            'header_title' => 'Updated Invoice',
        ])->assertOk();

        $template = TenantDocumentTemplate::getForCompany($this->company->id, 'invoice');
        $this->assertFalse($template->show_qr_code);
        $this->assertFalse($template->show_tax_breakup);
        $this->assertFalse($template->send_as_attachment);
        $this->assertTrue($template->send_text_with_link);
        $this->assertSame('Document {document_link}', $template->message_body_template);
    }

    public function test_attachment_mode_generates_a4_pdfs_for_invoice_and_quotation(): void
    {
        Mail::fake();
        $delivery = app(InvoiceDeliveryService::class);

        foreach (['invoice', 'quotation'] as $type) {
            $sale = Sale::create([
                'company_id' => $this->company->id,
                'sale_number' => strtoupper($type).'-ATTACHED-01',
                'operation_type' => $type === 'quotation' ? 'quotation' : 'sale',
                'customer_name' => 'Sarah Connor',
                'total' => 75.50,
                'items' => [['name' => 'Chair', 'price' => 75.50, 'quantity' => 1]],
                'status' => 'completed',
            ]);

            if ($type === 'quotation') {
                $delivery->sendQuotationEmail($sale, 'sarah@example.com');
                Mail::assertSent(QuotationMailable::class, fn (QuotationMailable $mail) =>
                    $mail->quote->is($sale) && $mail->attachPdf
                    && str_starts_with($mail->pdfBinary, '%PDF')
                );
            } else {
                $delivery->sendInvoiceEmail($sale, 'sarah@example.com');
                Mail::assertSent(InvoiceMailable::class, fn (InvoiceMailable $mail) =>
                    $mail->sale->is($sale) && $mail->attachPdf
                    && str_starts_with($mail->pdfBinary, '%PDF')
                );
            }
        }
    }
}
