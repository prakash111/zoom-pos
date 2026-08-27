<?php

namespace Tests\Feature\Tenant;

use App\Livewire\SuperAdmin\Tax\Index;
use App\Livewire\Tenant\Sales\Create;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\TaxRule;
use App\Models\TenantApiKey;
use App\Models\User;
use App\Services\FiscalEInvoicing\IndiaGstEInvoiceDriver;
use App\Services\FiscalEInvoicing\PeppolEInvoiceDriver;
use App\Services\FiscalEInvoicing\ZatcaEInvoiceDriver;
use App\Services\TaxCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GlobalTaxEngineAndApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected TaxCalculationService $taxService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Acme Global Retail',
            'trade_name' => 'Acme Global',
            'slug' => 'acme-global',
            'email' => 'store@acme.com',
            'country' => 'IN',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'tax_id' => '27AABCA1234A1Z5',
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'admin',
        ]);

        $this->taxService = app(TaxCalculationService::class);
    }

    public function test_exclusive_tax_calculation_with_components(): void
    {
        $taxRule = TaxRule::create([
            'company_id' => $this->company->id,
            'tax_name' => 'GST 18%',
            'tax_code' => 'GST_18',
            'rate' => 18.0,
            'is_inclusive' => false,
            'sub_components' => [
                ['name' => 'CGST', 'rate' => 9.0],
                ['name' => 'SGST', 'rate' => 9.0],
            ],
            'is_default' => true,
            'active' => true,
        ]);

        $items = [
            [
                'name' => 'Office Chair',
                'quantity' => 2,
                'price' => 1000.0,
                'tax_rate' => 18.0,
                'is_inclusive' => false,
            ],
        ];

        $res = $this->taxService->calculateCartTotals($items, $this->company);

        $this->assertEquals(2000.0, $res['subtotal']);
        $this->assertEquals(360.0, $res['tax_amount']);
        $this->assertEquals(2360.0, $res['total']);

        $this->assertCount(1, $res['tax_summary_table']);
        $summary = $res['tax_summary_table'][0];
        $this->assertEquals(2000.0, $summary['taxable_amount']);
        $this->assertEquals(360.0, $summary['tax_amount']);
        $this->assertCount(2, $summary['components']);
        $this->assertEquals(180.0, $summary['components'][0]['amount']);
        $this->assertEquals(180.0, $summary['components'][1]['amount']);
    }

    public function test_inclusive_tax_calculation(): void
    {
        $items = [
            [
                'name' => 'Restaurant Buffet',
                'quantity' => 1,
                'price' => 115.0,
                'tax_rate' => 15.0,
                'is_inclusive' => true,
            ],
        ];

        $res = $this->taxService->calculateCartTotals($items, $this->company);

        $this->assertEquals(100.0, $res['subtotal']);
        $this->assertEquals(15.0, $res['tax_amount']);
        $this->assertEquals(115.0, $res['total']);
    }

    public function test_b2b_customer_tax_exemption(): void
    {
        $exemptCustomer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Export Partner Ltd',
            'tax_id' => 'EXP-999',
            'is_tax_exempt' => true,
        ]);

        $items = [
            [
                'name' => 'Industrial Unit',
                'quantity' => 1,
                'price' => 5000.0,
                'tax_rate' => 18.0,
                'is_inclusive' => false,
            ],
        ];

        $res = $this->taxService->calculateCartTotals($items, $this->company, $exemptCustomer);

        $this->assertEquals(5000.0, $res['subtotal']);
        $this->assertEquals(0.0, $res['tax_amount']);
        $this->assertEquals(5000.0, $res['total']);
        $this->assertEmpty($res['tax_summary_table']);
    }

    public function test_multi_jurisdiction_presets_dictionary(): void
    {
        $presets = $this->taxService->getJurisdictionPresets();

        $expectedJurisdictions = ['IN', 'US', 'GB', 'AE', 'SA', 'CA', 'AU', 'EU', 'SG', 'BR', 'MX'];
        foreach ($expectedJurisdictions as $code) {
            $this->assertArrayHasKey($code, $presets);
            $this->assertNotEmpty($presets[$code]['rules']);
            $this->assertGreaterThan(0, $presets[$code]['standard_rate']);
        }
    }

    public function test_e_invoice_drivers_payload_and_qr(): void
    {
        $sale = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'INV-20260824-001',
            'customer_name' => 'Global Buyer Corp',
            'total' => 1180.0,
            'tax_amount' => 180.0,
            'status' => 'completed',
            'payment_method' => 'cash',
            'items' => [
                ['name' => 'Product A', 'quantity' => 1, 'price' => 1000.0, 'tax_rate' => 18.0],
            ],
            'tax_breakdown' => [
                ['tax_name' => 'GST 18%', 'rate' => 18.0, 'taxable_amount' => 1000.0, 'tax_amount' => 180.0],
            ],
        ]);

        // India GST Driver
        $gstDriver = new IndiaGstEInvoiceDriver;
        $gstRes = $gstDriver->submitInvoice($sale);
        $this->assertEquals('cleared', $gstRes['status']);
        $this->assertNotEmpty($gstRes['irn']);
        $this->assertNotEmpty($gstRes['qr_code_data']);

        // ZATCA Saudi Driver
        $saCompany = Company::create([
            'name' => 'Saudi Tech LLC',
            'country' => 'SA',
            'currency' => 'SAR',
            'tax_id' => '300000000000003',
        ]);
        $saSale = Sale::create([
            'company_id' => $saCompany->id,
            'sale_number' => 'SA-001',
            'total' => 115.0,
            'tax_amount' => 15.0,
            'status' => 'completed',
            'payment_method' => 'card',
        ]);
        $zatcaDriver = new ZatcaEInvoiceDriver;
        $zatcaRes = $zatcaDriver->submitInvoice($saSale);
        $this->assertEquals('cleared', $zatcaRes['status']);
        $this->assertNotEmpty($zatcaRes['qr_code_data']);
        $this->assertNotEmpty($zatcaRes['payload']['ubl_xml']);

        // PEPPOL Driver
        $peppolDriver = new PeppolEInvoiceDriver;
        $peppolRes = $peppolDriver->submitInvoice($sale);
        $this->assertEquals('cleared', $peppolRes['status']);
        $this->assertNotEmpty($peppolRes['payload']['ubl_xml']);
    }

    public function test_api_tax_calculate_endpoint(): void
    {
        $apiKey = TenantApiKey::create([
            'company_id' => $this->company->id,
            'name' => 'Shopify Production Key',
            'token' => 'zk_live_'.bin2hex(random_bytes(16)),
            'permissions' => ['tax:calculate'],
            'active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$apiKey->token,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/tax/calculate', [
            'items' => [
                ['name' => 'Custom Widget', 'quantity' => 3, 'price' => 100.0, 'tax_rate' => 10.0, 'is_inclusive' => false],
            ],
            'discount' => 20.0,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'subtotal' => 300.0,
            'discount' => 20.0,
            'tax_amount' => 30.0,
            'total' => 310.0,
        ]);
    }

    public function test_api_tax_invoice_issuance_and_inventory_decrement(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Smart Watch',
            'sku' => 'SW-001',
            'sale_price' => 200.0,
            'current_stock' => 15,
        ]);

        $apiKey = TenantApiKey::create([
            'company_id' => $this->company->id,
            'name' => 'WooCommerce Key',
            'token' => 'zk_live_'.bin2hex(random_bytes(16)),
            'permissions' => ['*'],
            'active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$apiKey->token,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/tax/invoices', [
            'customer' => [
                'name' => 'Alice Cooper',
                'phone' => '+1555123456',
                'email' => 'alice@cooper.com',
            ],
            'items' => [
                ['product_id' => $product->id, 'name' => 'Smart Watch', 'quantity' => 2, 'price' => 200.0, 'tax_rate' => 18.0],
            ],
            'payment_method' => 'stripe',
            'auto_einvoice' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('invoice.total', 472);
        $response->assertJsonPath('invoice.tax_amount', 72);

        // Verify stock decremented
        $product->refresh();
        $this->assertEquals(13, $product->current_stock);
    }

    public function test_superadmin_global_tax_matrix_view(): void
    {
        $superadmin = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'superadmin',
        ]);

        Livewire::actingAs($superadmin)
            ->test(Index::class)
            ->assertSee('Global Tax & E-Invoicing Reference Matrix')
            ->assertSee('11 Sovereign Jurisdictions')
            ->call('selectCountry', 'SA')
            ->assertSee('Saudi Arabia')
            ->assertSee('ZATCA VAT');
    }

    public function test_tenant_settings_tax_rule_crud_and_api_keys(): void
    {
        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Tenant\Settings\Index::class)
            ->call('newTaxRule')
            ->set('taxRuleName', 'State VAT 5%')
            ->set('taxRuleRate', 5.0)
            ->set('taxRuleType', 'percentage')
            ->set('taxRuleIsInclusive', false)
            ->set('taxRuleIsDefault', true)
            ->call('saveTaxRule')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tax_rules', [
            'company_id' => $this->company->id,
            'tax_name' => 'State VAT 5%',
            'is_default' => 1,
        ]);

        // Generate Developer API Key
        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Tenant\Settings\Index::class)
            ->set('newApiKeyName', 'ERP Next Connector')
            ->set('newApiKeyPermissions', ['tax:calculate', 'tax:invoices'])
            ->call('createApiKey')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenant_api_keys', [
            'company_id' => $this->company->id,
            'name' => 'ERP Next Connector',
            'active' => 1,
        ]);
    }

    public function test_tax_propagation_across_pos_thermal_receipt_and_invoice_pdf(): void
    {
        // 1. Configure default store tax rule: GST 18% (Exclusive) with CGST 9% & SGST 9%
        $taxRule = TaxRule::create([
            'company_id' => $this->company->id,
            'tax_name' => 'GST 18% (Exclusive)',
            'tax_code' => 'GST_18',
            'rate' => 18.0,
            'type' => 'percentage',
            'is_inclusive' => false,
            'is_default' => true,
            'country' => 'IN',
            'calc_type' => 'exclusive',
            'sub_components' => [
                ['name' => 'CGST', 'rate' => 9.0],
                ['name' => 'SGST', 'rate' => 9.0],
            ],
            'active' => true,
        ]);

        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Special Combo Meal',
            'sale_price' => 31.50,
            'current_stock' => 50,
            'active' => true,
        ]);

        $cashRegister = CashRegister::create([
            'company_id' => $this->company->id,
            'name' => 'Main POS Register',
            'status' => 'open',
            'opening_cash' => 100.0,
            'opened_at' => now(),
            'user_id' => $this->user->id,
        ]);

        // 2. Add item in POS and verify live calculations & tax components rendering
        $posTest = Livewire::actingAs($this->user)
            ->test(Create::class)
            ->call('addProductToCart', $product->id);

        $posTest->assertSee('CGST (9%)')
            ->assertSee('SGST (9%)');

        $this->assertEquals(31.50, $posTest->get('subtotal'));
        $this->assertEquals(5.67, $posTest->get('taxAmount'));
        $this->assertEquals(37.17, $posTest->get('total'));

        // 3. Complete Sale
        $posTest->call('save')->assertHasNoErrors();

        $sale = Sale::where('company_id', $this->company->id)->latest('id')->firstOrFail();
        $this->assertEquals(37.17, (float) $sale->total);
        $this->assertEquals(31.50, (float) $sale->subtotal);
        $this->assertEquals(5.67, (float) $sale->tax_amount);
        $this->assertNotEmpty($sale->tax_breakdown);

        $flattened = $sale->flattened_tax_components;
        $this->assertCount(2, $flattened);
        $this->assertEquals('CGST', $flattened[0]['name']);
        $this->assertEquals(9.0, $flattened[0]['rate']);
        $this->assertEquals(2.84, round($flattened[0]['amount'], 2));
        $this->assertEquals('SGST', $flattened[1]['name']);
        $this->assertEquals(9.0, $flattened[1]['rate']);
        $this->assertEquals(2.84, round($flattened[1]['amount'], 2));

        // 4. Verify 80mm Thermal Receipt PDF generation renders correctly
        $receiptResponse = $this->actingAs($this->user)
            ->get(route('tenant.sales.pdf', ['sale' => $sale->id, 'format' => '80mm']));
        $receiptResponse->assertStatus(200);

        // 5. Verify Standard A4 Invoice PDF generation
        $invoiceResponse = $this->actingAs($this->user)
            ->get(route('tenant.sales.pdf', ['sale' => $sale->id]));
        $invoiceResponse->assertStatus(200);
    }

    public function test_tenant_settings_multi_country_quick_pre_seeder(): void
    {
        $settingsTest = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Tenant\Settings\Index::class)
            ->assertSee('Pre-Seed Country Tax Rules')
            ->assertSee('India')
            ->assertSee('Saudi Arabia')
            ->assertSee('United Kingdom');

        // Pre-seed India GST
        $settingsTest->call('preSeedTaxRules', 'IN')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tax_rules', [
            'company_id' => $this->company->id,
            'tax_code' => 'GST_18_INTRA',
            'is_default' => 1,
            'country' => 'IN',
        ]);

        // Pre-seed Saudi Arabia ZATCA
        $settingsTest->call('preSeedTaxRules', 'SA')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tax_rules', [
            'company_id' => $this->company->id,
            'tax_code' => 'KSA_VAT_15',
            'is_default' => 1,
            'country' => 'SA',
        ]);

        // Previous India rule should no longer be default
        $this->assertDatabaseHas('tax_rules', [
            'company_id' => $this->company->id,
            'tax_code' => 'GST_18_INTRA',
            'is_default' => 0,
        ]);
    }
}
