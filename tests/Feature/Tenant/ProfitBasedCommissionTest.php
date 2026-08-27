<?php

namespace Tests\Feature\Tenant;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitBasedCommissionTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $salesperson;
    protected CommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Store Test',
            'email' => 'sales@store.test',
            'subscription_status' => 'active',
            'pos_mode' => 'general',
        ]);

        $this->salesperson = User::create([
            'company_id' => $this->company->id,
            'name' => 'Bob Sales',
            'email' => 'bob@store.test',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'commission_type' => 'profit_percentage',
            'commission_rate' => 10.00, // 10% on profit
        ]);

        $this->service = app(CommissionService::class);
    }

    public function test_profit_based_commission_computes_from_line_item_margins(): void
    {
        $prod1 = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Gadget A',
            'sale_price' => 100.00,
            'cost_price' => 40.00, // Margin = $60
        ]);

        $prod2 = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Gadget B',
            'sale_price' => 50.00,
            'cost_price' => 30.00, // Margin = $20
        ]);

        $cartItems = [
            [
                'id' => $prod1->id,
                'name' => $prod1->name,
                'price' => 100.00,
                'quantity' => 2, // Profit = $60 * 2 = $120
            ],
            [
                'id' => $prod2->id,
                'name' => $prod2->name,
                'price' => 50.00,
                'quantity' => 1, // Profit = $20 * 1 = $20
            ],
        ];

        // Total Net Profit = $120 + $20 = $140
        // Commission = 10% of $140 = $14.00
        $commission = $this->service->calculateCommission(
            rate: 10.00,
            type: 'profit_percentage',
            saleTotal: 250.00,
            items: $cartItems,
            companyId: $this->company->id
        );

        $this->assertEquals(14.00, $commission);
    }

    public function test_rate_formatting_helper(): void
    {
        $this->assertEquals('10.0% on Profit', $this->service->formatRate(10.00, 'profit_percentage'));
        $this->assertEquals('5.0% on Sale Total', $this->service->formatRate(5.00, 'percentage'));
        $this->assertEquals('$15.00 / sale', $this->service->formatRate(15.00, 'fixed'));
    }
}
