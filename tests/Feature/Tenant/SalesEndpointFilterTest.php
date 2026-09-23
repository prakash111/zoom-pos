<?php

namespace Tests\Feature\Tenant;

use App\Models\Company;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesEndpointFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_endpoint_filters_by_due_date_ranges_and_custom_dates(): void
    {
        $company = Company::create([
            'name' => 'Metro Retail Corp',
            'currency' => 'INR',
        ]);

        $store = Store::create([
            'company_id' => $company->id,
            'name' => 'Main Store',
            'code' => 'MAIN',
            'is_primary' => true,
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Manager',
            'email' => 'manager@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'current_store_id' => $store->id,
        ]);

        $today = now()->startOfDay();

        // 1. Overdue
        $saleOverdue = Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'sale_number' => 'SALE-OVERDUE',
            'total' => 100.00,
            'due_amount' => 100.00,
            'due_date' => (clone $today)->subDays(3)->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
        ]);
        \Illuminate\Support\Facades\DB::table('sales')->where('id', $saleOverdue->id)->update([
            'created_at' => (clone $today)->subDays(10),
        ]);

        // 2. Due Today
        Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'sale_number' => 'SALE-TODAY',
            'total' => 200.00,
            'due_amount' => 200.00,
            'due_date' => $today->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
            'created_at' => (clone $today)->subDays(2),
        ]);

        // 3. Due in 5 Days (matches due_7_days and due_15_days)
        Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'sale_number' => 'SALE-DUE-5D',
            'total' => 300.00,
            'due_amount' => 300.00,
            'due_date' => (clone $today)->addDays(5)->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
            'created_at' => (clone $today)->subDays(1),
        ]);

        // 4. Due in 12 Days (matches due_15_days only)
        Sale::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'sale_number' => 'SALE-DUE-12D',
            'total' => 400.00,
            'due_amount' => 400.00,
            'due_date' => (clone $today)->addDays(12)->toDateString(),
            'payment_status' => 'pending',
            'status' => 'completed',
            'created_at' => $today,
        ]);

        // 5. Test Filter: overdue (returns all unpaid dues with overdue placed first)
        $resOverdue = $this->actingAs($user, 'sanctum')->getJson('/api/v1/tenant/sales?filter=overdue');
        $resOverdue->assertStatus(200);
        $dataOverdue = $resOverdue->json('data');
        $this->assertCount(4, $dataOverdue);
        $this->assertEquals('SALE-OVERDUE', $dataOverdue[0]['sale_number']);
        $this->assertEquals('SALE-TODAY', $dataOverdue[1]['sale_number']);
        $this->assertEquals('SALE-DUE-5D', $dataOverdue[2]['sale_number']);
        $this->assertEquals('SALE-DUE-12D', $dataOverdue[3]['sale_number']);

        // 6. Test Filter: due_today
        $resToday = $this->actingAs($user, 'sanctum')->getJson('/api/v1/tenant/sales?filter=due_today');
        $resToday->assertStatus(200);
        $dataToday = $resToday->json('data');
        $this->assertCount(1, $dataToday);
        $this->assertEquals('SALE-TODAY', $dataToday[0]['sale_number']);

        // 7. Test Filter: due_7_days
        $res7 = $this->actingAs($user, 'sanctum')->getJson('/api/v1/tenant/sales?filter=due_7_days');
        $res7->assertStatus(200);
        $data7 = $res7->json('data');
        $this->assertCount(2, $data7); // TODAY + DUE-5D

        // 8. Test Filter: due_15_days
        $res15 = $this->actingAs($user, 'sanctum')->getJson('/api/v1/tenant/sales?filter=due_15_days');
        $res15->assertStatus(200);
        $data15 = $res15->json('data');
        $this->assertCount(3, $data15); // TODAY + DUE-5D + DUE-12D

        // 9. Test Filter: custom_date
        $startDate = (clone $today)->subDays(12)->toDateString();
        $endDate = (clone $today)->subDays(5)->toDateString();
        $resCustom = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/tenant/sales?filter=custom_date&start_date={$startDate}&end_date={$endDate}");
        $resCustom->assertStatus(200);
        $dataCustom = $resCustom->json('data');
        $this->assertCount(1, $dataCustom);
        $this->assertEquals('SALE-OVERDUE', $dataCustom[0]['sale_number']);

        // 10. Test Search Query
        $resSearch = $this->actingAs($user, 'sanctum')->getJson('/api/v1/tenant/sales?query=DUE-12D');
        $resSearch->assertStatus(200);
        $this->assertCount(1, $resSearch->json('data'));
        $this->assertEquals('SALE-DUE-12D', $resSearch->json('data.0.sale_number'));
    }
}
