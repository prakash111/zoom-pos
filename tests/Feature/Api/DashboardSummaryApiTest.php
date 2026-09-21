<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'id' => 'comp-summary-test-01',
            'name' => 'Metro Retail Mart',
            'trade_name' => 'MetroRetail',
            'slug' => 'metro-retail-mart',
            'email' => 'metro@retail.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'operating_mode' => 'general',
            'pos_mode' => 'general',
            'expires_at' => now()->addDays(30),
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Alex Johnson',
            'email' => 'alex@retail.com',
            'password' => Hash::make('secret123'),
            'role' => 'Store Manager',
        ]);
    }

    protected function token(): string
    {
        return $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'alex@retail.com',
            'password' => 'secret123',
        ])->assertOk()->json('token');
    }

    public function test_dashboard_summary_returns_contract_structure_and_metrics(): void
    {
        $token = $this->token();

        // Seed some data
        Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Sarah Connor',
            'phone' => '+15551234567',
        ]);

        Product::create([
            'company_id' => $this->company->id,
            'name' => 'Organic Coffee Beans',
            'current_stock' => 3,
            'minimum_stock' => 10,
            'price' => 15.00,
        ]);

        Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'ORD-2026-001',
            'customer_name' => 'Sarah Connor',
            'total' => 120.00,
            'paid_amount' => 100.00,
            'due_amount' => 20.00,
            'status' => 'completed',
            'payment_method' => 'cash',
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/dashboard/summary', [
            'X-Company-ID' => $this->company->id,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'greeting' => ['title', 'subtitle'],
            'status_badges' => ['datetime', 'weather'],
            'top_app_bar' => [
                'store_name',
                'tagline',
                'logo_url',
                'unread_notifications_count',
                'user' => ['name', 'role', 'avatar_url', 'initials'],
            ],
            'metrics' => [
                'total_sales' => ['value', 'formatted', 'trend', 'is_positive', 'sparkline'],
                'total_orders' => ['value', 'formatted', 'trend', 'is_positive', 'sparkline'],
                'total_customers' => ['value', 'formatted', 'trend', 'is_positive', 'sparkline'],
                'low_stock_items' => ['value', 'formatted', 'trend', 'is_positive', 'sparkline'],
            ],
            'sales_overview' => [
                'ranges',
                'current_range',
                'series' => [
                    '*' => ['date', 'label', 'day', 'amount'],
                ],
            ],
            'receivables' => [
                'total_outstanding',
                'formatted',
                'breakdown' => [
                    'overdue_amount',
                    'formatted_overdue',
                    'due_today_amount',
                    'formatted_due_today',
                    'outstanding_invoices_count',
                ],
            ],
            'quick_actions' => [
                '*' => ['key', 'label', 'icon', 'target'],
            ],
            'recent_transactions' => [
                '*' => ['id', 'order_number', 'customer_name', 'customer_initials', 'datetime', 'amount', 'formatted_amount', 'status', 'status_color'],
            ],
        ]);

        $json = $response->json();
        $this->assertEquals('MetroRetail', $json['top_app_bar']['store_name']);
        $this->assertEquals('Alex Johnson', $json['top_app_bar']['user']['name']);
        $this->assertEquals('AJ', $json['top_app_bar']['user']['initials']);
        $this->assertStringContainsString('Alex Johnson', $json['greeting']['title']);
        $this->assertEquals(1, $json['metrics']['total_customers']['value']);
        $this->assertEquals(1, $json['metrics']['low_stock_items']['value']);
        $this->assertEquals(20.0, $json['receivables']['total_outstanding']);
        $this->assertCount(4, $json['quick_actions']);
        $this->assertNotEmpty($json['recent_transactions']);
        $this->assertEquals('ORD-2026-001', $json['recent_transactions'][0]['order_number']);

        // Test this_month range
        $resMonth = $this->withToken($token)->getJson('/api/v1/dashboard/summary?range=this_month');
        $resMonth->assertOk();
        $this->assertEquals('this_month', $resMonth->json('sales_overview.current_range'));
        $this->assertNotEmpty($resMonth->json('sales_overview.series'));

        // Test quarter range
        $resQuarter = $this->withToken($token)->getJson('/api/v1/dashboard/summary?range=quarter');
        $resQuarter->assertOk();
        $this->assertEquals('quarter', $resQuarter->json('sales_overview.current_range'));
        $this->assertNotEmpty($resQuarter->json('sales_overview.series'));
    }
}
