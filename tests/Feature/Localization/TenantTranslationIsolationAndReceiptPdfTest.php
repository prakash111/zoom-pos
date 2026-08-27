<?php

namespace Tests\Feature\Localization;

use App\Livewire\Tenant\Languages\Index as TenantLanguagesIndex;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\User;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Localization\LocalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

class TenantTranslationIsolationAndReceiptPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
    }

    protected function tearDown(): void
    {
        if (file_exists(storage_path('installed'))) {
            @unlink(storage_path('installed'));
        }
        parent::tearDown();
    }

    protected function createTenant(string $name, string $language = 'en', ?string $defaultLocale = null): array
    {
        Plan::firstOrCreate(
            ['name' => 'professional'],
            [
                'display_name' => 'Professional',
                'price' => 199.00,
                'billing_cycle' => 'yearly',
                'active' => true,
                'is_featured' => true,
            ]
        );

        $company = Company::create([
            'id' => 'comp_'.bin2hex(random_bytes(8)),
            'name' => $name,
            'slug' => 'slug-'.bin2hex(random_bytes(4)),
            'email' => strtolower(str_replace(' ', '', $name)).'@test.com',
            'currency' => 'USD',
            'status' => 'active',
            'plan_name' => 'professional',
            'language' => $language,
            'default_locale' => $defaultLocale ?: $language,
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => "{$name} Admin",
            'login' => 'admin_'.bin2hex(random_bytes(3)),
            'email' => 'admin@'.strtolower(str_replace(' ', '', $name)).'.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        return [$company, $user];
    }

    /**
     * 1. Test Tenant & User Locale Resolution Hierarchy:
     * Priority 1 (User Selection in Session / users.locale)
     * Priority 2 (Store Primary Language: companies.default_locale)
     * Priority 3 (System Global Fallback: config('app.fallback_locale'))
     */
    public function test_locale_resolution_hierarchy(): void
    {
        $service = app(LocalizationService::class);

        // Case A: No session, no user -> falls back to system default (Priority 3)
        Session::flush();
        $this->assertSame(config('app.fallback_locale', 'en'), $service->getActiveLocale());

        // Case B: Tenant store configured with default_locale = 'es' (Priority 2)
        [$companyA, $userA] = $this->createTenant('Tenant Alpha', 'es', 'es');
        app()->instance('tenant.company_id', $companyA->id);
        $this->assertSame('es', $service->getActiveLocale());

        // Case C: Authenticated user has profile locale = 'fr' (Priority 1b overrides Priority 2)
        $userA->update(['locale' => 'fr']);
        $this->actingAs($userA, 'web');
        $this->assertSame('fr', $service->getActiveLocale());

        // Case D: Session explicitly set to 'de' (Priority 1a overrides Priority 1b and Priority 2)
        Session::put('locale', 'de');
        $this->assertSame('de', $service->getActiveLocale());
    }

    /**
     * 2. Test Multi-Tenant Translation Isolation:
     * Modifications in Tenant A dictionary affect ONLY Tenant A, leaving Tenant B unaffected.
     */
    public function test_tenant_custom_translations_are_strictly_isolated_without_cross_tenant_bleed(): void
    {
        [$companyA, $userA] = $this->createTenant('Store Alpha', 'en');
        [$companyB, $userB] = $this->createTenant('Store Beta', 'en');

        $service = app(LocalizationService::class);

        // Store Alpha customizes "TAX INVOICE / RECEIPT" and "Walk-in"
        $service->saveTenantTranslation($companyA->id, 'en', 'TAX INVOICE / RECEIPT', 'ALPHA TAX INVOICE');
        $service->saveTenantTranslation($companyA->id, 'en', 'Walk-in', 'Walk-in Guest (Alpha)');

        // Store Beta customizes "TAX INVOICE / RECEIPT" differently
        $service->saveTenantTranslation($companyB->id, 'en', 'TAX INVOICE / RECEIPT', 'BETA FISCAL TICKET');

        // Check Tenant A context
        app()->instance('tenant.company_id', $companyA->id);
        App::setLocale('en');
        $this->assertSame('ALPHA TAX INVOICE', __('TAX INVOICE / RECEIPT'));
        $this->assertSame('Walk-in Guest (Alpha)', __('Walk-in'));

        // Check Tenant B context
        app()->instance('tenant.company_id', $companyB->id);
        App::setLocale('en');
        $this->assertSame('BETA FISCAL TICKET', __('TAX INVOICE / RECEIPT'));
        $this->assertSame('Walk-in', __('Walk-in')); // Falls back to default system string

        // Check Non-tenant Global context
        app()->forgetInstance('tenant.company_id');
        auth('web')->logout();
        App::setLocale('en');
        $this->assertSame('TAX INVOICE / RECEIPT', __('TAX INVOICE / RECEIPT'));
    }

    /**
     * 3. Test that saving tenant customizations never modifies root /lang/*.json files.
     */
    public function test_saving_tenant_translations_never_modifies_root_language_files(): void
    {
        [$company, $admin] = $this->createTenant('Protected Root Tenant', 'en');

        $rootEnPath = base_path('lang/en.json');
        $originalHash = File::exists($rootEnPath) ? md5_file($rootEnPath) : null;

        Livewire::actingAs($admin, 'web')
            ->test(TenantLanguagesIndex::class)
            ->set('selectedLocale', 'en')
            ->set('customKey', 'Custom Tenant Slogan')
            ->set('customValue', 'Our Exclusive Tenant Slogan')
            ->call('addCustomPhrase')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenant_translations', [
            'tenant_id' => $company->id,
            'locale' => 'en',
            'key' => 'Custom Tenant Slogan',
            'value' => 'Our Exclusive Tenant Slogan',
        ]);

        if ($originalHash !== null) {
            $this->assertSame($originalHash, md5_file($rootEnPath), 'Root translation file was modified!');
        }
    }

    /**
     * 4. Test Navbar Language Switcher updates individual user session without altering other staff accounts.
     */
    public function test_navbar_language_switcher_updates_individual_user_session_and_profile_only(): void
    {
        [$company, $adminUser] = $this->createTenant('Multi-Staff Store', 'en', 'en');

        $staffUser = User::create([
            'company_id' => $company->id,
            'name' => 'Cashier Bob',
            'login' => 'cashier_bob',
            'email' => 'bob@multistaff.com',
            'password' => bcrypt('password123'),
            'role' => 'cashier',
            'locale' => 'en',
        ]);

        // Admin switches language to Spanish via navbar
        $response = $this->actingAs($adminUser, 'web')
            ->get(route('locale.switch', 'es'));

        $response->assertRedirect();
        $this->assertSame('es', session('locale'));
        $this->assertSame('es', $adminUser->fresh()->locale);

        // Verify Staff Bob's account and company store default language remain unchanged
        $this->assertSame('en', $staffUser->fresh()->locale);
        $this->assertSame('en', $company->fresh()->language);
        $this->assertSame('en', $company->fresh()->default_locale);
    }

    /**
     * 5. Test 58mm Thermal Receipt PDF Layout & DomPDF Rendering.
     */
    public function test_58mm_thermal_receipt_pdf_renders_with_proper_layout_and_tenant_translations(): void
    {
        [$company, $admin] = $this->createTenant('Thermal Cafe', 'en');

        // Add tenant phrase override
        $service = app(LocalizationService::class);
        $service->saveTenantTranslation($company->id, 'en', 'TAX INVOICE / RECEIPT', 'CAFÉ THERMAL RECEIPT');

        $sale = Sale::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'sale_number' => 'S-'.date('YmdHis'),
            'customer_name' => 'Jane Doe',
            'total' => 28.50,
            'paid_amount' => 30.00,
            'due_amount' => 0.00,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
            'items' => [
                [
                    'name' => 'Espresso Doppio Double Shot Extra Hot',
                    'quantity' => 2,
                    'price' => 4.25,
                ],
                [
                    'name' => 'Croissant Almond Pastry',
                    'quantity' => 1,
                    'price' => 20.00,
                ],
            ],
        ]);

        $deliveryService = app(InvoiceDeliveryService::class);
        $pdfBinary = $deliveryService->generateInvoicePdf($sale, '58mm');

        // Check valid PDF header
        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF-', $pdfBinary);

        // Verify view compiles with 58mm dimensions and custom override in tenant context
        app()->instance('tenant.company_id', $company->id);
        $viewHtml = view('pdf.receipt', [
            'sale' => $sale,
            'company' => $company,
            'logoBase64' => null,
            'is58mm' => true,
            'paperWidth' => '58mm',
            'qrCodeDataUri' => null,
            'qrCodeSvg' => null,
            'verificationUrl' => 'https://example.com/receipt/123',
        ])->render();

        $this->assertStringContainsString('58mm', $viewHtml);
        $this->assertStringContainsString('max-width: 52mm', $viewHtml);
        $this->assertStringContainsString('margin: 0mm 3mm', $viewHtml);
        $this->assertStringContainsString('table-layout: fixed', $viewHtml);
        $this->assertStringContainsString('col-left', $viewHtml);
        $this->assertStringContainsString('col-right', $viewHtml);
        $this->assertStringContainsString('CAFÉ THERMAL RECEIPT', $viewHtml);
        $this->assertStringContainsString('Jane Doe', $viewHtml);
        $this->assertStringContainsString('Espresso Doppio', $viewHtml);
    }

    /**
     * 6. Test Print Media CSS (@media print) and Unescaped HTML Terms Rendering.
     */
    public function test_print_media_css_and_unescaped_html_terms_rendering(): void
    {
        [$company, $admin] = $this->createTenant('Gourmet Bistro', 'en');
        $company->update([
            'invoice_terms' => '<p>All sales are final.</p><p>Exchange within <strong>7 days</strong> with original receipt.</p>',
        ]);

        $sale = Sale::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'sale_number' => 'S-2026-TERM',
            'customer_name' => 'John Smith',
            'total' => 45.00,
            'paid_amount' => 45.00,
            'due_amount' => 0.00,
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'status' => 'completed',
            'terms' => '<p>Special Discount Terms: Non-refundable</p>',
            'notes' => 'Customer requested digital invoice copy',
            'items' => [
                ['name' => 'Ribeye Steak', 'quantity' => 1, 'price' => 45.00],
            ],
        ]);

        // 1. Test Browser Print View (documents.receipt)
        $docHtml58 = view('documents.receipt', [
            'sale' => $sale,
            'company' => $company,
            'isQuotation' => false,
            'whatsAppUrl' => null,
            'backRoute' => null,
            'qrCodeSvg' => null,
            'qrCodeDataUri' => null,
            'verificationUrl' => 'https://example.com/receipt/test',
        ])->render();

        // Check @media print CSS rules for continuous roll and zero margin
        $this->assertStringContainsString('@media print', $docHtml58);
        $this->assertStringContainsString('margin: 0', $docHtml58);
        $this->assertStringContainsString('.no-print-bar', $docHtml58);
        $this->assertStringContainsString('display: none !important', $docHtml58);

        // Check Unescaped HTML rendering for Terms (no raw <p> tags displayed as text)
        $this->assertStringContainsString('<p>Special Discount Terms: Non-refundable</p>', $docHtml58);
        $this->assertStringNotContainsString('&lt;p&gt;Special Discount Terms: Non-refundable&lt;/p&gt;', $docHtml58);

        // 2. Test PDF View (pdf.receipt)
        $pdfHtml = view('pdf.receipt', [
            'sale' => $sale,
            'company' => $company,
            'logoBase64' => null,
            'is58mm' => true,
            'paperWidth' => '58mm',
            'paperSize' => '58mm',
            'qrCodeDataUri' => null,
            'qrCodeSvg' => null,
            'verificationUrl' => 'https://example.com/receipt/test',
        ])->render();

        $this->assertStringContainsString('@media print', $pdfHtml);
        $this->assertStringContainsString('size: 58mm auto', $pdfHtml);
        $this->assertStringContainsString('<p>Special Discount Terms: Non-refundable</p>', $pdfHtml);
        $this->assertStringNotContainsString('&lt;p&gt;Special Discount Terms: Non-refundable&lt;/p&gt;', $pdfHtml);

        // 3. Test Controller 58mm vs 80mm PDF Endpoints and Verify Single Page Output
        $response58 = $this->actingAs($admin, 'web')
            ->get(route('tenant.sales.pdf', $sale).'?format=58mm&download=1');
        $response58->assertOk();
        $response58->assertHeader('Content-Type', 'application/pdf');
        preg_match_all('/\/Type\s*\/Page\b/', $response58->getContent(), $pages58);
        $this->assertCount(1, $pages58[0], '58mm PDF must fit on a single page with no page-break spillover');

        $response80 = $this->actingAs($admin, 'web')
            ->get(route('tenant.sales.pdf', $sale).'?paperSize=80mm&download=1');
        $response80->assertOk();
        $response80->assertHeader('Content-Type', 'application/pdf');
        preg_match_all('/\/Type\s*\/Page\b/', $response80->getContent(), $pages80);
        $this->assertCount(1, $pages80[0], '80mm PDF must fit on a single page with no page-break spillover');
    }
}
