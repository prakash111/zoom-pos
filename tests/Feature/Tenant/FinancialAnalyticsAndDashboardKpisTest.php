<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Dashboard;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\VendorBill;
use App\Services\FinancialAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialAnalyticsAndDashboardKpisTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Acme Analytics Store',
            'trade_name' => 'Acme Analytics',
            'slug' => 'acme-analytics',
            'email' => 'store@analytics.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'operating_mode' => 'general',
            'pos_mode' => 'general',
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'admin',
        ]);
    }

    public function test_financial_analytics_service_calculates_daily_and_monthly_metrics_accurately(): void
    {
        $service = app(FinancialAnalyticsService::class);

        // Create products with cost price and sale price
        $burger = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Cheeseburger',
            'sale_price' => 15.00,
            'cost_price' => 5.00,
            'current_stock' => 100,
            'active' => true,
        ]);

        $drink = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Soda Can',
            'sale_price' => 5.00,
            'cost_price' => 1.00,
            'current_stock' => 100,
            'active' => true,
        ]);

        // 1. Create Sale Today: 2 Burgers ($30) + 1 Soda ($5) = $35 total. COGS = 2*5 + 1*1 = $11.
        Sale::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'sale_number' => 'POS-TODAY-001',
            'status' => 'completed',
            'total' => 35.00,
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $burger->id, 'name' => 'Cheeseburger', 'price' => 15.00, 'cost_price' => 5.00, 'quantity' => 2],
                ['product_id' => $drink->id, 'name' => 'Soda Can', 'price' => 5.00, 'cost_price' => 1.00, 'quantity' => 1],
            ],
            'created_at' => now(),
        ]);

        // 2. Create another Sale Today: 1 Burger ($15). COGS = $5.
        Sale::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'sale_number' => 'POS-TODAY-002',
            'status' => 'completed',
            'total' => 15.00,
            'payment_method' => 'card',
            'items' => [
                ['product_id' => $burger->id, 'name' => 'Cheeseburger', 'price' => 15.00, 'cost_price' => 5.00, 'quantity' => 1],
            ],
            'created_at' => now(),
        ]);

        // 3. Create Operating Expense / Vendor Bill Today = $10.00
        VendorBill::create([
            'company_id' => $this->company->id,
            'title' => 'Fresh Vegetables Supply',
            'vendor_name' => 'Produce Co',
            'bill_number' => 'BILL-001',
            'amount' => 10.00,
            'status' => 'paid',
            'bill_date' => now()->toDateString(),
        ]);

        // Calculation:
        // Daily Revenue = 35 + 15 = 50.00
        // Daily Orders = 2
        // Daily AOV = 50 / 2 = 25.00
        // Daily COGS = 11 + 5 = 16.00
        // Daily Expenses = 10.00
        // Daily Net Profit = 50 - 16 - 10 = 24.00
        // Daily Profit Margin = (24 / 50) * 100 = 48.0%
        // Total items = 3 + 1 = 4 items / 2 orders = 2.0 avg items

        $kpis = $service->getExecutiveDashboardKpis($this->company, forceFresh: true);

        $this->assertEquals(50.00, $kpis['dailyRevenue']);
        $this->assertEquals(2, $kpis['dailyOrdersCount']);
        $this->assertEquals(25.00, $kpis['dailyAov']);
        $this->assertEquals(24.00, $kpis['dailyProfit']);
        $this->assertEquals(48.0, $kpis['dailyProfitMargin']);
        $this->assertEquals(2.0, $kpis['avgItemsPerOrder']);
        $this->assertEquals(50.00, $kpis['monthlyRevenue']);
        $this->assertEquals(24.00, $kpis['monthlyProfit']);
        $this->assertEquals(48.0, $kpis['monthlyProfitMargin']);
    }

    public function test_dashboard_renders_retail_metrics_chart_and_shortcuts(): void
    {
        // Add sample customer and completed sale
        Customer::create([
            'company_id' => $this->company->id,
            'name' => 'John Executive',
            'phone' => '1234567890',
        ]);

        Sale::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'sale_number' => 'POS-DASH-001',
            'status' => 'completed',
            'total' => 120.50,
            'payment_method' => 'cash',
            'items' => [
                ['name' => 'Demo Item', 'price' => 120.50, 'quantity' => 1],
            ],
            'created_at' => now(),
        ]);

        Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->assertSee('Sales Overview')
            ->assertSee('Last 7 Days')
            ->assertSee('Total Sales')
            ->assertSee('Total Orders')
            ->assertSee('Total Customers')
            ->assertSee('Low Stock Items')
            ->assertSee('Amount Receivable')
            ->assertSee('120.50')
            ->assertSee('Create Order')
            ->assertSee('View Reports')
            ->assertSee('Recent Transactions')
            ->assertSee('Choose POS Layout');
    }

    public function test_financial_analytics_cache_invalidation(): void
    {
        $service = app(FinancialAnalyticsService::class);

        // Pre-fill cache
        $kpisBefore = $service->getExecutiveDashboardKpis($this->company);
        $this->assertEquals(0.0, $kpisBefore['dailyRevenue']);

        // Create new sale
        Sale::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'sale_number' => 'POS-CACHE-001',
            'status' => 'completed',
            'total' => 80.00,
            'payment_method' => 'cash',
            'items' => [],
            'created_at' => now(),
        ]);

        // Clear cache
        $service->clearCache($this->company);

        $kpisAfter = $service->getExecutiveDashboardKpis($this->company);
        $this->assertEquals(80.00, $kpisAfter['dailyRevenue']);
    }

    public function test_financial_drill_down_routes_are_accessible(): void
    {
        // 1. Sales Report Shortcut Route
        $salesResponse = $this->actingAs($this->user)
            ->get(route('tenant.reports.sales'));
        $salesResponse->assertStatus(200);

        // 2. Profit & Loss Shortcut Route
        $pnlResponse = $this->actingAs($this->user)
            ->get(route('tenant.reports.profit-loss'));
        $pnlResponse->assertStatus(200);

        // 3. Orders Ledger Shortcut Route
        $ordersResponse = $this->actingAs($this->user)
            ->get(route('tenant.sales.index'));
        $ordersResponse->assertStatus(200);

        // 4. Customer Directory Shortcut Route
        $customersResponse = $this->actingAs($this->user)
            ->get(route('tenant.customers.index'));
        $customersResponse->assertStatus(200);
    }
}
