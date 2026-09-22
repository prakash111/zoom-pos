<?php

namespace Tests\Feature\Tenant;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackendPrioritizedReceivablesAndInvoicesSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoices_endpoint_returns_overdue_first_then_due_today_then_future(): void
    {
        $company = Company::create([
            'name' => 'Metro Retail Corp',
            'currency' => 'INR',
            'currency_symbol' => '₹',
        ]);

        $store = Store::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'name' => 'Indiranagar Branch',
            'code' => 'IND-01',
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Store Manager',
            'email' => 'manager@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'current_store_id' => $store->id,
        ]);

        $today = now()->startOfDay();

        // 1. Oldest Overdue (due 10 days ago, total: 200)
        $oldestOverdue = Invoice::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'sale_number' => 'INV-OVERDUE-OLD',
            'total' => 200.00,
            'due_amount' => 200.00,
            'paid_amount' => 0.00,
            'due_date' => (clone $today)->subDays(10)->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
            'operation_type' => 'sale',
        ]);

        // 2. Recent Overdue (due 2 days ago, total: 100)
        $recentOverdue = Invoice::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'sale_number' => 'INV-OVERDUE-RECENT',
            'total' => 100.00,
            'due_amount' => 100.00,
            'paid_amount' => 0.00,
            'due_date' => (clone $today)->subDays(2)->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
            'operation_type' => 'sale',
        ]);

        // 3. Due Today - Smaller Amount (total: 400)
        $dueTodaySmall = Invoice::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'sale_number' => 'INV-TODAY-SMALL',
            'total' => 400.00,
            'due_amount' => 400.00,
            'paid_amount' => 0.00,
            'due_date' => $today->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
            'operation_type' => 'sale',
        ]);

        // 4. Due Today - Larger Amount (total: 900)
        $dueTodayLarge = Invoice::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'sale_number' => 'INV-TODAY-LARGE',
            'total' => 900.00,
            'due_amount' => 900.00,
            'paid_amount' => 0.00,
            'due_date' => $today->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
            'operation_type' => 'sale',
        ]);

        // 5. Future Due (due in 5 days, total: 350)
        $futureDue = Invoice::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'sale_number' => 'INV-FUTURE',
            'total' => 350.00,
            'due_amount' => 350.00,
            'paid_amount' => 0.00,
            'due_date' => (clone $today)->addDays(5)->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
            'operation_type' => 'sale',
        ]);

        // 6. Paid Invoice (should be filtered out)
        Invoice::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'sale_number' => 'INV-PAID',
            'total' => 1500.00,
            'due_amount' => 0.00,
            'paid_amount' => 1500.00,
            'due_date' => (clone $today)->subDays(3)->toDateString(),
            'payment_status' => 'paid',
            'status' => 'completed',
            'operation_type' => 'sale',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tenant/invoices');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(5, $data);

        // Sequence assertion:
        // Position 1: Oldest Overdue (highest priority)
        $this->assertEquals('INV-OVERDUE-OLD', $data[0]['sale_number']);
        // Position 2: Recent Overdue
        $this->assertEquals('INV-OVERDUE-RECENT', $data[1]['sale_number']);
        // Position 3: Due Today - Highest balance first
        $this->assertEquals('INV-TODAY-LARGE', $data[2]['sale_number']);
        // Position 4: Due Today - Next balance
        $this->assertEquals('INV-TODAY-SMALL', $data[3]['sale_number']);
        // Position 5: Future Due
        $this->assertEquals('INV-FUTURE', $data[4]['sale_number']);

        // Meta statistics assertion:
        $this->assertEquals(300.00, (float) $response->json('meta.total_overdue'));
        $this->assertEquals(1300.00, (float) $response->json('meta.total_due_today'));
    }

    public function test_pos_due_receivables_endpoint_returns_prioritized_sorting(): void
    {
        $company = Company::create([
            'name' => 'Metro Retail Corp',
            'currency' => 'INR',
            'currency_symbol' => '₹',
        ]);

        $store = Store::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'name' => 'Indiranagar Branch',
            'code' => 'IND-01',
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Store Cashier',
            'email' => 'cashier@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'current_store_id' => $store->id,
        ]);

        $today = now()->startOfDay();

        // Overdue sale
        Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'sale_number' => 'POS-OVERDUE',
            'total' => 300.00,
            'due_amount' => 300.00,
            'due_date' => (clone $today)->subDays(7)->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
        ]);

        // Sale due today
        Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'sale_number' => 'POS-TODAY',
            'total' => 600.00,
            'due_amount' => 600.00,
            'due_date' => $today->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
        ]);

        // Sale due next week
        Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'sale_number' => 'POS-FUTURE',
            'total' => 450.00,
            'due_amount' => 450.00,
            'due_date' => (clone $today)->addDays(7)->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/pos/receivables/due');

        $response->assertStatus(200);
        $receivables = $response->json('receivables');
        $this->assertCount(3, $receivables);

        $this->assertEquals('POS-OVERDUE', $receivables[0]['sale_number']);
        $this->assertEquals('POS-TODAY', $receivables[1]['sale_number']);
        $this->assertEquals('POS-FUTURE', $receivables[2]['sale_number']);

        $this->assertEquals(300.00, (float) $response->json('meta.total_overdue'));
        $this->assertEquals(600.00, (float) $response->json('meta.total_due_today'));
    }

    public function test_invoices_endpoint_filters_by_store_and_status(): void
    {
        $company = Company::create([
            'name' => 'Metro Retail Corp',
            'currency' => 'INR',
        ]);

        $storeA = Store::create([
            'company_id' => $company->id,
            'name' => 'Branch A',
            'code' => 'A',
            'is_primary' => true,
        ]);

        $storeB = Store::create([
            'company_id' => $company->id,
            'name' => 'Branch B',
            'code' => 'B',
            'is_primary' => false,
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'current_store_id' => $storeA->id,
        ]);

        $today = now()->startOfDay();

        // 2 invoices in Store A (1 overdue, 1 due today)
        Invoice::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $storeA->id,
            'sale_number' => 'INV-STORE-A-OVERDUE',
            'total' => 100.00,
            'due_amount' => 100.00,
            'due_date' => (clone $today)->subDays(1)->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $storeA->id,
            'sale_number' => 'INV-STORE-A-TODAY',
            'total' => 150.00,
            'due_amount' => 150.00,
            'due_date' => $today->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
        ]);

        // 1 invoice in Store B
        Invoice::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $storeB->id,
            'sale_number' => 'INV-STORE-B',
            'total' => 200.00,
            'due_amount' => 200.00,
            'due_date' => (clone $today)->subDays(1)->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
        ]);

        // Default query uses active Store A context (2 invoices in Store A)
        $responseA = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tenant/invoices');

        $responseA->assertStatus(200);
        $dataA = $responseA->json('data');
        $this->assertCount(2, $dataA);

        // Filter by Store B explicitly
        $responseB = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/tenant/invoices?store_id={$storeB->id}");

        $responseB->assertStatus(200);
        $dataB = $responseB->json('data');
        $this->assertCount(1, $dataB);
        $this->assertEquals('INV-STORE-B', $dataB[0]['sale_number']);

        // Filter Store A by status=overdue
        $responseOverdue = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/tenant/invoices?store_id={$storeA->id}&status=overdue&sort=due_date_asc");

        $responseOverdue->assertStatus(200);
        $dataOverdue = $responseOverdue->json('data');
        $this->assertCount(1, $dataOverdue);
        $this->assertEquals('INV-STORE-A-OVERDUE', $dataOverdue[0]['sale_number']);
    }

    public function test_dashboard_receivables_sdui_action_binding(): void
    {
        $company = Company::create([
            'name' => 'Metro Retail Corp',
            'currency' => 'INR',
            'currency_symbol' => '₹',
        ]);

        $store = Store::create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Store Owner',
            'email' => 'owner@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'current_store_id' => $store->id,
        ]);

        $today = now()->startOfDay();

        // 1 Overdue Invoice
        Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'sale_number' => 'INV-DASH-OVERDUE',
            'total' => 500.00,
            'due_amount' => 500.00,
            'due_date' => (clone $today)->subDays(3)->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
        ]);

        // 1 Due Today Invoice
        Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'sale_number' => 'INV-DASH-TODAY',
            'total' => 300.00,
            'due_amount' => 300.00,
            'due_date' => $today->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/pos/dashboard/summary');

        $response->assertStatus(200);

        // Check amount_receivable_card SDUI schema
        $card = $response->json('amount_receivable_card');
        $this->assertNotNull($card);
        $this->assertEquals('receivables_card', $card['type']);
        $this->assertEquals(800.00, (float) $card['total_amount']);

        $rows = $card['rows'];
        $this->assertCount(2, $rows);

        // Row 1: Overdue
        $this->assertEquals('row_overdue', $rows[0]['id']);
        $this->assertEquals('Overdue Amount', $rows[0]['label']);
        $this->assertEquals(500.00, (float) $rows[0]['amount']);
        $this->assertEquals('#EF4444', $rows[0]['color']);
        $this->assertEquals('navigate', $rows[0]['action']['type']);
        $this->assertEquals('/sales/invoices', $rows[0]['action']['route']);
        $this->assertEquals('overdue', $rows[0]['action']['params']['status']);
        $this->assertEquals('due_date_asc', $rows[0]['action']['params']['sort']);

        // Row 2: Due Today
        $this->assertEquals('row_due_today', $rows[1]['id']);
        $this->assertEquals('Due Today', $rows[1]['label']);
        $this->assertEquals(300.00, (float) $rows[1]['amount']);
        $this->assertEquals('#F59E0B', $rows[1]['color']);
        $this->assertEquals('navigate', $rows[1]['action']['type']);
        $this->assertEquals('/sales/invoices', $rows[1]['action']['route']);
        $this->assertEquals('due_today', $rows[1]['action']['params']['status']);
        $this->assertEquals('amount_desc', $rows[1]['action']['params']['sort']);

        // Also check receivables.rows binding
        $receivablesRows = $response->json('receivables.rows');
        $this->assertIsArray($receivablesRows);
        $this->assertEquals('/sales/invoices', $receivablesRows[0]['action']['route']);
        $this->assertEquals('overdue', $receivablesRows[0]['action']['params']['status']);
    }
}
