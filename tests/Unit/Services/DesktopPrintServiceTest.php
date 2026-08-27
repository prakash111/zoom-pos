<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Sale;
use App\Services\Printing\DesktopPrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesktopPrintServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_not_desktop_outside_the_nativephp_runtime(): void
    {
        $this->assertFalse(app(DesktopPrintService::class)->isDesktop());
    }

    public function test_available_printers_is_empty_outside_the_nativephp_runtime(): void
    {
        $this->assertSame([], app(DesktopPrintService::class)->availablePrinters());
    }

    public function test_print_calls_are_no_ops_outside_the_nativephp_runtime(): void
    {
        Plan::create([
            'name' => 'trial', 'display_name' => 'Free Trial', 'price' => 0, 'currency' => 'USD',
            'billing_cycle' => 'monthly', 'duration_days' => 14,
            'features' => ['pos' => true], 'limits' => ['products' => 500, 'users' => 5], 'active' => true,
        ]);
        $company = Company::create([
            'name' => 'Print Test Co', 'slug' => 'print-test-co', 'email' => 'owner@printtest.com',
            'country' => 'US', 'currency' => 'USD', 'currency_symbol' => '$', 'document' => 'US-777',
            'plan_name' => 'trial', 'expires_at' => now()->addDays(14),
        ]);
        $sale = Sale::create([
            'company_id' => $company->id, 'sale_number' => 'SALE-P-0001', 'total' => 10, 'net_amount' => 10,
            'status' => 'completed', 'items' => [['name' => 'Item', 'price' => 10, 'quantity' => 1]],
        ]);

        $service = app(DesktopPrintService::class);

        $this->assertFalse($service->printReceipt($sale, 'Any Printer', '80mm'));
        $this->assertFalse($service->printA4Document($sale, 'Any Printer'));
    }
}
