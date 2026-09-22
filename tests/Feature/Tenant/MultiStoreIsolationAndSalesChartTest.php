<?php

namespace Tests\Feature\Tenant;

use App\Models\Company;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiStoreIsolationAndSalesChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_store_isolated_fiscal_and_address_fields(): void
    {
        $company = Company::create([
            'name' => 'Metro Retail Corp',
            'trade_name' => 'Metro Retail',
            'tax_id' => 'GLOBAL-GSTIN-001',
            'phone' => '+919999999999',
            'address' => 'HQ Corporate Tower, MG Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'currency' => 'INR',
            'currency_symbol' => '₹',
        ]);

        $storeA = Store::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'name' => 'Metro Retail Mart - Indiranagar',
            'code' => 'BLR-01',
            'branch_code' => 'BLR-01',
            'phone' => '+918888888881',
            'tax_id' => '29AAAAA0000A1Z5',
            'address_line_1' => '100 Feet Road, HAL 2nd Stage',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560038',
            'receipt_header' => 'Welcome to Indiranagar Branch',
            'receipt_footer' => 'Thank you for shopping at Indiranagar',
            'invoice_prefix' => 'IND-',
        ]);

        $storeB = Store::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'name' => 'Metro Retail Mart - Koramangala',
            'code' => 'BLR-02',
            'branch_code' => 'BLR-02',
            'phone' => '+918888888882',
            'tax_id' => '29BBBBB0000B1Z6',
            'address_line_1' => '80 Feet Road, 4th Block',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560034',
            'receipt_header' => 'Welcome to Koramangala Branch',
            'receipt_footer' => 'Thank you for shopping at Koramangala',
            'invoice_prefix' => 'KOR-',
        ]);

        $storeC = Store::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'name' => 'Metro Retail Mart - Whitefield',
            'code' => 'BLR-03',
            // No phone, tax_id, address specified: should fallback to tenant
        ]);

        // Effective attribute assertions
        $this->assertEquals('29AAAAA0000A1Z5', $storeA->effective_tax_id);
        $this->assertStringContainsString('100 Feet Road', $storeA->effective_address);
        $this->assertStringContainsString('560038', $storeA->effective_address);
        $this->assertEquals('+918888888881', $storeA->effective_phone);

        $this->assertEquals('29BBBBB0000B1Z6', $storeB->effective_tax_id);
        $this->assertStringContainsString('80 Feet Road', $storeB->effective_address);
        $this->assertEquals('+918888888882', $storeB->effective_phone);

        // Fallback assertions for store C
        $this->assertEquals('GLOBAL-GSTIN-001', $storeC->effective_tax_id);
        $this->assertStringContainsString('HQ Corporate Tower', $storeC->effective_address);
        $this->assertEquals('+919999999999', $storeC->effective_phone);

        // Render receipt template for Store A
        $saleA = Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $storeA->id,
            'sale_number' => 'IND-1001',
            'customer_name' => 'Rahul Sharma',
            'total' => 1500,
            'net_amount' => 1500,
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);
        $saleA->setRelation('store', $storeA);

        $htmlA = view('tenant.documents.templates.document', [
            'document' => $saleA,
            'company' => $company,
            'paperFormat' => 'thermal_80mm',
            'type' => 'sale',
            'lines' => [],
            'customer' => null,
            'template' => null,
        ])->render();

        $this->assertStringContainsString('Metro Retail Mart - Indiranagar', $htmlA);
        $this->assertStringContainsString('29AAAAA0000A1Z5', $htmlA);
        $this->assertStringContainsString('100 Feet Road', $htmlA);
        $this->assertStringContainsString('+918888888881', $htmlA);
        $this->assertStringContainsString('Thank you for shopping at Indiranagar', $htmlA);
        $this->assertStringNotContainsString('29BBBBB0000B1Z6', $htmlA);

        // Updating Store B does not affect Store A's receipt output
        $storeB->update([
            'tax_id' => '29ZZZZZ9999Z9Z9',
            'address_line_1' => 'New Koramangala Address',
        ]);

        $freshHtmlA = view('tenant.documents.templates.document', [
            'document' => $saleA,
            'company' => $company,
            'paperFormat' => 'thermal_80mm',
            'type' => 'sale',
            'lines' => [],
            'customer' => null,
            'template' => null,
        ])->render();

        $this->assertStringContainsString('29AAAAA0000A1Z5', $freshHtmlA);
        $this->assertStringNotContainsString('29ZZZZZ9999Z9Z9', $freshHtmlA);
    }

    public function test_dashboard_sales_chart_endpoint_filters_by_store_and_ranges(): void
    {
        $company = Company::create([
            'name' => 'Metro Retail Corp',
            'currency' => 'INR',
            'currency_symbol' => '₹',
        ]);

        $storeA = Store::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'name' => 'Branch Alpha',
            'code' => 'ALPHA',
        ]);

        $storeB = Store::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'name' => 'Branch Beta',
            'code' => 'BETA',
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Store Manager',
            'email' => 'manager@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'current_store_id' => $storeA->id,
        ]);

        // Sales for Store A
        Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $storeA->id,
            'sale_number' => 'SA-01',
            'total' => 500,
            'net_amount' => 500,
            'status' => 'completed',
            'created_at' => now(),
        ]);

        // Sales for Store B
        Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $storeB->id,
            'sale_number' => 'SB-01',
            'total' => 2000,
            'net_amount' => 2000,
            'status' => 'completed',
            'created_at' => now(),
        ]);

        $this->actingAs($user);

        // 1. Chart for last_7_days filtered by user's current_store_id (Store A)
        $responseA = $this->getJson('/api/v1/dashboard/sales-chart?period=last_7_days');
        $responseA->assertOk();
        $responseA->assertJsonPath('success', true);
        $responseA->assertJsonPath('period', 'last_7_days');
        $this->assertEquals(500, (float) $responseA->json('total_sales'));

        // 2. Explicit store_id query param for Store B
        $responseB = $this->getJson('/api/v1/dashboard/sales-chart?period=last_7_days&store_id=' . $storeB->id);
        $responseB->assertOk();
        $this->assertEquals(2000, (float) $responseB->json('total_sales'));

        // 3. Test this_month range
        $responseMonth = $this->getJson('/api/v1/dashboard/sales-chart?period=this_month&store_id=' . $storeA->id);
        $responseMonth->assertOk();
        $responseMonth->assertJsonPath('period', 'this_month');
        $this->assertNotEmpty($responseMonth->json('series'));

        // 4. Test quarter range
        $responseQuarter = $this->getJson('/api/v1/dashboard/sales-chart?period=quarter&store_id=' . $storeA->id);
        $responseQuarter->assertOk();
        $responseQuarter->assertJsonPath('period', 'quarter');
        $this->assertNotEmpty($responseQuarter->json('series'));
    }
}
