<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Quotes\Create as QuotesCreate;
use App\Livewire\Tenant\Quotes\Edit as QuotesEdit;
use App\Livewire\Tenant\Quotes\Index as QuotesIndex;
use App\Livewire\Tenant\Quotes\Show as QuotesShow;
use App\Livewire\Tenant\Sales\Index;
use App\Livewire\Tenant\Sales\Show;
use App\Mail\InvoiceMailable;
use App\Mail\QuotationMailable;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\TaxRule;
use App\Services\Invoice\InvoiceDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class QuotesAndDocumentsTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

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

    public function test_quotation_uses_active_tenant_tax_rules_and_persists_component_breakdown(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        TaxRule::create([
            'company_id' => $company->id,
            'tax_name' => 'GST 18% (Intra-State)',
            'tax_code' => 'GST_18_INTRA',
            'rate' => 18,
            'is_default' => true,
            'active' => true,
            'sub_components' => [
                ['name' => 'CGST', 'rate' => 9],
                ['name' => 'SGST', 'rate' => 9],
            ],
        ]);
        $gstTwelve = TaxRule::create([
            'company_id' => $company->id,
            'tax_name' => 'GST 12%',
            'tax_code' => 'GST_12',
            'rate' => 12,
            'active' => true,
        ]);

        Livewire::test(QuotesCreate::class)
            ->assertSet('taxName', 'GST 18% (Intra-State)')
            ->assertSee('GST 18% (Intra-State)')
            ->assertSee('CGST + SGST')
            ->set('items.0.name', 'Taxable service')
            ->set('items.0.quantity', 1)
            ->set('items.0.price', 100)
            ->set('selectedTaxRuleId', $gstTwelve->id)
            ->assertSet('taxPercent', 12.0)
            ->assertSet('taxName', 'GST 12%')
            ->call('save');

        $quote = Sale::where('operation_type', 'quotation')->latest('created_at')->firstOrFail();
        $this->assertSame(112.0, (float) $quote->total);
        $this->assertSame('GST 12%', $quote->tax_name);
        $this->assertSame('GST 12%', $quote->tax_breakdown[0]['tax_name']);
    }

    public function test_quotation_detail_and_pdf_use_applied_gst_sovereign_label_for_company_and_customer(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();
        $company->update([
            'country' => 'US', // Simulates a stale provisioning country.
            'tax_id' => 'SGS100611456',
            'tax_id_label' => 'Tax ID',
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Sarah Smith',
            'document' => 'TAX-4423',
            'tax_id_label' => 'Tax ID / VAT',
        ]);
        $quote = Sale::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'user_id' => $user->id,
            'sale_number' => 'QUO-GST-001',
            'operation_type' => 'quotation',
            'status' => 'draft',
            'total' => 118,
            'tax_amount' => 18,
            'tax_name' => 'GST 18% (Intra-State)',
            'tax_rate' => 18,
            'items' => [['name' => 'Service', 'quantity' => 1, 'price' => 100]],
            'tax_breakdown' => [[
                'tax_name' => 'GST 18% (Intra-State)',
                'rate' => 18,
                'taxable_amount' => 100,
                'tax_amount' => 18,
                'components' => [],
            ]],
        ]);

        Livewire::test(QuotesShow::class, ['quote' => $quote])
            ->assertSee('GSTIN:')
            ->assertSee('SGS100611456')
            ->assertSee('TAX-4423')
            ->assertDontSee('Tax ID: SGS100611456', false)
            ->assertDontSee('Tax ID / VAT:', false);

        $pdfHtml = view('pdf.quotation', [
            'sale' => $quote->load(['customer', 'user']),
            'company' => $company,
            'logoBase64' => null,
        ])->render();

        $this->assertStringContainsString('GSTIN: SGS100611456', $pdfHtml);
        $this->assertStringContainsString('<strong>GSTIN:</strong> TAX-4423', $pdfHtml);
    }

    public function test_quotation_crud_and_pdf_generation(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();
        $company->update(['primary_color' => '#1e293b']);

        $product = Product::create(['company_id' => $company->id, 'name' => 'Design Service', 'current_stock' => 10, 'sale_price' => 100, 'active' => true]);
        $customer = Customer::create(['company_id' => $company->id, 'name' => 'Salford & Co.', 'email' => 'salford@example.com', 'document' => 'TAX-9988']);

        // 1. Create Quotation
        $quoteComponent = Livewire::test(QuotesCreate::class)
            ->set('customerId', $customer->id)
            ->set('userId', $user->id)
            ->set('items.0.product_id', $product->id)
            ->set('items.0.name', 'Design Service')
            ->set('items.0.description', 'Complete brand identity & guidelines')
            ->set('items.0.quantity', 2)
            ->set('items.0.price', 100)
            ->set('discountValue', 20)
            ->set('discountType', 'fixed')
            ->set('taxPercent', 10)
            ->set('notes', '50% advance upon contract signing')
            ->set('dueDate', '2026-09-30')
            ->call('save')
            ->assertRedirect();

        $quote = Sale::where('operation_type', 'quotation')->firstOrFail();
        $this->assertSame('Salford & Co.', $quote->customer_name);
        $this->assertSame('draft', $quote->status);
        $this->assertSame(198.0, (float) $quote->total); // (200 subtotal - 20 discount + 18 tax = 198)
        $this->assertSame(10.0, (float) $product->fresh()->current_stock); // Stock NOT deducted on quote
        $this->assertSame('50% advance upon contract signing', $quote->notes);
        $this->assertSame('2026-09-30', $quote->due_date?->format('Y-m-d'));

        // 2. View Quotation Livewire Component
        Livewire::test(QuotesShow::class, ['quote' => $quote])
            ->assertSee('Salford & Co.')
            ->assertSee('Design Service')
            ->assertSee('Complete brand identity & guidelines')
            ->assertSee('200.00')
            ->assertSee('#1e293b'); // Confirms real-time theme sync with company primary_color

        // 3. Edit Quotation Livewire Component
        Livewire::test(QuotesEdit::class, ['quote' => $quote])
            ->set('discountType', 'percent')
            ->set('discountValue', 10) // 10% of 200 = 20
            ->set('notes', 'Revised scope deliverables')
            ->call('save')
            ->assertRedirect();

        $this->assertSame('Revised scope deliverables', $quote->fresh()->notes);

        // 4. Test Quotation PDF route
        $response = $this->actingAs($user, 'web')->get(route('tenant.quotes.pdf', $quote));
        $response->assertOk();
        $response->assertSee('Design Service');

        // 5. Test Public shareable quotation link
        $pubResponse = $this->get(route('quotes.public', $quote->sale_number));
        $pubResponse->assertOk();
        $pubResponse->assertSee($quote->sale_number);
    }

    public function test_converting_quotation_to_sale_deducts_stock_and_creates_invoice(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();
        $product = Product::create(['company_id' => $company->id, 'name' => 'Website Development', 'current_stock' => 5, 'sale_price' => 500, 'active' => true]);
        $customer = Customer::create(['company_id' => $company->id, 'name' => 'Acme Corp']);

        $quote = Sale::create([
            'company_id' => $company->id,
            'operation_type' => 'quotation',
            'sale_number' => 'QUO-888',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'status' => 'draft',
            'total' => 500.0,
            'discount' => 0,
            'items' => [
                ['product_id' => $product->id, 'name' => 'Website Development', 'quantity' => 1, 'price' => 500],
            ],
        ]);

        Livewire::test(QuotesShow::class, ['quote' => $quote])
            ->call('convertToSale')
            ->assertRedirect();

        $this->assertSame('converted', $quote->fresh()->status);
        $this->assertSame(4.0, (float) $product->fresh()->current_stock); // Stock deducted

        $sale = Sale::where('operation_type', 'sale')->latest('id')->firstOrFail();
        $this->assertSame('completed', $sale->status);
        $this->assertSame(500.0, (float) $sale->total);

        // Test Invoice PDF route
        $response = $this->actingAs($user, 'web')->get(route('tenant.sales.pdf', $sale));
        $response->assertOk();
        $response->assertSee('Invoice / Receipt');

        // Test Public shareable invoice link
        $pubResponse = $this->get(route('sales.public', $sale->sale_number));
        $pubResponse->assertOk();
        $pubResponse->assertSee($sale->sale_number);
    }

    public function test_quotation_email_dispatch_uses_quotation_mailable_with_pdf_attachment_toggle(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();
        Mail::fake();

        $quote = Sale::create([
            'company_id' => $company->id,
            'operation_type' => 'quotation',
            'sale_number' => 'QUO-991',
            'customer_name' => 'Tech Corp',
            'status' => 'draft',
            'total' => 1250.0,
            'discount' => 50,
            'notes' => 'Deliverable within 30 days of advance payment',
            'items' => [
                ['name' => 'Cloud Migration', 'quantity' => 1, 'price' => 1300],
            ],
        ]);

        // 1. Dispatch with PDF attached
        Livewire::test(QuotesShow::class, ['quote' => $quote])
            ->set('recipientEmail', 'client@techcorp.test')
            ->set('attachPdf', true)
            ->set('customMessage', 'Special quote for {customer_name}, total {total_amount} with quote #{document_number}.')
            ->call('sendEmail')
            ->assertHasNoErrors();

        $quote->refresh();
        $this->assertSame('sent', $quote->status);
        $this->assertSame('quotation', $quote->operation_type); // Status updated to sent, NEVER changed to invoice

        Mail::assertSent(QuotationMailable::class, function ($mail) {
            $this->assertSame('client@techcorp.test', $mail->to[0]['address']);
            $this->assertStringContainsString('QUO-991', $mail->envelope()->subject);
            $this->assertStringContainsString('Quotation', $mail->envelope()->subject);
            $this->assertStringNotContainsString('Tax Invoice', $mail->envelope()->subject);
            $this->assertTrue($mail->attachPdf);

            $attachments = $mail->attachments();
            $this->assertCount(1, $attachments);

            return true;
        });

        // 2. Dispatch without PDF attached (text-only)
        Livewire::test(QuotesShow::class, ['quote' => $quote])
            ->set('recipientEmail', 'client2@techcorp.test')
            ->set('attachPdf', false)
            ->call('sendEmail')
            ->assertHasNoErrors();

        Mail::assertSent(QuotationMailable::class, function ($mail) {
            if ($mail->to[0]['address'] === 'client2@techcorp.test') {
                $this->assertFalse($mail->attachPdf);
                $attachments = $mail->attachments();
                $this->assertCount(0, $attachments);

                return true;
            }

            return false;
        });
    }

    public function test_invoice_email_dispatch_uses_invoice_mailable_with_pdf_attachment_toggle(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();
        Mail::fake();

        $sale = Sale::create([
            'company_id' => $company->id,
            'operation_type' => 'sale',
            'sale_number' => 'INV-550',
            'customer_name' => 'Alpha Inc',
            'status' => 'completed',
            'total' => 750.0,
            'discount' => 0,
            'notes' => 'Paid in full via Cash',
            'items' => [
                ['name' => 'Consulting Hours', 'quantity' => 5, 'price' => 150],
            ],
        ]);

        Livewire::test(Show::class, ['sale' => $sale])
            ->set('recipientEmail', 'finance@alphainc.test')
            ->set('attachPdf', true)
            ->set('customMessage', 'Invoice #{document_number} of {total_amount} for {customer_name}.')
            ->call('sendEmail')
            ->assertHasNoErrors();

        Mail::assertSent(InvoiceMailable::class, function ($mail) {
            $this->assertSame('finance@alphainc.test', $mail->to[0]['address']);
            $this->assertStringContainsString('INV-550', $mail->envelope()->subject);
            $this->assertStringContainsString('Tax Invoice', $mail->envelope()->subject);
            $this->assertStringNotContainsString('Quotation', $mail->envelope()->subject);
            $this->assertTrue($mail->attachPdf);

            $attachments = $mail->attachments();
            $this->assertCount(1, $attachments);

            return true;
        });
    }

    public function test_whatsapp_url_generator_and_placeholders_for_quotes_and_invoices(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();
        $delivery = app(InvoiceDeliveryService::class);

        $quote = Sale::create([
            'company_id' => $company->id,
            'operation_type' => 'quotation',
            'sale_number' => 'QUO-330',
            'customer_name' => 'Beta Dynamics',
            'status' => 'draft',
            'total' => 450.0,
            'discount' => 50,
            'items' => [
                ['name' => 'Server Setup', 'quantity' => 1, 'price' => 500],
            ],
        ]);

        $customTemplate = 'Hi {customer_name}! Your quote #{document_number} total is {total_amount}. View: {download_link}';
        $formatted = $delivery->formatCustomMessage($customTemplate, $quote, $company);

        $this->assertStringContainsString('Beta Dynamics', $formatted);
        $this->assertStringContainsString('QUO-330', $formatted);
        $this->assertStringContainsString('$450.00', $formatted);
        $this->assertStringContainsString(route('quotes.public', 'QUO-330'), $formatted);

        $waUrl = $delivery->generateQuotationWhatsAppUrl($quote, '+1555123456', $customTemplate);
        $this->assertStringContainsString('https://wa.me/1555123456?text=', $waUrl);
        $this->assertStringContainsString(rawurlencode('Beta Dynamics'), $waUrl);
    }

    public function test_dompdf_binary_generation_and_custom_terms_and_notes(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();
        $company->update([
            'primary_color' => '#7c3aed',
            'quote_terms' => "Custom Term 1: Valid 30 days\nCustom Term 2: 50% advance required",
            'invoice_terms' => 'Custom Invoice Term: Net 30 payment policy',
        ]);

        $quote = Sale::create([
            'company_id' => $company->id,
            'operation_type' => 'quotation',
            'sale_number' => 'QUO-777',
            'customer_name' => 'Gamma LLC',
            'status' => 'draft',
            'total' => 900.0,
            'notes' => 'Custom proposal remark: Priority express turnaround',
            'items' => [
                ['name' => 'Express Delivery Service', 'quantity' => 1, 'price' => 900],
            ],
        ]);

        $delivery = app(InvoiceDeliveryService::class);

        // 1. Generate Quotation PDF binary with custom theme color
        $quotePdf = $delivery->generateQuotationPdf($quote);
        $this->assertNotEmpty($quotePdf);
        $this->assertStringStartsWith('%PDF-', $quotePdf);

        // 2. Download Quotation PDF route
        $response = $this->actingAs($user, 'web')->get(route('tenant.quotes.pdf', $quote).'?download=1');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment; filename="Quotation-QUO-777.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertNotEmpty($response->headers->get('Content-Length'));

        // Stream mode
        $streamResponse = $this->actingAs($user, 'web')->get(route('tenant.quotes.pdf', $quote).'?stream=1');
        $streamResponse->assertOk();
        $streamResponse->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline; filename="Quotation-QUO-777.pdf"', $streamResponse->headers->get('Content-Disposition'));

        // 3. Generate Invoice PDF binary
        $sale = Sale::create([
            'company_id' => $company->id,
            'operation_type' => 'sale',
            'sale_number' => 'INV-777',
            'customer_name' => 'Gamma LLC',
            'status' => 'completed',
            'total' => 900.0,
            'notes' => 'Delivered on time',
            'items' => [
                ['name' => 'Express Delivery Service', 'quantity' => 1, 'price' => 900],
            ],
        ]);

        $invoicePdf = $delivery->generateInvoicePdf($sale);
        $this->assertNotEmpty($invoicePdf);
        $this->assertStringStartsWith('%PDF-', $invoicePdf);

        $invResponse = $this->actingAs($user, 'web')->get(route('tenant.sales.pdf', $sale).'?download=1');
        $invResponse->assertOk();
        $invResponse->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment; filename="Invoice-INV-777.pdf"', $invResponse->headers->get('Content-Disposition'));
        $this->assertNotEmpty($invResponse->headers->get('Content-Length'));
    }

    public function test_controller_send_endpoints_for_quotes_and_invoices(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();
        Mail::fake();

        $quote = Sale::create([
            'company_id' => $company->id,
            'operation_type' => 'quotation',
            'sale_number' => 'QUO-401',
            'customer_name' => 'Delta Group',
            'status' => 'draft',
            'total' => 600.0,
            'items' => [['name' => 'Support Plan', 'quantity' => 1, 'price' => 600]],
        ]);

        $response = $this->actingAs($user, 'web')->postJson(route('tenant.quotes.send', $quote), [
            'recipient_email' => 'delta@example.com',
            'custom_message' => 'Hello {customer_name}, please find quote {document_number}.',
            'attach_pdf' => true,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSame('sent', $quote->fresh()->status);
        Mail::assertSent(QuotationMailable::class);

        $sale = Sale::create([
            'company_id' => $company->id,
            'operation_type' => 'sale',
            'sale_number' => 'INV-401',
            'customer_name' => 'Delta Group',
            'status' => 'completed',
            'total' => 600.0,
            'items' => [['name' => 'Support Plan', 'quantity' => 1, 'price' => 600]],
        ]);

        $saleResponse = $this->actingAs($user, 'web')->postJson(route('tenant.sales.send', $sale), [
            'recipient_email' => 'delta-billing@example.com',
            'attach_pdf' => false,
        ]);

        $saleResponse->assertOk();
        $saleResponse->assertJson(['success' => true]);
        Mail::assertSent(InvoiceMailable::class);
    }

    public function test_customer_contact_info_and_logo_and_bulk_actions_in_quotes(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();
        $delivery = app(InvoiceDeliveryService::class);

        // Test Logo base64 resolution with data URI and fallback
        $dataUriLogo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $company->update(['logo' => $dataUriLogo]);
        $resolved = $delivery->resolveLogoBase64($company->logo);
        $this->assertSame($dataUriLogo, $resolved);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Acme Global Corp',
            'phone' => '+1555000111',
            'email' => 'billing@acmeglobal.test',
            'address' => '456 Tech Park Way',
            'city' => 'Metropolis',
            'state' => 'NY',
            'document' => 'US-EIN-987654321',
        ]);

        $quote = Sale::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'operation_type' => 'quotation',
            'sale_number' => 'QUO-800',
            'status' => 'draft',
            'total' => 1500.0,
            'notes' => '<p><strong>Phase 1:</strong> Design & Architecture.<br><strong>Phase 2:</strong> Implementation.</p>',
            'items' => [['name' => 'Architecture Consulting', 'quantity' => 1, 'price' => 1500]],
        ]);

        // 1. Check PDF View rendered HTML has customer contact info grouped under PROPOSAL PREPARED FOR
        $view = view('pdf.quotation', [
            'sale' => $quote,
            'company' => $company,
            'logoBase64' => $resolved,
        ])->render();

        $this->assertStringContainsString('PROPOSAL PREPARED FOR:', $view);
        $this->assertStringContainsString('Acme Global Corp', $view);
        $this->assertStringContainsString('+1555000111', $view);
        $this->assertStringContainsString('billing@acmeglobal.test', $view);
        $this->assertStringContainsString('456 Tech Park Way', $view);
        $this->assertStringContainsString('US-EIN-987654321', $view);
        $this->assertStringContainsString($dataUriLogo, $view);
        $this->assertStringContainsString('<strong>Phase 1:</strong>', $view); // Safe rich-text rendered

        // 2. Test Quotes Index bulk actions and convert to sale
        Livewire::test(QuotesIndex::class)
            ->set('selectAll', true)
            ->assertCount('selectedQuotes', 1)
            ->call('convertToSale', $quote->id)
            ->assertRedirect();

        $this->assertSame('converted', $quote->fresh()->status);
    }

    public function test_quote_terms_auto_sync_from_company_settings(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();
        $company->update([
            'quote_terms' => '<p>Standard Proposal Terms: 30 days validity, 50% upfront deposit.</p>',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Consulting Hours',
            'current_stock' => 50,
            'sale_price' => 120,
            'active' => true,
        ]);

        // 1. When creating a new quote, $this->notes should auto-load $company->quote_terms
        $createTest = Livewire::test(QuotesCreate::class);
        $this->assertSame('<p>Standard Proposal Terms: 30 days validity, 50% upfront deposit.</p>', $createTest->get('notes'));
        $createTest->assertSee('Quote Terms & Notes', false);

        // Create the quote saving default notes
        $createTest
            ->set('items.0.product_id', $product->id)
            ->set('items.0.name', 'Consulting Hours')
            ->set('items.0.quantity', 2)
            ->set('items.0.price', 120)
            ->call('save')
            ->assertRedirect();

        $quote = Sale::where('operation_type', 'quotation')->latest('id')->firstOrFail();
        $this->assertSame('<p>Standard Proposal Terms: 30 days validity, 50% upfront deposit.</p>', $quote->notes);

        // 2. When editing an existing quote with notes, it loads the quote's notes
        $editTest = Livewire::test(QuotesEdit::class, ['quote' => $quote]);
        $this->assertSame('<p>Standard Proposal Terms: 30 days validity, 50% upfront deposit.</p>', $editTest->get('notes'));
        $editTest->assertSee('Quote Terms & Notes', false);

        // 3. When editing a quote with empty notes, it falls back to company quote_terms
        $quote->update(['notes' => null]);
        $editFallbackTest = Livewire::test(QuotesEdit::class, ['quote' => $quote->fresh()]);
        $this->assertSame('<p>Standard Proposal Terms: 30 days validity, 50% upfront deposit.</p>', $editFallbackTest->get('notes'));
    }

    public function test_quotes_and_sales_navigation_and_redirects_use_spa_wire_navigate(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'SEO Audit',
            'current_stock' => 10,
            'sale_price' => 250,
            'active' => true,
        ]);

        $quote = Sale::create([
            'company_id' => $company->id,
            'sale_number' => 'QUO-SPA-001',
            'customer_name' => 'Acme Corp',
            'user_id' => $user->id,
            'total' => 250.00,
            'paid_amount' => 0.00,
            'due_amount' => 250.00,
            'status' => 'draft',
            'operation_type' => 'quotation',
            'items' => [
                ['product_id' => $product->id, 'name' => 'SEO Audit', 'quantity' => 1, 'price' => 250.00],
            ],
        ]);

        // 1. Verify Quotes index view has wire:navigate on New Quote, Quote #, Edit and View
        Livewire::test(QuotesIndex::class)
            ->assertSeeHtml('wire:navigate.hover href="'.route('tenant.quotes.create').'"')
            ->assertSeeHtml('wire:navigate.hover href="'.route('tenant.quotes.show', $quote).'"')
            ->assertSeeHtml('wire:navigate.hover href="'.route('tenant.quotes.edit', $quote).'"');

        // 2. Verify Quotes create back link has wire:navigate
        Livewire::test(QuotesCreate::class)
            ->assertSeeHtml('wire:navigate.hover href="'.route('tenant.quotes.index').'"');

        // 3. Verify Sales index view has wire:navigate on New Sale and View
        $sale = Sale::create([
            'company_id' => $company->id,
            'sale_number' => 'INV-SPA-001',
            'customer_name' => 'Walk-in',
            'user_id' => $user->id,
            'total' => 100.00,
            'paid_amount' => 100.00,
            'due_amount' => 0.00,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
            'operation_type' => 'sale',
            'items' => [
                ['name' => 'Item 1', 'quantity' => 1, 'price' => 100.00],
            ],
        ]);

        Livewire::test(Index::class)
            ->assertSeeHtml('wire:navigate.hover href="'.route('tenant.sales.create').'"')
            ->assertSeeHtml('wire:navigate.hover href="'.route('tenant.sales.show', $sale).'"');

        // 4. Verify livewire redirect on save uses navigate: true
        Livewire::test(QuotesEdit::class, ['quote' => $quote])
            ->set('notes', 'Testing SPA navigation')
            ->call('save')
            ->assertRedirect(route('tenant.quotes.show', $quote));
    }
}
