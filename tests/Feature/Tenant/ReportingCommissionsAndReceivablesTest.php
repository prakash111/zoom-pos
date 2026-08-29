<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Customers\Index as CustomersIndex;
use App\Livewire\Tenant\Quotes\Create as QuotesCreate;
use App\Livewire\Tenant\Quotes\Show as QuotesShow;
use App\Livewire\Tenant\Reports\Index as ReportsIndex;
use App\Livewire\Tenant\Sales\Create as SalesCreate;
use App\Livewire\Tenant\Sales\Show as SalesShow;
use App\Livewire\Tenant\Settings\Index as SettingsIndex;
use App\Livewire\Tenant\Users\Index as UsersIndex;
use App\Models\Customer;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class ReportingCommissionsAndReceivablesTest extends TestCase
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

    public function test_settings_branding_and_commission_configuration(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        Storage::fake('public');

        $logoFile = UploadedFile::fake()->image('store-logo.png', 300, 100);
        $faviconFile = UploadedFile::fake()->image('favicon.png', 32, 32);

        Livewire::test(SettingsIndex::class)
            ->set('logoFile', $logoFile)
            ->set('faviconFile', $faviconFile)
            ->set('receiptFormat', '58mm')
            ->set('defaultCommissionRate', 7.5)
            ->set('defaultCommissionType', 'percentage')
            ->call('save')
            ->assertHasNoErrors();

        $company->refresh();
        $this->assertNotNull($company->logo);
        $this->assertNotNull($company->favicon);
        $this->assertSame('58mm', $company->receipt_format);
        $this->assertSame(7.5, (float) $company->default_commission_rate);
        $this->assertSame('percentage', $company->default_commission_type);
        $this->assertStringContainsString('storage/', $company->getLogoUrl());
        $this->assertStringContainsString('storage/', $company->getFaviconUrl());
    }

    public function test_user_commission_rate_management(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $salesperson = User::create([
            'company_id' => $company->id,
            'name' => 'Bob Rep',
            'email' => 'bob@example.com',
            'login' => 'bob_rep',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_SALESPERSON,
            'commission_rate' => 5.0,
            'commission_type' => 'percentage',
            'status' => 'approved',
        ]);

        Livewire::test(UsersIndex::class)
            ->call('openCommissionModal', $salesperson->id)
            ->set('editingCommissionRate', 8.5)
            ->set('editingCommissionType', 'percentage')
            ->call('updateCommission')
            ->assertHasNoErrors();

        $salesperson->refresh();
        $this->assertSame(8.5, (float) $salesperson->commission_rate);
    }

    public function test_pos_salesperson_commission_calculation_and_cancellation_reversal(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $rep = User::create([
            'company_id' => $company->id,
            'name' => 'Jane Rep',
            'email' => 'jane@example.com',
            'login' => 'jane_rep',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_SALESPERSON,
            'commission_rate' => 10.0,
            'commission_type' => 'percentage',
            'status' => 'approved',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Test Item',
            'code' => 'SKU-001',
            'sale_price' => 100.0,
            'cost_price' => 50.0,
            'current_stock' => 10,
            'active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Valued Client',
            'email' => 'client@example.com',
        ]);

        // Complete a sale assigned to the sales rep
        Livewire::test(SalesCreate::class)
            ->call('addProductToCart', $product->id)
            ->set('salespersonId', $rep->id)
            ->set('customerId', $customer->id)
            ->set('paymentMethod', 'cash')
            ->call('save')
            ->assertHasNoErrors();

        $sale = Sale::where('company_id', $company->id)->latest('id')->firstOrFail();
        $this->assertSame($rep->id, $sale->user_id);
        $this->assertSame(10.0, (float) $sale->commission_rate);
        $this->assertSame(10.0, (float) $sale->commission_amount); // 10% of $100
        $this->assertSame('completed', $sale->status);

        // Cancel sale -> commission should be reversed to 0
        Livewire::test(SalesShow::class, ['sale' => $sale])
            ->call('cancel')
            ->assertHasNoErrors();

        $sale->refresh();
        $this->assertSame('cancelled', $sale->status);
        $this->assertSame(0.0, (float) $sale->commission_amount);
    }

    public function test_quotation_payment_terms_selection_and_rendering(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Acme Supplies',
            'email' => 'supplies@example.com',
        ]);
        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Consulting Service',
            'sku' => 'SRV-001',
            'price' => 250.0,
            'cost' => 100.0,
            'current_stock' => 100,
        ]);

        Livewire::test(QuotesCreate::class)
            ->set('customerId', $customer->id)
            ->set('paymentTerms', 'Net 30 Days')
            ->set('items', [
                ['product_id' => $product->id, 'name' => $product->name, 'quantity' => 2, 'price' => 250.0, 'subtotal' => 500.0],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $quote = Sale::where('operation_type', 'quotation')->latest('id')->firstOrFail();
        $this->assertSame('Net 30 Days', $quote->payment_terms);

        // Assert terms render in quotation show view
        Livewire::test(QuotesShow::class, ['quote' => $quote])
            ->assertSee('Net 30 Days');

        // Assert terms render in quotation PDF
        $response = $this->actingAs($admin, 'web')->get(route('tenant.quotes.pdf', $quote));
        $response->assertOk();
        $response->assertSee('Net 30 Days');
    }

    public function test_customer_credit_ledger_and_debt_settlement(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'John Corporate',
            'email' => 'john@example.com',
        ]);

        $creditSale = Sale::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'sale_number' => 'INV-CR-001',
            'total' => 300.0,
            'paid_amount' => 0.0,
            'due_amount' => 300.0,
            'payment_method' => 'credit',
            'payment_status' => 'pending',
            'status' => 'completed',
            'due_date' => now()->addDays(15),
        ]);

        $this->assertSame(300.0, $customer->total_due);

        // Open customer ledger and perform partial debt settlement of $120
        Livewire::test(CustomersIndex::class)
            ->call('openCreditLedger', $customer->id)
            ->assertSee('John Corporate')
            ->assertSee('INV-CR-001')
            ->assertSee('$300.00')
            ->call('openSettleModal', $creditSale->id)
            ->set('paymentAmount', 120.0)
            ->set('paymentMethod', 'cash')
            ->call('recordSettlement')
            ->assertHasNoErrors();

        $creditSale->refresh();
        $this->assertSame(120.0, (float) $creditSale->paid_amount);
        $this->assertSame(180.0, (float) $creditSale->due_amount);
        $this->assertSame('partially_paid', $creditSale->payment_status);
        $this->assertSame(180.0, $customer->fresh()->total_due);

        // Settle remaining $180
        Livewire::test(CustomersIndex::class)
            ->call('openCreditLedger', $customer->id)
            ->call('openSettleModal', $creditSale->id)
            ->set('paymentAmount', 180.0)
            ->set('paymentMethod', 'card')
            ->call('recordSettlement')
            ->assertHasNoErrors();

        $creditSale->refresh();
        $this->assertSame(300.0, (float) $creditSale->paid_amount);
        $this->assertSame(0.0, (float) $creditSale->due_amount);
        $this->assertSame('paid', $creditSale->payment_status);
        $this->assertSame(0.0, $customer->fresh()->total_due);
    }

    public function test_reports_subsystem_renders_all_five_tabs_and_exports_csv(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $rep = User::create([
            'company_id' => $company->id,
            'name' => 'Alice Agent',
            'email' => 'alice@example.com',
            'login' => 'alice_agent',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_SALESPERSON,
            'commission_rate' => 5.0,
            'status' => 'approved',
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Acme Inc',
            'email' => 'acme@example.com',
        ]);

        $sale = Sale::create([
            'company_id' => $company->id,
            'user_id' => $rep->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'sale_number' => 'INV-REP-101',
            'total' => 500.0,
            'discount' => 20.0,
            'tax' => 25.0,
            'paid_amount' => 400.0,
            'due_amount' => 100.0,
            'payment_method' => 'card',
            'payment_status' => 'partially_paid',
            'status' => 'completed',
            'commission_rate' => 5.0,
            'commission_amount' => 25.0,
            'items' => [
                ['name' => 'Enterprise Widget', 'quantity' => 5, 'price' => 100.0],
            ],
            'created_at' => now(),
            'due_date' => now()->addDays(10),
        ]);

        OrderPayment::create([
            'company_id' => $company->id,
            'sale_id' => $sale->id,
            'payment_method' => 'card',
            'amount' => 400.0,
            'tendered' => 400.0,
            'change_returned' => 0.0,
        ]);

        // Route accessibility
        $response = $this->actingAs($admin, 'web')->get(route('tenant.reports.index'));
        $response->assertOk();
        // ApexCharts is now lazy-loaded via Vite (resources/js/charts-loader.js)
        // when this DOM marker is present, instead of a route-gated raw <script> tag.
        $response->assertSee('data-apexcharts-dashboard', false);

        // Test Livewire component across all 5 tabs and KPI metrics
        $component = Livewire::test(ReportsIndex::class);

        // 1. KPI Summary Cards Grid
        $component->assertSee('Total Revenue')
            ->assertSee('Transactions Count')
            ->assertSee('Average Order Value (AOV)')
            ->assertSee('$500.00');

        $kpis = $component->get('kpiMetrics');
        $this->assertSame(500.0, (float) $kpis['total_revenue']);
        $this->assertSame(1, (int) $kpis['transactions_count']);
        $this->assertSame(500.0, (float) $kpis['aov']);
        $this->assertSame('Alice Agent', $kpis['top_contributor']['name']);

        // 2. Chart datasets validation
        $salesTrend = $component->get('chartSalesTrend');
        $this->assertNotEmpty($salesTrend['categories']);
        $this->assertContains(500.0, $salesTrend['revenue']);

        $topProductsChart = $component->get('chartTopProducts');
        $this->assertContains('Enterprise Widget', $topProductsChart['categories']);
        $this->assertContains(500.0, $topProductsChart['revenues']);

        $pmChart = $component->get('chartPaymentMethods');
        $this->assertContains('Card', $pmChart['labels']);
        $this->assertContains(400.0, $pmChart['series']);

        // Tab 1: Sales Summary
        $component->call('setTab', 'sales_summary')
            ->assertSee('Enterprise Widget')
            ->assertSee('$500.00');

        // Tab 2: Payment Methods & Volume share meters
        $component->call('setTab', 'payment_methods')
            ->assertSee('card')
            ->assertSee('$400.00')
            ->assertSee('100%');

        // Tab 3: Till Closings
        $component->call('setTab', 'till_closings')
            ->assertSee('Cash Register Shifts & Historical Z-Reports');

        // Tab 4: Staff Commissions & Leaderboard
        $component->call('setTab', 'commissions')
            ->assertSee('Alice Agent')
            ->assertSee('$25.00')
            ->assertSee('Leaderboard Cards');

        // Tab 5: Receivables Aging
        $component->call('setTab', 'aging')
            ->assertSee('Acme Inc')
            ->assertSee('$100.00');

        // Test CSV Export
        $component->call('setTab', 'sales_summary');
        $csvResponse = $component->call('exportCsv');
        $this->assertNotNull($csvResponse);
    }
}
